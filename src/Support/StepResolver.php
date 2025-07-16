<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Steps\GroupStep;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StepResolver
{
    /**
     * Resolve a given step into a FlowStep instance or Closure.
     */
    public static function resolve(mixed $step): FlowStep|Closure
    {
        // Step is already an instance or closure
        if ($step instanceof FlowStep || $step instanceof Closure) {
            return $step;
        }

        // Step is a string → check if it's a group first
        if (is_string($step)) {
            // Check if it's a registered group
            if (FlowGroupRegistry::has($step)) {
                return new GroupStep($step);
            }

            // Otherwise, resolve as a class
            $class = class_exists($step)
                ? $step
                : config('flowpipe.step_namespace', 'App\\Flowpipe\\Steps').'\\'.Str::studly($step);

            if (! class_exists($class)) {
                throw new InvalidArgumentException("Step class [$class] does not exist and no group named [$step] found.");
            }

            $instance = app($class);

            if (! $instance instanceof FlowStep) {
                throw new InvalidArgumentException("Resolved class [$class] must implement FlowStep.");
            }

            return $instance;
        }

        // Step is invalid
        throw new InvalidArgumentException('Invalid step type: '.gettype($step));
    }
}
