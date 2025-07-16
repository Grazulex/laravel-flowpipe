# Error Handling Strategies

Laravel Flowpipe provides sophisticated error handling capabilities through customizable strategies. This allows you to handle different types of errors in different ways: retry transient failures, fallback to alternative solutions, or compensate for failed operations.

## Overview

The error handling system is built around the concept of strategies that can be combined to create robust error handling patterns:

- **Retry Strategy**: Automatically retry failed operations with configurable delays
- **Fallback Strategy**: Provide alternative results when operations fail
- **Compensation Strategy**: Execute rollback or cleanup operations
- **Composite Strategy**: Combine multiple strategies for comprehensive error handling

## Basic Usage

### Retry Strategy

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Simple retry with exponential backoff
$result = Flowpipe::make()
    ->send($data)
    ->exponentialBackoff(3, 100, 2.0) // 3 attempts, 100ms base delay, 2x multiplier
    ->through([
        fn($data, $next) => $next(unreliableApiCall($data)),
    ])
    ->thenReturn();

// Linear backoff
$result = Flowpipe::make()
    ->send($data)
    ->linearBackoff(3, 100, 50) // 3 attempts, 100ms base, 50ms increment
    ->through([
        fn($data, $next) => $next(unreliableApiCall($data)),
    ])
    ->thenReturn();
```

### Fallback Strategy

```php
// Simple fallback with default value
$result = Flowpipe::make()
    ->send($data)
    ->withFallback(fn($payload, $error) => ['status' => 'cached', 'data' => $cachedData])
    ->through([
        fn($data, $next) => $next(fetchFromApi($data)),
    ])
    ->thenReturn();

// Fallback for specific exceptions
$result = Flowpipe::make()
    ->send($data)
    ->fallbackOnException(NetworkException::class, fn($payload, $error) => getCachedData($payload))
    ->through([
        fn($data, $next) => $next(fetchFromApi($data)),
    ])
    ->thenReturn();
```

### Compensation Strategy

```php
// Compensation (rollback) on failure
$result = Flowpipe::make()
    ->send($orderData)
    ->withCompensation(function ($payload, $error, $context) {
        // Rollback order creation
        Order::where('id', $payload['order_id'])->delete();
        return array_merge($payload, ['compensated' => true]);
    })
    ->through([
        fn($data, $next) => $next(processOrder($data)),
    ])
    ->thenReturn();

// Compensation for specific exceptions
$result = Flowpipe::make()
    ->send($data)
    ->compensateOnException(PaymentException::class, fn($payload, $error) => refundPayment($payload))
    ->through([
        fn($data, $next) => $next(processPayment($data)),
    ])
    ->thenReturn();
```

## Advanced Usage

### Composite Strategy

Combine multiple strategies to create comprehensive error handling:

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

$compositeStrategy = CompositeStrategy::make()
    ->retry(RetryStrategy::exponentialBackoff(3, 100, 2.0))
    ->fallback(FallbackStrategy::withDefault(['status' => 'cached']));

$result = Flowpipe::make()
    ->send($data)
    ->withErrorHandler($compositeStrategy)
    ->through([
        fn($data, $next) => $next(unreliableApiCall($data)),
    ])
    ->thenReturn();
```

### Custom Retry Logic

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

// Custom retry condition
$strategy = RetryStrategy::make(5, 100, function ($exception, $attempt) {
    // Only retry network errors
    if ($exception instanceof NetworkException) {
        return true;
    }
    
    // Retry rate limit errors with exponential backoff
    if ($exception instanceof RateLimitException) {
        sleep(pow(2, $attempt)); // Custom backoff
        return $attempt <= 3;
    }
    
    return false;
});

$result = Flowpipe::make()
    ->send($data)
    ->withRetryStrategy($strategy)
    ->through([
        fn($data, $next) => $next(apiCall($data)),
    ])
    ->thenReturn();
```

### Exception-Specific Handling

```php
// Handle different exceptions differently
$result = Flowpipe::make()
    ->send($data)
    ->fallbackOnException(ValidationException::class, function ($payload, $error) {
        return array_merge($payload, ['validation_errors' => $error->getErrors()]);
    })
    ->compensateOnException(DatabaseException::class, function ($payload, $error) {
        // Rollback database changes
        DB::rollBack();
        return array_merge($payload, ['database_rolled_back' => true]);
    })
    ->through([
        fn($data, $next) => $next(processData($data)),
    ])
    ->thenReturn();
```

## Error Handling Strategies

### RetryStrategy

Automatically retries failed operations with configurable delays and conditions.

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

// Basic retry
$strategy = RetryStrategy::make(3, 100); // 3 attempts, 100ms delay

// Exponential backoff
$strategy = RetryStrategy::exponentialBackoff(3, 100, 2.0);

// Linear backoff
$strategy = RetryStrategy::linearBackoff(3, 100, 50);

// Exception-specific retry
$strategy = RetryStrategy::forException(NetworkException::class, 5, 200);
```

### FallbackStrategy

Provides alternative results when operations fail.

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

// Default value fallback
$strategy = FallbackStrategy::withDefault(['status' => 'error']);

// Transform fallback
$strategy = FallbackStrategy::withTransform(fn($payload, $error) => [
    'original' => $payload,
    'error' => $error->getMessage(),
    'fallback' => true
]);

// Custom fallback handler
$strategy = FallbackStrategy::make(function ($payload, $error) {
    // Custom fallback logic
    return getCachedData($payload);
});

// Exception-specific fallback
$strategy = FallbackStrategy::forException(NetworkException::class, fn() => getCachedData());
```

### CompensationStrategy

Executes rollback or cleanup operations when failures occur.

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

// Basic compensation
$strategy = CompensationStrategy::make(function ($payload, $error, $context) {
    // Rollback logic
    rollbackTransaction($payload);
    return array_merge($payload, ['compensated' => true]);
});

// Rollback helper
$strategy = CompensationStrategy::rollback(function ($payload, $error) {
    DB::rollBack();
    return $payload;
});

// Cleanup helper
$strategy = CompensationStrategy::cleanup(function ($payload, $error) {
    cleanupResources($payload);
    return $payload;
});
```

## Real-World Examples

### E-commerce Order Processing

```php
$result = Flowpipe::make()
    ->send($orderData)
    
    // Retry payment processing
    ->exponentialBackoff(3, 200, 2.0)
    ->through([
        fn($data, $next) => $next(processPayment($data)),
    ])
    
    // Fallback to alternative payment method
    ->withFallback(function ($payload, $error) {
        return processAlternativePayment($payload);
    })
    ->through([
        fn($data, $next) => $next(createOrder($data)),
    ])
    
    // Compensate by refunding if fulfillment fails
    ->withCompensation(function ($payload, $error) {
        refundPayment($payload['payment_id']);
        return array_merge($payload, ['refunded' => true]);
    })
    ->through([
        fn($data, $next) => $next(fulfillOrder($data)),
    ])
    
    ->thenReturn();
```

### User Registration with Multiple Services

```php
$result = Flowpipe::make()
    ->send($userData)
    
    // Retry user creation
    ->exponentialBackoff(3, 100, 2.0)
    ->through([
        fn($data, $next) => $next(createUser($data)),
    ])
    
    // Fallback to guest user if profile creation fails
    ->withFallback(function ($payload, $error) {
        return array_merge($payload, ['profile_type' => 'guest']);
    })
    ->through([
        fn($data, $next) => $next(createUserProfile($data)),
    ])
    
    // Compensate by deleting user if email sending fails
    ->withCompensation(function ($payload, $error) {
        deleteUser($payload['user_id']);
        return array_merge($payload, ['user_deleted' => true]);
    })
    ->through([
        fn($data, $next) => $next(sendWelcomeEmail($data)),
    ])
    
    ->thenReturn();
```

## Error Context

All error handling strategies provide rich context about the error:

```php
$strategy = FallbackStrategy::make(function ($payload, $error) {
    // Error context is available
    logger()->error('Fallback triggered', [
        'error' => $error->getMessage(),
        'payload' => $payload,
        'timestamp' => now(),
    ]);
    
    return getFallbackData($payload);
});
```

## Best Practices

1. **Use Appropriate Strategies**: Choose the right strategy for each type of error
2. **Combine Strategies**: Use composite strategies for comprehensive error handling
3. **Log Errors**: Always log errors for debugging and monitoring
4. **Test Error Paths**: Test all error handling paths in your application
5. **Set Reasonable Limits**: Don't retry indefinitely - set appropriate limits
6. **Monitor Performance**: Error handling can impact performance - monitor accordingly

## Configuration

You can configure error handling globally in your application's service container:

```php
// In a service provider
$this->app->bind('flowpipe.error_handler', function () {
    return CompositeStrategy::make()
        ->retry(RetryStrategy::exponentialBackoff(3, 100, 2.0))
        ->fallback(FallbackStrategy::withDefault(['status' => 'error']));
});
```

Then use it in your flows:

```php
$result = Flowpipe::make()
    ->send($data)
    ->withErrorHandler(app('flowpipe.error_handler'))
    ->through($steps)
    ->thenReturn();
```

This comprehensive error handling system makes your workflows more robust and reliable by providing multiple layers of error recovery and handling strategies.
