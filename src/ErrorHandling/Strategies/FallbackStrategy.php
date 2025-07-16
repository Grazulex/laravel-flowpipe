<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling\Strategies;

use Closure;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerResult;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerStrategy;
use Throwable;

final class FallbackStrategy implements ErrorHandlerStrategy
{
    public function __construct(
        private readonly Closure $fallbackHandler,
        private readonly ?Closure $shouldFallback = null
    ) {}

    public static function make(Closure $fallbackHandler, ?Closure $shouldFallback = null): self
    {
        return new self($fallbackHandler, $shouldFallback);
    }

    public static function withDefault(mixed $defaultValue, ?Closure $shouldFallback = null): self
    {
        $fallbackHandler = fn (mixed $payload, Throwable $error): mixed => $defaultValue;

        return new self($fallbackHandler, $shouldFallback);
    }

    public static function withTransform(Closure $transformer, ?Closure $shouldFallback = null): self
    {
        $fallbackHandler = fn (mixed $payload, Throwable $error) => $transformer($payload, $error);

        return new self($fallbackHandler, $shouldFallback);
    }

    public static function withPayload(mixed $fallbackPayload, ?Closure $shouldFallback = null): self
    {
        $fallbackHandler = fn (mixed $payload, Throwable $error): mixed => $fallbackPayload;

        return new self($fallbackHandler, $shouldFallback);
    }

    public static function forException(string $exceptionClass, Closure $fallbackHandler): self
    {
        $shouldFallback = fn (Throwable $e): bool => $e instanceof $exceptionClass;

        return new self($fallbackHandler, $shouldFallback);
    }

    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        // Check if we should fallback
        if ($this->shouldFallback && ! ($this->shouldFallback)($error, $attemptNumber)) {
            return ErrorHandlerResult::fail($error, $context);
        }

        try {
            $fallbackResult = ($this->fallbackHandler)($payload, $error);

            return ErrorHandlerResult::fallback($fallbackResult, array_merge($context, [
                'fallback_triggered' => true,
                'fallback_reason' => $error->getMessage(),
                'original_error' => $error,
            ]));
        } catch (Throwable $fallbackError) {
            // Fallback itself failed
            return ErrorHandlerResult::fail($fallbackError, array_merge($context, [
                'fallback_failed' => true,
                'original_error' => $error,
                'fallback_error' => $fallbackError,
            ]));
        }
    }
}
