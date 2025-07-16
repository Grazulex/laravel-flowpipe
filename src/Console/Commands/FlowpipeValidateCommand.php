<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowDefinitionValidatorInterface;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

final class FlowpipeValidateCommand extends Command
{
    protected $signature = 'flowpipe:validate
                            {--path= : Path to specific flow definition file}
                            {--all : Validate all flow definitions}
                            {--format=table : Output format (table, json)}';

    protected $description = 'Validate flow definition files';

    private FlowDefinitionValidatorInterface $validator;

    public function __construct(FlowDefinitionValidatorInterface $validator)
    {
        parent::__construct();
        $this->validator = $validator;
    }

    public function handle(): int
    {
        try {
            $path = $this->option('path');
            $format = $this->option('format');

            if ($path) {
                return $this->validateSingleFlow($path, $format);
            }

            return $this->validateAllFlows($format);

        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return 1;
        }
    }

    private function validateSingleFlow(string $path, string $format): int
    {
        $result = $this->validator->validateFlow($path);

        if ($format === 'json') {
            $this->outputJsonResults([$path => $result]);
        } else {
            $this->outputTableResults([$path => $result]);
        }

        return $result->isValid() ? 0 : 1;
    }

    private function validateAllFlows(string $format): int
    {
        $results = $this->validator->validateAllFlows();

        if ($format === 'json') {
            $this->outputJsonResults($results);
        } else {
            $this->outputTableResults($results);
        }

        $hasErrors = false;
        foreach ($results as $result) {
            if (! $result->isValid()) {
                $hasErrors = true;
                break;
            }
        }

        if ($hasErrors) {
            $this->error('Validation failed');
        } else {
            $this->info('All flows are valid');
        }

        return $hasErrors ? 1 : 0;
    }

    private function outputTableResults(array $results): void
    {
        $table = new Table($this->output);
        $table->setHeaders(['Flow', 'Status', 'Errors', 'Warnings']);

        foreach ($results as $flowName => $result) {
            $status = $result->isValid() ? '<fg=green>Valid</fg=green>' : '<fg=red>Invalid</fg=red>';
            $table->addRow([
                $flowName,
                $status,
                $result->getErrorCount(),
                $result->getWarningCount(),
            ]);
        }

        $table->render();

        // Show detailed errors and warnings
        foreach ($results as $flowName => $result) {
            if (! $result->isValid()) {
                $this->line('');
                $this->line("<fg=red>Errors in $flowName:</fg=red>");
                foreach ($result->errors as $error) {
                    $this->line("  - $error");
                }
            }

            if ($result->hasWarnings()) {
                $this->line('');
                $this->line("<fg=yellow>Warnings in $flowName:</fg=yellow>");
                foreach ($result->warnings as $warning) {
                    $this->line("  - $warning");
                }
            }
        }
    }

    private function outputJsonResults(array $results): void
    {
        $output = [
            'valid' => true,
            'flows' => [],
            'summary' => [
                'total' => count($results),
                'valid' => 0,
                'invalid' => 0,
                'errors' => 0,
                'warnings' => 0,
            ],
        ];

        foreach ($results as $flowName => $result) {
            $flowData = [
                'name' => $flowName,
                'valid' => $result->isValid(),
                'errors' => $result->errors,
                'warnings' => $result->warnings,
                'error_count' => $result->getErrorCount(),
                'warning_count' => $result->getWarningCount(),
            ];

            $output['flows'][] = $flowData;

            if ($result->isValid()) {
                $output['summary']['valid']++;
            } else {
                $output['summary']['invalid']++;
                $output['valid'] = false;
            }

            $output['summary']['errors'] += $result->getErrorCount();
            $output['summary']['warnings'] += $result->getWarningCount();
        }

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }
}
