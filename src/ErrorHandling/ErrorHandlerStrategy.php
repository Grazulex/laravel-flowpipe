<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling;

use Throwable;

interface ErrorHandlerStrategy
{
    /**
     * Handle an error that occurred during flow execution.
     *
     * @param  Throwable  $error  The error that occurred
     * @param  mixed  $payload  The payload being processed
     * @param  int  $attemptNumber  The current attempt number (starting from 1)
     * @param  array  $context  Additional context about the error
     * @return ErrorHandlerResult The result of error handling
     */
    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult;
}
