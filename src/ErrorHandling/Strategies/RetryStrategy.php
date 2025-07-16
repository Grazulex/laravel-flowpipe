<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling\Strategies;

use Closure;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerResult;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerStrategy;
use Throwable;

final class RetryStrategy implements ErrorHandlerStrategy
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $delayMs = 100,
        private readonly ?Closure $shouldRetry = null,
        private readonly ?Closure $delayCalculator = null
    ) {}

    public static function make(int $maxAttempts = 3, int $delayMs = 100, ?Closure $shouldRetry = null, ?Closure $delayCalculator = null): self
    {
        return new self($maxAttempts, $delayMs, $shouldRetry, $delayCalculator);
    }

    public static function exponentialBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, float $multiplier = 2.0, ?Closure $shouldRetry = null): self
    {
        $delayCalculator = fn (int $attempt): int => (int) ($baseDelayMs * pow($multiplier, $attempt - 1));

        return new self($maxAttempts, $baseDelayMs, $shouldRetry, $delayCalculator);
    }

    public static function linearBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, int $increment = 100, ?Closure $shouldRetry = null): self
    {
        $delayCalculator = fn (int $attempt): int => $baseDelayMs + ($increment * ($attempt - 1));

        return new self($maxAttempts, $baseDelayMs, $shouldRetry, $delayCalculator);
    }

    public static function forException(string $exceptionClass, int $maxAttempts = 3, int $delayMs = 100): self
    {
        $shouldRetry = fn (Throwable $e): bool => $e instanceof $exceptionClass;

        return new self($maxAttempts, $delayMs, $shouldRetry);
    }

    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        // Check if we should retry
        if ($this->shouldRetry && ! ($this->shouldRetry)($error, $attemptNumber)) {
            return ErrorHandlerResult::fail($error, $context);
        }

        // Check if we've exceeded max attempts
        if ($attemptNumber >= $this->maxAttempts) {
            return ErrorHandlerResult::fail($error, $context);
        }

        // Calculate delay
        $delay = $this->delayCalculator instanceof Closure
            ? ($this->delayCalculator)($attemptNumber)
            : $this->delayMs;

        return ErrorHandlerResult::retry($payload, $delay, array_merge($context, [
            'retry_attempt' => $attemptNumber,
            'retry_delay' => $delay,
            'retry_reason' => $error->getMessage(),
        ]));
    }
}
