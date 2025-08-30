<?php
/**
 * Cache Management Interface
 * Admin interface for managing website cache
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cache.php';
require_once __DIR__ . '/../../includes/performance.php';

// Require authentication
$auth = new AuthManager();
$auth->requireAuth();

$cache = CacheManager::getInstance();
$contentCache = new ContentCache();
$performanceMonitor = PerformanceMonitor::getInstance();

$message = '';
$messageType = 'info';

// Handle cache operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'clear_all':
                $cleared = $cache->clear();
                $message = "Cleared $cleared cache entries.";
                $messageType = 'success';
                break;
                
            case 'clear_content':
                $contentCache->invalidateContent('all');
                $message = 'Content cache cleared successfully.';
                $messageType = 'success';
                break;
                
            case 'clear_featured':
                $contentCache->invalidateContent('featured_print');
                $message = 'Featured print cache cleared.';
                $messageType = 'success';
                break;
                
            case 'clear_shows':
                $contentCache->invalidateContent('shows');
                $message = 'Craft shows cache cleared.';
                $messageType = 'success';
                break;
                
            case 'clear_news':
                $contentCache->invalidateContent('news');
                $message = 'News cache cleared.';
                $messageType = 'success';
                break;
                
            case 'clear_settings':
                $contentCache->invalidateContent('settings');
                $message = 'Settings cache cleared.';
                $messageType = 'success';
                break;
                
            case 'clean_expired':
                $cleaned = $cache->cleanExpired();
                $message = "Cleaned $cleaned expired cache entries.";
                $messageType = 'success';
                break;
                
            case 'clean_performance_logs':
                $cleaned = $performanceMonitor->cleanOldLogs(30);
                $message = "Cleaned $cleaned old performance log entries.";
                $messageType = 'success';
                break;
        }
    }
}

// Get cache statistics
$cacheStats = $cache->getStats();
$performanceStats = $performanceMonitor->getPerformanceStats(7);

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Management - Admin</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .admin-header {
            background: #2563eb;
            color: white;
            padding: 1rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
        }
        
        .admin-nav {
            margin-bottom: 2rem;
        }
        
        .admin-nav a {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 0.5rem;
        }
        
        .admin-nav a:hover {
            background: #e5e7eb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 1rem 0;
            color: #2563eb;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .action-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .action-card h4 {
            margin: 0 0 1rem 0;
            color: #374151;
        }
        
        .action-card p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-danger {
            background: #dc2626;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        .btn-warning {
            background: #d97706;
        }
        
        .btn-warning:hover {
            background: #b45309;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Cache Management</h1>
            <p>Monitor and manage website caching and performance</p>
        </div>
        
        <div class="admin-nav">
            <a href="/admin/">Dashboard</a>
            <a href="/admin/manage/cache.php">Cache Management</a>
            <a href="/admin/logout.php">Logout</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Cache Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Cache Statistics</h3>
                <div class="stat-item">
                    <span>Total Entries:</span>
                    <strong><?php echo $cacheStats['total_entries']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>Valid Entries:</span>
                    <strong><?php echo $cacheStats['valid_entries']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>Expired Entries:</span>
                    <strong><?php echo $cacheStats['expired_entries']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>Total Size:</span>
                    <strong><?php echo $cacheStats['total_size_mb']; ?> MB</strong>
                </div>
            </div>
            
            <?php if ($performanceStats): ?>
            <div class="stat-card">
                <h3>Performance (Last 7 Days)</h3>
                <div class="stat-item">
                    <span>Total Requests:</span>
                    <strong><?php echo $performanceStats['total_requests']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>Slow Requests:</span>
                    <strong><?php echo $performanceStats['slow_requests']; ?></strong>
                </div>
                <div class="stat-item">
                    <span>Average Load Time:</span>
                    <strong><?php echo number_format($performanceStats['average_load_time'], 3); ?>s</strong>
                </div>
                <div class="stat-item">
                    <span>Max Load Time:</span>
                    <strong><?php echo number_format($performanceStats['max_load_time'], 3); ?>s</strong>
                </div>
                <div class="stat-item">
                    <span>Average Queries:</span>
                    <strong><?php echo number_format($performanceStats['average_queries'], 1); ?></strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Cache Actions -->
        <h2>Cache Actions</h2>
        <div class="actions-grid">
            <div class="action-card">
                <h4>Clear All Cache</h4>
                <p>Remove all cached data. Use when making major changes.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Clear all cache?')">
                        Clear All Cache
                    </button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clear Content Cache</h4>
                <p>Clear cached content data (featured prints, shows, news).</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_content">
                    <button type="submit" class="btn btn-warning">Clear Content Cache</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clear Featured Print Cache</h4>
                <p>Clear only the featured print cache.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_featured">
                    <button type="submit" class="btn">Clear Featured Cache</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clear Shows Cache</h4>
                <p>Clear cached craft shows data.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_shows">
                    <button type="submit" class="btn">Clear Shows Cache</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clear News Cache</h4>
                <p>Clear cached news articles data.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_news">
                    <button type="submit" class="btn">Clear News Cache</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clear Settings Cache</h4>
                <p>Clear cached site settings.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clear_settings">
                    <button type="submit" class="btn">Clear Settings Cache</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clean Expired Cache</h4>
                <p>Remove only expired cache entries.</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clean_expired">
                    <button type="submit" class="btn">Clean Expired</button>
                </form>
            </div>
            
            <div class="action-card">
                <h4>Clean Performance Logs</h4>
                <p>Remove old performance log entries (30+ days).</p>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="clean_performance_logs">
                    <button type="submit" class="btn">Clean Logs</button>
                </form>
            </div>
        </div>
        
        <?php if ($performanceStats && !empty($performanceStats['urls'])): ?>
        <!-- URL Performance -->
        <h2>URL Performance</h2>
        <div class="stat-card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f3f4f6;">
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb;">URL</th>
                        <th style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #e5e7eb;">Requests</th>
                        <th style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #e5e7eb;">Avg Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Sort URLs by average time (slowest first)
                    uasort($performanceStats['urls'], function($a, $b) {
                        return $b['avg_time'] <=> $a['avg_time'];
                    });
                    
                    foreach (array_slice($performanceStats['urls'], 0, 10) as $url => $stats): 
                    ?>
                    <tr>
                        <td style="padding: 0.75rem; border-bottom: 1px solid #f3f4f6;">
                            <?php echo htmlspecialchars($url); ?>
                        </td>
                        <td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #f3f4f6;">
                            <?php echo $stats['count']; ?>
                        </td>
                        <td style="padding: 0.75rem; text-align: right; border-bottom: 1px solid #f3f4f6;">
                            <?php echo number_format($stats['avg_time'], 3); ?>s
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>