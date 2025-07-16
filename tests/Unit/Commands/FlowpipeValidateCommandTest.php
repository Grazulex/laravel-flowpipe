<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Contracts\FlowDefinitionValidatorInterface;
use Grazulex\LaravelFlowpipe\Validation\ValidationResult;

beforeEach(function () {
    $this->validator = $this->createMock(FlowDefinitionValidatorInterface::class);
    $this->app->instance(FlowDefinitionValidatorInterface::class, $this->validator);
});

it('validates all flows when no arguments provided', function () {
    $result = new ValidationResult('test-flow', ['Test error'], []);

    $this->validator->expects($this->once())
        ->method('validateAllFlows')
        ->willReturn(['test-flow' => $result]);

    $this->artisan('flowpipe:validate')
        ->expectsOutput('Validation failed')
        ->assertExitCode(1);
});

it('validates specific flow when path provided', function () {
    $result = new ValidationResult('test-flow', [], []);

    $this->validator->expects($this->once())
        ->method('validateFlow')
        ->with('test-flow.yaml')
        ->willReturn($result);

    $this->artisan('flowpipe:validate --path=test-flow.yaml')
        ->assertExitCode(0);
});

it('outputs json format when requested', function () {
    $result = new ValidationResult('test-flow', ['Test error'], []);

    $this->validator->expects($this->once())
        ->method('validateAllFlows')
        ->willReturn(['test-flow' => $result]);

    $this->artisan('flowpipe:validate --format=json')
        ->expectsOutputToContain('"valid": false')
        ->assertExitCode(1);
});

it('handles file not found error', function () {
    $this->validator->expects($this->once())
        ->method('validateFlow')
        ->with('non-existent.yaml')
        ->willThrowException(new Exception('File not found'));

    $this->artisan('flowpipe:validate --path=non-existent.yaml')
        ->expectsOutput('Error: File not found')
        ->assertExitCode(1);
});

it('shows success message for valid flows', function () {
    $result = new ValidationResult('test-flow', [], []);

    $this->validator->expects($this->once())
        ->method('validateAllFlows')
        ->willReturn(['test-flow' => $result]);

    $this->artisan('flowpipe:validate')
        ->expectsOutput('All flows are valid')
        ->assertExitCode(0);
});
