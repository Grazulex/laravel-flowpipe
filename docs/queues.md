# Using Flowpipe with Laravel Queues

Laravel Flowpipe works seamlessly with Laravel's queue system without requiring any special configuration. This guide shows you how to leverage queues for asynchronous processing while keeping your flows simple and maintainable.

## Basic Queue Integration

### Simple Job with Flowpipe

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Grazulex\LaravelFlowpipe\Flowpipe;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $orderData
    ) {}

    public function handle(): void
    {
        $result = Flowpipe::make()
            ->send($this->orderData)
            ->through([
                ValidateOrderStep::class,
                ProcessPaymentStep::class,
                UpdateInventoryStep::class,
                SendConfirmationEmailStep::class,
            ])
            ->thenReturn();

        // Handle the result as needed
        logger()->info('Order processed', ['result' => $result]);
    }
}
```

### Dispatching the Job

```php
// In your controller or service
dispatch(new ProcessOrderJob($orderData))
    ->onQueue('orders');
```

## Advanced Queue Patterns

### 1. Chained Jobs with Flowpipe

```php
<?php

namespace App\Jobs;

class UserRegistrationChain
{
    public static function dispatch(array $userData): void
    {
        // Chain multiple jobs that each use Flowpipe
        ValidateUserJob::dispatch($userData)
            ->chain([
                new CreateUserProfileJob($userData),
                new SendWelcomeEmailJob($userData),
                new SetupUserPreferencesJob($userData),
            ]);
    }
}

class ValidateUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $userData) {}

    public function handle(): void
    {
        $result = Flowpipe::make()
            ->send($this->userData)
            ->through([
                ValidateEmailStep::class,
                ValidatePasswordStep::class,
                CheckExistingUserStep::class,
            ])
            ->thenReturn();

        if (!$result['valid']) {
            throw new ValidationException('User validation failed');
        }
    }
}
```

### 2. Parallel Processing with Multiple Queues

```php
<?php

namespace App\Services;

use Grazulex\LaravelFlowpipe\Flowpipe;

class OrderProcessingService
{
    public function processOrder(array $orderData): void
    {
        // Process payment synchronously (critical path)
        $paymentResult = Flowpipe::make()
            ->send($orderData)
            ->through([
                ValidatePaymentStep::class,
                ProcessPaymentStep::class,
            ])
            ->thenReturn();

        if ($paymentResult['payment_status'] !== 'success') {
            throw new PaymentException('Payment failed');
        }

        // Dispatch non-critical tasks to different queues
        dispatch(new UpdateInventoryJob($orderData))
            ->onQueue('inventory');
            
        dispatch(new SendNotificationJob($orderData))
            ->onQueue('notifications');
            
        dispatch(new UpdateAnalyticsJob($orderData))
            ->onQueue('analytics');
    }
}
```

### 3. Batched Jobs with Flowpipe

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Bus;

class ProcessLargeDatasetJob
{
    public static function dispatchBatch(array $dataChunks): Batch
    {
        $jobs = collect($dataChunks)->map(fn($chunk) => new ProcessChunkJob($chunk));

        return Bus::batch($jobs)
            ->then(function (Batch $batch) {
                // All jobs completed successfully
                logger()->info('Batch processing completed', ['batch_id' => $batch->id]);
            })
            ->catch(function (Batch $batch, Throwable $e) {
                // First batch job failure
                logger()->error('Batch processing failed', ['error' => $e->getMessage()]);
            })
            ->finally(function (Batch $batch) {
                // Cleanup
            })
            ->dispatch();
    }
}

class ProcessChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $chunk) {}

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $result = Flowpipe::make()
            ->send($this->chunk)
            ->through([
                ValidateChunkStep::class,
                ProcessChunkStep::class,
                SaveChunkResultStep::class,
            ])
            ->thenReturn();

        // Update batch progress
        $this->batch()->increment('processed_items', count($this->chunk));
    }
}
```

## Error Handling in Queued Jobs

### Retry Logic with Flowpipe Error Handling

```php
<?php

namespace App\Jobs;

class ResilientProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $maxExceptions = 2;
    public $backoff = [10, 30, 60]; // seconds

    public function handle(): void
    {
        try {
            $result = Flowpipe::make()
                ->send($this->data)
                ->exponentialBackoff(3, 100, 2.0) // Flowpipe-level retry
                ->withFallback(function ($payload, $error) {
                    // Fallback to cached data or default values
                    return $this->getFallbackData($payload);
                })
                ->through([
                    CallExternalApiStep::class,
                    ProcessApiResponseStep::class,
                    SaveResultStep::class,
                ])
                ->thenReturn();

        } catch (Throwable $e) {
            // Log error for monitoring
            logger()->error('Job processing failed', [
                'job' => self::class,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger queue retry
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        // Handle final failure
        logger()->error('Job permanently failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

## Real-World Examples

### E-commerce Order Processing

```php
<?php

namespace App\Jobs;

class EcommerceOrderProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $result = Flowpipe::make()
            ->send($this->orderData)
            
            // Critical path - process synchronously
            ->through([
                ValidateOrderStep::class,
                CheckInventoryStep::class,
                ProcessPaymentStep::class,
            ])
            
            // After payment success, dispatch async tasks
            ->through([
                function($data, $next) {
                    // Dispatch background tasks
                    dispatch(new UpdateInventoryJob($data))->onQueue('inventory');
                    dispatch(new SendOrderConfirmationJob($data))->onQueue('emails');
                    dispatch(new UpdateAnalyticsJob($data))->onQueue('analytics');
                    
                    return $next($data);
                },
            ])
            
            ->thenReturn();
    }
}
```

### User Registration Pipeline

```php
<?php

namespace App\Jobs;

class UserRegistrationPipelineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $result = Flowpipe::make()
            ->send($this->userData)
            ->through([
                // Synchronous validation
                ValidateRegistrationDataStep::class,
                CreateUserAccountStep::class,
                
                // Async post-registration tasks
                function($data, $next) {
                    // Send welcome email asynchronously
                    dispatch(new SendWelcomeEmailJob($data))
                        ->onQueue('emails')
                        ->delay(now()->addMinutes(5));
                    
                    // Setup user preferences asynchronously
                    dispatch(new SetupUserPreferencesJob($data))
                        ->onQueue('user-setup');
                    
                    // Add to marketing lists asynchronously
                    dispatch(new AddToMarketingListsJob($data))
                        ->onQueue('marketing')
                        ->delay(now()->addHours(1));
                    
                    return $next($data);
                },
            ])
            ->thenReturn();
    }
}
```

## Best Practices

### 1. Queue Configuration

```php
// config/queue.php
'queues' => [
    'critical' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'critical',
        'retry_after' => 90,
    ],
    'background' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'background',
        'retry_after' => 300,
    ],
],
```

### 2. Step Design for Queues

```php
<?php

namespace App\Steps;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

// ✅ Good: Stateless, serializable step
class ProcessPaymentStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        $paymentService = app(PaymentService::class);
        $result = $paymentService->process($payload);
        
        return $next(array_merge($payload, $result));
    }
}

// ❌ Avoid: Steps with closures won't serialize
class BadStepWithClosure implements FlowStep
{
    private \Closure $callback;
    
    public function __construct(\Closure $callback)
    {
        $this->callback = $callback; // Won't serialize
    }
}
```

### 3. Monitoring and Observability

```php
<?php

namespace App\Jobs;

class MonitoredFlowpipeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $tracer = new \Grazulex\LaravelFlowpipe\Tracer\DatabaseTracer();
        
        $result = Flowpipe::make()
            ->send($this->data)
            ->withTracer($tracer)
            ->through($this->steps)
            ->thenReturn();
        
        // Log performance metrics
        logger()->info('Flowpipe job completed', [
            'job' => self::class,
            'steps_count' => $tracer->count(),
            'duration' => $tracer->totalDuration(),
            'memory_peak' => memory_get_peak_usage(true),
        ]);
    }
}
```

## Performance Considerations

### 1. Memory Management

```php
// For large datasets, process in chunks
public function handle(): void
{
    $chunks = array_chunk($this->largeDataset, 100);
    
    foreach ($chunks as $chunk) {
        Flowpipe::make()
            ->send($chunk)
            ->through([
                ProcessChunkStep::class,
            ])
            ->thenReturn();
        
        // Free memory between chunks
        unset($chunk);
    }
}
```

### 2. Database Connections

```php
// Ensure database connections are properly handled
public function handle(): void
{
    DB::beginTransaction();
    
    try {
        $result = Flowpipe::make()
            ->send($this->data)
            ->through([
                DatabaseStep::class,
                AnotherDatabaseStep::class,
            ])
            ->thenReturn();
        
        DB::commit();
    } catch (Throwable $e) {
        DB::rollback();
        throw $e;
    }
}
```

## Summary

Laravel Flowpipe works perfectly with Laravel queues without any modifications:

- ✅ **Simple Integration**: Just wrap your Flowpipe calls in jobs
- ✅ **Full Queue Features**: Support for chains, batches, retries, and delays
- ✅ **Error Handling**: Combine Flowpipe error strategies with queue retry logic
- ✅ **Monitoring**: Full observability with tracers and logging
- ✅ **Performance**: Efficient memory and database connection management

The key is to design your steps to be stateless and serializable, then leverage Laravel's powerful queue system for orchestration and reliability.
