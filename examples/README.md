# Examples

This directory contains practical examples of using Laravel Flowpipe in real-world scenarios.

## Directory Structure

```
examples/
├── flows/           # Example YAML flow definitions
├── steps/           # Example step implementations
├── conditions/      # Example condition implementations
└── README.md        # This file
```

## Available Examples

### 1. User Registration Flow
- **Flow**: `flows/user-registration.yaml`
- **Steps**: `steps/user-registration/`
- **Description**: Complete user registration process with validation, creation, and email verification

### 2. Order Processing Flow
- **Flow**: `flows/order-processing.yaml`
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

## Usage

### 1. Copy Examples to Your Project

```bash
# Copy flow definitions
cp examples/flows/*.yaml flow_definitions/

# Copy step classes
cp -r examples/steps/ app/Flowpipe/Steps/

# Copy condition classes
cp -r examples/conditions/ app/Flowpipe/Conditions/
```

### 2. Install Required Dependencies

Some examples may require additional packages:

```bash
# For content moderation example
composer require openai-php/client

# For newsletter example
composer require mailgun/mailgun-php

# For data processing example
composer require league/csv
```

### 3. Configure Examples

Update the class namespaces in YAML files to match your project structure:

```yaml
# Before
steps:
  - name: validate-input
    class: Examples\Steps\ValidateInputStep

# After  
steps:
  - name: validate-input
    class: App\Flowpipe\Steps\ValidateInputStep
```

### 4. Run Examples

```bash
# List available flows
php artisan flowpipe:list

# Run a specific flow
php artisan tinker
>>> $context = new \Grazulex\LaravelFlowpipe\FlowContext(['user_data' => ['email' => 'user@example.com']]);
>>> $result = \Grazulex\LaravelFlowpipe\Flowpipe::run('user-registration', $context);
```

## Customization

### Adapting to Your Needs

1. **Modify Flow Definitions**: Update YAML files to match your business logic
2. **Extend Step Classes**: Add your specific business logic to step implementations
3. **Custom Conditions**: Create conditions that match your application's requirements
4. **Add Error Handling**: Implement proper error handling for your use cases

### Best Practices

1. **Start Simple**: Begin with basic flows and gradually add complexity
2. **Test Thoroughly**: Each example includes test cases you can adapt
3. **Document Changes**: Keep track of modifications you make to examples
4. **Follow Conventions**: Maintain consistent naming and structure

## Contributing Examples

If you have a useful flow example to share:

1. Create a new directory under the appropriate category
2. Include all necessary files (YAML, PHP classes, tests)
3. Add documentation explaining the use case
4. Submit a pull request

## Support

For questions about these examples:

1. Check the main documentation in `docs/`
2. Review the test cases for implementation details
3. Open an issue on the GitHub repository
4. Join the community discussions
