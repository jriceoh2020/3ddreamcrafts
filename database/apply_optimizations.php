<?php
/**
 * Apply Database Optimizations
 * Applies indexes and performance optimizations to the database
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class DatabaseOptimizer {
    private $db;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Apply all database optimizations
     */
    public function applyOptimizations() {
        echo "Applying database optimizations...\n";
        echo str_repeat("=", 50) . "\n";
        
        try {
            $this->createIndexes();
            $this->optimizePragmas();
            $this->analyzeDatabase();
            
            echo "\n✅ Database optimizations applied successfully!\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Error applying optimizations: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Create performance indexes
     */
    private function createIndexes() {
        echo "Creating performance indexes...\n";
        
        $indexes = [
            'idx_featured_prints_active' => 
                "CREATE INDEX IF NOT EXISTS idx_featured_prints_active 
                 ON featured_prints(is_active, updated_at DESC)",
                 
            'idx_craft_shows_date_active' => 
                "CREATE INDEX IF NOT EXISTS idx_craft_shows_date_active 
                 ON craft_shows(is_active, event_date ASC)",
                 
            'idx_craft_shows_event_date' => 
                "CREATE INDEX IF NOT EXISTS idx_craft_shows_event_date 
                 ON craft_shows(event_date)",
                 
            'idx_news_published_date' => 
                "CREATE INDEX IF NOT EXISTS idx_news_published_date 
                 ON news_articles(is_published, published_date DESC)",
                 
            'idx_news_published_date_only' => 
                "CREATE INDEX IF NOT EXISTS idx_news_published_date_only 
                 ON news_articles(published_date DESC)",
                 
            'idx_settings_name' => 
                "CREATE INDEX IF NOT EXISTS idx_settings_name 
                 ON settings(setting_name)",
                 
            'idx_admin_users_username' => 
                "CREATE INDEX IF NOT EXISTS idx_admin_users_username 
                 ON admin_users(username)"
        ];
        
        foreach ($indexes as $name => $sql) {
            try {
                $this->db->execute($sql);
                echo "  ✓ Created index: $name\n";
            } catch (Exception $e) {
                echo "  ⚠ Warning creating index $name: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Optimize SQLite PRAGMA settings
     */
    private function optimizePragmas() {
        echo "\nOptimizing SQLite settings...\n";
        
        $pragmas = [
            'journal_mode = WAL' => 'Enable WAL mode for better concurrency',
            'synchronous = NORMAL' => 'Balance between safety and performance',
            'cache_size = 10000' => 'Increase cache size (10MB)',
            'temp_store = MEMORY' => 'Store temporary tables in memory',
            'mmap_size = 268435456' => 'Enable memory-mapped I/O (256MB)',
            'optimize' => 'Optimize query planner statistics'
        ];
        
        foreach ($pragmas as $pragma => $description) {
            try {
                $this->db->execute("PRAGMA $pragma");
                echo "  ✓ $description\n";
            } catch (Exception $e) {
                echo "  ⚠ Warning setting pragma $pragma: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * Analyze database for query optimization
     */
    private function analyzeDatabase() {
        echo "\nAnalyzing database for query optimization...\n";
        
        try {
            $this->db->execute("ANALYZE");
            echo "  ✓ Database analysis completed\n";
        } catch (Exception $e) {
            echo "  ⚠ Warning during analysis: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Get database statistics
     */
    public function getDatabaseStats() {
        echo "\nDatabase Statistics:\n";
        echo str_repeat("-", 30) . "\n";
        
        try {
            // Get table sizes
            $tables = ['featured_prints', 'craft_shows', 'news_articles', 'settings', 'admin_users'];
            
            foreach ($tables as $table) {
                $result = $this->db->queryOne("SELECT COUNT(*) as count FROM $table");
                $count = $result ? $result['count'] : 0;
                echo sprintf("  %-20s %d records\n", $table . ':', $count);
            }
            
            // Get database file size
            $dbPath = DB_PATH;
            if (file_exists($dbPath)) {
                $size = filesize($dbPath);
                $sizeFormatted = $this->formatBytes($size);
                echo sprintf("  %-20s %s\n", 'Database size:', $sizeFormatted);
            }
            
            // Get index information
            $indexes = $this->db->query("SELECT name FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%'");
            echo sprintf("  %-20s %d indexes\n", 'Custom indexes:', count($indexes));
            
        } catch (Exception $e) {
            echo "  Error getting statistics: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Format bytes for human reading
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Test query performance
     */
    public function testQueryPerformance() {
        echo "\nTesting query performance...\n";
        echo str_repeat("-", 30) . "\n";
        
        $queries = [
            'Featured Print' => "SELECT * FROM featured_prints WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1",
            'Upcoming Shows' => "SELECT * FROM craft_shows WHERE is_active = 1 AND event_date >= date('now') ORDER BY event_date ASC LIMIT 10",
            'Recent News' => "SELECT * FROM news_articles WHERE is_published = 1 ORDER BY published_date DESC LIMIT 5",
            'Settings Lookup' => "SELECT setting_value FROM settings WHERE setting_name = 'site_title'"
        ];
        
        foreach ($queries as $name => $sql) {
            $start = microtime(true);
            
            try {
                $result = $this->db->query($sql);
                $duration = microtime(true) - $start;
                $count = count($result);
                
                echo sprintf("  %-15s %.3fms (%d records)\n", $name . ':', $duration * 1000, $count);
            } catch (Exception $e) {
                echo sprintf("  %-15s ERROR: %s\n", $name . ':', $e->getMessage());
            }
        }
    }
}

// Run optimization if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $optimizer = new DatabaseOptimizer();
    
    $success = $optimizer->applyOptimizations();
    $optimizer->getDatabaseStats();
    $optimizer->testQueryPerformance();
    
    exit($success ? 0 : 1);
}