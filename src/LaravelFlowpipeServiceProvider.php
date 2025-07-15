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
            __DIR__.'/Config/flowpipe.php' => config_path('dto.php'),
        ], 'flowpipe-config');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/flowpipe.php', 'flowpipe');
        $this->commands([
            Console\Commands\FlowpipeMakeStepCommand::class,
        ]);
    }
}
