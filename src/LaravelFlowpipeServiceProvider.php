<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe;

use Illuminate\Support\ServiceProvider;

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
}
