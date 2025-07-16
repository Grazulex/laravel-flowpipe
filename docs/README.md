# Laravel Flowpipe Documentation

Welcome to the comprehensive documentation for Laravel Flowpipe - a modern, composable, and traceable flow pipeline system for Laravel applications.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Core Concepts](#core-concepts)
3. [Configuration](#configuration)
4. [YAML Flow Definitions](#yaml-flow-definitions)
5. [Artisan Commands](#artisan-commands)
6. [Testing](#testing)
7. [Advanced Usage](#advanced-usage)
8. [Performance](#performance)
9. [Examples](#examples)

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
        fn($data) => strtoupper($data),
        fn($data) => str_replace(' ', '-', $data),
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

Laravel Flowpipe supports conditional execution with:
- `when()` - Execute steps when condition is true
- `unless()` - Execute steps when condition is false
- Dot notation for nested array access
- Field operators for complex conditions

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

Create reusable flow definitions in YAML format:

```yaml
flow: UserProcessingFlow
description: Process user data with validation and notifications

send:
  name: "John Doe"
  email: "john@example.com"
  is_active: true

steps:
  - condition:
      field: email
      operator: contains
      value: "@"
    then:
      - type: closure
        action: uppercase
    else:
      - type: closure
        action: lowercase
```

### Supported Step Types

- `closure` - Execute a closure action
- `step` - Execute a custom step class
- `condition` - Conditional execution

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
            fn($data) => strtoupper($data['name']),
        ])
        ->withTracer($tracer)
        ->thenReturn();
    
    $this->assertEquals('JOHN', $result);
    $this->assertCount(1, $tracer->getSteps());
    $this->assertEquals('JOHN', $tracer->getLastStep()['result']);
}
```

## Advanced Usage

### Custom Step Classes

```php
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

class ValidateUserStep implements FlowStep
{
    public function handle($data, \Closure $next)
    {
        if (!isset($data['email'])) {
            throw new \InvalidArgumentException('Email is required');
        }
        
        return $next($data);
    }
}
```

### Nested Flows

```php
$result = Flowpipe::make()
    ->send($userData)
    ->through([
        ValidateUserStep::class,
        function ($data) {
            return Flowpipe::make()
                ->send($data)
                ->through([
                    ProcessUserStep::class,
                    EnrichUserStep::class,
                ])
                ->thenReturn();
        },
        NotifyUserStep::class,
    ])
    ->thenReturn();
```

### Custom Tracers

```php
use Grazulex\LaravelFlowpipe\Contracts\Tracer;

class DatabaseTracer implements Tracer
{
    public function trace(string $step, $data, ?int $duration = null): void
    {
        DB::table('flow_traces')->insert([
            'step' => $step,
            'data' => json_encode($data),
            'duration' => $duration,
            'created_at' => now(),
        ]);
    }
}
```

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
- `when($condition, array $steps)` - Conditional execution
- `unless($condition, array $steps)` - Negative conditional execution
- `withTracer(Tracer $tracer)` - Add a tracer
- `thenReturn()` - Execute and return result

### Conditional Operations

- Field-based conditions: `field.operator.value`
- Dot notation: `user.profile.active`
- Array operations: `roles.contains.admin`

### Tracing Methods

- `getSteps()` - Get all traced steps
- `getLastStep()` - Get the last executed step
- `clearTraces()` - Clear all traces

---

For more examples and advanced usage patterns, see the [examples](../examples/README.md) directory.
