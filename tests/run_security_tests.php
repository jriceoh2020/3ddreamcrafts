<?php
/**
 * Security Test Runner
 * Runs comprehensive security tests for the craft vendor website
 */

require_once __DIR__ . '/SecurityTest.php';

echo "🔒 Starting Security Test Suite...\n";
echo "=====================================\n\n";

try {
    $securityTest = new SecurityTest();
    $securityTest->runAllTests();
    
    echo "\n🎉 All security tests completed successfully!\n";
    echo "The application has passed all security vulnerability checks.\n\n";
    
    echo "Security measures verified:\n";
    echo "• Input validation and sanitization\n";
    echo "• XSS (Cross-Site Scripting) protection\n";
    echo "• CSRF (Cross-Site Request Forgery) protection\n";
    echo "• SQL injection prevention\n";
    echo "• File upload security\n";
    echo "• Rate limiting for login attempts\n";
    echo "• Session security\n";
    echo "• Authentication security\n";
    echo "• Error handling and logging\n";
    echo "• Security event logging\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Security tests failed: " . $e->getMessage() . "\n";
    echo "Please review and fix security issues before deployment.\n";
    exit(1);
}

echo "✅ Security hardening implementation complete!\n";
?>