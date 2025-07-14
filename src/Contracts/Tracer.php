<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Contracts;

interface Tracer
{
    /**
     * Trace a step execution.
     */
    public function trace(
        string $stepClass,
        mixed $payloadBefore,
        mixed $payloadAfter,
        ?float $durationMs = null
    ): void;
}
