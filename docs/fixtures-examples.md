# Exemples d'utilisation avec les fixtures

## Utilisation des fixtures dans les tests

```php
<?php

use Tests\Fixtures\Groups\GroupsFixture;
use Tests\Fixtures\Steps\CustomSteps;
use Grazulex\LaravelFlowpipe\Flowpipe;

class ExampleUsageTest extends TestCase
{
    /** @test */
    public function it_can_use_groups_fixture()
    {
        $result = GroupsFixture::basicGroupFlow();
        
        expect($result)->toHaveKeys([
            'user_id',
            'action',
            'validated',
            'processed',
            'email_sent',
            'slack_notified'
        ]);
    }
    
    /** @test */
    public function it_can_use_nested_flows()
    {
        $result = GroupsFixture::nestedFlowExample();
        
        expect($result)->toHaveKeys([
            'data',
            'nested_step_1',
            'nested_step_2',
            'main_step'
        ]);
    }
    
    /** @test */
    public function it_can_use_custom_steps()
    {
        $result = Flowpipe::make()
            ->send(['user_id' => 1, 'email' => 'test@example.com'])
            ->through([
                new AuthenticationStep(['read', 'write']),
                new DataValidationStep(['email' => 'email']),
                new ApiCallStep('/api/users', 'POST'),
                new NotificationStep('email', ['admin@example.com']),
            ])
            ->thenReturn();
            
        expect($result)->toHaveKeys([
            'authenticated',
            'is_valid',
            'api_response',
            'notification_sent'
        ]);
    }
}
```

## Utilisation dans une application réelle

### 1. Configuration des groupes de base

```php
<?php

// Dans un Service Provider ou au démarrage de l'application
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\Fixtures\Steps\CustomSteps;

// Groupe d'authentification
Flowpipe::group('auth', [
    new AuthenticationStep(['read']),
    new LoggingStep('info', 'User authentication checked'),
]);

// Groupe de validation
Flowpipe::group('validation', [
    new DataValidationStep([
        'email' => 'email',
        'name' => 'required',
        'age' => 'numeric'
    ]),
    new LoggingStep('info', 'Data validation completed'),
]);

// Groupe de traitement
Flowpipe::group('processing', [
    new DatabaseStep('insert', 'users'),
    new CacheStep('user_data', 3600),
    new LoggingStep('info', 'User data processed'),
]);

// Groupe de notifications
Flowpipe::group('notifications', [
    new NotificationStep('email', ['admin@example.com']),
    new NotificationStep('slack', ['#general']),
    new LoggingStep('info', 'Notifications sent'),
]);
```

### 2. Utilisation dans un contrôleur

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\Fixtures\Steps\CustomSteps;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $result = Flowpipe::make()
            ->send($request->all())
            ->useGroup('auth')
            ->useGroup('validation')
            ->nested([
                new FileOperationStep('upload', '/tmp/avatar.jpg'),
                new ApiCallStep('/api/external/notify', 'POST'),
            ])
            ->useGroup('processing')
            ->useGroup('notifications')
            ->thenReturn();
            
        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }
        
        return response()->json($result);
    }
}
```

### 3. Workflow complexe avec gestion d'erreurs

```php
<?php

$result = Flowpipe::make()
    ->send(['order_id' => 123])
    ->through([
        new ErrorHandlingStep(false, [
            function($error, $payload) {
                Log::error('Order processing error', ['error' => $error, 'payload' => $payload]);
            }
        ]),
        'auth',
        'validation',
    ])
    ->nested([
        // Traitement de paiement
        new ApiCallStep('/api/payments/process', 'POST'),
        new DatabaseStep('update', 'orders', ['status' => 'paid']),
    ])
    ->through([
        // Mise à jour de l'inventaire
        new DatabaseStep('update', 'inventory'),
        new CacheStep('inventory_status', 1800),
    ])
    ->useGroup('notifications')
    ->thenReturn();
```

### 4. Utilisation avec mise en cache

```php
<?php

$result = Flowpipe::make()
    ->send(['product_id' => 456])
    ->through([
        new CacheStep('product_details', 3600),
        new AuthenticationStep(['read']),
    ])
    ->nested([
        new ApiCallStep('/api/products/details', 'GET'),
        new DataValidationStep(['price' => 'numeric']),
    ])
    ->through([
        new DatabaseStep('select', 'products'),
        new FileOperationStep('read', '/storage/product_images'),
    ])
    ->thenReturn();
```

### 5. Workflow de test avec groupes avancés

```php
<?php

use Tests\Fixtures\Groups\AdvancedGroupsFixture;

// Configuration des groupes avancés
AdvancedGroupsFixture::setupAdvancedGroups();

$result = Flowpipe::make()
    ->send(['test_data' => 'value'])
    ->through([
        'advanced-validation',
        'advanced-processing',
    ])
    ->nested([
        new LoggingStep('debug', 'Nested processing started'),
        new CacheStep('test_cache', 300),
        new LoggingStep('debug', 'Nested processing completed'),
    ])
    ->thenReturn();
```

## Avantages des fixtures

1. **Réutilisabilité** : Les fixtures peuvent être utilisées dans plusieurs tests
2. **Consistance** : Garantit que tous les tests utilisent les mêmes données
3. **Maintenabilité** : Un seul endroit pour modifier la configuration des tests
4. **Simplicité** : Simplifie l'écriture et la lecture des tests

## Bonnes pratiques

1. **Organisation** : Séparez les fixtures par domaine (Groups, Steps, etc.)
2. **Nommage** : Utilisez des noms explicites pour les méthodes de fixture
3. **Documentation** : Commentez les fixtures complexes
4. **Réutilisation** : Créez des fixtures génériques réutilisables
5. **Isolation** : Chaque fixture doit être indépendante des autres
