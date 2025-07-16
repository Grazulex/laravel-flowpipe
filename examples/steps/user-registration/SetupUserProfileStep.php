<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\DB;

final class SetupUserProfileStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userId = $context->get('user_id');

        if (! $userId) {
            $context->addError('User ID is required to setup profile');

            return $context;
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

            $context->set('profile_id', $profileId);
            $context->set('profile_created', true);

        } catch (Exception $e) {
            $context->addError('Failed to setup user profile: '.$e->getMessage());
            $context->set('profile_created', false);
        }

        return $context;
    }
}
