<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Grazulex\LaravelFlowpipe\Support\FlowBuilder;
use Illuminate\Support\Facades\File;

it('can execute a complete yaml flow from file', function () {
    $testDir = storage_path('test_yaml_flows');
    File::ensureDirectoryExists($testDir);

    $yamlContent = [
        'name' => 'integration_test',
        'description' => 'Integration test flow',
        'steps' => [
            ['type' => 'closure', 'action' => 'trim'],
            ['type' => 'closure', 'action' => 'uppercase'],
            [
                'type' => 'conditional',
                'condition' => 'is_not_empty',
                'then' => [
                    ['type' => 'closure', 'action' => 'prepend', 'value' => 'Result: '],
                ],
            ],
        ],
    ];

    File::put($testDir.'/integration_test.yaml', Symfony\Component\Yaml\Yaml::dump($yamlContent));

    // Load from registry
    $registry = new FlowDefinitionRegistry($testDir);
    $definition = $registry->get('integration_test');

    // Build and execute flow
    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->send(' hello world ')->thenReturn();

    expect($result)->toBe('Result: HELLO WORLD');

    // Cleanup
    File::deleteDirectory($testDir);
});

it('can handle complex nested conditions from yaml', function () {
    $testDir = storage_path('test_yaml_flows');
    File::ensureDirectoryExists($testDir);

    $yamlContent = [
        'name' => 'complex_test',
        'steps' => [
            [
                'type' => 'conditional',
                'condition' => [
                    'field' => 'type',
                    'operator' => 'equals',
                    'value' => 'user',
                ],
                'then' => [
                    [
                        'type' => 'conditional',
                        'condition' => [
                            'field' => 'active',
                            'operator' => 'equals',
                            'value' => true,
                        ],
                        'then' => [
                            ['type' => 'closure', 'action' => 'append', 'value' => ' - active user'],
                        ],
                        'else' => [
                            ['type' => 'closure', 'action' => 'append', 'value' => ' - inactive user'],
                        ],
                    ],
                ],
                'else' => [
                    ['type' => 'closure', 'action' => 'append', 'value' => ' - not a user'],
                ],
            ],
        ],
    ];

    File::put($testDir.'/complex_test.yaml', Symfony\Component\Yaml\Yaml::dump($yamlContent));

    $registry = new FlowDefinitionRegistry($testDir);
    $definition = $registry->get('complex_test');

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result1 = $flowpipe->send(['type' => 'user', 'active' => true, 'name' => 'John'])->thenReturn();
    expect($result1)->toEqual(['type' => 'user', 'active' => true, 'name' => 'John - active user']);

    $result2 = $flowpipe->send(['type' => 'admin', 'name' => 'Admin'])->thenReturn();
    expect($result2)->toEqual(['type' => 'admin', 'name' => 'Admin - not a user']);

    // Cleanup
    File::deleteDirectory($testDir);
});
