<?php
/**
 * Admin Dashboard
 * Main admin interface - requires authentication
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = AuthManager::getInstance();

// Require authentication
$auth->requireAuth();

// Get current user info
$currentUser = $auth->getCurrentUser();

// Get content statistics
$adminManager = new AdminManager();

// Get statistics for dashboard
$stats = [
    'featured_prints' => $adminManager->getAllContent('featured_prints', 1, 1000)['total_items'],
    'craft_shows' => $adminManager->getAllContent('craft_shows', 1, 1000)['total_items'],
    'news_articles' => $adminManager->getAllContent('news_articles', 1, 1000)['total_items'],
    'active_featured_prints' => 0,
    'upcoming_shows' => 0,
    'published_articles' => 0
];

// Get more detailed statistics
try {
    $db = DatabaseManager::getInstance();
    
    // Active featured prints
    $result = $db->queryOne("SELECT COUNT(*) as count FROM featured_prints WHERE is_active = 1");
    $stats['active_featured_prints'] = $result ? (int)$result['count'] : 0;
    
    // Upcoming shows
    $result = $db->queryOne("SELECT COUNT(*) as count FROM craft_shows WHERE is_active = 1 AND event_date >= date('now')");
    $stats['upcoming_shows'] = $result ? (int)$result['count'] : 0;
    
    // Published articles
    $result = $db->queryOne("SELECT COUNT(*) as count FROM news_articles WHERE is_published = 1");
    $stats['published_articles'] = $result ? (int)$result['count'] : 0;
    
    // Recent activity (last 7 days)
    $recentActivity = [];
    
    // Recent featured prints
    $recentPrints = $db->query("SELECT title, created_at FROM featured_prints WHERE created_at >= datetime('now', '-7 days') ORDER BY created_at DESC LIMIT 5");
    foreach ($recentPrints as $print) {
        $recentActivity[] = [
            'type' => 'featured_print',
            'title' => $print['title'],
            'action' => 'Created featured print',
            'date' => $print['created_at']
        ];
    }
    
    // Recent shows
    $recentShows = $db->query("SELECT title, created_at FROM craft_shows WHERE created_at >= datetime('now', '-7 days') ORDER BY created_at DESC LIMIT 5");
    foreach ($recentShows as $show) {
        $recentActivity[] = [
            'type' => 'craft_show',
            'title' => $show['title'],
            'action' => 'Created craft show',
            'date' => $show['created_at']
        ];
    }
    
    // Recent articles
    $recentArticles = $db->query("SELECT title, created_at FROM news_articles WHERE created_at >= datetime('now', '-7 days') ORDER BY created_at DESC LIMIT 5");
    foreach ($recentArticles as $article) {
        $recentActivity[] = [
            'type' => 'news_article',
            'title' => $article['title'],
            'action' => 'Created news article',
            'date' => $article['created_at']
        ];
    }
    
    // Sort recent activity by date
    usort($recentActivity, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    $recentActivity = array_slice($recentActivity, 0, 10);
    
} catch (Exception $e) {
    error_log("Dashboard statistics error: " . $e->getMessage());
    $recentActivity = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars(SITE_NAME); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info span {
            color: #666;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .welcome-card h2 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .welcome-card p {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .nav-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            transition: transform 0.2s;
        }
        
        .nav-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .nav-card h3 {
            margin-bottom: 0.5rem;
            color: #667eea;
        }
        
        .nav-card p {
            color: #666;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .activity-card h3 {
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .activity-action {
            font-size: 14px;
            color: #666;
        }
        
        .activity-date {
            font-size: 12px;
            color: #999;
            white-space: nowrap;
            margin-left: 1rem;
        }
        
        .activity-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .activity-type.featured_print {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .activity-type.craft_show {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .activity-type.news_article {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        
        .empty-activity {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo htmlspecialchars(SITE_NAME); ?> Admin</h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="welcome-card">
            <h2>Dashboard</h2>
            <p><strong>Last Login:</strong> <?php echo $currentUser['last_login'] ? date('M j, Y g:i A', strtotime($currentUser['last_login'])) : 'First login'; ?></p>
            <p><strong>Account Created:</strong> <?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></p>
        </div>
        
        <!-- Content Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_featured_prints']; ?></div>
                <div class="stat-label">Active Featured Prints</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['upcoming_shows']; ?></div>
                <div class="stat-label">Upcoming Shows</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['published_articles']; ?></div>
                <div class="stat-label">Published Articles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['featured_prints'] + $stats['craft_shows'] + $stats['news_articles']; ?></div>
                <div class="stat-label">Total Content Items</div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="activity-card">
            <h3>Recent Activity (Last 7 Days)</h3>
            <?php if (!empty($recentActivity)): ?>
                <ul class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title">
                                    <span class="activity-type <?php echo $activity['type']; ?>">
                                        <?php echo str_replace('_', ' ', $activity['type']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($activity['title']); ?>
                                </div>
                                <div class="activity-action"><?php echo $activity['action']; ?></div>
                            </div>
                            <div class="activity-date">
                                <?php echo formatDateTime($activity['date'], 'M j, g:i A'); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-activity">
                    No recent activity in the last 7 days.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="nav-grid">
            <a href="/admin/manage/featured-prints.php" class="nav-card">
                <h3>Featured Prints</h3>
                <p>Manage the featured print displayed on the homepage</p>
            </a>
            
            <a href="/admin/manage/uploads.php" class="nav-card">
                <h3>File Uploads</h3>
                <p>Upload and manage images and files</p>
            </a>
            
            <a href="/admin/manage/craft-shows.php" class="nav-card">
                <h3>Craft Shows</h3>
                <p>Add and manage upcoming craft show events</p>
            </a>
            
            <a href="/admin/manage/news-articles.php" class="nav-card">
                <h3>News & Updates</h3>
                <p>Create and publish news articles and updates</p>
            </a>
            
            <a href="/admin/settings/design.php" class="nav-card">
                <h3>Design Settings</h3>
                <p>Customize the website's appearance and styling</p>
            </a>
            
            <a href="/admin/settings/general.php" class="nav-card">
                <h3>General Settings</h3>
                <p>Configure site settings and preferences</p>
            </a>
            
            <a href="/" class="nav-card">
                <h3>View Website</h3>
                <p>Visit the public website in a new tab</p>
            </a>
        </div>
    </div>
</body>
</html>