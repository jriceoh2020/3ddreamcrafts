<?php
/**
 * Upload Security Tests
 * Focused tests for file upload security vulnerabilities
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/upload.php';

class UploadSecurityTest {
    private $uploadManager;
    private $testDir;
    private $testFiles = [];
    
    public function __construct() {
        $this->uploadManager = getUploadManager();
        $this->testDir = __DIR__ . '/sec_test/';
        $this->setupTestEnvironment();
    }
    
    public function runAllTests() {
        echo "=== Upload Security Tests ===\n\n";
        
        $tests = [
            'testPhpFilePrevention',
            'testExecutablePrevention',
            'testDoubleExtension',
            'testNullByteInjection',
            'testPathTraversal',
            'testFilenameInjection',
            'testMimeTypeSpoofing'
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
        
        echo "\n=== Security Test Results ===\n";
        echo "Passed: $passed/$total\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 1) . "%\n";
        
        $this->cleanup();
        
        return $passed === $total;
    }
    
    private function testPhpFilePrevention() {
        $phpContent = '<?php echo "malicious"; ?>';
        $testFile = $this->testDir . 'malicious.php';
        file_put_contents($testFile, $phpContent);
        
        $fileArray = [
            'name' => 'malicious.php',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'application/x-php'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        return !$result['success'];
    }
    
    private function testExecutablePrevention() {
        $executables = ['exe', 'bat', 'sh', 'cmd'];
        
        foreach ($executables as $ext) {
            $testFile = $this->testDir . "test.$ext";
            file_put_contents($testFile, 'content');
            
            $fileArray = [
                'name' => "test.$ext",
                'tmp_name' => $testFile,
                'size' => filesize($testFile),
                'error' => UPLOAD_ERR_OK,
                'type' => 'application/octet-stream'
            ];
            
            $result = $this->uploadManager->uploadFile($fileArray);
            if ($result['success']) {
                return false;
            }
        }
        
        return true;
    }
    
    private function testDoubleExtension() {
        $testFile = $this->createTestImage('test.jpg');
        
        $fileArray = [
            'name' => 'image.jpg.php',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        return !$result['success'];
    }
    
    private function testNullByteInjection() {
        $testFile = $this->createTestImage('test.jpg');
        
        $fileArray = [
            'name' => "image.jpg\x00.php",
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        
        if ($result['success']) {
            if (strpos($result['filename'], "\x00") !== false) {
                return false;
            }
            $this->testFiles[] = $result['path'];
        }
        
        return true;
    }
    
    private function testPathTraversal() {
        $traversalAttempts = [
            '../../../etc/passwd.jpg',
            '..\\..\\windows\\test.jpg',
            '/etc/passwd.jpg'
        ];
        
        foreach ($traversalAttempts as $maliciousName) {
            $testFile = $this->createTestImage('test.jpg');
            
            $fileArray = [
                'name' => $maliciousName,
                'tmp_name' => $testFile,
                'size' => filesize($testFile),
                'error' => UPLOAD_ERR_OK,
                'type' => 'image/jpeg'
            ];
            
            $result = $this->uploadManager->uploadFile($fileArray);
            
            if ($result['success']) {
                $uploadedPath = UPLOAD_PATH . ltrim($result['path'], '/');
                if (!file_exists($uploadedPath)) {
                    return false;
                }
                
                if (strpos($result['path'], '../') !== false || 
                    strpos($result['path'], '..\\') !== false ||
                    strpos($result['path'], '/etc/') !== false) {
                    return false;
                }
                
                $this->testFiles[] = $result['path'];
            }
        }
        
        return true;
    }
    
    private function testFilenameInjection() {
        $maliciousNames = [
            'test.jpg; rm -rf /',
            'test.jpg && echo "hack"',
            'test.jpg<script>alert(1)</script>'
        ];
        
        foreach ($maliciousNames as $maliciousName) {
            $testFile = $this->createTestImage('test.jpg');
            
            $fileArray = [
                'name' => $maliciousName,
                'tmp_name' => $testFile,
                'size' => filesize($testFile),
                'error' => UPLOAD_ERR_OK,
                'type' => 'image/jpeg'
            ];
            
            $result = $this->uploadManager->uploadFile($fileArray);
            
            if ($result['success']) {
                $filename = $result['filename'];
                $dangerousChars = [';', '&', '<', '>', '"', "'"];
                
                foreach ($dangerousChars as $char) {
                    if (strpos($filename, $char) !== false) {
                        return false;
                    }
                }
                
                $this->testFiles[] = $result['path'];
            }
        }
        
        return true;
    }
    
    private function testMimeTypeSpoofing() {
        $phpContent = '<?php echo "malicious"; ?>';
        $testFile = $this->testDir . 'spoofed.jpg';
        file_put_contents($testFile, $phpContent);
        
        $fileArray = [
            'name' => 'spoofed.jpg',
            'tmp_name' => $testFile,
            'size' => filesize($testFile),
            'error' => UPLOAD_ERR_OK,
            'type' => 'image/jpeg'
        ];
        
        $result = $this->uploadManager->uploadFile($fileArray);
        return !$result['success'];
    }
    
    private function setupTestEnvironment() {
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
        
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }
    }
    
    private function createTestImage($filename) {
        $filepath = $this->testDir . $filename;
        
        // Create a minimal JPEG file header for testing
        $jpegHeader = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB\x00C\x00";
        $jpegHeader .= str_repeat("\x00", 64); // Quantization table
        $jpegHeader .= "\xFF\xC0\x00\x11\x08\x00\x64\x00\x64\x01\x01\x11\x00\x02\x11\x01\x03\x11\x01";
        $jpegHeader .= "\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08";
        $jpegHeader .= "\xFF\xDA\x00\x0C\x03\x01\x00\x02\x11\x03\x11\x00\x3F\x00";
        $jpegHeader .= "\xFF\xD9"; // End of image
        
        file_put_contents($filepath, $jpegHeader);
        
        return $filepath;
    }
    
    private function cleanup() {
        foreach ($this->testFiles as $filePath) {
            $fullPath = UPLOAD_PATH . ltrim($filePath, '/');
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new UploadSecurityTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>