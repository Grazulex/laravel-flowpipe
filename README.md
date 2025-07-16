# Laravel Flowpipe

<p align="center">
  <img src="logo.png" alt="Laravel Flowpipe" width="100">
</p>

<p align="center">
  <strong>Composable, traceable and declarative Flow Pipelines for Laravel. A modern alternative to Laravel's Pipeline, with support for conditional steps, nested flows, tracing, validation, and more.</strong>
</p>

<p align="center">
  <a href="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml"><img src="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml"><img src="https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml/badge.svg" alt="Code Quality"></a>
  <a href="https://packagist.org/packages/grazulex/laravel-flowpipe"><img src="https://img.shields.io/packagist/v/grazulex/laravel-flowpipe.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/grazulex/laravel-flowpipe"><img src="https://img.shields.io/packagist/dt/grazulex/laravel-flowpipe.svg?style=flat-square" alt="Total Downloads"></a>
</p>


## Features

✨ **Fluent API** - Chainable, expressive syntax  
🔄 **Flexible Steps** - Support for closures, classes, and custom steps  
🎯 **Conditional Logic** - Built-in conditional step execution with dot notation  
📊 **Tracing & Debugging** - Track execution flow and performance  
🧪 **Test-Friendly** - Built-in test tracer for easy testing  
🚀 **Laravel Integration** - Seamless service provider integration  
⚡ **Performance** - Optimized for speed and memory efficiency  
📋 **YAML Flows** - Define flows in YAML for easy configuration  
🎨 **Artisan Commands** - Full CLI support for flow management  
📈 **Export & Documentation** - Export to JSON, Mermaid, and Markdown  

## Requirements

- PHP 8.3+
- Laravel 12.0+

## Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-flowpipe
```

The service provider will be automatically registered thanks to Laravel's package auto-discovery.

## Quick Start

### Basic Pipeline

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

$result = Flowpipe::make()
    ->send('Hello World')
    ->through([
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

### Conditional Steps

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

// Result: "JOHN"
```

### YAML Flow Definitions

Create flow definitions in YAML for easy configuration:

```yaml
# flow_definitions/user_processing.yaml
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
      - condition:
          field: is_active
          operator: equals
          value: true
        then:
          - type: closure
            action: uppercase
        else:
          - type: closure
            action: lowercase
    else:
      - type: closure
        action: append
        value: " - Invalid email"
```

### Artisan Commands

Laravel Flowpipe comes with powerful Artisan commands:

```bash
# List all available flows
php artisan flowpipe:list
php artisan flowpipe:list --detailed

# Run a flow
php artisan flowpipe:run user_processing

# Export flows to different formats
php artisan flowpipe:export user_processing --format=json
php artisan flowpipe:export user_processing --format=mermaid
php artisan flowpipe:export user_processing --format=md --output=docs/user_processing.md

# Create new flows
php artisan flowpipe:make-flow NewUserFlow --template=basic
php artisan flowpipe:make-flow ComplexFlow --template=conditional
php artisan flowpipe:make-flow AdvancedFlow --template=advanced

# Generate step classes
php artisan flowpipe:make-step ProcessUserStep
```

## Documentation

For detailed documentation, examples, and advanced usage, please see:

- 📚 [Full Documentation](docs/README.md)
- 🎯 [Examples](examples/README.md)
- 🔧 [Configuration](docs/configuration.md)
- 🧪 [Testing](docs/testing.md)
- 🎨 [Artisan Commands](docs/commands.md)

## Examples

### Basic Text Processing

```php
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->through([
        fn($text, $next) => $next(trim($text)),
        fn($text, $next) => $next(ucwords($text)),
        fn($text, $next) => $next(str_replace(' ', '-', $text)),
    ])
    ->thenReturn();

// Result: "Hello-World"
```

### User Registration Flow

```php
use App\Flowpipe\Steps\ValidateUserStep;
use App\Flowpipe\Steps\SendWelcomeEmailStep;
use App\Flowpipe\Steps\AddToCrmStep;
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['is_active'] ?? false);
    }
}

$user = Flowpipe::make()
    ->send($userData)
    ->through([
        new ValidateUserStep(),
        ConditionalStep::when(
            new IsActiveCondition(),
            new SendWelcomeEmailStep()
        ),
        ConditionalStep::when(
            new IsActiveCondition(),
            new AddToCrmStep()
        ),
    ])
    ->withTracer(new BasicTracer())
    ->thenReturn();
```

### Complex Conditional Logic

```php
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;
use Grazulex\LaravelFlowpipe\Contracts\Condition;

class IsAdminCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['role'] ?? '') === 'admin';
    }
}

class IsActiveCondition implements Condition
{
    public function evaluate(mixed $payload): bool
    {
        return is_array($payload) && ($payload['active'] ?? false);
    }
}

$result = Flowpipe::make()
    ->send(['user' => ['role' => 'admin', 'active' => true]])
    ->through([
        fn($data, $next) => $next($data['user']),
        ConditionalStep::when(
            new IsAdminCondition(),
            fn($user, $next) => $next(array_merge($user, ['permissions' => ['read', 'write', 'delete']]))
        ),
        ConditionalStep::when(
            new IsActiveCondition(),
            fn($user, $next) => $next(array_merge($user, ['status' => 'enabled']))
        ),
        ConditionalStep::unless(
            new IsActiveCondition(),
            fn($user, $next) => $next(array_merge($user, ['status' => 'disabled']))
        ),
    ])
    ->thenReturn();
```

## Testing

Laravel Flowpipe includes a dedicated test tracer for easy testing:

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

## Performance

Laravel Flowpipe is optimized for performance:

- **Lazy Evaluation**: Steps are only executed when needed
- **Memory Efficient**: Minimal memory footprint
- **Traceable**: Optional tracing with minimal overhead
- **Cacheable**: Flow definitions can be cached for better performance

## API Reference

### Flowpipe Methods

- `make()` - Create a new flowpipe instance
- `send($data)` - Set initial data
- `through(array $steps)` - Add steps to the pipeline
- `cache($key, $ttl, $store)` - Add cache step
- `retry($maxAttempts, $delayMs, $shouldRetry)` - Add retry step
- `rateLimit($key, $maxAttempts, $decayMinutes, $keyGenerator)` - Add rate limit step
- `transform($transformer)` - Add transform step
- `validate($rules, $messages, $customAttributes)` - Add validation step
- `batch($batchSize, $preserveKeys)` - Add batch step
- `withTracer(Tracer $tracer)` - Add a tracer
- `thenReturn()` - Execute and return result
- `context()` - Get flow context

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

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email jms@grazulex.be instead of using the issue tracker.

## Changelog

Please see [RELEASES.md](RELEASES.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Jean-Marc Strauven](https://github.com/Grazulex)
- [All Contributors](../../contributors)

## Support

- 🐛 [Report Issues](https://github.com/Grazulex/laravel-flowpipe/issues)
- 💬 [Discussions](https://github.com/Grazulex/laravel-flowpipe/discussions)
- 📚 [Documentation](https://github.com/Grazulex/laravel-flowpipe/wiki)

---

**Laravel Flowpipe** is a modern, powerful alternative to Laravel's built-in Pipeline with enhanced features for complex workflow management.
