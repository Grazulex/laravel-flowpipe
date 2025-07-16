<?php

declare(strict_types=1);

namespace Examples\Conditions\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\FlowContext;

final class EmailVerificationEnabledCondition implements Condition
{
    public function evaluate(FlowContext $context): bool
    {
        // Check if email verification is enabled in configuration
        $emailVerificationEnabled = config('app.user_registration.email_verification_enabled', true);

        // Additional checks can be added here
        // For example, check if user has already verified email
        $userAlreadyVerified = $context->get('user_already_verified', false);

        return $emailVerificationEnabled && ! $userAlreadyVerified;
    }
}
