<?php
/**
 * Database Manager Class
 * Handles SQLite database connections and operations using singleton pattern
 */

require_once __DIR__ . '/config.php';

class DatabaseManager {
    private static $instance = null;
    private $connection = null;
    private $transactionLevel = 0;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
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
     * Establish database connection
     */
    private function connect() {
        try {
            // Ensure database directory exists
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            // Create PDO connection with SQLite
            $this->connection = new PDO('sqlite:' . DB_PATH);
            
            // Set error mode to exceptions
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign key constraints
            $this->connection->exec('PRAGMA foreign_keys = ON');
            
            // Set journal mode for better concurrency
            $this->connection->exec('PRAGMA journal_mode = WAL');
            
            // Set synchronous mode for better performance
            $this->connection->exec('PRAGMA synchronous = NORMAL');
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get the database connection
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Execute a SELECT query with prepared statements
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return array Result set
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Query failed", $sql, $params, $e);
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute an INSERT, UPDATE, or DELETE query with prepared statements
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows or last insert ID for INSERT
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            // Return last insert ID for INSERT statements
            if (stripos(trim($sql), 'INSERT') === 0) {
                return $this->connection->lastInsertId();
            }
            
            // Return affected rows for UPDATE/DELETE
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("Execute failed", $sql, $params, $e);
            throw new Exception("Database execute failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a single query and return first row
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return array|null First row or null if no results
     */
    public function queryOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * Check if a table exists
     * @param string $tableName Table name to check
     * @return bool True if table exists
     */
    public function tableExists($tableName) {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
        $result = $this->query($sql, [$tableName]);
        return !empty($result);
    }
    
    /**
     * Begin a database transaction
     */
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionLevel++;
    }
    
    /**
     * Commit a database transaction
     */
    public function commit() {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->connection->commit();
        }
    }
    
    /**
     * Rollback a database transaction
     */
    public function rollback() {
        if ($this->transactionLevel > 0) {
            $this->connection->rollback();
            $this->transactionLevel = 0;
        }
    }
    
    /**
     * Get database schema version
     */
    public function getSchemaVersion() {
        try {
            $result = $this->queryOne("SELECT setting_value FROM settings WHERE setting_name = 'schema_version'");
            return $result ? (int)$result['setting_value'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Set database schema version
     */
    public function setSchemaVersion($version) {
        $this->execute(
            "INSERT OR REPLACE INTO settings (setting_name, setting_value, updated_at) VALUES (?, ?, ?)",
            ['schema_version', $version, date('Y-m-d H:i:s')]
        );
    }
    
    /**
     * Log database errors
     */
    private function logError($message, $sql, $params, $exception) {
        if (DEBUG_MODE) {
            error_log(sprintf(
                "[DatabaseManager] %s - SQL: %s - Params: %s - Error: %s",
                $message,
                $sql,
                json_encode($params),
                $exception->getMessage()
            ));
        }
    }
    
    /**
     * Close database connection
     */
    public function close() {
        $this->connection = null;
    }
    
    /**
     * Destructor to ensure connection is closed
     */
    public function __destruct() {
        $this->close();
    }
}