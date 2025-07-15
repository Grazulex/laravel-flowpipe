<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class FlowpipeExportCommand extends Command
{
    protected $signature = 'flowpipe:export {flow : The name of the flow to export} {--format=json : Export format (json|mermaid)} {--output= : Output file path (optional)}';

    protected $description = 'Export a flow definition to JSON or Mermaid format';

    public function handle(): void
    {
        $flowName = $this->argument('flow');
        $format = $this->option('format');
        $outputPath = $this->option('output');

        if (! in_array($format, ['json', 'mermaid'])) {
            $this->error('Invalid format. Supported formats: json, mermaid');

            return;
        }

        try {
            $registry = new FlowDefinitionRegistry();
            $definition = $registry->get($flowName);

            $this->info("Exporting flow: <comment>{$flowName}</comment> to <comment>{$format}</comment> format");

            $exportedContent = match ($format) {
                'json' => $this->exportToJson($definition),
                'mermaid' => $this->exportToMermaid($definition, $flowName),
                default => throw new RuntimeException("Unsupported format: {$format}")
            };

            if ($outputPath) {
                // Save to file
                $directory = dirname($outputPath);
                if (! File::exists($directory)) {
                    File::makeDirectory($directory, 0755, true);
                }

                File::put($outputPath, $exportedContent);
                $this->info("Exported to: <comment>{$outputPath}</comment>");
            } else {
                // Output to console
                $this->line('');
                $this->line($exportedContent);
            }

        } catch (RuntimeException $e) {
            $this->error("Error: {$e->getMessage()}");
        }
    }

    private function exportToJson(array $definition): string
    {
        return json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function exportToMermaid(array $definition, string $flowName): string
    {
        $mermaid = "flowchart TD\n";
        $mermaid .= "    Start([Start: {$flowName}])\n";

        $previousNode = 'Start';

        if (isset($definition['steps'])) {
            $stepCounter = 1;

            foreach ($definition['steps'] as $step) {
                $currentNode = "Step{$stepCounter}";
                $stepCounter++;

                if (isset($step['condition'])) {
                    // Conditional step
                    $condition = is_array($step['condition'])
                        ? json_encode($step['condition'])
                        : $step['condition'];

                    $mermaid .= "    {$currentNode}{{\"Condition: {$condition}\"}}\n";
                    $mermaid .= "    {$previousNode} --> {$currentNode}\n";

                    if (isset($step['then'])) {
                        $thenNode = "Then{$stepCounter}";
                        $stepCounter++;
                        $mermaid .= "    {$thenNode}[\"Then: Execute steps\"]\n";
                        $mermaid .= "    {$currentNode} -->|Yes| {$thenNode}\n";
                        $previousNode = $thenNode;
                    }

                    if (isset($step['else'])) {
                        $elseNode = "Else{$stepCounter}";
                        $stepCounter++;
                        $mermaid .= "    {$elseNode}[\"Else: Execute steps\"]\n";
                        $mermaid .= "    {$currentNode} -->|No| {$elseNode}\n";
                    }
                } elseif (isset($step['type'])) {
                    // Regular step
                    $stepType = $step['type'];
                    $action = $step['action'] ?? 'process';

                    $mermaid .= "    {$currentNode}[\"Type: {$stepType}\\nAction: {$action}\"]\n";
                    $mermaid .= "    {$previousNode} --> {$currentNode}\n";
                    $previousNode = $currentNode;
                } elseif (isset($step['step'])) {
                    // Class step
                    $stepClass = basename(str_replace('\\', '/', $step['step']));

                    $mermaid .= "    {$currentNode}[\"Class: {$stepClass}\"]\n";
                    $mermaid .= "    {$previousNode} --> {$currentNode}\n";
                    $previousNode = $currentNode;
                }
            }
        }

        $mermaid .= "    End([End])\n";

        return $mermaid."    {$previousNode} --> End\n";
    }
}
