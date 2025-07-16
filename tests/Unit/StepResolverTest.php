<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\Support\StepResolver;

describe('StepResolver', function () {
    it('resolves namespace intelligently', function () {
        // Mock config function
        config()->set('flowpipe.step_namespace', 'App\\Flowpipe\\Steps');

        // Test with reflection to access private method
        $reflection = new ReflectionClass(StepResolver::class);
        $method = $reflection->getMethod('resolveClassName');
        $method->setAccessible(true);

        // Test cases
        $testCases = [
            // Relative namespace (no backslash) - should be prefixed
            'SimpleStep' => 'App\\Flowpipe\\Steps\\SimpleStep',

            // Full namespace (contains backslash) - should be used as-is
            'UserRegistration\\ValidateInputStep' => 'UserRegistration\\ValidateInputStep',
            'Examples\\Steps\\UserRegistration\\ValidateInputStep' => 'Examples\\Steps\\UserRegistration\\ValidateInputStep',
            'My\\Custom\\Namespace\\MyStep' => 'My\\Custom\\Namespace\\MyStep',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke(null, $input);
            expect($result)->toBe($expected, "Failed for input: $input");
        }
    });
});
