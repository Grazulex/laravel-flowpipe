<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Illuminate\Console\Command;

final class FlowpipeListCommand extends Command
{
    protected $signature = 'flowpipe:list';

    protected $description = 'List all available flow definitions';

    public function handle(): void
    {
        $registry = new FlowDefinitionRegistry();
        $flows = $registry->list();

        if ($flows->isEmpty()) {
            $this->info('No flow definitions found.');
            $this->line('');
            $this->line('Create a flow definition in the <comment>'.config('flowpipe.definitions_path', 'flow_definitions').'</comment> directory.');

            return;
        }

        $this->info('Available flow definitions:');
        $this->line('');

        $flows->each(function (string $flow): void {
            $this->line("  • <info>{$flow}</info>");
        });

        $this->line('');
        $this->line('Run a flow with: <comment>php artisan flowpipe:run {flow-name}</comment>');
    }
}
