<?php
/**
 * Admin Logout Page
 * Handles user logout and session cleanup
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = AuthManager::getInstance();

// Perform logout
$auth->logout();

// Redirect to login page with success message
header('Location: /admin/login.php?logged_out=1');
exit;
?>