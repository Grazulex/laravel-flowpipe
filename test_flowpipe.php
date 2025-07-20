<?php

declare(strict_types=1);

require_once 'vendor/autoload.php';

use Grazulex\LaravelFlowpipe\Flowpipe;

try {
    // Test basic functionality
    $result = Flowpipe::make()
        ->send('Hello World')
        ->through([
            fn($data, $next) => $next(strtoupper($data)),
            fn($data, $next) => $next(str_replace(' ', '-', $data)),
            fn($data, $next) => $next($data . '!'),
        ])
        ->thenReturn();

    echo "Basic test result: " . $result . PHP_EOL;

    // Test group functionality
    Flowpipe::group('text-processing', [
        fn($data, $next) => $next(trim($data)),
        fn($data, $next) => $next(strtoupper($data)),
        fn($data, $next) => $next(str_replace(' ', '-', $data)),
    ]);

    $groupResult = Flowpipe::make()
        ->send('  hello world  ')
        ->useGroup('text-processing')
        ->through([
            fn($data, $next) => $next($data . '!'),
        ])
        ->thenReturn();

    echo "Group test result: " . $groupResult . PHP_EOL;

    // Test nested flows
    $nestedResult = Flowpipe::make()
        ->send('hello world')
        ->nested([
            fn($data, $next) => $next(strtoupper($data)),
            fn($data, $next) => $next(str_replace(' ', '-', $data)),
        ])
        ->through([
            fn($data, $next) => $next($data . '!'),
        ])
        ->thenReturn();

    echo "Nested flow test result: " . $nestedResult . PHP_EOL;

    echo "All tests passed!" . PHP_EOL;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    exit(1);
}