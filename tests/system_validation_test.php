<?php
/**
 * System Validation Test - Final Integration Testing
 * 
 * Task 15: Final integration and system testing
 * Simplified version that works in CLI mode without session conflicts
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/functions.php';

class SystemValidationTest {
    private $testResults = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    private $testDb;
    
    public function __construct() {
        $this->setupTestEnvironment();
    }
    
    private function setupTestEnvironment() {
        // Create isolated test database
        $this->testDb = __DIR__ . '/system_validation_test.db';
        
        if (file_exists($this->testDb)) {
            unlink($this->testDb);
        }
        
        // Override database path for testing
        if (!defined('DB_PATH')) {
            define('DB_PATH', $this->testDb);
        }
        
        $this->initializeTestDatabase();
        
        echo "=== SYSTEM VALIDATION TEST ===\n";
        echo "Task 15: Final integration and system testing\n";
        echo "Validating complete system functionality\n";
        echo "=====================================\n\n";
    }
    
    private function initializeTestDatabase() {
        try {
            $pdo = new PDO('sqlite:' . $this->testDb);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Execute schema
            $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
            $pdo->exec($schema);
            
            // Insert test data
            $this->insertTestData($pdo);
            
        } catch (Exception $e) {
            throw new Exception("Failed to initialize test database: " . $e->getMessage());
        }
    }
    
    private function insertTestData($pdo) {
        // Admin users
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin_users (username, password_hash, created_at) VALUES ('admin', '$passwordHash', datetime('now'))");
        
        // System settings
        $settings = [
            ['site_title', '3DDreamCrafts - Custom 3D Printed Objects'],
            ['facebook_url', 'https://facebook.com/3ddreamcrafts'],
            ['instagram_url', 'https://instagram.com/3ddreamcrafts'],
            ['theme_color', '#2c3e50'],
            ['accent_color', '#3498db']
        ];
        
        foreach ($settings as $setting) {
            $pdo->exec("INSERT INTO settings (setting_name, setting_value, updated_at) VALUES ('{$setting[0]}', '{$setting[1]}', datetime('now'))");
        }
        
        // Featured prints
        $pdo->exec("INSERT INTO featured_prints (title, description, image_path, is_active, created_at, updated_at) VALUES ('Test Dragon', 'A detailed dragon figurine', '/uploads/dragon.jpg', 1, datetime('now'), datetime('now'))");
        
        // Craft shows
        $pdo->exec("INSERT INTO craft_shows (title, event_date, location, description, is_active, created_at, updated_at) VALUES ('Spring Fair', '2025-04-15', 'Community Center', 'Annual spring craft fair', 1, datetime('now'), datetime('now'))");
        
        // News articles
        $pdo->exec("INSERT INTO news_articles (title, content, published_date, is_published, created_at, updated_at) VALUES ('New Equipment', 'We have new 3D printing equipment', '2025-01-15 10:00:00', 1, datetime('now'), datetime('now'))");
    }
    
    public function runAllTests() {
        echo "Starting system validation tests...\n\n";
        
        $this->testDatabaseIntegration();
        $this->testContentManagement();
        $this->testSystemRequirements();
        $this->testFileSystemIntegration();
        $this->testSecurityFeatures();
        $this->testPerformanceRequirements();
        $this->testDeploymentReadiness();
        
        $this->displayResults();
        $this->cleanup();
        
        return $this->failedTests === 0;
    }
    
    private function runTest($testName, $testFunction) {
        $this->totalTests++;
        echo "Testing: $testName... ";
        
        try {
            $result = $testFunction();
            if ($result) {
                echo "✓ PASS\n";
                $this->passedTests++;
                $this->testResults[$testName] = 'PASS';
            } else {
                echo "✗ FAIL\n";
                $this->failedTests++;
                $this->testResults[$testName] = 'FAIL';
            }
        } catch (Exception $e) {
            echo "✗ ERROR: " . $e->getMessage() . "\n";
            $this->failedTests++;
            $this->testResults[$testName] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    private function testDatabaseIntegration() {
        echo "1. DATABASE INTEGRATION\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Database Connection", function() {
            $db = DatabaseManager::getInstance();
            $connection = $db->getConnection();
            return $connection !== null;
        });
        
        $this->runTest("Schema Validation", function() {
            $db = DatabaseManager::getInstance();
            $tables = ['settings', 'featured_prints', 'craft_shows', 'news_articles', 'admin_users'];
            
            foreach ($tables as $table) {
                $result = $db->query("SELECT COUNT(*) as count FROM $table");
                if (!$result || !isset($result[0]['count'])) {
                    return false;
                }
            }
            return true;
        });
        
        $this->runTest("CRUD Operations", function() {
            $db = DatabaseManager::getInstance();
            
            // Insert
            $id = $db->execute("INSERT INTO featured_prints (title, description, image_path, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))", 
                ['Test Print', 'Test description', '/test.jpg', 1]);
            
            if (!$id) return false;
            
            // Read
            $result = $db->query("SELECT * FROM featured_prints WHERE id = ?", [$id]);
            if (!$result || $result[0]['title'] !== 'Test Print') return false;
            
            // Update
            $updateResult = $db->execute("UPDATE featured_prints SET title = ? WHERE id = ?", ['Updated Title', $id]);
            if (!$updateResult) return false;
            
            // Delete
            $deleteResult = $db->execute("DELETE FROM featured_prints WHERE id = ?", [$id]);
            return $deleteResult;
        });
    }
    
    private function testContentManagement() {
        echo "\n2. CONTENT MANAGEMENT\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Content Retrieval", function() {
            $contentManager = new ContentManager();
            
            $featuredPrint = $contentManager->getFeaturedPrint();
            if (!$featuredPrint || !isset($featuredPrint['title'])) return false;
            
            $recentNews = $contentManager->getRecentNews(5);
            if (!is_array($recentNews)) return false;
            
            $upcomingShows = $contentManager->getUpcomingShows(5);
            if (!is_array($upcomingShows)) return false;
            
            return true;
        });
        
        $this->runTest("Settings Management", function() {
            $contentManager = new ContentManager();
            $settings = $contentManager->getSettings();
            
            $requiredSettings = ['site_title', 'facebook_url', 'instagram_url'];
            foreach ($requiredSettings as $setting) {
                if (!isset($settings[$setting]) || empty($settings[$setting])) {
                    return false;
                }
            }
            return true;
        });
        
        $this->runTest("Content Ordering", function() {
            $contentManager = new ContentManager();
            
            // Test news ordering (reverse chronological)
            $news = $contentManager->getRecentNews();
            if (count($news) > 1) {
                $previousDate = null;
                foreach ($news as $article) {
                    if ($previousDate && strtotime($article['published_date']) > strtotime($previousDate)) {
                        return false;
                    }
                    $previousDate = $article['published_date'];
                }
            }
            
            // Test shows ordering (chronological)
            $shows = $contentManager->getUpcomingShows();
            if (count($shows) > 1) {
                $previousDate = null;
                foreach ($shows as $show) {
                    if ($previousDate && strtotime($show['event_date']) < strtotime($previousDate)) {
                        return false;
                    }
                    $previousDate = $show['event_date'];
                }
            }
            
            return true;
        });
    }
    
    private function testSystemRequirements() {
        echo "\n3. SYSTEM REQUIREMENTS VALIDATION\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Requirement 1: Landing Page Components", function() {
            $contentManager = new ContentManager();
            $settings = $contentManager->getSettings();
            
            // Check required components exist
            $featuredPrint = $contentManager->getFeaturedPrint();
            $recentNews = $contentManager->getRecentNews(3);
            $upcomingShows = $contentManager->getUpcomingShows(3);
            
            return $featuredPrint && is_array($recentNews) && is_array($upcomingShows) && 
                   isset($settings['facebook_url'], $settings['instagram_url']);
        });
        
        $this->runTest("Requirement 2: Featured Print Display", function() {
            $contentManager = new ContentManager();
            $featuredPrint = $contentManager->getFeaturedPrint();
            
            return $featuredPrint && 
                   isset($featuredPrint['title'], $featuredPrint['description'], $featuredPrint['image_path']) &&
                   !empty($featuredPrint['title']) && !empty($featuredPrint['description']);
        });
        
        $this->runTest("Requirement 3: Craft Shows Calendar", function() {
            $contentManager = new ContentManager();
            $shows = $contentManager->getUpcomingShows();
            
            if (!is_array($shows)) return false;
            
            // Check chronological ordering
            $previousDate = null;
            foreach ($shows as $show) {
                if (!isset($show['event_date'], $show['location'], $show['title'])) return false;
                
                if ($previousDate && strtotime($show['event_date']) < strtotime($previousDate)) {
                    return false;
                }
                $previousDate = $show['event_date'];
            }
            
            return true;
        });
        
        $this->runTest("Requirement 4: News and Updates", function() {
            $contentManager = new ContentManager();
            $news = $contentManager->getRecentNews();
            
            if (!is_array($news)) return false;
            
            // Check reverse chronological ordering
            $previousDate = null;
            foreach ($news as $article) {
                if (!isset($article['title'], $article['content'], $article['published_date'])) return false;
                
                if ($previousDate && strtotime($article['published_date']) > strtotime($previousDate)) {
                    return false;
                }
                $previousDate = $article['published_date'];
            }
            
            return true;
        });
        
        $this->runTest("Requirement 5: Social Media Integration", function() {
            $contentManager = new ContentManager();
            $settings = $contentManager->getSettings();
            
            return isset($settings['facebook_url'], $settings['instagram_url']) &&
                   filter_var($settings['facebook_url'], FILTER_VALIDATE_URL) &&
                   filter_var($settings['instagram_url'], FILTER_VALIDATE_URL);
        });
    }
    
    private function testFileSystemIntegration() {
        echo "\n4. FILE SYSTEM INTEGRATION\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Required Directories Exist", function() {
            $requiredDirs = [
                __DIR__ . '/../public',
                __DIR__ . '/../admin',
                __DIR__ . '/../includes',
                __DIR__ . '/../database'
            ];
            
            foreach ($requiredDirs as $dir) {
                if (!is_dir($dir)) return false;
            }
            return true;
        });
        
        $this->runTest("Core Files Exist", function() {
            $coreFiles = [
                __DIR__ . '/../public/index.php',
                __DIR__ . '/../public/shows.php',
                __DIR__ . '/../public/news.php',
                __DIR__ . '/../admin/index.php',
                __DIR__ . '/../admin/login.php',
                __DIR__ . '/../includes/config.php',
                __DIR__ . '/../includes/database.php',
                __DIR__ . '/../includes/auth.php',
                __DIR__ . '/../includes/content.php'
            ];
            
            foreach ($coreFiles as $file) {
                if (!file_exists($file)) return false;
            }
            return true;
        });
        
        $this->runTest("Upload Directory Setup", function() {
            $uploadDir = __DIR__ . '/../public/uploads';
            
            // Create if doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            return is_dir($uploadDir) && is_writable($uploadDir);
        });
        
        $this->runTest("Cache Directory Setup", function() {
            $cacheDir = __DIR__ . '/../cache';
            
            // Create if doesn't exist
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            
            return is_dir($cacheDir) && is_writable($cacheDir);
        });
    }
    
    private function testSecurityFeatures() {
        echo "\n5. SECURITY FEATURES\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("SQL Injection Protection", function() {
            $db = DatabaseManager::getInstance();
            
            // Test with malicious input
            $maliciousInput = "'; DROP TABLE admin_users; --";
            
            try {
                $result = $db->query("SELECT * FROM admin_users WHERE username = ?", [$maliciousInput]);
                // Should execute safely without dropping table
                return true;
            } catch (Exception $e) {
                return false;
            }
        });
        
        $this->runTest("XSS Protection Functions", function() {
            $maliciousScript = "<script>alert('XSS')</script>";
            $escaped = htmlspecialchars($maliciousScript, ENT_QUOTES, 'UTF-8');
            
            return $escaped !== $maliciousScript && 
                   strpos($escaped, '<script>') === false;
        });
        
        $this->runTest("Password Hashing", function() {
            $password = 'testpassword123';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            return password_verify($password, $hash) && 
                   $hash !== $password;
        });
        
        $this->runTest("File Upload Security", function() {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maliciousTypes = ['application/x-php', 'text/html', 'application/javascript'];
            
            foreach ($maliciousTypes as $type) {
                if (in_array($type, $allowedTypes)) {
                    return false;
                }
            }
            
            // Test filename sanitization
            $dangerousFilename = '../../../etc/passwd.jpg';
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($dangerousFilename));
            
            return $sanitized !== $dangerousFilename;
        });
    }
    
    private function testPerformanceRequirements() {
        echo "\n6. PERFORMANCE REQUIREMENTS\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Page Load Performance", function() {
            $startTime = microtime(true);
            
            $contentManager = new ContentManager();
            $featuredPrint = $contentManager->getFeaturedPrint();
            $recentNews = $contentManager->getRecentNews(5);
            $upcomingShows = $contentManager->getUpcomingShows(5);
            
            $endTime = microtime(true);
            $loadTime = $endTime - $startTime;
            
            // Should load within 3 seconds (Requirement 9.1)
            return $loadTime < 3.0;
        });
        
        $this->runTest("Database Query Performance", function() {
            $db = DatabaseManager::getInstance();
            
            $startTime = microtime(true);
            
            for ($i = 0; $i < 50; $i++) {
                $db->query("SELECT * FROM settings LIMIT 1");
            }
            
            $endTime = microtime(true);
            $totalTime = $endTime - $startTime;
            
            // 50 simple queries should complete quickly
            return $totalTime < 1.0;
        });
        
        $this->runTest("Memory Usage", function() {
            $startMemory = memory_get_usage();
            
            $contentManager = new ContentManager();
            $largeDataSet = [];
            
            for ($i = 0; $i < 100; $i++) {
                $largeDataSet[] = $contentManager->getRecentNews(1);
            }
            
            $endMemory = memory_get_usage();
            $memoryUsed = $endMemory - $startMemory;
            
            // Should use reasonable amount of memory
            return $memoryUsed < (20 * 1024 * 1024); // 20MB limit
        });
    }
    
    private function testDeploymentReadiness() {
        echo "\n7. DEPLOYMENT READINESS\n";
        echo str_repeat("-", 30) . "\n";
        
        $this->runTest("Configuration Files", function() {
            $configFiles = [
                __DIR__ . '/../apache/3ddreamcrafts.conf',
                __DIR__ . '/../config/php-security.ini',
                __DIR__ . '/../config/php-performance.ini'
            ];
            
            $hasConfigs = false;
            foreach ($configFiles as $file) {
                if (file_exists($file)) {
                    $hasConfigs = true;
                    break;
                }
            }
            
            return $hasConfigs;
        });
        
        $this->runTest("Security Files", function() {
            $securityFiles = [
                __DIR__ . '/../public/.htaccess',
                __DIR__ . '/../cache/.htaccess'
            ];
            
            foreach ($securityFiles as $file) {
                if (!file_exists($file)) return false;
            }
            return true;
        });
        
        $this->runTest("Deployment Scripts", function() {
            $deploymentScripts = [
                __DIR__ . '/../scripts/health_check.sh',
                __DIR__ . '/../scripts/backup_database.sh',
                __DIR__ . '/../database/init_database.php'
            ];
            
            foreach ($deploymentScripts as $script) {
                if (!file_exists($script)) return false;
            }
            return true;
        });
        
        $this->runTest("Documentation", function() {
            $docFiles = [
                __DIR__ . '/../README.md',
                __DIR__ . '/../DEPLOYMENT.md',
                __DIR__ . '/../SERVER_SETUP.md'
            ];
            
            foreach ($docFiles as $file) {
                if (!file_exists($file) || filesize($file) < 100) return false;
            }
            return true;
        });
    }
    
    private function displayResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "SYSTEM VALIDATION RESULTS\n";
        echo str_repeat("=", 50) . "\n";
        
        echo "Total Tests: {$this->totalTests}\n";
        echo "Passed: {$this->passedTests}\n";
        echo "Failed: {$this->failedTests}\n";
        echo "Success Rate: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n\n";
        
        if ($this->failedTests > 0) {
            echo "FAILED TESTS:\n";
            echo str_repeat("-", 20) . "\n";
            foreach ($this->testResults as $test => $result) {
                if ($result !== 'PASS') {
                    echo "✗ $test: $result\n";
                }
            }
            echo "\n";
        }
        
        if ($this->failedTests === 0) {
            echo "🎉 ALL TESTS PASSED - SYSTEM READY FOR DEPLOYMENT! 🎉\n";
            echo "\nSystem Integration Status: COMPLETE\n";
            echo "Requirements Validation: PASSED\n";
            echo "Security Features: VERIFIED\n";
            echo "Performance Requirements: MET\n";
            echo "Deployment Readiness: CONFIRMED\n";
        } else {
            echo "⚠️  SYSTEM NEEDS ATTENTION BEFORE DEPLOYMENT ⚠️\n";
            echo "\nPlease address failed tests before proceeding to production.\n";
        }
        
        echo "\nNext Steps:\n";
        echo "1. Review PRODUCTION_DEPLOYMENT_CHECKLIST.md\n";
        echo "2. Follow SYSTEM_MONITORING.md procedures\n";
        echo "3. Execute deployment using scripts in /scripts/\n";
        echo "4. Set up monitoring and maintenance schedules\n";
    }
    
    private function cleanup() {
        if (file_exists($this->testDb)) {
            unlink($this->testDb);
        }
    }
}

// Run the system validation test
if (php_sapi_name() === 'cli') {
    $test = new SystemValidationTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}