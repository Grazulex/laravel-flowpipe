# Laravel Flowpipe

<div align="center">
  <img src="new_logo.png" alt="Laravel Flowpipe" width="200">
  
  **Composable, traceable and declarative Flow Pipelines for Laravel**
  
  *A modern alternative to Laravel's Pipeline with advanced features for complex workflow management*

  [![Latest Version](https://img.shields.io/packagist/v/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)
  [![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-flowpipe.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-flowpipe)
  [![License](https://img.shields.io/github/license/grazulex/laravel-flowpipe.svg?style=flat-square)](https://github.com/Grazulex/laravel-flowpipe/blob/main/LICENSE.md)
  [![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-flowpipe.svg?style=flat-square)](https://php.net/)
  [![Laravel Version](https://img.shields.io/badge/laravel-12.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
  [![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-flowpipe/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-flowpipe/actions)
  [![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)
</div>

---

## 🚀 Overview

Laravel Flowpipe is a powerful, modern alternative to Laravel's built-in Pipeline package that provides **composable**, **traceable**, and **declarative** flow pipelines. Perfect for building complex business workflows, data processing pipelines, user registration flows, API integrations, and any scenario where you need reliable, maintainable, and testable step-by-step processing.

## ✨ Key Features

- 🚀 **Fluent API** - Chainable, expressive syntax
- 🔄 **Flexible Steps** - Support for closures, classes, and custom steps  
- 🎯 **Conditional Logic** - Built-in conditional step execution
- 📊 **Tracing & Debugging** - Track execution flow and performance
- 🛡️ **Advanced Error Handling** - Retry, fallback, and compensation strategies
- 🔗 **Step Groups** - Reusable, named collections of steps
- 🎯 **Nested Flows** - Create isolated sub-workflows for complex logic
- 📋 **YAML Support** - Define flows in YAML for easy configuration
- 🧪 **Test-Friendly** - Built-in test tracer for easy testing
- 🎨 **Artisan Commands** - Full CLI support for flow management
- ✅ **Flow Validation** - Validate flow definitions with comprehensive error reporting
- 📈 **Export & Documentation** - Export to JSON, Mermaid, and Markdown

## 📦 Installation

Install the package via Composer:

```bash
composer require grazulex/laravel-flowpipe
```

> **💡 Auto-Discovery**: The service provider will be automatically registered thanks to Laravel's package auto-discovery.

## ⚡ Quick Start

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

## 🔧 Requirements

- **PHP 8.3+**
- **Laravel 12.0+**

## 📚 Complete Documentation

For comprehensive documentation, examples, and advanced usage guides, visit our **Wiki**:

### � **[👉 Laravel Flowpipe Wiki](https://github.com/Grazulex/laravel-flowpipe/wiki)**

The wiki includes:

- **📚 [Getting Started Guide](https://github.com/Grazulex/laravel-flowpipe/wiki/Getting-Started)**
- **🛡️ [Error Handling & Strategies](https://github.com/Grazulex/laravel-flowpipe/wiki/Error-Handling)**
- **🔗 [Step Groups & Nested Flows](https://github.com/Grazulex/laravel-flowpipe/wiki/Step-Groups-and-Nested-Flows)**
- **� [YAML Flow Definitions](https://github.com/Grazulex/laravel-flowpipe/wiki/YAML-Flow-Definitions)**
- **🎨 [Artisan Commands](https://github.com/Grazulex/laravel-flowpipe/wiki/Artisan-Commands)**
- **🧪 [Testing Guide](https://github.com/Grazulex/laravel-flowpipe/wiki/Testing)**
- **� [Examples & Use Cases](https://github.com/Grazulex/laravel-flowpipe/wiki/Examples)**
- **� [API Reference](https://github.com/Grazulex/laravel-flowpipe/wiki/API-Reference)**

---

## 🤝 Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security-related issues, please email **jms@grazulex.be** instead of using the issue tracker.

## 📝 Changelog

Please see [RELEASES.md](RELEASES.md) for more information on what has changed recently.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## 👥 Credits

- **[Jean-Marc Strauven](https://github.com/Grazulex)**
- **[All Contributors](../../contributors)**

## 💬 Support

- 🐛 **[Report Issues](https://github.com/Grazulex/laravel-flowpipe/issues)**
- � **[Discussions](https://github.com/Grazulex/laravel-flowpipe/discussions)**
- � **[Documentation](https://github.com/Grazulex/laravel-flowpipe/wiki)**

---

<div align="center">
  <strong>Laravel Flowpipe</strong> - A modern, powerful alternative to Laravel's built-in Pipeline<br>
  with enhanced features for complex workflow management.
</div>

