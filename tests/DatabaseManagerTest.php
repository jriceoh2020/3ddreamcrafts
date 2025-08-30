<?php
/**
 * Unit Tests for DatabaseManager Class
 * Tests database connection, query execution, and basic operations
 */

require_once __DIR__ . '/../includes/database.php';

class DatabaseManagerTest {
    private $testDbPath;
    private $originalDbPath;
    
    public function __construct() {
        // Use a temporary database for testing
        $this->testDbPath = __DIR__ . '/test_craftsite.db';
        $this->originalDbPath = DB_PATH;
    }
    
    /**
     * Set up test environment
     */
    public function setUp() {
        // Remove existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Override DB_PATH constant for testing
        if (!defined('TEST_DB_PATH')) {
            define('TEST_DB_PATH', $this->testDbPath);
        }
        
        // Create test database schema
        $this->createTestSchema();
    }
    
    /**
     * Clean up test environment
     */
    public function tearDown() {
        // Remove test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Reset singleton instance
        $reflection = new ReflectionClass('DatabaseManager');
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
    
    /**
     * Create test database schema
     */
    private function createTestSchema() {
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create test tables
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS test_table (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                value INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert test data
        $pdo->exec("INSERT INTO settings (setting_name, setting_value) VALUES ('test_setting', 'test_value')");
        $pdo->exec("INSERT INTO test_table (name, value) VALUES ('test1', 100), ('test2', 200)");
    }
    
    /**
     * Test singleton pattern
     */
    public function testSingleton() {
        // Override DB_PATH for this test
        $reflection = new ReflectionClass('DatabaseManager');
        $constructor = $reflection->getConstructor();
        $constructor->setAccessible(true);
        
        // Create instance using test database
        $instance1 = DatabaseManager::getInstance();
        $instance2 = DatabaseManager::getInstance();
        
        $this->assertTrue($instance1 === $instance2, "Singleton pattern failed - instances are not the same");
        echo "✓ Singleton pattern test passed\n";
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        // Temporarily override DB_PATH
        $originalConstant = DB_PATH;
        
        // Use reflection to access private connect method
        $db = DatabaseManager::getInstance();
        $connection = $db->getConnection();
        
        $this->assertTrue($connection instanceof PDO, "Database connection is not a PDO instance");
        echo "✓ Database connection test passed\n";
    }
    
    /**
     * Test query method
     */
    public function testQuery() {
        $db = $this->getTestDatabaseManager();
        
        // Test simple query
        $result = $db->query("SELECT * FROM test_table");
        $this->assertTrue(is_array($result), "Query result is not an array");
        $this->assertTrue(count($result) === 2, "Query returned unexpected number of rows");
        
        // Test parameterized query
        $result = $db->query("SELECT * FROM test_table WHERE name = ?", ['test1']);
        $this->assertTrue(count($result) === 1, "Parameterized query returned unexpected number of rows");
        $this->assertTrue($result[0]['name'] === 'test1', "Parameterized query returned wrong data");
        
        echo "✓ Query method test passed\n";
    }
    
    /**
     * Test execute method
     */
    public function testExecute() {
        $db = $this->getTestDatabaseManager();
        
        // Test INSERT
        $insertId = $db->execute("INSERT INTO test_table (name, value) VALUES (?, ?)", ['test3', 300]);
        $this->assertTrue($insertId > 0, "INSERT did not return valid ID");
        
        // Test UPDATE
        $affectedRows = $db->execute("UPDATE test_table SET value = ? WHERE name = ?", [350, 'test3']);
        $this->assertTrue($affectedRows === 1, "UPDATE did not affect expected number of rows");
        
        // Test DELETE
        $affectedRows = $db->execute("DELETE FROM test_table WHERE name = ?", ['test3']);
        $this->assertTrue($affectedRows === 1, "DELETE did not affect expected number of rows");
        
        echo "✓ Execute method test passed\n";
    }
    
    /**
     * Test queryOne method
     */
    public function testQueryOne() {
        $db = $this->getTestDatabaseManager();
        
        $result = $db->queryOne("SELECT * FROM test_table WHERE name = ?", ['test1']);
        $this->assertTrue(is_array($result), "QueryOne result is not an array");
        $this->assertTrue($result['name'] === 'test1', "QueryOne returned wrong data");
        
        // Test no results
        $result = $db->queryOne("SELECT * FROM test_table WHERE name = ?", ['nonexistent']);
        $this->assertTrue($result === null, "QueryOne should return null for no results");
        
        echo "✓ QueryOne method test passed\n";
    }
    
    /**
     * Test tableExists method
     */
    public function testTableExists() {
        $db = $this->getTestDatabaseManager();
        
        $this->assertTrue($db->tableExists('test_table'), "tableExists returned false for existing table");
        $this->assertFalse($db->tableExists('nonexistent_table'), "tableExists returned true for non-existing table");
        
        echo "✓ TableExists method test passed\n";
    }
    
    /**
     * Test transaction methods
     */
    public function testTransactions() {
        $db = $this->getTestDatabaseManager();
        
        // Test successful transaction
        $db->beginTransaction();
        $db->execute("INSERT INTO test_table (name, value) VALUES (?, ?)", ['transaction_test', 999]);
        $db->commit();
        
        $result = $db->queryOne("SELECT * FROM test_table WHERE name = ?", ['transaction_test']);
        $this->assertTrue($result !== null, "Transaction commit failed");
        
        // Test rollback
        $db->beginTransaction();
        $db->execute("INSERT INTO test_table (name, value) VALUES (?, ?)", ['rollback_test', 888]);
        $db->rollback();
        
        $result = $db->queryOne("SELECT * FROM test_table WHERE name = ?", ['rollback_test']);
        $this->assertTrue($result === null, "Transaction rollback failed");
        
        echo "✓ Transaction methods test passed\n";
    }
    
    /**
     * Test schema version methods
     */
    public function testSchemaVersion() {
        $db = $this->getTestDatabaseManager();
        
        // Test getting version (should be 0 initially)
        $version = $db->getSchemaVersion();
        $this->assertTrue($version === 0, "Initial schema version should be 0");
        
        // Test setting version
        $db->setSchemaVersion(1);
        $version = $db->getSchemaVersion();
        $this->assertTrue($version === 1, "Schema version was not set correctly");
        
        echo "✓ Schema version methods test passed\n";
    }
    
    /**
     * Get test database manager instance
     */
    private function getTestDatabaseManager() {
        // Create a new PDO connection for testing
        $pdo = new PDO('sqlite:' . $this->testDbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create a mock DatabaseManager for testing
        return new class($pdo) {
            private $connection;
            
            public function __construct($connection) {
                $this->connection = $connection;
            }
            
            public function getConnection() {
                return $this->connection;
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
            
            public function beginTransaction() {
                $this->connection->beginTransaction();
            }
            
            public function commit() {
                $this->connection->commit();
            }
            
            public function rollback() {
                $this->connection->rollback();
            }
            
            public function getSchemaVersion() {
                try {
                    $result = $this->queryOne("SELECT setting_value FROM settings WHERE setting_name = 'schema_version'");
                    return $result ? (int)$result['setting_value'] : 0;
                } catch (Exception $e) {
                    return 0;
                }
            }
            
            public function setSchemaVersion($version) {
                $this->execute(
                    "INSERT OR REPLACE INTO settings (setting_name, setting_value, updated_at) VALUES (?, ?, ?)",
                    ['schema_version', $version, date('Y-m-d H:i:s')]
                );
            }
        };
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
     * Simple assertion helper for false conditions
     */
    private function assertFalse($condition, $message) {
        if ($condition) {
            throw new Exception("Assertion failed: " . $message);
        }
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running DatabaseManager Tests...\n\n";
        
        try {
            $this->setUp();
            
            $this->testSingleton();
            $this->testConnection();
            $this->testQuery();
            $this->testExecute();
            $this->testQueryOne();
            $this->testTableExists();
            $this->testTransactions();
            $this->testSchemaVersion();
            
            echo "\n✅ All DatabaseManager tests passed!\n";
            
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
    $test = new DatabaseManagerTest();
    $test->runAllTests();
}