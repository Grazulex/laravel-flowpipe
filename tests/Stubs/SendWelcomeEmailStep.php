<?php

declare(strict_types=1);

namespace Tests\Stubs;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class SendWelcomeEmailStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock email sending - just pass through
        return $next($payload);
    }
}
