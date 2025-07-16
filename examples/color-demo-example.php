<?php

/**
 * Enhanced Color Demonstration Example
 * 
 * This example demonstrates the new enhanced Mermaid export with color coding
 * for different step types in Laravel Flowpipe.
 */

use Grazulex\LaravelFlowpipe\Flowpipe;

// Define groups for color demonstration
Flowpipe::group('validation-group', [
    fn($data, $next) => $next(array_merge($data, ['email_validated' => true])),
    fn($data, $next) => $next(array_merge($data, ['name_validated' => true])),
]);

Flowpipe::group('processing-group', [
    fn($data, $next) => $next(array_merge($data, ['processed' => true])),
    fn($data, $next) => $next(array_merge($data, ['timestamp' => now()])),
]);

// Create a comprehensive flow that demonstrates all color types
$result = Flowpipe::make()
    ->send(['name' => 'John Doe', 'email' => 'john@example.com'])
    
    // Blue group - User validation
    ->useGroup('validation-group')
    
    // Pink transform - Data transformation
    ->transform(fn($data) => array_merge($data, ['name' => strtoupper($data['name'])]))
    
    // Green validation - Laravel validation
    ->validate([
        'name' => 'required|string|min:2',
        'email' => 'required|email'
    ])
    
    // Yellow cache - Cache the validated data
    ->cache('user-data-' . md5(serialize(['name' => 'John Doe', 'email' => 'john@example.com'])), 3600)
    
    // Light green nested flow - Complex password processing
    ->nested([
        fn($data, $next) => $next(array_merge($data, ['password_hash' => password_hash('defaultpassword', PASSWORD_DEFAULT)])),
        fn($data, $next) => $next(array_merge($data, ['password_verified' => true])),
        fn($data, $next) => $next(array_merge($data, ['security_level' => 'high'])),
    ])
    
    // Blue group - Additional processing
    ->useGroup('processing-group')
    
    // Purple batch - Batch process the data
    ->batch(100, true)
    
    // Red retry - Retry logic for reliability
    ->retry(3, 100)
    
    // Final processing
    ->through([
        fn($data, $next) => $next(array_merge($data, ['completed' => true])),
    ])
    
    ->thenReturn();

// Display results
echo "=== Enhanced Color Demo Results ===\n";
echo "User data processed successfully!\n";
echo "Name: " . $result['name'] . "\n";
echo "Email: " . $result['email'] . "\n";
echo "Email Validated: " . ($result['email_validated'] ? 'Yes' : 'No') . "\n";
echo "Name Validated: " . ($result['name_validated'] ? 'Yes' : 'No') . "\n";
echo "Password Hash: " . substr($result['password_hash'], 0, 20) . "...\n";
echo "Security Level: " . $result['security_level'] . "\n";
echo "Processed: " . ($result['processed'] ? 'Yes' : 'No') . "\n";
echo "Completed: " . ($result['completed'] ? 'Yes' : 'No') . "\n";

echo "\n=== Export Commands ===\n";
echo "To see the enhanced color-coded Mermaid diagram, run:\n";
echo "php artisan flowpipe:export color-demo --format=mermaid\n";
echo "php artisan flowpipe:export validation-group --type=group --format=mermaid\n";
echo "php artisan flowpipe:export processing-group --type=group --format=mermaid\n";

echo "\n=== Color Legend ===\n";
echo "📦 Blue: Groups (validation-group, processing-group)\n";
echo "🔄 Pink: Transform steps\n";
echo "✅ Green: Validation steps\n";
echo "💾 Yellow: Cache steps\n";
echo "🔄 Light Green: Nested flows\n";
echo "📊 Purple: Batch steps\n";
echo "🔄 Red: Retry steps\n";

echo "\n=== Example Mermaid Output ===\n";
echo "The generated Mermaid diagram will show:\n";
echo "- Blue boxes for groups\n";
echo "- Pink box for transform step\n";
echo "- Green box for validation step\n";
echo "- Yellow box for cache step\n";
echo "- Light green box for nested flow\n";
echo "- Purple box for batch step\n";
echo "- Red box for retry step\n";
echo "- All connected with arrows showing the flow\n";