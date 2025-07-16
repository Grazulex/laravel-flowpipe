<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Exception;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateUserAccountStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $validatedData = $context->get('validated_data', []);

        try {
            $userId = DB::table('users')->insertGetId([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'email_verified_at' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $context->set('user_id', $userId);
            $context->set('user_created', true);
            $context->set('user_data', [
                'id' => $userId,
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
            ]);

        } catch (Exception $e) {
            $context->addError('Failed to create user account: '.$e->getMessage());
            $context->set('user_created', false);
        }

        return $context;
    }
}
