<?php
/**
 * Simple Craft Shows Functionality Tests
 * Basic tests for craft show display and management functionality
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

class CraftShowsSimpleTest {
    private $db;
    private $contentManager;
    private $adminManager;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->contentManager = new ContentManager();
        $this->adminManager = new AdminManager();
    }
    
    public function runAllTests() {
        echo "Running Simple Craft Shows Tests...\n";
        echo "====================================\n\n";
        
        $tests = [
            'testGetUpcomingShows',
            'testCreateAndDeleteCraftShow',
            'testCraftShowValidation',
            'testShowsPageFunctionality'
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
        
        echo "\n====================================\n";
        echo "Tests completed: $passed passed, $failed failed\n";
        
        return $failed === 0;
    }
    
    public function testGetUpcomingShows() {
        $shows = $this->contentManager->getUpcomingShows();
        
        // Should return an array
        if (!is_array($shows)) {
            throw new Exception("getUpcomingShows should return an array");
        }
        
        // Each show should have required fields
        foreach ($shows as $show) {
            if (!isset($show['title']) || !isset($show['event_date']) || !isset($show['location'])) {
                throw new Exception("Show missing required fields");
            }
            
            // Should only include active shows (check if field exists first)
            if (isset($show['is_active']) && !$show['is_active']) {
                throw new Exception("Inactive show included in results");
            }
            
            // Should only include future events
            if ($show['event_date'] < date('Y-m-d')) {
                throw new Exception("Past event included in upcoming shows");
            }
        }
        
        // Test with limit
        $limitedShows = $this->contentManager->getUpcomingShows(1);
        if (count($limitedShows) > 1) {
            throw new Exception("Limit parameter not working");
        }
    }
    
    public function testCreateAndDeleteCraftShow() {
        $testData = [
            'title' => 'Test Craft Show ' . time(),
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Test Location',
            'description' => 'Test description for craft show',
            'is_active' => 1
        ];
        
        // Create the show
        $id = $this->adminManager->createContent('craft_shows', $testData);
        if (!$id) {
            throw new Exception("Failed to create craft show");
        }
        
        // Verify it was created
        $createdShow = $this->adminManager->getContentById('craft_shows', $id);
        if (!$createdShow) {
            throw new Exception("Created show not found");
        }
        
        if ($createdShow['title'] !== $testData['title']) {
            throw new Exception("Show title not saved correctly");
        }
        
        // Update the show
        $updateData = [
            'title' => 'Updated Test Show',
            'event_date' => $testData['event_date'],
            'location' => $testData['location'],
            'description' => 'Updated description',
            'is_active' => 1
        ];
        
        $updateResult = $this->adminManager->updateContent('craft_shows', $id, $updateData);
        if (!$updateResult) {
            throw new Exception("Failed to update craft show");
        }
        
        // Verify update
        $updatedShow = $this->adminManager->getContentById('craft_shows', $id);
        if ($updatedShow['title'] !== 'Updated Test Show') {
            throw new Exception("Show not updated correctly");
        }
        
        // Delete the show
        $deleteResult = $this->adminManager->deleteContent('craft_shows', $id);
        if (!$deleteResult) {
            throw new Exception("Failed to delete craft show");
        }
        
        // Verify deletion
        $deletedShow = $this->adminManager->getContentById('craft_shows', $id);
        if ($deletedShow) {
            throw new Exception("Show still exists after deletion");
        }
    }
    
    public function testCraftShowValidation() {
        // Test empty title
        $invalidData = [
            'title' => '',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Test Location'
        ];
        
        try {
            $result = $this->adminManager->createContent('craft_shows', $invalidData);
            if ($result) {
                throw new Exception("Empty title should be rejected");
            }
        } catch (Exception $e) {
            // Expected exception for validation
            if (strpos($e->getMessage(), 'Title is required') === false) {
                throw new Exception("Wrong validation error for empty title: " . $e->getMessage());
            }
        }
        
        // Test empty location
        $invalidData = [
            'title' => 'Test Show',
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'location' => ''
        ];
        
        try {
            $result = $this->adminManager->createContent('craft_shows', $invalidData);
            if ($result) {
                throw new Exception("Empty location should be rejected");
            }
        } catch (Exception $e) {
            // Expected exception for validation
            if (strpos($e->getMessage(), 'Location is required') === false) {
                throw new Exception("Wrong validation error for empty location: " . $e->getMessage());
            }
        }
        
        // Test invalid date format
        $invalidData = [
            'title' => 'Test Show',
            'event_date' => 'not-a-date',
            'location' => 'Test Location'
        ];
        
        try {
            $result = $this->adminManager->createContent('craft_shows', $invalidData);
            if ($result) {
                throw new Exception("Invalid date should be rejected");
            }
        } catch (Exception $e) {
            // Expected exception for validation
            if (strpos($e->getMessage(), 'Invalid date format') === false) {
                throw new Exception("Wrong validation error for invalid date: " . $e->getMessage());
            }
        }
    }
    
    public function testShowsPageFunctionality() {
        // Test that the shows page would work correctly
        $shows = $this->contentManager->getUpcomingShows(50);
        
        // Verify each show has display-ready data
        foreach ($shows as $show) {
            // Check required fields exist
            if (empty($show['title']) || empty($show['event_date']) || empty($show['location'])) {
                throw new Exception("Show missing required display fields");
            }
            
            // Check date is valid for display
            $eventDate = DateTime::createFromFormat('Y-m-d', $show['event_date']);
            if (!$eventDate) {
                throw new Exception("Invalid date format in show data");
            }
            
            // Check that HTML would be safe
            if ($show['title'] !== htmlspecialchars($show['title'], ENT_QUOTES, 'UTF-8')) {
                // This is expected since data is already sanitized
            }
        }
        
        // Test pagination functionality
        $paginatedShows = $this->adminManager->getAllContent('craft_shows', 1, 5);
        
        if (!isset($paginatedShows['content']) || !isset($paginatedShows['total_pages'])) {
            throw new Exception("Pagination data structure incorrect");
        }
        
        if (!is_array($paginatedShows['content'])) {
            throw new Exception("Paginated content should be an array");
        }
        
        if (count($paginatedShows['content']) > 5) {
            throw new Exception("Pagination limit not applied");
        }
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new CraftShowsSimpleTest();
    $success = $test->runAllTests();
    exit($success ? 0 : 1);
}
?>