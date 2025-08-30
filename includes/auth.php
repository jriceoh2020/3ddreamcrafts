<?php
/**
 * Authentication Manager Class
 * Handles user authentication, session management, and security features
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/content.php';

class AuthManager {
    private static $instance = null;
    private $db;
    private $securityManager;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->securityManager = SecurityManager::getInstance();
        $this->initializeSession();
    }
    
    /**
     * Get the singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize secure session configuration
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Skip session configuration in CLI mode for testing
            if (php_sapi_name() !== 'cli') {
                // Configure secure session settings
                ini_set('session.cookie_httponly', 1);
                ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
                ini_set('session.use_strict_mode', 1);
                ini_set('session.cookie_samesite', 'Strict');
                
                session_name(SESSION_NAME);
            }
            
            // Only start session if not in CLI mode or if headers haven't been sent
            if (php_sapi_name() === 'cli' || !headers_sent()) {
                session_start();
            }
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $this->regenerateSession();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                $this->regenerateSession();
            }
            
            // Check for session timeout
            $this->checkSessionTimeout();
        }
    }
    
    /**
     * Attempt to log in a user
     * @param string $username Username
     * @param string $password Plain text password
     * @return bool True if login successful
     */
    public function login($username, $password) {
        $ipAddress = $this->securityManager->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            // Validate input
            $username = validateTextInput($username, 0, 50);
            if (!$username || empty($password)) {
                $this->securityManager->recordLoginAttempt($ipAddress, $username ?: 'invalid', false, $userAgent);
                return false;
            }
            
            // Check rate limiting
            if ($this->securityManager->isRateLimited($ipAddress, $username)) {
                $this->securityManager->logSecurityEvent('rate_limit_exceeded', $ipAddress, null, "Username: $username", 'warning');
                throw new Exception('Rate limit exceeded');
            }
            
            // Check for suspicious activity
            $suspiciousActivity = $this->securityManager->checkSuspiciousActivity($ipAddress);
            if ($suspiciousActivity['rapid_requests'] || $suspiciousActivity['multiple_usernames']) {
                $this->securityManager->logSecurityEvent(
                    'suspicious_login_activity', 
                    $ipAddress, 
                    null, 
                    "Requests: {$suspiciousActivity['request_count']}, Usernames: {$suspiciousActivity['username_count']}", 
                    'warning'
                );
            }
            
            // Get user from database
            $user = $this->db->queryOne(
                "SELECT id, username, password_hash FROM admin_users WHERE username = ?",
                [$username]
            );
            
            if (!$user) {
                // Prevent timing attacks by still verifying a dummy hash
                password_verify($password, '$2y$10$dummy.hash.to.prevent.timing.attacks');
                $this->securityManager->recordLoginAttempt($ipAddress, $username, false, $userAgent);
                return false;
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->securityManager->recordLoginAttempt($ipAddress, $username, false, $userAgent);
                return false;
            }
            
            // Login successful - create session
            $this->regenerateSession();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            // Generate CSRF token
            $this->generateCSRFToken();
            
            // Record successful login
            $this->securityManager->recordLoginAttempt($ipAddress, $username, true, $userAgent);
            
            // Update last login time
            $this->db->execute(
                "UPDATE admin_users SET last_login = ? WHERE id = ?",
                [date('Y-m-d H:i:s'), $user['id']]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            
            // If it's a rate limit exception, handle it specially
            if (strpos($e->getMessage(), 'Rate limit') !== false) {
                return 'rate_limited';
            }
            
            return false;
        }
    }
    
    /**
     * Log out the current user
     */
    public function logout() {
        // Clear session data
        $_SESSION = [];
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Delete session cookie (only in web context)
            if (php_sapi_name() !== 'cli' && isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Destroy session
            session_destroy();
        }
    }
    
    /**
     * Check if user is authenticated
     * @return bool True if user is logged in
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['login_time']);
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     * @param string $redirectUrl URL to redirect to after login
     */
    public function requireAuth($redirectUrl = null) {
        if (!$this->isAuthenticated()) {
            if ($redirectUrl) {
                $_SESSION['redirect_after_login'] = $redirectUrl;
            }
            header('Location: /admin/login.php');
            exit;
        }
    }
    
    /**
     * Get current user information
     * @return array|null User data or null if not authenticated
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        try {
            return $this->db->queryOne(
                "SELECT id, username, last_login, created_at FROM admin_users WHERE id = ?",
                [$_SESSION['user_id']]
            );
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Create a new admin user
     * @param string $username Username
     * @param string $password Plain text password
     * @return bool True if user created successfully
     */
    public function createUser($username, $password) {
        try {
            // Validate input
            if (empty($username) || empty($password)) {
                throw new Exception("Username and password are required");
            }
            
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long");
            }
            
            // Check if username already exists
            $existing = $this->db->queryOne(
                "SELECT id FROM admin_users WHERE username = ?",
                [$username]
            );
            
            if ($existing) {
                throw new Exception("Username already exists");
            }
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $userId = $this->db->execute(
                "INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)",
                [$username, $passwordHash, date('Y-m-d H:i:s')]
            );
            
            return $userId > 0;
            
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Change user password
     * @param int $userId User ID
     * @param string $newPassword New plain text password
     * @return bool True if password changed successfully
     */
    public function changePassword($userId, $newPassword) {
        try {
            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                throw new Exception("Password must be at least " . PASSWORD_MIN_LENGTH . " characters long");
            }
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $affected = $this->db->execute(
                "UPDATE admin_users SET password_hash = ? WHERE id = ?",
                [$passwordHash, $userId]
            );
            
            return $affected > 0;
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Regenerate session ID for security
     */
    private function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['last_regeneration'] = time();
    }
    
    /**
     * Check for session timeout
     */
    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
                return;
            }
        }
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Get login redirect URL
     * @return string Redirect URL or default admin page
     */
    public function getLoginRedirectUrl() {
        $url = $_SESSION['redirect_after_login'] ?? '/admin/';
        unset($_SESSION['redirect_after_login']);
        return $url;
    }
}