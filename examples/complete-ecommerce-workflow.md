# Exemple complet : Workflow de commande e-commerce

Cet exemple montre comment utiliser Laravel Flowpipe pour créer un workflow complet de traitement de commande e-commerce avec des groupes d'étapes et des flux imbriqués.

## Configuration initiale

```php
<?php

use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\Fixtures\Steps\CustomSteps;

// Configuration des groupes réutilisables
Flowpipe::group('order-validation', [
    new DataValidationStep([
        'customer_id' => 'numeric',
        'items' => 'required',
        'total' => 'numeric',
    ]),
    new AuthenticationStep(['create_order']),
    new LoggingStep('info', 'Order validation completed'),
]);

Flowpipe::group('payment-processing', [
    new ApiCallStep('/api/payments/validate', 'POST'),
    new DatabaseStep('insert', 'payments'),
    new CacheStep('payment_status', 300),
    new LoggingStep('info', 'Payment processed'),
]);

Flowpipe::group('inventory-management', [
    new DatabaseStep('update', 'inventory'),
    new ApiCallStep('/api/inventory/reserve', 'POST'),
    new CacheStep('inventory_status', 1800),
    new LoggingStep('info', 'Inventory updated'),
]);

Flowpipe::group('order-fulfillment', [
    new DatabaseStep('insert', 'orders'),
    new ApiCallStep('/api/shipping/create', 'POST'),
    new FileOperationStep('create', '/storage/invoices'),
    new LoggingStep('info', 'Order fulfilled'),
]);

Flowpipe::group('customer-notifications', [
    new NotificationStep('email', ['order-confirmation']),
    new NotificationStep('sms', ['order-update']),
    new ApiCallStep('/api/external/notify', 'POST'),
    new LoggingStep('info', 'Customer notified'),
]);

Flowpipe::group('post-order-processing', [
    new CacheStep('order_analytics', 3600),
    new ApiCallStep('/api/analytics/track', 'POST'),
    new DatabaseStep('update', 'customer_stats'),
    new LoggingStep('info', 'Post-order processing completed'),
]);
```

## Workflow principal

```php
<?php

class OrderProcessingService
{
    public function processOrder(array $orderData): array
    {
        return Flowpipe::make()
            ->send($orderData)
            
            // Étape 1 : Validation initiale
            ->useGroup('order-validation')
            
            // Étape 2 : Traitement des paiements et gestion des erreurs
            ->through([
                new ErrorHandlingStep(false, [
                    function($error, $payload) {
                        Log::error('Payment processing error', [
                            'error' => $error,
                            'order_id' => $payload['order_id'] ?? null
                        ]);
                    }
                ]),
            ])
            ->useGroup('payment-processing')
            
            // Étape 3 : Gestion de l'inventaire avec workflow imbriqué
            ->nested([
                // Vérification de la disponibilité
                new ApiCallStep('/api/inventory/check', 'POST'),
                
                // Réservation conditionnelle
                new class implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep {
                    public function handle(mixed $payload, \Closure $next): mixed
                    {
                        if (!isset($payload['inventory_available']) || !$payload['inventory_available']) {
                            return $next([
                                ...$payload,
                                'error' => 'Insufficient inventory',
                                'order_status' => 'cancelled'
                            ]);
                        }
                        
                        return $next([
                            ...$payload,
                            'inventory_reserved' => true
                        ]);
                    }
                },
            ])
            ->useGroup('inventory-management')
            
            // Étape 4 : Traitement conditionnel selon le statut
            ->through([
                new class implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep {
                    public function handle(mixed $payload, \Closure $next): mixed
                    {
                        if (isset($payload['order_status']) && $payload['order_status'] === 'cancelled') {
                            // Workflow d'annulation
                            return Flowpipe::make()
                                ->send($payload)
                                ->through([
                                    new DatabaseStep('update', 'orders', ['status' => 'cancelled']),
                                    new NotificationStep('email', ['order-cancelled']),
                                    new LoggingStep('warning', 'Order cancelled'),
                                ])
                                ->thenReturn();
                        }
                        
                        return $next($payload);
                    }
                },
            ])
            
            // Étape 5 : Finalisation de la commande
            ->useGroup('order-fulfillment')
            
            // Étape 6 : Notifications client avec workflow imbriqué
            ->nested([
                // Préparation des données de notification
                new class implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep {
                    public function handle(mixed $payload, \Closure $next): mixed
                    {
                        $notificationData = [
                            'customer_name' => $payload['customer_name'] ?? 'Customer',
                            'order_number' => $payload['order_number'] ?? 'N/A',
                            'estimated_delivery' => date('Y-m-d', strtotime('+3 days')),
                        ];
                        
                        return $next([
                            ...$payload,
                            'notification_data' => $notificationData
                        ]);
                    }
                },
                
                // Envoi des notifications
                new NotificationStep('email', ['customer']),
                new NotificationStep('sms', ['customer']),
            ])
            ->useGroup('customer-notifications')
            
            // Étape 7 : Traitement post-commande
            ->useGroup('post-order-processing')
            
            // Étape 8 : Nettoyage et finalisation
            ->through([
                new class implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep {
                    public function handle(mixed $payload, \Closure $next): mixed
                    {
                        // Nettoyage des données temporaires
                        $cleanPayload = array_diff_key($payload, [
                            'temporary_data' => null,
                            'cache_keys' => null,
                        ]);
                        
                        return $next([
                            ...$cleanPayload,
                            'processing_completed' => true,
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                },
                new LoggingStep('info', 'Order processing completed successfully'),
            ])
            
            ->thenReturn();
    }
}
```

## Utilisation dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\OrderProcessingService;

class OrderController extends Controller
{
    public function __construct(
        private OrderProcessingService $orderService
    ) {}
    
    public function store(Request $request)
    {
        $orderData = $request->validate([
            'customer_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
        ]);
        
        $result = $this->orderService->processOrder($orderData);
        
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'order_status' => $result['order_status'] ?? 'failed'
            ], 400);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Order processed successfully',
            'order_id' => $result['order_id'] ?? null,
            'order_status' => $result['order_status'] ?? 'confirmed',
            'estimated_delivery' => $result['notification_data']['estimated_delivery'] ?? null,
        ]);
    }
}
```

## Test du workflow

```php
<?php

use Tests\TestCase;
use App\Services\OrderProcessingService;

class OrderProcessingTest extends TestCase
{
    /** @test */
    public function it_processes_successful_order()
    {
        $orderData = [
            'customer_id' => 1,
            'items' => [
                ['product_id' => 1, 'quantity' => 2, 'price' => 25.99],
                ['product_id' => 2, 'quantity' => 1, 'price' => 15.50],
            ],
            'total' => 67.48,
            'payment_method' => 'credit_card',
        ];
        
        $service = new OrderProcessingService();
        $result = $service->processOrder($orderData);
        
        expect($result)
            ->toHaveKey('processing_completed', true)
            ->toHaveKey('order_status', 'confirmed')
            ->toHaveKey('completed_at')
            ->not->toHaveKey('error');
    }
    
    /** @test */
    public function it_handles_insufficient_inventory()
    {
        $orderData = [
            'customer_id' => 1,
            'items' => [
                ['product_id' => 999, 'quantity' => 100, 'price' => 25.99],
            ],
            'total' => 2599.00,
            'payment_method' => 'credit_card',
            'inventory_available' => false, // Simulate insufficient inventory
        ];
        
        $service = new OrderProcessingService();
        $result = $service->processOrder($orderData);
        
        expect($result)
            ->toHaveKey('order_status', 'cancelled')
            ->toHaveKey('error', 'Insufficient inventory');
    }
    
    /** @test */
    public function it_logs_all_processing_steps()
    {
        $orderData = [
            'customer_id' => 1,
            'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 10.00]],
            'total' => 10.00,
            'payment_method' => 'credit_card',
        ];
        
        $service = new OrderProcessingService();
        $result = $service->processOrder($orderData);
        
        // Vérifier que toutes les étapes de logging ont été exécutées
        expect($result)
            ->toHaveKey('logged', true)
            ->toHaveKey('processing_completed', true);
    }
}
```

## Configuration avancée avec middleware

```php
<?php

// Middleware pour logging automatique
class LoggingMiddleware implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        $startTime = microtime(true);
        
        $result = $next($payload);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        Log::info('Step executed', [
            'execution_time' => $executionTime,
            'memory_usage' => memory_get_usage(true),
            'payload_size' => strlen(json_encode($payload))
        ]);
        
        return $result;
    }
}

// Ajout du middleware à tous les groupes
Flowpipe::group('monitored-validation', [
    new LoggingMiddleware(),
    new DataValidationStep([
        'customer_id' => 'numeric',
        'total' => 'numeric',
    ]),
]);
```

## Résumé des fonctionnalités utilisées

1. **Groupes d'étapes** : Réutilisation de workflows communs
2. **Flux imbriqués** : Traitement conditionnel et sous-workflows
3. **Gestion d'erreurs** : Capture et traitement des erreurs
4. **Étapes personnalisées** : Logique métier spécifique
5. **Logging** : Traçabilité complète du workflow
6. **Mise en cache** : Optimisation des performances
7. **Notifications** : Communication avec les clients
8. **Validation** : Contrôle de la qualité des données

Cet exemple montre la puissance et la flexibilité de Laravel Flowpipe pour créer des workflows complexes, maintenables et testables.
