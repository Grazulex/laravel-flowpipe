<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\DB;

final class AssignDefaultRoleStep implements FlowStep
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be an array');
        }

        $userId = $payload['id'] ?? null;

        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required to assign role');
        }

        try {
            // Get default role ID (typically 'user' role)
            $defaultRole = DB::table('roles')
                ->where('name', 'user')
                ->where('is_default', true)
                ->first();

            if (!$defaultRole) {
                throw new \RuntimeException('Default user role not found');
            }

            // Assign role to user
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $defaultRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payload['role_assigned'] = true;
            $payload['assigned_role'] = $defaultRole->name;

            return $next($payload);

        } catch (Exception $e) {
            throw new \RuntimeException('Failed to assign default role: ' . $e->getMessage());
        }
    }
}
