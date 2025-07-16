<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\Log;

final class LogRegistrationEventStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userData = $context->get('user_data', []);
        $userId = $context->get('user_id');

        try {
            // Log registration event
            Log::info('User registration completed', [
                'user_id' => $userId,
                'email' => $userData['email'] ?? null,
                'name' => $userData['name'] ?? null,
                'verification_email_sent' => $context->get('verification_email_sent', false),
                'welcome_email_sent' => $context->get('welcome_email_sent', false),
                'profile_created' => $context->get('profile_created', false),
                'role_assigned' => $context->get('role_assigned', false),
                'timestamp' => now()->toISOString(),
            ]);

            // You could also send to analytics service, metrics collector, etc.

            $context->set('registration_logged', true);

        } catch (Exception $e) {
            $context->addError('Failed to log registration event: '.$e->getMessage());
            $context->set('registration_logged', false);
        }

        return $context;
    }
}
