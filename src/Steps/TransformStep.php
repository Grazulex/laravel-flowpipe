<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class TransformStep implements FlowStep
{
    public function __construct(
        private readonly Closure $transformer
    ) {}

    public static function make(Closure $transformer): self
    {
        return new self($transformer);
    }

    public static function map(Closure $mapper): self
    {
        return new self(function ($payload) use ($mapper) {
            if (is_array($payload)) {
                return array_map($mapper, $payload);
            }

            return $mapper($payload);
        });
    }

    public static function filter(Closure $filter): self
    {
        return new self(function ($payload) use ($filter) {
            if (is_array($payload)) {
                return array_filter($payload, $filter);
            }

            return $filter($payload) ? $payload : null;
        });
    }

    public static function pluck(string $key): self
    {
        return new self(function ($payload) use ($key) {
            if (is_array($payload)) {
                return array_column($payload, $key);
            }

            return $payload[$key] ?? null;
        });
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $transformedPayload = ($this->transformer)($payload);

        return $next($transformedPayload);
    }
}
