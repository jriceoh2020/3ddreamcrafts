<?php
/**
 * Admin Test Runner
 * Runs all admin-related tests
 */

require_once __DIR__ . '/../includes/config.php';

echo "=== Admin Dashboard and Content Management Test Suite ===\n\n";

$testFiles = [
    'AdminDashboardTest.php' => 'Admin Dashboard and Content Management Tests',
    'AdminIntegrationTest.php' => 'Admin Integration Tests'
];

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

foreach ($testFiles as $file => $description) {
    echo "Running $description...\n";
    echo str_repeat('-', 60) . "\n";
    
    $testPath = __DIR__ . '/' . $file;
    if (!file_exists($testPath)) {
        echo "❌ Test file not found: $file\n\n";
        $failedTests++;
        continue;
    }
    
    // Capture output
    ob_start();
    $exitCode = 0;
    
    try {
        include $testPath;
    } catch (Exception $e) {
        echo "❌ Test execution failed: " . $e->getMessage() . "\n";
        $exitCode = 1;
    }
    
    $output = ob_get_clean();
    echo $output;
    
    if ($exitCode === 0) {
        $passedTests++;
        echo "✅ $description completed successfully\n";
    } else {
        $failedTests++;
        echo "❌ $description failed\n";
    }
    
    echo "\n";
    $totalTests++;
}

echo str_repeat('=', 60) . "\n";
echo "Test Summary:\n";
echo "Total test suites: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";

if ($failedTests === 0) {
    echo "\n🎉 All admin tests passed!\n";
    exit(0);
} else {
    echo "\n💥 Some admin tests failed. Please check the output above.\n";
    exit(1);
}
?>