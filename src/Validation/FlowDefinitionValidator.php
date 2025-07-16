<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Validation;

use Grazulex\LaravelFlowpipe\Contracts\FlowDefinitionValidatorInterface;
use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Grazulex\LaravelFlowpipe\Support\FlowGroupRegistry;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class FlowDefinitionValidator implements FlowDefinitionValidatorInterface
{
    private array $supportedStepTypes = [
        'action',
        'closure',
        'step',
        'condition',
        'group',
        'nested',
    ];

    private array $supportedOperators = [
        'equals',
        'contains',
        'greater_than',
        'less_than',
        'in',
    ];

    public function validateFlow(string $flowName, ?string $path = null): ValidationResult
    {
        $errors = [];
        $warnings = [];

        try {
            $registry = new FlowDefinitionRegistry($path);
            $definition = $registry->get($flowName);

            return $this->validateFlowDefinition($definition);
        } catch (Throwable $e) {
            $errors[] = "Failed to load flow definition: {$e->getMessage()}";
        }

        return new ValidationResult($flowName, $errors, $warnings);
    }

    public function validateFlowDefinition(array $definition): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $flowName = $definition['flow'] ?? 'unknown';

        $structureErrors = $this->validateStructure($definition);
        $errors = array_merge($errors, $structureErrors);

        $stepErrors = $this->validateSteps($definition['steps'] ?? []);
        $errors = array_merge($errors, $stepErrors);

        $referenceErrors = $this->validateReferences($definition['steps'] ?? [], $warnings);
        $errors = array_merge($errors, $referenceErrors);

        return new ValidationResult($flowName, $errors, $warnings);
    }

    public function validateAllFlows(?string $path = null): array
    {
        $registry = new FlowDefinitionRegistry($path);
        $flows = $registry->list();
        $results = [];

        foreach ($flows as $flowName) {
            try {
                $definition = $registry->get($flowName);
                $results[$flowName] = $this->validateFlowDefinition($definition);
            } catch (Throwable $e) {
                $results[$flowName] = new ValidationResult($flowName, ["Failed to load flow definition: {$e->getMessage()}"], []);
            }
        }

        return $results;
    }

    public function validateYamlSyntax(string $filePath): array
    {
        $errors = [];

        if (! file_exists($filePath)) {
            return ["File does not exist: {$filePath}"];
        }

        try {
            $content = file_get_contents($filePath);
            Yaml::parse($content);
        } catch (Throwable $e) {
            $errors[] = "YAML syntax error: {$e->getMessage()}";
        }

        return $errors;
    }

    private function validateStructure(array $definition): array
    {
        $errors = [];

        if (! isset($definition['flow']) || empty($definition['flow'])) {
            $errors[] = "Missing required field: 'flow'";
        }

        if (! isset($definition['steps']) || ! is_array($definition['steps'])) {
            $errors[] = "Missing or invalid 'steps' array";
        }

        if (isset($definition['flow']) && in_array(preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/', $definition['flow']), [0, false], true)) {
            $errors[] = "Invalid flow name format: '{$definition['flow']}'";
        }

        return $errors;
    }

    private function validateSteps(array $steps): array
    {
        $errors = [];

        foreach ($steps as $index => $step) {
            $stepErrors = $this->validateStep($step, $index);
            $errors = array_merge($errors, $stepErrors);
        }

        return $errors;
    }

    private function validateStep(array $step, int $index): array
    {
        $errors = [];
        $stepNum = $index + 1;

        if (! isset($step['type'])) {
            $errors[] = "Step {$stepNum}: Missing 'type' field";

            return $errors;
        }

        if (! in_array($step['type'], $this->supportedStepTypes)) {
            $errors[] = "Step {$stepNum}: Unsupported step type '{$step['type']}'";
        }

        switch ($step['type']) {
            case 'closure':
                if (! isset($step['action'])) {
                    $errors[] = "Step {$stepNum}: Closure step missing 'action' field";
                }
                break;

            case 'step':
                if (! isset($step['class'])) {
                    $errors[] = "Step {$stepNum}: Step type missing 'class' field";
                }
                break;

            case 'condition':
                $conditionErrors = $this->validateCondition($step, $stepNum);
                $errors = array_merge($errors, $conditionErrors);
                break;

            case 'group':
                if (! isset($step['name'])) {
                    $errors[] = "Step {$stepNum}: Group step missing 'name' field";
                }
                break;

            case 'nested':
                if (! isset($step['steps']) || ! is_array($step['steps'])) {
                    $errors[] = "Step {$stepNum}: Nested step missing 'steps' array";
                } else {
                    $nestedErrors = $this->validateSteps($step['steps']);
                    $errors = array_merge($errors, $nestedErrors);
                }
                break;
        }

        return $errors;
    }

    private function validateCondition(array $step, int $stepNum): array
    {
        $errors = [];

        if (! isset($step['condition'])) {
            $errors[] = "Step {$stepNum}: Condition step missing 'condition' field";

            return $errors;
        }

        $condition = $step['condition'];

        if (! is_array($condition)) {
            $errors[] = "Step {$stepNum}: Condition must be an array";

            return $errors;
        }

        if (! isset($condition['field'])) {
            $errors[] = "Step {$stepNum}: Condition missing 'field'";
        }

        if (! isset($condition['operator'])) {
            $errors[] = "Step {$stepNum}: Condition missing 'operator'";
        } elseif (! in_array($condition['operator'], $this->supportedOperators)) {
            $errors[] = "Step {$stepNum}: Unsupported operator '{$condition['operator']}'";
        }

        if (! isset($condition['value'])) {
            $errors[] = "Step {$stepNum}: Condition missing 'value'";
        }

        if (! isset($step['step'])) {
            $errors[] = "Step {$stepNum}: Condition step missing 'step' to execute";
        }

        return $errors;
    }

    private function validateReferences(array $steps, array &$warnings = []): array
    {
        $errors = [];

        // First, collect all groups defined in this flow
        $definedGroups = $this->collectDefinedGroups($steps);

        foreach ($steps as $index => $step) {
            $stepNum = $index + 1;

            switch ($step['type'] ?? '') {
                case 'group':
                    // Check if group exists in registry or is defined in this flow
                    if (isset($step['name']) && (! FlowGroupRegistry::has($step['name']) && ! in_array($step['name'], $definedGroups))) {
                        $warnings[] = "Step {$stepNum}: Group '{$step['name']}' not found in registry or flow definition";
                    }
                    break;

                case 'step':
                case 'action':
                    if (isset($step['class'])) {
                        if (! class_exists($step['class'])) {
                            $errors[] = "Step {$stepNum}: Class '{$step['class']}' not found";
                        } elseif (! $this->implementsFlowStep($step['class'])) {
                            $errors[] = "Step {$stepNum}: Class '{$step['class']}' does not implement FlowStep interface";
                        }
                    }
                    break;

                case 'nested':
                    if (isset($step['steps'])) {
                        $nestedErrors = $this->validateReferences($step['steps'], $warnings);
                        $errors = array_merge($errors, $nestedErrors);
                    }
                    break;
            }
        }

        return $errors;
    }

    private function collectDefinedGroups(array $steps): array
    {
        $groups = [];

        foreach ($steps as $step) {
            // Si c'est un groupe avec une définition complète
            if (($step['type'] ?? '') === 'group' && isset($step['name']) && isset($step['steps'])) {
                $groups[] = $step['name'];
            }

            // Recherche récursive dans les nested steps
            if (($step['type'] ?? '') === 'nested' && isset($step['steps'])) {
                $nestedGroups = $this->collectDefinedGroups($step['steps']);
                $groups = array_merge($groups, $nestedGroups);
            }
        }

        return array_unique($groups);
    }

    private function implementsFlowStep(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);

            return $reflection->implementsInterface(\Grazulex\LaravelFlowpipe\Contracts\FlowStep::class);
        } catch (Throwable) {
            return false;
        }
    }
}
