<?php

declare(strict_types=1);

/**
 * Example: User Registration with Groups and Nested Flows
 *
 * This example demonstrates a complete user registration process
 * using step groups for validation, nested flows for complex processing,
 * and conditional logic.
 */

require_once '../vendor/autoload.php';

use Grazulex\LaravelFlowpipe\Flowpipe;

// Define reusable step groups
Flowpipe::group('user-validation', [
    // Validate email format
    fn ($user, $next) => $next(
        filter_var($user['email'], FILTER_VALIDATE_EMAIL)
            ? $user
            : throw new InvalidArgumentException('Invalid email format')
    ),

    // Validate password strength
    fn ($user, $next) => $next(
        mb_strlen($user['password']) >= 8
            ? $user
            : throw new InvalidArgumentException('Password must be at least 8 characters')
    ),

    // Validate required fields
    fn ($user, $next) => $next(
        ! empty($user['name']) && ! empty($user['email'])
            ? $user
            : throw new InvalidArgumentException('Name and email are required')
    ),

    // Check for existing user (simplified)
    fn ($user, $next) => $next(array_merge($user, [
        'email_available' => true, // Simplified for example
        'validation_passed' => true,
    ])),
]);

Flowpipe::group('user-setup', [
    // Generate user ID
    fn ($user, $next) => $next(array_merge($user, [
        'user_id' => 'user_'.uniqid(),
        'username' => mb_strtolower(str_replace(' ', '.', $user['name'])),
    ])),

    // Set default preferences
    fn ($user, $next) => $next(array_merge($user, [
        'preferences' => [
            'theme' => 'light',
            'notifications' => true,
            'newsletter' => true,
        ],
    ])),

    // Set account metadata
    fn ($user, $next) => $next(array_merge($user, [
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'active',
        'email_verified' => false,
    ])),
]);

Flowpipe::group('user-notifications', [
    // Send welcome email
    fn ($user, $next) => $next(array_merge($user, [
        'welcome_email_sent' => true,
        'welcome_email_id' => 'email_'.uniqid(),
    ])),

    // Send verification email
    fn ($user, $next) => $next(array_merge($user, [
        'verification_email_sent' => true,
        'verification_token' => 'token_'.bin2hex(random_bytes(32)),
    ])),

    // Notify admin
    fn ($user, $next) => $next(array_merge($user, [
        'admin_notified' => true,
        'admin_notification_id' => 'admin_'.uniqid(),
    ])),
]);

// Sample user registration data
$userData = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'password' => 'securepassword123',
    'terms_accepted' => true,
    'marketing_consent' => true,
];

echo "=== User Registration with Groups and Nested Flows ===\n\n";
echo "Registration Data:\n";
print_r($userData);
echo "\n";

// Process user registration
try {
    $result = Flowpipe::make()
        ->send($userData)

        // Step 1: Validate user input
        ->useGroup('user-validation')

        // Step 2: Process password securely in nested flow
        ->nested([
            // Password processing sub-flow (isolated for security)
            fn ($user, $next) => $next(array_merge($user, [
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
                'password_strength' => 'strong', // Simplified scoring
            ])),

            // Remove plain password from array
            function ($user, $next) {
                unset($user['password']);

                return $next($user);
            },

            // Add security metadata
            fn ($user, $next) => $next(array_merge($user, [
                'password_created_at' => date('Y-m-d H:i:s'),
                'password_expires_at' => date('Y-m-d H:i:s', strtotime('+90 days')),
            ])),
        ])

        // Step 3: Set up user account
        ->useGroup('user-setup')

        // Step 4: Create user profile in nested flow
        ->nested([
            // Profile creation sub-flow
            fn ($user, $next) => $next(array_merge($user, [
                'profile' => [
                    'bio' => '',
                    'avatar' => 'default-avatar.png',
                    'timezone' => 'UTC',
                    'language' => 'en',
                ],
            ])),

            // Generate profile URLs
            fn ($user, $next) => $next(array_merge($user, [
                'profile_url' => '/profile/'.$user['username'],
                'avatar_url' => '/avatars/default-avatar.png',
            ])),

            // Set privacy settings
            fn ($user, $next) => $next(array_merge($user, [
                'privacy' => [
                    'profile_public' => false,
                    'email_public' => false,
                    'show_online_status' => true,
                ],
            ])),
        ])

        // Step 5: Handle permissions and roles in nested flow
        ->nested([
            // Role assignment sub-flow
            fn ($user, $next) => $next(array_merge($user, [
                'role' => 'user',
                'permissions' => ['read', 'write_own', 'comment'],
            ])),

            // Set quota limits
            fn ($user, $next) => $next(array_merge($user, [
                'quota' => [
                    'storage' => '1GB',
                    'uploads_per_day' => 10,
                    'api_calls_per_hour' => 100,
                ],
            ])),
        ])

        // Step 6: Send notifications
        ->useGroup('user-notifications')

        // Step 7: Final setup and logging
        ->through([
            // Final setup step
            fn ($user, $next) => $next(array_merge($user, [
                'registration_completed' => true,
                'completed_at' => date('Y-m-d H:i:s'),
                'onboarding_status' => 'pending',
            ])),

            // Log registration
            fn ($user, $next) => $next(array_merge($user, [
                'registration_logged' => true,
                'log_entry_id' => 'log_'.uniqid(),
            ])),
        ])

        ->thenReturn();

    echo "User registration completed successfully!\n\n";

    echo "=== Registration Summary ===\n";
    echo 'User ID: '.$result['user_id']."\n";
    echo 'Username: '.$result['username']."\n";
    echo 'Email: '.$result['email']."\n";
    echo 'Status: '.$result['status']."\n";
    echo 'Created At: '.$result['created_at']."\n";
    echo 'Profile URL: '.$result['profile_url']."\n";
    echo 'Role: '.$result['role']."\n";
    echo 'Email Verified: '.($result['email_verified'] ? 'Yes' : 'No')."\n";
    echo 'Welcome Email Sent: '.($result['welcome_email_sent'] ? 'Yes' : 'No')."\n";
    echo 'Admin Notified: '.($result['admin_notified'] ? 'Yes' : 'No')."\n";

    echo "\n=== User Preferences ===\n";
    foreach ($result['preferences'] as $key => $value) {
        echo '- '.ucfirst($key).': '.($value ? 'Enabled' : 'Disabled')."\n";
    }

    echo "\n=== User Permissions ===\n";
    foreach ($result['permissions'] as $permission) {
        echo '- '.ucfirst(str_replace('_', ' ', $permission))."\n";
    }

    echo "\n=== Privacy Settings ===\n";
    foreach ($result['privacy'] as $setting => $value) {
        echo '- '.ucfirst(str_replace('_', ' ', $setting)).': '.($value ? 'Yes' : 'No')."\n";
    }

    echo "\n=== Quota Limits ===\n";
    foreach ($result['quota'] as $limit => $value) {
        echo '- '.ucfirst(str_replace('_', ' ', $limit)).': '.$value."\n";
    }

} catch (Exception $e) {
    echo 'Registration failed: '.$e->getMessage()."\n";
}

echo "\n=== Workflow Analysis ===\n";
echo "Groups Used:\n";
foreach (Flowpipe::getGroups() as $groupName => $steps) {
    echo "- $groupName (".count($steps)." steps)\n";
}

echo "\nNested Flows Benefits:\n";
echo "✓ Password processing isolated for security\n";
echo "✓ Profile creation encapsulated\n";
echo "✓ Role assignment contained\n";
echo "✓ Each nested flow can be tested independently\n";
echo "✓ Complex logic is organized and maintainable\n";

echo "\nGroup Benefits:\n";
echo "✓ Validation logic is reusable\n";
echo "✓ User setup can be reused for different registration flows\n";
echo "✓ Notification logic is centralized\n";
echo "✓ Easy to modify individual components\n";
echo "✓ Clear separation of concerns\n";
