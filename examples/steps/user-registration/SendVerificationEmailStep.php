<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class SendVerificationEmailStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userData = $context->get('user_data', []);
        $userId = $context->get('user_id');

        if (! $userId || ! $userData) {
            $context->addError('User data is required to send verification email');

            return $context;
        }

        try {
            // Generate verification token
            $verificationToken = Str::random(64);

            // Store verification token (you would typically save this to database)
            $context->set('verification_token', $verificationToken);

            // Generate verification URL
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                ['id' => $userId, 'hash' => sha1($userData['email'])]
            );

            // Send verification email
            Mail::send('emails.verify-email', [
                'user' => $userData,
                'verificationUrl' => $verificationUrl,
            ], function ($message) use ($userData) {
                $message->to($userData['email'], $userData['name'])
                    ->subject('Verify Your Email Address');
            });

            $context->set('verification_email_sent', true);

        } catch (Exception $e) {
            $context->addError('Failed to send verification email: '.$e->getMessage());
            $context->set('verification_email_sent', false);
        }

        return $context;
    }
}
