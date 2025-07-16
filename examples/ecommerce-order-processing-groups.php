<?php

declare(strict_types=1);

/**
 * Example: E-commerce Order Processing with Groups and Nested Flows
 *
 * This example demonstrates a complete e-commerce order processing workflow
 * using step groups and nested flows for modular organization.
 */

require_once '../vendor/autoload.php';

use Grazulex\LaravelFlowpipe\Flowpipe;

// Define reusable step groups
Flowpipe::group('order-validation', [
    // Validate order has items
    fn ($order, $next) => $next(
        isset($order['items']) && count($order['items']) > 0
            ? $order
            : throw new InvalidArgumentException('Order must have items')
    ),

    // Validate customer information
    fn ($order, $next) => $next(
        isset($order['customer']) && ! empty($order['customer']['email'])
            ? $order
            : throw new InvalidArgumentException('Customer email required')
    ),

    // Validate order total
    fn ($order, $next) => $next(
        isset($order['total']) && $order['total'] > 0
            ? $order
            : throw new InvalidArgumentException('Order total must be greater than 0')
    ),
]);

Flowpipe::group('inventory-management', [
    // Check item availability
    fn ($order, $next) => $next(array_merge($order, [
        'inventory_checked' => true,
        'items_available' => true, // Simplified for example
    ])),

    // Reserve items
    fn ($order, $next) => $next(array_merge($order, [
        'items_reserved' => true,
        'reservation_id' => 'res_'.uniqid(),
    ])),
]);

Flowpipe::group('order-notifications', [
    // Send customer confirmation
    fn ($order, $next) => $next(array_merge($order, [
        'customer_notified' => true,
        'confirmation_email_sent' => true,
    ])),

    // Notify fulfillment team
    fn ($order, $next) => $next(array_merge($order, [
        'fulfillment_notified' => true,
        'fulfillment_ticket' => 'ticket_'.uniqid(),
    ])),
]);

// Sample order data
$orderData = [
    'items' => [
        ['name' => 'Product A', 'price' => 25.99, 'quantity' => 2],
        ['name' => 'Product B', 'price' => 15.50, 'quantity' => 1],
    ],
    'customer' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'address' => '123 Main St, City, Country',
    ],
    'total' => 67.48,
];

echo "=== E-commerce Order Processing Example ===\n\n";
echo "Initial Order Data:\n";
print_r($orderData);
echo "\n";

// Process the order using groups and nested flows
try {
    $result = Flowpipe::make()
        ->send($orderData)

        // Step 1: Validate order
        ->useGroup('order-validation')

        // Step 2: Check and reserve inventory
        ->useGroup('inventory-management')

        // Step 3: Process payment in nested flow (isolated)
        ->nested([
            // Payment processing sub-flow
            fn ($order, $next) => $next(array_merge($order, [
                'payment_id' => 'pay_'.uniqid(),
                'payment_method' => 'credit_card',
            ])),

            fn ($order, $next) => $next(array_merge($order, [
                'payment_status' => 'processed',
                'payment_date' => date('Y-m-d H:i:s'),
            ])),

            fn ($order, $next) => $next(array_merge($order, [
                'transaction_fee' => round($order['total'] * 0.029, 2), // 2.9% fee
            ])),
        ])

        // Step 4: Create order record in nested flow
        ->nested([
            // Order creation sub-flow
            fn ($order, $next) => $next(array_merge($order, [
                'order_id' => 'order_'.uniqid(),
                'order_number' => 'ORD-'.date('Ymd').'-'.rand(1000, 9999),
            ])),

            fn ($order, $next) => $next(array_merge($order, [
                'status' => 'confirmed',
                'created_at' => date('Y-m-d H:i:s'),
            ])),

            fn ($order, $next) => $next(array_merge($order, [
                'estimated_delivery' => date('Y-m-d', strtotime('+5 days')),
            ])),
        ])

        // Step 5: Send notifications
        ->useGroup('order-notifications')

        // Step 6: Final processing
        ->through([
            fn ($order, $next) => $next(array_merge($order, [
                'processing_completed' => true,
                'completed_at' => date('Y-m-d H:i:s'),
            ])),
        ])

        ->thenReturn();

    echo "Order processed successfully!\n\n";
    echo "Final Order Data:\n";
    print_r($result);

    echo "\n=== Order Summary ===\n";
    echo 'Order ID: '.$result['order_id']."\n";
    echo 'Order Number: '.$result['order_number']."\n";
    echo 'Status: '.$result['status']."\n";
    echo 'Payment ID: '.$result['payment_id']."\n";
    echo 'Payment Status: '.$result['payment_status']."\n";
    echo 'Total: $'.number_format($result['total'], 2)."\n";
    echo 'Transaction Fee: $'.number_format($result['transaction_fee'], 2)."\n";
    echo 'Estimated Delivery: '.$result['estimated_delivery']."\n";
    echo 'Customer Notified: '.($result['customer_notified'] ? 'Yes' : 'No')."\n";
    echo 'Fulfillment Notified: '.($result['fulfillment_notified'] ? 'Yes' : 'No')."\n";

} catch (Exception $e) {
    echo 'Error processing order: '.$e->getMessage()."\n";
}

echo "\n=== Groups Information ===\n";
echo "Available Groups:\n";
foreach (Flowpipe::getGroups() as $groupName => $steps) {
    echo "- $groupName (".count($steps)." steps)\n";
}

echo "\n=== Workflow Benefits ===\n";
echo "✓ Modular: Each group handles specific concerns\n";
echo "✓ Reusable: Groups can be used in different flows\n";
echo "✓ Isolated: Nested flows provide encapsulation\n";
echo "✓ Maintainable: Easy to update individual components\n";
echo "✓ Testable: Each group and nested flow can be tested independently\n";
