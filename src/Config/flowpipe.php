<?php

declare(strict_types=1);

return [
    'tracing' => [
        'enabled' => true,
        'default' => Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
