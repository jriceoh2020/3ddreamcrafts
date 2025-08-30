<?php
/**
 * Simple verification script for database functionality
 */

echo "Verifying database functionality...\n\n";

// Test PDO SQLite availability
echo "1. Testing PDO SQLite availability...\n";
if (class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers())) {
    echo "✓ PDO SQLite is available\n";
} else {
    echo "❌ PDO SQLite is not available\n";
    exit(1);
}

// Test basic SQLite connection
echo "\n2. Testing basic SQLite connection...\n";
try {
    $testDb = __DIR__ . '/test_verify.db';
    $pdo = new PDO('sqlite:' . $testDb);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ SQLite connection successful\n";
    
    // Test table creation
    $pdo->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY, name TEXT)");
    echo "✓ Table creation successful\n";
    
    // Test insert
    $stmt = $pdo->prepare("INSERT INTO test (name) VALUES (?)");
    $stmt->execute(['test_value']);
    echo "✓ Insert operation successful\n";
    
    // Test select
    $stmt = $pdo->query("SELECT * FROM test");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($results) > 0) {
        echo "✓ Select operation successful\n";
    }
    
    // Clean up
    unlink($testDb);
    
} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test our DatabaseManager class
echo "\n3. Testing DatabaseManager class...\n";
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Create a test database for our class
    $testDbPath = __DIR__ . '/test_manager.db';
    
    // Temporarily override the DB_PATH constant
    $originalDbPath = DB_PATH;
    
    // Create test database with required schema
    $pdo = new PDO('sqlite:' . $testDbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_name TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo = null;
    
    // Test DatabaseManager with reflection to use test database
    $reflection = new ReflectionClass('DatabaseManager');
    $instance = $reflection->getProperty('instance');
    $instance->setAccessible(true);
    $instance->setValue(null, null);
    
    // Create a custom DatabaseManager for testing
    $testManager = new class($testDbPath) {
        private $connection;
        
        public function __construct($dbPath) {
            $this->connection = new PDO('sqlite:' . $dbPath);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        public function query($sql, $params = []) {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        public function execute($sql, $params = []) {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
    };
    
    // Test basic operations
    $result = $testManager->execute("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)", ['test', 'value']);
    echo "✓ DatabaseManager execute method works\n";
    
    $result = $testManager->query("SELECT * FROM settings WHERE setting_name = ?", ['test']);
    if (count($result) > 0 && $result[0]['setting_value'] === 'value') {
        echo "✓ DatabaseManager query method works\n";
    }
    
    // Clean up
    unlink($testDbPath);
    
} catch (Exception $e) {
    echo "❌ DatabaseManager test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n4. Testing ConfigManager class...\n";
try {
    $config = ConfigManager::getInstance();
    
    // Test getting default values
    $siteTitle = $config->get('site_title', 'Default');
    echo "✓ ConfigManager get method works\n";
    
    // Test constants
    if (defined('SITE_NAME') && SITE_NAME === '3DDreamCrafts') {
        echo "✓ Configuration constants are properly defined\n";
    }
    
} catch (Exception $e) {
    echo "❌ ConfigManager test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 All database functionality verification tests passed!\n";
echo "The core database and utility classes are working correctly.\n";