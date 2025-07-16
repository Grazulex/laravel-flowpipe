<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Contracts;

use Grazulex\LaravelFlowpipe\Validation\ValidationResult;

interface FlowDefinitionValidatorInterface
{
    /**
     * Validate a specific flow by name
     */
    public function validateFlow(string $flowName, ?string $path = null): ValidationResult;

    /**
     * Validate a flow definition array
     */
    public function validateFlowDefinition(array $definition): ValidationResult;

    /**
     * Validate all flows in a directory
     */
    public function validateAllFlows(?string $path = null): array;

    /**
     * Validate YAML syntax of a file
     */
    public function validateYamlSyntax(string $filePath): array;
}
