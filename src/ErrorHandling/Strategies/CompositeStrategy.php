<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\ErrorHandling\Strategies;

use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerAction;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerResult;
use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerStrategy;
use Throwable;

final class CompositeStrategy implements ErrorHandlerStrategy
{
    /** @var ErrorHandlerStrategy[] */
    private array $strategies = [];

    public function __construct(array $strategies = [])
    {
        $this->strategies = $strategies;
    }

    public static function make(array $strategies = []): self
    {
        return new self($strategies);
    }

    public function addStrategy(ErrorHandlerStrategy $strategy): self
    {
        $this->strategies[] = $strategy;

        return $this;
    }

    public function retry(RetryStrategy $strategy): self
    {
        return $this->addStrategy($strategy);
    }

    public function fallback(FallbackStrategy $strategy): self
    {
        return $this->addStrategy($strategy);
    }

    public function compensate(CompensationStrategy $strategy): self
    {
        return $this->addStrategy($strategy);
    }

    public function handle(Throwable $error, mixed $payload, int $attemptNumber, array $context = []): ErrorHandlerResult
    {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->handle($error, $payload, $attemptNumber, $context);

            // If strategy wants to handle the error (not fail), use it
            if ($result->action !== ErrorHandlerAction::FAIL) {
                return $result;
            }
        }

        // No strategy handled the error, fail
        return ErrorHandlerResult::fail($error, $context);
    }
}
