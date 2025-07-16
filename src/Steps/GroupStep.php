<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Support\FlowGroupRegistry;
use InvalidArgumentException;

/**
 * Step that executes a group of steps
 */
final class GroupStep implements FlowStep
{
    public function __construct(
        private string $groupName
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        $steps = FlowGroupRegistry::get($this->groupName);

        if ($steps === []) {
            throw new InvalidArgumentException("Group '{$this->groupName}' not found or is empty");
        }

        $result = $payload;

        foreach ($steps as $step) {
            $result = $step instanceof Closure ? $step($result, fn ($data) => $data) : $step->handle($result, fn ($data) => $data);
        }

        return $next($result);
    }
}
