<?php
/**
 * Login Integration Test
 * Tests the complete login workflow including form submission and redirects
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

class LoginIntegrationTest {
    private $db;
    private $auth;
    private $testDbPath;
    
    public function __construct() {
        $this->testDbPath = __DIR__ . '/test_login_integration.db';
    }
    
    public function setUp() {
        // Clean up any existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Create test database
        $this->createTestDatabase();
        
        // Reset singleton instances
        $reflection = new ReflectionClass('DatabaseManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $this->db = DatabaseManager::getInstance();
        
        $reflection = new ReflectionClass('AuthManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $this->auth = AuthManager::getInstance();
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
    
    public function tearDown() {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
    
    private function createTestDatabase() {
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function testLoginPageAccess() {
        echo "Testing login page access...\n";
        
        // Simulate accessing login page
        ob_start();
        
        // Mock server variables
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/admin/login.php';
        
        // Capture output from login page
        try {
            include __DIR__ . '/../admin/login.php';
            $output = ob_get_contents();
            
            // Check that page contains expected elements
            assert(strpos($output, 'Admin Login') !== false, "Page should contain login title");
            assert(strpos($output, 'name="username"') !== false, "Page should contain username field");
            assert(strpos($output, 'name="password"') !== false, "Page should contain password field");
            assert(strpos($output, 'name="csrf_token"') !== false, "Page should contain CSRF token");
            
            echo "✓ Login page access test passed\n";
        } catch (Exception $e) {
            echo "✗ Login page access test failed: " . $e->getMessage() . "\n";
            throw $e;
        } finally {
            ob_end_clean();
        }
    }
    
    public function testLoginFormSubmission() {
        echo "Testing login form submission...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Generate CSRF token
        $csrfToken = $this->auth->generateCSRFToken();
        
        // Mock POST request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'username' => 'testuser',
            'password' => 'testpassword123',
            'csrf_token' => $csrfToken
        ];
        
        // Capture any output/redirects
        ob_start();
        
        try {
            // Include login page (which processes the POST)
            include __DIR__ . '/../admin/login.php';
            
            // If we get here without redirect, check authentication status
            assert($this->auth->isAuthenticated() === true, "User should be authenticated after successful login");
            
            echo "✓ Login form submission test passed\n";
        } catch (Exception $e) {
            // Check if it's a redirect (which is expected)
            $headers = headers_list();
            $redirectFound = false;
            foreach ($headers as $header) {
                if (strpos($header, 'Location:') === 0) {
                    $redirectFound = true;
                    break;
                }
            }
            
            if ($redirectFound) {
                echo "✓ Login form submission test passed (redirect detected)\n";
            } else {
                echo "✗ Login form submission test failed: " . $e->getMessage() . "\n";
                throw $e;
            }
        } finally {
            ob_end_clean();
            // Clear POST data
            $_POST = [];
        }
    }
    
    public function testInvalidLoginSubmission() {
        echo "Testing invalid login submission...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Generate CSRF token
        $csrfToken = $this->auth->generateCSRFToken();
        
        // Mock POST request with wrong password
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'username' => 'testuser',
            'password' => 'wrongpassword',
            'csrf_token' => $csrfToken
        ];
        
        ob_start();
        
        try {
            include __DIR__ . '/../admin/login.php';
            $output = ob_get_contents();
            
            // Should show error message
            assert(strpos($output, 'Invalid username or password') !== false, "Should show error message");
            assert($this->auth->isAuthenticated() === false, "User should not be authenticated");
            
            echo "✓ Invalid login submission test passed\n";
        } catch (Exception $e) {
            echo "✗ Invalid login submission test failed: " . $e->getMessage() . "\n";
            throw $e;
        } finally {
            ob_end_clean();
            $_POST = [];
        }
    }
    
    public function testCSRFProtection() {
        echo "Testing CSRF protection...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Mock POST request with invalid CSRF token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'username' => 'testuser',
            'password' => 'testpassword123',
            'csrf_token' => 'invalid_token'
        ];
        
        ob_start();
        
        try {
            include __DIR__ . '/../admin/login.php';
            $output = ob_get_contents();
            
            // Should show CSRF error
            assert(strpos($output, 'Invalid security token') !== false, "Should show CSRF error message");
            assert($this->auth->isAuthenticated() === false, "User should not be authenticated");
            
            echo "✓ CSRF protection test passed\n";
        } catch (Exception $e) {
            echo "✗ CSRF protection test failed: " . $e->getMessage() . "\n";
            throw $e;
        } finally {
            ob_end_clean();
            $_POST = [];
        }
    }
    
    public function runAllTests() {
        echo "Running Login Integration tests...\n\n";
        
        $tests = [
            'testLoginPageAccess',
            'testLoginFormSubmission',
            'testInvalidLoginSubmission',
            'testCSRFProtection'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $this->setUp();
                $this->$test();
                $passed++;
            } catch (Exception $e) {
                echo "✗ Test $test failed: " . $e->getMessage() . "\n";
                $failed++;
            } catch (AssertionError $e) {
                echo "✗ Test $test failed: " . $e->getMessage() . "\n";
                $failed++;
            } finally {
                $this->tearDown();
            }
        }
        
        echo "\nTest Results:\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Total: " . ($passed + $failed) . "\n";
        
        return $failed === 0;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new LoginIntegrationTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}