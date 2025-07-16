<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Validation;

final class ValidationResult
{
    public function __construct(
        public readonly string $flowName,
        public readonly array $errors,
        public readonly array $warnings
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function toArray(): array
    {
        return [
            'flow' => $this->flowName,
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
