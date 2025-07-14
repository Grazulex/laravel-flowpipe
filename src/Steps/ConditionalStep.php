<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\Condition;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class ConditionalStep implements FlowStep
{
    public function __construct(
        private Condition $condition,
        private FlowStep $step,
        private bool $negate = false
    ) {}

    public static function when(Condition $condition, FlowStep $step): self
    {
        return new self($condition, $step);
    }

    public static function unless(Condition $condition, FlowStep $step): self
    {
        return new self($condition, $step, true);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $shouldRun = $this->condition->evaluate($payload);

        if ($this->negate) {
            $shouldRun = ! $shouldRun;
        }

        return $shouldRun
            ? $this->step->handle($payload, $next)
            : $next($payload);
    }
}
