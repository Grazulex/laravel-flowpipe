<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;

final class ClosureStep implements FlowStep
{
    public function __construct(
        private Closure $closure
    ) {}

    public static function make(Closure $closure): self
    {
        return new self($closure);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        return ($this->closure)($payload, $next);
    }
}
