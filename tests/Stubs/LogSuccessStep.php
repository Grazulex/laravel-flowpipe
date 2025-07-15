<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class LogSuccessStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock logging - just pass through
        return $next($payload);
    }
}
