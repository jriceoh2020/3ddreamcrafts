<?php
/**
 * Change Admin Password
 * Interactive script to change admin user password
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Change Admin Password ===\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

try {
    $db = DatabaseManager::getInstance();
    $auth = AuthManager::getInstance();

    // Get username
    echo "Enter username (default: admin): ";
    $username = trim(fgets(STDIN));
    if (empty($username)) {
        $username = 'admin';
    }

    // Check if user exists
    $user = $db->queryOne("SELECT id, username FROM admin_users WHERE username = ?", [$username]);

    if (!$user) {
        echo "✗ Error: User '{$username}' not found!\n";
        exit(1);
    }

    echo "✓ Found user: {$user['username']} (ID: {$user['id']})\n\n";

    // Get new password
    echo "Enter new password (min 8 characters): ";
    $password1 = trim(fgets(STDIN));

    if (strlen($password1) < PASSWORD_MIN_LENGTH) {
        echo "✗ Error: Password must be at least " . PASSWORD_MIN_LENGTH . " characters\n";
        exit(1);
    }

    echo "Confirm new password: ";
    $password2 = trim(fgets(STDIN));

    if ($password1 !== $password2) {
        echo "✗ Error: Passwords do not match!\n";
        exit(1);
    }

    // Change password
    echo "\nChanging password...\n";

    $passwordHash = password_hash($password1, PASSWORD_DEFAULT);
    $affected = $db->execute(
        "UPDATE admin_users SET password_hash = ? WHERE id = ?",
        [$passwordHash, $user['id']]
    );

    if ($affected > 0) {
        echo "✓ Password changed successfully for user '{$username}'!\n\n";
        echo "You can now login with your new password.\n";
    } else {
        echo "✗ Error: Failed to update password\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
