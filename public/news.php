<?php
/**
 * 3DDreamCrafts Website - News and Updates Page
 * Public news page with article listing and pagination
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

// Initialize content manager and get data
$contentManager = new ContentManager();
$settings = $contentManager->getSettings();

// Get page number from URL
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

// Get news articles with pagination
$newsData = $contentManager->getNewsWithPagination($page);
$articles = $newsData['articles'];

$config = ConfigManager::getInstance();
$siteTitle = $settings['site_title'];
$themeColor = $settings['theme_color'];
$accentColor = $settings['accent_color'];
$siteLogo = $config->get('site_logo', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Updates - <?php echo htmlspecialchars($siteTitle); ?></title>
    <meta name="description" content="Stay updated with the latest news and updates from 3DDreamCrafts. Read about new products, craft show experiences, and more.">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/dynamic.php">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }
        
        /* Header and Navigation */
        .header {
            background: linear-gradient(135deg, <?php echo $themeColor; ?> 0%, <?php echo $accentColor; ?> 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        
        .nav-menu a:hover {
            opacity: 0.8;
        }
        
        .nav-menu a.active {
            text-decoration: underline;
        }

        .header-logo {
            max-height: 50px;
            max-width: 150px;
            height: auto;
            width: auto;
            object-fit: contain;
            vertical-align: middle;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, <?php echo $themeColor; ?> 0%, <?php echo $accentColor; ?> 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Main Content */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .main-content {
            padding: 3rem 0;
        }
        
        /* News Articles */
        .news-article {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .news-article:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.15);
        }
        
        .article-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .article-title {
            color: <?php echo $themeColor; ?>;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .article-date {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .article-date::before {
            content: "📅";
        }
        
        .article-content {
            color: #444;
            font-size: 1rem;
            line-height: 1.7;
        }
        
        .article-content p {
            margin-bottom: 1rem;
        }
        
        .article-content p:last-child {
            margin-bottom: 0;
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 10px;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .empty-state h2 {
            color: <?php echo $themeColor; ?>;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            text-decoration: none;
            color: #333;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            min-width: 44px;
            text-align: center;
        }
        
        .pagination a:hover {
            background-color: <?php echo $themeColor; ?>;
            color: white;
            border-color: <?php echo $themeColor; ?>;
            transform: translateY(-1px);
        }
        
        .pagination .current {
            background-color: <?php echo $themeColor; ?>;
            color: white;
            border-color: <?php echo $themeColor; ?>;
        }
        
        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .pagination .disabled:hover {
            background-color: transparent;
            color: #ccc;
            border-color: #e0e0e0;
            transform: none;
        }
        
        .pagination-info {
            text-align: center;
            color: #666;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        /* Back to Home Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: <?php echo $themeColor; ?>;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .back-link:hover {
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .back-link::before {
            content: "←";
            font-size: 1.2rem;
        }
        
        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                gap: 1rem;
            }

            .header-logo {
                max-height: 40px;
                max-width: 120px;
            }

            .container {
                padding: 0 1rem;
            }
            
            .news-article {
                padding: 1.5rem;
            }
            
            .article-title {
                font-size: 1.3rem;
            }
            
            .pagination {
                gap: 0.25rem;
            }
            
            .pagination a,
            .pagination span {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                padding: 2rem 1rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }

            .header-logo {
                max-height: 35px;
                max-width: 100px;
            }

            .news-article {
                padding: 1rem;
            }
            
            .empty-state {
                padding: 3rem 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header with Navigation -->
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo">
                <?php if (!empty($siteLogo)): ?>
                    <img src="<?php echo htmlspecialchars($siteLogo); ?>"
                         alt="<?php echo htmlspecialchars($siteTitle); ?>"
                         class="header-logo">
                <?php else: ?>
                    <?php echo htmlspecialchars($siteTitle); ?>
                <?php endif; ?>
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="/">Home</a></li>
                    <li><a href="/shows.php">Craft Shows</a></li>
                    <li><a href="/news.php" class="active">News</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <h1>News & Updates</h1>
        <p>Stay updated with our latest news, product announcements, and craft show experiences.</p>
    </section>

    <!-- Main Content -->
    <main class="container main-content">
        <a href="/" class="back-link">Back to Home</a>
        
        <?php if (!empty($articles)): ?>
            <!-- News Articles -->
            <?php foreach ($articles as $article): ?>
                <article class="news-article">
                    <header class="article-header">
                        <h2 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h2>
                        <div class="article-date">
                            <?php echo date('F j, Y \a\t g:i A', strtotime($article['published_date'])); ?>
                        </div>
                    </header>
                    <div class="article-content">
                        <?php 
                        // Convert line breaks to paragraphs and allow basic HTML
                        $content = htmlspecialchars($article['content']);
                        $content = nl2br($content);
                        echo $content;
                        ?>
                    </div>
                </article>
            <?php endforeach; ?>
            
            <!-- Pagination -->
            <?php if ($newsData['total_pages'] > 1): ?>
                <nav class="pagination">
                    <?php if ($newsData['current_page'] > 1): ?>
                        <a href="?page=1" title="First page">««</a>
                        <a href="?page=<?php echo $newsData['current_page'] - 1; ?>" title="Previous page">‹</a>
                    <?php else: ?>
                        <span class="disabled">««</span>
                        <span class="disabled">‹</span>
                    <?php endif; ?>
                    
                    <?php
                    // Show page numbers with ellipsis for large page counts
                    $start = max(1, $newsData['current_page'] - 2);
                    $end = min($newsData['total_pages'], $newsData['current_page'] + 2);
                    
                    if ($start > 1) {
                        echo '<a href="?page=1">1</a>';
                        if ($start > 2) {
                            echo '<span class="disabled">…</span>';
                        }
                    }
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i === $newsData['current_page']): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php
                    if ($end < $newsData['total_pages']) {
                        if ($end < $newsData['total_pages'] - 1) {
                            echo '<span class="disabled">…</span>';
                        }
                        echo '<a href="?page=' . $newsData['total_pages'] . '">' . $newsData['total_pages'] . '</a>';
                    }
                    ?>
                    
                    <?php if ($newsData['current_page'] < $newsData['total_pages']): ?>
                        <a href="?page=<?php echo $newsData['current_page'] + 1; ?>" title="Next page">›</a>
                        <a href="?page=<?php echo $newsData['total_pages']; ?>" title="Last page">»»</a>
                    <?php else: ?>
                        <span class="disabled">›</span>
                        <span class="disabled">»»</span>
                    <?php endif; ?>
                </nav>
                
                <div class="pagination-info">
                    Showing page <?php echo $newsData['current_page']; ?> of <?php echo $newsData['total_pages']; ?>
                    (<?php echo $newsData['total_items']; ?> total articles)
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="icon">📰</div>
                <h2>No News Articles Yet</h2>
                <p>We haven't published any news articles yet, but check back soon for updates about our latest creations, craft show experiences, and more!</p>
                <p>In the meantime, feel free to browse our <a href="/shows.php" style="color: <?php echo $themeColor; ?>;">upcoming craft shows</a> or return to the <a href="/" style="color: <?php echo $themeColor; ?>;">homepage</a>.</p>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?>. All rights reserved.</p>
        <p>Custom 3D printed crafts and creations</p>
    </footer>
</body>
</html>