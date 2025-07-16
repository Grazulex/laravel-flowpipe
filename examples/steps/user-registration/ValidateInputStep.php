<?php

declare(strict_types=1);

namespace Examples\Steps\UserRegistration;

use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\FlowContext;
use Illuminate\Support\Facades\Validator;

final class ValidateInputStep implements FlowStep
{
    public function handle(FlowContext $context): FlowContext
    {
        $userData = $context->get('user_data', []);

        $validator = Validator::make($userData, [
            'email' => 'required|email|max:255',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
            'name' => 'required|string|max:255',
            'terms_accepted' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            $context->addErrors($validator->errors()->all());
            $context->set('validation_passed', false);

            return $context;
        }

        $context->set('validation_passed', true);
        $context->set('validated_data', $validator->validated());

        return $context;
    }
}
