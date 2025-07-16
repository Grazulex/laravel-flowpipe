<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Exception;
use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Illuminate\Console\Command;

final class FlowpipeListCommand extends Command
{
    protected $signature = 'flowpipe:list {--detailed : Show detailed information about each flow}';

    protected $description = 'List all available flow definitions';

    public function handle(): void
    {
        $registry = new FlowDefinitionRegistry();
        $flows = $registry->list();

        if ($flows->isEmpty()) {
            $this->info('No flow definitions found.');
            $this->line('');
            $this->line('Create a flow definition in the <comment>'.config('flowpipe.definitions_path', 'flow_definitions').'</comment> directory.');
            $this->line('Use: <comment>php artisan flowpipe:make-flow {name}</comment> to create a new flow.');

            return;
        }

        $this->info('Available flow definitions:');
        $this->line('');

        if ($this->option('detailed')) {
            $this->displayDetailedList($registry, $flows);
        } else {
            $this->displaySimpleList($registry, $flows);
        }

        $this->line('');
        $this->line('Run a flow with: <comment>php artisan flowpipe:run {flow-name}</comment>');
        $this->line('Show detailed info: <comment>php artisan flowpipe:list --detailed</comment>');
    }

    private function displaySimpleList(FlowDefinitionRegistry $registry, \Illuminate\Support\Collection $flows): void
    {
        $flows->each(function (string $flow) use ($registry): void {
            try {
                $definition = $registry->get($flow);
                $description = $this->normalizeDescription($definition['description'] ?? 'No description');
                $stepCount = $this->countSteps($definition['steps'] ?? []);

                $this->line("  <info>• {$flow}</info>");
                $this->line("    <comment>{$description}</comment>");
                $this->line("    <fg=gray>Steps: {$stepCount}</fg=gray>");
                $this->line('');
            } catch (Exception $e) {
                $this->line("  <info>• {$flow}</info>");
                $this->line('    <error>Error loading flow definition</error>');
                $this->line('');
            }
        });
    }

    private function displayDetailedList(FlowDefinitionRegistry $registry, \Illuminate\Support\Collection $flows): void
    {
        $flows->each(function (string $flow) use ($registry): void {
            try {
                $definition = $registry->get($flow);
                $description = $this->normalizeDescription($definition['description'] ?? 'No description');
                $stepCount = $this->countSteps($definition['steps'] ?? []);
                $stepTypes = $this->getStepTypes($definition['steps'] ?? []);
                $hasConditions = $this->hasConditions($definition['steps'] ?? []);
                $hasPayload = isset($definition['send']);

                $this->line("  <info>• {$flow}</info>");
                $this->line("    <comment>{$description}</comment>");
                $this->line("    <fg=gray>Steps:</fg=gray> {$stepCount}");
                $this->line('    <fg=gray>Types:</fg=gray> '.implode(', ', $stepTypes));
                $this->line('    <fg=gray>Features:</fg=gray> '.$this->formatFeatures($hasConditions, $hasPayload));
                $this->line('');
            } catch (Exception $e) {
                $this->line("  <info>• {$flow}</info>");
                $this->line('    <error>Error loading flow definition</error>');
                $this->line('');
            }
        });
    }

    private function countSteps(array $steps): int
    {
        $count = 0;

        foreach ($steps as $step) {
            $count++;

            // Count nested steps in conditions
            if (isset($step['then'])) {
                $count += $this->countSteps($step['then']);
            }
            if (isset($step['else'])) {
                $count += $this->countSteps($step['else']);
            }
        }

        return $count;
    }

    private function getStepTypes(array $steps): array
    {
        $types = [];

        foreach ($steps as $step) {
            if (isset($step['type'])) {
                $types[] = $step['type'];
            } elseif (isset($step['step'])) {
                $types[] = 'custom';
            } elseif (isset($step['condition'])) {
                $types[] = 'condition';

                // Check nested steps
                if (isset($step['then'])) {
                    $types = array_merge($types, $this->getStepTypes($step['then']));
                }
                if (isset($step['else'])) {
                    $types = array_merge($types, $this->getStepTypes($step['else']));
                }
            }
        }

        return array_unique($types);
    }

    private function hasConditions(array $steps): bool
    {
        foreach ($steps as $step) {
            if (isset($step['condition'])) {
                return true;
            }

            // Check nested steps
            if (isset($step['then']) && $this->hasConditions($step['then'])) {
                return true;
            }
            if (isset($step['else']) && $this->hasConditions($step['else'])) {
                return true;
            }
        }

        return false;
    }

    private function formatFeatures(bool $hasConditions, bool $hasPayload): string
    {
        $features = [];

        if ($hasConditions) {
            $features[] = '<fg=green>Conditions</fg=green>';
        }

        if ($hasPayload) {
            $features[] = '<fg=blue>Payload</fg=blue>';
        }

        if ($features === []) {
            $features[] = '<fg=gray>None</fg=gray>';
        }

        return implode(', ', $features);
    }

    private function normalizeDescription(string $description): string
    {
        // Remplace les retours à la ligne multiples par des espaces
        $description = preg_replace('/\s+/', ' ', $description);

        // Supprime les espaces en début et fin
        $description = mb_trim($description);

        // Limite la longueur si nécessaire (optionnel)
        if (mb_strlen($description) > 100) {
            return mb_substr($description, 0, 97).'...';
        }

        return $description;
    }
}
