<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe;

use Grazulex\LaravelFlowpipe\Contracts\Tracer;
use Illuminate\Support\Str;

final class FlowContext
{
    private string $flowId;

    /** @var array<string, mixed> */
    private array $tags = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?Tracer $tracer = null;

    public function __construct(?Tracer $tracer = null)
    {
        $this->flowId = Str::uuid()->toString();
        $this->tracer = $tracer;
    }

    public function id(): string
    {
        return $this->flowId;
    }

    public function tracer(): ?Tracer
    {
        return $this->tracer;
    }

    public function tag(string $key, mixed $value): void
    {
        $this->tags[$key] = $value;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function meta(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->metadata;
    }
}
