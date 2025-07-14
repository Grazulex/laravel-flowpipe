<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Contracts;

use Closure;

interface FlowStep
{
    /**
     * Handle the step logic.
     */
    public function handle(mixed $payload, Closure $next): mixed;
}
