<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Grazulex\LaravelFlowpipe\Steps\GroupStep;
use Grazulex\LaravelFlowpipe\Steps\NestedFlowStep;
use Grazulex\LaravelFlowpipe\Support\FlowGroupRegistry;
use Grazulex\LaravelFlowpipe\Support\Helpers;
use Grazulex\LaravelFlowpipe\Support\StepResolver;
use Grazulex\LaravelFlowpipe\Tracer as TracerNamespace;

final class Flowpipe
{
    private mixed $payload = null;

    /** @var array<FlowStep|Closure> */
    private array $steps = [];

    private FlowContext $context;

    private function __construct() {}

    public static function make(?Tracer $tracer = null): self
    {
        $instance = new self();
        $instance->context = new FlowContext($tracer);

        return $instance;
    }

    public static function debug(bool $logToFile = false, string $logChannel = 'default'): self
    {
        return self::make(new TracerNamespace\DebugTracer($logToFile, $logChannel));
    }

    public static function performance(): self
    {
        return self::make(new TracerNamespace\PerformanceTracer());
    }

    public static function database(string $tableName = 'flowpipe_traces'): self
    {
        return self::make(new TracerNamespace\DatabaseTracer($tableName));
    }

    public static function test(): self
    {
        return self::make(new TracerNamespace\TestTracer());
    }

    /**
     * Define a reusable group of steps
     *
     * @param  array<FlowStep|Closure|string>  $steps
     */
    public static function group(string $name, array $steps): void
    {
        FlowGroupRegistry::register($name, $steps);
    }

    /**
     * Get all registered groups
     */
    public static function getGroups(): array
    {
        return FlowGroupRegistry::all();
    }

    /**
     * Check if a group exists
     */
    public static function hasGroup(string $name): bool
    {
        return FlowGroupRegistry::has($name);
    }

    /**
     * Clear all registered groups
     */
    public static function clearGroups(): void
    {
        FlowGroupRegistry::clear();
    }

    public function withTracer(?Tracer $tracer): self
    {
        $this->context = new FlowContext($tracer);

        return $this;
    }

    public function send(mixed $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @param  array<FlowStep|Closure|string|class-string>  $steps
     */
    public function through(array $steps): self
    {
        $this->steps = array_merge($this->steps, array_map([StepResolver::class, 'resolve'], $steps));

        return $this;
    }

    /**
     * Use a predefined group of steps
     */
    public function useGroup(string $name): self
    {
        $this->steps[] = new GroupStep($name);

        return $this;
    }

    /**
     * Create a nested flow
     *
     * @param  array<FlowStep|Closure|string>  $steps
     */
    public function nested(array $steps): self
    {
        $this->steps[] = new NestedFlowStep($steps);

        return $this;
    }

    public function cache(string $key, int $ttl = 3600, ?string $store = null): self
    {
        $this->steps[] = new Steps\CacheStep($key, $ttl, $store);

        return $this;
    }

    public function retry(int $maxAttempts = 3, int $delayMs = 100, ?Closure $shouldRetry = null): self
    {
        $this->steps[] = new Steps\RetryStep($maxAttempts, $delayMs, $shouldRetry);

        return $this;
    }

    public function rateLimit(string $key, int $maxAttempts = 60, int $decayMinutes = 1, ?Closure $keyGenerator = null): self
    {
        $this->steps[] = new Steps\RateLimitStep($key, $maxAttempts, $decayMinutes, $keyGenerator);

        return $this;
    }

    public function transform(Closure $transformer): self
    {
        $this->steps[] = new Steps\TransformStep($transformer);

        return $this;
    }

    public function validate(array $rules, array $messages = [], array $customAttributes = []): self
    {
        $this->steps[] = new Steps\ValidationStep($rules, $messages, $customAttributes);

        return $this;
    }

    public function batch(int $batchSize = 100, bool $preserveKeys = false): self
    {
        $this->steps[] = new Steps\BatchStep($batchSize, $preserveKeys);

        return $this;
    }

    public function thenReturn(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->steps),
            function (Closure $next, FlowStep|Closure $step): Closure {
                return function (mixed $payload) use ($step, $next) {
                    $before = $payload;
                    $start = microtime(true);

                    $result = $step instanceof Closure
                        ? $step($payload, $next)
                        : $step->handle($payload, $next);

                    $duration = Helpers::durationMs($start);

                    $stepClass = $step instanceof Closure ? 'Closure' : $step::class;

                    $this->context->tracer()?->trace(
                        $stepClass,
                        $before,
                        $result,
                        $duration
                    );

                    return $result;
                };
            },
            fn ($finalPayload) => $finalPayload
        );

        return $pipeline($this->payload);
    }

    public function context(): FlowContext
    {
        return $this->context;
    }
}
