# YAML Validation Command Usage Examples

This document shows how to use the `flowpipe:validate` command to validate YAML flow definitions.

## Basic Usage

### Validate All Flows

```bash
php artisan flowpipe:validate --all
```

Sample output:
```
Validating all flow definitions...

┌─────────────────────┬─────────┬────────┬──────────┐
│ Flow                │ Status  │ Errors │ Warnings │
├─────────────────────┼─────────┼────────┼──────────┤
│ UserProcessingFlow  │ ✅ Valid │ 0      │ 0        │
│ PaymentFlow         │ ✅ Valid │ 0      │ 1        │
│ InvalidFlow         │ ❌ Invalid │ 3      │ 0        │
└─────────────────────┴─────────┴────────┴──────────┘

Errors in 'InvalidFlow':
  - Step 1: Unsupported step type 'invalid_type'
  - Step 2: Closure step missing 'action' field
  - Step 3: Group 'non_existent_group' not found

Summary:
  Total flows: 3
  Valid flows: 2
  Invalid flows: 1
  Total errors: 3
  Total warnings: 1
```

### Validate Single Flow

```bash
php artisan flowpipe:validate UserProcessingFlow
```

Sample output:
```
Validating flow: UserProcessingFlow
✅ Flow 'UserProcessingFlow' is valid
```

### Validate with Errors

```bash
php artisan flowpipe:validate InvalidFlow
```

Sample output:
```
Validating flow: InvalidFlow
❌ Flow 'InvalidFlow' has errors:
  - Step 1: Unsupported step type 'invalid_type'
  - Step 2: Closure step missing 'action' field
  - Step 3: Group 'non_existent_group' not found
```

## Advanced Usage

### JSON Output

```bash
php artisan flowpipe:validate --all --format=json
```

Sample output:
```json
{
  "summary": {
    "total_flows": 3,
    "valid_flows": 2,
    "invalid_flows": 1,
    "total_errors": 3,
    "total_warnings": 1
  },
  "results": [
    {
      "flow": "UserProcessingFlow",
      "valid": true,
      "errors": [],
      "warnings": []
    },
    {
      "flow": "PaymentFlow",
      "valid": true,
      "errors": [],
      "warnings": [
        "Step 3: Consider using more specific error handling"
      ]
    },
    {
      "flow": "InvalidFlow",
      "valid": false,
      "errors": [
        "Step 1: Unsupported step type 'invalid_type'",
        "Step 2: Closure step missing 'action' field",
        "Step 3: Group 'non_existent_group' not found"
      ],
      "warnings": []
    }
  ]
}
```

### Custom Path

```bash
php artisan flowpipe:validate --all --path=custom/flow/definitions
```

## Common Validation Errors

### 1. Missing Required Fields

**Error**: Missing required field: 'flow'
**Cause**: YAML file doesn't have a `flow` field
**Fix**: Add `flow: YourFlowName` to the YAML file

### 2. Invalid Step Types

**Error**: Unsupported step type 'custom_step'
**Cause**: Using a step type that's not supported
**Fix**: Use one of: `closure`, `step`, `condition`, `group`, `nested`

### 3. Missing Step Fields

**Error**: Closure step missing 'action' field
**Cause**: `closure` step without `action` field
**Fix**: Add `action: your_action_name` to the closure step

### 4. Invalid Operators

**Error**: Unsupported operator 'not_equal'
**Cause**: Using an unsupported condition operator
**Fix**: Use one of: `equals`, `contains`, `greater_than`, `less_than`, `in`

### 5. Missing References

**Error**: Group 'user_validation' not found
**Cause**: Referencing a group that doesn't exist
**Fix**: Define the group or check the group name

### 6. YAML Syntax Errors

**Error**: YAML syntax error: found character that cannot start any token
**Cause**: Invalid YAML syntax
**Fix**: Check YAML formatting, indentation, and special characters

## Integration with CI/CD

### Exit Codes

- `0`: All flows are valid
- `1`: One or more flows have errors

### Usage in CI Pipeline

```yaml
# .github/workflows/validate-flows.yml
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

### Usage in Scripts

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

## Best Practices

1. **Always validate before deployment**
2. **Use meaningful flow names** (start with letter/underscore)
3. **Test group references** before using them
4. **Keep YAML syntax clean** with proper indentation
5. **Use descriptive error messages** in custom steps
6. **Validate in CI/CD pipelines** to catch errors early

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

This flow would validate successfully because:
- It has a valid flow name
- All step types are supported
- Required fields are present
- Condition operator is valid
- Nested structure is proper
