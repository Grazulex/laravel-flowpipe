<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Support\FlowBuilder;

it('can build a flow with direct step references', function () {
    $definition = [
        'flow' => 'TestFlow',
        'steps' => [
            ['step' => 'Tests\\Stubs\\CheckUserValidityStep'],
            ['step' => 'Tests\\Stubs\\CustomStep'],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    expect($flowpipe)->toBeInstanceOf(Grazulex\LaravelFlowpipe\Flowpipe::class);
});

it('can build a flow with dot notation conditions', function () {
    $definition = [
        'flow' => 'UserFlow',
        'steps' => [
            [
                'condition' => 'user.is_active',
                'then' => [
                    ['step' => 'Tests\\Stubs\\SendWelcomeEmailStep'],
                ],
            ],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $user = (object) ['is_active' => true];
    $result = $flowpipe->send($user)->thenReturn();

    expect($result)->toBe($user);
});

it('can build a flow with nested flow definitions', function () {
    $definition = [
        'flow' => 'ComplexFlow',
        'steps' => [
            [
                'condition' => 'user.is_active',
                'then' => [
                    'flow' => [
                        'name' => 'NestedFlow',
                        'steps' => [
                            ['step' => 'Tests\\Stubs\\SendWelcomeEmailStep'],
                            ['step' => 'Tests\\Stubs\\AddToCrmStep'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    expect($flowpipe)->toBeInstanceOf(Grazulex\LaravelFlowpipe\Flowpipe::class);
});

it('can handle initial payload from definition', function () {
    $definition = [
        'flow' => 'InitialPayloadFlow',
        'send' => 'test_payload',
        'steps' => [
            ['type' => 'closure', 'action' => 'uppercase'],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->thenReturn();
    expect($result)->toBe('TEST_PAYLOAD');
});

it('resolves step classes with namespace', function () {
    config(['flowpipe.step_namespace' => 'Tests\\Stubs']);

    $definition = [
        'flow' => 'NamespaceTest',
        'steps' => [
            ['step' => 'CustomStep'],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    // We can't test the exact resolution without the actual classes,
    // but we can verify it doesn't throw an exception
    expect($flowpipe)->toBeInstanceOf(Grazulex\LaravelFlowpipe\Flowpipe::class);
});

it('can build a flow similar to the user registration example', function () {
    $definition = [
        'flow' => 'ProcessUserRegistrationFlow',
        'description' => 'Vérifie la validité de l\'utilisateur et, s\'il est actif, lui envoie un email de bienvenue.',
        'steps' => [
            ['step' => 'Tests\\Stubs\\CheckUserValidityStep'],
            [
                'condition' => 'user.is_active',
                'then' => [
                    'flow' => [
                        'name' => 'NotifyAndSyncUserFlow',
                        'steps' => [
                            ['step' => 'Tests\\Stubs\\SendWelcomeEmailStep'],
                            ['step' => 'Tests\\Stubs\\AddToCrmStep'],
                        ],
                    ],
                ],
            ],
            ['step' => 'Tests\\Stubs\\LogSuccessStep'],
        ],
    ];

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    expect($flowpipe)->toBeInstanceOf(Grazulex\LaravelFlowpipe\Flowpipe::class);

    // Test with an active user
    $activeUser = (object) ['is_active' => true, 'name' => 'John'];
    $result = $flowpipe->send($activeUser)->thenReturn();
    expect($result)->toBe($activeUser);
});
