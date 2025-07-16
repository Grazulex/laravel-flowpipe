<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Throwable;

final class RetryStep implements FlowStep
{
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $delayMs = 100,
        private readonly ?Closure $shouldRetry = null
    ) {}

    public static function make(int $maxAttempts = 3, int $delayMs = 100, ?Closure $shouldRetry = null): self
    {
        return new self($maxAttempts, $delayMs, $shouldRetry);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxAttempts) {
            try {
                return $next($payload);
            } catch (Throwable $e) {
                $lastException = $e;
                $attempts++;

                // Check if we should retry this exception
                if ($this->shouldRetry && ! ($this->shouldRetry)($e, $attempts)) {
                    throw $e;
                }

                // Don't delay on the last attempt
                if ($attempts < $this->maxAttempts) {
                    usleep($this->delayMs * 1000);
                }
            }
        }

        throw $lastException;
    }
}
