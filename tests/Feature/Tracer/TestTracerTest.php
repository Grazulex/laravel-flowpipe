<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

it('traces all executed steps in order', function () {
    $tracer = new TestTracer();

    $result = Flowpipe::make($tracer)
        ->send('X')
        ->through([
            ClosureStep::make(fn ($p, $n) => $n($p.'1')),
            ClosureStep::make(fn ($p, $n) => $n($p.'2')),
        ])
        ->thenReturn();

    expect($result)->toBe('X12');
    expect($tracer->count())->toBe(2);
    expect($tracer->steps())->toHaveCount(2);
    expect($tracer->steps())->toMatchArray([
        'Grazulex\\LaravelFlowpipe\\Steps\\ClosureStep',
        'Grazulex\\LaravelFlowpipe\\Steps\\ClosureStep',
    ]);
    expect($tracer->firstStep())->toBe('Grazulex\\LaravelFlowpipe\\Steps\\ClosureStep');
});
