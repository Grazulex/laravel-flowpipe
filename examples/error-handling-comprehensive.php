<?php

declare(strict_types=1);

/**
 * Example: Comprehensive Error Handling Patterns
 *
 * This example demonstrates real-world error handling scenarios including:
 * - E-commerce order processing with complex error handling
 * - User registration with multiple service dependencies
 * - File processing with retry and fallback
 * - API integration with circuit breaker pattern
 * - Database operations with compensation
 */

require_once '../vendor/autoload.php';

use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;
use Grazulex\LaravelFlowpipe\Flowpipe;

// Exception classes for different error scenarios
final class NetworkException extends Exception {}
final class ValidationException extends Exception {}
final class PaymentException extends Exception {}
final class InventoryException extends Exception {}
final class DatabaseException extends Exception {}
final class ExternalServiceException extends Exception {}

// Mock service classes
final class PaymentService
{
    private static int $attempts = 0;
    
    public static function charge(array $data): array
    {
        self::$attempts++;
        
        if (self::$attempts <= 2) {
            throw new PaymentException('Payment gateway timeout');
        }
        
        return array_merge($data, ['payment_id' => 'pay_' . uniqid(), 'charged' => true]);
    }
    
    public static function refund(string $paymentId): array
    {
        return ['refund_id' => 'ref_' . uniqid(), 'refunded' => true];
    }
    
    public static function reset(): void
    {
        self::$attempts = 0;
    }
}

final class InventoryService
{
    public static function reserve(array $items): array
    {
        if (rand(1, 10) > 7) {
            throw new InventoryException('Insufficient inventory');
        }
        
        return ['reservation_id' => 'res_' . uniqid(), 'items_reserved' => count($items)];
    }
    
    public static function release(string $reservationId): void
    {
        // Release inventory reservation
        echo "Released inventory reservation: $reservationId\n";
    }
}

final class EmailService
{
    private static int $failures = 0;
    
    public static function send(array $emailData): array
    {
        self::$failures++;
        
        if (self::$failures <= 1) {
            throw new ExternalServiceException('Email service unavailable');
        }
        
        return array_merge($emailData, ['email_id' => 'email_' . uniqid(), 'sent' => true]);
    }
    
    public static function reset(): void
    {
        self::$failures = 0;
    }
}

final class DatabaseService
{
    private static bool $shouldFail = false;
    
    public static function createOrder(array $data): array
    {
        if (self::$shouldFail) {
            throw new DatabaseException('Database connection failed');
        }
        
        return array_merge($data, ['order_id' => 'ord_' . uniqid(), 'created' => true]);
    }
    
    public static function deleteOrder(string $orderId): void
    {
        echo "Deleted order: $orderId\n";
    }
    
    public static function setShouldFail(bool $shouldFail): void
    {
        self::$shouldFail = $shouldFail;
    }
}

echo "=== Comprehensive Error Handling Patterns Demo ===\n\n";

// Example 1: E-commerce Order Processing with Complex Error Handling
echo "1. E-commerce Order Processing with Complex Error Handling\n";
echo "==========================================================\n";

try {
    PaymentService::reset();
    EmailService::reset();
    
    $orderData = [
        'customer_id' => 'cust_123',
        'items' => [
            ['id' => 'item_1', 'quantity' => 2, 'price' => 29.99],
            ['id' => 'item_2', 'quantity' => 1, 'price' => 15.99],
        ],
        'total' => 75.97,
        'email' => 'customer@example.com',
    ];
    
    $result = Flowpipe::make()
        ->send($orderData)
        
        // Step 1: Validate order (with fallback to basic validation)
        ->withFallback(function ($payload, $error) {
            echo "⚠️  Order validation failed, using basic validation: {$error->getMessage()}\n";
            return array_merge($payload, ['validation_mode' => 'basic']);
        })
        ->through([
            function ($data, $next) {
                // Simulate validation that might fail
                if (rand(1, 10) > 8) {
                    throw new ValidationException('Advanced validation service unavailable');
                }
                return $next(array_merge($data, ['validated' => true]));
            },
        ])
        
        // Step 2: Reserve inventory (with compensation)
        ->withCompensation(function ($payload, $error, $context) {
            echo "🔄 Inventory reservation failed, releasing reservation\n";
            if (isset($payload['reservation_id'])) {
                InventoryService::release($payload['reservation_id']);
            }
            return array_merge($payload, ['inventory_compensated' => true]);
        })
        ->through([
            function ($data, $next) {
                $reservation = InventoryService::reserve($data['items']);
                return $next(array_merge($data, $reservation));
            },
        ])
        
        // Step 3: Process payment (with retry and compensation)
        ->withErrorHandler(
            CompositeStrategy::make()
                ->retry(RetryStrategy::exponentialBackoff(3, 200, 2.0))
                ->compensate(CompensationStrategy::make(function ($payload, $error, $context) {
                    echo "🔄 Payment failed, releasing inventory reservation\n";
                    if (isset($payload['reservation_id'])) {
                        InventoryService::release($payload['reservation_id']);
                    }
                    return array_merge($payload, ['payment_compensated' => true]);
                }))
        )
        ->through([
            function ($data, $next) {
                $payment = PaymentService::charge($data);
                return $next(array_merge($data, $payment));
            },
        ])
        
        // Step 4: Create order record (with compensation)
        ->withCompensation(function ($payload, $error, $context) {
            echo "🔄 Order creation failed, refunding payment\n";
            if (isset($payload['payment_id'])) {
                PaymentService::refund($payload['payment_id']);
            }
            if (isset($payload['reservation_id'])) {
                InventoryService::release($payload['reservation_id']);
            }
            return array_merge($payload, ['order_compensated' => true]);
        })
        ->through([
            function ($data, $next) {
                $order = DatabaseService::createOrder($data);
                return $next(array_merge($data, $order));
            },
        ])
        
        // Step 5: Send confirmation email (with retry and fallback)
        ->withErrorHandler(
            CompositeStrategy::make()
                ->retry(RetryStrategy::linearBackoff(3, 100, 50))
                ->fallback(FallbackStrategy::make(function ($payload, $error) {
                    echo "⚠️  Email sending failed, queuing for later: {$error->getMessage()}\n";
                    return array_merge($payload, ['email_queued' => true]);
                }))
        )
        ->through([
            function ($data, $next) {
                $email = EmailService::send([
                    'to' => $data['email'],
                    'subject' => 'Order Confirmation',
                    'order_id' => $data['order_id'],
                ]);
                return $next(array_merge($data, $email));
            },
        ])
        
        ->thenReturn();
    
    echo "✅ Order processed successfully!\n";
    echo "   Order ID: {$result['order_id']}\n";
    echo "   Payment ID: {$result['payment_id']}\n";
    echo "   Items Reserved: {$result['items_reserved']}\n";
    echo "   Email Status: " . (isset($result['email_id']) ? "Sent ({$result['email_id']})" : "Queued") . "\n";
    
} catch (Exception $e) {
    echo "❌ Order processing failed: {$e->getMessage()}\n";
}

echo "\n";

// Example 2: User Registration with Multiple Service Dependencies
echo "2. User Registration with Multiple Service Dependencies\n";
echo "======================================================\n";

try {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secure_password',
        'plan' => 'premium',
    ];
    
    $result = Flowpipe::make()
        ->send($userData)
        
        // Step 1: Create user account (with retry)
        ->exponentialBackoff(3, 100, 2.0)
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 8) {
                    throw new DatabaseException('Database temporarily unavailable');
                }
                return $next(array_merge($data, ['user_id' => 'user_' . uniqid(), 'created' => true]));
            },
        ])
        
        // Step 2: Setup user profile (with fallback)
        ->withFallback(function ($payload, $error) {
            echo "⚠️  Profile setup failed, using basic profile: {$error->getMessage()}\n";
            return array_merge($payload, ['profile_type' => 'basic']);
        })
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 7) {
                    throw new ExternalServiceException('Profile service unavailable');
                }
                return $next(array_merge($data, ['profile_created' => true, 'profile_type' => 'full']));
            },
        ])
        
        // Step 3: Setup billing (with compensation)
        ->withCompensation(function ($payload, $error, $context) {
            echo "🔄 Billing setup failed, deleting user account\n";
            if (isset($payload['user_id'])) {
                DatabaseService::deleteOrder($payload['user_id']); // Simulate user deletion
            }
            return array_merge($payload, ['billing_compensated' => true]);
        })
        ->through([
            function ($data, $next) {
                if ($data['plan'] === 'premium' && rand(1, 10) > 8) {
                    throw new PaymentException('Billing setup failed');
                }
                return $next(array_merge($data, ['billing_setup' => true]));
            },
        ])
        
        // Step 4: Send welcome email (with retry and fallback)
        ->withErrorHandler(
            CompositeStrategy::make()
                ->retry(RetryStrategy::make(2, 50))
                ->fallback(FallbackStrategy::make(function ($payload, $error) {
                    echo "⚠️  Welcome email failed, will send later: {$error->getMessage()}\n";
                    return array_merge($payload, ['welcome_email_queued' => true]);
                }))
        )
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 6) {
                    throw new ExternalServiceException('Email service timeout');
                }
                return $next(array_merge($data, ['welcome_email_sent' => true]));
            },
        ])
        
        ->thenReturn();
    
    echo "✅ User registration successful!\n";
    echo "   User ID: {$result['user_id']}\n";
    echo "   Profile Type: {$result['profile_type']}\n";
    echo "   Billing Setup: " . ($result['billing_setup'] ? 'Yes' : 'No') . "\n";
    echo "   Welcome Email: " . (isset($result['welcome_email_sent']) ? 'Sent' : 'Queued') . "\n";
    
} catch (Exception $e) {
    echo "❌ User registration failed: {$e->getMessage()}\n";
}

echo "\n";

// Example 3: File Processing with Advanced Error Handling
echo "3. File Processing with Advanced Error Handling\n";
echo "==============================================\n";

try {
    $fileData = [
        'file_path' => '/uploads/data.csv',
        'format' => 'csv',
        'destination' => '/processed/',
    ];
    
    $result = Flowpipe::make()
        ->send($fileData)
        
        // Step 1: Validate file (with fallback)
        ->withFallback(function ($payload, $error) {
            echo "⚠️  File validation failed, using basic validation: {$error->getMessage()}\n";
            return array_merge($payload, ['validation_mode' => 'basic']);
        })
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 9) {
                    throw new ValidationException('File format validation service unavailable');
                }
                return $next(array_merge($data, ['validated' => true]));
            },
        ])
        
        // Step 2: Process file (with retry and compensation)
        ->withErrorHandler(
            CompositeStrategy::make()
                ->retry(RetryStrategy::exponentialBackoff(3, 500, 2.0))
                ->compensate(CompensationStrategy::make(function ($payload, $error, $context) {
                    echo "🔄 File processing failed, cleaning up temporary files\n";
                    return array_merge($payload, ['cleanup_performed' => true]);
                }))
        )
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 7) {
                    throw new ExternalServiceException('File processing service overloaded');
                }
                return $next(array_merge($data, ['processed' => true, 'output_file' => '/processed/data_processed.csv']));
            },
        ])
        
        // Step 3: Store results (with fallback to queue)
        ->withFallback(function ($payload, $error) {
            echo "⚠️  Storage failed, queuing for later: {$error->getMessage()}\n";
            return array_merge($payload, ['queued_for_storage' => true]);
        })
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 8) {
                    throw new DatabaseException('Storage service unavailable');
                }
                return $next(array_merge($data, ['stored' => true, 'storage_id' => 'stor_' . uniqid()]));
            },
        ])
        
        ->thenReturn();
    
    echo "✅ File processing successful!\n";
    echo "   Validation Mode: {$result['validation_mode']}\n";
    echo "   Processed: " . ($result['processed'] ? 'Yes' : 'No') . "\n";
    echo "   Storage Status: " . (isset($result['stored']) ? "Stored ({$result['storage_id']})" : 'Queued') . "\n";
    
} catch (Exception $e) {
    echo "❌ File processing failed: {$e->getMessage()}\n";
}

echo "\n";

// Example 4: API Integration with Circuit Breaker Pattern
echo "4. API Integration with Circuit Breaker Pattern\n";
echo "==============================================\n";

try {
    $apiData = [
        'endpoint' => 'https://api.example.com/users',
        'method' => 'GET',
        'timeout' => 5,
    ];
    
    $result = Flowpipe::make()
        ->send($apiData)
        
        // Step 1: Try primary API (with retry)
        ->withErrorHandler(
            CompositeStrategy::make()
                ->retry(RetryStrategy::exponentialBackoff(3, 200, 2.0))
                ->fallback(FallbackStrategy::make(function ($payload, $error) {
                    echo "⚠️  Primary API failed, trying secondary API: {$error->getMessage()}\n";
                    return array_merge($payload, ['use_secondary' => true]);
                }))
        )
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 6) {
                    throw new NetworkException('Primary API timeout');
                }
                return $next(array_merge($data, ['primary_response' => ['data' => 'primary_data'], 'source' => 'primary']));
            },
        ])
        
        // Step 2: Try secondary API if primary failed
        ->through([
            function ($data, $next) {
                if (isset($data['use_secondary']) && $data['use_secondary']) {
                    if (rand(1, 10) > 8) {
                        throw new NetworkException('Secondary API also failed');
                    }
                    return $next(array_merge($data, ['secondary_response' => ['data' => 'secondary_data'], 'source' => 'secondary']));
                }
                return $next($data);
            },
        ])
        
        // Step 3: Process response (with fallback to cached data)
        ->withFallback(function ($payload, $error) {
            echo "⚠️  Response processing failed, using cached data: {$error->getMessage()}\n";
            return array_merge($payload, ['response' => ['data' => 'cached_data'], 'source' => 'cache']);
        })
        ->through([
            function ($data, $next) {
                if (rand(1, 10) > 9) {
                    throw new ExternalServiceException('Response processing service failed');
                }
                
                $response = $data['primary_response'] ?? $data['secondary_response'] ?? null;
                if (!$response) {
                    throw new ExternalServiceException('No response data available');
                }
                
                return $next(array_merge($data, ['response' => $response]));
            },
        ])
        
        ->thenReturn();
    
    echo "✅ API integration successful!\n";
    echo "   Data Source: {$result['source']}\n";
    echo "   Response: " . json_encode($result['response']) . "\n";
    
} catch (Exception $e) {
    echo "❌ API integration failed: {$e->getMessage()}\n";
}

echo "\n=== Error Handling Pattern Summary ===\n";
echo "✅ Retry Patterns:\n";
echo "   • Exponential backoff for transient failures\n";
echo "   • Linear backoff for predictable delays\n";
echo "   • Custom retry logic based on exception types\n";
echo "   • Configurable maximum attempts and delays\n\n";

echo "✅ Fallback Patterns:\n";
echo "   • Default values when services are unavailable\n";
echo "   • Alternative service endpoints\n";
echo "   • Cached data as fallback\n";
echo "   • Graceful degradation of functionality\n\n";

echo "✅ Compensation Patterns:\n";
echo "   • Automatic rollback of database transactions\n";
echo "   • Release of reserved resources\n";
echo "   • Cleanup of temporary files\n";
echo "   • Saga pattern for distributed transactions\n\n";

echo "✅ Composite Patterns:\n";
echo "   • Retry + Fallback for maximum resilience\n";
echo "   • Retry + Compensation for resource management\n";
echo "   • Multi-layered error handling strategies\n";
echo "   • Exception-specific handling approaches\n\n";

echo "🛡️  Benefits:\n";
echo "   • Improved system resilience\n";
echo "   • Better user experience\n";
echo "   • Reduced manual intervention\n";
echo "   • Maintainable error handling code\n";
echo "   • Comprehensive error logging and monitoring\n";