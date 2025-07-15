<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Flow Definition Path
    |--------------------------------------------------------------------------
    |
    | This is where your YAML flow definitions live.
    | Relative to the base_path() of the Laravel app.
    |
    */

    'definitions_path' => 'flow_definitions',
    /*
    |--------------------------------------------------------------------------
    | Default Step Namespace
    |--------------------------------------------------------------------------
    |
    | This namespace will be prepended to step class names when resolving
    | strings like 'MyStep' into 'App\\Flowpipe\\Steps\\MyStep'.
    |
    */

    'step_namespace' => 'App\\Flowpipe\\Steps',

    /*
    |--------------------------------------------------------------------------
    | Tracing Configuration
    |--------------------------------------------------------------------------
    |
    | This section configures the tracing functionality of Flowpipe.
    | You can enable or disable tracing and set the default tracer class.
    | */

    'tracing' => [
        'enabled' => true,
        'default' => Grazulex\LaravelFlowpipe\Tracer\BasicTracer::class,
    ],
];
