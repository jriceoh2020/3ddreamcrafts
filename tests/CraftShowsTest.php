<?php
/**
 * Craft Shows Functionality Tests
 * Tests for craft show display and management functionality
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

class CraftShowsTest {
    private $db;
    private $contentManager;
    private $adminManager;
    private $testDbPath;
    
    public function __construct() {
        // Use a separate test database
        $this->testDbPath = __DIR__ . '/craft_shows_test.db';
        
        // Remove existing test database
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        // Override database path for testing by temporarily changing the constant
        if (!defined('DB_PATH')) {
            define('DB_PATH', $this->testDbPath);
        }
        
        $this->db = DatabaseManager::getInstance();
        $this->contentManager = new ContentManager();
        $this->adminManager = new AdminManager();
        
        $this->setupTestDatabase();
    }
    
    public function __destruct() {
        // Clean up test database
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
    
    private function insertTestData() {
        // Insert test craft shows directly to avoid validation issues during setup
        $shows = [
            [
                'title' => 'Spring Craft Fair',
                'event_date' => date('Y-m-d', strtotime('+7 days')),
                'location' => 'Community Center, Main St',
                'description' => 'Annual spring craft fair with local artisans',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Summer Market',
                'event_date' => date('Y-m-d', strtotime('+30 days')),
                'location' => 'City Park Pavilion',
                'description' => 'Outdoor summer market event',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Past Event',
                'event_date' => date('Y-m-d', strtotime('-7 days')),
                'location' => 'Old Venue',
                'description' => 'This event has already passed',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'title' => 'Inactive Show',
                'event_date' => date('Y-m-d', strtotime('+14 days')),
                'location' => 'Inactive Venue',
                'description' => 'This show is not active',
                'is_active' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        foreach ($shows as $show) {
            $sql = "INSERT INTO craft_shows (title, event_date, location, description, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $this->db->execute($sql, array_values($show));
        }
    }
    
    public function runAllTests() {
        echo "Running Craft Shows Tests...\n";
        echo "================================\n\n";
        
        $tests = [
            'testGetUpcomingShows',
            'testGetUpcomingShowsWithLimit',
            'testGetUpcomingShowsEmpty',
            'testCreateCraftShow',
            'testCreateCraftShowValidation',
            'testUpdateCraftShow',
            'testDeleteCraftShow',
            'testToggleActiveStatus',
            'testGetAllContentPagination',
            'testCraftShowDataValidation',
            'testDateValidation',
            'testShowsPageDisplay'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $this->$test();
                echo "✓ $test passed\n";
                $passed++;
            } catch (Exception $e) {
                echo "✗ $test failed: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n================================\n";
        echo "Tests completed: $passed passed, $failed failed\n";
        
        return $failed === 0;
    }
    
    public function testGetUpcomingShows() {
        $shows = $this->contentManager->getUpcomingShows();
        
        // Should return only active shows with future dates
        if (count($shows) !== 2) {
            throw new Exception("Expected 2 upcoming shows, got " . count($shows));
        }
        
        // Should be ordered by event_date ASC
        if ($shows[0]['title'] !== 'Spring Craft Fair') {
            throw new Exception("Shows not ordered correctly by date");
        }
        
        // Should not include past events or inactive shows
        foreach ($shows as $show) {
            if ($show['event_date'] < date('Y-m-d')) {
                throw new Exception("Past event included in upcoming shows");
            }
            if (!$show['is_active']) {
                throw new Exception("Inactive show included in upcoming shows");
            }
        }
    }
    
    public function testGetUpcomingShowsWithLimit() {
        $shows = $this->contentManager->getUpcomingShows(1);
        
        if (count($shows) !== 1) {
            throw new Exception("Limit not applied correctly");
        }
        
        if ($shows[0]['title'] !== 'Spring Craft Fair') {
            throw new Exception("Wrong show returned with limit");
        }
    }
    
    public function testGetUpcomingShowsEmpty() {
        // Delete all active future shows
        $this->db->execute("UPDATE craft_shows SET is_active = 0 WHERE event_date >= date('now')");
        
        $shows = $this->contentManager->getUpcomingShows();
        
        if (!empty($shows)) {
            throw new Exception("Expected empty array when no upcoming shows");
        }
        
        // Restore test data
        $this->db->execute("UPDATE craft_shows SET is_active = 1 WHERE event_date >= date('now')");
    }
    
    public function testCreateCraftShow() {
        $data = [
            'title' => 'New Test Show',
            'event_date' => date('Y-m-d', strtotime('+60 days')),
            'location' => 'Test Venue',
            'description' => 'Test description',
            'is_active' => 1
        ];
        
        $result = $this->adminManager->createContent('craft_shows', $data);
        
        if (!$result) {
            throw new Exception("Failed to create craft show");
        }
        
        // Verify the show was created
        $shows = $this->db->query("SELECT * FROM craft_shows WHERE title = ?", [$data['title']]);
        if (empty($shows)) {
            throw new Exception("Craft show not found in database after creation");
        }
        
        $show = $shows[0];
        if ($show['title'] !== $data['title'] || $show['location'] !== $data['location']) {
            throw new Exception("Craft show data not saved correctly");
        }
    }
    
    public function testCreateCraftShowValidation() {
        // Test missing required fields
        $invalidData = [
            'title' => '', // Empty title
            'event_date' => date('Y-m-d', strtotime('+60 days')),
            'location' => 'Test Venue'
        ];
        
        try {
            $this->adminManager->createContent('craft_shows', $invalidData);
            throw new Exception("Should have failed with empty title");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Title is required') === false) {
                throw new Exception("Wrong validation error for empty title: " . $e->getMessage());
            }
        }
        
        // Test invalid date
        $invalidData = [
            'title' => 'Test Show',
            'event_date' => 'invalid-date',
            'location' => 'Test Venue'
        ];
        
        try {
            $this->adminManager->createContent('craft_shows', $invalidData);
            throw new Exception("Should have failed with invalid date");
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invalid date format') === false) {
                throw new Exception("Wrong validation error for invalid date: " . $e->getMessage());
            }
        }
    }
    
    public function testUpdateCraftShow() {
        // Get an existing show
        $shows = $this->db->query("SELECT * FROM craft_shows LIMIT 1");
        if (empty($shows)) {
            throw new Exception("No shows available for update test");
        }
        
        $show = $shows[0];
        $newTitle = 'Updated Show Title';
        
        $updateData = [
            'title' => $newTitle,
            'event_date' => $show['event_date'],
            'location' => $show['location'],
            'description' => 'Updated description',
            'is_active' => $show['is_active']
        ];
        
        $result = $this->adminManager->updateContent('craft_shows', $show['id'], $updateData);
        
        if (!$result) {
            throw new Exception("Failed to update craft show");
        }
        
        // Verify the update
        $updatedShow = $this->adminManager->getContentById('craft_shows', $show['id']);
        if ($updatedShow['title'] !== $newTitle) {
            throw new Exception("Craft show title not updated correctly");
        }
        if ($updatedShow['description'] !== 'Updated description') {
            throw new Exception("Craft show description not updated correctly");
        }
    }
    
    public function testDeleteCraftShow() {
        // Create a show to delete
        $data = [
            'title' => 'Show to Delete',
            'event_date' => date('Y-m-d', strtotime('+90 days')),
            'location' => 'Delete Venue',
            'is_active' => 1
        ];
        
        $id = $this->adminManager->createContent('craft_shows', $data);
        if (!$id) {
            throw new Exception("Failed to create show for deletion test");
        }
        
        // Delete the show
        $result = $this->adminManager->deleteContent('craft_shows', $id);
        if (!$result) {
            throw new Exception("Failed to delete craft show");
        }
        
        // Verify deletion
        $deletedShow = $this->adminManager->getContentById('craft_shows', $id);
        if ($deletedShow !== null) {
            throw new Exception("Craft show still exists after deletion");
        }
    }
    
    public function testToggleActiveStatus() {
        // Get an active show
        $shows = $this->db->query("SELECT * FROM craft_shows WHERE is_active = 1 LIMIT 1");
        if (empty($shows)) {
            throw new Exception("No active shows available for toggle test");
        }
        
        $show = $shows[0];
        $originalStatus = $show['is_active'];
        
        // Toggle status
        $result = $this->adminManager->toggleActiveStatus('craft_shows', $show['id'], 'is_active');
        if (!$result) {
            throw new Exception("Failed to toggle active status");
        }
        
        // Verify status changed
        $updatedShow = $this->adminManager->getContentById('craft_shows', $show['id']);
        if ($updatedShow['is_active'] == $originalStatus) {
            throw new Exception("Active status not toggled");
        }
        
        // Toggle back
        $this->adminManager->toggleActiveStatus('craft_shows', $show['id'], 'is_active');
        $restoredShow = $this->adminManager->getContentById('craft_shows', $show['id']);
        if ($restoredShow['is_active'] != $originalStatus) {
            throw new Exception("Active status not restored correctly");
        }
    }
    
    public function testGetAllContentPagination() {
        $result = $this->adminManager->getAllContent('craft_shows', 1, 2);
        
        if (count($result['content']) > 2) {
            throw new Exception("Pagination limit not applied");
        }
        
        if ($result['total_items'] < 4) {
            throw new Exception("Total items count incorrect");
        }
        
        if ($result['current_page'] !== 1) {
            throw new Exception("Current page not set correctly");
        }
        
        if ($result['total_pages'] < 2) {
            throw new Exception("Total pages calculation incorrect");
        }
    }
    
    public function testCraftShowDataValidation() {
        // Test data sanitization
        $data = [
            'title' => '<script>alert("xss")</script>Test Show',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Test & Location',
            'description' => '<p>Safe HTML</p><script>bad</script>',
            'is_active' => 1
        ];
        
        $id = $this->adminManager->createContent('craft_shows', $data);
        if (!$id) {
            throw new Exception("Failed to create show for validation test");
        }
        
        $show = $this->adminManager->getContentById('craft_shows', $id);
        
        // Check that HTML is escaped
        if (strpos($show['title'], '<script>') !== false) {
            throw new Exception("Script tags not sanitized in title");
        }
        
        if (strpos($show['location'], '&amp;') === false) {
            throw new Exception("HTML entities not encoded in location");
        }
    }
    
    public function testDateValidation() {
        // Test various date formats
        $validDates = ['2024-12-25', '2025-01-01', '2025-06-15'];
        $invalidDates = ['2024-13-01', '2024-02-30', 'invalid', '12/25/2024'];
        
        foreach ($validDates as $date) {
            $data = [
                'title' => 'Date Test Show',
                'event_date' => $date,
                'location' => 'Test Venue',
                'is_active' => 1
            ];
            
            $result = $this->adminManager->createContent('craft_shows', $data);
            if (!$result) {
                throw new Exception("Valid date $date was rejected");
            }
            
            // Clean up
            $this->adminManager->deleteContent('craft_shows', $result);
        }
        
        foreach ($invalidDates as $date) {
            $data = [
                'title' => 'Invalid Date Test',
                'event_date' => $date,
                'location' => 'Test Venue',
                'is_active' => 1
            ];
            
            try {
                $this->adminManager->createContent('craft_shows', $data);
                throw new Exception("Invalid date $date was accepted");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Invalid date format') === false) {
                    throw new Exception("Wrong error for invalid date $date: " . $e->getMessage());
                }
            }
        }
    }
    
    public function testShowsPageDisplay() {
        // Test that shows page would display correctly
        $upcomingShows = $this->contentManager->getUpcomingShows(50);
        
        // Should have upcoming shows
        if (empty($upcomingShows)) {
            throw new Exception("No upcoming shows for page display");
        }
        
        // Check required fields are present
        foreach ($upcomingShows as $show) {
            if (empty($show['title'])) {
                throw new Exception("Show missing title for display");
            }
            if (empty($show['event_date'])) {
                throw new Exception("Show missing event date for display");
            }
            if (empty($show['location'])) {
                throw new Exception("Show missing location for display");
            }
            
            // Verify date is in future
            if ($show['event_date'] < date('Y-m-d')) {
                throw new Exception("Past event in upcoming shows display");
            }
        }
        
        // Test empty state scenario
        $this->db->execute("UPDATE craft_shows SET is_active = 0");
        $emptyShows = $this->contentManager->getUpcomingShows();
        
        if (!empty($emptyShows)) {
            throw new Exception("Empty state not working correctly");
        }
        
        // Restore data
        $this->db->execute("UPDATE craft_shows SET is_active = 1 WHERE event_date >= date('now')");
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CraftShowsTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>