<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Validator;

final class ValidateInputStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        $validator = Validator::make($payload, [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'name' => 'required|string|max:255',
            'terms_accepted' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validator->errors()->all()));
        }

        return $next($validator->validated());
    }
}
