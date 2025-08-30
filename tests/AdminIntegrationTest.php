<?php
/**
 * Admin Integration Tests
 * End-to-end tests for admin dashboard and content management workflows
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/functions.php';

class AdminIntegrationTest {
    private $db;
    private $adminManager;
    private $auth;
    private $testDbPath;
    
    public function __construct() {
        // Use test database
        $this->testDbPath = __DIR__ . '/admin_integration_test.db';
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
    }
    
    private function setupTestUser() {
        // Create test admin user
        $hashedPassword = password_hash('testpass123', PASSWORD_DEFAULT);
        $this->db->execute(
            "INSERT INTO admin_users (username, password_hash, created_at) VALUES (?, ?, ?)",
            ['testadmin', $hashedPassword, date('Y-m-d H:i:s')]
        );
    }
    
    public function testCompleteContentManagementWorkflow() {
        echo "Testing complete content management workflow...\n";
        
        // Step 1: Create featured print
        $printData = [
            'title' => 'Dragon Figurine',
            'description' => 'Detailed 3D printed dragon figurine with intricate scales',
            'image_path' => 'uploads/dragon.jpg',
            'is_active' => 1
        ];
        
        $printId = $this->adminManager->createContent('featured_prints', $printData);
        assert($printId !== false, "Failed to create featured print");
        
        // Step 2: Create craft show
        $showData = [
            'title' => 'Spring Craft Fair',
            'event_date' => date('Y-m-d', strtotime('+2 weeks')),
            'location' => 'Community Center, Main St',
            'description' => 'Annual spring craft fair featuring local artisans',
            'is_active' => 1
        ];
        
        $showId = $this->adminManager->createContent('craft_shows', $showData);
        assert($showId !== false, "Failed to create craft show");
        
        // Step 3: Create news article
        $articleData = [
            'title' => 'New Dragon Collection Available',
            'content' => 'We are excited to announce our new collection of dragon figurines, featuring detailed scales and realistic poses.',
            'published_date' => date('Y-m-d H:i:s'),
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $articleData);
        assert($articleId !== false, "Failed to create news article");
        
        // Step 4: Update settings
        $settings = [
            'site_title' => 'Dragon Crafts 3D',
            'theme_color' => '#8b5cf6',
            'accent_color' => '#f59e0b'
        ];
        
        $settingsResult = $this->adminManager->updateSettings($settings);
        assert($settingsResult === true, "Failed to update settings");
        
        // Step 5: Verify dashboard statistics
        $stats = [
            'featured_prints' => $this->adminManager->getAllContent('featured_prints', 1, 1000)['total_items'],
            'craft_shows' => $this->adminManager->getAllContent('craft_shows', 1, 1000)['total_items'],
            'news_articles' => $this->adminManager->getAllContent('news_articles', 1, 1000)['total_items']
        ];
        
        assert($stats['featured_prints'] === 1, "Expected 1 featured print");
        assert($stats['craft_shows'] === 1, "Expected 1 craft show");
        assert($stats['news_articles'] === 1, "Expected 1 news article");
        
        // Step 6: Test public content retrieval
        $contentManager = new ContentManager();
        
        $featuredPrint = $contentManager->getFeaturedPrint();
        assert($featuredPrint !== null, "Should retrieve featured print");
        assert($featuredPrint['title'] === 'Dragon Figurine', "Featured print title mismatch");
        
        $upcomingShows = $contentManager->getUpcomingShows();
        assert(count($upcomingShows) === 1, "Should have 1 upcoming show");
        assert($upcomingShows[0]['title'] === 'Spring Craft Fair', "Show title mismatch");
        
        $recentNews = $contentManager->getRecentNews();
        assert(count($recentNews) === 1, "Should have 1 news article");
        assert($recentNews[0]['title'] === 'New Dragon Collection Available', "Article title mismatch");
        
        $siteSettings = $contentManager->getSettings();
        assert($siteSettings['site_title'] === 'Dragon Crafts 3D', "Site title not updated");
        assert($siteSettings['theme_color'] === '#8b5cf6', "Theme color not updated");
        
        // Step 7: Test content updates
        $updatePrintData = [
            'title' => 'Premium Dragon Figurine',
            'description' => 'Premium quality 3D printed dragon with hand-painted details',
            'is_active' => 1
        ];
        
        $updateResult = $this->adminManager->updateContent('featured_prints', $printId, $updatePrintData);
        assert($updateResult === true, "Failed to update featured print");
        
        $updatedPrint = $this->adminManager->getContentById('featured_prints', $printId);
        assert($updatedPrint['title'] === 'Premium Dragon Figurine', "Print title not updated");
        
        // Step 8: Test content deactivation
        $toggleResult = $this->adminManager->toggleActiveStatus('featured_prints', $printId, 'is_active');
        assert($toggleResult === true, "Failed to toggle print status");
        
        $toggledPrint = $this->adminManager->getContentById('featured_prints', $printId);
        assert($toggledPrint['is_active'] == 0, "Print not deactivated");
        
        // Verify public site no longer shows inactive content
        $noFeaturedPrint = $contentManager->getFeaturedPrint();
        assert($noFeaturedPrint === null, "Should not retrieve inactive featured print");
        
        // Step 9: Test content deletion
        $deleteResult = $this->adminManager->deleteContent('featured_prints', $printId);
        assert($deleteResult === true, "Failed to delete featured print");
        
        $deletedPrint = $this->adminManager->getContentById('featured_prints', $printId);
        assert($deletedPrint === null, "Featured print not deleted");
        
        echo "✓ Complete content management workflow test passed\n";
    }
    
    public function testErrorHandlingAndValidation() {
        echo "Testing error handling and validation...\n";
        
        // Test invalid data handling
        $invalidPrintData = [
            'title' => '', // Empty title should fail
            'description' => 'Valid description',
            'is_active' => 1
        ];
        
        try {
            $this->adminManager->createContent('featured_prints', $invalidPrintData);
            assert(false, "Should have thrown exception for empty title");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Title is required') !== false, "Wrong error message");
        }
        
        // Test invalid table access
        try {
            $this->adminManager->createContent('invalid_table', ['test' => 'data']);
            assert(false, "Should have thrown exception for invalid table");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Invalid table name') !== false, "Wrong error message");
        }
        
        // Test invalid date format
        $invalidShowData = [
            'title' => 'Test Show',
            'event_date' => 'invalid-date-format',
            'location' => 'Test Location'
        ];
        
        try {
            $this->adminManager->createContent('craft_shows', $invalidShowData);
            assert(false, "Should have thrown exception for invalid date");
        } catch (Exception $e) {
            assert(strpos($e->getMessage(), 'Invalid date format') !== false, "Wrong error message");
        }
        
        // Test XSS prevention
        $xssData = [
            'title' => '<script>alert("xss")</script>Malicious Title',
            'description' => '<img src="x" onerror="alert(1)">',
            'is_active' => 1
        ];
        
        $xssId = $this->adminManager->createContent('featured_prints', $xssData);
        assert($xssId !== false, "Should create content but sanitize it");
        
        $sanitized = $this->adminManager->getContentById('featured_prints', $xssId);
        assert(strpos($sanitized['title'], '<script>') === false, "Script tags should be sanitized");
        assert(strpos($sanitized['description'], 'onerror') === false, "Event handlers should be sanitized");
        
        echo "✓ Error handling and validation test passed\n";
    }
    
    public function testBulkOperationsAndPerformance() {
        echo "Testing bulk operations and performance...\n";
        
        $startTime = microtime(true);
        
        // Create multiple items
        $itemIds = [];
        for ($i = 1; $i <= 50; $i++) {
            $data = [
                'title' => "Bulk Test Print $i",
                'description' => "Description for test print $i",
                'is_active' => ($i % 2 === 0) ? 1 : 0
            ];
            
            $id = $this->adminManager->createContent('featured_prints', $data);
            assert($id !== false, "Failed to create bulk item $i");
            $itemIds[] = $id;
        }
        
        $createTime = microtime(true) - $startTime;
        assert($createTime < 5.0, "Bulk creation took too long: {$createTime}s");
        
        // Test pagination with bulk data
        $page1 = $this->adminManager->getAllContent('featured_prints', 1, 10);
        assert(count($page1['content']) === 10, "Expected 10 items on page 1");
        assert($page1['total_items'] === 50, "Expected 50 total items");
        assert($page1['total_pages'] === 5, "Expected 5 pages");
        
        // Test bulk updates
        $updateStartTime = microtime(true);
        foreach (array_slice($itemIds, 0, 10) as $id) {
            $this->adminManager->updateContent('featured_prints', $id, [
                'description' => 'Updated bulk description'
            ]);
        }
        $updateTime = microtime(true) - $updateStartTime;
        assert($updateTime < 2.0, "Bulk updates took too long: {$updateTime}s");
        
        // Test bulk deletes
        $deleteStartTime = microtime(true);
        foreach ($itemIds as $id) {
            $this->adminManager->deleteContent('featured_prints', $id);
        }
        $deleteTime = microtime(true) - $deleteStartTime;
        assert($deleteTime < 3.0, "Bulk deletes took too long: {$deleteTime}s");
        
        // Verify all items deleted
        $remaining = $this->adminManager->getAllContent('featured_prints', 1, 100);
        assert($remaining['total_items'] === 0, "All items should be deleted");
        
        echo "✓ Bulk operations and performance test passed\n";
    }
    
    public function testConcurrentAccess() {
        echo "Testing concurrent access scenarios...\n";
        
        // Create test content
        $printId = $this->adminManager->createContent('featured_prints', [
            'title' => 'Concurrent Test Print',
            'description' => 'Test concurrent access',
            'is_active' => 1
        ]);
        
        // Simulate concurrent updates (in real scenario, this would be multiple requests)
        $update1 = [
            'title' => 'Updated by User 1',
            'description' => 'Updated by first user'
        ];
        
        $update2 = [
            'title' => 'Updated by User 2', 
            'description' => 'Updated by second user'
        ];
        
        // Both updates should succeed (last one wins)
        $result1 = $this->adminManager->updateContent('featured_prints', $printId, $update1);
        $result2 = $this->adminManager->updateContent('featured_prints', $printId, $update2);
        
        assert($result1 === true, "First update should succeed");
        assert($result2 === true, "Second update should succeed");
        
        $final = $this->adminManager->getContentById('featured_prints', $printId);
        assert($final['title'] === 'Updated by User 2', "Last update should win");
        
        echo "✓ Concurrent access test passed\n";
    }
    
    public function testDataIntegrityAndConsistency() {
        echo "Testing data integrity and consistency...\n";
        
        // Test transaction-like behavior for settings updates
        $originalSettings = $this->adminManager->getSettings();
        
        $newSettings = [
            'site_title' => 'New Site Title',
            'theme_color' => '#ff0000',
            'invalid_setting' => 'should_be_ignored'
        ];
        
        // This should partially succeed (valid settings) and ignore invalid ones
        $result = $this->adminManager->updateSettings($newSettings);
        
        $updatedSettings = $this->adminManager->getSettings();
        assert($updatedSettings['site_title'] === 'New Site Title', "Valid setting should be updated");
        assert($updatedSettings['theme_color'] === '#ff0000', "Valid setting should be updated");
        
        // Test referential integrity (featured prints should maintain consistency)
        $printId = $this->adminManager->createContent('featured_prints', [
            'title' => 'Integrity Test Print',
            'description' => 'Testing data integrity',
            'is_active' => 1
        ]);
        
        // Verify timestamps are properly set
        $print = $this->adminManager->getContentById('featured_prints', $printId);
        assert(!empty($print['created_at']), "Created timestamp should be set");
        assert(!empty($print['updated_at']), "Updated timestamp should be set");
        
        $originalUpdated = $print['updated_at'];
        
        // Wait a moment and update
        sleep(1);
        $this->adminManager->updateContent('featured_prints', $printId, [
            'description' => 'Updated description'
        ]);
        
        $updatedPrint = $this->adminManager->getContentById('featured_prints', $printId);
        assert($updatedPrint['updated_at'] > $originalUpdated, "Updated timestamp should change");
        assert($updatedPrint['created_at'] === $print['created_at'], "Created timestamp should not change");
        
        echo "✓ Data integrity and consistency test passed\n";
    }
    
    public function runAllTests() {
        echo "Running Admin Integration Tests...\n\n";
        
        try {
            $this->testCompleteContentManagementWorkflow();
            $this->testErrorHandlingAndValidation();
            $this->testBulkOperationsAndPerformance();
            $this->testConcurrentAccess();
            $this->testDataIntegrityAndConsistency();
            
            echo "\n✅ All admin integration tests passed!\n";
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
    $test = new AdminIntegrationTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>