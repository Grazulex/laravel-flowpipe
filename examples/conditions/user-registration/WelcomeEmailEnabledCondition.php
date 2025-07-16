<?php

declare(strict_types=1);

namespace Examples\Conditions\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\FlowContext;

final class WelcomeEmailEnabledCondition implements Condition
{
    public function evaluate(FlowContext $context): bool
    {
        // Check if welcome emails are enabled
        $welcomeEmailEnabled = config('app.user_registration.welcome_email_enabled', true);

        // Check if user has opted into marketing emails
        $validatedData = $context->get('validated_data', []);
        $marketingOptIn = $validatedData['marketing_opt_in'] ?? true;

        // Check if user registration was successful
        $userCreated = $context->get('user_created', false);

        return $welcomeEmailEnabled && $marketingOptIn && $userCreated;
    }
}
