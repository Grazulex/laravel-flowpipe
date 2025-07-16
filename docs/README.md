# Laravel Flowpipe Documentation

Welcome to the comprehensive documentation for Laravel Flowpipe - a modern, composable, and traceable flow pipeline system for Laravel applications.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Core Concepts](#core-concepts)
3. [Step Groups & Nested Flows](#step-groups--nested-flows)
4. [Configuration](#configuration)
5. [YAML Flow Definitions](#yaml-flow-definitions)
6. [Artisan Commands](#artisan-commands)
7. [Testing](#testing)
8. [Advanced Usage](#advanced-usage)
9. [Integrations](#integrations)
10. [Performance](#performance)
11. [Examples](#examples)

## Getting Started

### Installation

```bash
composer require grazulex/laravel-flowpipe
```

### Basic Usage

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::make()
    ->send('Hello World')
    ->through([
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD"
```

## Core Concepts

### Flowpipe

The `Flowpipe` class is the main entry point for creating and executing flow pipelines. It provides a fluent API for building complex data processing flows.

### Steps

Steps are the building blocks of flows. They can be:
- **Closures**: Anonymous functions for simple operations
- **Classes**: Custom step classes implementing the `FlowStep` interface
- **Callables**: Any callable PHP construct

### Conditional Logic

Laravel Flowpipe supports conditional execution through the `ConditionalStep` class:

```php
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['active'] ?? false);
    }
}

$result = Flowpipe::make()
    ->send(['active' => true, 'name' => 'John'])
    ->through([
        fn($data, $next) => $next($data['name']),
        ConditionalStep::when(
            new IsActiveCondition(),
            fn($name, $next) => $next(strtoupper($name))
        ),
        ConditionalStep::unless(
            new IsActiveCondition(),
            fn($name, $next) => $next(strtolower($name))
        ),
    ])
    ->thenReturn();
```

## Step Groups & Nested Flows

Laravel Flowpipe now supports **Step Groups** and **Nested Flows** for better organization and modularity.

### Step Groups

Define reusable collections of steps that can be referenced by name:

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Define a reusable group
Flowpipe::group('user-validation', [
    fn($user, $next) => $next(filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user : throw new InvalidArgumentException('Invalid email')),
    fn($user, $next) => $next(strlen($user['password']) >= 8 ? $user : throw new InvalidArgumentException('Password too short')),
    fn($user, $next) => $next(strlen($user['name']) > 0 ? $user : throw new InvalidArgumentException('Name required')),
]);

// Use the group in flows
$result = Flowpipe::make()
    ->send(['email' => 'user@example.com', 'password' => 'securepass', 'name' => 'John'])
    ->useGroup('user-validation')
    ->through([
        fn($user, $next) => $next(array_merge($user, ['id' => uniqid()])),
    ])
    ->thenReturn();
```

### Nested Flows

Create isolated sub-workflows that run independently:

```php
$result = Flowpipe::make()
    ->send(['name' => 'John Doe', 'email' => 'john@example.com'])
    ->nested([
        // This nested flow runs independently
        fn($data, $next) => $next(array_merge($data, ['processed' => true])),
        fn($data, $next) => $next(array_merge($data, ['id' => uniqid()])),
    ])
    ->through([
        // Main flow continues here
        fn($data, $next) => $next(array_merge($data, ['completed' => true])),
    ])
    ->thenReturn();
```

### Combining Groups and Nested Flows

```php
// Define groups
Flowpipe::group('validation', [
    fn($data, $next) => $next(/* validation logic */),
]);

Flowpipe::group('notifications', [
    fn($data, $next) => $next(/* notification logic */),
]);

// Use in complex flows
$result = Flowpipe::make()
    ->send($userData)
    ->useGroup('validation')
    ->nested([
        // Complex processing in isolation
        fn($data, $next) => $next(/* complex processing */),
    ])
    ->useGroup('notifications')
    ->thenReturn();
```

### Group Management

```php
// Check if a group exists
if (Flowpipe::hasGroup('user-validation')) {
    // Use the group
}

// Get all registered groups
$groups = Flowpipe::getGroups();

// Clear all groups (useful for testing)
Flowpipe::clearGroups();
```

For comprehensive examples and advanced usage, see the [Step Groups Guide](step-groups.md).

### Tracing

Tracing allows you to monitor and debug flow execution:
- `BasicTracer` - For development and debugging
- `TestTracer` - For testing and assertions
- Custom tracers implementing the `Tracer` interface

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Grazulex\LaravelFlowpipe\LaravelFlowpipeServiceProvider"
```

Configuration options:

```php
return [
    'definitions_path' => 'flow_definitions',
    'trace_enabled' => env('FLOWPIPE_TRACE_ENABLED', false),
    'cache_enabled' => env('FLOWPIPE_CACHE_ENABLED', true),
];
```

## YAML Flow Definitions

Create reusable flow definitions in YAML format, including support for groups and nested flows:

```yaml
flow: UserProcessingFlow
description: Process user data with validation and notifications

send:
  name: "John Doe"
  email: "john@example.com"
  password: "securepass123"

steps:
  # Use a predefined group
  - type: group
    name: user-validation
    
  # Create a nested flow
  - type: nested
    steps:
      - type: closure
        action: hash_password
      - type: closure
        action: remove_plain_password
        
  # Continue with main flow
  - type: closure
    action: create_user_record
    
  # Use another group
  - type: group
    name: user-notifications
```

### Group Definitions

Define groups in separate YAML files:

```yaml
# groups/user-validation.yaml
group: user-validation
description: Validate user input data
steps:
  - type: closure
    action: validate_email
  - type: closure
    action: validate_password_strength
  - type: closure
    action: validate_required_fields
```

### Supported Step Types

- `action` - Execute an action (legacy alias for `step`)
- `closure` - Execute a closure action
- `step` - Execute a custom step class
- `condition` - Conditional execution
- `group` - Execute a predefined group
- `nested` - Execute a nested flow

### Supported Operators

- `equals` - Exact match
- `contains` - String contains
- `greater_than` - Numeric comparison
- `less_than` - Numeric comparison
- `in` - Array contains value

## Artisan Commands

Laravel Flowpipe provides comprehensive CLI support:

### List Flows

```bash
php artisan flowpipe:list
php artisan flowpipe:list --detailed
```

### Run Flows

```bash
php artisan flowpipe:run user_processing
php artisan flowpipe:run user_processing --payload='{"name": "John"}'
```

### Export Flows

```bash
php artisan flowpipe:export user_processing --format=json
php artisan flowpipe:export user_processing --format=mermaid
php artisan flowpipe:export user_processing --format=md --output=docs/user_processing.md
```

### Validate Flows

Validate YAML flow definitions for syntax errors and structural issues:

```bash
# Validate a specific flow
php artisan flowpipe:validate user_processing

# Validate all flows
php artisan flowpipe:validate --all

# Validate flows from a specific path
php artisan flowpipe:validate --all --path=custom/flow/path

# Get JSON output format
php artisan flowpipe:validate --all --format=json
```

The validation command checks for:
- **YAML syntax errors**: Invalid YAML format
- **Required fields**: Missing flow name or steps
- **Step structure**: Valid step types and required fields
- **References**: Existing groups and step classes
- **Condition syntax**: Valid operators and structure
- **Nested flows**: Proper nesting structure

### Generate Flows

```bash
php artisan flowpipe:make-flow NewUserFlow --template=basic
php artisan flowpipe:make-flow ComplexFlow --template=conditional
php artisan flowpipe:make-flow AdvancedFlow --template=advanced
```

### Generate Steps

```bash
php artisan flowpipe:make-step ProcessUserStep
```

## Testing

Laravel Flowpipe includes robust testing support:

```php
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

public function test_user_processing_flow()
{
    $tracer = new TestTracer();
    
    $result = Flowpipe::make()
        ->send(['name' => 'John'])
        ->through([
            fn($data, $next) => $next(strtoupper($data['name'])),
        ])
        ->withTracer($tracer)
        ->thenReturn();
    
    $this->assertEquals('JOHN', $result);
    $this->assertCount(1, $tracer->count());
}
```

## Advanced Usage

### Custom Step Classes

```php
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

class ValidateUserStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload) || !isset($payload['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }
        
        return $next($payload);
    }
}
```

### Nested Flows

```php
$result = Flowpipe::make()
    ->send($userData)
    ->through([
        new ValidateUserStep(),
        function ($data, $next) {
            return Flowpipe::make()
                ->send($data)
                ->through([
                    new ProcessUserStep(),
                    new EnrichUserStep(),
                ])
                ->thenReturn();
        },
        new NotifyUserStep(),
    ])
    ->thenReturn();
```

### Custom Tracers

```php
use Grazulex\LaravelFlowpipe\Contracts\Tracer;

class DatabaseTracer implements Tracer
{
    public function trace(string $stepClass, mixed $payloadBefore, mixed $payloadAfter, ?float $durationMs = null): void
    {
        // Store trace data in database
    }
}
```

## Integrations

### Laravel Queues

Flowpipe works seamlessly with Laravel's queue system without any modifications. You can easily run your flows asynchronously by wrapping them in Laravel jobs.

```php
// In a Laravel Job
class ProcessOrderJob implements ShouldQueue
{
    public function handle(): void
    {
        $result = Flowpipe::make()
            ->send($this->orderData)
            ->through([
                ValidateOrderStep::class,
                ProcessPaymentStep::class,
                UpdateInventoryStep::class,
            ])
            ->thenReturn();
    }
}

// Dispatch the job
dispatch(new ProcessOrderJob($orderData))->onQueue('orders');
```

For comprehensive queue integration patterns, batching, error handling, and best practices, see the [Queue Integration Guide](queues.md).

## Performance

### Optimization Tips

1. **Use Lazy Evaluation**: Steps are only executed when needed
2. **Enable Caching**: Cache flow definitions for better performance
3. **Minimize Tracing**: Only enable tracing when needed
4. **Batch Operations**: Process multiple items in single steps

### Benchmarks

Laravel Flowpipe is optimized for performance:
- **Memory Usage**: < 1MB for complex flows
- **Execution Time**: < 1ms overhead per step
- **Scalability**: Handles 1000+ steps efficiently

## Examples

See the [examples](../examples/README.md) directory for comprehensive examples and use cases.

## API Reference

### Flowpipe Methods

- `make()` - Create a new flowpipe instance
- `send($data)` - Set initial data
- `through(array $steps)` - Add steps to the pipeline
- `useGroup(string $name)` - Add a predefined group to the pipeline
- `nested(array $steps)` - Create a nested flow
- `cache($key, $ttl, $store)` - Add cache step
- `retry($maxAttempts, $delayMs, $shouldRetry)` - Add retry step
- `rateLimit($key, $maxAttempts, $decayMinutes, $keyGenerator)` - Add rate limit step
- `transform($transformer)` - Add transform step
- `validate($rules, $messages, $customAttributes)` - Add validation step
- `batch($batchSize, $preserveKeys)` - Add batch step
- `withTracer(Tracer $tracer)` - Add a tracer
- `thenReturn()` - Execute and return result
- `context()` - Get flow context

### Static Methods

- `group(string $name, array $steps)` - Define a reusable step group
- `hasGroup(string $name)` - Check if a group exists
- `getGroups()` - Get all registered groups
- `clearGroups()` - Clear all registered groups (useful for testing)

### Conditional Steps

- `ConditionalStep::when($condition, $step)` - Execute step when condition is true
- `ConditionalStep::unless($condition, $step)` - Execute step when condition is false

### Tracer Methods

- `trace($stepClass, $before, $after, $duration)` - Trace step execution
- `all()` - Get all trace logs
- `steps()` - Get all step names
- `count()` - Get number of traced steps
- `firstStep()` - Get first step name
- `lastStep()` - Get last step name
- `clear()` - Clear all traces

---

For more examples and advanced usage patterns, see the [examples](../examples/README.md) directory.
