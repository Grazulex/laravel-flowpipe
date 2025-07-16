# Step Groups & Nesting

Laravel Flowpipe supports reusable step groups and nested flows for better code organization and modularity. With enhanced Mermaid export capabilities, you can visualize your flow structures with rich color coding for different step types.

## Overview

Step Groups allow you to define reusable collections of steps that can be referenced by name in your flows. This promotes code reuse and better organization of complex workflows.

Nested Flows allow you to create sub-workflows that run independently within your main flow, providing better isolation and modularity.

## Enhanced Visualization with Group Colors

Laravel Flowpipe now supports enhanced Mermaid diagrams with rich color coding:

- **Groups**: Blue theme (`📦 Group elements`)
- **Nested Flows**: Light green theme (`🔄 Nested elements`)
- **Conditional Steps**: Orange theme (`❓ Conditional elements`)
- **Transform Steps**: Pink theme (`🔄 Transform elements`)
- **Validation Steps**: Green theme (`✅ Validation elements`)
- **Cache Steps**: Yellow theme (`💾 Cache elements`)
- **Batch Steps**: Purple theme (`📊 Batch elements`)
- **Retry Steps**: Red theme (`🔄 Retry elements`)

You can export any flow or group to see these color-coded visualizations:

```bash
# Export a group with colors
php artisan flowpipe:export user-validation --type=group --format=mermaid

# Export a flow with enhanced colors
php artisan flowpipe:export user-processing --format=mermaid
```

## Step Groups

### Defining Step Groups

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Define a reusable group
Flowpipe::group('user-validation', [
    ValidateEmailStep::class,
    CheckUserExistsStep::class,
    ValidatePasswordStep::class,
]);

// Define another group
Flowpipe::group('notifications', [
    SendEmailStep::class,
    LogEventStep::class,
    UpdateDashboardStep::class,
]);
```

### Using Groups in Flows

You can use groups in two ways:

#### 1. Through the `through()` method

```php
$result = Flowpipe::make()
    ->send($userData)
    ->through([
        'user-validation', // Reference the group by name
        CreateUserStep::class,
        'notifications',
    ])
    ->thenReturn();
```

#### 2. Using the `useGroup()` method

```php
$result = Flowpipe::make()
    ->send($userData)
    ->useGroup('user-validation')
    ->through([
        CreateUserStep::class,
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

## Nested Flows

Nested flows allow you to create sub-workflows that run independently:

```php
$result = Flowpipe::make()
    ->send($data)
    ->nested([
        // This mini-flow runs independently
        ValidationStep::class,
        TransformStep::class,
    ])
    ->through([
        // Main flow continues here
        ProcessStep::class,
        NotifyStep::class,
    ])
    ->thenReturn();
```

### Benefits of Nested Flows

1. **Isolation**: Each nested flow runs in its own context
2. **Modularity**: Complex logic can be broken down into manageable pieces
3. **Reusability**: Nested flows can be easily extracted and reused
4. **Testing**: Each nested flow can be tested independently

## Combining Groups and Nesting

You can combine both features for powerful workflow composition:

```php
// Define groups for different concerns
Flowpipe::group('validation', [
    ValidateInputStep::class,
    SanitizeDataStep::class,
]);

Flowpipe::group('notifications', [
    SendEmailStep::class,
    LogEventStep::class,
]);

// Use in complex flows
$result = Flowpipe::make()
    ->send($data)
    ->useGroup('validation')
    ->nested([
        // Complex processing in isolation
        CalculateMetricsStep::class,
        GenerateReportStep::class,
    ])
    ->useGroup('notifications')
    ->thenReturn();
```

## Advanced Examples

### E-commerce Order Processing

```php
// Define reusable groups
Flowpipe::group('order-validation', [
    ValidateOrderDataStep::class,
    CheckInventoryStep::class,
    ValidatePaymentStep::class,
]);

Flowpipe::group('order-processing', [
    ProcessPaymentStep::class,
    UpdateInventoryStep::class,
    CreateOrderStep::class,
]);

Flowpipe::group('order-fulfillment', [
    GenerateInvoiceStep::class,
    SendConfirmationStep::class,
    NotifyWarehouseStep::class,
]);

// Main order processing flow
$result = Flowpipe::make()
    ->send($orderData)
    ->useGroup('order-validation')
    ->nested([
        // Handle special promotions
        CheckPromotionsStep::class,
        ApplyDiscountsStep::class,
    ])
    ->useGroup('order-processing')
    ->useGroup('order-fulfillment')
    ->thenReturn();
```

### User Registration with Conditional Logic

```php
Flowpipe::group('user-validation', [
    ValidateEmailStep::class,
    CheckPasswordStrengthStep::class,
    VerifyUniqueUsernameStep::class,
]);

Flowpipe::group('user-setup', [
    CreateUserStep::class,
    SetupUserPreferencesStep::class,
    CreateUserDirectoryStep::class,
]);

$result = Flowpipe::make()
    ->send($userData)
    ->useGroup('user-validation')
    ->when('email_verified', [
        'user-setup',
    ])
    ->nested([
        // Send welcome sequence
        SendWelcomeEmailStep::class,
        ScheduleOnboardingStep::class,
    ])
    ->unless('email_verified', [
        SendVerificationEmailStep::class,
    ])
    ->thenReturn();
```

## Best Practices

### 1. Group Organization

- Group related steps together
- Use descriptive group names
- Keep groups focused on a single responsibility

```php
// Good
Flowpipe::group('user-validation', [
    ValidateEmailStep::class,
    CheckUserExistsStep::class,
]);

// Avoid
Flowpipe::group('mixed-stuff', [
    ValidateEmailStep::class,
    SendEmailStep::class,
    UpdateDatabaseStep::class,
]);
```

### 2. Nested Flow Usage

- Use nested flows for complex, related operations
- Keep nested flows small and focused
- Consider extracting complex nested flows into separate methods

```php
// Good
->nested([
    CalculateShippingStep::class,
    ApplyShippingDiscountStep::class,
])

// Consider refactoring if too complex
->nested([
    // Too many steps here...
])
```

### 3. Performance Considerations

- Groups are resolved at runtime, so there's minimal overhead
- Nested flows create new Flowpipe instances, which has a small memory cost
- Use caching for frequently used groups in high-traffic applications

### 4. Testing

- Test groups independently
- Use fixtures for complex group setups
- Test nested flows in isolation

```php
public function test_user_validation_group()
{
    $result = Flowpipe::make()
        ->send($testData)
        ->useGroup('user-validation')
        ->thenReturn();
        
    $this->assertTrue($result['is_valid']);
}
```

## Error Handling

Groups and nested flows inherit the error handling behavior of their parent flow:

```php
$result = Flowpipe::make()
    ->send($data)
    ->useGroup('validation')
    ->nested([
        // If this fails, the error bubbles up
        RiskyOperationStep::class,
    ])
    ->through([
        // This step can handle errors from nested flow
        ErrorHandlingStep::class,
    ])
    ->thenReturn();
```

## API Reference

### Static Methods

- `Flowpipe::group(string $name, array $steps)` - Define a step group
- `Flowpipe::getGroups()` - Get all registered groups
- `Flowpipe::hasGroup(string $name)` - Check if a group exists
- `Flowpipe::clearGroups()` - Clear all registered groups

### Instance Methods

- `useGroup(string $name)` - Add a group to the flow
- `nested(array $steps)` - Create a nested flow

## Enhanced Mermaid Export Examples

### Exporting Groups with Colors

```php
// Define a colorful group
Flowpipe::group('data-processing', [
    // Transform steps will appear in pink
    fn($data, $next) => $next(array_map('strtoupper', $data)),
    // Validation steps will appear in green
    fn($data, $next) => $next(array_filter($data, fn($item) => strlen($item) > 0)),
    // Cache steps will appear in yellow
    fn($data, $next) => $next(Cache::remember('processed-' . md5(serialize($data)), 3600, fn() => $data)),
]);

// Export this group to see the color-coded visualization
// php artisan flowpipe:export data-processing --type=group --format=mermaid
```

### Complex Flow with Multiple Colors

```php
$result = Flowpipe::make()
    ->send($data)
    ->useGroup('data-processing')  // Blue group box
    ->nested([                     // Light green nested flow
        fn($data, $next) => $next(array_merge($data, ['processed' => true])),
    ])
    ->transform(fn($data) => $data) // Pink transform step
    ->validate(['required' => true]) // Green validation step
    ->cache('result-key', 3600)     // Yellow cache step
    ->batch(100)                    // Purple batch step
    ->retry(3)                      // Red retry step
    ->thenReturn();

// Export this flow to see all the different colored steps
// php artisan flowpipe:export complex-flow --format=mermaid
```

For more examples and advanced usage, see the [examples directory](../examples/).
