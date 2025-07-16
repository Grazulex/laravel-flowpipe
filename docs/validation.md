# Invalid Flow Example

This example demonstrates common validation errors that the `flowpipe:validate` command can detect.

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
