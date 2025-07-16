# Configuration

Laravel Flowpipe can be configured through the `config/flowpipe.php` file. Here are all available configuration options:

## Publishing Configuration

To publish the configuration file:

```bash
php artisan vendor:publish --provider="Grazulex\LaravelFlowpipe\LaravelFlowpipeServiceProvider"
```

## Current Configuration Options

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Flow Definitions Path
    |--------------------------------------------------------------------------
    |
    | This is where your YAML flow definitions live.
    | Relative to the base_path() of the Laravel app.
    |
    */
    'definitions_path' => 'flow_definitions',

    /*
    |--------------------------------------------------------------------------
    | Default Step Namespace
    |--------------------------------------------------------------------------
    |
    | This namespace will be prepended to step class names when resolving
    | strings like 'MyStep' into 'App\\Flowpipe\\Steps\\MyStep'.
    |
    */
    'step_namespace' => 'App\\Flowpipe\\Steps',

    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | This section configures the tracing functionality of Flowpipe.
    | You can enable or disable tracing and set the default tracer class.
    |
    */
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

## Configuration Details

### Flow Definitions Path

The `definitions_path` setting determines where your YAML flow definition files are stored:

```php
'definitions_path' => 'flow_definitions',
```

This path is relative to your Laravel application's base path. You can change it to:
- `'resources/flows'` - Store flows in resources directory
- `'storage/flows'` - Store flows in storage directory
- `'config/flows'` - Store flows in config directory

### Step Namespace

The `step_namespace` setting defines the default namespace for your step classes:

```php
'step_namespace' => 'App\\Flowpipe\\Steps',
```

When you reference a step in YAML as `MyStep`, it will be resolved to `App\Flowpipe\Steps\MyStep`.

### Tracing Configuration

Tracing helps you debug and monitor flow execution:

```php
'tracing' => [
    'enabled' => true,
    'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
],
```

- `enabled`: Whether tracing is active by default
- `default`: The default tracer class to use

## Usage Examples

### Basic Configuration

Most users will only need to modify the paths and namespace:

```php
// config/flowpipe.php
return [
    'definitions_path' => 'resources/flows',
    'step_namespace' => 'App\\Services\\Flows\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

### Development vs Production

For development, you might want tracing enabled:

```php
// config/flowpipe.php (development)
return [
    'definitions_path' => 'flow_definitions',
    'step_namespace' => 'App\\Flowpipe\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

For production, you might disable tracing for performance:

```php
// config/flowpipe.php (production)
return [
    'definitions_path' => 'flow_definitions',
    'step_namespace' => 'App\\Flowpipe\\Steps',
    'tracing' => [
        'enabled' => false,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

### Custom Tracer

You can create and use your own tracer:

```php
// config/flowpipe.php
return [
    'definitions_path' => 'flow_definitions',
    'step_namespace' => 'App\\Flowpipe\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \App\Tracers\DatabaseTracer::class,
    ],
];
```

## Available Tracers

### BasicTracer

The default tracer that provides basic tracing functionality:

```php
'tracing' => [
    'enabled' => true,
    'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
],
```

### TestTracer

Useful for testing and debugging:

```php
'tracing' => [
    'enabled' => true,
    'default' => \Grazulex\LaravelFlowpipe\Tracer\TestTracer::class,
],
```

## Best Practices

### 1. Organization

Keep your flows organized by using descriptive paths:

```php
'definitions_path' => 'resources/flows',
```

Then organize your files like:
```
resources/flows/
├── user/
│   ├── registration.yaml
│   └── activation.yaml
├── order/
│   ├── processing.yaml
│   └── fulfillment.yaml
└── admin/
    └── maintenance.yaml
```

### 2. Namespace Convention

Use a consistent namespace structure:

```php
'step_namespace' => 'App\\Flowpipe\\Steps',
```

And organize your step classes:
```
app/Flowpipe/Steps/
├── User/
│   ├── RegistrationStep.php
│   └── ActivationStep.php
├── Order/
│   ├── ProcessingStep.php
│   └── FulfillmentStep.php
└── Admin/
    └── MaintenanceStep.php
```

### 3. Tracing Strategy

Enable tracing during development and testing:

```php
'tracing' => [
    'enabled' => app()->environment('local', 'testing'),
    'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
],
```

## Configuration per Environment

You can create environment-specific configurations:

### config/flowpipe.php (base configuration)
```php
return [
    'definitions_path' => 'flow_definitions',
    'step_namespace' => 'App\\Flowpipe\\Steps',
    'tracing' => [
        'enabled' => true,
        'default' => \Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
```

### Programmatic Configuration

You can also configure Flowpipe programmatically:

```php
// In a service provider
use Grazulex\LaravelFlowpipe\Flowpipe;

public function boot()
{
    // Override configuration at runtime
    config(['flowpipe.tracing.enabled' => false]);
    
    // Or configure specific settings
    if (app()->environment('production')) {
        config(['flowpipe.tracing.enabled' => false]);
    }
}
```

## Troubleshooting

### Common Issues

1. **Flow definitions not found**: Check that your `definitions_path` is correct and files exist
2. **Step class not found**: Verify your `step_namespace` matches your actual class structure
3. **Tracing not working**: Ensure tracing is enabled and the tracer class exists

### Common Solutions

#### Flow Not Found Error
```bash
# Check if your flow files exist
ls -la flow_definitions/
```

#### Step Class Resolution Error
```php
// Make sure your step class exists at the correct path
// If step_namespace is 'App\\Flowpipe\\Steps'
// and your YAML references 'MyStep'
// The class should be at: app/Flowpipe/Steps/MyStep.php
```

#### Tracing Issues
```php
// Check if tracer class exists
use Grazulex\LaravelFlowpipe\Tracer\BasicTracer;
$tracer = new BasicTracer();
```

### Debug Tips

1. **Check configuration loading**:
   ```php
   dd(config('flowpipe'));
   ```

2. **Verify file paths**:
   ```php
   dd(base_path(config('flowpipe.definitions_path')));
   ```

3. **Test step resolution**:
   ```php
   use Grazulex\LaravelFlowpipe\Support\StepResolver;
   $resolver = new StepResolver();
   dd($resolver->resolve('MyStep'));
   ```

## Summary

The current Laravel Flowpipe configuration is minimal and focused. It provides:

- **Simple path configuration** for flow definitions
- **Namespace configuration** for step classes  
- **Basic tracing configuration** for debugging

This keeps the configuration clean and focused on essential features while remaining easily extensible for future enhancements.
