<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Log;

final class LogRegistrationEventStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        try {
            // Log registration event
            Log::info('User registration completed', [
                'user_id' => $payload['id'] ?? null,
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? null,
                'verification_email_sent' => $payload['verification_email_sent'] ?? false,
                'welcome_email_sent' => $payload['welcome_email_sent'] ?? false,
                'profile_created' => $payload['profile_created'] ?? false,
                'role_assigned' => $payload['role_assigned'] ?? false,
                'timestamp' => now()->toISOString(),
            ]);

            // You could also send to analytics service, metrics collector, etc.

            $payload['registration_logged'] = true;

            return $next($payload);

        } catch (Exception $e) {
            throw new \RuntimeException('Failed to log registration event: ' . $e->getMessage());
        }
    }
}
