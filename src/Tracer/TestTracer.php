<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Tracer;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;

final class TestTracer implements Tracer
{
    /** @var array<int, array{step: string, before: mixed, after: mixed, duration: float|null}> */
    private array $logs = [];

    public function trace(string $stepClass, mixed $payloadBefore, mixed $payloadAfter, ?float $durationMs = null): void
    {
        $this->logs[] = [
            'step' => $stepClass,
            'before' => $payloadBefore,
            'after' => $payloadAfter,
            'duration' => $durationMs,
        ];
    }

    public function all(): array
    {
        return $this->logs;
    }

    public function steps(): array
    {
        return array_column($this->logs, 'step');
    }

    public function count(): int
    {
        return count($this->logs);
    }

    public function firstStep(): ?string
    {
        return $this->logs[0]['step'] ?? null;
    }

    public function lastStep(): ?string
    {
        return $this->logs[array_key_last($this->logs)]['step'] ?? null;
    }

    public function clear(): void
    {
        $this->logs = [];
    }
}
