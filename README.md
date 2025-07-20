# Laravel Flowpipe

<img src="new_logo.png" alt="Laravel Flowpipe" width="200">

Composable, traceable and declarative Flow Pipelines for Laravel. A modern alternative to Laravel's Pipeline, with support for conditional steps, nested flows, tracing, validation, and more.

[![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)
[![License](https://img.shields.io/github/license/grazulex/laravel-flowpipe.svg?style=flat-square)](https://github.com/Grazulex/laravel-flowpipe/blob/main/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-flowpipe.svg?style=flat-square)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-flowpipe/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-flowpipe/actions)
[![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)

## 📖 Table of Contents

- [Overview](#overview)
- [✨ Features](#-features)
- [📦 Installation](#-installation)
- [🚀 Quick Start](#-quick-start)
- [🛡️ Error Handling](#️-error-handling)
- [🔗 Step Groups & Nested Flows](#-step-groups--nested-flows)
- [📋 YAML Flow Definitions](#-yaml-flow-definitions)
- [⚙️ Configuration](#️-configuration)
- [📚 Documentation](#-documentation)
- [💡 Examples](#-examples)
- [🧪 Testing](#-testing)
- [🔧 Requirements](#-requirements)
- [🚀 Performance](#-performance)
- [🤝 Contributing](#-contributing)
- [🔒 Security](#-security)
- [📄 License](#-license)

## Overview

Laravel Flowpipe is a powerful, modern alternative to Laravel's built-in Pipeline package that provides composable, traceable, and declarative flow pipelines. Perfect for building complex business workflows, data processing pipelines, and API integrations with advanced error handling and retry mechanisms.

## ✨ Features

- 🚀 **Fluent API** - Chainable, expressive syntax for building pipelines
- 🔄 **Flexible Steps** - Support for closures, classes, and custom steps
- 🎯 **Conditional Logic** - Built-in conditional step execution
- 📊 **Tracing & Debugging** - Track execution flow and performance
- 🛡️ **Error Handling** - Comprehensive retry, fallback, and compensation strategies
- � **Step Groups** - Reusable, named collections of steps
- 🎯 **Nested Flows** - Create isolated sub-workflows
- 📋 **YAML Support** - Define flows in YAML for easy configuration
- 🧪 **Test-Friendly** - Built-in test tracer for easy testing
- ⚡ **Performance** - Optimized for speed and memory efficiency
- 🎨 **Artisan Commands** - Full CLI support for flow management
- ✅ **Flow Validation** - Validate flow definitions with error reporting

## 📦 Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-flowpipe
```

> **💡 Auto-Discovery**  
> The service provider will be automatically registered thanks to Laravel's package auto-discovery.

Publish configuration:

```bash
php artisan vendor:publish --tag=flowpipe-config
```

## 🚀 Quick Start

### 1. Create a Basic Pipeline

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

### 2. Using Factory Methods

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Create with debug tracing
$debugFlow = Flowpipe::debug(true, 'flowpipe');
$result = $debugFlow
    ->send($data)
    ->through($steps)
    ->thenReturn();

// Create with performance tracing
$perfFlow = Flowpipe::performance();
$result = $perfFlow
    ->send($data)
    ->through($steps)
    ->thenReturn();

// Create with test tracing (for unit tests)
$testFlow = Flowpipe::test();
$result = $testFlow
    ->send($data)
    ->through($steps)
    ->thenReturn();
```

### 3. Using Step Groups

```php
// Define reusable step groups
Flowpipe::group('text-processing', [
    fn($data, $next) => $next(trim($data)),
    fn($data, $next) => $next(strtoupper($data)),
    fn($data, $next) => $next(str_replace(' ', '-', $data)),
]);

// Use groups in flows
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->useGroup('text-processing')
    ->through([
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

### 4. Using Error Handling

```php
// Exponential backoff retry
$result = Flowpipe::make()
    ->send(['api_url' => 'https://api.example.com/data'])
    ->exponentialBackoff(3, 100, 2.0) // 3 attempts, 100ms base delay, 2x multiplier
    ->through([
        fn($data, $next) => $next(callExternalAPI($data['api_url'])),
        fn($data, $next) => $next(processAPIResponse($data)),
    ])
    ->thenReturn();
```

## 🛡️ Error Handling

Laravel Flowpipe provides comprehensive error handling strategies:

```php
// Exponential backoff retry
$result = Flowpipe::make()
    ->send($userData)
    ->exponentialBackoff(3, 100, 2.0) // 3 attempts, 100ms base delay, 2x multiplier
    ->through([
        fn($data, $next) => $next(saveToDatabase($data)),
    ])
    ->thenReturn();

// Simple fallback with default value
$result = Flowpipe::make()
    ->send(['user_id' => 123])
    ->withFallback(fn($payload, $error) => ['cached_data' => true])
    ->through([
        fn($data, $next) => $next(fetchUserProfile($data['user_id'])),
    ])
    ->thenReturn();
```

## 🔗 Step Groups & Nested Flows

Laravel Flowpipe supports reusable step groups and nested flows for better organization:

### Step Groups

Define reusable groups of steps:

```php
// Define reusable step groups
Flowpipe::group('user-validation', [
    fn($user, $next) => $next(filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user : throw new InvalidArgumentException('Invalid email')),
    fn($user, $next) => $next(strlen($user['name']) > 0 ? $user : throw new InvalidArgumentException('Name required')),
]);

Flowpipe::group('notifications', [
    fn($user, $next) => $next(array_merge($user, ['email_sent' => true])),
    fn($user, $next) => $next(array_merge($user, ['logged' => true])),
]);

// Use groups in flows
$result = Flowpipe::make()
    ->send(['email' => 'user@example.com', 'name' => 'John Doe'])
    ->useGroup('user-validation')
    ->useGroup('notifications')
    ->thenReturn();
```

### Nested Flows

Create isolated sub-workflows:

```php
$result = Flowpipe::make()
    ->send('hello world')
    ->nested([
        // This nested flow runs independently
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
    ])
    ->through([
        // Main flow continues with nested result
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

## 📋 YAML Flow Definitions

Create flow definitions in YAML for easy configuration:

```yaml
# flow_definitions/user_processing.yaml
flow: UserProcessingFlow
description: Process user data with validation

send:
  name: "John Doe"
  email: "john@example.com"

steps:
  - type: group
    name: user-validation
  - type: nested
    steps:
      - type: closure
        action: uppercase
  - type: group
    name: notifications
```

```bash
# Run flows via Artisan commands
php artisan flowpipe:run user_processing
php artisan flowpipe:export user_processing --format=mermaid
```

## 💡 Examples

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

$user = Flowpipe::make()
    ->send($userData)
    ->through([
        new ValidateUserStep(),
        new SendWelcomeEmailStep(),
    ])
    ->thenReturn();
```

Check out the [examples directory](examples) for more examples.

## 🧪 Testing

Laravel Flowpipe includes a test tracer for easy testing:

```php
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

public function test_user_processing_flow()
{
    $result = Flowpipe::test()
        ->send(['name' => 'John'])
        ->through([
            fn($data, $next) => $next(strtoupper($data['name'])),
        ])
        ->thenReturn();
    
    $this->assertEquals('JOHN', $result);
}
```

## 📚 Documentation

For detailed documentation, examples, and advanced usage:

- 📚 [Full Documentation](docs/README.md)
- 🎯 [Examples](examples/README.md)
- 🔧 [Configuration](docs/configuration.md)
- 🧪 [Testing](docs/testing.md)
- 🛡️ [Error Handling](docs/error-handling.md)

## 🔧 Requirements

- PHP: ^8.3
- Laravel: ^12.0
- Carbon: ^3.10
- Symfony YAML: ^7.3

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover a security vulnerability, please review our [Security Policy](SECURITY.md) before disclosing it.

## 📄 License

Laravel Flowpipe is open-sourced software licensed under the [MIT license](LICENSE.md).

---

**Made with ❤️ for the Laravel community**

### Resources

- [📖 Documentation](docs/README.md)
- [💬 Discussions](https://github.com/Grazulex/laravel-flowpipe/discussions)
- [🐛 Issue Tracker](https://github.com/Grazulex/laravel-flowpipe/issues)
- [📦 Packagist](https://packagist.org/packages/grazulex/laravel-flowpipe)

### Community Links

- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) - Our code of conduct
- [CONTRIBUTING.md](CONTRIBUTING.md) - How to contribute
- [SECURITY.md](SECURITY.md) - Security policy
- [RELEASES.md](RELEASES.md) - Release notes and changelog

