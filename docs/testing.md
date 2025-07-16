# Testing

Laravel Flowpipe provides comprehensive testing utilities to help you test your flows, steps, and conditions effectively.

## Testing Setup

### Basic Test Structure

```php
<?php

namespace Tests\Feature;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class FlowpipeTest extends TestCase
{
    public function test_basic_flow_execution()
    {
        $context = new FlowContext(['user_id' => 1]);
        
        $result = Flowpipe::run('user-registration', $context);
        
        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('user_created', $result->getData());
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
use Grazulex\LaravelFlowpipe\FlowContext;
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;
use Tests\TestCase;

class FlowExecutionTest extends TestCase
{
    public function test_flow_execution_with_tracing()
    {
        $tracer = new TestTracer();
        $context = new FlowContext(['order_id' => 123]);
        
        $result = Flowpipe::run('order-processing', $context, $tracer);
        
        // Assert flow completed successfully
        $this->assertTrue($result->isSuccess());
        
        // Assert specific steps were executed
        $this->assertTrue($tracer->wasStepExecuted('validate-order'));
        $this->assertTrue($tracer->wasStepExecuted('process-payment'));
        
        // Assert step execution order
        $this->assertEquals(1, $tracer->getStepExecutionOrder('validate-order'));
        $this->assertEquals(2, $tracer->getStepExecutionOrder('process-payment'));
        
        // Assert step execution times
        $this->assertGreaterThan(0, $tracer->getStepExecutionTime('validate-order'));
        
        // Get full execution trace
        $trace = $tracer->getTrace();
        $this->assertCount(3, $trace);
    }
}
```

#### 2. Mock Steps

Create mock steps for testing:

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\FlowContext;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class MockStepTest extends TestCase
{
    public function test_flow_with_mock_steps()
    {
        // Create a mock step using closure
        $mockStep = new ClosureStep(function (FlowContext $context) {
            $context->set('payment_processed', true);
            return $context;
        });
        
        // Register the mock step
        Flowpipe::registerStep('mock-payment', $mockStep);
        
        $context = new FlowContext(['amount' => 100]);
        $result = Flowpipe::run('payment-flow', $context);
        
        $this->assertTrue($result->get('payment_processed'));
    }
}
```

#### 3. Test Conditions

Test conditions in isolation:

```php
<?php

namespace Tests\Unit;

use App\Flowpipe\Conditions\HasInventoryCondition;
use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class ConditionTest extends TestCase
{
    public function test_has_inventory_condition()
    {
        $condition = new HasInventoryCondition();
        
        // Test with sufficient inventory
        $context = new FlowContext(['inventory_count' => 10]);
        $this->assertTrue($condition->evaluate($context));
        
        // Test with insufficient inventory
        $context = new FlowContext(['inventory_count' => 0]);
        $this->assertFalse($condition->evaluate($context));
    }
}
```

## Testing Individual Steps

### Step Unit Tests

```php
<?php

namespace Tests\Unit;

use App\Flowpipe\Steps\ValidateInputStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class ValidateInputStepTest extends TestCase
{
    public function test_validates_required_fields()
    {
        $step = new ValidateInputStep();
        
        // Test with valid input
        $context = new FlowContext([
            'email' => 'user@example.com',
            'password' => 'password123'
        ]);
        
        $result = $step->handle($context);
        
        $this->assertTrue($result->get('validation_passed'));
        $this->assertFalse($result->hasErrors());
    }
    
    public function test_fails_with_invalid_input()
    {
        $step = new ValidateInputStep();
        
        // Test with invalid input
        $context = new FlowContext([
            'email' => 'invalid-email',
            'password' => ''
        ]);
        
        $result = $step->handle($context);
        
        $this->assertFalse($result->get('validation_passed'));
        $this->assertTrue($result->hasErrors());
        $this->assertContains('Invalid email format', $result->getErrors());
    }
}
```

### Step Integration Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Flowpipe\Steps\CreateUserStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserStepTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_creates_user_in_database()
    {
        $step = new CreateUserStep();
        
        $context = new FlowContext([
            'email' => 'user@example.com',
            'password' => 'password123',
            'name' => 'John Doe'
        ]);
        
        $result = $step->handle($context);
        
        $this->assertTrue($result->get('user_created'));
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'name' => 'John Doe'
        ]);
        
        $user = User::where('email', 'user@example.com')->first();
        $this->assertEquals($user->id, $result->get('user_id'));
    }
}
```

## Flow Integration Tests

### Complete Flow Tests

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;
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
        
        $context = new FlowContext([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'payment_method' => 'credit_card'
        ]);
        
        $result = Flowpipe::run('order-processing', $context);
        
        // Assert flow completed successfully
        $this->assertTrue($result->isSuccess());
        
        // Assert order was created
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'completed'
        ]);
        
        // Assert inventory was updated
        $product->refresh();
        $this->assertEquals(8, $product->stock);
        
        // Assert context contains expected data
        $this->assertArrayHasKey('order_id', $result->getData());
        $this->assertArrayHasKey('payment_id', $result->getData());
    }
    
    public function test_order_processing_with_insufficient_inventory()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 1]);
        
        $context = new FlowContext([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 5, // More than available
            'payment_method' => 'credit_card'
        ]);
        
        $result = Flowpipe::run('order-processing', $context);
        
        // Assert flow failed
        $this->assertFalse($result->isSuccess());
        
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

use App\Flowpipe\Steps\PaymentStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_flow_handles_step_exceptions()
    {
        // Mock a step that throws an exception
        $mockStep = new class implements \Grazulex\LaravelFlowpipe\Contracts\FlowStep {
            public function handle(FlowContext $context): FlowContext
            {
                throw new \Exception('Payment failed');
            }
        };
        
        Flowpipe::registerStep('failing-payment', $mockStep);
        
        $context = new FlowContext(['amount' => 100]);
        $result = Flowpipe::run('payment-flow', $context);
        
        $this->assertFalse($result->isSuccess());
        $this->assertContains('Payment failed', $result->getErrors());
    }
    
    public function test_flow_continues_after_recoverable_error()
    {
        $context = new FlowContext(['retry_count' => 3]);
        $result = Flowpipe::run('resilient-flow', $context);
        
        $this->assertTrue($result->isSuccess());
        $this->assertGreaterThan(0, $result->get('retry_attempts'));
    }
}
```

## Performance Tests

### Testing Flow Performance

```php
<?php

namespace Tests\Performance;

use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class FlowPerformanceTest extends TestCase
{
    public function test_flow_execution_performance()
    {
        $context = new FlowContext(['items' => range(1, 1000)]);
        
        $startTime = microtime(true);
        $result = Flowpipe::run('batch-processing', $context);
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        $this->assertTrue($result->isSuccess());
        $this->assertLessThan(5.0, $executionTime, 'Flow should complete within 5 seconds');
    }
    
    public function test_flow_memory_usage()
    {
        $memoryBefore = memory_get_usage();
        
        $context = new FlowContext(['large_dataset' => str_repeat('data', 10000)]);
        $result = Flowpipe::run('memory-intensive-flow', $context);
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        $this->assertTrue($result->isSuccess());
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Flow should use less than 50MB');
    }
}
```

## Test Data Management

### Factory Methods

```php
<?php

namespace Tests\Unit;

use Grazulex\LaravelFlowpipe\FlowContext;
use Tests\TestCase;

class TestDataFactoryTest extends TestCase
{
    protected function createUserRegistrationContext(array $overrides = []): FlowContext
    {
        $data = array_merge([
            'email' => 'user@example.com',
            'password' => 'password123',
            'name' => 'John Doe',
            'terms_accepted' => true,
        ], $overrides);
        
        return new FlowContext($data);
    }
    
    protected function createOrderContext(array $overrides = []): FlowContext
    {
        $data = array_merge([
            'user_id' => 1,
            'product_id' => 1,
            'quantity' => 1,
            'payment_method' => 'credit_card',
            'shipping_address' => '123 Main St',
        ], $overrides);
        
        return new FlowContext($data);
    }
    
    public function test_user_registration_flow()
    {
        $context = $this->createUserRegistrationContext();
        $result = Flowpipe::run('user-registration', $context);
        
        $this->assertTrue($result->isSuccess());
    }
    
    public function test_order_processing_flow()
    {
        $context = $this->createOrderContext(['quantity' => 5]);
        $result = Flowpipe::run('order-processing', $context);
        
        $this->assertTrue($result->isSuccess());
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
    'trace_enabled' => true,
    'cache_enabled' => false,
    'validation_enabled' => true,
    'performance' => [
        'max_steps' => 50,
        'timeout' => 10,
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
│   └── FlowContextTest.php
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
$this->assertTrue($result->isSuccess());
$this->assertArrayHasKey('user_id', $result->getData());
$this->assertEquals('completed', $result->get('status'));

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
$context = new FlowContext([
    'email' => 'test@example.com',
    'amount' => 100,
]);
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
        php-version: '8.1'
        
    - name: Install dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader
      
    - name: Run tests
      run: vendor/bin/pest --coverage
      
    - name: Upload coverage
      uses: codecov/codecov-action@v3
```

This comprehensive testing guide covers all aspects of testing Laravel Flowpipe flows, from unit tests to integration tests and performance testing.
