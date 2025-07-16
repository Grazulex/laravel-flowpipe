# Laravel Flowpipe Usage Examples

This document demonstrates the complete usage of Laravel Flowpipe with all available features, including comprehensive error handling patterns.

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

## Error Handling Patterns

### Basic Error Handling

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Retry with exponential backoff
$result = Flowpipe::make()
    ->send(['api_endpoint' => 'https://api.example.com/data'])
    ->exponentialBackoff(3, 100, 2.0) // 3 attempts, 100ms base delay, 2x multiplier
    ->through([
        fn($data, $next) => $next(file_get_contents($data['api_endpoint'])),
    ])
    ->thenReturn();

// Fallback to cached data
$result = Flowpipe::make()
    ->send(['user_id' => 123])
    ->withFallback(fn($payload, $error) => Cache::get("user_{$payload['user_id']}", []))
    ->through([
        fn($data, $next) => $next(fetchUserFromDatabase($data['user_id'])),
    ])
    ->thenReturn();
```

### Production-Ready Error Handling

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

// E-commerce order processing with comprehensive error handling
$orderResult = Flowpipe::make()
    ->send($orderData)
    
    // Step 1: Validate order with fallback
    ->withFallback(function ($payload, $error) {
        Log::warning('Order validation failed, using basic validation', [
            'order_id' => $payload['order_id'],
            'error' => $error->getMessage()
        ]);
        return array_merge($payload, ['validation_mode' => 'basic']);
    })
    ->through([
        fn($data, $next) => $next(validateOrderComprehensive($data)),
    ])
    
    // Step 2: Process payment with retry and compensation
    ->withErrorHandler(
        CompositeStrategy::make()
            ->retry(RetryStrategy::exponentialBackoff(3, 200, 2.0))
            ->compensate(CompensationStrategy::make(function ($payload, $error, $context) {
                // Rollback any partial payment processing
                if (isset($payload['payment_intent_id'])) {
                    cancelPaymentIntent($payload['payment_intent_id']);
                }
                return array_merge($payload, ['payment_cancelled' => true]);
            }))
    )
    ->through([
        fn($data, $next) => $next(processPayment($data)),
    ])
    
    // Step 3: Update inventory with fallback
    ->withFallback(function ($payload, $error) {
        // Queue for manual inventory processing
        QueueManualInventoryUpdate::dispatch($payload);
        return array_merge($payload, ['inventory_queued' => true]);
    })
    ->through([
        fn($data, $next) => $next(updateInventory($data)),
    ])
    
    ->thenReturn();
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