<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;

/**
 * Step that creates and executes a nested flow
 */
final class NestedFlowStep implements FlowStep
{
    /** @var array<FlowStep|Closure|string> */
    private array $steps;

    /**
     * @param  array<FlowStep|Closure|string>  $steps
     */
    public function __construct(array $steps)
    {
        $this->steps = $steps;
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $result = Flowpipe::make()
            ->send($payload)
            ->through($this->steps)
            ->thenReturn();

        return $next($result);
    }
}
