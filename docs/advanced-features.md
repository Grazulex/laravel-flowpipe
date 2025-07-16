# Advanced Laravel Flowpipe Features

This document showcases the advanced features available in Laravel Flowpipe.

## New Steps Available

### 1. Cache Step
Cache the results of expensive operations:

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::make()
    ->send($expensiveData)
    ->cache('my-cache-key', 3600) // Cache for 1 hour
    ->through([
        fn($data, $next) => $next(expensiveOperation($data)),
    ])
    ->thenReturn();
```

### 2. Retry Step
Automatically retry failed operations:

```php
$result = Flowpipe::make()
    ->send($unreliableData)
    ->retry(3, 100) // 3 attempts, 100ms delay
    ->through([
        fn($data, $next) => $next(unreliableApiCall($data)),
    ])
    ->thenReturn();

// With custom retry logic
$result = Flowpipe::make()
    ->send($data)
    ->retry(5, 200, function ($exception) {
        // Only retry on specific exceptions
        return $exception instanceof ConnectionException;
    })
    ->through([
        fn($data, $next) => $next(networkOperation($data)),
    ])
    ->thenReturn();
```

### 3. Rate Limiting Step
Limit the rate of operations:

```php
$result = Flowpipe::make()
    ->send($apiData)
    ->rateLimit('api-calls', 60, 1) // 60 calls per minute
    ->through([
        fn($data, $next) => $next(apiCall($data)),
    ])
    ->thenReturn();

// With custom key generation
$result = Flowpipe::make()
    ->send($userData)
    ->rateLimit('user-ops', 10, 1, fn($data) => $data['user_id'])
    ->through([
        fn($data, $next) => $next(userOperation($data)),
    ])
    ->thenReturn();
```

### 4. Transform Step
Transform data with helper methods:

```php
use Grazulex\LaravelFlowpipe\Steps\TransformStep;

// Basic transformation
$result = Flowpipe::make()
    ->send($data)
    ->transform(fn($data) => strtoupper($data))
    ->thenReturn();
```
    ->through([
        TransformStep::filter(fn($user) => $user['active']),
    ])
    ->thenReturn();

// Pluck specific fields
$result = Flowpipe::make()
    ->send($users)
    ->through([
        TransformStep::pluck('name'),
    ])
    ->thenReturn();
```

### 5. Validation Step
Validate data using Laravel's validation:

```php
use Grazulex\LaravelFlowpipe\Steps\ValidationStep;

// Example with array payload (returns validated array)
$result = Flowpipe::make()
    ->send(['email' => 'foo@bar.com', 'name' => 'John'])
    ->validate([
        'email' => 'required|email',
        'name' => 'required|string|max:255',
    ])
    ->through([
        fn($data, $next) => $next(array_merge($data, ['processed' => true])),
    ])
    ->thenReturn();

// Example with scalar payload and one rule
$result = Flowpipe::make()
    ->send('foo@bar.com')
    ->validate(['email' => 'required|email'])
    ->thenReturn();
```

### 6. Batch Step
Process data in batches:

```php
$result = Flowpipe::make()
    ->send($largeDataset)
    ->batch(50) // Process 50 items at a time
    ->through([
        fn($batch, $next) => $next(processBatch($batch)),
    ])
    ->thenReturn();
```

## Advanced Tracers

### 1. Debug Tracer
Comprehensive debugging with console output and logging:

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::debug(true, 'flowpipe') // Log to file
    ->send($data)
    ->through([
        SlowStep::class,
        FastStep::class,
    ])
    ->thenReturn();

// Manual usage
$tracer = new \Grazulex\LaravelFlowpipe\Tracer\DebugTracer();
$result = Flowpipe::make($tracer)
    ->send($data)
    ->through($steps)
    ->thenReturn();

// Get detailed statistics
$tracer->printSummary();
```

### 2. Performance Tracer
Monitor performance and identify bottlenecks:

```php
$result = Flowpipe::performance()
    ->send($data)
    ->through([
        ExpensiveStep::class,
        MemoryHeavyStep::class,
    ])
    ->thenReturn();

// Get performance metrics
$tracer = $result->context()->tracer();
$report = $tracer->getPerformanceReport();

if ($tracer->hasPerformanceIssues()) {
    $bottlenecks = $tracer->getBottlenecks();
    // Handle performance issues
}
```

### 3. Database Tracer
Store execution traces in database:

```php
// First, create the table
\Grazulex\LaravelFlowpipe\Tracer\DatabaseTracer::createTable();

$result = Flowpipe::database('my_traces')
    ->send($data)
    ->through($steps)
    ->thenReturn();

// Get traces for this execution
$tracer = $result->context()->tracer();
$traces = $tracer->getExecutionTraces();

// Cleanup old traces
DatabaseTracer::cleanup(30); // Remove traces older than 30 days
```

## Complex Example: E-commerce Order Processing

```php
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ValidationStep;

class OrderProcessingPipeline
{
    public function process(array $orderData)
    {
        return Flowpipe::debug(true, 'orders')
            ->send($orderData)
            
            // Validate order data
            ->validate([
                'user_id' => 'required|exists:users,id',
                'items' => 'required|array|min:1',
                'payment_method' => 'required|in:credit_card,paypal',
            ])
            
            // Cache expensive user lookup
            ->cache("user-{$orderData['user_id']}", 300)
            
            // Rate limit orders per user
            ->rateLimit('orders', 5, 1, fn($data) => $data['user_id'])
            
            // Process in batches if many items
            ->batch(10)
            
            // Retry payment processing
            ->retry(3, 1000, fn($e) => $e instanceof PaymentException)
            
            // Transform and process
            ->through([
                LoadUserStep::class,
                ValidateInventoryStep::class,
                CalculateTotalStep::class,
                ProcessPaymentStep::class,
                CreateOrderStep::class,
                SendConfirmationStep::class,
            ])
            
            ->thenReturn();
    }
}
```

## Chaining Operations

You can chain multiple operations for complex workflows:

```php
$result = Flowpipe::make()
    ->send($rawData)
    
    // Pre-processing
    ->validate(['required_field' => 'required'])
    ->transform(fn($data) => array_map('trim', $data))
    
    // Main processing with caching and retries
    ->cache('processed-data', 1800)
    ->retry(2, 500)
    ->batch(25)
    
    // Rate limiting for external calls
    ->rateLimit('api-calls', 100, 1)
    
    // Final processing
    ->through([
        ProcessDataStep::class,
        SaveToDbStep::class,
        NotifyStep::class,
    ])
    
    ->thenReturn();
```

## Performance Monitoring

```php
use Grazulex\LaravelFlowpipe\Tracer\PerformanceTracer;

$tracer = new PerformanceTracer();

$result = Flowpipe::make($tracer)
    ->send($data)
    ->through($complexSteps)
    ->thenReturn();

// Check for performance issues
if ($tracer->hasPerformanceIssues()) {
    $bottlenecks = $tracer->getBottlenecks(3);
    $memoryHogs = $tracer->getMemoryHogs(3);
    
    // Log or alert about performance issues
    Log::warning('Flowpipe performance issues detected', [
        'bottlenecks' => $bottlenecks,
        'memory_hogs' => $memoryHogs,
        'total_time' => $tracer->getTotalExecutionTime(),
    ]);
}
```

## Error Handling with Retry Logic

```php
use Grazulex\LaravelFlowpipe\Steps\RetryStep;

$result = Flowpipe::make()
    ->send($data)
    ->through([
        // Custom retry logic for different exceptions
        RetryStep::make(5, 200, function ($exception, $attempt) {
            // Retry network errors up to 3 times
            if ($exception instanceof NetworkException && $attempt <= 3) {
                return true;
            }
            
            // Retry rate limit errors with exponential backoff
            if ($exception instanceof RateLimitException) {
                sleep(pow(2, $attempt)); // Exponential backoff
                return $attempt <= 5;
            }
            
            return false;
        }),
        
        ApiCallStep::class,
    ])
    ->thenReturn();
```

These advanced features make Laravel Flowpipe a powerful tool for building robust, scalable, and maintainable data processing pipelines in your Laravel applications.
