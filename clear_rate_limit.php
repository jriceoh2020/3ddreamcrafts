<?php
/**
 * Clear Rate Limiting
 * Removes all login attempt records to reset rate limiting
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Clear Rate Limiting ===\n\n";

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

try {
    $db = DatabaseManager::getInstance();

    // Check if login_attempts table exists
    if (!$db->tableExists('login_attempts')) {
        echo "✓ No login_attempts table found (nothing to clear)\n";
        exit(0);
    }

    // Show current failed attempts
    echo "Current failed login attempts:\n";
    echo str_repeat("-", 70) . "\n";

    $attempts = $db->query(
        "SELECT ip_address, username, COUNT(*) as count, MAX(attempt_time) as last_attempt
         FROM login_attempts
         WHERE success = 0
         GROUP BY ip_address, username
         ORDER BY last_attempt DESC"
    );

    if (empty($attempts)) {
        echo "No failed attempts found.\n\n";
    } else {
        foreach ($attempts as $attempt) {
            echo sprintf(
                "IP: %-15s | Username: %-20s | Attempts: %d | Last: %s\n",
                $attempt['ip_address'],
                $attempt['username'],
                $attempt['count'],
                $attempt['last_attempt']
            );
        }
        echo str_repeat("-", 70) . "\n\n";
    }

    // Clear all login attempts
    echo "Clearing all login attempts...\n";
    $deleted = $db->execute("DELETE FROM login_attempts");
    echo "✓ Cleared {$deleted} login attempt records\n\n";

    // Also clear security log entries related to rate limiting
    if ($db->tableExists('security_log')) {
        $secDeleted = $db->execute(
            "DELETE FROM security_log WHERE event_type IN ('rate_limit_exceeded', 'login_failure')"
        );
        echo "✓ Cleared {$secDeleted} security log entries\n\n";
    }

    echo "Rate limiting cleared! You can now try logging in again.\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
