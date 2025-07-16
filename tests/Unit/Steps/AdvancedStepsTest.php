<?php

declare(strict_types=1);

namespace Tests\Unit\Steps;

use Exception;
use Grazulex\LaravelFlowpipe\Flowpipe;
use Grazulex\LaravelFlowpipe\Steps\BatchStep;
use Grazulex\LaravelFlowpipe\Steps\CacheStep;
use Grazulex\LaravelFlowpipe\Steps\RetryStep;
use Grazulex\LaravelFlowpipe\Steps\TransformStep;
use Grazulex\LaravelFlowpipe\Steps\ValidationStep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdvancedStepsTest extends TestCase
{
    public function test_cache_step_caches_results(): void
    {
        Cache::shouldReceive('store')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('has')
            ->with('flowpipe.test-key.'.md5(serialize('test-data')))
            ->once()
            ->andReturn(false);

        Cache::shouldReceive('put')
            ->with('flowpipe.test-key.'.md5(serialize('test-data')), 'processed-test-data', 3600)
            ->once();

        $step = new CacheStep('test-key', 3600);
        $result = $step->handle('test-data', fn ($data) => 'processed-'.$data);

        $this->assertEquals('processed-test-data', $result);
    }

    public function test_retry_step_retries_on_failure(): void
    {
        $attempts = 0;
        $step = new RetryStep(3, 0); // 3 attempts, no delay

        $result = $step->handle('test', function ($data) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Failed');
            }

            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    public function test_transform_step_transforms_data(): void
    {
        $step = new TransformStep(fn ($data) => mb_strtoupper($data));
        $result = $step->handle('hello', fn ($data) => $data);

        $this->assertEquals('HELLO', $result);
    }

    public function test_transform_step_map_helper(): void
    {
        $step = TransformStep::map(fn ($item) => $item * 2);
        $result = $step->handle([1, 2, 3], fn ($data) => $data);

        $this->assertEquals([2, 4, 6], $result);
    }

    public function test_transform_step_filter_helper(): void
    {
        $step = TransformStep::filter(fn ($item) => $item > 2);
        $result = $step->handle([1, 2, 3, 4], fn ($data) => $data);

        $this->assertEquals([2 => 3, 3 => 4], $result);
    }

    public function test_validation_step_validates_data(): void
    {
        $step = new ValidationStep(['name' => 'required|string']);

        $result = $step->handle(['name' => 'John'], fn ($data) => $data);

        $this->assertEquals(['name' => 'John'], $result);
    }

    public function test_validation_step_throws_on_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        $step = new ValidationStep(['name' => 'required|string']);
        $step->handle(['name' => null], fn ($data) => $data);
    }

    public function test_batch_step_processes_in_batches(): void
    {
        $step = new BatchStep(2);
        $processed = [];

        $result = $step->handle([1, 2, 3, 4, 5], function ($batch) use (&$processed) {
            $processed[] = $batch;

            return array_map(fn ($x) => $x * 2, $batch);
        });

        $this->assertEquals([2, 4, 6, 8, 10], $result);
        $this->assertCount(3, $processed); // 3 batches
        $this->assertEquals([1, 2], $processed[0]);
        $this->assertEquals([3, 4], $processed[1]);
        $this->assertEquals([5], $processed[2]);
    }

    public function test_flowpipe_helper_methods(): void
    {
        $result = Flowpipe::make()
            ->send('hello')
            ->transform(fn ($data) => mb_strtoupper($data))
            ->validate(['data' => 'string'])
            ->thenReturn();

        $this->assertEquals('HELLO', $result);
    }

    public function test_flowpipe_static_factory_methods(): void
    {
        $debugPipe = Flowpipe::debug();
        $this->assertInstanceOf(Flowpipe::class, $debugPipe);

        $perfPipe = Flowpipe::performance();
        $this->assertInstanceOf(Flowpipe::class, $perfPipe);

        $testPipe = Flowpipe::test();
        $this->assertInstanceOf(Flowpipe::class, $testPipe);
    }
}
