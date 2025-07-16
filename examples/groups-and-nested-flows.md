# Step Groups and Nested Flows Examples

This document provides comprehensive examples of using Step Groups and Nested Flows in Laravel Flowpipe, including enhanced Mermaid export capabilities with rich color coding.

## Table of Contents

1. [Basic Step Groups](#basic-step-groups)
2. [Nested Flows](#nested-flows)
3. [Combining Groups and Nested Flows](#combining-groups-and-nested-flows)
4. [Enhanced Mermaid Export with Colors](#enhanced-mermaid-export-with-colors)
5. [YAML Definitions](#yaml-definitions)
6. [Real-world Examples](#real-world-examples)

## Enhanced Mermaid Export with Colors

Laravel Flowpipe now supports rich color coding in Mermaid diagrams for different step types:

- **Groups**: Blue theme (`📦 Group elements`)
- **Nested Flows**: Light green theme (`🔄 Nested elements`)
- **Conditional Steps**: Orange theme (`❓ Conditional elements`)
- **Transform Steps**: Pink theme (`🔄 Transform elements`)
- **Validation Steps**: Green theme (`✅ Validation elements`)
- **Cache Steps**: Yellow theme (`💾 Cache elements`)
- **Batch Steps**: Purple theme (`📊 Batch elements`)
- **Retry Steps**: Red theme (`🔄 Retry elements`)

### Exporting Groups with Enhanced Colors

```bash
# Export a group with enhanced color coding
php artisan flowpipe:export user-validation --type=group --format=mermaid

# Export a flow with all color-coded steps
php artisan flowpipe:export user-registration --format=mermaid

# Export to markdown with embedded colored diagram
php artisan flowpipe:export user-registration --format=md --output=docs/user-registration.md
```

## Basic Step Groups

### Defining and Using Groups

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Define text processing group
Flowpipe::group('text-processing', [
    fn($data, $next) => $next(trim($data)),
    fn($data, $next) => $next(strtoupper($data)),
    fn($data, $next) => $next(str_replace(' ', '-', $data)),
]);

// Define validation group
Flowpipe::group('text-validation', [
    fn($data, $next) => $next(strlen($data) > 0 ? $data : throw new InvalidArgumentException('Empty text')),
    fn($data, $next) => $next(preg_match('/^[A-Z-]+$/', $data) ? $data : throw new InvalidArgumentException('Invalid format')),
]);

// Use groups in a flow
$result = Flowpipe::make()
    ->send('  hello world  ')
    ->useGroup('text-processing')
    ->useGroup('text-validation')
    ->through([
        fn($data, $next) => $next($data . '!'),
    ])
    ->thenReturn();

// Result: "HELLO-WORLD!"
```

### Group Management

```php
// Check if groups exist
if (Flowpipe::hasGroup('text-processing')) {
    echo "Group exists!\n";
}

// Get all groups
$groups = Flowpipe::getGroups();
foreach ($groups as $name => $steps) {
    echo "Group: $name has " . count($steps) . " steps\n";
}

// Clear all groups (useful for testing)
Flowpipe::clearGroups();
```

## Nested Flows

### Basic Nested Flow

```php
$result = Flowpipe::make()
    ->send(['name' => 'John Doe', 'email' => 'john@example.com'])
    ->nested([
        // This sub-flow runs independently
        fn($data, $next) => $next(array_merge($data, ['processed' => true])),
        fn($data, $next) => $next(array_merge($data, ['id' => uniqid()])),
    ])
    ->through([
        // Main flow continues here
        fn($data, $next) => $next(array_merge($data, ['completed' => true])),
    ])
    ->thenReturn();

// Result: Array with name, email, processed, id, and completed fields
```

### Multiple Nested Flows

```php
$result = Flowpipe::make()
    ->send('raw data')
    ->nested([
        // First nested flow: cleaning
        fn($data, $next) => $next(trim($data)),
        fn($data, $next) => $next(strtolower($data)),
    ])
    ->nested([
        // Second nested flow: formatting
        fn($data, $next) => $next(ucwords($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
    ])
    ->through([
        // Main flow: finalization
        fn($data, $next) => $next($data . '-final'),
    ])
    ->thenReturn();

// Result: "Raw-Data-final"
```

## Combining Groups and Nested Flows

### E-commerce Order Processing

```php
use Grazulex\LaravelFlowpipe\Flowpipe;

// Define reusable groups
Flowpipe::group('order-validation', [
    fn($order, $next) => $next(isset($order['items']) && count($order['items']) > 0 ? $order : throw new InvalidArgumentException('No items')),
    fn($order, $next) => $next(isset($order['customer']) ? $order : throw new InvalidArgumentException('No customer')),
    fn($order, $next) => $next($order['total'] > 0 ? $order : throw new InvalidArgumentException('Invalid total')),
]);

Flowpipe::group('inventory-check', [
    fn($order, $next) => $next(array_merge($order, ['inventory_checked' => true])),
    fn($order, $next) => $next(array_merge($order, ['items_available' => true])),
]);

Flowpipe::group('notifications', [
    fn($order, $next) => $next(array_merge($order, ['customer_notified' => true])),
    fn($order, $next) => $next(array_merge($order, ['admin_notified' => true])),
]);

// Process order with groups and nested flows
$result = Flowpipe::make()
    ->send([
        'items' => [['name' => 'Product A', 'price' => 10]],
        'customer' => ['name' => 'John', 'email' => 'john@example.com'],
        'total' => 10
    ])
    ->useGroup('order-validation')
    ->useGroup('inventory-check')
    ->nested([
        // Complex payment processing in isolation
        fn($order, $next) => $next(array_merge($order, ['payment_id' => 'pay_' . uniqid()])),
        fn($order, $next) => $next(array_merge($order, ['payment_status' => 'completed'])),
        fn($order, $next) => $next(array_merge($order, ['payment_date' => date('Y-m-d H:i:s')])),
    ])
    ->nested([
        // Fulfillment processing
        fn($order, $next) => $next(array_merge($order, ['order_id' => 'order_' . uniqid()])),
        fn($order, $next) => $next(array_merge($order, ['status' => 'processing'])),
    ])
    ->useGroup('notifications')
    ->thenReturn();

// Result: Complete order with validation, payment, fulfillment, and notifications
```

### User Registration Flow

```php
// Define user processing groups
Flowpipe::group('user-validation', [
    fn($user, $next) => $next(filter_var($user['email'], FILTER_VALIDATE_EMAIL) ? $user : throw new InvalidArgumentException('Invalid email')),
    fn($user, $next) => $next(strlen($user['password']) >= 8 ? $user : throw new InvalidArgumentException('Password too short')),
    fn($user, $next) => $next(strlen($user['name']) > 0 ? $user : throw new InvalidArgumentException('Name required')),
]);

Flowpipe::group('user-setup', [
    fn($user, $next) => $next(array_merge($user, ['id' => uniqid()])),
    fn($user, $next) => $next(array_merge($user, ['created_at' => date('Y-m-d H:i:s')])),
    fn($user, $next) => $next(array_merge($user, ['status' => 'active'])),
]);

Flowpipe::group('user-notifications', [
    fn($user, $next) => $next(array_merge($user, ['welcome_email_sent' => true])),
    fn($user, $next) => $next(array_merge($user, ['admin_notified' => true])),
]);

// Complete registration flow
$result = Flowpipe::make()
    ->send([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'securepassword123'
    ])
    ->useGroup('user-validation')
    ->nested([
        // Password hashing in isolation
        fn($user, $next) => $next(array_merge($user, ['password' => password_hash($user['password'], PASSWORD_DEFAULT)])),
    ])
    ->useGroup('user-setup')
    ->nested([
        // Profile setup
        fn($user, $next) => $next(array_merge($user, ['profile_created' => true])),
        fn($user, $next) => $next(array_merge($user, ['preferences_set' => true])),
    ])
    ->useGroup('user-notifications')
    ->thenReturn();

// Result: Complete user registration with hashed password, profile, and notifications
```

## YAML Definitions

### Group Definitions

Create group definitions in YAML:

```yaml
# groups/text-processing.yaml
group: text-processing
description: Process text data
steps:
  - type: closure
    action: trim
  - type: closure
    action: uppercase
  - type: closure
    action: replace
    from: ' '
    to: '-'
```

```yaml
# groups/user-validation.yaml
group: user-validation
description: Validate user data
steps:
  - type: closure
    action: validate_email
  - type: closure
    action: validate_password
  - type: closure
    action: validate_name
```

### Flow with Groups and Nested Steps

```yaml
# flows/user-registration.yaml
flow: UserRegistrationFlow
description: Complete user registration with groups and nested flows

send:
  name: "John Doe"
  email: "john@example.com"
  password: "securepassword123"

steps:
  # Use predefined group
  - type: group
    name: user-validation
    
  # Nested flow for password processing
  - type: nested
    steps:
      - type: closure
        action: hash_password
      - type: closure
        action: validate_hash
        
  # Another group
  - type: group
    name: user-setup
    
  # Complex nested flow
  - type: nested
    steps:
      - type: closure
        action: create_profile
      - type: closure
        action: set_preferences
      - condition:
          field: email_verified
          operator: equals
          value: true
        then:
          - type: closure
            action: activate_account
        else:
          - type: closure
            action: send_verification
            
  # Final notifications group
  - type: group
    name: user-notifications
```

## Real-world Examples

### Enhanced Color Visualization Example

```php
// Create a flow with multiple step types to showcase colors
Flowpipe::group('data-validation', [
    fn($data, $next) => $next(filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data : throw new InvalidArgumentException('Invalid email')),
    fn($data, $next) => $next(strlen($data['name']) > 0 ? $data : throw new InvalidArgumentException('Name required')),
]);

Flowpipe::group('data-processing', [
    fn($data, $next) => $next(array_merge($data, ['processed_at' => now()])),
    fn($data, $next) => $next(array_merge($data, ['id' => uniqid()])),
]);

$result = Flowpipe::make()
    ->send(['name' => 'John Doe', 'email' => 'john@example.com'])
    ->useGroup('data-validation')         // Blue group
    ->transform(fn($data) => $data)       // Pink transform
    ->validate(['email' => 'required'])   // Green validation
    ->cache('user-data', 3600)           // Yellow cache
    ->nested([                           // Light green nested
        fn($data, $next) => $next(array_merge($data, ['nested_processed' => true])),
    ])
    ->useGroup('data-processing')        // Blue group
    ->batch(100)                         // Purple batch
    ->retry(3)                           // Red retry
    ->thenReturn();

// Export this flow to see the color-coded visualization:
// php artisan flowpipe:export enhanced-color-demo --format=mermaid
```

The exported Mermaid diagram will show:
- Blue boxes for groups (`data-validation`, `data-processing`)
- Pink box for transform step
- Green box for validation step
- Yellow box for cache step
- Light green box for nested flow
- Purple box for batch step
- Red box for retry step

### Data Processing Pipeline

```php
// ETL Pipeline with groups and nested flows
Flowpipe::group('data-extraction', [
    fn($config, $next) => $next(array_merge($config, ['raw_data' => 'extracted_data'])),
    fn($config, $next) => $next(array_merge($config, ['extraction_time' => microtime(true)])),
]);

Flowpipe::group('data-validation', [
    fn($data, $next) => $next(array_merge($data, ['validation_passed' => true])),
    fn($data, $next) => $next(array_merge($data, ['validation_time' => microtime(true)])),
]);

Flowpipe::group('data-loading', [
    fn($data, $next) => $next(array_merge($data, ['loaded' => true])),
    fn($data, $next) => $next(array_merge($data, ['load_time' => microtime(true)])),
]);

$result = Flowpipe::make()
    ->send(['source' => 'database', 'table' => 'users'])
    ->useGroup('data-extraction')
    ->useGroup('data-validation')
    ->nested([
        // Complex transformations
        fn($data, $next) => $next(array_merge($data, ['transformed' => true])),
        fn($data, $next) => $next(array_merge($data, ['enriched' => true])),
        fn($data, $next) => $next(array_merge($data, ['formatted' => true])),
    ])
    ->useGroup('data-loading')
    ->thenReturn();
```

### Content Management System

```php
// CMS workflow with groups and nested flows
Flowpipe::group('content-validation', [
    fn($content, $next) => $next(strlen($content['title']) > 0 ? $content : throw new InvalidArgumentException('Title required')),
    fn($content, $next) => $next(strlen($content['body']) > 0 ? $content : throw new InvalidArgumentException('Body required')),
]);

Flowpipe::group('content-processing', [
    fn($content, $next) => $next(array_merge($content, ['slug' => strtolower(str_replace(' ', '-', $content['title']))])),
    fn($content, $next) => $next(array_merge($content, ['word_count' => str_word_count($content['body'])])),
]);

Flowpipe::group('content-publishing', [
    fn($content, $next) => $next(array_merge($content, ['published_at' => date('Y-m-d H:i:s')])),
    fn($content, $next) => $next(array_merge($content, ['status' => 'published'])),
]);

$result = Flowpipe::make()
    ->send([
        'title' => 'My Blog Post',
        'body' => 'This is the content of my blog post.',
        'author' => 'John Doe'
    ])
    ->useGroup('content-validation')
    ->useGroup('content-processing')
    ->nested([
        // SEO optimization
        fn($content, $next) => $next(array_merge($content, ['meta_description' => substr($content['body'], 0, 160)])),
        fn($content, $next) => $next(array_merge($content, ['seo_optimized' => true])),
    ])
    ->nested([
        // Image processing
        fn($content, $next) => $next(array_merge($content, ['featured_image' => 'default.jpg'])),
        fn($content, $next) => $next(array_merge($content, ['thumbnails_generated' => true])),
    ])
    ->useGroup('content-publishing')
    ->thenReturn();
```

## Best Practices

1. **Group Organization**: Keep groups focused on single responsibilities
2. **Naming**: Use descriptive names for groups and nested flows
3. **Testing**: Test groups and nested flows independently
4. **Documentation**: Document complex group interactions
5. **Error Handling**: Implement proper error handling in nested flows
6. **Performance**: Consider the overhead of nested flows for simple operations

## Testing Groups and Nested Flows

```php
use Grazulex\LaravelFlowpipe\Tracer\TestTracer;

public function test_group_execution()
{
    $tracer = new TestTracer();
    
    Flowpipe::group('test-group', [
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next($data . '!'),
    ]);
    
    $result = Flowpipe::make()
        ->send('hello')
        ->useGroup('test-group')
        ->withTracer($tracer)
        ->thenReturn();
    
    $this->assertEquals('HELLO!', $result);
    $this->assertCount(1, $tracer->count()); // Group counts as one step
}

public function test_nested_flow_execution()
{
    $tracer = new TestTracer();
    
    $result = Flowpipe::make()
        ->send('hello')
        ->nested([
            fn($data, $next) => $next(strtoupper($data)),
            fn($data, $next) => $next($data . '!'),
        ])
        ->withTracer($tracer)
        ->thenReturn();
    
    $this->assertEquals('HELLO!', $result);
    $this->assertCount(1, $tracer->count()); // Nested flow counts as one step
}
```

This comprehensive guide shows how to effectively use step groups and nested flows in Laravel Flowpipe for building modular, maintainable workflows.