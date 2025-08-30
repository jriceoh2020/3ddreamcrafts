<?php
/**
 * Authentication System Verification
 * Quick verification that all authentication components are working
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

echo "Authentication System Verification\n";
echo "==================================\n\n";

try {
    $auth = AuthManager::getInstance();
    $db = DatabaseManager::getInstance();
    
    // Clean up any existing test user
    $db->execute("DELETE FROM admin_users WHERE username = 'verifyuser'");
    
    echo "1. Testing user creation...\n";
    $result = $auth->createUser('verifyuser', 'verifypass123');
    if ($result) {
        echo "   ✓ User created successfully\n";
    } else {
        echo "   ✗ User creation failed\n";
        exit(1);
    }
    
    echo "2. Testing login with correct credentials...\n";
    $result = $auth->login('verifyuser', 'verifypass123');
    if ($result && $auth->isAuthenticated()) {
        echo "   ✓ Login successful\n";
    } else {
        echo "   ✗ Login failed\n";
        exit(1);
    }
    
    echo "3. Testing CSRF token generation...\n";
    $token = $auth->generateCSRFToken();
    if (!empty($token) && strlen($token) === 64) {
        echo "   ✓ CSRF token generated: " . substr($token, 0, 16) . "...\n";
    } else {
        echo "   ✗ CSRF token generation failed\n";
        exit(1);
    }
    
    echo "4. Testing CSRF token validation...\n";
    if ($auth->validateCSRFToken($token)) {
        echo "   ✓ CSRF token validation successful\n";
    } else {
        echo "   ✗ CSRF token validation failed\n";
        exit(1);
    }
    
    echo "5. Testing current user retrieval...\n";
    $user = $auth->getCurrentUser();
    if ($user && $user['username'] === 'verifyuser') {
        echo "   ✓ Current user retrieved: " . $user['username'] . "\n";
    } else {
        echo "   ✗ Current user retrieval failed\n";
        exit(1);
    }
    
    echo "6. Testing logout...\n";
    $auth->logout();
    if (!$auth->isAuthenticated()) {
        echo "   ✓ Logout successful\n";
    } else {
        echo "   ✗ Logout failed\n";
        exit(1);
    }
    
    echo "7. Testing login with wrong password...\n";
    $result = $auth->login('verifyuser', 'wrongpassword');
    if (!$result && !$auth->isAuthenticated()) {
        echo "   ✓ Login correctly rejected wrong password\n";
    } else {
        echo "   ✗ Login incorrectly accepted wrong password\n";
        exit(1);
    }
    
    // Clean up
    $db->execute("DELETE FROM admin_users WHERE username = 'verifyuser'");
    
    echo "\n✓ All authentication system components verified successfully!\n";
    echo "\nAuthentication system features implemented:\n";
    echo "- Secure password hashing with password_hash()\n";
    echo "- Session management with regeneration and timeout\n";
    echo "- CSRF protection with token generation and validation\n";
    echo "- Login/logout functionality\n";
    echo "- User authentication state management\n";
    echo "- Admin login page with form validation\n";
    echo "- Admin dashboard with authentication requirement\n";
    echo "- Comprehensive test coverage\n";
    
} catch (Exception $e) {
    echo "✗ Verification failed: " . $e->getMessage() . "\n";
    exit(1);
}