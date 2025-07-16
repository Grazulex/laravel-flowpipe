<?php

declare(strict_types=1);

/**
 * Example: Advanced Error Handling Strategies
 *
 * This example demonstrates the use of customizable error handling strategies
 * including retry, fallback, and compensation patterns.
 */

require_once '../vendor/autoload.php';

use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\Flowpipe;

// Simulate unreliable services
final class UnreliableApiService
{
    private static int $callCount = 0;

    public static function call(array $data): array
    {
        self::$callCount++;
        echo 'API Call attempt #'.self::$callCount."\n";

        // Simulate random failures
        if (self::$callCount < 3) {
            throw new RuntimeException('API temporarily unavailable');
        }

        return array_merge($data, ['api_response' => 'success']);
    }

    public static function reset(): void
    {
        self::$callCount = 0;
    }
}

final class PaymentService
{
    public static function process(array $data): array
    {
        if (rand(1, 10) > 7) {
            throw new RuntimeException('Payment declined');
        }

        return array_merge($data, ['payment_status' => 'paid']);
    }
}

echo "=== Advanced Error Handling Strategies Demo ===\n\n";

// Example 1: Retry Strategy with Exponential Backoff
echo "1. Retry Strategy with Exponential Backoff\n";
echo "-------------------------------------------\n";

try {
    UnreliableApiService::reset();

    $result = Flowpipe::make()
        ->send(['user_id' => 123, 'action' => 'fetch_profile'])
        ->exponentialBackoff(5, 100, 2.0) // 5 attempts, 100ms base delay, 2x multiplier
        ->through([
            fn ($data, $next) => $next(UnreliableApiService::call($data)),
        ])
        ->thenReturn();

    echo '✓ Success: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Failed: '.$e->getMessage()."\n";
}

echo "\n";

// Example 2: Fallback Strategy
echo "2. Fallback Strategy\n";
echo "--------------------\n";

try {
    $result = Flowpipe::make()
        ->send(['user_id' => 123, 'amount' => 100])
        ->withFallback(function ($payload, $error) {
            echo 'Fallback triggered: '.$error->getMessage()."\n";

            return array_merge($payload, ['payment_status' => 'pending', 'fallback_used' => true]);
        })
        ->through([
            fn ($data, $next) => $next(PaymentService::process($data)),
        ])
        ->thenReturn();

    echo '✓ Result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Failed: '.$e->getMessage()."\n";
}

echo "\n";

// Example 3: Compensation Strategy
echo "3. Compensation Strategy\n";
echo "------------------------\n";

try {
    $result = Flowpipe::make()
        ->send(['order_id' => 456, 'items' => ['item1', 'item2']])
        ->withCompensation(function ($payload, $error, $context) {
            echo 'Compensation triggered: '.$error->getMessage()."\n";
            echo 'Rolling back order: '.$payload['order_id']."\n";

            return array_merge($payload, ['compensation_applied' => true, 'order_status' => 'cancelled']);
        })
        ->through([
            function ($data, $next) {
                // Simulate order processing failure
                if (rand(1, 2) === 1) {
                    throw new RuntimeException('Inventory not available');
                }

                return $next(array_merge($data, ['order_status' => 'confirmed']));
            },
        ])
        ->thenReturn();

    echo '✓ Result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Failed: '.$e->getMessage()."\n";
}

echo "\n";

// Example 4: Composite Strategy (Retry + Fallback)
echo "4. Composite Strategy (Retry + Fallback)\n";
echo "----------------------------------------\n";

try {
    $compositeStrategy = CompositeStrategy::make()
        ->retry(RetryStrategy::exponentialBackoff(3, 50, 2.0))
        ->fallback(FallbackStrategy::withDefault(['status' => 'cached', 'fallback' => true]));

    $result = Flowpipe::make()
        ->send(['request' => 'user_data'])
        ->withErrorHandler($compositeStrategy)
        ->through([
            function ($data, $next) {
                // Simulate service that always fails
                throw new RuntimeException('Service unavailable');
            },
        ])
        ->thenReturn();

    echo '✓ Result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Failed: '.$e->getMessage()."\n";
}

echo "\n";

// Example 5: Exception-specific Strategies
echo "5. Exception-specific Strategies\n";
echo "--------------------------------\n";

final class ValidationException extends Exception {}
final class NetworkException extends Exception {}

try {
    $result = Flowpipe::make()
        ->send(['email' => 'invalid-email'])
        ->fallbackOnException(ValidationException::class, function ($payload, $error) {
            echo "Validation fallback: Using default email\n";

            return array_merge($payload, ['email' => 'default@example.com']);
        })
        ->compensateOnException(NetworkException::class, function ($payload, $error, $context) {
            echo "Network compensation: Queuing for later\n";

            return array_merge($payload, ['queued' => true]);
        })
        ->through([
            function ($data, $next) {
                if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException('Invalid email format');
                }

                return $next($data);
            },
        ])
        ->thenReturn();

    echo '✓ Result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Failed: '.$e->getMessage()."\n";
}

echo "\n";

// Example 6: Complex Workflow with Multiple Error Handling
echo "6. Complex Workflow with Multiple Error Handling\n";
echo "------------------------------------------------\n";

try {
    $result = Flowpipe::make()
        ->send(['user_id' => 789, 'order_total' => 99.99])

        // Step 1: Validate with fallback
        ->withFallback(function ($payload, $error) {
            echo "Validation fallback: Using guest user\n";

            return array_merge($payload, ['user_id' => 'guest', 'guest_order' => true]);
        })
        ->through([
            function ($data, $next) {
                if ($data['user_id'] === 999) {
                    throw new RuntimeException('User not found');
                }

                return $next(array_merge($data, ['user_validated' => true]));
            },
        ])

        // Step 2: Process payment with retry
        ->exponentialBackoff(3, 100, 2.0)
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 8) {
                    throw new RuntimeException('Payment gateway timeout');
                }

                return $next(array_merge($data, ['payment_processed' => true]));
            },
        ])

        // Step 3: Fulfill order with compensation
        ->withCompensation(function ($payload, $error, $context) {
            echo "Order compensation: Refunding payment\n";

            return array_merge($payload, ['refund_issued' => true, 'order_cancelled' => true]);
        })
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 9) {
                    throw new RuntimeException('Fulfillment center unavailable');
                }

                return $next(array_merge($data, ['order_fulfilled' => true]));
            },
        ])

        ->thenReturn();

    echo '✓ Final Result: '.json_encode($result)."\n";
} catch (Exception $e) {
    echo '✗ Workflow Failed: '.$e->getMessage()."\n";
}

echo "\n=== Error Handling Benefits ===\n";
echo "✓ Retry: Automatic recovery from transient failures\n";
echo "✓ Fallback: Graceful degradation when services are unavailable\n";
echo "✓ Compensation: Rollback operations to maintain data consistency\n";
echo "✓ Composite: Combine multiple strategies for comprehensive error handling\n";
echo "✓ Exception-specific: Handle different types of errors appropriately\n";
echo "✓ Configurable: Customize delay, attempts, and conditions\n";
echo "✓ Maintainable: Clear separation of business logic and error handling\n";
