# Error Handling Usage Guide

This guide provides practical examples and patterns for implementing error handling in Laravel Flowpipe applications.

## Quick Start

### Basic Error Handling

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Simple retry with exponential backoff
$result = Flowpipe::make()
    ->send($data)
    ->exponentialBackoff(3, 100, 2.0)
    ->through([
        fn($data, $next) => $next(unreliableOperation($data)),
    ])
    ->thenReturn();

// Fallback to default value
$result = Flowpipe::make()
    ->send($data)
    ->withFallback(fn($payload, $error) => ['status' => 'cached'])
    ->through([
        fn($data, $next) => $next(externalApiCall($data)),
    ])
    ->thenReturn();
```

## Common Patterns

### 1. Web API Integration

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;

// API with retry and fallback
$apiStrategy = CompositeStrategy::make()
    ->retry(RetryStrategy::exponentialBackoff(3, 200, 2.0))
    ->fallback(FallbackStrategy::make(function ($payload, $error) {
        // Use cached data if API fails
        return Cache::get('api_data_' . $payload['key'], ['status' => 'cached']);
    }));

$result = Flowpipe::make()
    ->send(['key' => 'user_123', 'endpoint' => '/api/users/123'])
    ->withErrorHandler($apiStrategy)
    ->through([
        fn($data, $next) => $next(Http::get($data['endpoint'])->json()),
    ])
    ->thenReturn();
```

### 2. Database Operations

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;
use Illuminate\Support\Facades\DB;

// Database transaction with rollback
$result = Flowpipe::make()
    ->send($transactionData)
    ->withCompensation(function ($payload, $error, $context) {
        // Rollback transaction on any failure
        DB::rollBack();
        Log::error('Transaction failed, rolled back', [
            'error' => $error->getMessage(),
            'payload' => $payload,
        ]);
        return array_merge($payload, ['rolled_back' => true]);
    })
    ->through([
        fn($data, $next) => $next(DB::transaction(fn() => createComplexData($data))),
    ])
    ->thenReturn();
```

### 3. File Processing

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

// File processing with retry and cleanup
$result = Flowpipe::make()
    ->send($fileData)
    
    // Retry file processing
    ->withRetryStrategy(RetryStrategy::exponentialBackoff(3, 500, 2.0))
    ->through([
        fn($data, $next) => $next(processLargeFile($data)),
    ])
    
    // Cleanup temporary files on failure
    ->withCompensation(function ($payload, $error, $context) {
        if (isset($payload['temp_files'])) {
            foreach ($payload['temp_files'] as $file) {
                @unlink($file);
            }
        }
        return array_merge($payload, ['cleanup_performed' => true]);
    })
    ->through([
        fn($data, $next) => $next(finalizeFile($data)),
    ])
    
    ->thenReturn();
```

### 4. Payment Processing

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;

// Payment with retry and refund compensation
$paymentStrategy = CompositeStrategy::make()
    ->retry(RetryStrategy::exponentialBackoff(3, 300, 2.0))
    ->compensate(CompensationStrategy::make(function ($payload, $error, $context) {
        // Refund payment if subsequent steps fail
        if (isset($payload['payment_id'])) {
            PaymentService::refund($payload['payment_id']);
        }
        return array_merge($payload, ['refunded' => true]);
    }));

$result = Flowpipe::make()
    ->send($paymentData)
    ->withErrorHandler($paymentStrategy)
    ->through([
        fn($data, $next) => $next(chargePayment($data)),
        fn($data, $next) => $next(createOrder($data)),
        fn($data, $next) => $next(updateInventory($data)),
    ])
    ->thenReturn();
```

## Advanced Patterns

### Exception-Specific Handling

```php
use App\Exceptions\{NetworkException, ValidationException, PaymentException};

$result = Flowpipe::make()
    ->send($data)
    
    // Handle network errors with retry
    ->withRetryStrategy(RetryStrategy::forException(NetworkException::class, 5, 100))
    
    // Handle validation errors with fallback
    ->fallbackOnException(ValidationException::class, function ($payload, $error) {
        return array_merge($payload, ['validation_errors' => $error->getErrors()]);
    })
    
    // Handle payment errors with compensation
    ->compensateOnException(PaymentException::class, function ($payload, $error) {
        // Specific compensation for payment failures
        return processPaymentFailure($payload, $error);
    })
    
    ->through($steps)
    ->thenReturn();
```

### Custom Retry Logic

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

$customRetry = RetryStrategy::make(5, 100, function ($exception, $attempt) {
    // Custom retry logic
    if ($exception instanceof RateLimitException) {
        // Use delay from rate limit header
        $delay = $exception->getRetryAfter() * 1000;
        usleep($delay);
        return $attempt <= 3;
    }
    
    if ($exception instanceof NetworkException) {
        // Retry network errors with exponential backoff
        $delay = 100 * pow(2, $attempt - 1);
        usleep($delay * 1000);
        return true;
    }
    
    // Don't retry validation errors
    return !($exception instanceof ValidationException);
});

$result = Flowpipe::make()
    ->send($data)
    ->withRetryStrategy($customRetry)
    ->through($steps)
    ->thenReturn();
```

### Circuit Breaker Pattern

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Illuminate\Support\Facades\Cache;

class CircuitBreakerStrategy extends CompositeStrategy
{
    private string $serviceName;
    private int $failureThreshold;
    private int $timeoutSeconds;
    
    public function __construct(string $serviceName, int $failureThreshold = 5, int $timeoutSeconds = 60)
    {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->timeoutSeconds = $timeoutSeconds;
        
        parent::__construct();
    }
    
    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        $key = "circuit_breaker_{$this->serviceName}";
        $failures = Cache::get($key, 0);
        
        if ($failures >= $this->failureThreshold) {
            // Circuit is open, use fallback immediately
            return ErrorHandlerResult::fallback(
                $this->getFallbackData($payload), 
                array_merge($context, ['circuit_breaker' => 'open'])
            );
        }
        
        // Try normal error handling
        $result = parent::handle($error, $payload, $attemptNumber, $context);
        
        if ($result->action === ErrorHandlerAction::FAIL) {
            // Increment failure count
            Cache::put($key, $failures + 1, $this->timeoutSeconds);
        } else {
            // Reset failure count on success
            Cache::forget($key);
        }
        
        return $result;
    }
    
    private function getFallbackData(mixed $payload): mixed
    {
        // Return cached data or default response
        return Cache::get("fallback_{$this->serviceName}", ['status' => 'degraded']);
    }
}

// Usage
$circuitBreaker = new CircuitBreakerStrategy('external_api', 5, 300);
$result = Flowpipe::make()
    ->send($data)
    ->withErrorHandler($circuitBreaker)
    ->through($steps)
    ->thenReturn();
```

## Monitoring and Observability

### Logging Error Handling

```php
use Illuminate\Support\Facades\Log;

$strategy = FallbackStrategy::make(function ($payload, $error) {
    // Structured logging
    Log::error('Fallback strategy activated', [
        'error_type' => get_class($error),
        'error_message' => $error->getMessage(),
        'payload_type' => is_object($payload) ? get_class($payload) : gettype($payload),
        'timestamp' => now()->toISOString(),
        'correlation_id' => request()->header('X-Correlation-ID'),
    ]);
    
    return getFallbackData($payload);
});
```

### Metrics Collection

```php
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;

class MetricsAwareCompositeStrategy extends CompositeStrategy
{
    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        $startTime = microtime(true);
        
        // Collect metrics
        app('metrics')->increment('flowpipe.error_handling.attempts', [
            'error_type' => get_class($error),
            'attempt_number' => $attemptNumber,
        ]);
        
        $result = parent::handle($error, $payload, $attemptNumber, $context);
        
        $duration = microtime(true) - $startTime;
        app('metrics')->timing('flowpipe.error_handling.duration', $duration * 1000, [
            'action' => $result->action->value,
            'error_type' => get_class($error),
        ]);
        
        return $result;
    }
}
```

## Testing Error Handling

### Unit Testing

```php
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;

class ErrorHandlingTest extends TestCase
{
    public function test_retry_strategy_works()
    {
        $attempts = 0;
        $strategy = RetryStrategy::make(3, 0);
        
        $result = Flowpipe::make()
            ->send('test')
            ->withRetryStrategy($strategy)
            ->through([
                function ($data, $next) use (&$attempts) {
                    $attempts++;
                    if ($attempts < 3) {
                        throw new RuntimeException('Simulated failure');
                    }
                    return $next('success');
                },
            ])
            ->thenReturn();
        
        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }
    
    public function test_fallback_strategy_works()
    {
        $result = Flowpipe::make()
            ->send('test')
            ->withFallback(fn($payload, $error) => 'fallback_result')
            ->through([
                fn($data, $next) => throw new RuntimeException('Always fails'),
            ])
            ->thenReturn();
        
        $this->assertEquals('fallback_result', $result);
    }
}
```

### Integration Testing

```php
use Grazulex\LaravelFlowpipe\Flowpipe;
use Illuminate\Support\Facades\Http;

class APIIntegrationTest extends TestCase
{
    public function test_api_error_handling()
    {
        // Mock external API failure
        Http::fake([
            'api.example.com/*' => Http::response(null, 500),
        ]);
        
        $result = Flowpipe::make()
            ->send(['user_id' => 123])
            ->exponentialBackoff(3, 100, 2.0)
            ->withFallback(fn($payload, $error) => ['cached' => true])
            ->through([
                fn($data, $next) => $next(Http::get("https://api.example.com/users/{$data['user_id']}")->json()),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['cached']);
    }
}
```

## Best Practices

1. **Strategy Selection**: Choose appropriate strategies for each error type
2. **Monitoring**: Always log error handling activities
3. **Testing**: Test all error paths thoroughly
4. **Performance**: Monitor error handling overhead
5. **Documentation**: Document error handling strategies for your team
6. **Graceful Degradation**: Provide meaningful fallback behavior
7. **Resource Management**: Clean up resources in compensation strategies
8. **Circuit Breakers**: Implement circuit breakers for external dependencies
9. **Correlation IDs**: Use correlation IDs for tracking across services
10. **Metrics**: Collect metrics on error rates and handling effectiveness

This guide provides a foundation for implementing robust error handling in your Laravel Flowpipe applications. Adapt these patterns to your specific use cases and requirements.