<?php
/**
 * Test Runner for Core Database and Utility Classes
 * Executes all unit tests for Task 2 implementation
 */

echo "=== 3DDreamCrafts Core Classes Test Suite ===\n";
echo "Testing DatabaseManager, ConfigManager, and Upload System implementations\n";
echo "========================================================\n\n";

// Include test classes
require_once __DIR__ . '/DatabaseManagerTest.php';
require_once __DIR__ . '/ConfigManagerTest.php';
require_once __DIR__ . '/UploadBasicTest.php';
require_once __DIR__ . '/UploadSecTest.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Run a test class and track results
 */
function runTestClass($testClassName, &$totalTests, &$passedTests, &$failedTests) {
    echo "Running {$testClassName}...\n";
    echo str_repeat("-", 50) . "\n";
    
    try {
        $test = new $testClassName();
        $test->runAllTests();
        $passedTests++;
        echo "\n";
    } catch (Exception $e) {
        $failedTests++;
        echo "\n❌ {$testClassName} failed: " . $e->getMessage() . "\n\n";
    }
    
    $totalTests++;
}

// Run all test classes
runTestClass('DatabaseManagerTest', $totalTests, $passedTests, $failedTests);
runTestClass('ConfigManagerTest', $totalTests, $passedTests, $failedTests);
runTestClass('UploadBasicTest', $totalTests, $passedTests, $failedTests);
runTestClass('UploadSecurityTest', $totalTests, $passedTests, $failedTests);

// Display final results
echo "========================================================\n";
echo "Test Results Summary:\n";
echo "Total Test Classes: {$totalTests}\n";
echo "Passed: {$passedTests}\n";
echo "Failed: {$failedTests}\n";

if ($failedTests === 0) {
    echo "\n🎉 All tests passed! Core database, utility, and upload system classes are working correctly.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please review the output above.\n";
    exit(1);
}