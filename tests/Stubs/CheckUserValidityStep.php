<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class CheckUserValidityStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock validation - just pass through
        return $next($payload);
    }
}
