<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use RuntimeException;

final class FlowBuilder
{
    public function buildFromDefinition(array $definition): Flowpipe
    {
        if (! isset($definition['steps'])) {
            throw new RuntimeException('Flow definition must contain a "steps" array');
        }

        $steps = [];
        foreach ($definition['steps'] as $stepDefinition) {
            $steps[] = $this->buildStep($stepDefinition);
        }

        $flowpipe = Flowpipe::make()->through($steps);

        // Handle initial payload if specified
        if (isset($definition['send'])) {
            $initialPayload = $this->resolveInitialPayload($definition['send']);
            $flowpipe->send($initialPayload);
        }

        return $flowpipe;
    }

    public function buildStep(array $stepDefinition): mixed
    {
        // Handle direct step reference (step: ClassName)
        if (isset($stepDefinition['step'])) {
            return $this->buildStepFromClass($stepDefinition['step']);
        }

        // Handle condition with nested flow
        if (isset($stepDefinition['condition'])) {
            return $this->buildConditionalStep($stepDefinition);
        }

        // Handle legacy type-based steps
        $type = $stepDefinition['type'] ?? null;

        if (! $type) {
            throw new RuntimeException('Step definition must contain either "step", "condition", or "type" field');
        }

        return match ($type) {
            'closure' => $this->buildClosureStep($stepDefinition),
            'conditional' => $this->buildConditionalStep($stepDefinition),
            'class' => $this->buildClassStep($stepDefinition),
            'step' => $this->buildClassStep($stepDefinition),
            'group' => $this->buildGroupStep($stepDefinition),
            'nested' => $this->buildNestedStep($stepDefinition),
            default => throw new RuntimeException("Unknown step type: {$type}")
        };
    }

    private function appendValueStatic(mixed $payload, string $value): mixed
    {
        if (is_string($payload)) {
            return $payload.$value;
        }

        if (is_array($payload)) {
            // For arrays, we'll append to a specific field or the whole structure
            if (isset($payload['name'])) {
                $payload['name'] .= $value;
            }

            return $payload;
        }

        return $payload.$value;
    }

    private function prependValueStatic(mixed $payload, string $value): mixed
    {
        if (is_string($payload)) {
            return $value.$payload;
        }

        if (is_array($payload)) {
            // For arrays, we'll prepend to a specific field or the whole structure
            if (isset($payload['name'])) {
                $payload['name'] = $value.$payload['name'];
            }

            return $payload;
        }

        return $value.$payload;
    }

    private function buildClosureStep(array $stepDefinition): Closure
    {
        $action = $stepDefinition['action'] ?? null;

        if (! $action) {
            throw new RuntimeException('Closure step must have an "action" field');
        }

        // For now, we'll create simple closures based on action names
        $value = $stepDefinition['value'] ?? '';

        return match ($action) {
            'uppercase' => fn ($payload, $next) => $next(is_string($payload) ? mb_strtoupper($payload) : $payload),
            'lowercase' => fn ($payload, $next) => $next(is_string($payload) ? mb_strtolower($payload) : $payload),
            'trim' => fn ($payload, $next) => $next(is_string($payload) ? mb_trim($payload) : $payload),
            'reverse' => fn ($payload, $next) => $next(is_string($payload) ? strrev($payload) : $payload),
            'append' => fn ($payload, $next) => $next($this->appendValueStatic($payload, $value)),
            'prepend' => fn ($payload, $next) => $next($this->prependValueStatic($payload, $value)),
            default => throw new RuntimeException("Unknown closure action: {$action}")
        };
    }

    private function buildConditionalStep(array $stepDefinition): ConditionalStep
    {
        $condition = $stepDefinition['condition'] ?? null;
        $then = $stepDefinition['then'] ?? null;
        $else = $stepDefinition['else'] ?? null;

        if (! $condition) {
            throw new RuntimeException('Conditional step must have a "condition" field');
        }

        $conditionObject = $this->buildConditionObject($condition);

        if ($then && $else) {
            // When we have both then and else, we need to create a custom step
            return $this->createThenElseStep($conditionObject, $then, $else);
        }

        if ($then) {
            $thenSteps = $this->buildStepsFromDefinition($then);
            $compositeStep = $this->createCompositeStep($thenSteps);

            return ConditionalStep::when($conditionObject, $compositeStep);
        }

        if ($else) {
            $elseSteps = $this->buildStepsFromDefinition($else);
            $compositeStep = $this->createCompositeStep($elseSteps);

            return ConditionalStep::unless($conditionObject, $compositeStep);
        }

        throw new RuntimeException('Conditional step must have either "then" or "else" field');
    }

    private function buildStepsFromDefinition(array $definition): array
    {
        // Handle nested flow definition
        if (isset($definition['flow'])) {
            $flowDefinition = $definition['flow'];
            if (isset($flowDefinition['steps'])) {
                $steps = [];
                foreach ($flowDefinition['steps'] as $stepDef) {
                    $steps[] = $this->buildStep($stepDef);
                }

                return $steps;
            }
        }

        // Handle array of steps (indexed array)
        if (isset($definition[0])) {
            $steps = [];
            foreach ($definition as $stepDef) {
                $steps[] = $this->buildStep($stepDef);
            }

            return $steps;
        }

        // Handle single step wrapped in array
        return [$this->buildStep($definition)];
    }

    private function buildGroupStep(array $stepDefinition): string
    {
        $name = $stepDefinition['name'] ?? null;

        if (! $name) {
            throw new RuntimeException('Group step must have a "name" field');
        }

        return $name; // This will be resolved by StepResolver as a group
    }

    private function buildNestedStep(array $stepDefinition): \Grazulex\LaravelFlowpipe\Steps\NestedFlowStep
    {
        $steps = $stepDefinition['steps'] ?? null;

        if (! $steps) {
            throw new RuntimeException('Nested step must have a "steps" field');
        }

        $nestedSteps = [];
        foreach ($steps as $step) {
            $nestedSteps[] = $this->buildStep($step);
        }

        return new \Grazulex\LaravelFlowpipe\Steps\NestedFlowStep($nestedSteps);
    }

    private function buildClassStep(array $stepDefinition): string
    {
        $class = $stepDefinition['class'] ?? $stepDefinition['step'] ?? null;

        if (! $class) {
            throw new RuntimeException('Class step must have a "class" or "step" field');
        }

        return $this->buildStepFromClass($class);
    }

    private function buildConditionObject(array|string $condition): \Grazulex\LaravelFlowpipe\Contracts\Condition
    {
        $conditionClosure = $this->buildCondition($condition);

        return new class($conditionClosure) implements \Grazulex\LaravelFlowpipe\Contracts\Condition
        {
            public function __construct(private Closure $closure) {}

            public function evaluate(mixed $payload): bool
            {
                return ($this->closure)($payload);
            }
        };
    }

    private function createCompositeStep(array $steps): \Grazulex\LaravelFlowpipe\Contracts\FlowStep
    {
        // Resolve all steps to ensure they are proper instances
        $resolvedSteps = array_map([StepResolver::class, 'resolve'], $steps);

        return new class($resolvedSteps) implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep
        {
            public function __construct(private array $steps) {}

            public function handle(mixed $payload, Closure $next): mixed
            {
                // Create a mini-pipeline for the composite steps
                $pipeline = array_reduce(
                    array_reverse($this->steps),
                    function (Closure $carry, $step): Closure {
                        return function (mixed $payload) use ($step, $carry) {
                            return $step instanceof Closure
                                ? $step($payload, $carry)
                                : $step->handle($payload, $carry);
                        };
                    },
                    fn ($finalPayload): mixed => $finalPayload
                );

                return $next($pipeline($payload));
            }
        };
    }

    private function buildCondition(array|string $condition): Closure
    {
        if (is_string($condition)) {
            // Handle dot notation conditions (e.g., "user.is_active") or simple field access (e.g., "is_active")
            if (str_contains($condition, '.')) {
                return fn ($payload): bool => (bool) data_get($payload, $condition);
            }

            // Handle predefined conditions
            return match ($condition) {
                'always_true' => fn ($payload): true => true,
                'always_false' => fn ($payload): false => false,
                'is_string' => fn ($payload): bool => is_string($payload),
                'is_numeric' => fn ($payload): bool => is_numeric($payload),
                'is_array' => fn ($payload): bool => is_array($payload),
                'is_empty' => fn ($payload): bool => empty($payload),
                'is_not_empty' => fn ($payload): bool => ! empty($payload),
                default => fn ($payload): bool => (bool) data_get($payload, $condition) // Treat as field access
            };
        }

        // $condition is array at this point
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (! $field || ! $operator) {
            throw new RuntimeException('Array condition must have "field" and "operator" fields');
        }

        return match ($operator) {
            'equals' => fn ($payload): bool => data_get($payload, $field) === $value,
            'not_equals' => fn ($payload): bool => data_get($payload, $field) !== $value,
            'greater_than' => fn ($payload): bool => data_get($payload, $field) > $value,
            'less_than' => fn ($payload): bool => data_get($payload, $field) < $value,
            'contains' => fn ($payload): bool => str_contains(data_get($payload, $field), $value),
            'starts_with' => fn ($payload): bool => str_starts_with(data_get($payload, $field), $value),
            'ends_with' => fn ($payload): bool => str_ends_with(data_get($payload, $field), $value),
            default => throw new RuntimeException("Unknown condition operator: {$operator}")
        };
    }

    private function createThenElseStep(\Grazulex\LaravelFlowpipe\Contracts\Condition $condition, array $thenSteps, array $elseSteps): ConditionalStep
    {
        $thenComposite = $this->createCompositeStep($this->buildStepsFromDefinition($thenSteps));
        $elseComposite = $this->createCompositeStep($this->buildStepsFromDefinition($elseSteps));

        $combinedStep = new class($condition, $thenComposite, $elseComposite) implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep
        {
            public function __construct(
                private \Grazulex\LaravelFlowpipe\Contracts\Condition $condition,
                private \Grazulex\LaravelFlowpipe\Contracts\FlowStep $thenStep,
                private \Grazulex\LaravelFlowpipe\Contracts\FlowStep $elseStep
            ) {}

            public function handle(mixed $payload, Closure $next): mixed
            {
                if ($this->condition->evaluate($payload)) {
                    return $this->thenStep->handle($payload, $next);
                }

                return $this->elseStep->handle($payload, $next);

            }
        };

        // We need to wrap this in a ConditionalStep, so we'll use a dummy condition that always returns true
        $alwaysTrue = new class implements \Grazulex\LaravelFlowpipe\Contracts\Condition
        {
            public function evaluate(mixed $payload): bool
            {
                return true;
            }
        };

        return ConditionalStep::when($alwaysTrue, $combinedStep);
    }

    private function resolveInitialPayload(string $payloadClass): mixed
    {
        // Try to decode as JSON first
        if (str_starts_with($payloadClass, '{') || str_starts_with($payloadClass, '[')) {
            $decoded = json_decode($payloadClass, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Try to resolve as a class
        if (class_exists($payloadClass)) {
            return new $payloadClass();
        }

        // Handle simple values or return as string
        return $payloadClass;
    }

    private function buildStepFromClass(string $stepClass): string
    {
        // Simply return the class name as-is
        // The StepResolver will handle namespace resolution and class loading
        return $stepClass;
    }
}
