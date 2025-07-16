<?php

declare(strict_types=1);

use Grazulex\LaravelFlowpipe\ErrorHandling\ErrorHandlerAction;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompensationStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\CompositeStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\FallbackStrategy;
use Grazulex\LaravelFlowpipe\ErrorHandling\Strategies\RetryStrategy;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ErrorHandlerStep;

describe('ErrorHandlerStep', function () {
    it('can handle retry strategy', function () {
        $attempts = 0;
        $strategy = RetryStrategy::make(3, 0); // 3 attempts, no delay
        $step = ErrorHandlerStep::make($strategy);

        $result = $step->handle('test', function ($data) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Failed');
            }

            return 'success';
        });

        expect($result)->toBe('success');
        expect($attempts)->toBe(3);
    });

    it('can handle fallback strategy', function () {
        $strategy = FallbackStrategy::withDefault('fallback_value');
        $step = ErrorHandlerStep::make($strategy);

        $result = $step->handle('test', function ($data) {
            throw new Exception('Always fails');
        });

        expect($result)->toBe('fallback_value');
    });

    it('can handle compensation strategy', function () {
        $compensationCalled = false;
        $strategy = CompensationStrategy::make(function ($payload, $error) use (&$compensationCalled) {
            $compensationCalled = true;

            return 'compensated';
        });
        $step = ErrorHandlerStep::make($strategy);

        $result = $step->handle('test', function ($data) {
            throw new Exception('Always fails');
        });

        expect($result)->toBe('compensated');
        expect($compensationCalled)->toBeTrue();
    });

    it('can handle composite strategy', function () {
        $strategy = CompositeStrategy::make()
            ->retry(RetryStrategy::make(2, 0))
            ->fallback(FallbackStrategy::withDefault('fallback'));

        $step = ErrorHandlerStep::make($strategy);

        $result = $step->handle('test', function ($data) {
            throw new Exception('Always fails');
        });

        expect($result)->toBe('fallback');
    });
});

describe('RetryStrategy', function () {
    it('can create with exponential backoff', function () {
        $strategy = RetryStrategy::exponentialBackoff(3, 100, 2.0);

        $result = $strategy->handle(new Exception('test'), 'payload', 2);

        expect($result->action)->toBe(ErrorHandlerAction::RETRY);
        expect($result->delayMs)->toBe(200); // 100 * 2^1
    });

    it('can create with linear backoff', function () {
        $strategy = RetryStrategy::linearBackoff(3, 100, 50);

        $result = $strategy->handle(new Exception('test'), 'payload', 2);

        expect($result->action)->toBe(ErrorHandlerAction::RETRY);
        expect($result->delayMs)->toBe(150); // 100 + 50 * 1
    });

    it('can filter by exception type', function () {
        $strategy = RetryStrategy::forException(RuntimeException::class);

        $result1 = $strategy->handle(new RuntimeException('test'), 'payload', 1);
        $result2 = $strategy->handle(new InvalidArgumentException('test'), 'payload', 1);

        expect($result1->action)->toBe(ErrorHandlerAction::RETRY);
        expect($result2->action)->toBe(ErrorHandlerAction::FAIL);
    });

    it('stops retrying after max attempts', function () {
        $strategy = RetryStrategy::make(3, 0);

        $result = $strategy->handle(new Exception('test'), 'payload', 3);

        expect($result->action)->toBe(ErrorHandlerAction::FAIL);
    });
});

describe('FallbackStrategy', function () {
    it('can create with default value', function () {
        $strategy = FallbackStrategy::withDefault('default');

        $result = $strategy->handle(new Exception('test'), 'payload', 1);

        expect($result->action)->toBe(ErrorHandlerAction::FALLBACK);
        expect($result->payload)->toBe('default');
    });

    it('can create with transform function', function () {
        $strategy = FallbackStrategy::withTransform(fn ($payload, $error) => $payload.'_transformed');

        $result = $strategy->handle(new Exception('test'), 'original', 1);

        expect($result->action)->toBe(ErrorHandlerAction::FALLBACK);
        expect($result->payload)->toBe('original_transformed');
    });

    it('can filter by exception type', function () {
        $strategy = FallbackStrategy::forException(RuntimeException::class, fn () => 'fallback');

        $result1 = $strategy->handle(new RuntimeException('test'), 'payload', 1);
        $result2 = $strategy->handle(new InvalidArgumentException('test'), 'payload', 1);

        expect($result1->action)->toBe(ErrorHandlerAction::FALLBACK);
        expect($result2->action)->toBe(ErrorHandlerAction::FAIL);
    });
});

describe('CompensationStrategy', function () {
    it('can create compensation handler', function () {
        $strategy = CompensationStrategy::make(fn ($payload, $error) => 'compensated');

        $result = $strategy->handle(new Exception('test'), 'payload', 1);

        expect($result->action)->toBe(ErrorHandlerAction::COMPENSATE);
        expect($result->payload)->toBe('compensated');
    });

    it('can filter by exception type', function () {
        $strategy = CompensationStrategy::forException(RuntimeException::class, fn () => 'compensated');

        $result1 = $strategy->handle(new RuntimeException('test'), 'payload', 1);
        $result2 = $strategy->handle(new InvalidArgumentException('test'), 'payload', 1);

        expect($result1->action)->toBe(ErrorHandlerAction::COMPENSATE);
        expect($result2->action)->toBe(ErrorHandlerAction::FAIL);
    });
});

describe('Flowpipe Error Handling Integration', function () {
    it('can use exponential backoff', function () {
        $attempts = 0;

        $result = Flowpipe::make()
            ->send('test')
            ->exponentialBackoff(3, 0) // No delay for testing
            ->through([
                function ($data, $next) use (&$attempts) {
                    $attempts++;
                    if ($attempts < 3) {
                        throw new Exception('Failed');
                    }

                    return $next('success');
                },
            ])
            ->thenReturn();

        expect($result)->toBe('success');
        expect($attempts)->toBe(3);
    });

    it('can use fallback', function () {
        $result = Flowpipe::make()
            ->send('test')
            ->withFallback(fn ($payload, $error) => 'fallback')
            ->through([
                function ($data, $next) {
                    throw new Exception('Always fails');
                },
            ])
            ->thenReturn();

        expect($result)->toBe('fallback');
    });

    it('can use compensation', function () {
        $result = Flowpipe::make()
            ->send('test')
            ->withCompensation(fn ($payload, $error) => 'compensated')
            ->through([
                function ($data, $next) {
                    throw new Exception('Always fails');
                },
            ])
            ->thenReturn();

        expect($result)->toBe('compensated');
    });

    it('can use composite error handler', function () {
        $composite = CompositeStrategy::make()
            ->retry(RetryStrategy::make(2, 0))
            ->fallback(FallbackStrategy::withDefault('fallback'));

        $result = Flowpipe::make()
            ->send('test')
            ->withErrorHandler($composite)
            ->through([
                function ($data, $next) {
                    throw new Exception('Always fails');
                },
            ])
            ->thenReturn();

        expect($result)->toBe('fallback');
    });
});
