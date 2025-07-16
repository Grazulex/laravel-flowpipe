<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe;

use Illuminate\Support\ServiceProvider;
use Throwable;

final class LaravelFlowpipeServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Config/flowpipe.php' => config_path('flowpipe.php'),
        ], 'flowpipe-config');

        // Auto-load groups if enabled
        if (config('flowpipe.groups.enabled', true) && config('flowpipe.groups.auto_register', true)) {
            $this->loadGroups();
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/flowpipe.php', 'flowpipe');
        $this->commands([
            Console\Commands\FlowpipeListCommand::class,
            Console\Commands\FlowpipeMakeStepCommand::class,
            Console\Commands\FlowpipeRunCommand::class,
            Console\Commands\FlowpipeExportCommand::class,
            Console\Commands\FlowpipeMakeFlowCommand::class,
        ]);
    }

    /**
     * Load group definitions from YAML files
     */
    private function loadGroups(): void
    {
        try {
            $registry = new Registry\FlowDefinitionRegistry();
            $registry->loadGroups();
        } catch (Throwable $e) {
            // Silently fail if groups can't be loaded during boot
            // This prevents the application from crashing if group files are missing
        }
    }
}
