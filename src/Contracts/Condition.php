<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Contracts;

interface Condition
{
    /**
     * Evaluate the condition.
     */
    public function evaluate(mixed $payload): bool;
}
