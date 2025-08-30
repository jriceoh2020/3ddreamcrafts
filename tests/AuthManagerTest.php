<?php
/**
 * AuthManager Test Suite
 * Tests for authentication functionality including login, logout, and session management
 */

// Set up test environment before including other files
if (!defined('TEST_MODE')) {
    define('TEST_MODE', true);
}

require_once __DIR__ . '/../includes/config.php';

// Override DB_PATH for testing
if (!defined('TEST_DB_PATH')) {
    define('TEST_DB_PATH', __DIR__ . '/test_auth.db');
}

// Temporarily override the DB_PATH constant
$originalDbPath = DB_PATH;
if (function_exists('runkit_constant_redefine')) {
    runkit_constant_redefine('DB_PATH', TEST_DB_PATH);
} else {
    // For systems without runkit, we'll work around this
    $_ENV['TEST_DB_PATH'] = TEST_DB_PATH;
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

class AuthManagerTest {
    private $db;
    private $auth;
    private $testDbPath;
    
    public function __construct() {
        $this->testDbPath = TEST_DB_PATH;
    }
    
    public function setUp() {
        // Clean up any existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Create test database with schema
        $this->createTestDatabase();
        
        // Override DB_PATH constant by creating a temporary config
        $this->overrideDbPath();
        
        // Reset singleton instances
        $reflection = new ReflectionClass('DatabaseManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $reflection = new ReflectionClass('AuthManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        // Create new database manager instance with test database
        $this->db = DatabaseManager::getInstance();
        
        // Clear any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        // Create auth manager instance (after session cleanup)
        $this->auth = AuthManager::getInstance();
    }
    
    private function overrideDbPath() {
        // Create a temporary database manager that uses our test database
        $reflection = new ReflectionClass('DatabaseManager');
        $connectMethod = $reflection->getMethod('connect');
        $connectMethod->setAccessible(true);
        
        // We'll override the DB_PATH by modifying the constant temporarily
        // This is a bit hacky but necessary for testing
        if (!defined('TEST_MODE')) {
            define('TEST_MODE', true);
        }
    }
    
    public function tearDown() {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        // Reset singleton instances
        $reflection = new ReflectionClass('DatabaseManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $reflection = new ReflectionClass('AuthManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
    
    private function createTestDatabase() {
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create admin_users table
        $pdo->exec("
            CREATE TABLE admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create settings table
        $pdo->exec("
            CREATE TABLE settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function testCreateUser() {
        echo "Testing user creation...\n";
        
        // Test successful user creation
        $result = $this->auth->createUser('testuser', 'testpassword123');
        assert($result === true, "User creation should succeed");
        
        // Verify user exists in database
        $user = $this->db->queryOne("SELECT * FROM admin_users WHERE username = ?", ['testuser']);
        assert($user !== null, "User should exist in database");
        assert($user['username'] === 'testuser', "Username should match");
        assert(password_verify('testpassword123', $user['password_hash']), "Password should be hashed correctly");
        
        echo "✓ User creation test passed\n";
    }
    
    public function testCreateUserValidation() {
        echo "Testing user creation validation...\n";
        
        // Test empty username
        try {
            $this->auth->createUser('', 'testpassword123');
            assert(false, "Should throw exception for empty username");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'required') !== false, "Should mention required fields");
        }
        
        // Test empty password
        try {
            $this->auth->createUser('testuser', '');
            assert(false, "Should throw exception for empty password");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'required') !== false, "Should mention required fields");
        }
        
        // Test short password
        try {
            $this->auth->createUser('testuser', '123');
            assert(false, "Should throw exception for short password");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'characters long') !== false, "Should mention password length");
        }
        
        // Create a user first
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Test duplicate username
        try {
            $this->auth->createUser('testuser', 'anotherpassword123');
            assert(false, "Should throw exception for duplicate username");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'already exists') !== false, "Should mention username exists");
        }
        
        echo "✓ User creation validation test passed\n";
    }
    
    public function testLogin() {
        echo "Testing login functionality...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Test successful login
        $result = $this->auth->login('testuser', 'testpassword123');
        assert($result === true, "Login should succeed with correct credentials");
        assert($this->auth->isAuthenticated() === true, "User should be authenticated after login");
        
        // Check session data
        assert(isset($_SESSION['user_id']), "Session should contain user_id");
        assert(isset($_SESSION['username']), "Session should contain username");
        assert($_SESSION['username'] === 'testuser', "Session username should match");
        assert(isset($_SESSION['csrf_token']), "Session should contain CSRF token");
        
        echo "✓ Login test passed\n";
    }
    
    public function testLoginFailure() {
        echo "Testing login failure scenarios...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'testpassword123');
        
        // Test wrong password
        $result = $this->auth->login('testuser', 'wrongpassword');
        assert($result === false, "Login should fail with wrong password");
        assert($this->auth->isAuthenticated() === false, "User should not be authenticated");
        
        // Test non-existent user
        $result = $this->auth->login('nonexistent', 'testpassword123');
        assert($result === false, "Login should fail with non-existent user");
        assert($this->auth->isAuthenticated() === false, "User should not be authenticated");
        
        // Test empty credentials
        $result = $this->auth->login('', '');
        assert($result === false, "Login should fail with empty credentials");
        assert($this->auth->isAuthenticated() === false, "User should not be authenticated");
        
        echo "✓ Login failure test passed\n";
    }
    
    public function testLogout() {
        echo "Testing logout functionality...\n";
        
        // Create and login user
        $this->auth->createUser('testuser', 'testpassword123');
        $this->auth->login('testuser', 'testpassword123');
        
        // Verify user is logged in
        assert($this->auth->isAuthenticated() === true, "User should be authenticated before logout");
        
        // Logout
        $this->auth->logout();
        
        // Verify user is logged out
        assert($this->auth->isAuthenticated() === false, "User should not be authenticated after logout");
        assert(empty($_SESSION), "Session should be empty after logout");
        
        echo "✓ Logout test passed\n";
    }
    
    public function testCSRFToken() {
        echo "Testing CSRF token functionality...\n";
        
        // Create and login user
        $this->auth->createUser('testuser', 'testpassword123');
        $this->auth->login('testuser', 'testpassword123');
        
        // Generate CSRF token
        $token1 = $this->auth->generateCSRFToken();
        assert(!empty($token1), "CSRF token should not be empty");
        assert(strlen($token1) === 64, "CSRF token should be 64 characters long");
        
        // Generate again - should be same token
        $token2 = $this->auth->generateCSRFToken();
        assert($token1 === $token2, "CSRF token should be consistent within session");
        
        // Validate token
        assert($this->auth->validateCSRFToken($token1) === true, "Valid CSRF token should validate");
        assert($this->auth->validateCSRFToken('invalid') === false, "Invalid CSRF token should not validate");
        assert($this->auth->validateCSRFToken('') === false, "Empty CSRF token should not validate");
        
        echo "✓ CSRF token test passed\n";
    }
    
    public function testGetCurrentUser() {
        echo "Testing get current user functionality...\n";
        
        // Test when not authenticated
        $user = $this->auth->getCurrentUser();
        assert($user === null, "Should return null when not authenticated");
        
        // Create and login user
        $this->auth->createUser('testuser', 'testpassword123');
        $this->auth->login('testuser', 'testpassword123');
        
        // Test when authenticated
        $user = $this->auth->getCurrentUser();
        assert($user !== null, "Should return user data when authenticated");
        assert($user['username'] === 'testuser', "Username should match");
        assert(isset($user['id']), "User data should contain ID");
        assert(isset($user['created_at']), "User data should contain created_at");
        
        echo "✓ Get current user test passed\n";
    }
    
    public function testChangePassword() {
        echo "Testing password change functionality...\n";
        
        // Create test user
        $this->auth->createUser('testuser', 'oldpassword123');
        $user = $this->db->queryOne("SELECT id FROM admin_users WHERE username = ?", ['testuser']);
        
        // Change password
        $result = $this->auth->changePassword($user['id'], 'newpassword123');
        assert($result === true, "Password change should succeed");
        
        // Test login with old password (should fail)
        $loginResult = $this->auth->login('testuser', 'oldpassword123');
        assert($loginResult === false, "Login with old password should fail");
        
        // Test login with new password (should succeed)
        $loginResult = $this->auth->login('testuser', 'newpassword123');
        assert($loginResult === true, "Login with new password should succeed");
        
        // Test password length validation
        try {
            $this->auth->changePassword($user['id'], '123');
            assert(false, "Should throw exception for short password");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'characters long') !== false, "Should mention password length");
        }
        
        echo "✓ Password change test passed\n";
    }
    
    public function runAllTests() {
        echo "Running AuthManager tests...\n\n";
        
        $tests = [
            'testCreateUser',
            'testCreateUserValidation',
            'testLogin',
            'testLoginFailure',
            'testLogout',
            'testCSRFToken',
            'testGetCurrentUser',
            'testChangePassword'
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
    $test = new AuthManagerTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}