<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CheckEmailUniquenessStep implements FlowStep
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Payload must be an array');
        }

        $email = $payload['email'] ?? null;

        if (! $email) {
            throw new InvalidArgumentException('Email is required for uniqueness check');
        }

        $emailExists = DB::table('users')
            ->where('email', $email)
            ->exists();

        if ($emailExists) {
            throw new InvalidArgumentException('This email address is already registered');
        }

        return $next($payload);
    }
}
