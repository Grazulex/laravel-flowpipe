<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Steps;

use Closure;
use Grazulex\LaravelFlowpipe\Contracts\FlowStep;
use Illuminate\Support\Facades\Cache;

final class CacheStep implements FlowStep
{
    public function __construct(
        private readonly string $key,
        private readonly int $ttl = 3600,
        private readonly ?string $store = null
    ) {}

    public static function make(string $key, int $ttl = 3600, ?string $store = null): self
    {
        return new self($key, $ttl, $store);
    }

    public function handle(mixed $payload, Closure $next): mixed
    {
        $cache = $this->store !== null && $this->store !== '' && $this->store !== '0' ? Cache::store($this->store) : Cache::store();

        $cacheKey = $this->generateCacheKey($payload);

        // Try to get from cache
        if ($cache->has($cacheKey)) {
            return $cache->get($cacheKey);
        }

        // Process and cache the result
        $result = $next($payload);
        $cache->put($cacheKey, $result, $this->ttl);

        return $result;
    }

    private function generateCacheKey(mixed $payload): string
    {
        $payloadHash = md5(serialize($payload));

        return "flowpipe.{$this->key}.{$payloadHash}";
    }
}
