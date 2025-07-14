<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Tracer;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Grazulex\LaravelFlowpipe\Support\Helpers;

final class BasicTracer implements Tracer
{
    public function trace(string $stepClass, mixed $payloadBefore, mixed $payloadAfter, ?float $durationMs = null): void
    {
        if (! config('flowpipe.tracing.enabled', false)) {
            return;
        }

        echo sprintf(
            "[TRACE] %s | Δ%.2fms\nPayload before: %s\nPayload after:  %s\n\n",
            Helpers::shortClassName($stepClass),
            $durationMs ?? 0,
            var_export($payloadBefore, true),
            var_export($payloadAfter, true),
        );
    }
}
