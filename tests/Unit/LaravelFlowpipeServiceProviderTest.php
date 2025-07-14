<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\LaravelFlowpipeServiceProvider;

beforeEach(function () {
    $this->provider = new LaravelFlowpipeServiceProvider($this->app);
});

it('registers the service provider', function () {
    $this->provider->register();
    // Service provider registration should complete without errors
    $this->assertTrue(true);
});

it('bootstraps the service provider', function () {
    $this->provider->boot();
    // Check if the boot method runs without errors
    $this->assertTrue(true);
});
