<?php
/**
 * Login Debugging Script
 * Run this to diagnose login issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 3DDreamCrafts Login Debug ===\n\n";

// Include the necessary files
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $db = DatabaseManager::getInstance();

    // Check database connection
    echo "✓ Database connection successful\n";
    echo "  Database path: " . DB_PATH . "\n";
    echo "  File exists: " . (file_exists(DB_PATH) ? 'YES' : 'NO') . "\n";
    echo "  File permissions: " . substr(sprintf('%o', fileperms(DB_PATH)), -4) . "\n\n";

    // Check if admin_users table exists
    if (!$db->tableExists('admin_users')) {
        echo "✗ ERROR: admin_users table does not exist!\n";
        echo "  Run: php database/init_database.php\n\n";
        exit(1);
    }

    echo "✓ admin_users table exists\n\n";

    // List all users
    $users = $db->query("SELECT id, username, created_at, last_login FROM admin_users");

    echo "Current users in database:\n";
    echo str_repeat("-", 70) . "\n";

    if (empty($users)) {
        echo "✗ NO USERS FOUND!\n\n";
        echo "Creating default admin user...\n";

        // Create admin user
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $userId = $db->execute(
            "INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)",
            ['admin', $passwordHash, date('Y-m-d H:i:s')]
        );

        if ($userId) {
            echo "✓ Admin user created successfully!\n";
            echo "  Username: admin\n";
            echo "  Password: admin123\n\n";
        } else {
            echo "✗ Failed to create admin user\n\n";
            exit(1);
        }
    } else {
        foreach ($users as $user) {
            echo sprintf(
                "  ID: %d | Username: %s | Created: %s | Last Login: %s\n",
                $user['id'],
                $user['username'],
                $user['created_at'],
                $user['last_login'] ?? 'Never'
            );
        }
        echo str_repeat("-", 70) . "\n\n";
    }

    // Test password verification
    echo "Testing password verification:\n";
    echo str_repeat("-", 70) . "\n";

    $testUser = $db->queryOne("SELECT * FROM admin_users WHERE username = ?", ['admin']);

    if ($testUser) {
        echo "✓ User 'admin' found in database\n";

        // Test password
        $testPassword = 'admin123';
        $isValid = password_verify($testPassword, $testUser['password_hash']);

        if ($isValid) {
            echo "✓ Password 'admin123' verifies correctly\n";
            echo "  Hash algorithm: " . password_get_info($testUser['password_hash'])['algoName'] . "\n\n";
        } else {
            echo "✗ Password 'admin123' does NOT verify!\n";
            echo "  This suggests the password hash is incorrect.\n\n";

            echo "Resetting admin password to 'admin123'...\n";
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            $db->execute(
                "UPDATE admin_users SET password_hash = ? WHERE username = ?",
                [$newHash, 'admin']
            );
            echo "✓ Password reset complete. Try logging in now.\n\n";
        }
    } else {
        echo "✗ User 'admin' NOT found in database\n\n";
    }

    // Check login_attempts table
    echo "Checking login attempts:\n";
    echo str_repeat("-", 70) . "\n";

    if ($db->tableExists('login_attempts')) {
        $recentAttempts = $db->query(
            "SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 10"
        );

        if (!empty($recentAttempts)) {
            echo "Recent login attempts:\n";
            foreach ($recentAttempts as $attempt) {
                $status = $attempt['success'] ? 'SUCCESS' : 'FAILED';
                echo sprintf(
                    "  [%s] %s | %s | IP: %s\n",
                    $attempt['attempt_time'],
                    $status,
                    $attempt['username'],
                    $attempt['ip_address']
                );
            }
        } else {
            echo "No login attempts recorded yet.\n";
        }
    } else {
        echo "login_attempts table does not exist (will be created on first login)\n";
    }

    echo "\n";
    echo str_repeat("=", 70) . "\n";
    echo "Debug complete!\n\n";

    echo "Next steps:\n";
    echo "1. Try logging in with:\n";
    echo "   Username: admin\n";
    echo "   Password: admin123\n\n";
    echo "2. If still failing, check:\n";
    echo "   - Browser console for JavaScript errors\n";
    echo "   - Apache error log: /var/log/apache2/3ddreamcrafts_error.log\n";
    echo "   - PHP error log: /var/log/php_errors.log\n\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
