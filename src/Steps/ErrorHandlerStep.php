<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerAction;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerStrategy;
use RuntimeException;
use Throwable;

final class ErrorHandlerStep implements FlowStep
{
    public function __construct(
        private readonly ErrorHandlerStrategy $strategy,
        private readonly int $maxAttempts = 3
    ) {}

    public static function make(ErrorHandlerStrategy $strategy, int $maxAttempts = 3): self
    {
        return new self($strategy, $maxAttempts);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $attemptNumber = 1;
        $context = [];

        while ($attemptNumber <= $this->maxAttempts) {
            try {
                return $next($payload);
            } catch (Throwable $error) {
                $result = $this->strategy->handle($error, $payload, $attemptNumber, $context);

                switch ($result->action) {
                    case ErrorHandlerAction::RETRY:
                        $attemptNumber++;
                        $payload = $result->payload;
                        $context = $result->context;

                        if ($result->delayMs > 0) {
                            usleep($result->delayMs * 1000);
                        }

                        continue 2; // Continue the while loop

                    case ErrorHandlerAction::FALLBACK:

                    case ErrorHandlerAction::COMPENSATE:
                        return $result->payload;

                    case ErrorHandlerAction::FAIL:
                        throw $result->error ?? $error;
                    case ErrorHandlerAction::ABORT:
                        throw $result->error ?? $error;
                }
            }
        }

        throw new RuntimeException('Maximum attempts exceeded');
    }
}
