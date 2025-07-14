<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use InvalidArgumentException;

final class StepResolver
{
    /**
     * Resolve a step given a Closure, instance, or class name.
     */
    public static function resolve(FlowStep|Closure|string $step): FlowStep|Closure
    {
        if ($step instanceof Closure || $step instanceof FlowStep) {
            return $step;
        }

        if (class_exists($step)) {
            $instance = app($step);

            if (! $instance instanceof FlowStep) {
                throw new InvalidArgumentException("Resolved class [$step] must implement FlowStep.");
            }

            return $instance;
        }

        throw new InvalidArgumentException('Invalid step provided: '.gettype($step));
    }
}
