# Flow Validation Guide

Laravel Flowpipe provides comprehensive validation for YAML flow definitions to ensure they are correctly structured and all references are valid.

## Overview

The `flowpipe:validate` command analyzes your flow definitions and provides detailed feedback on:

- **Structure validation**: Ensures all required fields are present
- **Step type validation**: Validates that only supported step types are used
- **Reference validation**: Checks that groups and step classes exist
- **Condition validation**: Validates condition operators and structure
- **YAML syntax validation**: Catches YAML formatting errors

## Command Usage

### Basic Validation

```bash
# Validate all flows
php artisan flowpipe:validate --all

# Validate specific flow
php artisan flowpipe:validate --path=user-registration.yaml
```

### Output Formats

```bash
# Table format (default)
php artisan flowpipe:validate --all --format=table

# JSON format (for CI/CD integration)
php artisan flowpipe:validate --all --format=json
```

### Custom Path

```bash
# Validate flows in custom directory
php artisan flowpipe:validate --all --path=custom/flows
```

## Validation Rules

### Required Fields

Every flow definition must have:
- `flow`: The flow name
- `steps`: An array of steps to execute

### Supported Step Types

- `closure`: Execute a closure action
- `step`: Execute a step class
- `condition`: Execute conditional logic
- `group`: Execute a predefined group
- `nested`: Execute nested flow steps

### Supported Condition Operators

- `equals`: Exact match
- `contains`: Contains substring
- `greater_than`: Numeric comparison
- `less_than`: Numeric comparison
- `in`: Value in array

### Flow Name Requirements

- Must start with a letter or underscore
- Can contain letters, numbers, underscores, and hyphens
- Must be unique within the definitions directory

## Common Validation Errors

### 1. Missing Required Fields

**Error**: `Missing required field: 'flow'`
**Cause**: YAML file doesn't have a `flow` field
**Fix**: Add `flow: YourFlowName` to the YAML file

### 2. Invalid Step Types

**Error**: `Unsupported step type 'custom_step'`
**Cause**: Using a step type that's not supported
**Fix**: Use one of: `closure`, `step`, `condition`, `group`, `nested`

### 3. Missing Step Fields

**Error**: `Closure step missing 'action' field`
**Cause**: `closure` step without `action` field
**Fix**: Add `action: your_action_name` to the closure step

### 4. Invalid Operators

**Error**: `Unsupported operator 'not_equal'`
**Cause**: Using an unsupported condition operator
**Fix**: Use one of: `equals`, `contains`, `greater_than`, `less_than`, `in`

### 5. Missing References

**Error**: `Group 'user_validation' not found`
**Cause**: Referencing a group that doesn't exist
**Fix**: Define the group or check the group name

### 6. YAML Syntax Errors

**Error**: `YAML syntax error: found character that cannot start any token`
**Cause**: Invalid YAML syntax
**Fix**: Check YAML formatting, indentation, and special characters

## Example Valid Flow

```yaml
flow: UserRegistrationFlow
description: Process new user registration
send:
  name: "John Doe"
  email: "john@example.com"
  password: "securepass123"

steps:
  - type: group
    name: user-validation
    
  - type: condition
    condition:
      field: email
      operator: contains
      value: "@"
    step:
      type: closure
      action: send_welcome_email
      
  - type: nested
    steps:
      - type: closure
        action: hash_password
      - type: closure
        action: create_user_record
        
  - type: group
    name: user-notifications
```

## Invalid Flow Examples

This section demonstrates common validation errors that the `flowpipe:validate` command can detect.

## Invalid YAML Structure

```yaml
flow: InvalidStructureFlow
description: This flow has multiple structural issues
# Missing required steps field
send:
  data: "test"
```

## Invalid Step Types

```yaml
flow: InvalidStepTypesFlow
description: This flow has invalid step types
steps:
  - type: unsupported_step_type
    action: some_action
  - type: closure
    # Missing required action field
  - type: step
    # Missing required class field
```

## Invalid Condition Steps

```yaml
flow: InvalidConditionFlow
description: This flow has invalid condition steps
steps:
  - type: condition
    condition:
      field: active
      operator: unsupported_operator
      value: true
    # Missing step to execute
  - type: condition
    condition:
      # Missing required fields
    step:
      type: closure
      action: process_data
```

## Invalid Nested Flows

```yaml
flow: InvalidNestedFlow
description: This flow has invalid nested structure
steps:
  - type: nested
    # Missing steps array
  - type: nested
    steps:
      - type: closure
        # Missing action field
      - type: unsupported_type
        action: nested_action
```

## Invalid Group References

```yaml
flow: InvalidGroupFlow
description: This flow references non-existent groups
steps:
  - type: group
    name: non_existent_group
  - type: group
    # Missing name field
```

## Invalid Flow Names

```yaml
flow: 123-invalid-flow-name
description: Flow names must start with a letter or underscore
steps:
  - type: closure
    action: test_action
```

## Running Validation

To check for these errors, run:

```bash
# Validate specific flow
php artisan flowpipe:validate InvalidStructureFlow

# Validate all flows and see detailed errors
php artisan flowpipe:validate --all

# Get JSON output for programmatic processing
php artisan flowpipe:validate --all --format=json
```

## Common Validation Errors

1. **Missing required fields**: `flow`, `steps`, `action`, `class`, `name`
2. **Invalid step types**: Only `closure`, `step`, `condition`, `group`, `nested` are supported
3. **Invalid operators**: Only `equals`, `contains`, `greater_than`, `less_than`, `in` are supported
4. **Invalid flow names**: Must start with letter/underscore, contain only alphanumeric, underscore, hyphen
5. **Missing references**: Groups or step classes that don't exist
6. **YAML syntax errors**: Invalid YAML format

## Best Practices

1. **Always validate** before deploying flow definitions
2. **Use descriptive names** for flows and steps
3. **Test references** ensure groups and classes exist
4. **Follow naming conventions** for flow names
5. **Structure nested flows** properly with required fields

## CI/CD Integration

### Exit Codes

The validation command returns appropriate exit codes:
- `0`: All flows are valid
- `1`: One or more flows have errors

### GitHub Actions Example

```yaml
name: Validate Flow Definitions

on: [push, pull_request]

jobs:
  validate-flows:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
    
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
    
    - name: Validate flow definitions
      run: php artisan flowpipe:validate --all --format=json
```

### Shell Script Example

```bash
#!/bin/bash
# validate-flows.sh

echo "Validating flow definitions..."

if php artisan flowpipe:validate --all; then
    echo "✅ All flows are valid"
    exit 0
else
    echo "❌ Some flows have errors"
    exit 1
fi
```

### JSON Output for Processing

```json
{
  "valid": false,
  "flows": [
    {
      "name": "UserProcessingFlow",
      "valid": true,
      "errors": [],
      "warnings": [],
      "error_count": 0,
      "warning_count": 0
    },
    {
      "name": "InvalidFlow",
      "valid": false,
      "errors": [
        "Step 1: Unsupported step type 'invalid_type'",
        "Step 2: Closure step missing 'action' field"
      ],
      "warnings": [],
      "error_count": 2,
      "warning_count": 0
    }
  ],
  "summary": {
    "total": 2,
    "valid": 1,
    "invalid": 1,
    "errors": 2,
    "warnings": 0
  }
}
```
