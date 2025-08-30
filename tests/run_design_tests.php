<?php
/**
 * Design System Test Runner
 * Runs comprehensive tests for the design customization system
 */

require_once __DIR__ . '/DesignSystemTest.php';

echo "=== 3DDreamCrafts Design System Test Suite ===\n";
echo "Testing design customization, CSS generation, and backup system\n";
echo "========================================================\n\n";

try {
    $designTest = new DesignSystemTest();
    $success = $designTest->runAllTests();
    
    if ($success) {
        echo "\n🎉 All design system tests passed! The design customization system is fully functional.\n";
        echo "\nFeatures tested:\n";
        echo "✅ Design settings update and validation\n";
        echo "✅ Backup creation and restoration\n";
        echo "✅ Dynamic CSS generation\n";
        echo "✅ Color utility functions\n";
        echo "✅ Backup management (list, delete, cleanup)\n";
        echo "✅ Error handling and security\n";
        exit(0);
    } else {
        echo "\n❌ Some design system tests failed.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n❌ Design system test suite failed with exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>