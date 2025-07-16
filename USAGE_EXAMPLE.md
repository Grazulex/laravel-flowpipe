# Laravel Flowpipe Usage Example

This example demonstrates the complete usage of Laravel Flowpipe with all available features.

## Basic Usage

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;

// Simple text processing
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->through([
        fn($text, $next) => $next(trim($text)),
        fn($text, $next) => $next(ucwords($text)),
        fn($text, $next) => $next(str_replace(' ', '-', $text)),
    ])
    ->thenReturn();

echo $result; // "Hello-World"
```

## Using Built-in Steps

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;

// Using built-in steps
$result = Flowpipe::make()
    ->send(['email' => 'john@example.com', 'name' => 'John Doe'])
    ->validate([
        'email' => 'required|email',
        'name' => 'required|string|max:255',
    ])
    ->transform(fn($data) => array_merge($data, ['processed_at' => now()]))
    ->cache('user-data', 300)
    ->through([
        fn($data, $next) => $next(array_merge($data, ['saved' => true])),
    ])
    ->thenReturn();

var_dump($result);
```

## Using Conditional Steps

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;

class IsAdminCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['role'] ?? '') === 'admin';
    }
}

$result = Flowpipe::make()
    ->send(['name' => 'John', 'role' => 'admin'])
    ->through([
        ConditionalStep::when(
            new IsAdminCondition(),
            fn($data, $next) => $next(array_merge($data, ['permissions' => ['read', 'write', 'delete']]))
        ),
        ConditionalStep::unless(
            new IsAdminCondition(),
            fn($data, $next) => $next(array_merge($data, ['permissions' => ['read']]))
        ),
    ])
    ->thenReturn();

var_dump($result);
```

## Using Custom Steps

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

class ValidateUserStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Valid email is required');
        }

        return $next(array_merge($payload, ['validated' => true]));
    }
}

class CreateUserStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        // Simulate user creation
        $payload['id'] = rand(1000, 9999);
        $payload['created_at'] = now();
        
        return $next($payload);
    }
}

$result = Flowpipe::make()
    ->send(['email' => 'john@example.com', 'name' => 'John Doe'])
    ->through([
        new ValidateUserStep(),
        new CreateUserStep(),
    ])
    ->thenReturn();

var_dump($result);
```

## Using Tracers

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

$tracer = new TestTracer();

$result = Flowpipe::make()
    ->send(['data' => 'test'])
    ->through([
        fn($data, $next) => $next(array_merge($data, ['step1' => true])),
        fn($data, $next) => $next(array_merge($data, ['step2' => true])),
    ])
    ->withTracer($tracer)
    ->thenReturn();

echo "Steps executed: " . $tracer->count() . "\n";
echo "First step: " . $tracer->firstStep() . "\n";
echo "Last step: " . $tracer->lastStep() . "\n";

// Get all logs
$logs = $tracer->all();
foreach ($logs as $log) {
    echo "Step: {$log['step']}\n";
    echo "Duration: {$log['duration']}ms\n";
    echo "Before: " . json_encode($log['before']) . "\n";
    echo "After: " . json_encode($log['after']) . "\n";
    echo "---\n";
}
```

## Error Handling

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

class FailingStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (rand(1, 10) > 5) {
            throw new \RuntimeException('Random failure');
        }
        
        return $next($payload);
    }
}

try {
    $result = Flowpipe::make()
        ->send(['data' => 'test'])
        ->retry(3, 100) // Retry up to 3 times with 100ms delay
        ->through([
            new FailingStep(),
        ])
        ->thenReturn();
    
    echo "Success: " . json_encode($result) . "\n";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
```

## Performance Monitoring

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\PerformanceTracer;

$tracer = new PerformanceTracer();

$result = Flowpipe::make()
    ->send(range(1, 1000))
    ->batch(100)
    ->through([
        fn($batch, $next) => $next(array_map(fn($x) => $x * 2, $batch)),
        fn($batch, $next) => $next(array_filter($batch, fn($x) => $x > 100)),
    ])
    ->withTracer($tracer)
    ->thenReturn();

echo "Total execution time: " . $tracer->getTotalTime() . "ms\n";
echo "Peak memory usage: " . $tracer->getPeakMemoryUsage() . " bytes\n";
```

## Using with Laravel Models

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class CreateUserStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => bcrypt($payload['password']),
        ]);

        return $next(array_merge($payload, ['user' => $user]));
    }
}

class SendWelcomeEmailStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        Mail::to($payload['user']->email)->send(new WelcomeEmail($payload['user']));
        
        return $next(array_merge($payload, ['email_sent' => true]));
    }
}

$result = Flowpipe::make()
    ->send([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
    ])
    ->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
    ])
    ->through([
        new CreateUserStep(),
        new SendWelcomeEmailStep(),
    ])
    ->thenReturn();

echo "User created: " . $result['user']->id . "\n";
echo "Welcome email sent: " . ($result['email_sent'] ? 'Yes' : 'No') . "\n";
```

## Running from Command Line

```bash
# Create a flow definition
php artisan flowpipe:make-flow user-registration

# Run the flow
php artisan flowpipe:run user-registration --payload='{"name":"John","email":"john@example.com"}'

# List all flows
php artisan flowpipe:list --detailed

# Export flow to different formats
php artisan flowpipe:export user-registration --format=json
php artisan flowpipe:export user-registration --format=mermaid
php artisan flowpipe:export user-registration --format=md --output=docs/user-registration.md
```

This comprehensive example shows how to use all the features of Laravel Flowpipe effectively.