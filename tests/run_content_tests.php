<?php
/**
 * Content Management Test Runner
 * Runs all tests for content management functionality
 */

require_once __DIR__ . '/ContentManagerTest.php';
require_once __DIR__ . '/ValidationTest.php';

echo "=== Content Management Test Suite ===\n\n";

$allPassed = true;

// Run ContentManager tests
echo "1. Running ContentManager Tests\n";
echo str_repeat("-", 40) . "\n";
$contentTest = new ContentManagerTest();
$contentPassed = $contentTest->runAllTests();
$allPassed = $allPassed && $contentPassed;

echo "\n";

// Run Validation tests
echo "2. Running Validation Tests\n";
echo str_repeat("-", 40) . "\n";
$validationTest = new ValidationTest();
$validationPassed = $validationTest->runAllTests();
$allPassed = $allPassed && $validationPassed;

echo "\n";
echo str_repeat("=", 50) . "\n";

if ($allPassed) {
    echo "🎉 ALL CONTENT MANAGEMENT TESTS PASSED! 🎉\n";
    echo "Content management foundation is ready for use.\n";
} else {
    echo "❌ SOME TESTS FAILED\n";
    echo "Please review the test output above for details.\n";
}

echo str_repeat("=", 50) . "\n";

exit($allPassed ? 0 : 1);
?>