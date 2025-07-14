# Laravel Flowpipe

<div align="center">
  <img src="logo.png" alt="Laravel Arc" width="100">
  <p><strong>Composable, traceable and declarative Flow Pipelines for Laravel. A modern alternative to Laravel's Pipeline, with support for conditional steps, nested flows, tracing, validation, and more.</strong></p>
  
    [![Tests](https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml/badge.svg)](https://github.com/Grazulex/laravel-flowpipe/actions/workflows/tests.yml)
    [![Code Quality](https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml/badge.svg)](https://github.com/Grazulex/laravel-flowpipe/actions/workflows/code-quality.yml)
    [![Latest Version on Packagist](https://img.shields.io/packagist/v/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)
    [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)

</div>
**Composable, traceable and declarative Flow Pipelines for Laravel.** A modern alternative to Laravel's Pipeline, with support for conditional steps, nested flows, tracing, and more.

## Features

✨ **Fluent API** - Chainable, expressive syntax  
🔄 **Flexible Steps** - Support for closures, classes, and custom steps  
🎯 **Conditional Logic** - Built-in conditional step execution  
📊 **Tracing & Debugging** - Track execution flow and performance  
🧪 **Test-Friendly** - Built-in test tracer for easy testing  
🚀 **Laravel Integration** - Seamless service provider integration  
⚡ **Performance** - Optimized for speed and memory efficiency  

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
    ->send('Hello')
    ->through([
        fn($payload, $next) => $next(strtoupper($payload)),
        fn($payload, $next) => $next($payload . ' World'),
    ])
    ->thenReturn();

// Result: "HELLO World"
```

### Using Step Classes

```php
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;

$result = Flowpipe::make()
    ->send(['name' => 'John'])
    ->through([
        ClosureStep::make(fn($data, $next) => $next([
            ...$data, 
            'processed' => true
        ])),
        ValidateDataStep::class, // Your custom step class
        TransformDataStep::class,
    ])
    ->thenReturn();
```

### Custom Steps

Create your own step by implementing the `FlowStep` interface:

```php
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

class ValidateEmailStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
        
        return $next($payload);
    }
}
```

## Advanced Usage

### Conditional Steps

Execute steps based on conditions:

```php
use Grazulex\LaravelFlowpipe\Steps\ConditionalStep;

$result = Flowpipe::make()
    ->send($user)
    ->through([
        ConditionalStep::when(
            new IsAdminCondition(),
            new AdminOnlyStep()
        ),
        ConditionalStep::unless(
            new IsVerifiedCondition(),
            new SendVerificationStep()
        ),
    ])
    ->thenReturn();
```

### Tracing & Debugging

Track your pipeline execution with built-in tracers:

```php
use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;

$tracer = new BasicTracer();

$result = Flowpipe::make($tracer)
    ->send($data)
    ->through($steps)
    ->thenReturn();

// Access execution trace
$context = $pipeline->context();
$tracer = $context->tracer();
```

### Test Tracer

Use the test tracer for unit testing:

```php
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

$tracer = new TestTracer();

Flowpipe::make($tracer)
    ->send('test')
    ->through([SomeStep::class])
    ->thenReturn();

// Assert execution
expect($tracer->count())->toBe(1);
expect($tracer->firstStep())->toBe('SomeStep');
expect($tracer->steps())->toContain('SomeStep');
```

## Architecture

### Core Components

- **`Flowpipe`** - Main pipeline class with fluent API
- **`FlowStep`** - Interface for pipeline steps
- **`FlowContext`** - Context container for pipeline execution
- **`StepResolver`** - Resolves different step types (closures, classes, strings)
- **`Tracer`** - Interface for execution tracing

### Step Types

1. **Closure Steps** - Anonymous functions
2. **Class Steps** - Step classes implementing `FlowStep`
3. **String Steps** - Class names resolved via Laravel's service container
4. **Conditional Steps** - Steps with conditional execution logic

### Tracers

- **`BasicTracer`** - Simple console output tracer
- **`TestTracer`** - Full-featured tracer for testing
- **Custom Tracers** - Implement `Tracer` interface for custom tracing

## Testing

Run the test suite:

```bash
composer test
```

Run specific test types:

```bash
# Unit tests only
./vendor/bin/pest tests/Unit

# Feature tests only  
./vendor/bin/pest tests/Feature

# With coverage
./vendor/bin/pest --coverage
```

## Code Quality

This package follows strict code quality standards:

```bash
# Code style (Laravel Pint)
composer pint

# Static analysis (PHPStan)
composer phpstan

# Refactoring (Rector)
composer rector

# Run all quality checks
composer full
```

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
