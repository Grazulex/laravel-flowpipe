<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

it('can process a simple pipeline with closure steps', function () {
    $tracer = new TestTracer();

    $result = Flowpipe::make()
        ->send('Hello')
        ->through([
            fn ($payload, $next) => $next($payload.' world'),
            ClosureStep::make(fn ($payload, $next) => $next(mb_strtoupper($payload))),
        ])
        ->withTracer($tracer)
        ->thenReturn();

    expect($result)->toBe('HELLO WORLD');
    expect($tracer->count())->toBe(2);
    expect($tracer->steps())->toContain('Closure', 'Grazulex\LaravelFlowpipe\Steps\ClosureStep');
});
