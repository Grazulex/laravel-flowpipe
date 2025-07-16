<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Grazulex\LaravelFlowpipe\Support\FlowBuilder;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

it('can build flow with groups from YAML', function () {
    $testDir = storage_path('test_yaml_groups');
    File::ensureDirectoryExists($testDir);

    // Create a YAML flow definition with groups
    $yamlContent = [
        'flow' => 'TestGroupFlow',
        'description' => 'Test flow with groups',
        'steps' => [
            [
                'type' => 'group',
                'name' => 'test-group',
            ],
            [
                'type' => 'closure',
                'action' => 'append',
                'value' => ' world',
            ],
        ],
    ];

    File::put($testDir.'/test_group_flow.yaml', Yaml::dump($yamlContent));

    // Define a group first
    Flowpipe::group('test-group', [
        fn ($data, $next) => $next(mb_strtoupper($data)),
        fn ($data, $next) => $next($data.'!'),
    ]);

    // Load from registry and build
    $registry = new FlowDefinitionRegistry($testDir);
    $definition = $registry->get('test_group_flow');

    $builder = new FlowBuilder();
    $flow = $builder->buildFromDefinition($definition);

    $result = $flow->send('hello')->thenReturn();

    expect($result)->toBe('HELLO! world');

    // Clean up
    File::deleteDirectory($testDir);
});

it('can build flow with nested steps from YAML', function () {
    $testDir = storage_path('test_yaml_nested');
    File::ensureDirectoryExists($testDir);

    // Create a YAML flow definition with nested steps
    $yamlContent = [
        'flow' => 'TestNestedFlow',
        'description' => 'Test flow with nested steps',
        'steps' => [
            [
                'type' => 'nested',
                'steps' => [
                    [
                        'type' => 'closure',
                        'action' => 'uppercase',
                    ],
                    [
                        'type' => 'closure',
                        'action' => 'append',
                        'value' => '!',
                    ],
                ],
            ],
            [
                'type' => 'closure',
                'action' => 'append',
                'value' => ' world',
            ],
        ],
    ];

    File::put($testDir.'/test_nested_flow.yaml', Yaml::dump($yamlContent));

    // Load from registry and build
    $registry = new FlowDefinitionRegistry($testDir);
    $definition = $registry->get('test_nested_flow');

    $builder = new FlowBuilder();
    $flow = $builder->buildFromDefinition($definition);

    $result = $flow->send('hello')->thenReturn();

    expect($result)->toBe('HELLO! world');

    // Clean up
    File::deleteDirectory($testDir);
});

it('can load groups from YAML files', function () {
    $testDir = storage_path('test_yaml_group_definitions');
    $groupsDir = $testDir.'/groups';
    File::ensureDirectoryExists($groupsDir);

    // Create a group definition YAML file
    $groupDefinition = [
        'group' => 'yaml-test-group',
        'description' => 'Test group defined in YAML',
        'steps' => [
            [
                'type' => 'closure',
                'action' => 'uppercase',
            ],
            [
                'type' => 'closure',
                'action' => 'append',
                'value' => '!',
            ],
        ],
    ];

    File::put($groupsDir.'/yaml-test-group.yaml', Yaml::dump($groupDefinition));

    // Load groups from registry
    $registry = new FlowDefinitionRegistry($testDir);
    $registry->loadGroups();

    // Test that the group was loaded
    expect(Flowpipe::hasGroup('yaml-test-group'))->toBeTrue();

    // Use the group
    $result = Flowpipe::make()
        ->send('hello')
        ->useGroup('yaml-test-group')
        ->thenReturn();

    expect($result)->toBe('HELLO!');

    // Clean up
    File::deleteDirectory($testDir);
});

it('can list groups from YAML files', function () {
    $testDir = storage_path('test_yaml_group_listing');
    $groupsDir = $testDir.'/groups';
    File::ensureDirectoryExists($groupsDir);

    // Create multiple group definition YAML files
    $group1 = [
        'group' => 'group1',
        'steps' => [
            ['type' => 'closure', 'action' => 'uppercase'],
        ],
    ];

    $group2 = [
        'group' => 'group2',
        'steps' => [
            ['type' => 'closure', 'action' => 'lowercase'],
        ],
    ];

    File::put($groupsDir.'/group1.yaml', Yaml::dump($group1));
    File::put($groupsDir.'/group2.yaml', Yaml::dump($group2));

    // List groups from registry
    $registry = new FlowDefinitionRegistry($testDir);
    $groups = $registry->listGroups();

    expect($groups->toArray())->toContain('group1', 'group2');
    expect($groups)->toHaveCount(2);

    // Clean up
    File::deleteDirectory($testDir);
});
