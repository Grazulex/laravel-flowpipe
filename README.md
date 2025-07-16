# Laravel Flowpipe

<p align="center">
  <img src="logo.png" alt="Laravel Flowpipe" width="100">
</p>

<p align="center">
  <strong>Composable, traceable and declarative Flow Pipelines for Laravel. A modern alternative to Laravel's Pipeline, with support for conditional steps, nested flows, tracing, validation, and more.</strong>
</p>

<p align="center">
  <a href="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml"><img src="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml"><img src="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml/badge.svg" alt="Code Quality"></a>
  <a href="https://packagist.org/packages/grazulex/laravel-flowpipe"><img src="https://img.shields.io/packagist/v/grazulex/laravel-flowpipe.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/grazulex/laravel-flowpipe"><img src="https://img.shields.io/packagist/dt/grazulex/laravel-flowpipe.svg?style=flat-square" alt="Total Downloads"></a>
</p>


## Features

✨ **Fluent API** - Chainable, expressive syntax  
🔄 **Flexible Steps** - Support for closures, classes, and custom steps  
🎯 **Conditional Logic** - Built-in conditional step execution with dot notation  
📊 **Tracing & Debugging** - Track execution flow and performance  
🧪 **Test-Friendly** - Built-in test tracer for easy testing  
🚀 **Laravel Integration** - Seamless service provider integration  
⚡ **Performance** - Optimized for speed and memory efficiency  
📋 **YAML Flows** - Define flows in YAML for easy configuration  
🎨 **Artisan Commands** - Full CLI support for flow management  
✅ **Flow Validation** - Validate flow definitions with comprehensive error reporting  
📈 **Export & Documentation** - Export to JSON, Mermaid, and Markdown  
🔗 **Step Groups** - Reusable, named collections of steps  
🎯 **Nested Flows** - Create isolated sub-workflows for complex logic  
🛡️ **Advanced Error Handling** - Comprehensive error handling with retry, fallback, and compensation strategies  
🔄 **Retry Strategies** - Exponential and linear backoff, custom retry logic  
🎯 **Fallback Patterns** - Graceful degradation with fallback mechanisms  
🔧 **Compensation** - Automatic rollback and cleanup operations  
🎨 **Composite Strategies** - Combine multiple error handling approaches  

## Requirements

- PHP 8.3+
- Laravel 12.0+

## Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-flowpipe
```

The service provider will be automatically registered thanks to Laravel's package auto-discovery.

## Quick Start

### Basic Pipeline

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::make()
    ->send('Hello World')
    ->through([
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

### Error Handling with Retry

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Exponential backoff retry
$result = Flowpipe::make()
    ->send(['api_url' => 'https://api.example.com/data'])
    ->exponentialBackoff(3, 100, 2.0) // 3 attempts, 100ms base delay, 2x multiplier
    ->through([
        fn($data, $next) => $next(callExternalAPI($data['api_url'])),
        fn($data, $next) => $next(processAPIResponse($data)),
    ])
    ->thenReturn();

// Linear backoff retry
$result = Flowpipe::make()
    ->send($userData)
    ->linearBackoff(3, 100, 50) // 3 attempts, 100ms base + 50ms increment
    ->through([
        fn($data, $next) => $next(saveToDatabase($data)),
    ])
    ->thenReturn();
```

### Fallback Strategies

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Simple fallback with default value
$result = Flowpipe::make()
    ->send(['user_id' => 123])
    ->withFallback(fn($payload, $error) => ['cached_data' => true, 'user_id' => $payload['user_id']])
    ->through([
        fn($data, $next) => $next(fetchUserProfile($data['user_id'])),
    ])
    ->thenReturn();

// Exception-specific fallback
$result = Flowpipe::make()
    ->send($orderData)
    ->fallbackOnException(NetworkException::class, fn($payload, $error) => getCachedOrderData($payload))
    ->through([
        fn($data, $next) => $next(fetchOrderFromAPI($data)),
    ])
    ->thenReturn();
```

### Compensation (Rollback) Strategies

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Automatic rollback on failure
$result = Flowpipe::make()
    ->send($transactionData)
    ->withCompensation(function ($payload, $error, $context) {
        // Rollback the transaction
        rollbackTransaction($payload['transaction_id']);
        return array_merge($payload, ['rolled_back' => true]);
    })
    ->through([
        fn($data, $next) => $next(processTransaction($data)),
    ])
    ->thenReturn();

// Exception-specific compensation
$result = Flowpipe::make()
    ->send($paymentData)
    ->compensateOnException(PaymentException::class, fn($payload, $error) => refundPayment($payload))
    ->through([
        fn($data, $next) => $next(chargePayment($data)),
    ])
    ->thenReturn();
```

### Composite Error Handling

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

// Combine multiple strategies
$compositeStrategy = CompositeStrategy::make()
    ->retry(RetryStrategy::exponentialBackoff(3, 100, 2.0))
    ->fallback(FallbackStrategy::withDefault(['status' => 'cached']));

$result = Flowpipe::make()
    ->send($data)
    ->withErrorHandler($compositeStrategy)
    ->through([
        fn($data, $next) => $next(unreliableOperation($data)),
    ])
    ->thenReturn();
```

### Conditional Steps

```php
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['active'] ?? false);
    }
}

$result = Flowpipe::make()
    ->send(['active' => true, 'name' => 'John'])
    ->through([
        fn($data, $next) => $next($data['name']),
        ConditionalStep::when(
            new IsActiveCondition(),
            fn($name, $next) => $next(strtoupper($name))
        ),
        ConditionalStep::unless(
            new IsActiveCondition(),
            fn($name, $next) => $next(strtolower($name))
        ),
    ])
    ->thenReturn();

// Result: "JOHN"
```

### Step Groups & Nested Flows

Laravel Flowpipe supports reusable step groups and nested flows for better organization and modularity.

#### Step Groups

Define reusable groups of steps:

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Define reusable step groups
Flowpipe::group('text-processing', [
    fn($data, $next) => $next(trim($data)),
    fn($data, $next) => $next(strtoupper($data)),
    fn($data, $next) => $next(str_replace(' ', '-', $data)),
]);

Flowpipe::group('validation', [
    fn($data, $next) => $next(strlen($data) > 0 ? $data : throw new InvalidArgumentException('Empty data')),
    fn($data, $next) => $next(preg_match('/^[A-Z-]+$/', $data) ? $data : throw new InvalidArgumentException('Invalid format')),
]);

// Use groups in flows
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->useGroup('text-processing')
    ->useGroup('validation')
    ->through([
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

#### Nested Flows

Create isolated sub-workflows:

```php
$result = Flowpipe::make()
    ->send('hello world')
    ->nested([
        // This nested flow runs independently
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
    ])
    ->through([
        // Main flow continues with nested result
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

#### Combining Groups and Nested Flows

```php
// Define processing groups
Flowpipe::group('user-validation', [
    fn($user, $next) => $next(filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user : throw new InvalidArgumentException('Invalid email')),
    fn($user, $next) => $next(strlen($user['name']) > 0 ? $user : throw new InvalidArgumentException('Name required')),
]);

Flowpipe::group('notifications', [
    fn($user, $next) => $next(array_merge($user, ['email_sent' => true])),
    fn($user, $next) => $next(array_merge($user, ['logged' => true])),
]);

$result = Flowpipe::make()
    ->send(['email' => 'user@example.com', 'name' => 'John Doe'])
    ->useGroup('user-validation')
    ->nested([
        // Complex processing in isolation
        fn($user, $next) => $next(array_merge($user, ['id' => uniqid()])),
        fn($user, $next) => $next(array_merge($user, ['created_at' => now()])),
    ])
    ->useGroup('notifications')
    ->thenReturn();

// Result: Complete user array with validation, processing, and notifications
```

### YAML Flow Definitions

Create flow definitions in YAML for easy configuration, including groups and nested flows:

```yaml
# flow_definitions/user_processing.yaml
flow: UserProcessingFlow
description: Process user data with validation and notifications

send:
  name: "John Doe"
  email: "john@example.com"
  is_active: true

steps:
  # Use a pre-defined group
  - type: group
    name: user-validation
    
  # Create a nested flow
  - type: nested
    steps:
      - type: closure
        action: append
        value: "_processed"
      - condition:
          field: is_active
          operator: equals
          value: true
        then:
          - type: closure
            action: uppercase
        else:
          - type: closure
            action: lowercase
            
  # Use another group
  - type: group
    name: notifications
```

Define groups in separate YAML files:

```yaml
# groups/user-validation.yaml
group: user-validation
description: Validate user data
steps:
  - type: closure
    action: validate_email
  - type: closure
    action: validate_name
```

### Artisan Commands

Laravel Flowpipe comes with powerful Artisan commands:

```bash
# List all available flows
php artisan flowpipe:list
php artisan flowpipe:list --detailed

# Validate flow definitions
php artisan flowpipe:validate --all
php artisan flowpipe:validate --path=user-registration.yaml
php artisan flowpipe:validate --all --format=json

# Run a flow
php artisan flowpipe:run user_processing
php artisan flowpipe:run user_processing --payload='{"name":"John","email":"john@example.com"}'

# Export flows to different formats with enhanced group colors
php artisan flowpipe:export user_processing --format=json
php artisan flowpipe:export user_processing --format=mermaid
php artisan flowpipe:export user_processing --format=md --output=docs/user_processing.md

# Export groups with enhanced color styling
php artisan flowpipe:export user-validation --type=group --format=mermaid
php artisan flowpipe:export notifications --type=group --format=md

# Create new flows
php artisan flowpipe:make-flow NewUserFlow --template=basic
php artisan flowpipe:make-flow ComplexFlow --template=conditional
php artisan flowpipe:make-flow AdvancedFlow --template=advanced

# Generate step classes
php artisan flowpipe:make-step ProcessUserStep
```

### Enhanced Mermaid Export with Group Colors

Laravel Flowpipe now supports enhanced Mermaid diagrams with rich color coding for different step types:

- **Groups**: Blue theme (📦 Group elements)
- **Nested Flows**: Green theme (🔄 Nested elements)
- **Conditional Steps**: Orange theme (❓ Conditional elements)
- **Transform Steps**: Pink theme (🔄 Transform elements)
- **Validation Steps**: Green theme (✅ Validation elements)
- **Cache Steps**: Yellow theme (💾 Cache elements)
- **Batch Steps**: Purple theme (📊 Batch elements)
- **Retry Steps**: Red theme (🔄 Retry elements)

## Documentation

For detailed documentation, examples, and advanced usage, please see:

- 📚 [Full Documentation](docs/README.md)
- 🎯 [Examples](examples/README.md)
- 🔧 [Configuration](docs/configuration.md)
- 🧪 [Testing](docs/testing.md)
- 🎨 [Artisan Commands](docs/commands.md)
- 🛡️ [Error Handling](docs/error-handling.md)
- 🔄 [Queue Integration](docs/queues.md)
- 📖 [Error Handling Usage Guide](docs/error-handling-usage.md)
- 🎯 [Usage Examples](docs/usage-examples.md)

## Examples

### Basic Text Processing

```php
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->through([
        fn($text, $next) => $next(trim($text)),
        fn($text, $next) => $next(ucwords($text)),
        fn($text, $next) => $next(str_replace(' ', '-', $text)),
    ])
    ->thenReturn();

// Result: "Hello-World"
```

### User Registration Flow

```php
use App\Flowpipe\Steps\ValidateUserStep;
use App\Flowpipe\Steps\SendWelcomeEmailStep;
use App\Flowpipe\Steps\AddToCrmStep;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['is_active'] ?? false);
    }
}

$user = Flowpipe::make()
    ->send($userData)
    ->through([
        new ValidateUserStep(),
        ConditionalStep::when(
            new IsActiveCondition(),
            new SendWelcomeEmailStep()
        ),
        ConditionalStep::when(
            new IsActiveCondition(),
            new AddToCrmStep()
        ),
    ])
    ->withTracer(new BasicTracer())
    ->thenReturn();
```

### Complex Conditional Logic

```php
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;

class IsAdminCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['role'] ?? '') === 'admin';
    }
}

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['active'] ?? false);
    }
}

$result = Flowpipe::make()
    ->send(['user' => ['role' => 'admin', 'active' => true]])
    ->through([
        fn($data, $next) => $next($data['user']),
        ConditionalStep::when(
            new IsAdminCondition(),
            fn($user, $next) => $next(array_merge($user, ['permissions' => ['read', 'write', 'delete']]))
        ),
        ConditionalStep::when(
            new IsActiveCondition(),
            fn($user, $next) => $next(array_merge($user, ['status' => 'enabled']))
        ),
        ConditionalStep::unless(
            new IsActiveCondition(),
            fn($user, $next) => $next(array_merge($user, ['status' => 'disabled']))
        ),
    ])
    ->thenReturn();
```

### Error Handling in Production Workflows

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

// Production-ready order processing with comprehensive error handling
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
        fn($data, $next) => $next(validateOrder($data)),
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
    
    // Step 3: Update inventory with fallback to manual processing
    ->withFallback(function ($payload, $error) {
        // Queue for manual inventory processing
        QueueManualInventoryUpdate::dispatch($payload);
        return array_merge($payload, ['inventory_queued' => true]);
    })
    ->through([
        fn($data, $next) => $next(updateInventory($data)),
    ])
    
    // Step 4: Send confirmation with retry
    ->exponentialBackoff(3, 100, 2.0)
    ->through([
        fn($data, $next) => $next(sendOrderConfirmation($data)),
    ])
    
    ->thenReturn();
```

### Custom Error Handling Strategies

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

// Custom retry logic based on exception type
$customRetryStrategy = RetryStrategy::make(5, 100, function ($exception, $attempt) {
    // Only retry network errors
    if ($exception instanceof NetworkException) {
        return true;
    }
    
    // Retry rate limit errors with exponential backoff
    if ($exception instanceof RateLimitException) {
        sleep(pow(2, $attempt)); // Custom backoff
        return $attempt <= 3;
    }
    
    // Don't retry validation errors
    if ($exception instanceof ValidationException) {
        return false;
    }
    
    return $attempt <= 2; // Default retry for other errors
});

$result = Flowpipe::make()
    ->send($data)
    ->withRetryStrategy($customRetryStrategy)
    ->through([
        fn($data, $next) => $next(complexApiCall($data)),
    ])
    ->thenReturn();
```

## Testing

Laravel Flowpipe includes a dedicated test tracer for easy testing:

```php
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

public function test_user_processing_flow()
{
    $tracer = new TestTracer();
    
    $result = Flowpipe::make()
        ->send(['name' => 'John'])
        ->through([
            fn($data, $next) => $next(strtoupper($data['name'])),
        ])
        ->withTracer($tracer)
        ->thenReturn();
    
    $this->assertEquals('JOHN', $result);
    $this->assertCount(1, $tracer->count());
}
```

## Performance

Laravel Flowpipe is optimized for performance:

- **Lazy Evaluation**: Steps are only executed when needed
- **Memory Efficient**: Minimal memory footprint
- **Traceable**: Optional tracing with minimal overhead
- **Cacheable**: Flow definitions can be cached for better performance

## API Reference

### Flowpipe Methods

- `make()` - Create a new flowpipe instance
- `send($data)` - Set initial data
- `through(array $steps)` - Add steps to the pipeline
- `useGroup(string $name)` - Add a predefined group to the pipeline
- `nested(array $steps)` - Create a nested flow
- `cache($key, $ttl, $store)` - Add cache step
- `retry($maxAttempts, $delayMs, $shouldRetry)` - Add retry step
- `rateLimit($key, $maxAttempts, $decayMinutes, $keyGenerator)` - Add rate limit step
- `transform($transformer)` - Add transform step
- `validate($rules, $messages, $customAttributes)` - Add validation step
- `batch($batchSize, $preserveKeys)` - Add batch step
- `withTracer(Tracer $tracer)` - Add a tracer
- `thenReturn()` - Execute and return result
- `context()` - Get flow context

### Error Handling Methods

- `withErrorHandler(ErrorHandlerStrategy $strategy, int $maxAttempts = 3)` - Add custom error handler
- `withRetryStrategy(RetryStrategy $strategy)` - Add retry strategy
- `withFallback(Closure $fallbackHandler, ?Closure $shouldFallback = null)` - Add fallback handling
- `withCompensation(Closure $compensationHandler, ?Closure $shouldCompensate = null)` - Add compensation handling
- `withCompositeErrorHandler(array $strategies = [])` - Add composite error handling
- `exponentialBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, float $multiplier = 2.0, ?Closure $shouldRetry = null)` - Add exponential backoff retry
- `linearBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, int $increment = 100, ?Closure $shouldRetry = null)` - Add linear backoff retry
- `fallbackOnException(string $exceptionClass, Closure $fallbackHandler)` - Add exception-specific fallback
- `compensateOnException(string $exceptionClass, Closure $compensationHandler)` - Add exception-specific compensation

### Static Methods

- `group(string $name, array $steps)` - Define a reusable step group
- `hasGroup(string $name)` - Check if a group exists
- `getGroups()` - Get all registered groups
- `clearGroups()` - Clear all registered groups (useful for testing)

### Conditional Steps

- `ConditionalStep::when($condition, $step)` - Execute step when condition is true
- `ConditionalStep::unless($condition, $step)` - Execute step when condition is false

### Error Handling Strategies

#### RetryStrategy
- `RetryStrategy::make(int $maxAttempts = 3, int $delayMs = 100, ?Closure $shouldRetry = null, ?Closure $delayCalculator = null)` - Basic retry
- `RetryStrategy::exponentialBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, float $multiplier = 2.0, ?Closure $shouldRetry = null)` - Exponential backoff
- `RetryStrategy::linearBackoff(int $maxAttempts = 3, int $baseDelayMs = 100, int $increment = 100, ?Closure $shouldRetry = null)` - Linear backoff
- `RetryStrategy::forException(string $exceptionClass, int $maxAttempts = 3, int $delayMs = 100)` - Exception-specific retry

#### FallbackStrategy
- `FallbackStrategy::make(Closure $fallbackHandler, ?Closure $shouldFallback = null)` - Custom fallback
- `FallbackStrategy::withDefault(mixed $defaultValue, ?Closure $shouldFallback = null)` - Default value fallback
- `FallbackStrategy::withTransform(Closure $transformer, ?Closure $shouldFallback = null)` - Transform fallback
- `FallbackStrategy::withPayload(mixed $fallbackPayload, ?Closure $shouldFallback = null)` - Payload fallback
- `FallbackStrategy::forException(string $exceptionClass, Closure $fallbackHandler)` - Exception-specific fallback

#### CompensationStrategy
- `CompensationStrategy::make(Closure $compensationHandler, ?Closure $shouldCompensate = null)` - Basic compensation
- `CompensationStrategy::rollback(Closure $rollbackHandler, ?Closure $shouldCompensate = null)` - Rollback compensation
- `CompensationStrategy::cleanup(Closure $cleanupHandler, ?Closure $shouldCompensate = null)` - Cleanup compensation
- `CompensationStrategy::forException(string $exceptionClass, Closure $compensationHandler)` - Exception-specific compensation

#### CompositeStrategy
- `CompositeStrategy::make(array $strategies = [])` - Create composite strategy
- `CompositeStrategy::addStrategy(ErrorHandlerStrategy $strategy)` - Add strategy to composite
- `CompositeStrategy::retry(RetryStrategy $strategy)` - Add retry strategy
- `CompositeStrategy::fallback(FallbackStrategy $strategy)` - Add fallback strategy
- `CompositeStrategy::compensate(CompensationStrategy $strategy)` - Add compensation strategy

### Tracer Methods

- `trace($stepClass, $before, $after, $duration)` - Trace step execution
- `all()` - Get all trace logs
- `steps()` - Get all step names
- `count()` - Get number of traced steps
- `firstStep()` - Get first step name
- `lastStep()` - Get last step name
- `clear()` - Clear all traces

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email jms@grazulex.be instead of using the issue tracker.

## Changelog

Please see [RELEASES.md](RELEASES.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Jean-Marc Strauven](https://github.com/Grazulex)
- [All Contributors](../../contributors)

## Support

- 🐛 [Report Issues](https://github.com/Grazulex/laravel-flowpipe/issues)
- 💬 [Discussions](https://github.com/Grazulex/laravel-flowpipe/discussions)
- 📚 [Documentation](https://github.com/Grazulex/laravel-flowpipe/wiki)

---

**Laravel Flowpipe** is a modern, powerful alternative to Laravel's built-in Pipeline with enhanced features for complex workflow management.
