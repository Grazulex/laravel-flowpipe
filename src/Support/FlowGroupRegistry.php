<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Support;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

/**
 * Registry for storing and managing named step groups
 */
final class FlowGroupRegistry
{
    /** @var array<string, array<FlowStep|Closure>> */
    private static array $groups = [];

    /**
     * Register a group of steps with a name
     *
     * @param  array<FlowStep|string|callable>  $steps
     */
    public static function register(string $name, array $steps): void
    {
        self::$groups[$name] = array_map([StepResolver::class, 'resolve'], $steps);
    }

    /**
     * Get a registered group by name
     *
     * @return array<FlowStep|Closure>
     */
    public static function get(string $name): array
    {
        return self::$groups[$name] ?? [];
    }

    /**
     * Check if a group exists
     */
    public static function has(string $name): bool
    {
        return isset(self::$groups[$name]);
    }

    /**
     * Get all registered groups
     *
     * @return array<string, array<FlowStep|Closure>>
     */
    public static function all(): array
    {
        return self::$groups;
    }

    /**
     * Clear all registered groups
     */
    public static function clear(): void
    {
        self::$groups = [];
    }

    /**
     * Get count of registered groups
     */
    public static function count(): int
    {
        return count(self::$groups);
    }
}
