# Examples

This directory contains practical examples of using Laravel Flowpipe in real-world scenarios, with special focus on the new **Step Groups** and **Nested Flows** features.

## Directory Structure

```
examples/
├── flows/           # Example YAML flow definitions
├── groups/          # Example group definitions (YAML)
├── steps/           # Example step implementations
├── conditions/      # Example condition implementations
├── groups-and-nested-flows.md  # Comprehensive guide
├── ecommerce-order-processing-groups.php  # PHP example
├── user-registration-groups.php           # PHP example
└── README.md        # This file
```

## New Features Examples

### 1. Step Groups and Nested Flows Guide
- **File**: `groups-and-nested-flows.md`
- **Description**: Comprehensive guide with examples showing how to use step groups and nested flows
- **Features**: Basic groups, nested flows, combinations, YAML definitions, real-world examples

### 2. E-commerce Order Processing with Groups
- **PHP Example**: `ecommerce-order-processing-groups.php`
- **YAML Flow**: `flows/ecommerce-order-groups.yaml`
- **Groups Used**: `order-validation`, `inventory-management`, `order-notifications`
- **Description**: Complete e-commerce order processing using step groups and nested flows for payment processing and order creation

### 3. User Registration with Groups and Nested Flows
- **PHP Example**: `user-registration-groups.php`
- **YAML Flow**: `flows/user-registration-groups.yaml`
- **Groups Used**: `user-validation`, `user-setup`, `user-notifications`
- **Description**: Complete user registration process with password hashing in nested flows, profile creation, and role assignment

## Available Examples

### 1. User Registration Flow
- **Flow**: `flows/user-registration.yaml`
- **Groups**: `groups/user-validation.yaml`
- **Steps**: `steps/user-registration/`
- **Description**: Complete user registration process with validation, creation, and email verification

### 2. Order Processing Flow
- **Flow**: `flows/order-processing.yaml`
- **Groups**: `groups/order-processing.yaml`
- **Steps**: `steps/order-processing/`
- **Conditions**: `conditions/order-processing/`
- **Description**: E-commerce order processing with inventory checks, payment, and fulfillment

### 3. Content Moderation Flow
- **Flow**: `flows/content-moderation.yaml`
- **Steps**: `steps/content-moderation/`
- **Conditions**: `conditions/content-moderation/`
- **Description**: Automated content moderation with AI analysis and human review

### 4. Data Processing Pipeline
- **Flow**: `flows/data-processing.yaml`
- **Steps**: `steps/data-processing/`
- **Description**: ETL pipeline for data transformation and analysis

### 5. Newsletter Campaign Flow
- **Flow**: `flows/newsletter-campaign.yaml`
- **Steps**: `steps/newsletter/`
- **Conditions**: `conditions/newsletter/`
- **Description**: Newsletter campaign management with segmentation and scheduling

## Step Groups

The examples demonstrate three types of reusable step groups:

### User Validation Group (`groups/user-validation.yaml`)
- Email format validation
- Password strength checking
- Required field validation
- User existence checking
- Terms acceptance validation

### Order Processing Group (`groups/order-processing.yaml`)
- Order data validation
- Inventory checking and reservation
- Payment processing
- Order record creation
- Confirmation generation

### Notifications Group (`groups/notifications.yaml`)
- Welcome email sending
- Verification email dispatch
- SMS notifications
- Event logging
- Admin notifications

## Nested Flows Examples

The examples show nested flows used for:

1. **Payment Processing**: Isolated payment handling with error management
2. **Password Hashing**: Secure password processing with cleanup
3. **Profile Creation**: User profile setup with privacy settings
4. **Role Assignment**: Permission and quota management
5. **Order Creation**: Order record generation with metadata

## Usage

### 1. Copy Examples to Your Project

```bash
# Copy flow definitions
cp examples/flows/*.yaml flow_definitions/

# Copy group definitions
cp examples/groups/*.yaml flow_definitions/groups/

# Copy step classes
cp -r examples/steps/ app/Flowpipe/Steps/

# Copy condition classes
cp -r examples/conditions/ app/Flowpipe/Conditions/
```

### 2. Run PHP Examples

```bash
# Run e-commerce example
php examples/ecommerce-order-processing-groups.php

# Run user registration example
php examples/user-registration-groups.php
```

### 3. Install Required Dependencies

Some examples may require additional packages:

```bash
# For content moderation example
composer require openai-php/client

# For newsletter example
composer require mailgun/mailgun-php

# For data processing example
composer require league/csv
```

### 4. Configure Examples

Update the class namespaces in YAML files to match your project structure:

```yaml
# Before
steps:
  - type: closure
    action: validate_email

# After (if using custom steps)
steps:
  - type: step
    class: App\Flowpipe\Steps\ValidateEmailStep
```

### 5. Run Examples

```bash
# List available flows
php artisan flowpipe:list

# Run a specific flow
php artisan flowpipe:run user-registration

# Export flow to different formats
php artisan flowpipe:export user-registration --format=mermaid
```

## Key Benefits Demonstrated

### Step Groups
- **Reusability**: Groups can be used across multiple flows
- **Modularity**: Each group handles specific concerns
- **Maintainability**: Easy to update individual components
- **Testing**: Groups can be tested independently

### Nested Flows
- **Isolation**: Complex logic runs in its own context
- **Security**: Sensitive operations (like password hashing) are contained
- **Organization**: Complex workflows are broken into manageable pieces
- **Error Handling**: Errors in nested flows can be handled appropriately

## Customization

### Adapting to Your Needs

1. **Modify Group Definitions**: Update YAML files to match your business logic
2. **Extend Step Classes**: Add your specific business logic to step implementations
3. **Custom Conditions**: Create conditions that match your application's requirements
4. **Add Error Handling**: Implement proper error handling for your use cases

### Best Practices

1. **Start Simple**: Begin with basic groups and gradually add complexity
2. **Test Thoroughly**: Each example includes test patterns you can adapt
3. **Document Changes**: Keep track of modifications you make to examples
4. **Follow Conventions**: Maintain consistent naming and structure
5. **Use Nested Flows Wisely**: Don't overuse nested flows for simple operations

## Testing Examples

The examples include testing patterns:

```php
// Test a group
public function test_user_validation_group()
{
    $result = Flowpipe::make()
        ->send($testData)
        ->useGroup('user-validation')
        ->thenReturn();
        
    $this->assertTrue($result['validation_passed']);
}

// Test a nested flow
public function test_password_processing_nested_flow()
{
    $result = Flowpipe::make()
        ->send(['password' => 'test123'])
        ->nested([
            fn($data, $next) => $next(['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)]),
        ])
        ->thenReturn();
        
    $this->assertArrayHasKey('password_hash', $result);
}
```

## Contributing Examples

If you have a useful flow example to share:

1. Create a new example file with clear documentation
2. Include both PHP and YAML versions when applicable
3. Add comprehensive comments explaining the workflow
4. Include testing examples
5. Submit a pull request

## Support

For questions about these examples:

1. Check the main documentation in `docs/`
2. Review the comprehensive guide in `groups-and-nested-flows.md`
3. Review the test cases for implementation details
4. Open an issue on the GitHub repository
5. Join the community discussions
