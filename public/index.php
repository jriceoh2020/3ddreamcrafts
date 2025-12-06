<?php
/**
 * 3DDreamCrafts Website - Main Landing Page
 * Public homepage for the craft vendor website
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/performance.php';

// Initialize performance optimizations
initializePerformanceOptimizations();

// Initialize content manager and get data
$contentManager = new ContentManager();
$settings = $contentManager->getSettings();
$featuredPrint = $contentManager->getFeaturedPrint();
$upcomingShows = $contentManager->getUpcomingShows(3); // Get next 3 shows
$recentNews = $contentManager->getRecentNews(3); // Get latest 3 news items

// Get social media URLs from settings
$config = ConfigManager::getInstance();
$facebookUrl = $config->get('facebook_url', '#');
$instagramUrl = $config->get('instagram_url', '#');

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
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <meta name="description" content="3DDreamCrafts - Custom 3D printed crafts and creations. Visit us at local craft shows and stay updated with our latest news.">
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
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, <?php echo $themeColor; ?> 0%, <?php echo $accentColor; ?> 100%);
            color: white;
            text-align: center;
            padding: 4rem 2rem;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .hero-logo {
            max-height: 120px;
            max-width: 200px;
            height: auto;
            width: auto;
            object-fit: contain;
        }

        .hero-text {
            flex: 1;
            min-width: 300px;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            color: #FFFFFF; /* Gold color - change this to your preferred color */
        }

        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .main-content {
            padding: 3rem 0;
        }
        
        .section {
            margin-bottom: 4rem;
        }
        
        .section h2 {
            color: <?php echo $themeColor; ?>;
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        /* Featured Print Section */
        .featured-print {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .featured-print img {
            max-width: 100%;
            height: auto;
            max-height: 400px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .featured-print h3 {
            color: <?php echo $themeColor; ?>;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .featured-print p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .no-featured {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .content-card h3 {
            color: <?php echo $themeColor; ?>;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }
        
        .show-item, .news-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .show-item:last-child, .news-item:last-child {
            border-bottom: none;
        }
        
        .show-date {
            font-weight: bold;
            color: <?php echo $accentColor; ?>;
            font-size: 0.9rem;
        }
        
        .show-title {
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .show-location {
            color: #666;
            font-size: 0.9rem;
        }
        
        .news-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .news-title {
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .news-excerpt {
            color: #666;
            font-size: 0.95rem;
        }
        
        .view-more {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: <?php echo $themeColor; ?>;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: transform 0.2s;
        }
        
        .view-more:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .no-content {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 1rem;
        }
        
        /* Social Media Section */
        .social-media {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .social-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: <?php echo $themeColor; ?>;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .social-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        .social-link.facebook {
            background: #1877f2;
        }
        
        .social-link.instagram {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
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
            .hero h1 {
                font-size: 2rem;
            }

            .hero-content {
                flex-direction: column;
                text-align: center;
            }

            .hero-logo {
                max-height: 80px;
            }

            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                gap: 1rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .social-links {
                flex-direction: column;
                align-items: center;
            }
            
            .container {
                padding: 0 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 2rem 1rem;
            }

            .hero-logo {
                max-height: 60px;
            }

            .hero h1 {
                font-size: 1.8rem;
            }
            
            .section h2 {
                font-size: 1.5rem;
            }
        }
        
        /* Lazy loading styles */
        .lazy-loading {
            opacity: 0.3;
            transition: opacity 0.3s ease;
        }
        
        .lazy-loading.loaded {
            opacity: 1;
        }
        
        /* Performance optimization styles */
        img {
            will-change: transform;
        }
        
        .content-card {
            contain: layout style paint;
        }
    </style>
</head>
<body>
    <!-- Header with Navigation -->
    <header class="header">
        <div class="nav-container">
            <a href="/" class="logo"><?php echo htmlspecialchars($siteTitle); ?></a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="/">Home</a></li>
                    <li><a href="/shows.php">Craft Shows</a></li>
                    <li><a href="/news.php">News</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?php echo htmlspecialchars($siteLogo); ?>"
                     alt="<?php echo htmlspecialchars($siteTitle); ?> Logo"
                     class="hero-logo">
            <?php endif; ?>
            <div class="hero-text">
                <h1>Welcome to <?php echo htmlspecialchars($siteTitle); ?></h1>
                <p>Custom 3D printed crafts and creations. Discover unique items and visit us at local shows and festivals.</p>
            </div>
        </div>
    </section>

    <!-- Social Media Section -->
    <section class="section">
        <div class="container">
            <div class="social-media">
                <h2>Follow Us</h2>
                <p>Stay connected and see more of our work on social media!</p>
                <div class="social-links">
                    <?php if ($facebookUrl && $facebookUrl !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($facebookUrl); ?>" target="_blank" rel="noopener" class="social-link facebook">
                            <span>📘</span> Facebook
                        </a>
                    <?php endif; ?>
                    <?php if ($instagramUrl && $instagramUrl !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($instagramUrl); ?>" target="_blank" rel="noopener" class="social-link instagram">
                            <span>📷</span> Instagram
                        </a>
                    <?php endif; ?>
                    <?php if (($facebookUrl === '#' || !$facebookUrl) && ($instagramUrl === '#' || !$instagramUrl)): ?>
                        <p style="color: #666; font-style: italic;">Social media links will be available soon!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container main-content">
        <!-- Content Grid: Shows and News -->
        <div class="content-grid">
            <!-- Upcoming Shows -->
            <div class="content-card">
                <h3>Upcoming Craft Shows</h3>
                <?php if (!empty($upcomingShows)): ?>
                    <?php foreach ($upcomingShows as $show): ?>
                        <div class="show-item">
                            <div class="show-date"><?php echo date('F j, Y', strtotime($show['event_date'])); ?></div>
                            <div class="show-title"><?php echo htmlspecialchars($show['title']); ?></div>
                            <div class="show-location"><?php echo htmlspecialchars($show['location']); ?></div>
                            <?php if (!empty($show['description'])): ?>
                                <div class="show-description"><?php echo htmlspecialchars($show['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <a href="/shows.php" class="view-more">View All Shows</a>
                <?php else: ?>
                    <div class="no-content">
                        <p>No upcoming shows scheduled. Check back soon!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent News -->
            <div class="content-card">
                <h3>Latest News</h3>
                <?php if (!empty($recentNews)): ?>
                    <?php foreach ($recentNews as $article): ?>
                        <div class="news-item">
                            <div class="news-date"><?php echo date('F j, Y', strtotime($article['published_date'])); ?></div>
                            <div class="news-title"><?php echo htmlspecialchars($article['title']); ?></div>
                            <div class="news-excerpt">
                                <?php 
                                $excerpt = strip_tags($article['content']);
                                echo htmlspecialchars(strlen($excerpt) > 150 ? substr($excerpt, 0, 150) . '...' : $excerpt); 
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="/news.php" class="view-more">View All News</a>
                <?php else: ?>
                    <div class="no-content">
                        <p>No news articles available. Check back soon for updates!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Featured Print Section -->
        <section class="section">
            <h2>Featured Print</h2>
            <?php if ($featuredPrint): ?>
                <div class="featured-print">
                    <?php if (!empty($featuredPrint['image_path'])): ?>
                        <img src="/<?php echo htmlspecialchars($featuredPrint['image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($featuredPrint['title']); ?>">
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($featuredPrint['title']); ?></h3>
                    <?php if (!empty($featuredPrint['description'])): ?>
                        <p><?php echo htmlspecialchars($featuredPrint['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-featured">
                    <p>No featured print available at the moment. Check back soon for our latest creations!</p>
                </div>
            <?php endif; ?>
        </section>


    </main>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteTitle); ?>. All rights reserved.</p>
        <p>Custom 3D printed crafts and creations</p>
    </footer>
</body>
</html>