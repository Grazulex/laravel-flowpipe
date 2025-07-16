<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\Mail;

final class SendWelcomeEmailStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userData = $context->get('user_data', []);

        if (! $userData) {
            $context->addError('User data is required to send welcome email');

            return $context;
        }

        try {
            Mail::send('emails.welcome', [
                'user' => $userData,
                'loginUrl' => route('login'),
                'supportUrl' => route('support'),
            ], function ($message) use ($userData) {
                $message->to($userData['email'], $userData['name'])
                    ->subject('Welcome to Our Platform!');
            });

            $context->set('welcome_email_sent', true);

        } catch (Exception $e) {
            $context->addError('Failed to send welcome email: '.$e->getMessage());
            $context->set('welcome_email_sent', false);
        }

        return $context;
    }
}
