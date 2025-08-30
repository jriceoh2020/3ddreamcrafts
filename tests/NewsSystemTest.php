<?php
/**
 * News System Tests
 * Tests for news display and publishing workflow
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

class NewsSystemTest {
    private $db;
    private $contentManager;
    private $adminManager;
    private $testArticleIds = [];
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->contentManager = new ContentManager();
        $this->adminManager = new AdminManager();
    }
    
    public function runAllTests() {
        echo "Running News System Tests...\n";
        echo str_repeat("=", 50) . "\n";
        
        $tests = [
            'testCreateNewsArticle',
            'testGetRecentNews',
            'testGetNewsWithPagination',
            'testGetNewsArticleById',
            'testUpdateNewsArticle',
            'testTogglePublishedStatus',
            'testDeleteNewsArticle',
            'testNewsDisplayOnPublicPage',
            'testEmptyNewsState',
            'testNewsPagination',
            'testNewsValidation'
        ];
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                echo "Running $test... ";
                $this->$test();
                echo "PASSED\n";
                $passed++;
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        // Cleanup
        $this->cleanup();
        
        echo str_repeat("=", 50) . "\n";
        echo "Tests completed: $passed passed, $failed failed\n";
        
        return $failed === 0;
    }
    
    public function testCreateNewsArticle() {
        $data = [
            'title' => 'Test News Article',
            'content' => 'This is a test news article content with multiple paragraphs.\n\nSecond paragraph here.',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $data);
        
        if (!$articleId) {
            throw new Exception("Failed to create news article");
        }
        
        $this->testArticleIds[] = $articleId;
        
        // Verify the article was created correctly
        $article = $this->adminManager->getContentById('news_articles', $articleId);
        
        if (!$article) {
            throw new Exception("Created article not found");
        }
        
        if ($article['title'] !== $data['title']) {
            throw new Exception("Article title mismatch");
        }
        
        if ($article['content'] !== $data['content']) {
            throw new Exception("Article content mismatch");
        }
        
        if ($article['is_published'] != $data['is_published']) {
            throw new Exception("Article published status mismatch");
        }
    }
    
    public function testGetRecentNews() {
        // Create multiple test articles
        $articles = [
            [
                'title' => 'Recent Article 1',
                'content' => 'Content 1',
                'published_date' => '2024-01-20 10:00:00',
                'is_published' => 1
            ],
            [
                'title' => 'Recent Article 2',
                'content' => 'Content 2',
                'published_date' => '2024-01-19 10:00:00',
                'is_published' => 1
            ],
            [
                'title' => 'Draft Article',
                'content' => 'Draft content',
                'published_date' => '2024-01-18 10:00:00',
                'is_published' => 0
            ]
        ];
        
        foreach ($articles as $data) {
            $id = $this->adminManager->createContent('news_articles', $data);
            if ($id) {
                $this->testArticleIds[] = $id;
            }
        }
        
        // Test getting recent news (should only return published articles)
        $recentNews = $this->contentManager->getRecentNews(5);
        
        if (empty($recentNews)) {
            throw new Exception("No recent news returned");
        }
        
        // Check that only published articles are returned
        foreach ($recentNews as $article) {
            if (!$article['is_published']) {
                throw new Exception("Unpublished article returned in recent news");
            }
        }
        
        // Check ordering (most recent first)
        if (count($recentNews) >= 2) {
            $firstDate = strtotime($recentNews[0]['published_date']);
            $secondDate = strtotime($recentNews[1]['published_date']);
            
            if ($firstDate < $secondDate) {
                throw new Exception("Recent news not ordered correctly");
            }
        }
    }
    
    public function testGetNewsWithPagination() {
        // Create multiple test articles for pagination
        for ($i = 1; $i <= 15; $i++) {
            $data = [
                'title' => "Pagination Test Article $i",
                'content' => "Content for article $i",
                'published_date' => date('Y-m-d H:i:s', strtotime("-$i days")),
                'is_published' => 1
            ];
            
            $id = $this->adminManager->createContent('news_articles', $data);
            if ($id) {
                $this->testArticleIds[] = $id;
            }
        }
        
        // Test first page
        $page1 = $this->contentManager->getNewsWithPagination(1, 5);
        
        if (count($page1['articles']) !== 5) {
            throw new Exception("Page 1 should have 5 articles, got " . count($page1['articles']));
        }
        
        if ($page1['current_page'] !== 1) {
            throw new Exception("Current page should be 1");
        }
        
        if ($page1['total_pages'] < 3) {
            throw new Exception("Should have at least 3 pages with 15+ articles");
        }
        
        // Test second page
        $page2 = $this->contentManager->getNewsWithPagination(2, 5);
        
        if (count($page2['articles']) !== 5) {
            throw new Exception("Page 2 should have 5 articles");
        }
        
        if ($page2['current_page'] !== 2) {
            throw new Exception("Current page should be 2");
        }
        
        // Verify articles are different between pages
        $page1Ids = array_column($page1['articles'], 'id');
        $page2Ids = array_column($page2['articles'], 'id');
        
        if (array_intersect($page1Ids, $page2Ids)) {
            throw new Exception("Pages should not have overlapping articles");
        }
    }
    
    public function testGetNewsArticleById() {
        // Create a test article
        $data = [
            'title' => 'Single Article Test',
            'content' => 'Content for single article test',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $data);
        $this->testArticleIds[] = $articleId;
        
        // Test getting the article by ID
        $article = $this->contentManager->getNewsArticle($articleId);
        
        if (!$article) {
            throw new Exception("Article not found by ID");
        }
        
        if ($article['title'] !== $data['title']) {
            throw new Exception("Retrieved article title mismatch");
        }
        
        // Test getting non-existent article
        $nonExistent = $this->contentManager->getNewsArticle(99999);
        
        if ($nonExistent !== null) {
            throw new Exception("Non-existent article should return null");
        }
        
        // Test getting unpublished article (should return null for public access)
        $unpublishedData = [
            'title' => 'Unpublished Article',
            'content' => 'This should not be accessible',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 0
        ];
        
        $unpublishedId = $this->adminManager->createContent('news_articles', $unpublishedData);
        $this->testArticleIds[] = $unpublishedId;
        
        $unpublishedArticle = $this->contentManager->getNewsArticle($unpublishedId);
        
        if ($unpublishedArticle !== null) {
            throw new Exception("Unpublished article should not be accessible via public method");
        }
    }
    
    public function testUpdateNewsArticle() {
        // Create a test article
        $originalData = [
            'title' => 'Original Title',
            'content' => 'Original content',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $originalData);
        $this->testArticleIds[] = $articleId;
        
        // Add a small delay to ensure different timestamps
        sleep(1);
        
        // Update the article
        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content with more information',
            'published_date' => '2024-01-16 11:00:00',
            'is_published' => 0
        ];
        
        $result = $this->adminManager->updateContent('news_articles', $articleId, $updateData);
        
        if (!$result) {
            throw new Exception("Failed to update news article");
        }
        
        // Verify the update
        $updatedArticle = $this->adminManager->getContentById('news_articles', $articleId);
        
        if ($updatedArticle['title'] !== $updateData['title']) {
            throw new Exception("Article title not updated");
        }
        
        if ($updatedArticle['content'] !== $updateData['content']) {
            throw new Exception("Article content not updated");
        }
        
        if ($updatedArticle['is_published'] != $updateData['is_published']) {
            throw new Exception("Article published status not updated");
        }
        
        // Verify updated_at timestamp was changed
        if ($updatedArticle['updated_at'] === $updatedArticle['created_at']) {
            throw new Exception("Updated timestamp should be different from created timestamp");
        }
    }
    
    public function testTogglePublishedStatus() {
        // Create a published article
        $data = [
            'title' => 'Toggle Test Article',
            'content' => 'Content for toggle test',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $data);
        $this->testArticleIds[] = $articleId;
        
        // Toggle to unpublished
        $result = $this->adminManager->toggleActiveStatus('news_articles', $articleId, 'is_published');
        
        if (!$result) {
            throw new Exception("Failed to toggle published status");
        }
        
        // Verify it's now unpublished
        $article = $this->adminManager->getContentById('news_articles', $articleId);
        
        if ($article['is_published'] != 0) {
            throw new Exception("Article should be unpublished after toggle");
        }
        
        // Toggle back to published
        $result = $this->adminManager->toggleActiveStatus('news_articles', $articleId, 'is_published');
        
        if (!$result) {
            throw new Exception("Failed to toggle published status back");
        }
        
        // Verify it's published again
        $article = $this->adminManager->getContentById('news_articles', $articleId);
        
        if ($article['is_published'] != 1) {
            throw new Exception("Article should be published after second toggle");
        }
    }
    
    public function testDeleteNewsArticle() {
        // Create a test article
        $data = [
            'title' => 'Delete Test Article',
            'content' => 'This article will be deleted',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $data);
        
        // Verify it exists
        $article = $this->adminManager->getContentById('news_articles', $articleId);
        
        if (!$article) {
            throw new Exception("Test article was not created");
        }
        
        // Delete the article
        $result = $this->adminManager->deleteContent('news_articles', $articleId);
        
        if (!$result) {
            throw new Exception("Failed to delete news article");
        }
        
        // Verify it's gone
        $deletedArticle = $this->adminManager->getContentById('news_articles', $articleId);
        
        if ($deletedArticle !== null) {
            throw new Exception("Article should be null after deletion");
        }
        
        // Don't add to cleanup array since it's already deleted
    }
    
    public function testNewsDisplayOnPublicPage() {
        // Clear existing articles first
        $this->cleanup();
        $this->db->execute('DELETE FROM news_articles');
        
        // Create test articles
        $publishedData = [
            'title' => 'Published Article for Display',
            'content' => 'This should appear on the public page',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $draftData = [
            'title' => 'Draft Article',
            'content' => 'This should NOT appear on the public page',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 0
        ];
        
        $publishedId = $this->adminManager->createContent('news_articles', $publishedData);
        $draftId = $this->adminManager->createContent('news_articles', $draftData);
        
        $this->testArticleIds[] = $publishedId;
        $this->testArticleIds[] = $draftId;
        
        // Test that only published articles appear in public methods
        $recentNews = $this->contentManager->getRecentNews(10);
        $publishedTitles = array_column($recentNews, 'title');
        
        if (!in_array($publishedData['title'], $publishedTitles)) {
            throw new Exception("Published article should appear in recent news");
        }
        
        if (in_array($draftData['title'], $publishedTitles)) {
            throw new Exception("Draft article should NOT appear in recent news");
        }
        
        // Test pagination results
        $paginatedNews = $this->contentManager->getNewsWithPagination(1, 10);
        $paginatedTitles = array_column($paginatedNews['articles'], 'title');
        
        if (!in_array($publishedData['title'], $paginatedTitles)) {
            throw new Exception("Published article should appear in paginated news");
        }
        
        if (in_array($draftData['title'], $paginatedTitles)) {
            throw new Exception("Draft article should NOT appear in paginated news");
        }
    }
    
    public function testEmptyNewsState() {
        // Clear all test articles first
        $this->cleanup();
        
        // Also clear any other articles that might exist
        $this->db->execute('DELETE FROM news_articles');
        
        // Test empty state for recent news
        $recentNews = $this->contentManager->getRecentNews(5);
        
        if (!is_array($recentNews)) {
            throw new Exception("Recent news should return an array even when empty");
        }
        
        if (!empty($recentNews)) {
            throw new Exception("Recent news should be empty when no articles exist");
        }
        
        // Test empty state for pagination
        $paginatedNews = $this->contentManager->getNewsWithPagination(1, 5);
        
        if (!is_array($paginatedNews['articles'])) {
            throw new Exception("Paginated news articles should return an array even when empty");
        }
        
        if (!empty($paginatedNews['articles'])) {
            throw new Exception("Paginated news articles should be empty when no articles exist");
        }
        
        if ($paginatedNews['total_pages'] != 0) {
            throw new Exception("Total pages should be 0 when no articles exist, got " . $paginatedNews['total_pages'] . " (type: " . gettype($paginatedNews['total_pages']) . ")");
        }
        
        if ($paginatedNews['total_items'] !== 0) {
            throw new Exception("Total items should be 0 when no articles exist");
        }
    }
    
    public function testNewsPagination() {
        // Clear existing articles first
        $this->cleanup();
        $this->db->execute('DELETE FROM news_articles');
        
        // Create exactly 12 articles to test pagination boundaries
        for ($i = 1; $i <= 12; $i++) {
            $data = [
                'title' => "Pagination Boundary Test $i",
                'content' => "Content $i",
                'published_date' => date('Y-m-d H:i:s', strtotime("-$i hours")),
                'is_published' => 1
            ];
            
            $id = $this->adminManager->createContent('news_articles', $data);
            if ($id) {
                $this->testArticleIds[] = $id;
            }
        }
        
        // Test with 5 items per page (should have 3 pages)
        $page1 = $this->contentManager->getNewsWithPagination(1, 5);
        $page2 = $this->contentManager->getNewsWithPagination(2, 5);
        $page3 = $this->contentManager->getNewsWithPagination(3, 5);
        $page4 = $this->contentManager->getNewsWithPagination(4, 5); // Should be empty
        
        if (count($page1['articles']) !== 5) {
            throw new Exception("Page 1 should have 5 articles");
        }
        
        if (count($page2['articles']) !== 5) {
            throw new Exception("Page 2 should have 5 articles");
        }
        
        if (count($page3['articles']) !== 2) {
            throw new Exception("Page 3 should have 2 articles");
        }
        
        if (!empty($page4['articles'])) {
            throw new Exception("Page 4 should be empty");
        }
        
        if ($page1['total_pages'] != 3) {
            throw new Exception("Should have exactly 3 pages, got " . $page1['total_pages'] . " (type: " . gettype($page1['total_pages']) . ")");
        }
        
        if ($page1['total_items'] !== 12) {
            throw new Exception("Should have exactly 12 total items");
        }
    }
    
    public function testNewsValidation() {
        // Test missing required fields
        try {
            $invalidData = [
                'content' => 'Content without title',
                'published_date' => '2024-01-15 10:00:00',
                'is_published' => 1
            ];
            
            $result = $this->adminManager->createContent('news_articles', $invalidData);
            
            if ($result) {
                $this->testArticleIds[] = $result;
                throw new Exception("Should not create article without title");
            }
        } catch (Exception $e) {
            // Expected to fail
            if (strpos($e->getMessage(), 'Title is required') === false) {
                throw new Exception("Should fail with 'Title is required' message");
            }
        }
        
        try {
            $invalidData = [
                'title' => 'Title without content',
                'published_date' => '2024-01-15 10:00:00',
                'is_published' => 1
            ];
            
            $result = $this->adminManager->createContent('news_articles', $invalidData);
            
            if ($result) {
                $this->testArticleIds[] = $result;
                throw new Exception("Should not create article without content");
            }
        } catch (Exception $e) {
            // Expected to fail
            if (strpos($e->getMessage(), 'Content is required') === false) {
                throw new Exception("Should fail with 'Content is required' message");
            }
        }
        
        // Test data sanitization
        $data = [
            'title' => '<script>alert("xss")</script>Safe Title',
            'content' => '<script>alert("xss")</script>Safe content with <strong>allowed</strong> HTML',
            'published_date' => '2024-01-15 10:00:00',
            'is_published' => 1
        ];
        
        $articleId = $this->adminManager->createContent('news_articles', $data);
        
        if (!$articleId) {
            throw new Exception("Failed to create article with HTML content");
        }
        
        $this->testArticleIds[] = $articleId;
        
        $article = $this->adminManager->getContentById('news_articles', $articleId);
        
        // Check that dangerous HTML was escaped
        if (strpos($article['title'], '<script>') !== false) {
            throw new Exception("Script tags should be escaped in title");
        }
        
        if (strpos($article['content'], '<script>') !== false) {
            throw new Exception("Script tags should be escaped in content");
        }
    }
    
    private function cleanup() {
        foreach ($this->testArticleIds as $id) {
            try {
                $this->adminManager->deleteContent('news_articles', $id);
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->testArticleIds = [];
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $test = new NewsSystemTest();
        $success = $test->runAllTests();
        exit($success ? 0 : 1);
    } catch (Exception $e) {
        echo "Test setup failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>