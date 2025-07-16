<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

final class RateLimitStep implements FlowStep
{
    public function __construct(
        private readonly string $key,
        private readonly int $maxAttempts = 60,
        private readonly int $decayMinutes = 1,
        private readonly ?Closure $keyGenerator = null
    ) {}

    public static function make(
        string $key,
        int $maxAttempts = 60,
        int $decayMinutes = 1,
        ?Closure $keyGenerator = null
    ): self {
        return new self($key, $maxAttempts, $decayMinutes, $keyGenerator);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $rateLimitKey = $this->generateKey($payload);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $this->maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw new ThrottleRequestsException(
                "Rate limit exceeded. Try again in {$seconds} seconds."
            );
        }

        $result = $next($payload);

        RateLimiter::hit($rateLimitKey, $this->decayMinutes * 60);

        return $result;
    }

    private function generateKey(mixed $payload): string
    {
        if ($this->keyGenerator instanceof Closure) {
            return $this->key.':'.($this->keyGenerator)($payload);
        }

        return $this->key.':'.md5(serialize($payload));
    }
}
