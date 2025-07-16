<?php

declare(strict_types=1);

namespace Tests\Fixtures\Classes\Groups;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\ClosureStep;

/**
 * Fixture for testing step groups and nested flows
 */
final class GroupsFixture
{
    public static function setupGroups(): void
    {
        // Define validation group
        Flowpipe::group('data-validation', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'validated' => true,
                'validation_timestamp' => microtime(true),
            ])),
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'sanitized' => true,
            ])),
        ]);

        // Define processing group
        Flowpipe::group('data-processing', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'processed' => true,
                'processing_timestamp' => microtime(true),
            ])),
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'transformed' => true,
            ])),
        ]);

        // Define notification group
        Flowpipe::group('notifications', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'email_sent' => true,
            ])),
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'slack_notified' => true,
            ])),
        ]);
    }

    public static function basicGroupFlow(): array
    {
        self::setupGroups();

        $result = Flowpipe::make()
            ->send(['user_id' => 123, 'action' => 'create'])
            ->through([
                'data-validation',
                'data-processing',
                'notifications',
            ])
            ->thenReturn();

        return $result;
    }

    public static function nestedFlowExample(): array
    {
        $result = Flowpipe::make()
            ->send(['data' => 'initial'])
            ->nested([
                ClosureStep::make(fn ($payload, $next) => $next([
                    ...$payload,
                    'nested_step_1' => true,
                ])),
                ClosureStep::make(fn ($payload, $next) => $next([
                    ...$payload,
                    'nested_step_2' => true,
                ])),
            ])
            ->through([
                ClosureStep::make(fn ($payload, $next) => $next([
                    ...$payload,
                    'main_step' => true,
                ])),
            ])
            ->thenReturn();

        return $result;
    }

    public static function combinedGroupsAndNesting(): array
    {
        self::setupGroups();

        $result = Flowpipe::make()
            ->send(['order_id' => 456])
            ->useGroup('data-validation')
            ->nested([
                ClosureStep::make(fn ($payload, $next) => $next([
                    ...$payload,
                    'payment_processed' => true,
                ])),
                ClosureStep::make(fn ($payload, $next) => $next([
                    ...$payload,
                    'inventory_updated' => true,
                ])),
            ])
            ->useGroup('notifications')
            ->thenReturn();

        return $result;
    }

    public static function complexNestedGroupFlow(): array
    {
        // Define sub-groups
        Flowpipe::group('input-validation', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'input_validated' => true,
            ])),
        ]);

        Flowpipe::group('business-logic', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'business_rules_applied' => true,
            ])),
        ]);

        Flowpipe::group('output-formatting', [
            ClosureStep::make(fn ($payload, $next) => $next([
                ...$payload,
                'output_formatted' => true,
            ])),
        ]);

        $result = Flowpipe::make()
            ->send(['request_id' => 789])
            ->useGroup('input-validation')
            ->nested([
                // Nested flow with its own groups
                ClosureStep::make(function ($payload, $next) {
                    $nestedResult = Flowpipe::make()
                        ->send($payload)
                        ->useGroup('business-logic')
                        ->through([
                            ClosureStep::make(fn ($p, $n) => $n([
                                ...$p,
                                'nested_processing' => true,
                            ])),
                        ])
                        ->thenReturn();

                    return $next($nestedResult);
                }),
            ])
            ->useGroup('output-formatting')
            ->thenReturn();

        return $result;
    }
}

// Test step for groups
final class TestGroupStep implements FlowStep
{
    public function __construct(
        private string $identifier
    ) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        return $next([
            ...$payload,
            'steps_executed' => [...($payload['steps_executed'] ?? []), $this->identifier],
        ]);
    }
}

// Advanced fixture with custom steps
final class AdvancedGroupsFixture
{
    public static function setupAdvancedGroups(): void
    {
        Flowpipe::group('advanced-validation', [
            new TestGroupStep('validation-1'),
            new TestGroupStep('validation-2'),
        ]);

        Flowpipe::group('advanced-processing', [
            new TestGroupStep('processing-1'),
            new TestGroupStep('processing-2'),
        ]);
    }

    public static function advancedGroupFlow(): array
    {
        self::setupAdvancedGroups();

        $result = Flowpipe::make()
            ->send(['steps_executed' => []])
            ->through([
                'advanced-validation',
                'advanced-processing',
            ])
            ->thenReturn();

        return $result;
    }
}
