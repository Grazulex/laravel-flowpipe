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

### Microservices Integration with Circuit Breaker

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

$circuitBreakerStrategy = CompositeStrategy::make()
    ->retry(RetryStrategy::exponentialBackoff(3, 100, 2.0))
    ->fallback(FallbackStrategy::make(function ($payload, $error) {
        // Circuit breaker - use cached data
        return getCachedData($payload['service_key']);
    }));

$result = Flowpipe::make()
    ->send(['service_key' => 'user_service', 'user_id' => 123])
    ->withErrorHandler($circuitBreakerStrategy)
    ->through([
        fn($data, $next) => $next(callUserMicroservice($data)),
    ])
    ->thenReturn();
```

### Database Transaction with Saga Pattern

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

$sagaStrategy = CompensationStrategy::make(function ($payload, $error, $context) {
    // Compensate by rolling back all operations
    foreach ($payload['operations'] as $operation) {
        $operation['rollback']();
    }
    return array_merge($payload, ['saga_compensated' => true]);
});

$result = Flowpipe::make()
    ->send($transactionData)
    ->withErrorHandler($sagaStrategy)
    ->through([
        fn($data, $next) => $next(step1Transaction($data)),
        fn($data, $next) => $next(step2Transaction($data)),
        fn($data, $next) => $next(step3Transaction($data)),
    ])
    ->thenReturn();
```

### File Processing with Cleanup

```php
$result = Flowpipe::make()
    ->send($fileData)
    
    // Retry file processing
    ->exponentialBackoff(3, 500, 2.0)
    ->through([
        fn($data, $next) => $next(processLargeFile($data)),
    ])
    
    // Compensate by cleaning up temporary files
    ->withCompensation(function ($payload, $error, $context) {
        cleanupTemporaryFiles($payload['temp_files']);
        return array_merge($payload, ['cleanup_performed' => true]);
    })
    ->through([
        fn($data, $next) => $next(finalizeProcessing($data)),
    ])
    
    ->thenReturn();
```

### API Rate Limiting with Backoff

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

// Custom retry strategy for rate limiting
$rateLimitStrategy = RetryStrategy::make(5, 100, function ($exception, $attempt) {
    if ($exception instanceof RateLimitException) {
        // Extract retry-after header or use exponential backoff
        $delay = $exception->getRetryAfter() ?? (100 * pow(2, $attempt - 1));
        usleep($delay * 1000);
        return true;
    }
    return false;
});

$result = Flowpipe::make()
    ->send($apiRequest)
    ->withRetryStrategy($rateLimitStrategy)
    ->through([
        fn($data, $next) => $next(makeAPIRequest($data)),
    ])
    ->thenReturn();
```

### Multi-Step User Registration

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

## Monitoring and Debugging

### Error Context and Logging

All error handling strategies provide rich context for monitoring and debugging:

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Illuminate\Support\Facades\Log;

$strategy = FallbackStrategy::make(function ($payload, $error) {
    // Log error with context
    Log::error('Fallback strategy activated', [
        'error_message' => $error->getMessage(),
        'error_type' => get_class($error),
        'payload' => $payload,
        'timestamp' => now()->toISOString(),
        'trace' => $error->getTraceAsString(),
    ]);
    
    return getFallbackData($payload);
});
```

### Retry Monitoring

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

$retryStrategy = RetryStrategy::exponentialBackoff(3, 100, 2.0, function ($exception, $attempt) {
    // Log retry attempts
    Log::warning("Retry attempt {$attempt} for {$exception->getMessage()}");
    
    // Only retry specific exceptions
    return $exception instanceof TransientException;
});
```

### Compensation Tracking

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

$compensationStrategy = CompensationStrategy::make(function ($payload, $error, $context) {
    // Log compensation activity
    Log::critical('Compensation strategy activated', [
        'original_error' => $error->getMessage(),
        'compensation_context' => $context,
        'payload' => $payload,
        'timestamp' => now()->toISOString(),
    ]);
    
    // Perform compensation
    performRollback($payload);
    
    // Return enriched payload
    return array_merge($payload, [
        'compensation_timestamp' => now()->toISOString(),
        'compensation_reason' => $error->getMessage(),
    ]);
});
```

### Performance Monitoring

```php
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\PerformanceTracer;

// Use performance tracer to monitor error handling overhead
$result = Flowpipe::performance()
    ->send($data)
    ->exponentialBackoff(3, 100, 2.0)
    ->through([
        fn($data, $next) => $next(expensiveOperation($data)),
    ])
    ->thenReturn();

// Access performance metrics
$tracer = Flowpipe::performance()->context()->tracer();
$metrics = $tracer->all();
```

### Integration with Monitoring Systems

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

// Custom monitoring wrapper
class MonitoredCompositeStrategy extends CompositeStrategy 
{
    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        // Send metrics to monitoring system
        app('monitoring')->increment('flowpipe.error_handling.attempts', [
            'error_type' => get_class($error),
            'attempt_number' => $attemptNumber,
        ]);
        
        $startTime = microtime(true);
        $result = parent::handle($error, $payload, $attemptNumber, $context);
        $duration = microtime(true) - $startTime;
        
        // Track error handling performance
        app('monitoring')->timing('flowpipe.error_handling.duration', $duration * 1000, [
            'action' => $result->action->value,
            'error_type' => get_class($error),
        ]);
        
        return $result;
    }
}
```

## Best Practices

1. **Choose Appropriate Strategies**: Select the right strategy for each error type
2. **Combine Strategies Wisely**: Use composite strategies for comprehensive coverage
3. **Set Reasonable Limits**: Don't retry indefinitely - balance resilience with performance
4. **Monitor Error Patterns**: Track error rates and patterns to identify system issues
5. **Test Error Paths**: Ensure all error handling paths are tested
6. **Use Structured Logging**: Log errors with consistent structure for better analysis
7. **Consider Circuit Breakers**: Implement circuit breaker patterns for external dependencies
8. **Resource Cleanup**: Always ensure resources are properly cleaned up in compensation strategies
9. **Graceful Degradation**: Provide meaningful fallback behavior
10. **Documentation**: Document your error handling strategies for team understanding

## Configuration and Customization

### Global Error Handler Configuration

```php
// In a service provider
$this->app->bind('flowpipe.default_error_handler', function () {
    return CompositeStrategy::make()
        ->retry(RetryStrategy::exponentialBackoff(3, 100, 2.0))
        ->fallback(FallbackStrategy::withDefault(['status' => 'error', 'fallback' => true]));
});

// Usage
$result = Flowpipe::make()
    ->send($data)
    ->withErrorHandler(app('flowpipe.default_error_handler'))
    ->through($steps)
    ->thenReturn();
```

### Custom Error Handler Factory

```php
class ErrorHandlerFactory
{
    public static function forExternalAPI(): CompositeStrategy
    {
        return CompositeStrategy::make()
            ->retry(RetryStrategy::exponentialBackoff(3, 200, 2.0))
            ->fallback(FallbackStrategy::make(fn($payload, $error) => getCachedData($payload)));
    }
    
    public static function forDatabase(): CompositeStrategy
    {
        return CompositeStrategy::make()
            ->retry(RetryStrategy::linearBackoff(5, 50, 25))
            ->compensate(CompensationStrategy::rollback(fn($payload, $error) => DB::rollBack()));
    }
    
    public static function forPayment(): CompositeStrategy
    {
        return CompositeStrategy::make()
            ->retry(RetryStrategy::exponentialBackoff(3, 300, 2.0))
            ->compensate(CompensationStrategy::make(fn($payload, $error) => refundPayment($payload)));
    }
}
```

### Environment-Specific Configuration

```php
// config/flowpipe.php
return [
    'error_handling' => [
        'default_retry_attempts' => env('FLOWPIPE_RETRY_ATTEMPTS', 3),
        'default_retry_delay' => env('FLOWPIPE_RETRY_DELAY', 100),
        'default_retry_multiplier' => env('FLOWPIPE_RETRY_MULTIPLIER', 2.0),
        'enable_fallback_logging' => env('FLOWPIPE_FALLBACK_LOGGING', true),
        'enable_compensation_logging' => env('FLOWPIPE_COMPENSATION_LOGGING', true),
    ],
];
```

This comprehensive error handling system provides the foundation for building resilient, maintainable workflows that can handle various failure scenarios gracefully while providing rich monitoring and debugging capabilities.
