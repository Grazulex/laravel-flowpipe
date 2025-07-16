<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Grazulex\LaravelFlowpipe\Registry\FlowDefinitionRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class FlowpipeExportCommand extends Command
{
    protected $signature = 'flowpipe:export {flow : The name of the flow to export} {--format=json : Export format (json|mermaid|md)} {--output= : Output file path (optional)}';

    protected $description = 'Export a flow definition to JSON, Mermaid, or Markdown format';

    public function handle(): void
    {
        $flowName = $this->argument('flow');
        $format = $this->option('format');
        $outputPath = $this->option('output');

        if (! in_array($format, ['json', 'mermaid', 'md'])) {
            $this->error('Invalid format. Supported formats: json, mermaid, md');

            return;
        }

        try {
            $registry = new FlowDefinitionRegistry();
            $definition = $registry->get($flowName);

            $this->info("Exporting flow: <comment>{$flowName}</comment> to <comment>{$format}</comment> format");

            $exportedContent = match ($format) {
                'json' => $this->exportToJson($definition),
                'mermaid' => $this->exportToMermaid($definition, $flowName),
                'md' => $this->exportToMarkdown($definition, $flowName),
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

    private function exportToMarkdown(array $definition, string $flowName): string
    {
        $markdown = "# Flow Documentation: {$flowName}\n\n";

        // Basic Information
        $markdown .= "## 📋 Basic Information\n\n";
        $markdown .= "- **Flow Name**: `{$flowName}`\n";
        $markdown .= '- **Description**: '.($definition['description'] ?? 'No description provided')."\n";
        $markdown .= '- **Generated**: '.date('Y-m-d H:i:s')."\n\n";

        // Initial Payload
        if (isset($definition['send'])) {
            $markdown .= "## 🚀 Initial Payload\n\n";
            $markdown .= "```json\n";
            $markdown .= json_encode($definition['send'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $markdown .= "\n```\n\n";
        }

        // Flow Statistics
        $markdown .= "## 📊 Flow Statistics\n\n";
        $stepCount = $this->countSteps($definition['steps'] ?? []);
        $stepTypes = $this->getStepTypes($definition['steps'] ?? []);
        $hasConditions = $this->hasConditions($definition['steps'] ?? []);

        $markdown .= "- **Total Steps**: {$stepCount}\n";
        $markdown .= '- **Step Types**: '.implode(', ', $stepTypes)."\n";
        $markdown .= '- **Contains Conditions**: '.($hasConditions ? 'Yes' : 'No')."\n";
        $markdown .= '- **Has Initial Payload**: '.(isset($definition['send']) ? 'Yes' : 'No')."\n\n";

        // Flow Visualization
        $markdown .= "## 🌊 Flow Visualization\n\n";
        $markdown .= "```mermaid\n";
        $markdown .= $this->exportToMermaid($definition, $flowName);
        $markdown .= "\n```\n\n";

        // Detailed Steps
        $markdown .= "## 🔧 Detailed Steps\n\n";
        if (isset($definition['steps']) && ! empty($definition['steps'])) {
            $markdown .= $this->generateStepDocumentation($definition['steps'], 1);
        } else {
            $markdown .= "_No steps defined._\n\n";
        }

        // YAML Source
        $markdown .= "## 📄 YAML Source\n\n";
        $markdown .= "```yaml\n";
        $markdown .= "# Original flow definition\n";
        $markdown .= "flow: {$flowName}\n";
        if (isset($definition['description'])) {
            $markdown .= 'description: '.$definition['description']."\n";
        }
        if (isset($definition['send'])) {
            $markdown .= 'send: '.json_encode($definition['send'])."\n";
        }
        $markdown .= "\nsteps:\n";
        $markdown .= $this->arrayToYaml($definition['steps'] ?? [], 1);
        $markdown .= "```\n\n";

        // Footer
        $markdown .= "---\n";

        return $markdown."_Generated by Laravel Flowpipe_\n";
    }

    private function generateStepDocumentation(array $steps, int $level): string
    {
        $markdown = '';
        $stepNumber = 1;

        foreach ($steps as $step) {
            $indent = str_repeat('  ', $level - 1);
            $markdown .= "{$indent}### Step {$stepNumber}\n\n";

            if (isset($step['condition'])) {
                $markdown .= "{$indent}**Type**: Conditional Step\n\n";
                $condition = is_array($step['condition'])
                    ? json_encode($step['condition'], JSON_PRETTY_PRINT)
                    : $step['condition'];
                $markdown .= "{$indent}**Condition**:\n```json\n{$condition}\n```\n\n";

                if (isset($step['then'])) {
                    $markdown .= "{$indent}**Then Branch**:\n";
                    $markdown .= $this->generateStepDocumentation($step['then'], $level + 1);
                }

                if (isset($step['else'])) {
                    $markdown .= "{$indent}**Else Branch**:\n";
                    $markdown .= $this->generateStepDocumentation($step['else'], $level + 1);
                }
            } elseif (isset($step['type'])) {
                $markdown .= "{$indent}**Type**: ".ucfirst($step['type'])." Step\n\n";
                $markdown .= "{$indent}**Action**: `".($step['action'] ?? 'process')."`\n\n";

                if (isset($step['value'])) {
                    $markdown .= "{$indent}**Value**: `".$step['value']."`\n\n";
                }
            } elseif (isset($step['step'])) {
                $markdown .= "{$indent}**Type**: Custom Step Class\n\n";
                $markdown .= "{$indent}**Class**: `".$step['step']."`\n\n";
            }

            $stepNumber++;
        }

        return $markdown;
    }

    private function arrayToYaml(array $array, int $indent = 0): string
    {
        $yaml = '';
        $indentStr = str_repeat('  ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (isset($value[0])) {
                    // Array list
                    $yaml .= "{$indentStr}- ";
                    if (is_array($value[0])) {
                        $yaml .= "\n";
                        foreach ($value as $item) {
                            $yaml .= $this->arrayToYaml([$item], $indent + 1);
                        }
                    } else {
                        $yaml .= $this->arrayToYaml($value, $indent + 1);
                    }
                } else {
                    // Associative array
                    $yaml .= "{$indentStr}{$key}:\n";
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } elseif (is_numeric($key)) {
                $yaml .= "{$indentStr}- {$value}\n";
            } else {
                $yaml .= "{$indentStr}{$key}: {$value}\n";
            }
        }

        return $yaml;
    }

    private function countSteps(array $steps): int
    {
        $count = 0;

        foreach ($steps as $step) {
            $count++;

            if (isset($step['then'])) {
                $count += $this->countSteps($step['then']);
            }
            if (isset($step['else'])) {
                $count += $this->countSteps($step['else']);
            }
        }

        return $count;
    }

    private function getStepTypes(array $steps): array
    {
        $types = [];

        foreach ($steps as $step) {
            if (isset($step['type'])) {
                $types[] = $step['type'];
            } elseif (isset($step['step'])) {
                $types[] = 'custom';
            } elseif (isset($step['condition'])) {
                $types[] = 'condition';

                if (isset($step['then'])) {
                    $types = array_merge($types, $this->getStepTypes($step['then']));
                }
                if (isset($step['else'])) {
                    $types = array_merge($types, $this->getStepTypes($step['else']));
                }
            }
        }

        return array_unique($types);
    }

    private function hasConditions(array $steps): bool
    {
        foreach ($steps as $step) {
            if (isset($step['condition'])) {
                return true;
            }

            if (isset($step['then']) && $this->hasConditions($step['then'])) {
                return true;
            }
            if (isset($step['else']) && $this->hasConditions($step['else'])) {
                return true;
            }
        }

        return false;
    }
}
