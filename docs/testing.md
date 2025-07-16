# Testing

Laravel Flowpipe provides comprehensive testing utilities to help you test your flows, steps, and conditions effectively.

## Testing Setup

### Basic Test Structure

```php
<?php

namespace Tests\Feature;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;
use Tests\TestCase;

class FlowpipeTest extends TestCase
{
    public function test_basic_flow_execution()
    {
        $result = Flowpipe::make()
            ->send(['user_id' => 1])
            ->through([
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['processed']);
        $this->assertEquals(1, $result['user_id']);
    }
}
```

### Test Utilities

Laravel Flowpipe provides several testing utilities:

#### 1. Test Tracer

Use the `TestTracer` to capture flow execution details:

```php
<?php

namespace Tests\Feature;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;
use Tests\TestCase;

class FlowExecutionTest extends TestCase
{
    public function test_flow_execution_with_tracing()
    {
        $tracer = new TestTracer();
        
        $result = Flowpipe::make()
            ->send(['order_id' => 123])
            ->through([
                fn($data, $next) => $next(array_merge($data, ['validated' => true])),
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
            ])
            ->withTracer($tracer)
            ->thenReturn();
        
        // Assert flow completed successfully
        $this->assertTrue($result['processed']);
        
        // Assert specific steps were executed
        $this->assertEquals(2, $tracer->count());
        $this->assertEquals('Closure', $tracer->firstStep());
        $this->assertEquals('Closure', $tracer->lastStep());
        
        // Get all step names
        $steps = $tracer->steps();
        $this->assertCount(2, $steps);
        
        // Get all execution logs
        $logs = $tracer->all();
        $this->assertCount(2, $logs);
        
        // Check first log details
        $firstLog = $logs[0];
        $this->assertEquals('Closure', $firstLog['step']);
        $this->assertEquals(['order_id' => 123], $firstLog['before']);
        $this->assertEquals(['order_id' => 123, 'validated' => true], $firstLog['after']);
        $this->assertIsFloat($firstLog['duration']);
    }
}
```

#### 2. Custom Step Testing

Create and test custom steps:

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class CustomStepTest extends TestCase
{
    public function test_custom_step_implementation()
    {
        $step = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                if (!is_array($payload)) {
                    throw new \InvalidArgumentException('Payload must be an array');
                }
                
                $payload['custom_processed'] = true;
                return $next($payload);
            }
        };
        
        $result = Flowpipe::make()
            ->send(['data' => 'test'])
            ->through([$step])
            ->thenReturn();
        
        $this->assertTrue($result['custom_processed']);
        $this->assertEquals('test', $result['data']);
    }
}
```

#### 3. Test Conditions

Test conditions in isolation:

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class ConditionTest extends TestCase
{
    public function test_has_inventory_condition()
    {
        $condition = new class implements Condition {
            public function evaluate(mixed $payload): bool
            {
                return is_array($payload) && ($payload['inventory_count'] ?? 0) > 0;
            }
        };
        
        // Test with sufficient inventory
        $result = Flowpipe::make()
            ->send(['inventory_count' => 10])
            ->through([
                ConditionalStep::when($condition, fn($data, $next) => $next(array_merge($data, ['has_inventory' => true])))
            ])
            ->thenReturn();
        
        $this->assertTrue($result['has_inventory']);
        
        // Test with insufficient inventory
        $result = Flowpipe::make()
            ->send(['inventory_count' => 0])
            ->through([
                ConditionalStep::when($condition, fn($data, $next) => $next(array_merge($data, ['has_inventory' => true])))
            ])
            ->thenReturn();
        
        $this->assertFalse($result['has_inventory'] ?? false);
    }
}
```

## Testing Individual Steps

### Step Unit Tests

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Tests\TestCase;

class ValidateInputStepTest extends TestCase
{
    public function test_validates_required_fields()
    {
        $step = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                if (!is_array($payload)) {
                    throw new \InvalidArgumentException('Payload must be an array');
                }
                
                if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Valid email is required');
                }
                
                if (empty($payload['password']) || strlen($payload['password']) < 8) {
                    throw new \InvalidArgumentException('Password must be at least 8 characters');
                }
                
                return $next(array_merge($payload, ['validation_passed' => true]));
            }
        };
        
        // Test with valid input
        $result = Flowpipe::make()
            ->send([
                'email' => 'user@example.com',
                'password' => 'password123'
            ])
            ->through([$step])
            ->thenReturn();
        
        $this->assertTrue($result['validation_passed']);
    }
    
    public function test_fails_with_invalid_input()
    {
        $step = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                if (!is_array($payload)) {
                    throw new \InvalidArgumentException('Payload must be an array');
                }
                
                if (empty($payload['email']) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Valid email is required');
                }
                
                if (empty($payload['password']) || strlen($payload['password']) < 8) {
                    throw new \InvalidArgumentException('Password must be at least 8 characters');
                }
                
                return $next(array_merge($payload, ['validation_passed' => true]));
            }
        };
        
        // Test with invalid input
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid email is required');
        
        Flowpipe::make()
            ->send([
                'email' => 'invalid-email',
                'password' => ''
            ])
            ->through([$step])
            ->thenReturn();
    }
}
```

### Step Integration Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserStepTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_creates_user_in_database()
    {
        $step = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                if (!is_array($payload)) {
                    throw new \InvalidArgumentException('Payload must be an array');
                }
                
                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Hash::make($payload['password']),
                ]);
                
                return $next(array_merge($payload, [
                    'user_id' => $user->id,
                    'user_created' => true
                ]));
            }
        };
        
        $result = Flowpipe::make()
            ->send([
                'email' => 'user@example.com',
                'password' => 'password123',
                'name' => 'John Doe'
            ])
            ->through([$step])
            ->thenReturn();
        
        $this->assertTrue($result['user_created']);
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'name' => 'John Doe'
        ]);
        
        $user = User::where('email', 'user@example.com')->first();
        $this->assertEquals($user->id, $result['user_id']);
    }
}
```

## Flow Integration Tests

### Complete Flow Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProcessingFlowTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_order_processing_flow()
    {
        // Setup test data
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        
        // Create flow steps
        $validateStep = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                $product = Product::find($payload['product_id']);
                if ($product->stock < $payload['quantity']) {
                    throw new \InvalidArgumentException('Insufficient inventory');
                }
                return $next(array_merge($payload, ['validated' => true]));
            }
        };
        
        $createOrderStep = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                $order = Order::create([
                    'user_id' => $payload['user_id'],
                    'product_id' => $payload['product_id'],
                    'quantity' => $payload['quantity'],
                    'status' => 'completed',
                ]);
                
                return $next(array_merge($payload, ['order_id' => $order->id]));
            }
        };
        
        $updateInventoryStep = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                $product = Product::find($payload['product_id']);
                $product->update(['stock' => $product->stock - $payload['quantity']]);
                
                return $next(array_merge($payload, ['inventory_updated' => true]));
            }
        };
        
        $result = Flowpipe::make()
            ->send([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'payment_method' => 'credit_card'
            ])
            ->through([
                $validateStep,
                $createOrderStep,
                $updateInventoryStep,
            ])
            ->thenReturn();
        
        // Assert flow completed successfully
        $this->assertTrue($result['validated']);
        $this->assertTrue($result['inventory_updated']);
        
        // Assert order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'completed'
        ]);
        
        // Assert inventory was updated
        $product->refresh();
        $this->assertEquals(8, $product->stock);
        
        // Assert result contains expected data
        $this->assertArrayHasKey('order_id', $result);
    }
    
    public function test_order_processing_with_insufficient_inventory()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);
        
        $validateStep = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                $product = Product::find($payload['product_id']);
                if ($product->stock < $payload['quantity']) {
                    throw new \InvalidArgumentException('Insufficient inventory');
                }
                return $next(array_merge($payload, ['validated' => true]));
            }
        };
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient inventory');
        
        Flowpipe::make()
            ->send([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => 5, // More than available
                'payment_method' => 'credit_card'
            ])
            ->through([$validateStep])
            ->thenReturn();
        
        // Assert no order was created
        $this->assertDatabaseMissing('orders', [
            'user_id' => $user->id
        ]);
        
        // Assert inventory wasn't changed
        $product->refresh();
        $this->assertEquals(1, $product->stock);
    }
}
```

## Error Handling Tests

### Testing Error Scenarios

```php
<?php

namespace Tests\Feature;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_flow_handles_step_exceptions()
    {
        $failingStep = new class implements FlowStep {
            public function handle(mixed $payload, \Closure $next): mixed
            {
                throw new \Exception('Payment failed');
            }
        };
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment failed');
        
        Flowpipe::make()
            ->send(['amount' => 100])
            ->through([$failingStep])
            ->thenReturn();
    }
    
    public function test_flow_with_retry_step()
    {
        $result = Flowpipe::make()
            ->send(['data' => 'test'])
            ->retry(3, 100, function ($exception) {
                return $exception instanceof \RuntimeException;
            })
            ->through([
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['processed']);
    }
}
```

## Performance Tests

### Testing Flow Performance

```php
<?php

namespace Tests\Performance;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class FlowPerformanceTest extends TestCase
{
    public function test_flow_execution_performance()
    {
        $startTime = microtime(true);
        
        $result = Flowpipe::make()
            ->send(['items' => range(1, 1000)])
            ->through([
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
                fn($data, $next) => $next(array_merge($data, ['validated' => true])),
            ])
            ->thenReturn();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        $this->assertTrue($result['processed']);
        $this->assertLessThan(1.0, $executionTime, 'Flow should complete within 1 second');
    }
    
    public function test_flow_memory_usage()
    {
        $memoryBefore = memory_get_usage();
        
        $result = Flowpipe::make()
            ->send(['large_dataset' => str_repeat('data', 10000)])
            ->through([
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
            ])
            ->thenReturn();
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        $this->assertTrue($result['processed']);
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Flow should use less than 10MB');
    }
}
```

## Test Data Management

### Factory Methods

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class TestDataFactoryTest extends TestCase
{
    protected function createUserRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'user@example.com',
            'password' => 'password123',
            'name' => 'John Doe',
            'terms_accepted' => true,
        ], $overrides);
    }
    
    protected function createOrderData(array $overrides = []): array
    {
        return array_merge([
            'user_id' => 1,
            'product_id' => 1,
            'quantity' => 1,
            'payment_method' => 'credit_card',
            'shipping_address' => '123 Main St',
        ], $overrides);
    }
    
    public function test_user_registration_flow()
    {
        $data = $this->createUserRegistrationData();
        
        $result = Flowpipe::make()
            ->send($data)
            ->through([
                fn($data, $next) => $next(array_merge($data, ['processed' => true])),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['processed']);
        $this->assertEquals('user@example.com', $result['email']);
    }
    
    public function test_order_processing_flow()
    {
        $data = $this->createOrderData(['quantity' => 5]);
        
        $result = Flowpipe::make()
            ->send($data)
            ->through([
                fn($data, $next) => $next(array_merge($data, ['validated' => true])),
            ])
            ->thenReturn();
        
        $this->assertTrue($result['validated']);
        $this->assertEquals(5, $result['quantity']);
    }
}
```

## Testing Configuration

### PHPUnit Configuration

```xml
<!-- phpunit.xml -->
<phpunit>
    <testsuites>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Performance">
            <directory suffix="Test.php">./tests/Performance</directory>
        </testsuite>
    </testsuites>
    
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </coverage>
</phpunit>
```

### Test Environment Configuration

```php
// config/flowpipe.php (testing environment)
return [
    'definitions_path' => 'tests/fixtures/flows',
    'step_namespace' => 'Tests\\Fixtures\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\TestTracer::class,
    ],
];
```

## Best Practices

### 1. Test Organization

```
tests/
├── Feature/
│   ├── FlowExecutionTest.php
│   ├── OrderProcessingFlowTest.php
│   └── UserRegistrationFlowTest.php
├── Unit/
│   ├── Steps/
│   │   ├── ValidateInputStepTest.php
│   │   └── CreateUserStepTest.php
│   ├── Conditions/
│   │   └── HasInventoryConditionTest.php
│   └── TracerTest.php
├── Performance/
│   └── FlowPerformanceTest.php
└── fixtures/
    └── flows/
        ├── test-flow.yaml
        └── mock-flow.yaml
```

### 2. Test Naming

```php
// Good test names
public function test_user_registration_creates_user_successfully()
public function test_order_processing_fails_with_insufficient_inventory()
public function test_payment_step_handles_declined_cards()

// Bad test names
public function test_flow()
public function test_step_works()
public function test_condition()
```

### 3. Assertions

```php
// Specific assertions
$this->assertTrue($result['processed']);
$this->assertArrayHasKey('user_id', $result);
$this->assertEquals('completed', $result['status']);

// Avoid generic assertions
$this->assertNotNull($result);
$this->assertTrue(true);
```

### 4. Test Data

```php
// Use factories for complex data
$user = User::factory()->create();
$order = Order::factory()->create(['user_id' => $user->id]);

// Use builders for simple data
$data = [
    'email' => 'test@example.com',
    'amount' => 100,
];
```

## Continuous Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        
    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Run tests
      run: vendor/bin/pest --coverage
      
    - name: Upload coverage
      uses: codecov/codecov-action@v3
```

## Advanced Testing Scenarios

### Testing with Batch Processing

```php
public function test_batch_processing_flow()
{
    $items = range(1, 100);
    
    $result = Flowpipe::make()
        ->send(['items' => $items])
        ->batch(10)
        ->through([
            fn($data, $next) => $next(array_merge($data, ['processed' => true])),
        ])
        ->thenReturn();
    
    $this->assertTrue($result['processed']);
    $this->assertCount(100, $result['items']);
}
```

### Testing with Caching

```php
public function test_flow_with_caching()
{
    $result = Flowpipe::make()
        ->send(['data' => 'test'])
        ->cache('test-key', 300)
        ->through([
            fn($data, $next) => $next(array_merge($data, ['cached' => true])),
        ])
        ->thenReturn();
    
    $this->assertTrue($result['cached']);
}
```

### Testing with Validation

```php
public function test_flow_with_validation()
{
    $result = Flowpipe::make()
        ->send(['email' => 'test@example.com', 'name' => 'John'])
        ->validate([
            'email' => 'required|email',
            'name' => 'required|string',
        ])
        ->through([
            fn($data, $next) => $next(array_merge($data, ['validated' => true])),
        ])
        ->thenReturn();
    
    $this->assertTrue($result['validated']);
}
```

This comprehensive testing guide covers all aspects of testing Laravel Flowpipe flows using the actual implementation, from unit tests to integration tests and performance testing.
