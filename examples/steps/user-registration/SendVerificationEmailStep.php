<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class SendVerificationEmailStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        $userData = $payload;
        $userId = $userData['id'] ?? null;

        if (!$userId || !isset($userData['email'])) {
            throw new \InvalidArgumentException('User data with ID and email is required to send verification email');
        }

        try {
            // Generate verification token
            $verificationToken = Str::random(64);

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

            // Add verification info to payload
            $userData['verification_token'] = $verificationToken;
            $userData['verification_email_sent'] = true;

            return $next($userData);

        } catch (Exception $e) {
            throw new \RuntimeException('Failed to send verification email: ' . $e->getMessage());
        }
    }
}
