<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Tracer;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;

final class PerformanceTracer implements Tracer
{
    private array $metrics = [];

    private float $startTime;

    private int $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public function trace(string $stepName, mixed $before, mixed $result, ?float $durationMs = null): void
    {
        $this->metrics[] = [
            'step' => $stepName,
            'duration_ms' => $durationMs ?? 0.0,
            'memory_before' => memory_get_usage(true),
            'memory_after' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true) - $this->startTime,
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getTotalExecutionTime(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    public function getTotalMemoryUsed(): int
    {
        return memory_get_usage(true) - $this->startMemory;
    }

    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    public function getBottlenecks(int $count = 5): array
    {
        return collect($this->metrics)
            ->sortByDesc('duration_ms')
            ->take($count)
            ->values()
            ->all();
    }

    public function getMemoryHogs(int $count = 5): array
    {
        return collect($this->metrics)
            ->sortByDesc('memory_peak')
            ->take($count)
            ->values()
            ->all();
    }

    public function hasPerformanceIssues(): bool
    {
        // Check if total execution time is over 1 second
        if ($this->getTotalExecutionTime() > 1000) {
            return true;
        }

        // Check if any single step takes over 500ms
        foreach ($this->metrics as $metric) {
            if ($metric['duration_ms'] > 500) {
                return true;
            }
        }

        // Check if memory usage is over 50MB
        return $this->getPeakMemoryUsage() > 50 * 1024 * 1024;
    }

    public function getPerformanceReport(): array
    {
        return [
            'total_execution_time_ms' => $this->getTotalExecutionTime(),
            'total_memory_used_bytes' => $this->getTotalMemoryUsed(),
            'peak_memory_usage_bytes' => $this->getPeakMemoryUsage(),
            'step_count' => count($this->metrics),
            'bottlenecks' => $this->getBottlenecks(),
            'memory_hogs' => $this->getMemoryHogs(),
            'has_performance_issues' => $this->hasPerformanceIssues(),
        ];
    }
}
