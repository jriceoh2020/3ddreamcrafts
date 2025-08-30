<?php
/**
 * Simple ContentManager Test
 * Basic tests for content management functionality without database conflicts
 */

require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/functions.php';

class ContentManagerSimpleTest {
    
    /**
     * Test ContentManager instantiation
     */
    public function testContentManagerInstantiation() {
        echo "Testing ContentManager instantiation...\n";
        
        try {
            $contentManager = new ContentManager();
            if (!$contentManager instanceof ContentManager) {
                throw new Exception("ContentManager instantiation failed");
            }
            echo "✓ ContentManager instantiation test passed\n";
        } catch (Exception $e) {
            throw new Exception("ContentManager instantiation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test AdminManager instantiation
     */
    public function testAdminManagerInstantiation() {
        echo "Testing AdminManager instantiation...\n";
        
        try {
            $adminManager = new AdminManager();
            if (!$adminManager instanceof AdminManager) {
                throw new Exception("AdminManager instantiation failed");
            }
            if (!$adminManager instanceof ContentManager) {
                throw new Exception("AdminManager should extend ContentManager");
            }
            echo "✓ AdminManager instantiation test passed\n";
        } catch (Exception $e) {
            throw new Exception("AdminManager instantiation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Test validation functions exist and work
     */
    public function testValidationFunctions() {
        echo "Testing validation functions...\n";
        
        // Test validateTextInput function exists
        if (!function_exists('validateTextInput')) {
            throw new Exception("validateTextInput function not found");
        }
        
        // Test basic validation
        $result = validateTextInput('Hello World', 5, 20, true);
        if ($result !== 'Hello World') {
            throw new Exception("validateTextInput basic test failed");
        }
        
        // Test validateEmail function exists
        if (!function_exists('validateEmail')) {
            throw new Exception("validateEmail function not found");
        }
        
        // Test email validation
        $result = validateEmail('test@example.com');
        if ($result !== 'test@example.com') {
            throw new Exception("validateEmail basic test failed");
        }
        
        // Test validateInteger function exists
        if (!function_exists('validateInteger')) {
            throw new Exception("validateInteger function not found");
        }
        
        // Test integer validation
        $result = validateInteger('42');
        if ($result !== 42) {
            throw new Exception("validateInteger basic test failed");
        }
        
        // Test sanitizeFilename function exists
        if (!function_exists('sanitizeFilename')) {
            throw new Exception("sanitizeFilename function not found");
        }
        
        // Test filename sanitization
        $result = sanitizeFilename('test.jpg');
        if ($result !== 'test.jpg') {
            throw new Exception("sanitizeFilename basic test failed");
        }
        
        echo "✓ Validation functions test passed\n";
    }
    
    /**
     * Test utility functions exist
     */
    public function testUtilityFunctions() {
        echo "Testing utility functions...\n";
        
        // Test formatDate function exists
        if (!function_exists('formatDate')) {
            throw new Exception("formatDate function not found");
        }
        
        // Test formatDateTime function exists
        if (!function_exists('formatDateTime')) {
            throw new Exception("formatDateTime function not found");
        }
        
        // Test truncateText function exists
        if (!function_exists('truncateText')) {
            throw new Exception("truncateText function not found");
        }
        
        // Test basic truncation
        $result = truncateText('This is a long text that should be truncated', 20);
        if (strlen($result) > 23) { // 20 + 3 for "..."
            throw new Exception("truncateText not working correctly");
        }
        
        echo "✓ Utility functions test passed\n";
    }
    
    /**
     * Test AdminManager methods exist
     */
    public function testAdminManagerMethods() {
        echo "Testing AdminManager methods exist...\n";
        
        $adminManager = new AdminManager();
        
        // Check if required methods exist
        $requiredMethods = [
            'createContent',
            'updateContent',
            'deleteContent',
            'getContentById',
            'getAllContent',
            'updateSettings',
            'toggleActiveStatus'
        ];
        
        foreach ($requiredMethods as $method) {
            if (!method_exists($adminManager, $method)) {
                throw new Exception("AdminManager method '$method' not found");
            }
        }
        
        echo "✓ AdminManager methods test passed\n";
    }
    
    /**
     * Test ContentManager methods exist
     */
    public function testContentManagerMethods() {
        echo "Testing ContentManager methods exist...\n";
        
        $contentManager = new ContentManager();
        
        // Check if required methods exist
        $requiredMethods = [
            'getFeaturedPrint',
            'getUpcomingShows',
            'getRecentNews',
            'getNewsWithPagination',
            'getNewsArticle',
            'getSettings'
        ];
        
        foreach ($requiredMethods as $method) {
            if (!method_exists($contentManager, $method)) {
                throw new Exception("ContentManager method '$method' not found");
            }
        }
        
        echo "✓ ContentManager methods test passed\n";
    }
    
    /**
     * Run all simple tests
     */
    public function runAllTests() {
        echo "Running Simple Content Management tests...\n\n";
        
        try {
            $this->testContentManagerInstantiation();
            $this->testAdminManagerInstantiation();
            $this->testValidationFunctions();
            $this->testUtilityFunctions();
            $this->testAdminManagerMethods();
            $this->testContentManagerMethods();
            
            echo "\n✅ All Simple Content Management tests passed!\n";
            echo "Content management foundation classes and functions are properly implemented.\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ContentManagerSimpleTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>