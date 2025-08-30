<?php
/**
 * Create Admin User Utility
 * Command-line script to create the first admin user
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow command line execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

echo "3DDreamCrafts Admin User Creation\n";
echo "=================================\n\n";

try {
    $auth = AuthManager::getInstance();
    
    // Get username
    echo "Enter admin username: ";
    $username = trim(fgets(STDIN));
    
    if (empty($username)) {
        die("Username cannot be empty.\n");
    }
    
    // Get password
    echo "Enter admin password (minimum " . PASSWORD_MIN_LENGTH . " characters): ";
    
    // Hide password input if possible
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $password = trim(fgets(STDIN));
    } else {
        // Unix/Linux/Mac
        system('stty -echo');
        $password = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    }
    
    if (empty($password)) {
        die("Password cannot be empty.\n");
    }
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        die("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.\n");
    }
    
    // Confirm password
    echo "Confirm admin password: ";
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $confirmPassword = trim(fgets(STDIN));
    } else {
        system('stty -echo');
        $confirmPassword = trim(fgets(STDIN));
        system('stty echo');
        echo "\n";
    }
    
    if ($password !== $confirmPassword) {
        die("Passwords do not match.\n");
    }
    
    // Create the user
    echo "\nCreating admin user...\n";
    
    $result = $auth->createUser($username, $password);
    
    if ($result) {
        echo "✓ Admin user '$username' created successfully!\n";
        echo "\nYou can now log in at: /admin/login.php\n";
    } else {
        echo "✗ Failed to create admin user.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}