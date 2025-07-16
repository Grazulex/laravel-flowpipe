<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateUserAccountStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        try {
            $userId = DB::table('users')->insertGetId([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => Hash::make($payload['password']),
                'email_verified_at' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userData = [
                'id' => $userId,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'created_at' => now(),
            ];

            return $next($userData);

        } catch (Exception $e) {
            throw new \RuntimeException('Failed to create user account: ' . $e->getMessage());
        }
    }
}
