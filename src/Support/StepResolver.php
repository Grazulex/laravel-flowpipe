<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StepResolver
{
    /**
     * Resolve a given step into a FlowStep instance or Closure.
     */
    public static function resolve(FlowStep|Closure|string $step): FlowStep|Closure
    {
        // Already a usable instance
        if ($step instanceof Closure || $step instanceof FlowStep) {
            return $step;
        }

        // Try resolving by class name or configured namespace
        if (is_string($step)) {
            $class = class_exists($step)
                ? $step
                : config('flowpipe.step_namespace', 'App\\Flowpipe\\Steps').'\\'.Str::studly($step);

            if (! class_exists($class)) {
                throw new InvalidArgumentException("Step class [$class] does not exist.");
            }

            $instance = app($class);

            if (! $instance instanceof FlowStep) {
                throw new InvalidArgumentException("Resolved class [$class] must implement FlowStep.");
            }

            return $instance;
        }

        throw new InvalidArgumentException('Invalid step type: '.gettype($step));
    }
}
