<?php
// Quick test: simulate a request through the kernel
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

// Create a fake request
$request = Illuminate\Http\Request::create('/dashboard', 'GET');

// Handle through the kernel (this includes middleware, auth session, etc.)
try {
    $response = $kernel->handle($request);
    $content = $response->getContent();

    file_put_contents('/tmp/dashboard_page.html', $content);
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Content length: " . strlen($content) . " bytes\n";

    // Check for key elements
    $checks = [
        'Chart.js CDN' => 'cdn.jsdelivr.net/npm/chart.js',
        'revenueChart canvas' => 'id="revenueChart"',
        'transactionChart canvas' => 'id="transactionChart"',
        'new Chart(' => 'new Chart(',
        'Dashboard heading' => 'Dashboard</h1>',
        'Selamat datang' => 'Selamat datang',
    ];
    foreach ($checks as $name => $needle) {
        echo "$name: " . (strpos($content, $needle) !== false ? "PRESENT" : "MISSING") . "\n";
    }

    // Check for error messages
    if (strpos($content, 'Whoops') !== false || strpos($content, 'Error') !== false || strpos($content, 'Exception') !== false) {
        echo "\nWARNING: Error content detected in response!\n";
        // Extract error section
        if (preg_match('/Whoops.*?(<\/div>|<\/main>|<\/section>)/s', $content, $errMatch)) {
            echo "Error: " . substr($errMatch[0], 0, 500) . "\n";
        }
    }

    // Check for @push('scripts') in output
    if (strpos($content, "@push('scripts')") !== false) {
        echo "\nWARNING: @push('scripts') directive not compiled!\n";
    }

    // Look for script tags with chart code
    if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $content, $matches)) {
        echo "\nScript blocks found: " . count($matches[1]) . "\n";
        foreach ($matches[1] as $i => $script) {
            if (strpos($script, 'Chart') !== false) {
                echo "  Script $i contains Chart.js code (" . strlen($script) . " chars)\n";
            }
            if (strpos($script, 'DOMContentLoaded') !== false) {
                echo "  Script $i contains DOMContentLoaded\n";
            }
        }
    }

} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}