<?php
/**
 * Configuration Management System
 * Handles application-wide settings and constants
 */

// Database Configuration
define('DB_PATH', __DIR__ . '/../database/craftsite.db');

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../public/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_NAME', 'craftsite_admin');

// Security Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_RATE_LIMIT_WINDOW', 900); // 15 minutes in seconds
define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes

// Application Configuration
define('SITE_NAME', '3DDreamCrafts');
define('ITEMS_PER_PAGE', 10);
define('TIMEZONE', 'America/New_York');

// Error Reporting (adjust for production)
define('DEBUG_MODE', true);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Configure error reporting based on debug mode
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Initialize error handler (will be loaded when needed)
if (!defined('CLI_MODE')) {
    define('CLI_MODE', php_sapi_name() === 'cli');
}

/**
 * Configuration Manager Class
 * Handles dynamic configuration settings stored in database
 */
class ConfigManager {
    private static $instance = null;
    private $settings = [];
    private $loaded = false;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load settings from database
     */
    public function loadSettings() {
        if ($this->loaded) {
            return;
        }
        
        try {
            $db = DatabaseManager::getInstance();
            $result = $db->query("SELECT setting_name, setting_value FROM settings");
            
            foreach ($result as $row) {
                $this->settings[$row['setting_name']] = $row['setting_value'];
            }
            
            $this->loaded = true;
        } catch (Exception $e) {
            // If settings table doesn't exist yet, use defaults
            $this->loadDefaultSettings();
        }
    }
    
    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        $this->loadSettings();
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        
        try {
            $db = DatabaseManager::getInstance();
            $db->execute(
                "INSERT OR REPLACE INTO settings (setting_name, setting_value, updated_at) VALUES (?, ?, ?)",
                [$key, $value, date('Y-m-d H:i:s')]
            );
        } catch (Exception $e) {
            throw new Exception("Failed to save setting: " . $e->getMessage());
        }
    }
    
    /**
     * Load default settings
     */
    private function loadDefaultSettings() {
        $this->settings = [
            'site_title' => SITE_NAME,
            'theme_color' => '#2563eb',
            'accent_color' => '#dc2626',
            'font_family' => 'Arial, sans-serif',
            'items_per_page' => ITEMS_PER_PAGE,
            'maintenance_mode' => '0'
        ];
        $this->loaded = true;
    }
}
?>