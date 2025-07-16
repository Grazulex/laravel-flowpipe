<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Registry;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class FlowDefinitionRegistry
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path(config('flowpipe.definitions_path', 'flow_definitions'));
    }

    public function list(): Collection
    {
        if (!File::exists($this->path)) {
            return collect();
        }
        
        return collect(File::files($this->path))
            ->filter(fn ($f): bool => str_ends_with($f->getFilename(), '.yaml'))
            ->map(fn ($f): string => $f->getFilenameWithoutExtension());
    }

    public function get(string $name): array
    {
        $file = $this->path.'/'.$name.'.yaml';

        if (! file_exists($file)) {
            throw new RuntimeException("Flow definition [$name] not found.");
        }

        return Yaml::parseFile($file);
    }
}
