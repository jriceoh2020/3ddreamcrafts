<?php
/**
 * Security Functions
 * Comprehensive security utilities for input validation, XSS protection, and rate limiting
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Security Manager Class
 * Handles comprehensive security features including rate limiting and logging
 */
class SecurityManager {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->initializeSecurityTables();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize security-related database tables
     */
    private function initializeSecurityTables() {
        try {
            // Create login attempts table for rate limiting
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    username TEXT,
                    success INTEGER DEFAULT 0,
                    attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                    user_agent TEXT
                )
            ");
            
            // Create security log table
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS security_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_type TEXT NOT NULL,
                    ip_address TEXT,
                    user_id INTEGER,
                    details TEXT,
                    severity TEXT DEFAULT 'info',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Create index for performance
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON login_attempts(ip_address, attempt_time)");
            $this->db->execute("CREATE INDEX IF NOT EXISTS idx_security_log_type_time ON security_log(event_type, created_at)");
            
        } catch (Exception $e) {
            error_log("Failed to initialize security tables: " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP is rate limited for login attempts
     * @param string $ipAddress IP address to check
     * @param string $username Username being attempted
     * @return bool True if rate limited
     */
    public function isRateLimited($ipAddress, $username = null) {
        try {
            $timeWindow = date('Y-m-d H:i:s', time() - LOGIN_RATE_LIMIT_WINDOW);
            
            // Count failed attempts in the time window
            $failedAttempts = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM login_attempts 
                 WHERE ip_address = ? AND success = 0 AND attempt_time > ?",
                [$ipAddress, $timeWindow]
            );
            
            return $failedAttempts['count'] >= MAX_LOGIN_ATTEMPTS;
            
        } catch (Exception $e) {
            $this->logSecurityEvent('rate_limit_check_error', $ipAddress, null, $e->getMessage(), 'error');
            return false; // Fail open for availability
        }
    }
    
    /**
     * Record login attempt
     * @param string $ipAddress IP address
     * @param string $username Username attempted
     * @param bool $success Whether login was successful
     * @param string $userAgent User agent string
     */
    public function recordLoginAttempt($ipAddress, $username, $success, $userAgent = '') {
        try {
            $this->db->execute(
                "INSERT INTO login_attempts (ip_address, username, success, user_agent) VALUES (?, ?, ?, ?)",
                [$ipAddress, $username, $success ? 1 : 0, $userAgent]
            );
            
            // Log security event
            $eventType = $success ? 'login_success' : 'login_failure';
            $this->logSecurityEvent($eventType, $ipAddress, null, "Username: $username", $success ? 'info' : 'warning');
            
        } catch (Exception $e) {
            error_log("Failed to record login attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Clean old login attempts (for maintenance)
     */
    public function cleanOldLoginAttempts() {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - (LOGIN_RATE_LIMIT_WINDOW * 2));
            $this->db->execute("DELETE FROM login_attempts WHERE attempt_time < ?", [$cutoffTime]);
        } catch (Exception $e) {
            error_log("Failed to clean old login attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Log security event
     * @param string $eventType Type of security event
     * @param string $ipAddress IP address
     * @param int $userId User ID (if applicable)
     * @param string $details Event details
     * @param string $severity Severity level (info, warning, error, critical)
     */
    public function logSecurityEvent($eventType, $ipAddress = null, $userId = null, $details = '', $severity = 'info') {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            
            $this->db->execute(
                "INSERT INTO security_log (event_type, ip_address, user_id, details, severity) VALUES (?, ?, ?, ?, ?)",
                [$eventType, $ipAddress, $userId, $details, $severity]
            );
            
            // Also log to system error log for critical events
            if ($severity === 'critical' || $severity === 'error') {
                error_log("SECURITY EVENT [$severity]: $eventType - IP: $ipAddress - Details: $details");
            }
            
        } catch (Exception $e) {
            error_log("Failed to log security event: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address (handles proxies)
     * @return string Client IP address
     */
    public function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check for suspicious activity patterns
     * @param string $ipAddress IP address to check
     * @return array Suspicious activity indicators
     */
    public function checkSuspiciousActivity($ipAddress) {
        try {
            $timeWindow = date('Y-m-d H:i:s', time() - 3600); // Last hour
            
            // Check for rapid requests
            $rapidRequests = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM login_attempts WHERE ip_address = ? AND attempt_time > ?",
                [$ipAddress, $timeWindow]
            );
            
            // Check for multiple usernames from same IP
            $multipleUsernames = $this->db->queryOne(
                "SELECT COUNT(DISTINCT username) as count FROM login_attempts 
                 WHERE ip_address = ? AND attempt_time > ?",
                [$ipAddress, $timeWindow]
            );
            
            return [
                'rapid_requests' => $rapidRequests['count'] > 20,
                'multiple_usernames' => $multipleUsernames['count'] > 5,
                'request_count' => $rapidRequests['count'],
                'username_count' => $multipleUsernames['count']
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent('suspicious_activity_check_error', $ipAddress, null, $e->getMessage(), 'error');
            return ['rapid_requests' => false, 'multiple_usernames' => false];
        }
    }
}

/**
 * Additional security and validation functions
 */

/**
 * Escape output for HTML context
 * @param string $string String to escape
 * @return string Escaped string
 */
function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape output for HTML attribute context
 * @param string $string String to escape
 * @return string Escaped string
 */
function escapeHtmlAttr($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape output for JavaScript context
 * @param string $string String to escape
 * @return string Escaped string
 */
function escapeJs($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Escape output for URL context
 * @param string $string String to escape
 * @return string Escaped string
 */
function escapeUrl($string) {
    return urlencode($string);
}

/**
 * Validate date input with enhanced security
 * @param string $date Date string
 * @param string $format Expected format
 * @return string|false Valid date or false
 */
function validateDateSecure($date, $format = 'Y-m-d') {
    // Remove null bytes and trim
    $date = str_replace("\0", '', trim($date));
    
    $dateObj = DateTime::createFromFormat($format, $date);
    
    if ($dateObj && $dateObj->format($format) === $date) {
        return $date;
    }
    
    return false;
}

/**
 * Validate CSRF token from request
 * @param array $request Request data ($_POST, $_GET, etc.)
 * @return bool True if valid
 */
function validateRequestCSRF($request) {
    $token = $request['csrf_token'] ?? '';
    return validateCSRFToken($token);
}

/**
 * Generate secure random token
 * @param int $length Token length in bytes
 * @return string Random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if request is from admin area
 * @return bool True if admin request
 */
function isAdminRequest() {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($requestUri, '/admin/') === 0;
}

/**
 * Validate file upload security
 * @param array $file $_FILES array element
 * @return array Validation result with 'valid' and 'error' keys
 */
function validateFileUpload($file) {
    $result = ['valid' => false, 'error' => ''];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $result['error'] = 'No file uploaded or invalid upload';
        return $result;
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $result['error'] = 'File size exceeds maximum allowed size';
        return $result;
    }
    
    // Check file type
    $extension = getFileExtension($file['name']);
    if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
        $result['error'] = 'File type not allowed';
        return $result;
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp'
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        $result['error'] = 'Invalid file type detected';
        return $result;
    }
    
    $result['valid'] = true;
    return $result;
}
?>