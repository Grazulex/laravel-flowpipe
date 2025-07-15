<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;

it('outputs trace information when tracing is enabled', function () {
    config(['flowpipe.tracing.enabled' => true]);
    
    $tracer = new BasicTracer();
    
    ob_start();
    $tracer->trace(ClosureStep::class, 'before', 'after', 15.5);
    $output = ob_get_clean();
    
    expect($output)
        ->toContain('[TRACE] ClosureStep | Δ15.50ms')
        ->toContain('Payload before: \'before\'')
        ->toContain('Payload after:  \'after\'');
});

it('does not output trace information when tracing is disabled', function () {
    config(['flowpipe.tracing.enabled' => false]);
    
    $tracer = new BasicTracer();
    
    ob_start();
    $tracer->trace(ClosureStep::class, 'before', 'after', 15.5);
    $output = ob_get_clean();
    
    expect($output)->toBe('');
});

it('handles null duration in trace output', function () {
    config(['flowpipe.tracing.enabled' => true]);
    
    $tracer = new BasicTracer();
    
    ob_start();
    $tracer->trace(ClosureStep::class, 'test', 'test', null);
    $output = ob_get_clean();
    
    expect($output)->toContain('Δ0.00ms');
});
