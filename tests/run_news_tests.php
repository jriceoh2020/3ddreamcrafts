<?php
/**
 * News System Test Runner
 * Runs all news-related tests
 */

require_once __DIR__ . '/../includes/config.php';

echo "3DDreamCrafts News System Test Suite\n";
echo "====================================\n\n";

// Check if database exists
if (!file_exists(DB_PATH)) {
    echo "ERROR: Database not found at " . DB_PATH . "\n";
    echo "Please run the database initialization script first.\n";
    exit(1);
}

// Run news system tests
echo "Running News System Tests...\n";
require_once __DIR__ . '/NewsSystemTest.php';

try {
    $newsTest = new NewsSystemTest();
    $newsSuccess = $newsTest->runAllTests();
    
    echo "\n";
    
    if ($newsSuccess) {
        echo "✅ All news system tests passed!\n";
        exit(0);
    } else {
        echo "❌ Some news system tests failed.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Test execution failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>