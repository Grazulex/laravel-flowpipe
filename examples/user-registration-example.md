# User Registration Flow Example

This example demonstrates a complete user registration flow with validation, email verification, and profile setup.

## Overview

The user registration flow includes:
1. Input validation
2. Email uniqueness check
3. User account creation
4. Email verification (conditional)
5. Profile setup
6. Role assignment
7. Welcome email (conditional)
8. Event logging

## Flow Definition

```yaml
name: user-registration
description: Complete user registration process with validation, email verification, and profile setup

steps:
  - name: validate-input
    class: Examples\Steps\UserRegistration\ValidateInputStep
    description: Validate user input data (email, password, name)

  - name: check-email-uniqueness
    class: Examples\Steps\UserRegistration\CheckEmailUniquenessStep
    description: Verify email address is not already registered

  - name: create-user-account
    class: Examples\Steps\UserRegistration\CreateUserAccountStep
    description: Create user account in database

  - name: send-verification-email
    class: Examples\Steps\UserRegistration\SendVerificationEmailStep
    description: Send email verification link to user
    condition:
      class: Examples\Conditions\UserRegistration\EmailVerificationEnabledCondition

  - name: setup-user-profile
    class: Examples\Steps\UserRegistration\SetupUserProfileStep
    description: Initialize user profile with default settings

  - name: assign-default-role
    class: Examples\Steps\UserRegistration\AssignDefaultRoleStep
    description: Assign default user role and permissions

  - name: send-welcome-email
    class: Examples\Steps\UserRegistration\SendWelcomeEmailStep
    description: Send welcome email to new user
    condition:
      class: Examples\Conditions\UserRegistration\WelcomeEmailEnabledCondition

  - name: log-registration-event
    class: Examples\Steps\UserRegistration\LogRegistrationEventStep
    description: Log user registration for analytics
```

## Usage

### Basic Usage

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;

// Prepare user registration data
$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'SecurePassword123!',
    'terms_accepted' => true,
    'marketing_opt_in' => true,
];

// Create flow context
$context = new FlowContext(['user_data' => $userData]);

// Execute the flow
$result = Flowpipe::run('user-registration', $context);

// Check if registration was successful
if ($result->isSuccess()) {
    $userId = $result->get('user_id');
    echo "User registered successfully with ID: {$userId}";
} else {
    $errors = $result->getErrors();
    echo "Registration failed: " . implode(', ', $errors);
}
```

### Advanced Usage with Tracing

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

// Create tracer for debugging
$tracer = new TestTracer();

// Prepare context
$context = new FlowContext(['user_data' => $userData]);

// Execute with tracing
$result = Flowpipe::run('user-registration', $context, $tracer);

// Analyze execution
$trace = $tracer->getTrace();
foreach ($trace as $step) {
    echo "Step: {$step['name']}, Duration: {$step['duration']}ms\n";
}
```

### Error Handling

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;

try {
    $context = new FlowContext(['user_data' => $userData]);
    $result = Flowpipe::run('user-registration', $context);
    
    if ($result->isSuccess()) {
        // Handle successful registration
        $userId = $result->get('user_id');
        $verificationSent = $result->get('verification_email_sent', false);
        
        if ($verificationSent) {
            return redirect()->route('verify-email-notice');
        } else {
            return redirect()->route('dashboard');
        }
    } else {
        // Handle validation errors
        $errors = $result->getErrors();
        return back()->withErrors($errors)->withInput();
    }
    
} catch (\Exception $e) {
    // Handle system errors
    logger()->error('User registration failed', [
        'error' => $e->getMessage(),
        'user_data' => $userData,
    ]);
    
    return back()->withErrors(['system' => 'Registration failed. Please try again.']);
}
```

## Configuration

### Application Configuration

```php
// config/flowpipe.php
return [
    'definitions_path' => 'flow_definitions',
    'step_namespace' => 'App\\Flowpipe\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

### Custom Application Settings

You can add your own configuration for the registration flow:

```php
// config/app.php
return [
    // ... other config
    'user_registration' => [
        'email_verification_enabled' => true,
        'welcome_email_enabled' => true,
        'default_user_role' => 'user',
    ],
];
```

## Database Tables

This example assumes the following database structure:

### Users Table

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### User Profiles Table

```sql
CREATE TABLE user_profiles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    avatar VARCHAR(255),
    bio TEXT,
    preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Roles Table

```sql
CREATE TABLE roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### User Roles Table

```sql
CREATE TABLE user_roles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    role_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

## Email Templates

### Verification Email Template

```php
<!-- resources/views/emails/verify-email.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
</head>
<body>
    <h1>Welcome {{ $user['name'] }}!</h1>
    <p>Please verify your email address by clicking the button below:</p>
    <a href="{{ $verificationUrl }}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Verify Email
    </a>
    <p>If you didn't create an account, you can safely ignore this email.</p>
</body>
</html>
```

### Welcome Email Template

```php
<!-- resources/views/emails/welcome.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Our Platform</title>
</head>
<body>
    <h1>Welcome {{ $user['name'] }}!</h1>
    <p>Your account has been created successfully. You can now:</p>
    <ul>
        <li>Explore our features</li>
        <li>Customize your profile</li>
        <li>Connect with others</li>
    </ul>
    <p>
        <a href="{{ $loginUrl }}">Login to your account</a>
    </p>
    <p>
        If you need help, visit our <a href="{{ $supportUrl }}">support center</a>.
    </p>
</body>
</html>
```

## Testing

### Unit Tests

```php
<?php

namespace Tests\Unit\UserRegistration;

use Examples\Steps\UserRegistration\ValidateInputStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class ValidateInputStepTest extends TestCase
{
    public function test_validates_valid_input()
    {
        $step = new ValidateInputStep();
        $context = new FlowContext([
            'user_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'SecurePassword123!',
                'terms_accepted' => true,
            ]
        ]);

        $result = $step->handle($context);

        $this->assertTrue($result->get('validation_passed'));
        $this->assertFalse($result->hasErrors());
    }

    public function test_fails_with_invalid_email()
    {
        $step = new ValidateInputStep();
        $context = new FlowContext([
            'user_data' => [
                'name' => 'John Doe',
                'email' => 'invalid-email',
                'password' => 'SecurePassword123!',
                'terms_accepted' => true,
            ]
        ]);

        $result = $step->handle($context);

        $this->assertFalse($result->get('validation_passed'));
        $this->assertTrue($result->hasErrors());
    }
}
```

### Integration Tests

```php
<?php

namespace Tests\Feature\UserRegistration;

use Examples\Steps\UserRegistration\CreateUserAccountStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserAccountStepTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_user_account()
    {
        $step = new CreateUserAccountStep();
        $context = new FlowContext([
            'validated_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
            ]
        ]);

        $result = $step->handle($context);

        $this->assertTrue($result->get('user_created'));
        $this->assertIsInt($result->get('user_id'));
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }
}
```

## Customization

### Custom Validation Rules

```php
<?php

namespace Examples\Steps\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\Validator;

class ValidateInputStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userData = $context->get('user_data', []);
        
        $validator = Validator::make($userData, [
            'email' => [
                'required',
                'email',
                'max:255',
                // Custom rule to check against disposable email domains
                function ($attribute, $value, $fail) {
                    if ($this->isDisposableEmail($value)) {
                        $fail('Disposable email addresses are not allowed.');
                    }
                },
            ],
            'password' => 'required|min:8|confirmed',
            'name' => 'required|string|max:255',
        ]);

        // Rest of the validation logic...
    }

    private function isDisposableEmail(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $disposableDomains = ['10minutemail.com', 'tempmail.org'];
        
        return in_array($domain, $disposableDomains);
    }
}
```

### Custom Profile Setup

```php
<?php

namespace Examples\Steps\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;

class SetupUserProfileStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userId = $context->get('user_id');
        $userData = $context->get('validated_data', []);
        
        // Custom profile setup based on user type
        $userType = $userData['account_type'] ?? 'personal';
        
        $preferences = $this->getDefaultPreferences($userType);
        
        // Create profile with custom preferences
        $profileId = DB::table('user_profiles')->insertGetId([
            'user_id' => $userId,
            'account_type' => $userType,
            'preferences' => json_encode($preferences),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context->set('profile_id', $profileId);
        $context->set('profile_created', true);
        
        return $context;
    }

    private function getDefaultPreferences(string $userType): array
    {
        $basePreferences = [
            'notifications' => ['email' => true, 'sms' => false],
            'privacy' => ['profile_visibility' => 'public'],
        ];

        switch ($userType) {
            case 'business':
                $basePreferences['features'] = ['analytics' => true, 'api_access' => true];
                break;
            case 'personal':
                $basePreferences['features'] = ['analytics' => false, 'api_access' => false];
                break;
        }

        return $basePreferences;
    }
}
```

This example provides a complete, production-ready user registration flow that can be easily customized and extended for specific application needs.
