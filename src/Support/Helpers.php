<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;

/**
 * Generic helper methods used inside Laravel-Flowpipe.
 */
final class Helpers
{
    public static function isClosure(mixed $value): bool
    {
        return $value instanceof Closure;
    }

    public static function isFlowStep(mixed $value): bool
    {
        return $value instanceof \Grazulex\LaravelFlowpipe\Contracts\FlowStep;
    }

    public static function nowInMs(): float
    {
        return microtime(true) * 1000;
    }

    public static function durationMs(float $start, ?float $end = null): float
    {
        return ($end ?? microtime(true)) * 1000 - $start * 1000;
    }

    public static function shortClassName(object|string $value): string
    {
        $full = is_string($value) ? $value : $value::class;

        return class_basename($full);
    }
}
