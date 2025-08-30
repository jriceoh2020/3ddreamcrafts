<?php
/**
 * Error Handler System
 * Comprehensive error handling with logging and user-friendly error pages
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

/**
 * Error Handler Class
 * Manages error logging, display, and security event tracking
 */
class ErrorHandler {
    private static $instance = null;
    private $securityManager;
    private $logFile;
    
    private function __construct() {
        $this->securityManager = SecurityManager::getInstance();
        $this->logFile = __DIR__ . '/../logs/error.log';
        $this->ensureLogDirectory();
        $this->registerHandlers();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register error and exception handlers
     */
    private function registerHandlers() {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($severity, $message, $file, $line) {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = $this->getErrorType($severity);
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errorType,
            $message,
            $file,
            $line
        );
        
        // Log the error
        $this->logError($logMessage, $severity);
        
        // Log security event for critical errors
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            $this->securityManager->logSecurityEvent(
                'php_error',
                null,
                null,
                "Error: $message in $file:$line",
                'error'
            );
        }
        
        // Display error in debug mode, otherwise show generic message
        if (DEBUG_MODE) {
            echo "<div style='background: #fee; border: 1px solid #fcc; padding: 10px; margin: 10px; border-radius: 5px;'>";
            echo "<strong>$errorType:</strong> $message<br>";
            echo "<small>File: $file, Line: $line</small>";
            echo "</div>";
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handleException($exception) {
        $logMessage = sprintf(
            "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // Log the exception
        $this->logError($logMessage, E_ERROR);
        
        // Log security event
        $this->securityManager->logSecurityEvent(
            'uncaught_exception',
            null,
            null,
            $exception->getMessage(),
            'error'
        );
        
        // Show error page
        $this->showErrorPage(500, 'Internal Server Error', 'An unexpected error occurred. Please try again later.');
    }
    
    /**
     * Handle fatal errors
     */
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $logMessage = sprintf(
                "[%s] Fatal Error: %s in %s on line %d",
                date('Y-m-d H:i:s'),
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            // Log the fatal error
            $this->logError($logMessage, E_ERROR);
            
            // Log security event
            $this->securityManager->logSecurityEvent(
                'fatal_error',
                null,
                null,
                $error['message'],
                'critical'
            );
            
            // Show error page if headers not sent
            if (!headers_sent()) {
                $this->showErrorPage(500, 'Internal Server Error', 'A critical error occurred. Please contact support.');
            }
        }
    }
    
    /**
     * Log error to file
     */
    private function logError($message, $severity) {
        $logEntry = $message . "\n";
        
        // Add request information for context
        if (isset($_SERVER['REQUEST_URI'])) {
            $logEntry .= "Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
        }
        
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $logEntry .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
        }
        
        $logEntry .= "IP: " . $this->securityManager->getClientIP() . "\n";
        $logEntry .= str_repeat('-', 80) . "\n";
        
        // Write to log file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to system error log for critical errors
        if ($severity & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)) {
            error_log($message);
        }
    }
    
    /**
     * Get human-readable error type
     */
    private function getErrorType($severity) {
        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        return $errorTypes[$severity] ?? 'Unknown Error';
    }
    
    /**
     * Show user-friendly error page
     */
    public function showErrorPage($code, $title, $message, $showBackLink = true) {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=UTF-8');
        }
        
        $isAdmin = isAdminRequest();
        $backUrl = $isAdmin ? '/admin/' : '/';
        $backText = $isAdmin ? 'Back to Admin' : 'Back to Home';
        
        echo $this->getErrorPageHtml($code, $title, $message, $showBackLink, $backUrl, $backText);
        exit;
    }
    
    /**
     * Get error page HTML
     */
    private function getErrorPageHtml($code, $title, $message, $showBackLink, $backUrl, $backText) {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title - Error $code</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 20px;
            line-height: 1;
        }
        
        .error-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }
        
        .error-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .error-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .error-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 30px 20px;
            }
            
            .error-code {
                font-size: 48px;
            }
            
            .error-title {
                font-size: 20px;
            }
            
            .error-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <div class="error-code">$code</div>
        <div class="error-title">$title</div>
        <div class="error-message">$message</div>
        
        <div class="error-actions">
HTML;
        
        if ($showBackLink) {
            $html .= "<a href=\"$backUrl\" class=\"btn btn-primary\">$backText</a>";
        }
        
        $html .= <<<HTML
            <button onclick="history.back()" class="btn btn-secondary">Go Back</button>
            <button onclick="location.reload()" class="btn btn-secondary">Retry</button>
        </div>
    </div>
    
    <script>
        // Auto-retry for temporary errors after 5 seconds
        if ($code >= 500 && $code < 600) {
            setTimeout(function() {
                if (confirm('Would you like to retry automatically?')) {
                    location.reload();
                }
            }, 5000);
        }
    </script>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Handle application-specific errors
     */
    public function handleApplicationError($type, $message, $code = 400) {
        $this->securityManager->logSecurityEvent(
            'application_error',
            null,
            null,
            "$type: $message",
            'warning'
        );
        
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            429 => 'Too Many Requests'
        ];
        
        $title = $titles[$code] ?? 'Application Error';
        $this->showErrorPage($code, $title, $message);
    }
    
    /**
     * Handle validation errors
     */
    public function handleValidationError($errors) {
        if (is_array($errors)) {
            $message = "Please correct the following errors:\n• " . implode("\n• ", $errors);
        } else {
            $message = $errors;
        }
        
        $this->handleApplicationError('validation', $message, 422);
    }
    
    /**
     * Handle security violations
     */
    public function handleSecurityViolation($type, $details = '') {
        $this->securityManager->logSecurityEvent(
            'security_violation',
            null,
            null,
            "$type: $details",
            'critical'
        );
        
        $this->showErrorPage(403, 'Access Denied', 'Security violation detected. This incident has been logged.');
    }
}

/**
 * Initialize error handler
 */
function initializeErrorHandler() {
    return ErrorHandler::getInstance();
}

/**
 * Show 404 error page
 */
function show404() {
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->showErrorPage(404, 'Page Not Found', 'The requested page could not be found.');
}

/**
 * Show 403 error page
 */
function show403($message = 'You do not have permission to access this resource.') {
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->showErrorPage(403, 'Access Forbidden', $message);
}

/**
 * Show 500 error page
 */
function show500($message = 'An internal server error occurred. Please try again later.') {
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->showErrorPage(500, 'Internal Server Error', $message);
}

/**
 * Handle rate limiting error
 */
function showRateLimitError() {
    $errorHandler = ErrorHandler::getInstance();
    $errorHandler->showErrorPage(429, 'Too Many Requests', 'Too many login attempts. Please try again later.');
}