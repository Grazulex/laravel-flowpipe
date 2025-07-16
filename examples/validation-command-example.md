# Validation Command Example

This example demonstrates how to use the `flowpipe:validate` command to validate your flow definitions.

## Example Flow Files

### Valid Flow (`examples/flows/validation-demo.yaml`)
```yaml
flow: ValidationDemo
description: Example flow showing all validation features
send:
  name: "Demo User"
  email: "demo@example.com"
  age: 25
  status: "active"

steps:
  # Group step example
  - type: group
    name: user-validation
    
  # Conditional step example
  - type: condition
    condition:
      field: status
      operator: equals
      value: "active"
    step:
      type: closure
      action: process_active_user
      
  # Nested flow example
  - type: nested
    steps:
      - type: closure
        action: log_user_activity
      - type: closure
        action: update_last_seen
        
  # Action step example (legacy alias for step)
  - type: action
    class: App\Flowpipe\Steps\SendNotificationStep
    
  # Complex conditional example
  - type: condition
    condition:
      field: age
      operator: greater_than
      value: 18
    step:
      type: group
      name: adult-user-processing
    else:
      - type: closure
        action: handle_minor_user
```

### Invalid Flow (`examples/flows/invalid-validation-demo.yaml`)
```yaml
flow: InvalidValidationDemo
description: Example flow showing common validation errors
# Missing required 'steps' field

send:
  name: "Demo User"
  email: "demo@example.com"

# This should have steps array but it's missing
invalid_field: "This field is not recognized"
```

## Running Validation

### 1. Validate All Flows
```bash
$ php artisan flowpipe:validate --all

┌─────────────────────────┬─────────┬────────┬──────────┐
│ Flow                    │ Status  │ Errors │ Warnings │
├─────────────────────────┼─────────┼────────┼──────────┤
│ ValidationDemo          │ ✅ Valid │ 0      │ 0        │
│ InvalidValidationDemo   │ ❌ Invalid │ 1      │ 0        │
└─────────────────────────┴─────────┴────────┴──────────┘

Errors in 'InvalidValidationDemo':
  - Missing required field: 'steps'

Validation failed
```

### 2. Validate Specific Flow
```bash
$ php artisan flowpipe:validate --path=validation-demo.yaml

✅ Flow 'ValidationDemo' is valid
```

### 3. Validate with JSON Output
```bash
$ php artisan flowpipe:validate --all --format=json

{
  "valid": false,
  "flows": [
    {
      "name": "ValidationDemo",
      "valid": true,
      "errors": [],
      "warnings": [],
      "error_count": 0,
      "warning_count": 0
    },
    {
      "name": "InvalidValidationDemo",
      "valid": false,
      "errors": [
        "Missing required field: 'steps'"
      ],
      "warnings": [],
      "error_count": 1,
      "warning_count": 0
    }
  ],
  "summary": {
    "total": 2,
    "valid": 1,
    "invalid": 1,
    "errors": 1,
    "warnings": 0
  }
}
```

## Common Validation Scenarios

### 1. Development Workflow
```bash
# Create a new flow
php artisan flowpipe:make-flow NewUserFlow

# Edit the flow definition
# ... make changes to flow_definitions/new_user_flow.yaml ...

# Validate the flow
php artisan flowpipe:validate --path=new_user_flow.yaml

# If valid, test the flow
php artisan flowpipe:run NewUserFlow
```

### 2. CI/CD Integration
```bash
# In your CI pipeline
php artisan flowpipe:validate --all --format=json > validation-results.json

# Check exit code
if [ $? -eq 0 ]; then
    echo "All flows are valid"
else
    echo "Some flows have errors"
    exit 1
fi
```

### 3. Pre-deployment Validation
```bash
# Validate all flows before deployment
php artisan flowpipe:validate --all

# Export documentation for valid flows
php artisan flowpipe:list | tail -n +3 | while read flow; do
    if php artisan flowpipe:validate --path="$flow.yaml" > /dev/null 2>&1; then
        php artisan flowpipe:export "$flow" --format=md --output="docs/flows/$flow.md"
    fi
done
```

## Best Practices

1. **Always validate before deployment**: Include validation in your CI/CD pipeline
2. **Use descriptive error messages**: The validator provides detailed error descriptions
3. **Fix errors systematically**: Address structural issues before reference issues
4. **Test with JSON output**: Use JSON format for automated processing
5. **Document validation results**: Keep records of validation outcomes

## Validation Rules Summary

- **Required fields**: `flow`, `steps`
- **Step types**: `action`, `closure`, `step`, `condition`, `group`, `nested`
- **Condition operators**: `equals`, `contains`, `greater_than`, `less_than`, `in`
- **Flow names**: Must start with letter/underscore, contain only alphanumeric, underscore, hyphen
- **References**: Groups and step classes must exist
- **YAML syntax**: Must be valid YAML format

## Integration with Other Commands

The validation command works seamlessly with other Flowpipe commands:

```bash
# Generate, validate, and run
php artisan flowpipe:make-flow TestFlow
php artisan flowpipe:validate --path=test_flow.yaml
php artisan flowpipe:run TestFlow

# Export only valid flows
php artisan flowpipe:validate --all --format=json | jq -r '.flows[] | select(.valid == true) | .name' | while read flow; do
    php artisan flowpipe:export "$flow" --format=mermaid
done
```