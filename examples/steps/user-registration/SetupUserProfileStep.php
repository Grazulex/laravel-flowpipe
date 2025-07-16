<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Closure;
use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class SetupUserProfileStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Payload must be an array');
        }

        $userId = $payload['id'] ?? null;

        if (! $userId) {
            throw new InvalidArgumentException('User ID is required to setup profile');
        }

        try {
            // Create user profile with default settings
            $profileId = DB::table('user_profiles')->insertGetId([
                'user_id' => $userId,
                'avatar' => null,
                'bio' => '',
                'preferences' => json_encode([
                    'notifications' => [
                        'email' => true,
                        'sms' => false,
                        'push' => true,
                    ],
                    'privacy' => [
                        'profile_visibility' => 'public',
                        'show_email' => false,
                    ],
                    'theme' => 'light',
                    'language' => 'en',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payload['profile_id'] = $profileId;
            $payload['profile_created'] = true;

            return $next($payload);

        } catch (Exception $e) {
            throw new RuntimeException('Failed to setup user profile: '.$e->getMessage());
        }
    }
}
