<?php
/**
 * File Upload System Tests
 * Tests for secure file upload functionality and validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';

class FileUploadTest {
    private $uploadManager;
    private $testDir;
    private $testFiles = [];
    
    public function __construct() {
        $this->uploadManager = getUploadManager();
        $this->testDir = __DIR__ . '/test_uploads/';
        $this->setupTestEnvironment();
    }
    
    public function runAllTests() {
        echo "=== File Upload System Tests ===\n\n";
        
        $tests = [
            'testValidImageUpload',
            'testInvalidFileType',
            'testFileSizeLimit',
            'testFilenameSanitization',
            'testImageProcessing',
            'testFilePathValidation',
            'testFileOperations',
            'testSecurityValidation',
            'testDirectoryTraversal',
            'testMimeTypeValidation'
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
    
    private function testValidImageUpload() {
        // Create a test image file
        $testFile = $this->createTestImage('test_valid.jpg', 100, 100);
        
        $fileArray = [
            'name' => 'test_valid.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray, 'test');
        
        if (!$result['success']) {
            return false;
        }
        
        // Verify file was uploaded
        $uploadedPath = UPLOAD_PATH . 'test/' . $result['filename'];
        if (!file_exists($uploadedPath)) {
            return false;
        }
        
        // Verify file info
        if (empty($result['filename']) || empty($result['path'])) {
            return false;
        }
        
        $this->testFiles[] = $result['path'];
        return true;
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
        // Create a large test file (simulate file larger than limit)
        $testFile = $this->testDir . 'test_large.jpg';
        $largeSize = MAX_UPLOAD_SIZE + 1000;
        
        // Create a fake large file for testing
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
        // Test filename with dangerous characters
        $testFile = $this->createTestImage('test_image.jpg', 50, 50);
        
        $fileArray = [
            'name' => '../../../evil<script>.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        if (!$result['success']) {
            return false;
        }
        
        // Verify filename was sanitized
        $filename = $result['filename'];
        if (strpos($filename, '../') !== false || 
            strpos($filename, '<') !== false || 
            strpos($filename, 'script') !== false) {
            return false;
        }
        
        $this->testFiles[] = $result['path'];
        return true;
    }
    
    private function testImageProcessing() {
        // Create a large test image that should be resized
        $testFile = $this->createTestImage('test_large_image.jpg', 3000, 3000);
        
        $fileArray = [
            'name' => 'test_large_image.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        if (!$result['success']) {
            return false;
        }
        
        // Verify image was resized
        $dimensions = $result['dimensions'];
        if ($dimensions['width'] > 2048 || $dimensions['height'] > 2048) {
            return false;
        }
        
        $this->testFiles[] = $result['path'];
        return true;
    }
    
    private function testFilePathValidation() {
        // Test various path validation scenarios
        $validPaths = [
            'uploads/test.jpg',
            'uploads/folder/test.jpg',
            'test.jpg'
        ];
        
        $invalidPaths = [
            '../test.jpg',
            '/etc/passwd',
            'C:\\Windows\\system32\\test.jpg',
            '../../uploads/test.jpg'
        ];
        
        // Test valid paths
        foreach ($validPaths as $path) {
            $info = $this->uploadManager->getFileInfo($path);
            // Should not throw errors (may return null if file doesn't exist)
        }
        
        // Test invalid paths
        foreach ($invalidPaths as $path) {
            $info = $this->uploadManager->getFileInfo($path);
            if ($info !== null) {
                return false; // Should return null for invalid paths
            }
        }
        
        return true;
    }
    
    private function testFileOperations() {
        // Test file listing, info retrieval, and deletion
        $testFile = $this->createTestImage('test_operations.jpg', 100, 100);
        
        $fileArray = [
            'name' => 'test_operations.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray, 'test');
        
        if (!$result['success']) {
            return false;
        }
        
        $filePath = $result['path'];
        
        // Test file info retrieval
        $info = $this->uploadManager->getFileInfo($filePath);
        if (!$info || $info['size'] <= 0) {
            return false;
        }
        
        // Test file listing
        $files = $this->uploadManager->listFiles('test');
        $found = false;
        foreach ($files as $file) {
            if ($file['path'] === $filePath) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        // Test file deletion
        $deleteResult = $this->uploadManager->deleteFile($filePath);
        if (!$deleteResult) {
            return false;
        }
        
        // Verify file was deleted
        $info = $this->uploadManager->getFileInfo($filePath);
        return $info === null;
    }
    
    private function testSecurityValidation() {
        // Test various security scenarios
        
        // Test empty file
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
        $testFile = $this->createTestImage('noextension', 50, 50);
        
        $fileArray = [
            'name' => 'noextension',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        if ($result['success']) {
            return false; // Should fail for file with no extension
        }
        
        return true;
    }
    
    private function testDirectoryTraversal() {
        // Test directory traversal prevention
        $testFile = $this->createTestImage('test.jpg', 50, 50);
        
        $fileArray = [
            'name' => '../../../etc/passwd.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        if (!$result['success']) {
            return false;
        }
        
        // Verify file was saved in uploads directory, not traversed path
        $uploadedPath = UPLOAD_PATH . $result['filename'];
        if (!file_exists($uploadedPath)) {
            return false;
        }
        
        // Verify no directory traversal occurred
        if (strpos($result['path'], '../') !== false) {
            return false;
        }
        
        $this->testFiles[] = $result['path'];
        return true;
    }
    
    private function testMimeTypeValidation() {
        // Create a file with wrong extension but correct MIME type
        $testFile = $this->createTestImage('test.txt', 50, 50); // JPEG with .txt extension
        
        $fileArray = [
            'name' => 'test.txt',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        // Should fail due to extension mismatch
        return !$result['success'];
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
        
        if (!is_dir(UPLOAD_PATH . 'test/')) {
            mkdir(UPLOAD_PATH . 'test/', 0755, true);
        }
    }
    
    private function createTestImage($filename, $width = 100, $height = 100) {
        $filepath = $this->testDir . $filename;
        
        // Create a simple test image
        $image = imagecreate($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Add some content to make it a valid image
        imageline($image, 0, 0, $width, $height, $black);
        imageline($image, 0, $height, $width, 0, $black);
        
        // Save as JPEG
        imagejpeg($image, $filepath, 90);
        imagedestroy($image);
        
        return $filepath;
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
            $files = glob($this->testDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
        
        // Clean up test upload directory
        $testUploadDir = UPLOAD_PATH . 'test/';
        if (is_dir($testUploadDir)) {
            $files = glob($testUploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($testUploadDir);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new FileUploadTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>