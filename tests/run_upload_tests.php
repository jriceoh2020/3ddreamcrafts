<?php
/**
 * Upload System Test Runner
 * Runs all file upload related tests
 */

require_once __DIR__ . '/../includes/config.php';

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== File Upload System Test Suite ===\n";
echo "Testing file upload functionality and security...\n\n";

$testFiles = [
    'UploadBasicTest.php' => 'Basic Upload Functionality Tests',
    'UploadSecTest.php' => 'Upload Security Tests'
];

$totalPassed = 0;
$totalTests = 0;
$allSuccess = true;

foreach ($testFiles as $testFile => $description) {
    echo "Running $description...\n";
    echo str_repeat('-', 50) . "\n";
    
    $testPath = __DIR__ . '/' . $testFile;
    
    if (!file_exists($testPath)) {
        echo "ERROR: Test file $testFile not found!\n\n";
        $allSuccess = false;
        continue;
    }
    
    // Capture output and run test
    ob_start();
    $testSuccess = false;
    
    try {
        include $testPath;
        
        // Get the test class name from filename
        $className = str_replace('.php', '', $testFile);
        
        // Map filename to actual class name
        $classMap = [
            'UploadBasicTest' => 'UploadBasicTest',
            'UploadSecTest' => 'UploadSecurityTest'
        ];
        
        $actualClassName = isset($classMap[$className]) ? $classMap[$className] : $className;
        
        if (class_exists($actualClassName)) {
            $test = new $actualClassName();
            $testSuccess = $test->runAllTests();
        }
    } catch (Exception $e) {
        echo "FATAL ERROR: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    echo $output;
    
    if ($testSuccess) {
        echo "✓ $description completed successfully\n";
    } else {
        echo "✗ $description failed\n";
        $allSuccess = false;
    }
    
    echo "\n";
}

echo str_repeat('=', 60) . "\n";
echo "FINAL RESULTS\n";
echo str_repeat('=', 60) . "\n";

if ($allSuccess) {
    echo "✓ ALL UPLOAD TESTS PASSED\n";
    echo "The file upload system is working correctly and securely.\n";
} else {
    echo "✗ SOME TESTS FAILED\n";
    echo "Please review the failed tests and fix any issues.\n";
}

echo "\nTest Categories Completed:\n";
foreach ($testFiles as $testFile => $description) {
    echo "- $description\n";
}

echo "\nUpload System Features Tested:\n";
echo "- File type validation\n";
echo "- File size limits\n";
echo "- Filename sanitization\n";
echo "- Image processing and resizing\n";
echo "- Path traversal prevention\n";
echo "- Security vulnerability prevention\n";
echo "- File operations (upload, delete, list)\n";
echo "- MIME type validation\n";
echo "- Malicious file detection\n";

exit($allSuccess ? 0 : 1);
?>