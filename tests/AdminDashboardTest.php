<?php
/**
 * Admin Dashboard and Content Management Tests
 * Tests for admin dashboard functionality and content management workflows
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/functions.php';

class AdminDashboardTest {
    private $db;
    private $adminManager;
    private $auth;
    private $testDbPath;
    
    public function __construct() {
        // Use test database
        $this->testDbPath = __DIR__ . '/admin_test.db';
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Override database path for testing
        define('TEST_DB_PATH', $this->testDbPath);
        
        $this->db = DatabaseManager::getInstance();
        $this->adminManager = new AdminManager();
        $this->auth = AuthManager::getInstance();
        
        $this->setupTestDatabase();
        $this->setupTestUser();
    }
    
    public function __destruct() {
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }
    
    private function setupTestDatabase() {
        // Create tables
        $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->db->execute($statement);
            }
        }
        
        // Insert test data
        $this->insertTestData();
    }
    
    private function setupTestUser() {
        // Create test admin user
        $hashedPassword = password_hash('testpass123', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)",
            ['testadmin', $hashedPassword, date('Y-m-d H:i:s')]
        );
    }
    
    private function insertTestData() {
        // Insert test featured prints
        $this->db->execute(
            "INSERT INTO featured_prints (title, description, image_path, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test Print 1', 'Test description 1', 'uploads/test1.jpg', 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        $this->db->execute(
            "INSERT INTO featured_prints (title, description, image_path, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test Print 2', 'Test description 2', 'uploads/test2.jpg', 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        // Insert test craft shows
        $futureDate = date('Y-m-d', strtotime('+1 week'));
        $this->db->execute(
            "INSERT INTO craft_shows (title, event_date, location, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
            ['Test Show 1', $futureDate, 'Test Location 1', 'Test show description', 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        // Insert test news articles
        $this->db->execute(
            "INSERT INTO news_articles (title, content, published_date, is_published, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['Test Article 1', 'Test article content', date('Y-m-d H:i:s'), 1, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        
        // Insert test settings
        $this->db->execute(
            "INSERT INTO settings (setting_name, setting_value, updated_at) VALUES (?, ?, ?)",
            ['site_title', 'Test Site', date('Y-m-d H:i:s')]
        );
    }
    
    public function testDashboardStatistics() {
        echo "Testing dashboard statistics...\n";
        
        // Test getting content statistics
        $stats = [
            'featured_prints' => $this->adminManager->getAllContent('featured_prints', 1, 1000)['total_items'],
            'craft_shows' => $this->adminManager->getAllContent('craft_shows', 1, 1000)['total_items'],
            'news_articles' => $this->adminManager->getAllContent('news_articles', 1, 1000)['total_items']
        ];
        
        assert($stats['featured_prints'] === 2, "Expected 2 featured prints, got {$stats['featured_prints']}");
        assert($stats['craft_shows'] === 1, "Expected 1 craft show, got {$stats['craft_shows']}");
        assert($stats['news_articles'] === 1, "Expected 1 news article, got {$stats['news_articles']}");
        
        // Test active content counts
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM featured_prints WHERE is_active = 1");
        $activePrints = $result ? (int)$result['count'] : 0;
        assert($activePrints === 1, "Expected 1 active featured print, got $activePrints");
        
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM craft_shows WHERE is_active = 1 AND event_date >= date('now')");
        $upcomingShows = $result ? (int)$result['count'] : 0;
        assert($upcomingShows === 1, "Expected 1 upcoming show, got $upcomingShows");
        
        $result = $this->db->queryOne("SELECT COUNT(*) as count FROM news_articles WHERE is_published = 1");
        $publishedArticles = $result ? (int)$result['count'] : 0;
        assert($publishedArticles === 1, "Expected 1 published article, got $publishedArticles");
        
        echo "✓ Dashboard statistics test passed\n";
    }
    
    public function testFeaturedPrintsCRUD() {
        echo "Testing featured prints CRUD operations...\n";
        
        // Test Create
        $createData = [
            'title' => 'New Test Print',
            'description' => 'New test description',
            'image_path' => 'uploads/new_test.jpg',
            'is_active' => 1
        ];
        
        $newId = $this->adminManager->createContent('featured_prints', $createData);
        assert($newId !== false, "Failed to create featured print");
        
        // Test Read
        $created = $this->adminManager->getContentById('featured_prints', $newId);
        assert($created !== null, "Failed to retrieve created featured print");
        assert($created['title'] === 'New Test Print', "Title mismatch in created print");
        assert($created['is_active'] == 1, "Active status mismatch in created print");
        
        // Test Update
        $updateData = [
            'title' => 'Updated Test Print',
            'description' => 'Updated description',
            'is_active' => 0
        ];
        
        $updateResult = $this->adminManager->updateContent('featured_prints', $newId, $updateData);
        assert($updateResult === true, "Failed to update featured print");
        
        $updated = $this->adminManager->getContentById('featured_prints', $newId);
        assert($updated['title'] === 'Updated Test Print', "Title not updated");
        assert($updated['is_active'] == 0, "Active status not updated");
        
        // Test Toggle Active Status
        $toggleResult = $this->adminManager->toggleActiveStatus('featured_prints', $newId, 'is_active');
        assert($toggleResult === true, "Failed to toggle active status");
        
        $toggled = $this->adminManager->getContentById('featured_prints', $newId);
        assert($toggled['is_active'] == 1, "Active status not toggled");
        
        // Test Delete
        $deleteResult = $this->adminManager->deleteContent('featured_prints', $newId);
        assert($deleteResult === true, "Failed to delete featured print");
        
        $deleted = $this->adminManager->getContentById('featured_prints', $newId);
        assert($deleted === null, "Featured print not deleted");
        
        echo "✓ Featured prints CRUD test passed\n";
    }
    
    public function testCraftShowsCRUD() {
        echo "Testing craft shows CRUD operations...\n";
        
        // Test Create
        $createData = [
            'title' => 'New Test Show',
            'event_date' => date('Y-m-d', strtotime('+2 weeks')),
            'location' => 'New Test Location',
            'description' => 'New test show description',
            'is_active' => 1
        ];
        
        $newId = $this->adminManager->createContent('craft_shows', $createData);
        assert($newId !== false, "Failed to create craft show");
        
        // Test Read
        $created = $this->adminManager->getContentById('craft_shows', $newId);
        assert($created !== null, "Failed to retrieve created craft show");
        assert($created['title'] === 'New Test Show', "Title mismatch in created show");
        assert($created['location'] === 'New Test Location', "Location mismatch in created show");
        
        // Test Update
        $updateData = [
            'title' => 'Updated Test Show',
            'location' => 'Updated Location',
            'is_active' => 0
        ];
        
        $updateResult = $this->adminManager->updateContent('craft_shows', $newId, $updateData);
        assert($updateResult === true, "Failed to update craft show");
        
        $updated = $this->adminManager->getContentById('craft_shows', $newId);
        assert($updated['title'] === 'Updated Test Show', "Title not updated");
        assert($updated['location'] === 'Updated Location', "Location not updated");
        
        // Test Delete
        $deleteResult = $this->adminManager->deleteContent('craft_shows', $newId);
        assert($deleteResult === true, "Failed to delete craft show");
        
        echo "✓ Craft shows CRUD test passed\n";
    }
    
    public function testNewsArticlesCRUD() {
        echo "Testing news articles CRUD operations...\n";
        
        // Test Create
        $createData = [
            'title' => 'New Test Article',
            'content' => 'New test article content with more details.',
            'published_date' => date('Y-m-d H:i:s'),
            'is_published' => 1
        ];
        
        $newId = $this->adminManager->createContent('news_articles', $createData);
        assert($newId !== false, "Failed to create news article");
        
        // Test Read
        $created = $this->adminManager->getContentById('news_articles', $newId);
        assert($created !== null, "Failed to retrieve created news article");
        assert($created['title'] === 'New Test Article', "Title mismatch in created article");
        assert($created['is_published'] == 1, "Published status mismatch in created article");
        
        // Test Update
        $updateData = [
            'title' => 'Updated Test Article',
            'content' => 'Updated article content',
            'is_published' => 0
        ];
        
        $updateResult = $this->adminManager->updateContent('news_articles', $newId, $updateData);
        assert($updateResult === true, "Failed to update news article");
        
        $updated = $this->adminManager->getContentById('news_articles', $newId);
        assert($updated['title'] === 'Updated Test Article', "Title not updated");
        assert($updated['content'] === 'Updated article content', "Content not updated");
        
        // Test Delete
        $deleteResult = $this->adminManager->deleteContent('news_articles', $newId);
        assert($deleteResult === true, "Failed to delete news article");
        
        echo "✓ News articles CRUD test passed\n";
    }
    
    public function testSettingsManagement() {
        echo "Testing settings management...\n";
        
        // Test Update Settings
        $settings = [
            'site_title' => 'Updated Test Site',
            'theme_color' => '#ff0000',
            'accent_color' => '#00ff00'
        ];
        
        $result = $this->adminManager->updateSettings($settings);
        assert($result === true, "Failed to update settings");
        
        // Test Get Settings
        $currentSettings = $this->adminManager->getSettings();
        assert($currentSettings['site_title'] === 'Updated Test Site', "Site title not updated");
        assert($currentSettings['theme_color'] === '#ff0000', "Theme color not updated");
        assert($currentSettings['accent_color'] === '#00ff00', "Accent color not updated");
        
        echo "✓ Settings management test passed\n";
    }
    
    public function testInputValidation() {
        echo "Testing input validation...\n";
        
        // Test invalid table name
        try {
            $this->adminManager->createContent('invalid_table', ['test' => 'data']);
            assert(false, "Should have thrown exception for invalid table name");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Invalid table name') !== false, "Wrong exception message");
        }
        
        // Test missing required fields
        try {
            $this->adminManager->createContent('featured_prints', ['description' => 'No title']);
            assert(false, "Should have thrown exception for missing title");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Title is required') !== false, "Wrong exception message");
        }
        
        // Test invalid date format
        try {
            $this->adminManager->createContent('craft_shows', [
                'title' => 'Test Show',
                'event_date' => 'invalid-date',
                'location' => 'Test Location'
            ]);
            assert(false, "Should have thrown exception for invalid date");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Invalid date format') !== false, "Wrong exception message");
        }
        
        echo "✓ Input validation test passed\n";
    }
    
    public function testPagination() {
        echo "Testing pagination...\n";
        
        // Add more test data for pagination
        for ($i = 3; $i <= 15; $i++) {
            $this->adminManager->createContent('featured_prints', [
                'title' => "Test Print $i",
                'description' => "Description $i",
                'is_active' => 1
            ]);
        }
        
        // Test pagination
        $page1 = $this->adminManager->getAllContent('featured_prints', 1, 5);
        assert(count($page1['content']) === 5, "Expected 5 items on page 1");
        assert($page1['current_page'] === 1, "Wrong current page");
        assert($page1['total_pages'] >= 3, "Expected at least 3 pages");
        
        $page2 = $this->adminManager->getAllContent('featured_prints', 2, 5);
        assert(count($page2['content']) === 5, "Expected 5 items on page 2");
        assert($page2['current_page'] === 2, "Wrong current page");
        
        // Test that items are different between pages
        $page1Ids = array_column($page1['content'], 'id');
        $page2Ids = array_column($page2['content'], 'id');
        assert(empty(array_intersect($page1Ids, $page2Ids)), "Pages should have different items");
        
        echo "✓ Pagination test passed\n";
    }
    
    public function testCSRFTokenGeneration() {
        echo "Testing CSRF token generation and validation...\n";
        
        // Start session for CSRF testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Test token generation
        $token1 = generateCSRFToken();
        assert(!empty($token1), "CSRF token should not be empty");
        assert(strlen($token1) === 64, "CSRF token should be 64 characters long");
        
        // Test token validation
        assert(validateCSRFToken($token1) === true, "Valid CSRF token should validate");
        assert(validateCSRFToken('invalid_token') === false, "Invalid CSRF token should not validate");
        
        // Test token uniqueness
        $token2 = generateCSRFToken();
        assert($token1 !== $token2, "CSRF tokens should be unique");
        
        echo "✓ CSRF token test passed\n";
    }
    
    public function testFormValidationFunctions() {
        echo "Testing form validation functions...\n";
        
        // Test text input validation
        assert(validateTextInput('Valid text', 1, 50) === 'Valid text', "Valid text should pass");
        assert(validateTextInput('', 1, 50, true) === null, "Empty required text should fail");
        assert(validateTextInput('', 1, 50, false) === '', "Empty optional text should return empty string");
        assert(validateTextInput(str_repeat('a', 100), 1, 50) === null, "Too long text should fail");
        
        // Test email validation
        assert(validateEmail('test@example.com') === 'test@example.com', "Valid email should pass");
        assert(validateEmail('invalid-email') === null, "Invalid email should fail");
        
        // Test URL validation
        assert(validateUrl('https://example.com') === 'https://example.com', "Valid URL should pass");
        assert(validateUrl('invalid-url') === null, "Invalid URL should fail");
        
        // Test integer validation
        assert(validateInteger('123', 1, 200) === 123, "Valid integer should pass");
        assert(validateInteger('abc', 1, 200) === null, "Non-numeric should fail");
        assert(validateInteger('300', 1, 200) === null, "Out of range should fail");
        
        // Test hex color validation
        assert(validateHexColor('#ff0000') === '#ff0000', "Valid hex color should pass");
        assert(validateHexColor('ff0000') === '#ff0000', "Hex color without # should be fixed");
        assert(validateHexColor('invalid') === null, "Invalid hex color should fail");
        
        echo "✓ Form validation functions test passed\n";
    }
    
    public function runAllTests() {
        echo "Running Admin Dashboard and Content Management Tests...\n\n";
        
        try {
            $this->testDashboardStatistics();
            $this->testFeaturedPrintsCRUD();
            $this->testCraftShowsCRUD();
            $this->testNewsArticlesCRUD();
            $this->testSettingsManagement();
            $this->testInputValidation();
            $this->testPagination();
            $this->testCSRFTokenGeneration();
            $this->testFormValidationFunctions();
            
            echo "\n✅ All admin dashboard and content management tests passed!\n";
            return true;
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return false;
        } catch (AssertionError $e) {
            echo "\n❌ Assertion failed: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
            return false;
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new AdminDashboardTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>