<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Grazulex\LaravelFlowpipe\Support\Helpers;
use Grazulex\LaravelFlowpipe\Support\StepResolver;

final class Flowpipe
{
    private mixed $payload = null;

    /** @var array<FlowStep|Closure> */
    private array $steps = [];

    private FlowContext $context;

    private function __construct() {}

    public static function make(?Tracer $tracer = null): self
    {
        $instance = new self();
        $instance->context = new FlowContext($tracer);

        return $instance;
    }

    public function withTracer(?Tracer $tracer): self
    {
        $this->context = new FlowContext($tracer);

        return $this;
    }

    public function send(mixed $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * @param  array<FlowStep|Closure|string|class-string>  $steps
     */
    public function through(array $steps): self
    {
        $this->steps = array_map([StepResolver::class, 'resolve'], $steps);

        return $this;
    }

    public function thenReturn(): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->steps),
            function (Closure $next, FlowStep|Closure $step): Closure {
                return function (mixed $payload) use ($step, $next) {
                    $before = $payload;
                    $start = microtime(true);

                    $result = $step instanceof Closure
                        ? $step($payload, $next)
                        : $step->handle($payload, $next);

                    $duration = Helpers::durationMs($start);

                    $this->context->tracer()?->trace(
                        Helpers::shortClassName($step),
                        $before,
                        $result,
                        $duration
                    );

                    return $result;
                };
            },
            fn ($finalPayload) => $finalPayload
        );

        return $pipeline($this->payload);
    }

    public function context(): FlowContext
    {
        return $this->context;
    }
}
