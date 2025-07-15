<?php

declare(strict_types=1);

namespace Grazulex\LaravelFlowpipe\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

final class FlowpipeMakeStepCommand extends GeneratorCommand
{
    protected $name = 'flowpipe:make-step';

    protected $description = 'Create a new Flowpipe Step class';

    protected $type = 'Step';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/step.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Flowpipe\\Steps';
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the Step class'],
        ];
    }
}
