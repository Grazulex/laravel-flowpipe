<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class BatchStep implements FlowStep
{
    public function __construct(
        private readonly int $batchSize = 100,
        private readonly bool $preserveKeys = false
    ) {}

    public static function make(int $batchSize = 100, bool $preserveKeys = false): self
    {
        return new self($batchSize, $preserveKeys);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        if (! is_array($payload)) {
            return $next($payload);
        }

        $batches = array_chunk($payload, $this->batchSize, $this->preserveKeys);
        $results = [];

        foreach ($batches as $batch) {
            $batchResult = $next($batch);

            if (is_array($batchResult)) {
                $results = array_merge($results, $batchResult);
            } else {
                $results[] = $batchResult;
            }
        }

        return $results;
    }
}
