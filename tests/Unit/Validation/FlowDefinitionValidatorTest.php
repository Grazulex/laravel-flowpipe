<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Grazulex\LaravelFlowpipe\Validation\FlowDefinitionValidator;
use Grazulex\LaravelFlowpipe\Validation\ValidationResult;
use Tests\TestCase;

final class FlowDefinitionValidatorTest extends TestCase
{
    private FlowDefinitionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FlowDefinitionValidator();
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $tempDir = $this->tempPath();
        if (is_dir($tempDir)) {
            array_map('unlink', glob($tempDir.'/*'));
            rmdir($tempDir);
        }

        parent::tearDown();
    }

    public function test_validates_valid_flow_definition(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'description' => 'Test flow',
            'steps' => [
                [
                    'type' => 'closure',
                    'action' => 'test_action',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors);
    }

    public function test_detects_missing_flow_name(): void
    {
        $definition = [
            'steps' => [
                [
                    'type' => 'closure',
                    'action' => 'test_action',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Missing required field: 'flow'", $result->errors);
    }

    public function test_detects_missing_steps(): void
    {
        $definition = [
            'flow' => 'TestFlow',
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Missing or invalid 'steps' array", $result->errors);
    }

    public function test_validates_invalid_flow_name_format(): void
    {
        $definition = [
            'flow' => '123-invalid-name',
            'steps' => [],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Invalid flow name format: '123-invalid-name'", $result->errors);
    }

    public function test_validates_closure_step_missing_action(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'closure',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Closure step missing 'action' field", $result->errors);
    }

    public function test_validates_step_missing_class(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'step',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Step type missing 'class' field", $result->errors);
    }

    public function test_validates_unsupported_step_type(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'unsupported_type',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Unsupported step type 'unsupported_type'", $result->errors);
    }

    public function test_validates_condition_step(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'condition',
                    'condition' => [
                        'field' => 'active',
                        'operator' => 'equals',
                        'value' => true,
                    ],
                    'step' => [
                        'type' => 'closure',
                        'action' => 'process_active',
                    ],
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertTrue($result->isValid());
    }

    public function test_validates_condition_step_missing_fields(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'condition',
                    'condition' => [
                        'field' => 'active',
                        // Missing operator and value
                    ],
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Condition missing 'operator'", $result->errors);
        $this->assertContains("Step 1: Condition missing 'value'", $result->errors);
        $this->assertContains("Step 1: Condition step missing 'step' to execute", $result->errors);
    }

    public function test_validates_unsupported_operator(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'condition',
                    'condition' => [
                        'field' => 'active',
                        'operator' => 'unsupported_operator',
                        'value' => true,
                    ],
                    'step' => [
                        'type' => 'closure',
                        'action' => 'process_active',
                    ],
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Unsupported operator 'unsupported_operator'", $result->errors);
    }

    public function test_validates_nested_steps(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'nested',
                    'steps' => [
                        [
                            'type' => 'closure',
                            'action' => 'nested_action',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertTrue($result->isValid());
    }

    public function test_validates_nested_steps_missing_steps(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'nested',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Nested step missing 'steps' array", $result->errors);
    }

    public function test_validates_group_step_missing_name(): void
    {
        $definition = [
            'flow' => 'TestFlow',
            'steps' => [
                [
                    'type' => 'group',
                ],
            ],
        ];

        $result = $this->validator->validateFlowDefinition($definition);

        $this->assertFalse($result->isValid());
        $this->assertContains("Step 1: Group step missing 'name' field", $result->errors);
    }

    public function test_validates_yaml_syntax(): void
    {
        $validYaml = <<<'YAML'
flow: TestFlow
steps:
  - type: closure
    action: test_action
YAML;

        $invalidYaml = <<<'YAML'
flow: TestFlow
steps:
  - type: closure
    action: test_action
    invalid_yaml: [
YAML;

        $this->createTempFile($validYaml, 'valid.yaml');
        $this->createTempFile($invalidYaml, 'invalid.yaml');

        $validErrors = $this->validator->validateYamlSyntax($this->tempPath('valid.yaml'));
        $invalidErrors = $this->validator->validateYamlSyntax($this->tempPath('invalid.yaml'));

        $this->assertEmpty($validErrors);
        $this->assertNotEmpty($invalidErrors);
        $this->assertStringContainsString('YAML syntax error', $invalidErrors[0]);
    }

    private function createTempFile(string $content, string $filename): void
    {
        if (! is_dir($this->tempPath())) {
            mkdir($this->tempPath(), 0777, true);
        }

        file_put_contents($this->tempPath($filename), $content);
    }

    private function tempPath(string $filename = ''): string
    {
        return sys_get_temp_dir().'/flowpipe_test/'.$filename;
    }
}
