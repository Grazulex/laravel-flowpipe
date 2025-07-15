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

it('returns empty arrays and null when no traces exist', function () {
    $tracer = new TestTracer();

    expect($tracer->count())->toBe(0);
    expect($tracer->all())->toBeEmpty();
    expect($tracer->steps())->toBeEmpty();
    expect($tracer->firstStep())->toBeNull();
    expect($tracer->lastStep())->toBeNull();
});

it('can get the last step', function () {
    $tracer = new TestTracer();

    $tracer->trace('Step1', 'before1', 'after1', 10.0);
    $tracer->trace('Step2', 'before2', 'after2', 20.0);
    $tracer->trace('Step3', 'before3', 'after3', 30.0);

    expect($tracer->lastStep())->toBe('Step3');
    expect($tracer->firstStep())->toBe('Step1');
});

it('can get all trace details', function () {
    $tracer = new TestTracer();

    $tracer->trace('TestStep', 'input', 'output', 15.5);

    $all = $tracer->all();
    expect($all)->toHaveCount(1);
    expect($all[0])->toMatchArray([
        'step' => 'TestStep',
        'before' => 'input',
        'after' => 'output',
        'duration' => 15.5,
    ]);
});

it('can clear all traces', function () {
    $tracer = new TestTracer();

    $tracer->trace('Step1', 'before', 'after', 10.0);
    $tracer->trace('Step2', 'before', 'after', 20.0);

    expect($tracer->count())->toBe(2);

    $tracer->clear();

    expect($tracer->count())->toBe(0);
    expect($tracer->all())->toBeEmpty();
    expect($tracer->firstStep())->toBeNull();
    expect($tracer->lastStep())->toBeNull();
});
