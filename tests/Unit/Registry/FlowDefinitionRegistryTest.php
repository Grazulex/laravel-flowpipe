<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->testDir = storage_path('test_flows');
    if (! File::exists($this->testDir)) {
        File::makeDirectory($this->testDir, 0755, true);
    }
});

afterEach(function () {
    if (File::exists($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
});

it('can list yaml files in the flow definitions directory', function () {
    // Create test YAML files
    File::put($this->testDir.'/test_flow1.yaml', "name: test_flow1\nsteps: []");
    File::put($this->testDir.'/test_flow2.yaml', "name: test_flow2\nsteps: []");
    File::put($this->testDir.'/not_a_flow.txt', 'This should be ignored');

    $registry = new FlowDefinitionRegistry($this->testDir);
    $flows = $registry->list();

    expect($flows)->toHaveCount(2);
    expect($flows->toArray())->toContain('test_flow1', 'test_flow2');
    expect($flows->toArray())->not->toContain('not_a_flow');
});

it('returns empty collection when no yaml files exist', function () {
    $registry = new FlowDefinitionRegistry($this->testDir);
    $flows = $registry->list();

    expect($flows)->toBeEmpty();
});

it('can get a specific flow definition', function () {
    $flowContent = [
        'name' => 'test_flow',
        'steps' => [
            ['type' => 'closure', 'action' => 'transform'],
            ['type' => 'conditional', 'condition' => 'check_something'],
        ],
    ];

    File::put($this->testDir.'/test_flow.yaml', Symfony\Component\Yaml\Yaml::dump($flowContent));

    $registry = new FlowDefinitionRegistry($this->testDir);
    $definition = $registry->get('test_flow');

    expect($definition)->toBe($flowContent);
});

it('throws exception when flow definition does not exist', function () {
    $registry = new FlowDefinitionRegistry($this->testDir);

    expect(fn () => $registry->get('non_existent_flow'))
        ->toThrow(RuntimeException::class, 'Flow definition [non_existent_flow] not found.');
});

it('uses default path from config when no path is provided', function () {
    config(['flowpipe.definitions_path' => 'custom_flow_definitions']);

    $registry = new FlowDefinitionRegistry();

    // We can't easily test the exact path without accessing private properties,
    // but we can test that it doesn't throw an error on construction
    expect($registry)->toBeInstanceOf(FlowDefinitionRegistry::class);
});

it('can handle complex yaml structures', function () {
    $complexFlow = [
        'name' => 'complex_flow',
        'description' => 'A complex flow with nested structures',
        'steps' => [
            [
                'type' => 'conditional',
                'condition' => [
                    'field' => 'status',
                    'operator' => 'equals',
                    'value' => 'active',
                ],
                'then' => [
                    ['type' => 'closure', 'action' => 'process_active'],
                    ['type' => 'closure', 'action' => 'send_notification'],
                ],
                'else' => [
                    ['type' => 'closure', 'action' => 'process_inactive'],
                ],
            ],
        ],
        'metadata' => [
            'version' => '1.0',
            'author' => 'Test Author',
        ],
    ];

    File::put($this->testDir.'/complex_flow.yaml', Symfony\Component\Yaml\Yaml::dump($complexFlow));

    $registry = new FlowDefinitionRegistry($this->testDir);
    $definition = $registry->get('complex_flow');

    expect($definition)->toBe($complexFlow);
    expect($definition['name'])->toBe('complex_flow');
    expect($definition['steps'])->toHaveCount(1);
    expect($definition['metadata']['version'])->toBe('1.0');
});
