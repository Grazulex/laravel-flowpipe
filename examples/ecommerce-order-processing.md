# E-commerce Order Processing with Step Groups

This example demonstrates how to use step groups and nested flows to create a modular, maintainable e-commerce order processing system.

## Overview

The order processing flow includes:
1. **Order Validation Group** - Validate order data, inventory, and customer
2. **Payment Processing Group** - Handle payment authorization and capture
3. **Fulfillment Group** - Process shipping and inventory updates
4. **Notification Group** - Send confirmations and updates

## Flow Definition with Groups

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;

// Define reusable step groups
Flowpipe::group('order-validation', [
    ValidateOrderDataStep::class,
    CheckInventoryStep::class,
    ValidateCustomerStep::class,
    CalculateOrderTotalStep::class,
]);

Flowpipe::group('payment-processing', [
    AuthorizePaymentStep::class,
    CapturePaymentStep::class,
    HandlePaymentFailureStep::class,
]);

Flowpipe::group('fulfillment', [
    ReserveInventoryStep::class,
    CreateShipmentStep::class,
    UpdateInventoryStep::class,
    GenerateTrackingStep::class,
]);

Flowpipe::group('notifications', [
    SendOrderConfirmationStep::class,
    SendShippingNotificationStep::class,
    UpdateCustomerAccountStep::class,
]);

// Main order processing flow
$result = Flowpipe::make()
    ->send($orderData)
    ->through([
        'order-validation',
        'payment-processing',
        'fulfillment',
        'notifications',
    ])
    ->thenReturn();
```

## Usage Examples

### Basic Order Processing

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;

$orderData = [
    'customer_id' => 123,
    'items' => [
        ['product_id' => 1, 'quantity' => 2, 'price' => 29.99],
        ['product_id' => 2, 'quantity' => 1, 'price' => 49.99],
    ],
    'shipping_address' => [
        'street' => '123 Main St',
        'city' => 'New York',
        'zip' => '10001',
    ],
    'payment_method' => 'credit_card',
    'payment_token' => 'tok_123456789',
];

$result = Flowpipe::make()
    ->send($orderData)
    ->through([
        'order-validation',
        'payment-processing',
        'fulfillment',
        'notifications',
    ])
    ->thenReturn();

if ($result['success']) {
    echo "Order #{$result['order_id']} processed successfully!";
} else {
    echo "Order processing failed: " . $result['error'];
}
```

### Advanced Processing with Nested Flows

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::make()
    ->send($orderData)
    ->useGroup('order-validation')
    ->nested([
        // Complex payment processing with multiple options
        DetectPaymentMethodStep::class,
        ClosureStep::make(function ($payload, $next) {
            // Conditional payment processing based on method
            $paymentFlow = match ($payload['payment_method']) {
                'credit_card' => ['ProcessCreditCardStep', 'ValidateCreditCardStep'],
                'paypal' => ['ProcessPayPalStep', 'ValidatePayPalStep'],
                'apple_pay' => ['ProcessApplePayStep', 'ValidateApplePayStep'],
                default => throw new Exception('Unsupported payment method'),
            };
            
            $result = Flowpipe::make()
                ->send($payload)
                ->through($paymentFlow)
                ->thenReturn();
                
            return $next($result);
        }),
    ])
    ->useGroup('fulfillment')
    ->useGroup('notifications')
    ->thenReturn();
```

### Conditional Processing with Groups

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;

// Define specialized groups for different order types
Flowpipe::group('digital-fulfillment', [
    GenerateDownloadLinksStep::class,
    SendDigitalDeliveryStep::class,
    UpdateLicenseStep::class,
]);

Flowpipe::group('physical-fulfillment', [
    ReserveInventoryStep::class,
    CreateShipmentStep::class,
    UpdateInventoryStep::class,
    GenerateTrackingStep::class,
]);

$result = Flowpipe::make()
    ->send($orderData)
    ->useGroup('order-validation')
    ->useGroup('payment-processing')
    ->through([
        // Conditional fulfillment based on order type
        ConditionalStep::when(
            new IsDigitalOrderCondition(),
            new GroupStep('digital-fulfillment')
        ),
        ConditionalStep::when(
            new IsPhysicalOrderCondition(),
            new GroupStep('physical-fulfillment')
        ),
    ])
    ->useGroup('notifications')
    ->thenReturn();
```

## Step Implementations

### Order Validation Steps

```php
<?php

namespace App\Steps\OrderValidation;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Closure;

class ValidateOrderDataStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Validate required fields
        $requiredFields = ['customer_id', 'items', 'shipping_address', 'payment_method'];
        
        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }
        
        // Validate items
        if (empty($payload['items'])) {
            throw new \Exception("Order must contain at least one item");
        }
        
        $payload['validation_status'] = 'data_validated';
        
        return $next($payload);
    }
}

class CheckInventoryStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        foreach ($payload['items'] as $item) {
            // Simulate inventory check
            $availableStock = $this->getAvailableStock($item['product_id']);
            
            if ($availableStock < $item['quantity']) {
                throw new \Exception("Insufficient stock for product {$item['product_id']}");
            }
        }
        
        $payload['validation_status'] = 'inventory_validated';
        
        return $next($payload);
    }
    
    private function getAvailableStock(int $productId): int
    {
        // Simulate database lookup
        return rand(1, 100);
    }
}
```

### Payment Processing Steps

```php
<?php

namespace App\Steps\PaymentProcessing;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Closure;

class AuthorizePaymentStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        // Simulate payment authorization
        $authResult = $this->authorizePayment(
            $payload['payment_token'],
            $payload['total_amount'] ?? $this->calculateTotal($payload['items'])
        );
        
        if (!$authResult['success']) {
            throw new \Exception("Payment authorization failed: {$authResult['error']}");
        }
        
        $payload['payment_authorization'] = $authResult['authorization_code'];
        $payload['payment_status'] = 'authorized';
        
        return $next($payload);
    }
    
    private function authorizePayment(string $token, float $amount): array
    {
        // Simulate payment gateway call
        return [
            'success' => rand(0, 1) === 1,
            'authorization_code' => 'AUTH_' . uniqid(),
            'error' => 'Insufficient funds',
        ];
    }
    
    private function calculateTotal(array $items): float
    {
        return array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'],
            $items
        ));
    }
}
```

## Testing with Groups

```php
<?php

namespace Tests\Feature\OrderProcessing;

use Tests\TestCase;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

class OrderProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Define groups for testing
        Flowpipe::group('order-validation', [
            ValidateOrderDataStep::class,
            CheckInventoryStep::class,
        ]);
        
        Flowpipe::group('payment-processing', [
            AuthorizePaymentStep::class,
            CapturePaymentStep::class,
        ]);
    }
    
    public function test_can_process_order_with_groups()
    {
        $tracer = new TestTracer();
        
        $orderData = [
            'customer_id' => 123,
            'items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => 29.99],
            ],
            'payment_method' => 'credit_card',
            'payment_token' => 'tok_123456789',
        ];
        
        $result = Flowpipe::make($tracer)
            ->send($orderData)
            ->through([
                'order-validation',
                'payment-processing',
            ])
            ->thenReturn();
        
        // Verify execution
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order_id', $result);
        
        // Verify tracing
        $steps = $tracer->steps();
        $this->assertContains('GroupStep', $steps);
        $this->assertEquals(2, $tracer->count());
    }
    
    public function test_can_use_nested_flows()
    {
        $result = Flowpipe::make()
            ->send(['data' => 'test'])
            ->nested([
                ClosureStep::make(fn($payload, $next) => $next([
                    ...$payload,
                    'processed' => true,
                ])),
            ])
            ->through([
                ClosureStep::make(fn($payload, $next) => $next([
                    ...$payload,
                    'completed' => true,
                ])),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['processed']);
        $this->assertTrue($result['completed']);
    }
}
```

## Configuration

### Group Configuration File

You can define groups in a configuration file:

```php
// config/flowpipe_groups.php
return [
    'order-validation' => [
        'ValidateOrderDataStep',
        'CheckInventoryStep',
        'ValidateCustomerStep',
    ],
    
    'payment-processing' => [
        'AuthorizePaymentStep',
        'CapturePaymentStep',
    ],
    
    'fulfillment' => [
        'ReserveInventoryStep',
        'CreateShipmentStep',
        'UpdateInventoryStep',
    ],
];
```

### Loading Groups from Configuration

```php
<?php

// In a service provider or bootstrap file
$groups = config('flowpipe_groups');

foreach ($groups as $name => $steps) {
    Flowpipe::group($name, $steps);
}
```

## Benefits of Using Step Groups

1. **Modularity**: Related steps are grouped together
2. **Reusability**: Groups can be used across different flows
3. **Maintainability**: Changes to a group affect all flows using it
4. **Testing**: Groups can be tested independently
5. **Documentation**: Clear separation of concerns

This approach makes your Laravel Flowpipe workflows more organized, maintainable, and easier to understand.
