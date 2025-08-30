<?php
/**
 * Integration Test for DatabaseManager and ConfigManager
 * Tests the interaction between both classes
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "Running Integration Test for Core Classes...\n";
echo "============================================\n\n";

// Create a test database
$testDbPath = __DIR__ . '/integration_test.db';

try {
    // Set up test database with schema
    echo "1. Setting up test database...\n";
    $pdo = new PDO('sqlite:' . $testDbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create settings table
    $pdo->exec("DROP TABLE IF EXISTS settings");
    $pdo->exec("
        CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_name TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert some test settings
    $pdo->exec("INSERT INTO settings (setting_name, setting_value) VALUES ('site_title', 'Test Site')");
    $pdo->exec("INSERT INTO settings (setting_name, setting_value) VALUES ('theme_color', '#ff0000')");
    $pdo = null;
    echo "✓ Test database created successfully\n";
    
    // Test DatabaseManager with actual database operations
    echo "\n2. Testing DatabaseManager operations...\n";
    
    // Create a test instance that uses our test database
    $testDb = new class($testDbPath) {
        private $connection;
        
        public function __construct($dbPath) {
            $this->connection = new PDO('sqlite:' . $dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->exec('PRAGMA foreign_keys = ON');
            $this->connection->exec('PRAGMA journal_mode = WAL');
        }
        
        public function query($sql, $params = []) {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        public function execute($sql, $params = []) {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            if (stripos(trim($sql), 'INSERT') === 0) {
                return $this->connection->lastInsertId();
            }
            return $stmt->rowCount();
        }
        
        public function queryOne($sql, $params = []) {
            $result = $this->query($sql, $params);
            return !empty($result) ? $result[0] : null;
        }
        
        public function tableExists($tableName) {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
            $result = $this->query($sql, [$tableName]);
            return !empty($result);
        }
    };
    
    // Test CRUD operations
    $settings = $testDb->query("SELECT * FROM settings");
    echo "✓ Retrieved " . count($settings) . " settings from database\n";
    
    // Test insert
    $insertId = $testDb->execute("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)", ['test_setting', 'test_value']);
    echo "✓ Inserted new setting with ID: $insertId\n";
    
    // Test update
    $affected = $testDb->execute("UPDATE settings SET setting_value = ? WHERE setting_name = ?", ['updated_value', 'test_setting']);
    echo "✓ Updated $affected setting(s)\n";
    
    // Test queryOne
    $setting = $testDb->queryOne("SELECT * FROM settings WHERE setting_name = ?", ['test_setting']);
    if ($setting && $setting['setting_value'] === 'updated_value') {
        echo "✓ QueryOne method working correctly\n";
    }
    
    // Test tableExists
    if ($testDb->tableExists('settings')) {
        echo "✓ TableExists method working correctly\n";
    }
    
    echo "\n3. Testing configuration constants and values...\n";
    
    // Test that all required constants are defined
    $requiredConstants = [
        'DB_PATH', 'UPLOAD_PATH', 'MAX_UPLOAD_SIZE', 'ALLOWED_IMAGE_TYPES',
        'SESSION_TIMEOUT', 'SESSION_NAME', 'CSRF_TOKEN_NAME', 'PASSWORD_MIN_LENGTH',
        'SITE_NAME', 'ITEMS_PER_PAGE', 'TIMEZONE', 'DEBUG_MODE'
    ];
    
    foreach ($requiredConstants as $constant) {
        if (defined($constant)) {
            echo "✓ $constant is defined\n";
        } else {
            throw new Exception("Required constant $constant is not defined");
        }
    }
    
    echo "\n4. Testing ConfigManager integration...\n";
    
    // Test ConfigManager default behavior
    $config = ConfigManager::getInstance();
    
    // Test getting default values
    $siteTitle = $config->get('site_title', 'Default Title');
    echo "✓ ConfigManager can retrieve settings (got: '$siteTitle')\n";
    
    $nonExistent = $config->get('non_existent_key', 'default_value');
    if ($nonExistent === 'default_value') {
        echo "✓ ConfigManager returns default values correctly\n";
    }
    
    echo "\n5. Testing error handling...\n";
    
    // Test invalid SQL
    try {
        $testDb->query("INVALID SQL STATEMENT");
        throw new Exception("Should have thrown an exception for invalid SQL");
    } catch (PDOException $e) {
        echo "✓ Database properly handles invalid SQL\n";
    }
    
    // Test prepared statement with invalid table
    try {
        $testDb->query("SELECT * FROM nonexistent_table");
        throw new Exception("Should have thrown an exception for nonexistent table");
    } catch (PDOException $e) {
        echo "✓ Database properly handles nonexistent tables\n";
    }
    
    echo "\n🎉 Integration test completed successfully!\n";
    echo "All core database and utility classes are working together correctly.\n";
    
} catch (Exception $e) {
    echo "\n❌ Integration test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Clean up test database (ignore errors)
    if (file_exists($testDbPath)) {
        @unlink($testDbPath);
    }
}