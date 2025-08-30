<?php
/**
 * ContentManager Test Suite
 * Tests for content retrieval and management functionality
 */

require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/config.php';

class ContentManagerTest {
    private $db;
    private $contentManager;
    private $adminManager;
    private $testDbPath;
    
    public function __construct() {
        // Use a test database
        $this->testDbPath = __DIR__ . '/test_content.db';
        
        // Clean up any existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Override DB_PATH for testing by temporarily changing the constant
        $this->setupTestDatabase();
        $this->contentManager = new ContentManager();
        $this->adminManager = new AdminManager();
    }
    
    public function __destruct() {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }
    
    /**
     * Set up test database with schema and sample data
     */
    private function setupTestDatabase() {
        try {
            // Create PDO connection directly for testing
            $pdo = new PDO('sqlite:' . $this->testDbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create tables
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_name TEXT UNIQUE NOT NULL,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS featured_prints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                image_path TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS craft_shows (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                event_date DATE NOT NULL,
                location TEXT NOT NULL,
                description TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS news_articles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                published_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_published BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert test data using PDO directly
            $this->insertTestDataWithPDO($pdo);
            
            // Now get the DatabaseManager instance
            $this->db = DatabaseManager::getInstance();
            
        } catch (Exception $e) {
            throw new Exception("Failed to setup test database: " . $e->getMessage());
        }
    }
    
    /**
     * Insert test data using PDO directly
     */
    private function insertTestDataWithPDO($pdo) {
        // Featured prints
        $stmt = $pdo->prepare("INSERT INTO featured_prints (title, description, image_path, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Test Print 1', 'A beautiful 3D printed object', 'uploads/test1.jpg', 1]);
        $stmt->execute(['Test Print 2', 'Another amazing print', 'uploads/test2.jpg', 0]);
        
        // Craft shows
        $futureDate = date('Y-m-d', strtotime('+1 week'));
        $pastDate = date('Y-m-d', strtotime('-1 week'));
        
        $stmt = $pdo->prepare("INSERT INTO craft_shows (title, event_date, location, description, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Future Show', $futureDate, 'Test Location', 'Upcoming craft show', 1]);
        $stmt->execute(['Past Show', $pastDate, 'Old Location', 'Past craft show', 1]);
        
        // News articles
        $stmt = $pdo->prepare("INSERT INTO news_articles (title, content, published_date, is_published) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Test Article 1', 'This is test content for article 1', date('Y-m-d H:i:s'), 1]);
        $stmt->execute(['Test Article 2', 'This is test content for article 2', date('Y-m-d H:i:s'), 0]);
        
        // Settings
        $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");
        $stmt->execute(['site_title', 'Test Site']);
    }
    
    /**
     * Test getting featured print
     */
    public function testGetFeaturedPrint() {
        echo "Testing getFeaturedPrint()...\n";
        
        $featuredPrint = $this->contentManager->getFeaturedPrint();
        
        if ($featuredPrint === null) {
            throw new Exception("getFeaturedPrint() returned null");
        }
        
        if ($featuredPrint['title'] !== 'Test Print 1') {
            throw new Exception("Expected 'Test Print 1', got: " . $featuredPrint['title']);
        }
        
        if ($featuredPrint['is_active'] != 1) {
            throw new Exception("Featured print should be active");
        }
        
        echo "✓ getFeaturedPrint() test passed\n";
    }
    
    /**
     * Test getting upcoming shows
     */
    public function testGetUpcomingShows() {
        echo "Testing getUpcomingShows()...\n";
        
        $shows = $this->contentManager->getUpcomingShows();
        
        if (empty($shows)) {
            throw new Exception("getUpcomingShows() returned empty array");
        }
        
        if (count($shows) !== 1) {
            throw new Exception("Expected 1 upcoming show, got: " . count($shows));
        }
        
        if ($shows[0]['title'] !== 'Future Show') {
            throw new Exception("Expected 'Future Show', got: " . $shows[0]['title']);
        }
        
        echo "✓ getUpcomingShows() test passed\n";
    }
    
    /**
     * Test getting recent news
     */
    public function testGetRecentNews() {
        echo "Testing getRecentNews()...\n";
        
        $news = $this->contentManager->getRecentNews();
        
        if (empty($news)) {
            throw new Exception("getRecentNews() returned empty array");
        }
        
        if (count($news) !== 1) {
            throw new Exception("Expected 1 published article, got: " . count($news));
        }
        
        if ($news[0]['title'] !== 'Test Article 1') {
            throw new Exception("Expected 'Test Article 1', got: " . $news[0]['title']);
        }
        
        echo "✓ getRecentNews() test passed\n";
    }
    
    /**
     * Test creating content
     */
    public function testCreateContent() {
        echo "Testing createContent()...\n";
        
        $data = [
            'title' => 'New Test Print',
            'description' => 'A newly created test print',
            'image_path' => 'uploads/new_test.jpg',
            'is_active' => 1
        ];
        
        $id = $this->adminManager->createContent('featured_prints', $data);
        
        if (!$id) {
            throw new Exception("createContent() failed to create content");
        }
        
        // Verify content was created
        $created = $this->adminManager->getContentById('featured_prints', $id);
        
        if (!$created) {
            throw new Exception("Created content not found");
        }
        
        if ($created['title'] !== 'New Test Print') {
            throw new Exception("Created content has wrong title");
        }
        
        echo "✓ createContent() test passed\n";
    }
    
    /**
     * Test updating content
     */
    public function testUpdateContent() {
        echo "Testing updateContent()...\n";
        
        $updateData = [
            'title' => 'Updated Test Print',
            'description' => 'Updated description'
        ];
        
        $success = $this->adminManager->updateContent('featured_prints', 1, $updateData);
        
        if (!$success) {
            throw new Exception("updateContent() failed");
        }
        
        // Verify content was updated
        $updated = $this->adminManager->getContentById('featured_prints', 1);
        
        if ($updated['title'] !== 'Updated Test Print') {
            throw new Exception("Content was not updated correctly");
        }
        
        echo "✓ updateContent() test passed\n";
    }
    
    /**
     * Test deleting content
     */
    public function testDeleteContent() {
        echo "Testing deleteContent()...\n";
        
        $success = $this->adminManager->deleteContent('featured_prints', 2);
        
        if (!$success) {
            throw new Exception("deleteContent() failed");
        }
        
        // Verify content was deleted
        $deleted = $this->adminManager->getContentById('featured_prints', 2);
        
        if ($deleted !== null) {
            throw new Exception("Content was not deleted");
        }
        
        echo "✓ deleteContent() test passed\n";
    }
    
    /**
     * Test input validation
     */
    public function testInputValidation() {
        echo "Testing input validation...\n";
        
        // Test invalid table name
        try {
            $this->adminManager->createContent('invalid_table', []);
            throw new Exception("Should have thrown exception for invalid table");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid table name') === false) {
                throw new Exception("Wrong exception message: " . $e->getMessage());
            }
        }
        
        // Test missing required field
        try {
            $this->adminManager->createContent('featured_prints', ['description' => 'No title']);
            throw new Exception("Should have thrown exception for missing title");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Title is required') === false) {
                throw new Exception("Wrong exception message: " . $e->getMessage());
            }
        }
        
        // Test invalid date format
        try {
            $this->adminManager->createContent('craft_shows', [
                'title' => 'Test Show',
                'event_date' => 'invalid-date',
                'location' => 'Test Location'
            ]);
            throw new Exception("Should have thrown exception for invalid date");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid date format') === false) {
                throw new Exception("Wrong exception message: " . $e->getMessage());
            }
        }
        
        echo "✓ Input validation tests passed\n";
    }
    
    /**
     * Test pagination
     */
    public function testPagination() {
        echo "Testing pagination...\n";
        
        $result = $this->contentManager->getNewsWithPagination(1, 1);
        
        if (!isset($result['articles']) || !isset($result['total_pages'])) {
            throw new Exception("Pagination result missing required keys");
        }
        
        if (count($result['articles']) > 1) {
            throw new Exception("Pagination not working correctly");
        }
        
        if ($result['current_page'] !== 1) {
            throw new Exception("Current page not set correctly");
        }
        
        echo "✓ Pagination test passed\n";
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "Running ContentManager tests...\n\n";
        
        try {
            $this->testGetFeaturedPrint();
            $this->testGetUpcomingShows();
            $this->testGetRecentNews();
            $this->testCreateContent();
            $this->testUpdateContent();
            $this->testDeleteContent();
            $this->testInputValidation();
            $this->testPagination();
            
            echo "\n✅ All ContentManager tests passed!\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ContentManagerTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>