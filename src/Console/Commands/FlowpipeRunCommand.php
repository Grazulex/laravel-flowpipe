<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Grazulex\LaravelFlowpipe\Support\FlowBuilder;
use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;
use Illuminate\Console\Command;
use RuntimeException;

final class FlowpipeRunCommand extends Command
{
    protected $signature = 'flowpipe:run {flow : The name of the flow to run} {--payload= : Initial payload (JSON string)}';

    protected $description = 'Run a flow definition from YAML';

    public function handle(): void
    {
        $flowName = $this->argument('flow');
        $payloadJson = $this->option('payload');

        try {
            $registry = new FlowDefinitionRegistry();
            $definition = $registry->get($flowName);

            $this->info("Running flow: <comment>{$flowName}</comment>");

            // Parse payload if provided
            $payload = null;
            if ($payloadJson) {
                $payload = json_decode($payloadJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Invalid JSON payload: '.json_last_error_msg());
                }
            }

            // Build and run the flow
            $flowBuilder = new FlowBuilder();
            $flowpipe = $flowBuilder->buildFromDefinition($definition);

            if ($payload !== null) {
                $flowpipe->send($payload);
            }

            $result = $flowpipe->withTracer(new BasicTracer())->thenReturn();

            $this->line('');
            $this->info('Flow completed successfully!');
            $this->line('Result: '.json_encode($result, JSON_PRETTY_PRINT));

        } catch (RuntimeException $e) {
            $this->error("Error: {$e->getMessage()}");
        }
    }
}
