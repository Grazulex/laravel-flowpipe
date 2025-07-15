<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FlowpipeMakeFlowCommand extends Command
{
    protected $signature = 'flowpipe:make-flow {name : The name of the flow} {--description= : Description of the flow} {--template=basic : Template to use (basic|conditional|advanced)}';

    protected $description = 'Create a new flow definition YAML file';

    public function handle(): void
    {
        $name = $this->argument('name');
        $description = $this->option('description') ?? 'A new flow definition';
        $template = $this->option('template');

        if (! in_array($template, ['basic', 'conditional', 'advanced'])) {
            $this->error('Invalid template. Supported templates: basic, conditional, advanced');

            return;
        }

        $flowsPath = config('flowpipe.definitions_path', 'flow_definitions');
        $fileName = Str::snake($name).'.yaml';
        $filePath = $flowsPath.'/'.$fileName;

        if (File::exists($filePath)) {
            $this->error("Flow definition already exists: {$filePath}");

            return;
        }

        // Create directory if it doesn't exist
        if (! File::exists($flowsPath)) {
            File::makeDirectory($flowsPath, 0755, true);
            $this->info("Created directory: {$flowsPath}");
        }

        $content = $this->generateFlowContent($name, $description, $template);

        File::put($filePath, $content);

        $this->info("Flow definition created: <comment>{$filePath}</comment>");
        $this->line('');
        $this->line('You can now:');
        $this->line("  • Edit the flow: <comment>{$filePath}</comment>");
        $this->line("  • Run the flow: <comment>php artisan flowpipe:run {$fileName}</comment>");
        $this->line('  • List all flows: <comment>php artisan flowpipe:list</comment>');
    }

    private function generateFlowContent(string $name, string $description, string $template): string
    {
        $flowName = Str::studly($name);

        $stubPath = __DIR__."/stubs/flow-{$template}.stub";

        if (! file_exists($stubPath)) {
            throw new InvalidArgumentException("Template stub not found: {$template}");
        }

        $content = file_get_contents($stubPath);

        // Replace placeholders in the stub
        $content = str_replace(
            ['{{ flowName }}', '{{ description }}', '{{ date }}'],
            [$flowName, $description, date('Y-m-d H:i:s')],
            $content
        );

        return $content;
    }
}
