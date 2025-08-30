<?php
/**
 * Security Initialization
 * Initialize security components for admin pages
 */

// Only initialize in web context, not CLI
if (!CLI_MODE) {
    require_once __DIR__ . '/error-handler.php';
    
    // Initialize error handler
    $errorHandler = initializeErrorHandler();
    
    // Initialize security manager
    $securityManager = SecurityManager::getInstance();
    
    // Clean old login attempts periodically (1% chance)
    if (rand(1, 100) === 1) {
        $securityManager->cleanOldLoginAttempts();
    }
}
?>