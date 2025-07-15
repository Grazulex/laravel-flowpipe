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

final class SendWelcomeEmailStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock email sending - just pass through
        return $next($payload);
    }
}

final class AddToCrmStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock CRM addition - just pass through
        return $next($payload);
    }
}

final class LogSuccessStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Mock logging - just pass through
        return $next($payload);
    }
}

final class CustomStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        return $next($payload);
    }
}
