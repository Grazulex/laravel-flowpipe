<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling;

use Throwable;

final readonly class ErrorHandlerResult
{
    public function __construct(
        public ErrorHandlerAction $action,
        public mixed $payload = null,
        public ?Throwable $error = null,
        public int $delayMs = 0,
        public array $context = []
    ) {}

    public static function retry(mixed $payload, int $delayMs = 0, array $context = []): self
    {
        return new self(ErrorHandlerAction::RETRY, $payload, null, $delayMs, $context);
    }

    public static function fallback(mixed $payload, array $context = []): self
    {
        return new self(ErrorHandlerAction::FALLBACK, $payload, null, 0, $context);
    }

    public static function compensate(mixed $payload, array $context = []): self
    {
        return new self(ErrorHandlerAction::COMPENSATE, $payload, null, 0, $context);
    }

    public static function fail(Throwable $error, array $context = []): self
    {
        return new self(ErrorHandlerAction::FAIL, null, $error, 0, $context);
    }

    public static function abort(Throwable $error, array $context = []): self
    {
        return new self(ErrorHandlerAction::ABORT, null, $error, 0, $context);
    }
}
