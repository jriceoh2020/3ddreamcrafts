<?php
/**
 * Unit Tests for ConfigManager Class
 * Tests configuration management and database integration
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class ConfigManagerTest {
    private $testDbPath;
    
    public function __construct() {
        $this->testDbPath = __DIR__ . '/test_config.db';
    }
    
    /**
     * Set up test environment
     */
    public function setUp() {
        // Remove existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Create test database with settings table
        $this->createTestDatabase();
        
        // Reset ConfigManager singleton
        $reflection = new ReflectionClass('ConfigManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
    
    /**
     * Clean up test environment
     */
    public function tearDown() {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }
    
    /**
     * Create test database
     */
    private function createTestDatabase() {
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert test settings
        $pdo->exec("INSERT INTO settings (setting_name, setting_value) VALUES ('test_setting', 'test_value')");
        $pdo->exec("INSERT INTO settings (setting_name, setting_value) VALUES ('site_title', 'Test Site')");
    }
    
    /**
     * Test singleton pattern
     */
    public function testSingleton() {
        $instance1 = ConfigManager::getInstance();
        $instance2 = ConfigManager::getInstance();
        
        $this->assertTrue($instance1 === $instance2, "ConfigManager singleton pattern failed");
        echo "✓ ConfigManager singleton test passed\n";
    }
    
    /**
     * Test default settings loading
     */
    public function testDefaultSettings() {
        $config = ConfigManager::getInstance();
        
        // Test getting default values when database is not available
        $siteTitle = $config->get('site_title', 'Default Title');
        $this->assertTrue(!empty($siteTitle), "Default site title should not be empty");
        
        $nonExistent = $config->get('non_existent_setting', 'default_value');
        $this->assertTrue($nonExistent === 'default_value', "Should return default value for non-existent setting");
        
        echo "✓ Default settings test passed\n";
    }
    
    /**
     * Test configuration constants
     */
    public function testConstants() {
        $this->assertTrue(defined('DB_PATH'), "DB_PATH constant should be defined");
        $this->assertTrue(defined('UPLOAD_PATH'), "UPLOAD_PATH constant should be defined");
        $this->assertTrue(defined('MAX_UPLOAD_SIZE'), "MAX_UPLOAD_SIZE constant should be defined");
        $this->assertTrue(defined('SESSION_TIMEOUT'), "SESSION_TIMEOUT constant should be defined");
        $this->assertTrue(defined('SITE_NAME'), "SITE_NAME constant should be defined");
        
        // Test constant values
        $this->assertTrue(MAX_UPLOAD_SIZE === 5 * 1024 * 1024, "MAX_UPLOAD_SIZE should be 5MB");
        $this->assertTrue(SESSION_TIMEOUT === 3600, "SESSION_TIMEOUT should be 3600 seconds");
        $this->assertTrue(SITE_NAME === '3DDreamCrafts', "SITE_NAME should be '3DDreamCrafts'");
        
        echo "✓ Configuration constants test passed\n";
    }
    
    /**
     * Test allowed image types configuration
     */
    public function testAllowedImageTypes() {
        $this->assertTrue(defined('ALLOWED_IMAGE_TYPES'), "ALLOWED_IMAGE_TYPES should be defined");
        
        $allowedTypes = ALLOWED_IMAGE_TYPES;
        $this->assertTrue(is_array($allowedTypes), "ALLOWED_IMAGE_TYPES should be an array");
        $this->assertTrue(in_array('jpg', $allowedTypes), "jpg should be in allowed types");
        $this->assertTrue(in_array('png', $allowedTypes), "png should be in allowed types");
        $this->assertTrue(in_array('gif', $allowedTypes), "gif should be in allowed types");
        
        echo "✓ Allowed image types test passed\n";
    }
    
    /**
     * Test timezone configuration
     */
    public function testTimezoneConfiguration() {
        $this->assertTrue(defined('TIMEZONE'), "TIMEZONE constant should be defined");
        
        $currentTimezone = date_default_timezone_get();
        $this->assertTrue($currentTimezone === TIMEZONE, "Timezone should be set correctly");
        
        echo "✓ Timezone configuration test passed\n";
    }
    
    /**
     * Test debug mode configuration
     */
    public function testDebugModeConfiguration() {
        $this->assertTrue(defined('DEBUG_MODE'), "DEBUG_MODE constant should be defined");
        
        // Test that error reporting is configured based on debug mode
        if (DEBUG_MODE) {
            $this->assertTrue(error_reporting() !== 0, "Error reporting should be enabled in debug mode");
        } else {
            $this->assertTrue(error_reporting() === 0, "Error reporting should be disabled in production mode");
        }
        
        echo "✓ Debug mode configuration test passed\n";
    }
    
    /**
     * Simple assertion helper
     */
    private function assertTrue($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: " . $message);
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running ConfigManager Tests...\n\n";
        
        try {
            $this->setUp();
            
            $this->testSingleton();
            $this->testDefaultSettings();
            $this->testConstants();
            $this->testAllowedImageTypes();
            $this->testTimezoneConfiguration();
            $this->testDebugModeConfiguration();
            
            echo "\n✅ All ConfigManager tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        } finally {
            $this->tearDown();
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ConfigManagerTest();
    $test->runAllTests();
}