<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Support\FlowBuilder;

it('can build a simple flow from definition', function () {
    $definition = [
        'name' => 'simple_flow',
        'steps' => [
            ['type' => 'closure', 'action' => 'uppercase'],
            ['type' => 'closure', 'action' => 'trim'],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    expect($flowpipe)->toBeInstanceOf(Flowpipe::class);

    $result = $flowpipe->send(' hello world ')->thenReturn();
    expect($result)->toBe('HELLO WORLD');
});

it('can build a flow with closure actions', function () {
    $definition = [
        'name' => 'text_flow',
        'steps' => [
            ['type' => 'closure', 'action' => 'append', 'value' => ' world'],
            ['type' => 'closure', 'action' => 'uppercase'],
            ['type' => 'closure', 'action' => 'prepend', 'value' => 'Say: '],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->send('hello')->thenReturn();
    expect($result)->toBe('Say: HELLO WORLD');
});

it('can build a flow with conditional steps', function () {
    $definition = [
        'name' => 'conditional_flow',
        'steps' => [
            [
                'type' => 'conditional',
                'condition' => 'is_string',
                'then' => [
                    ['type' => 'closure', 'action' => 'uppercase'],
                ],
                'else' => [
                    ['type' => 'closure', 'action' => 'append', 'value' => ' (not a string)'],
                ],
            ],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->send('hello')->thenReturn();
    expect($result)->toBe('HELLO');

    $result2 = $flowpipe->send(123)->thenReturn();
    expect($result2)->toBe('123 (not a string)');
});

it('can build conditions with field operators', function () {
    $definition = [
        'name' => 'field_condition_flow',
        'steps' => [
            [
                'type' => 'conditional',
                'condition' => [
                    'field' => 'status',
                    'operator' => 'equals',
                    'value' => 'active',
                ],
                'then' => [
                    ['type' => 'closure', 'action' => 'append', 'value' => ' - processed'],
                ],
            ],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->send(['status' => 'active', 'name' => 'test'])->thenReturn();
    expect($result)->toEqual(['status' => 'active', 'name' => 'test - processed']);
});

it('throws exception for missing steps', function () {
    $definition = ['name' => 'invalid_flow'];

    $builder = new FlowBuilder();

    expect(fn () => $builder->buildFromDefinition($definition))
        ->toThrow(RuntimeException::class, 'Flow definition must contain a "steps" array');
});

it('throws exception for unknown step type', function () {
    $definition = [
        'name' => 'invalid_flow',
        'steps' => [
            ['type' => 'unknown_type'],
        ],
    ];

    $builder = new FlowBuilder();

    expect(fn () => $builder->buildFromDefinition($definition))
        ->toThrow(RuntimeException::class, 'Unknown step type: unknown_type');
});

it('throws exception for missing closure action', function () {
    $definition = [
        'name' => 'invalid_flow',
        'steps' => [
            ['type' => 'closure'],
        ],
    ];

    $builder = new FlowBuilder();

    expect(fn () => $builder->buildFromDefinition($definition))
        ->toThrow(RuntimeException::class, 'Closure step must have an "action" field');
});
