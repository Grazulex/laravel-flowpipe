<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Tracer;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Illuminate\Support\Facades\Log;

final class DebugTracer implements Tracer
{
    private array $traces = [];

    private bool $logToFile;

    private string $logChannel;

    public function __construct(bool $logToFile = false, string $logChannel = 'default')
    {
        $this->logToFile = $logToFile;
        $this->logChannel = $logChannel;
    }

    public function trace(string $stepName, mixed $before, mixed $result, ?float $durationMs = null): void
    {
        $trace = [
            'step' => $stepName,
            'timestamp' => now()->toISOString(),
            'before' => $this->formatPayload($before),
            'after' => $this->formatPayload($result),
            'duration_ms' => $durationMs ?? 0.0,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        $this->traces[] = $trace;

        if ($this->logToFile) {
            Log::channel($this->logChannel)->debug('Flowpipe Step Executed', $trace);
        }

        $this->outputToConsole($trace);
    }

    public function getTraces(): array
    {
        return $this->traces;
    }

    public function clear(): void
    {
        $this->traces = [];
    }

    public function getTotalDuration(): float
    {
        return array_sum(array_column($this->traces, 'duration_ms'));
    }

    public function getAverageDuration(): float
    {
        if ($this->traces === []) {
            return 0.0;
        }

        return $this->getTotalDuration() / count($this->traces);
    }

    public function getSlowestStep(): ?array
    {
        if ($this->traces === []) {
            return null;
        }

        return collect($this->traces)
            ->sortByDesc('duration_ms')
            ->first();
    }

    public function getStepStats(): array
    {
        $stats = [];

        foreach ($this->traces as $trace) {
            $stepName = $trace['step'];

            if (! isset($stats[$stepName])) {
                $stats[$stepName] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'max_duration' => 0,
                ];
            }

            $stats[$stepName]['count']++;
            $stats[$stepName]['total_duration'] += $trace['duration_ms'];
            $stats[$stepName]['min_duration'] = min($stats[$stepName]['min_duration'], $trace['duration_ms']);
            $stats[$stepName]['max_duration'] = max($stats[$stepName]['max_duration'], $trace['duration_ms']);
        }

        // Calculate averages
        foreach ($stats as &$stat) {
            $stat['avg_duration'] = $stat['total_duration'] / $stat['count'];
        }

        return $stats;
    }

    public function printSummary(): void
    {
        echo "\n🎯 Flowpipe Execution Summary:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo sprintf("Total Steps: %d\n", count($this->traces));
        echo sprintf("Total Duration: %.2fms\n", $this->getTotalDuration());
        echo sprintf("Average Duration: %.2fms\n", $this->getAverageDuration());

        $slowest = $this->getSlowestStep();
        if ($slowest !== null && $slowest !== []) {
            echo sprintf("Slowest Step: %s (%.2fms)\n", $slowest['step'], $slowest['duration_ms']);
        }

        echo "\n📊 Step Statistics:\n";
        foreach ($this->getStepStats() as $stepName => $stats) {
            echo sprintf(
                "  %s: %d calls, %.2fms avg (%.2fms-%.2fms)\n",
                $stepName,
                $stats['count'],
                $stats['avg_duration'],
                $stats['min_duration'],
                $stats['max_duration']
            );
        }
    }

    private function formatPayload(mixed $payload): string
    {
        if (is_scalar($payload)) {
            return (string) $payload;
        }

        if (is_array($payload)) {
            return 'Array('.count($payload).')';
        }

        if (is_object($payload)) {
            return get_class($payload);
        }

        return gettype($payload);
    }

    private function outputToConsole(array $trace): void
    {
        $memoryMB = round($trace['memory_usage'] / 1024 / 1024, 2);
        $peakMemoryMB = round($trace['memory_peak'] / 1024 / 1024, 2);

        echo sprintf(
            "🔍 [%s] %s | %s → %s | %.2fms | Mem: %.2fMB (Peak: %.2fMB)\n",
            $trace['timestamp'],
            $trace['step'],
            $trace['before'],
            $trace['after'],
            $trace['duration_ms'],
            $memoryMB,
            $peakMemoryMB
        );
    }
}
