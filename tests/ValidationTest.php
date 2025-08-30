<?php
/**
 * Validation Functions Test Suite
 * Tests for input validation and sanitization functions
 */

require_once __DIR__ . '/../includes/content.php';

class ValidationTest {
    
    /**
     * Test text input validation
     */
    public function testValidateTextInput() {
        echo "Testing validateTextInput()...\n";
        
        // Test valid input
        $result = validateTextInput('Hello World', 5, 20, true);
        if ($result !== 'Hello World') {
            throw new Exception("Valid text input failed");
        }
        
        // Test input too short
        $result = validateTextInput('Hi', 5, 20, true);
        if ($result !== null) {
            throw new Exception("Short text should return null");
        }
        
        // Test input too long
        $result = validateTextInput(str_repeat('a', 25), 5, 20, true);
        if ($result !== null) {
            throw new Exception("Long text should return null");
        }
        
        // Test empty optional input
        $result = validateTextInput('', 5, 20, false);
        if ($result !== '') {
            throw new Exception("Empty optional input should return empty string");
        }
        
        // Test empty required input
        $result = validateTextInput('', 5, 20, true);
        if ($result !== null) {
            throw new Exception("Empty required input should return null");
        }
        
        // Test HTML encoding
        $result = validateTextInput('<script>alert("xss")</script>', 0, 100, true);
        if (strpos($result, '<script>') !== false) {
            throw new Exception("HTML should be encoded");
        }
        
        echo "✓ validateTextInput() test passed\n";
    }
    
    /**
     * Test email validation
     */
    public function testValidateEmail() {
        echo "Testing validateEmail()...\n";
        
        // Test valid email
        $result = validateEmail('test@example.com');
        if ($result !== 'test@example.com') {
            throw new Exception("Valid email failed");
        }
        
        // Test invalid email
        $result = validateEmail('invalid-email');
        if ($result !== null) {
            throw new Exception("Invalid email should return null");
        }
        
        // Test email with spaces
        $result = validateEmail(' test@example.com ');
        if ($result !== 'test@example.com') {
            throw new Exception("Email with spaces should be trimmed");
        }
        
        echo "✓ validateEmail() test passed\n";
    }
    
    /**
     * Test URL validation
     */
    public function testValidateUrl() {
        echo "Testing validateUrl()...\n";
        
        // Test valid URL
        $result = validateUrl('https://example.com');
        if ($result !== 'https://example.com') {
            throw new Exception("Valid URL failed");
        }
        
        // Test invalid URL
        $result = validateUrl('not-a-url');
        if ($result !== null) {
            throw new Exception("Invalid URL should return null");
        }
        
        // Test URL with spaces
        $result = validateUrl(' https://example.com ');
        if ($result !== 'https://example.com') {
            throw new Exception("URL with spaces should be trimmed");
        }
        
        echo "✓ validateUrl() test passed\n";
    }
    
    /**
     * Test integer validation
     */
    public function testValidateInteger() {
        echo "Testing validateInteger()...\n";
        
        // Test valid integer
        $result = validateInteger('42');
        if ($result !== 42) {
            throw new Exception("Valid integer failed");
        }
        
        // Test integer with range
        $result = validateInteger('50', 1, 100);
        if ($result !== 50) {
            throw new Exception("Integer in range failed");
        }
        
        // Test integer below minimum
        $result = validateInteger('0', 1, 100);
        if ($result !== null) {
            throw new Exception("Integer below minimum should return null");
        }
        
        // Test integer above maximum
        $result = validateInteger('150', 1, 100);
        if ($result !== null) {
            throw new Exception("Integer above maximum should return null");
        }
        
        // Test non-numeric input
        $result = validateInteger('abc');
        if ($result !== null) {
            throw new Exception("Non-numeric input should return null");
        }
        
        echo "✓ validateInteger() test passed\n";
    }
    
    /**
     * Test hex color validation
     */
    public function testValidateHexColor() {
        echo "Testing validateHexColor()...\n";
        
        // Test valid hex color with #
        $result = validateHexColor('#ff0000');
        if ($result !== '#ff0000') {
            throw new Exception("Valid hex color with # failed");
        }
        
        // Test valid hex color without #
        $result = validateHexColor('ff0000');
        if ($result !== '#ff0000') {
            throw new Exception("Valid hex color without # failed");
        }
        
        // Test uppercase hex color
        $result = validateHexColor('#FF0000');
        if ($result !== '#ff0000') {
            throw new Exception("Uppercase hex color should be converted to lowercase");
        }
        
        // Test invalid hex color
        $result = validateHexColor('#gggggg');
        if ($result !== null) {
            throw new Exception("Invalid hex color should return null");
        }
        
        // Test short hex color
        $result = validateHexColor('#fff');
        if ($result !== null) {
            throw new Exception("Short hex color should return null");
        }
        
        echo "✓ validateHexColor() test passed\n";
    }
    
    /**
     * Test filename sanitization
     */
    public function testSanitizeFilename() {
        echo "Testing sanitizeFilename()...\n";
        
        // Test normal filename
        $result = sanitizeFilename('test.jpg');
        if ($result !== 'test.jpg') {
            throw new Exception("Normal filename failed");
        }
        
        // Test filename with special characters
        $result = sanitizeFilename('test file!@#$.jpg');
        // The exact result may vary based on regex replacement, just check it's sanitized
        if (strpos($result, '!') !== false || strpos($result, '@') !== false || strpos($result, '#') !== false) {
            throw new Exception("Filename with special characters not properly sanitized: " . $result);
        }
        
        // Test filename with path
        $result = sanitizeFilename('/path/to/test.jpg');
        if ($result !== 'test.jpg') {
            throw new Exception("Filename with path failed");
        }
        
        // Test empty filename
        $result = sanitizeFilename('');
        if (strpos($result, 'file_') !== 0) {
            throw new Exception("Empty filename should generate default name");
        }
        
        echo "✓ sanitizeFilename() test passed\n";
    }
    
    /**
     * Test CSRF token functions
     */
    public function testCSRFToken() {
        echo "Testing CSRF token functions...\n";
        
        // Start session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate token
        $token = generateCSRFToken();
        if (empty($token)) {
            throw new Exception("CSRF token generation failed");
        }
        
        // Validate correct token
        $isValid = validateCSRFToken($token);
        if (!$isValid) {
            throw new Exception("Valid CSRF token validation failed");
        }
        
        // Validate incorrect token
        $isValid = validateCSRFToken('invalid-token');
        if ($isValid) {
            throw new Exception("Invalid CSRF token should not validate");
        }
        
        echo "✓ CSRF token test passed\n";
    }
    
    /**
     * Test HTML content cleaning
     */
    public function testCleanHtmlContent() {
        echo "Testing cleanHtmlContent()...\n";
        
        // Test allowed tags
        $html = '<p>Hello <strong>world</strong>!</p>';
        $result = cleanHtmlContent($html);
        if ($result !== $html) {
            throw new Exception("Allowed tags should be preserved");
        }
        
        // Test disallowed tags
        $html = '<p>Hello <script>alert("xss")</script> world!</p>';
        $result = cleanHtmlContent($html);
        if (strpos($result, '<script>') !== false) {
            throw new Exception("Disallowed tags should be removed");
        }
        
        // Test javascript: URLs
        $html = '<a href="javascript:alert(\'xss\')">Link</a>';
        $result = cleanHtmlContent($html);
        if (strpos($result, 'javascript:') !== false) {
            throw new Exception("JavaScript URLs should be removed");
        }
        
        echo "✓ cleanHtmlContent() test passed\n";
    }
    
    /**
     * Run all validation tests
     */
    public function runAllTests() {
        echo "Running Validation tests...\n\n";
        
        try {
            $this->testValidateTextInput();
            $this->testValidateEmail();
            $this->testValidateUrl();
            $this->testValidateInteger();
            $this->testValidateHexColor();
            $this->testSanitizeFilename();
            $this->testCSRFToken();
            $this->testCleanHtmlContent();
            
            echo "\n✅ All Validation tests passed!\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ValidationTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>