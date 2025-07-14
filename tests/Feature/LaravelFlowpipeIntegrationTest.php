<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\LaravelFlowpipeServiceProvider;

it('loads the Laravel Flowpipe service provider successfully', function () {
    // Test that the service provider is loaded in the application
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(LaravelFlowpipeServiceProvider::class);
});

it('can resolve services from the container', function () {
    // Test that basic Laravel services are available
    expect($this->app)->toBeInstanceOf(Illuminate\Foundation\Application::class);
    expect($this->app->get('config'))->toBeInstanceOf(Illuminate\Config\Repository::class);
});

it('has the correct application environment for testing', function () {
    // Ensure we're in testing environment
    expect($this->app->environment())->toBe('testing');
});

it('can access Laravel core services', function () {
    // Test that we can access core Laravel services
    expect($this->app->get('events'))->toBeInstanceOf(Illuminate\Events\Dispatcher::class);
    expect($this->app->get('log'))->toBeInstanceOf(Illuminate\Log\LogManager::class);
});
