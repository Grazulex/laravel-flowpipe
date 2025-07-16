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
        if (! File::exists($this->path)) {
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

    public function loadGroups(): void
    {
        $groupsPath = $this->path.'/groups';

        if (! File::exists($groupsPath)) {
            return;
        }

        collect(File::files($groupsPath))
            ->filter(fn ($f): bool => str_ends_with($f->getFilename(), '.yaml'))
            ->each(function ($file): void {
                $name = $file->getFilenameWithoutExtension();
                $definition = Yaml::parseFile($file->getPathname());

                if (isset($definition['group']) && isset($definition['steps'])) {
                    // Convert step definitions to actual steps using FlowBuilder
                    $builder = new \Grazulex\LaravelFlowpipe\Support\FlowBuilder();
                    $steps = [];

                    foreach ($definition['steps'] as $stepDef) {
                        $steps[] = $builder->buildStep($stepDef);
                    }

                    \Grazulex\LaravelFlowpipe\Support\FlowGroupRegistry::register(
                        $definition['group'],
                        $steps
                    );
                }
            });
    }

    public function listGroups(): Collection
    {
        $groupsPath = $this->path.'/groups';

        if (! File::exists($groupsPath)) {
            return collect();
        }

        return collect(File::files($groupsPath))
            ->filter(fn ($f): bool => str_ends_with($f->getFilename(), '.yaml'))
            ->map(fn ($f): string => $f->getFilenameWithoutExtension());
    }

    public function getGroup(string $name): array
    {
        $file = $this->path.'/groups/'.$name.'.yaml';

        if (! file_exists($file)) {
            throw new RuntimeException("Group definition [$name] not found.");
        }

        return Yaml::parseFile($file);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
