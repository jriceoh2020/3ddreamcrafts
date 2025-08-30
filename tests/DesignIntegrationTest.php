<?php
/**
 * Design System Integration Test
 * Tests the complete design customization workflow
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/design-backup.php';

class DesignIntegrationTest {
    private $db;
    private $adminManager;
    private $backupManager;
    private $testDbPath;
    
    public function __construct() {
        // Use a separate test database
        $this->testDbPath = __DIR__ . '/design_integration_test.db';
        
        // Clean up any existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Create test database
        $this->createTestDatabase();
        
        $this->db = DatabaseManager::getInstance();
        $this->adminManager = new AdminManager();
        $this->backupManager = new DesignBackupManager();
    }
    
    public function __destruct() {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }
    
    /**
     * Run complete integration test
     */
    public function runIntegrationTest() {
        echo "Running Design System Integration Test...\n";
        echo "==========================================\n\n";
        
        try {
            $this->testCompleteWorkflow();
            $this->testDynamicCSSIntegration();
            $this->testBackupWorkflow();
            $this->testErrorHandling();
            
            echo "\n✅ All Design System integration tests passed!\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Design System integration test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Test complete design customization workflow
     */
    private function testCompleteWorkflow() {
        echo "Testing complete design customization workflow...\n";
        
        // 1. Get initial settings
        $initialSettings = $this->adminManager->getSettings();
        assert(isset($initialSettings['theme_color']), "Initial theme color should exist");
        
        // 2. Update design settings
        $newSettings = [
            'theme_color' => '#8b5cf6',
            'accent_color' => '#f59e0b',
            'font_family' => 'Georgia, serif'
        ];
        
        $updateResult = $this->adminManager->updateSettings($newSettings);
        assert($updateResult === true, "Settings update should succeed");
        
        // 3. Verify settings were saved
        $updatedSettings = $this->adminManager->getSettings();
        assert($updatedSettings['theme_color'] === '#8b5cf6', "Theme color should be updated");
        assert($updatedSettings['accent_color'] === '#f59e0b', "Accent color should be updated");
        assert($updatedSettings['font_family'] === 'Georgia, serif', "Font family should be updated");
        
        // 4. Create backup of new settings
        $backupFilename = $this->backupManager->createBackup('workflow_test');
        assert($backupFilename !== false, "Backup creation should succeed");
        
        // 5. Change settings again
        $secondSettings = [
            'theme_color' => '#10b981',
            'accent_color' => '#f97316',
            'font_family' => 'Arial, sans-serif'
        ];
        
        $this->adminManager->updateSettings($secondSettings);
        
        // 6. Restore from backup
        $restoreResult = $this->backupManager->restoreBackup($backupFilename);
        assert($restoreResult === true, "Backup restore should succeed");
        
        // 7. Verify restoration
        // Give the system a moment to process the restore
        usleep(100000); // 100ms
        
        // Clear any cached settings
        $config = ConfigManager::getInstance();
        $reflection = new ReflectionClass($config);
        $loadedProperty = $reflection->getProperty('loaded');
        $loadedProperty->setAccessible(true);
        $loadedProperty->setValue($config, false);
        
        $restoredSettings = $this->adminManager->getSettings();
        
        // Debug output if assertion fails
        if ($restoredSettings['theme_color'] !== '#8b5cf6') {
            echo "DEBUG: Expected '#8b5cf6', got '{$restoredSettings['theme_color']}'\n";
            echo "DEBUG: All restored settings: " . json_encode($restoredSettings) . "\n";
        }
        
        assert($restoredSettings['theme_color'] === '#8b5cf6', "Theme color should be restored");
        
        echo "✓ Complete workflow test passed\n";
    }
    
    /**
     * Test dynamic CSS integration
     */
    private function testDynamicCSSIntegration() {
        echo "Testing dynamic CSS integration...\n";
        
        // Set specific test colors
        $testSettings = [
            'theme_color' => '#3b82f6',
            'accent_color' => '#ef4444',
            'font_family' => 'Roboto, sans-serif'
        ];
        
        $this->adminManager->updateSettings($testSettings);
        
        // Test that settings are available for CSS generation
        $settings = $this->adminManager->getSettings();
        assert($settings['theme_color'] === '#3b82f6', "CSS should get correct theme color");
        assert($settings['accent_color'] === '#ef4444', "CSS should get correct accent color");
        assert($settings['font_family'] === 'Roboto, sans-serif', "CSS should get correct font family");
        
        // Test color utility functions
        $lighterColor = $this->adjustBrightness('#3b82f6', 20);
        $darkerColor = $this->adjustBrightness('#3b82f6', -20);
        
        assert($lighterColor !== '#3b82f6', "Lighter color should be different");
        assert($darkerColor !== '#3b82f6', "Darker color should be different");
        
        $contrastColor = $this->getContrastColor('#3b82f6');
        assert(in_array($contrastColor, ['#ffffff', '#000000']), "Contrast color should be black or white");
        
        echo "✓ Dynamic CSS integration test passed\n";
    }
    
    /**
     * Test backup workflow
     */
    private function testBackupWorkflow() {
        echo "Testing backup workflow...\n";
        
        // Create multiple backups
        $backup1 = $this->backupManager->createBackup('test_backup_1');
        $backup2 = $this->backupManager->createBackup('test_backup_2');
        
        assert($backup1 !== false, "First backup should be created");
        assert($backup2 !== false, "Second backup should be created");
        
        // Get backup list
        $backupList = $this->backupManager->getBackupList();
        assert(count($backupList) >= 2, "Should have at least 2 backups");
        
        // Get backup details
        $details1 = $this->backupManager->getBackupDetails($backup1);
        assert($details1 !== null, "Should get backup details");
        assert($details1['name'] === 'test_backup_1', "Backup name should match");
        
        // Delete a backup
        $deleteResult = $this->backupManager->deleteBackup($backup2);
        assert($deleteResult === true, "Backup deletion should succeed");
        
        // Verify deletion
        $updatedList = $this->backupManager->getBackupList();
        $foundDeleted = false;
        foreach ($updatedList as $backup) {
            if ($backup['filename'] === $backup2) {
                $foundDeleted = true;
                break;
            }
        }
        assert($foundDeleted === false, "Deleted backup should not be in list");
        
        echo "✓ Backup workflow test passed\n";
    }
    
    /**
     * Test error handling
     */
    private function testErrorHandling() {
        echo "Testing error handling...\n";
        
        // Test invalid color validation
        $invalidColor = validateHexColor('invalid-color');
        assert($invalidColor === null, "Invalid color should return null");
        
        // Test valid color validation
        $validColor = validateHexColor('#ff0000');
        assert($validColor === '#ff0000', "Valid color should pass");
        
        // Test color without hash
        $colorWithoutHash = validateHexColor('ff0000');
        assert($colorWithoutHash === '#ff0000', "Color without hash should be fixed");
        
        // Test backup restore with non-existent file
        $restoreResult = $this->backupManager->restoreBackup('non_existent.json');
        assert($restoreResult === false, "Restoring non-existent backup should fail");
        
        // Test backup deletion with non-existent file
        $deleteResult = $this->backupManager->deleteBackup('non_existent.json');
        assert($deleteResult === false, "Deleting non-existent backup should fail");
        
        echo "✓ Error handling test passed\n";
    }
    
    /**
     * Helper function to adjust color brightness
     */
    private function adjustBrightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Helper function to get contrast color
     */
    private function getContrastColor($hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    /**
     * Create test database with required tables
     */
    private function createTestDatabase() {
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create settings table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default settings
        $pdo->exec("INSERT OR REPLACE INTO settings (setting_name, setting_value) VALUES ('site_title', '3DDreamCrafts')");
        $pdo->exec("INSERT OR REPLACE INTO settings (setting_name, setting_value) VALUES ('theme_color', '#2563eb')");
        $pdo->exec("INSERT OR REPLACE INTO settings (setting_name, setting_value) VALUES ('accent_color', '#dc2626')");
        $pdo->exec("INSERT OR REPLACE INTO settings (setting_name, setting_value) VALUES ('font_family', 'Arial, sans-serif')");
        
        $pdo = null;
    }
}

// Run test if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new DesignIntegrationTest();
    $success = $test->runIntegrationTest();
    exit($success ? 0 : 1);
}

?>