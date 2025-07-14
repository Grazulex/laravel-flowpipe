<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;

final class PayloadIsUppercase implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_string($payload) && mb_strtoupper($payload) === $payload;
    }
}

it('runs the step only when condition is true', function () {
    $result = Flowpipe::make()
        ->send('HELLO')
        ->through([
            ConditionalStep::when(
                new PayloadIsUppercase(),
                ClosureStep::make(fn ($p, $n) => $n($p.' WORLD'))
            ),
        ])
        ->thenReturn();

    expect($result)->toBe('HELLO WORLD');
});

it('skips the step when condition is false', function () {
    $result = Flowpipe::make()
        ->send('hello')
        ->through([
            ConditionalStep::when(
                new PayloadIsUppercase(),
                ClosureStep::make(fn ($p, $n) => $n($p.' WORLD'))
            ),
        ])
        ->thenReturn();

    expect($result)->toBe('hello');
});

it('runs the step with unless when condition is false', function () {
    $result = Flowpipe::make()
        ->send('hello')
        ->through([
            ConditionalStep::unless(
                new PayloadIsUppercase(),
                ClosureStep::make(fn ($p, $n) => $n(mb_strtoupper($p)))
            ),
        ])
        ->thenReturn();

    expect($result)->toBe('HELLO');
});
