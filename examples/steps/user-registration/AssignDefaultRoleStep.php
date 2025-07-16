<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\DB;

final class AssignDefaultRoleStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userId = $context->get('user_id');

        if (! $userId) {
            $context->addError('User ID is required to assign role');

            return $context;
        }

        try {
            // Get default role ID (typically 'user' role)
            $defaultRole = DB::table('roles')
                ->where('name', 'user')
                ->where('is_default', true)
                ->first();

            if (! $defaultRole) {
                $context->addError('Default user role not found');

                return $context;
            }

            // Assign role to user
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $defaultRole->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $context->set('role_assigned', true);
            $context->set('assigned_role', $defaultRole->name);

        } catch (Exception $e) {
            $context->addError('Failed to assign default role: '.$e->getMessage());
            $context->set('role_assigned', false);
        }

        return $context;
    }
}
