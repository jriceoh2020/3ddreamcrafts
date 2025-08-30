<?php
/**
 * Basic Upload Tests
 * Tests file upload functionality without requiring GD extension
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';

class UploadBasicTest {
    private $uploadManager;
    private $testDir;
    private $testFiles = [];
    
    public function __construct() {
        $this->uploadManager = getUploadManager();
        $this->testDir = __DIR__ . '/basic_test/';
        $this->setupTestEnvironment();
    }
    
    public function runAllTests() {
        echo "=== Basic Upload Tests ===\n\n";
        
        $tests = [
            'testUploadManagerCreation',
            'testInvalidFileType',
            'testFileSizeLimit',
            'testFilenameSanitization',
            'testPathValidation',
            'testSecurityValidation',
            'testUtilityFunctions'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            try {
                echo "Running $test... ";
                $result = $this->$test();
                if ($result) {
                    echo "PASS\n";
                    $passed++;
                } else {
                    echo "FAIL\n";
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n=== Test Results ===\n";
        echo "Passed: $passed/$total\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 1) . "%\n";
        
        $this->cleanup();
        
        return $passed === $total;
    }
    
    private function testUploadManagerCreation() {
        // Test that upload manager can be created
        $manager = getUploadManager();
        return $manager instanceof FileUploadManager;
    }
    
    private function testInvalidFileType() {
        // Create a test text file
        $testFile = $this->testDir . 'test_invalid.txt';
        file_put_contents($testFile, 'This is not an image');
        
        $fileArray = [
            'name' => 'test_invalid.txt',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        // Should fail due to invalid file type
        return !$result['success'] && strpos($result['error'], 'Invalid file type') !== false;
    }
    
    private function testFileSizeLimit() {
        // Test file size limit validation
        $testFile = $this->testDir . 'test_large.jpg';
        file_put_contents($testFile, 'fake image content');
        
        $largeSize = MAX_UPLOAD_SIZE + 1000;
        
        $fileArray = [
            'name' => 'test_large.jpg',
            'tmp_name' => $testFile,
            'size' => $largeSize,
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        // Should fail due to file size
        return !$result['success'] && strpos($result['error'], 'too large') !== false;
    }
    
    private function testFilenameSanitization() {
        // Test filename sanitization functions
        $dangerousFilename = '../../../evil<script>.jpg';
        $sanitized = sanitizeFilename($dangerousFilename);
        
        // Should not contain dangerous path characters
        if (strpos($sanitized, '../') !== false || 
            strpos($sanitized, '<') !== false || 
            strpos($sanitized, '>') !== false) {
            return false;
        }
        
        // Should be a valid filename (the word "script" itself is not dangerous when sanitized)
        if (empty($sanitized) || strpos($sanitized, '.') === false) {
            return false;
        }
        
        // Test unique filename generation
        $testDir = rtrim($this->testDir, '/\\');
        $filename = 'test.jpg';
        
        // Ensure test directory exists
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        // Create a file to test uniqueness - use same path format as function
        $fullPath = $testDir . '/' . $filename;
        file_put_contents($fullPath, 'test');
        
        // Verify file was created
        if (!file_exists($fullPath)) {
            echo "Failed to create test file: $fullPath\n";
            return false;
        }
        
        $uniqueFilename = generateUniqueFilename($filename, $testDir);
        
        // Debug output for troubleshooting (commented out for clean test output)
        // echo "TestDir: $testDir\n";
        // echo "Original: $filename, Unique: $uniqueFilename, File exists: " . (file_exists($fullPath) ? 'yes' : 'no') . "\n";
        
        // When a file exists, the function should return a different filename
        $result = true;
        if ($uniqueFilename === $filename) {
            // This means the function didn't detect the existing file
            $result = false;
        }
        
        // Clean up test file
        unlink($fullPath);
        
        return $result;
    }
    
    private function testPathValidation() {
        // Test path validation functions
        $validPaths = [
            'uploads/test.jpg',
            'uploads/folder/test.jpg',
            'test.jpg'
        ];
        
        $invalidPaths = [
            '../test.jpg',
            '/etc/passwd',
            'C:\\Windows\\system32\\test.jpg'
        ];
        
        // Test valid paths (should not throw errors)
        foreach ($validPaths as $path) {
            try {
                $info = $this->uploadManager->getFileInfo($path);
                // Should return null for non-existent files, not throw errors
            } catch (Exception $e) {
                return false;
            }
        }
        
        // Test invalid paths (should return null)
        foreach ($invalidPaths as $path) {
            $info = $this->uploadManager->getFileInfo($path);
            if ($info !== null) {
                return false;
            }
        }
        
        return true;
    }
    
    private function testSecurityValidation() {
        // Test empty file validation
        $emptyFile = $this->testDir . 'empty.jpg';
        file_put_contents($emptyFile, '');
        
        $fileArray = [
            'name' => 'empty.jpg',
            'tmp_name' => $emptyFile,
            'size' => 0,
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        if ($result['success']) {
            return false; // Should fail for empty file
        }
        
        // Test file with no extension
        $noExtFile = $this->testDir . 'noextension';
        file_put_contents($noExtFile, 'fake content');
        
        $fileArray = [
            'name' => 'noextension',
            'tmp_name' => $noExtFile,
            'size' => filesize($noExtFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        if ($result['success']) {
            return false; // Should fail for file with no extension
        }
        
        return true;
    }
    
    private function testUtilityFunctions() {
        // Test utility functions from functions.php
        
        // Test file extension function
        $extension = getFileExtension('test.JPG');
        if ($extension !== 'jpg') {
            return false;
        }
        
        // Test allowed image type check
        if (!isAllowedImageType('test.jpg')) {
            return false;
        }
        
        if (isAllowedImageType('test.exe')) {
            return false;
        }
        
        // Test file size formatting
        $formatted = formatFileSize(1024);
        if (strpos($formatted, 'KB') === false) {
            return false;
        }
        
        // Test directory creation
        $testSubDir = $this->testDir . 'subdir/';
        if (!ensureDirectoryExists($testSubDir)) {
            return false;
        }
        
        if (!is_dir($testSubDir)) {
            return false;
        }
        
        return true;
    }
    
    private function setupTestEnvironment() {
        // Create test directory
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        
        // Ensure uploads directory exists
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
    }
    
    private function cleanup() {
        // Clean up test files
        foreach ($this->testFiles as $filePath) {
            $fullPath = UPLOAD_PATH . ltrim($filePath, '/');
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        // Clean up test directory
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }
    
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UploadBasicTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>