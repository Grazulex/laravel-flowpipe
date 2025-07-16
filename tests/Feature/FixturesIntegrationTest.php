<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Grazulex\LaravelFlowpipe\Support\FlowBuilder;

it('can execute the modern user flow fixture', function () {
    $fixturesPath = __DIR__.'/../fixtures/flows';

    $registry = new FlowDefinitionRegistry($fixturesPath);
    $definition = $registry->get('modern_user_flow');

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->thenReturn();

    // The result should be the initial payload processed through all steps
    expect($result)->toHaveKey('name', 'John Doe');
    expect($result)->toHaveKey('email', 'john@example.com');
    expect($result)->toHaveKey('is_active', true);
    expect($result)->toHaveKey('age', 25);
});

it('can execute the hybrid processing flow fixture', function () {
    $fixturesPath = __DIR__.'/../fixtures/flows';

    $registry = new FlowDefinitionRegistry($fixturesPath);
    $definition = $registry->get('hybrid_processing_flow');

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->thenReturn();

    // The result should contain the processed user data
    expect($result)->toHaveKey('user');
    expect($result)->toHaveKey('action', 'registration');
    expect($result['user'])->toHaveKey('name', 'Alice');
    expect($result['user'])->toHaveKey('role', 'admin');
});

it('can execute the updated text processor fixture', function () {
    $fixturesPath = __DIR__.'/../fixtures/flows';

    $registry = new FlowDefinitionRegistry($fixturesPath);
    $definition = $registry->get('text_processor');

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    $result = $flowpipe->send('  hello world  ')->thenReturn();

    expect($result)->toBe('Processed: HELLO WORLD');
});

it('can execute the updated user validation fixture', function () {
    $fixturesPath = __DIR__.'/../fixtures/flows';

    $registry = new FlowDefinitionRegistry($fixturesPath);
    $definition = $registry->get('user_validation');

    $builder = new FlowBuilder();
    $flowpipe = $builder->buildFromDefinition($definition);

    // Test with valid adult user
    $adultUser = ['email' => 'adult@example.com', 'age' => 25, 'name' => 'John'];
    $result = $flowpipe->send($adultUser)->thenReturn();
    expect($result['name'])->toBe('John - Valid adult user');

    // Test with valid minor user
    $minorUser = ['email' => 'minor@example.com', 'age' => 16, 'name' => 'Jane'];
    $result2 = $flowpipe->send($minorUser)->thenReturn();
    expect($result2['name'])->toBe('Jane - Valid minor user');

    // Test with invalid email
    $invalidUser = ['email' => 'invalid-email', 'age' => 25, 'name' => 'Bob'];
    $result3 = $flowpipe->send($invalidUser)->thenReturn();
    expect($result3['name'])->toBe('Bob - Invalid email');
});
