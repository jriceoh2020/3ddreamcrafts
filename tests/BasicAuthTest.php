<?php
/**
 * Basic Authentication Test
 * Simple functional tests for the authentication system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

class BasicAuthTest {
    private $db;
    private $auth;
    
    public function setUp() {
        // Initialize database and auth
        $this->db = DatabaseManager::getInstance();
        
        // Ensure admin_users table exists
        $this->createTablesIfNeeded();
        
        // Clear any existing test users
        $this->cleanupTestUsers();
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        // Create fresh auth instance
        $reflection = new ReflectionClass('AuthManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        
        $this->auth = AuthManager::getInstance();
    }
    
    public function tearDown() {
        // Clean up test users
        $this->cleanupTestUsers();
        
        // Clear session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }
    
    private function createTablesIfNeeded() {
        try {
            // Check if admin_users table exists
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='admin_users'");
            
            if (empty($result)) {
                // Create admin_users table
                $this->db->execute("
                    CREATE TABLE admin_users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        username TEXT UNIQUE NOT NULL,
                        password_hash TEXT NOT NULL,
                        last_login DATETIME,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                echo "Created admin_users table for testing\n";
            }
            
            // Check if settings table exists
            $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
            
            if (empty($result)) {
                // Create settings table
                $this->db->execute("
                    CREATE TABLE settings (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        setting_name TEXT UNIQUE NOT NULL,
                        setting_value TEXT,
                        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                echo "Created settings table for testing\n";
            }
        } catch (Exception $e) {
            echo "Error creating tables: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function cleanupTestUsers() {
        try {
            $this->db->execute("DELETE FROM admin_users WHERE username LIKE 'test%'");
        } catch (Exception $e) {
            // Ignore errors during cleanup
        }
    }
    
    public function testCreateUser() {
        echo "Testing user creation...\n";
        
        try {
            // Test successful user creation
            $result = $this->auth->createUser('testuser', 'testpassword123');
            assert($result === true, "User creation should succeed");
            
            // Verify user exists in database
            $user = $this->db->queryOne("SELECT * FROM admin_users WHERE username = ?", ['testuser']);
            assert($user !== null, "User should exist in database");
            assert($user['username'] === 'testuser', "Username should match");
            assert(password_verify('testpassword123', $user['password_hash']), "Password should be hashed correctly");
            
            echo "✓ User creation test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ User creation test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testLogin() {
        echo "Testing login functionality...\n";
        
        try {
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
            
            echo "✓ Login test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Login test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testLoginFailure() {
        echo "Testing login failure scenarios...\n";
        
        try {
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
            
            echo "✓ Login failure test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Login failure test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testLogout() {
        echo "Testing logout functionality...\n";
        
        try {
            // Create and login user
            $this->auth->createUser('testuser', 'testpassword123');
            $this->auth->login('testuser', 'testpassword123');
            
            // Verify user is logged in
            assert($this->auth->isAuthenticated() === true, "User should be authenticated before logout");
            
            // Logout
            $this->auth->logout();
            
            // Verify user is logged out
            assert($this->auth->isAuthenticated() === false, "User should not be authenticated after logout");
            
            echo "✓ Logout test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Logout test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testPasswordValidation() {
        echo "Testing password validation...\n";
        
        try {
            // Test short password
            try {
                $this->auth->createUser('testuser', '123');
                assert(false, "Should throw exception for short password");
            } catch (Exception $e) {
                assert(strpos($e->getMessage(), 'characters long') !== false, "Should mention password length");
            }
            
            // Test empty password
            try {
                $this->auth->createUser('testuser2', '');
                assert(false, "Should throw exception for empty password");
            } catch (Exception $e) {
                assert(strpos($e->getMessage(), 'required') !== false, "Should mention required fields");
            }
            
            echo "✓ Password validation test passed\n";
            return true;
        } catch (Exception $e) {
            echo "✗ Password validation test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function runAllTests() {
        echo "Running Basic Authentication tests...\n\n";
        
        $tests = [
            'testCreateUser',
            'testLogin',
            'testLoginFailure',
            'testLogout',
            'testPasswordValidation'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $this->setUp();
                if ($this->$test()) {
                    $passed++;
                } else {
                    $failed++;
                }
            } catch (Exception $e) {
                echo "✗ Test $test failed with exception: " . $e->getMessage() . "\n";
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
    $test = new BasicAuthTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}