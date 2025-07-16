<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ValidationStep implements FlowStep
{
    public function __construct(
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $customAttributes = []
    ) {}

    public static function make(array $rules, array $messages = [], array $customAttributes = []): self
    {
        return new self($rules, $messages, $customAttributes);
    }

    public static function required(array $fields): self
    {
        $rules = array_fill_keys($fields, 'required');

        return new self($rules);
    }

    public static function email(string $field = 'email'): self
    {
        return new self([$field => 'required|email']);
    }

    public static function numeric(string $field, ?int $min = null, ?int $max = null): self
    {
        $rule = 'required|numeric';

        if ($min !== null) {
            $rule .= "|min:{$min}";
        }

        if ($max !== null) {
            $rule .= "|max:{$max}";
        }

        return new self([$field => $rule]);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        // If payload is not an array, try to wrap it according to rules
        $data = $payload;
        if (! is_array($payload)) {
            // If only one rule, use its key
            if (count($this->rules) === 1) {
                $key = array_key_first($this->rules);
                $data = [$key => $payload];
            } else {
                // Otherwise, fail fast (or could throw a more explicit exception)
                throw new InvalidArgumentException('ValidationStep expects array payload for multiple rules.');
            }
        }
        $validator = Validator::make($data, $this->rules, $this->messages, $this->customAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        // If we wrapped a scalar, unwrap it after validation
        if (! is_array($payload) && count($this->rules) === 1) {
            $key = array_key_first($this->rules);

            return $next($validated[$key]);
        }

        return $next($validated);
    }
}
