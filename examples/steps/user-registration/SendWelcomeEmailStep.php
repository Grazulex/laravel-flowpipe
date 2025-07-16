<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Closure;
use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

final class SendWelcomeEmailStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Payload must be an array');
        }

        $userData = $payload;

        if (! isset($userData['email']) || ! isset($userData['name'])) {
            throw new InvalidArgumentException('User data with email and name is required to send welcome email');
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

            $userData['welcome_email_sent'] = true;

            return $next($userData);

        } catch (Exception $e) {
            throw new RuntimeException('Failed to send welcome email: '.$e->getMessage());
        }
    }
}
