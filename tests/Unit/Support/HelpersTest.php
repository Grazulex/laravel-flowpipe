<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Steps\ClosureStep;
use Grazulex\LaravelFlowpipe\Support\Helpers;

it('can detect if value is a closure', function () {
    expect(Helpers::isClosure(fn () => 'test'))->toBeTrue();
    expect(Helpers::isClosure(function () {
        return 'test';
    }))->toBeTrue();
    expect(Helpers::isClosure('string'))->toBeFalse();
    expect(Helpers::isClosure(123))->toBeFalse();
    expect(Helpers::isClosure(null))->toBeFalse();
});

it('can detect if value is a flow step', function () {
    $step = ClosureStep::make(fn ($payload, $next) => $next($payload));

    expect(Helpers::isFlowStep($step))->toBeTrue();
    expect(Helpers::isFlowStep('string'))->toBeFalse();
    expect(Helpers::isFlowStep(123))->toBeFalse();
    expect(Helpers::isFlowStep(null))->toBeFalse();
});

it('can get current time in milliseconds', function () {
    $now = Helpers::nowInMs();

    expect($now)->toBeFloat();
    expect($now)->toBeGreaterThan(0);

    // Should be close to current time
    expect($now)->toBeGreaterThan(microtime(true) * 1000 - 100);
});

it('can calculate duration in milliseconds', function () {
    $start = microtime(true);
    usleep(1000); // Sleep 1ms
    $end = microtime(true);

    $duration = Helpers::durationMs($start, $end);

    expect($duration)->toBeFloat();
    expect($duration)->toBeGreaterThan(0);
    expect($duration)->toBeLessThan(10); // Should be less than 10ms
});

it('can calculate duration from start to now', function () {
    $start = microtime(true);
    usleep(1000); // Sleep 1ms

    $duration = Helpers::durationMs($start);

    expect($duration)->toBeFloat();
    expect($duration)->toBeGreaterThan(0);
});

it('can extract short class name from string', function () {
    expect(Helpers::shortClassName('App\\Models\\User'))->toBe('User');
    expect(Helpers::shortClassName('Grazulex\\LaravelFlowpipe\\Steps\\ClosureStep'))->toBe('ClosureStep');
    expect(Helpers::shortClassName('SimpleClass'))->toBe('SimpleClass');
});

it('can extract short class name from object', function () {
    $step = ClosureStep::make(fn ($payload, $next) => $next($payload));

    expect(Helpers::shortClassName($step))->toBe('ClosureStep');
});
