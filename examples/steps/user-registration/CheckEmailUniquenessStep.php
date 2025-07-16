<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\DB;

final class CheckEmailUniquenessStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $validatedData = $context->get('validated_data', []);
        $email = $validatedData['email'] ?? null;

        if (! $email) {
            $context->addError('Email is required for uniqueness check');

            return $context;
        }

        $emailExists = DB::table('users')
            ->where('email', $email)
            ->exists();

        if ($emailExists) {
            $context->addError('This email address is already registered');
            $context->set('email_unique', false);

            return $context;
        }

        $context->set('email_unique', true);

        return $context;
    }
}
