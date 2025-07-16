<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Tracer;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Illuminate\Support\Facades\DB;

final class DatabaseTracer implements Tracer
{
    private string $tableName;

    private string $executionId;

    public function __construct(string $tableName = 'flowpipe_traces')
    {
        $this->tableName = $tableName;
        $this->executionId = uniqid('exec_', true);
    }

    public static function createTable(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('flowpipe_traces')) {
            DB::getSchemaBuilder()->create('flowpipe_traces', function ($table): void {
                $table->id();
                $table->string('execution_id');
                $table->string('step_name');
                $table->text('payload_before')->nullable();
                $table->text('payload_after')->nullable();
                $table->float('duration_ms');
                $table->bigInteger('memory_usage');
                $table->timestamp('executed_at');
                $table->timestamps();

                $table->index(['execution_id', 'executed_at']);
            });
        }
    }

    public static function cleanup(int $daysOld = 30): int
    {
        return DB::table('flowpipe_traces')
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    public function trace(string $stepName, mixed $before, mixed $result, ?float $durationMs = null): void
    {
        DB::table($this->tableName)->insert([
            'execution_id' => $this->executionId,
            'step_name' => $stepName,
            'payload_before' => $this->serializePayload($before),
            'payload_after' => $this->serializePayload($result),
            'duration_ms' => $durationMs ?? 0.0,
            'memory_usage' => memory_get_usage(true),
            'executed_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getExecutionTraces(): array
    {
        return DB::table($this->tableName)
            ->where('execution_id', $this->executionId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    private function serializePayload(mixed $payload): string
    {
        if (is_scalar($payload)) {
            return (string) $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
