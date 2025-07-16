<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling\Strategies;

use Closure;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerResult;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerStrategy;
use Throwable;

final class CompensationStrategy implements ErrorHandlerStrategy
{
    public function __construct(
        private readonly Closure $compensationHandler,
        private readonly ?Closure $shouldCompensate = null
    ) {}

    public static function make(Closure $compensationHandler, ?Closure $shouldCompensate = null): self
    {
        return new self($compensationHandler, $shouldCompensate);
    }

    public static function rollback(Closure $rollbackHandler, ?Closure $shouldCompensate = null): self
    {
        return new self($rollbackHandler, $shouldCompensate);
    }

    public static function cleanup(Closure $cleanupHandler, ?Closure $shouldCompensate = null): self
    {
        return new self($cleanupHandler, $shouldCompensate);
    }

    public static function forException(string $exceptionClass, Closure $compensationHandler): self
    {
        $shouldCompensate = fn (Throwable $e): bool => $e instanceof $exceptionClass;

        return new self($compensationHandler, $shouldCompensate);
    }

    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        // Check if we should compensate
        if ($this->shouldCompensate && ! ($this->shouldCompensate)($error, $attemptNumber)) {
            return ErrorHandlerResult::fail($error, $context);
        }

        try {
            $compensationResult = ($this->compensationHandler)($payload, $error, $context);

            return ErrorHandlerResult::compensate($compensationResult, array_merge($context, [
                'compensation_triggered' => true,
                'compensation_reason' => $error->getMessage(),
                'original_error' => $error,
            ]));
        } catch (Throwable $compensationError) {
            // Compensation itself failed
            return ErrorHandlerResult::fail($compensationError, array_merge($context, [
                'compensation_failed' => true,
                'original_error' => $error,
                'compensation_error' => $compensationError,
            ]));
        }
    }
}
