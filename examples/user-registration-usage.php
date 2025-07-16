<?php

declare(strict_types=1);

use Examples\Steps\UserRegistration\AssignDefaultRoleStep;
use Examples\Steps\UserRegistration\CheckEmailUniquenessStep;
use Examples\Steps\UserRegistration\CreateUserAccountStep;
use Examples\Steps\UserRegistration\LogRegistrationEventStep;
use Examples\Steps\UserRegistration\SendVerificationEmailStep;
use Examples\Steps\UserRegistration\SendWelcomeEmailStep;
use Examples\Steps\UserRegistration\SetupUserProfileStep;
use Examples\Steps\UserRegistration\ValidateInputStep;
use Grazulex\LaravelFlowpipe\Flowpipe;

// Example 1: Basic user registration flow
$userData = [
    'name' => 'John Doe',
    'email' => 'john.doe@example.com',
    'password' => 'SecurePassword123!',
    'terms_accepted' => true,
];

try {
    $result = Flowpipe::make()
        ->send($userData)
        ->through([
            new ValidateInputStep(),
            new CheckEmailUniquenessStep(),
            new CreateUserAccountStep(),
            new SendVerificationEmailStep(),
            new SetupUserProfileStep(),
            new AssignDefaultRoleStep(),
            new SendWelcomeEmailStep(),
            new LogRegistrationEventStep(),
        ])
        ->thenReturn();

    echo "User registration successful!\n";
    echo 'User ID: '.$result['id']."\n";
    echo 'Email: '.$result['email']."\n";
    echo 'Profile created: '.($result['profile_created'] ? 'Yes' : 'No')."\n";
    echo 'Role assigned: '.($result['role_assigned'] ? $result['assigned_role'] : 'No')."\n";
    echo 'Welcome email sent: '.($result['welcome_email_sent'] ? 'Yes' : 'No')."\n";

} catch (Exception $e) {
    echo 'Registration failed: '.$e->getMessage()."\n";
}

// Example 2: User registration with conditional steps
use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;

final class EmailVerificationEnabledCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return config('auth.email_verification', true);
    }
}

final class WelcomeEmailEnabledCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return config('mail.welcome_email', true);
    }
}

$result = Flowpipe::make()
    ->send($userData)
    ->through([
        new ValidateInputStep(),
        new CheckEmailUniquenessStep(),
        new CreateUserAccountStep(),
        ConditionalStep::when(
            new EmailVerificationEnabledCondition(),
            new SendVerificationEmailStep()
        ),
        new SetupUserProfileStep(),
        new AssignDefaultRoleStep(),
        ConditionalStep::when(
            new WelcomeEmailEnabledCondition(),
            new SendWelcomeEmailStep()
        ),
        new LogRegistrationEventStep(),
    ])
    ->thenReturn();

// Example 3: User registration with error handling and tracing
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

$tracer = new TestTracer();

try {
    $result = Flowpipe::make()
        ->send($userData)
        ->through([
            new ValidateInputStep(),
            new CheckEmailUniquenessStep(),
            new CreateUserAccountStep(),
            new SendVerificationEmailStep(),
            new SetupUserProfileStep(),
            new AssignDefaultRoleStep(),
            new SendWelcomeEmailStep(),
            new LogRegistrationEventStep(),
        ])
        ->withTracer($tracer)
        ->thenReturn();

    echo "Registration completed successfully!\n";
    echo 'Steps executed: '.$tracer->count()."\n";
    echo 'First step: '.$tracer->firstStep()."\n";
    echo 'Last step: '.$tracer->lastStep()."\n";

} catch (Exception $e) {
    echo 'Registration failed at step: '.$tracer->lastStep()."\n";
    echo 'Error: '.$e->getMessage()."\n";
}
