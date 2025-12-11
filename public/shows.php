<?php
/**
 * 3DDreamCrafts Website - Craft Shows Page
 * Display upcoming craft shows with chronological ordering
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/content.php';

// Initialize content manager and get data
$contentManager = new ContentManager();
$settings = $contentManager->getSettings();

// Get all upcoming shows (no limit for the dedicated shows page)
$upcomingShows = $contentManager->getUpcomingShows(50); // Get up to 50 shows

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
    <title>Craft Shows - <?php echo htmlspecialchars($siteTitle); ?></title>
    <meta name="description" content="Upcoming craft shows where you can find <?php echo htmlspecialchars($siteTitle); ?>. Visit us at local events and see our latest 3D printed creations.">
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
            border-bottom: 2px solid white;
            padding-bottom: 2px;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, <?php echo $themeColor; ?> 0%, <?php echo $accentColor; ?> 100%);
            color: white;
            text-align: center;
            padding: 3rem 2rem;
        }

        .page-header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header-text {
            flex: 1;
        }

        .hero-logo {
            max-height: 120px;
            max-width: 200px;
            height: auto;
            width: auto;
            object-fit: contain;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .main-content {
            padding: 3rem 0;
        }
        
        /* Shows Grid */
        .shows-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .show-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid <?php echo $themeColor; ?>;
        }
        
        .show-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .show-date {
            display: inline-block;
            background: <?php echo $accentColor; ?>;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .show-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: <?php echo $themeColor; ?>;
            margin-bottom: 1rem;
        }
        
        .show-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 1rem;
            margin-bottom: 1rem;
        }
        
        .show-location::before {
            content: "📍";
            font-size: 1.1rem;
        }
        
        .show-description {
            color: #555;
            line-height: 1.6;
            margin-top: 1rem;
        }
        
        .show-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: #666;
        }
        
        .days-until {
            font-weight: bold;
            color: <?php echo $accentColor; ?>;
        }
        
        /* Empty State */
        .no-shows {
            text-align: center;
            background: white;
            border-radius: 12px;
            padding: 4rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .no-shows-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .no-shows h2 {
            color: <?php echo $themeColor; ?>;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        
        .no-shows p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .back-home {
            display: inline-block;
            padding: 1rem 2rem;
            background: <?php echo $themeColor; ?>;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .back-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
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

            .page-header-content {
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

            .shows-grid {
                grid-template-columns: 1fr;
            }
            
            .show-card {
                padding: 1.5rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .show-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                padding: 2rem 1rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }

            .hero-logo {
                max-height: 60px;
            }

            .shows-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .show-card {
                padding: 1rem;
            }
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
                    <li><a href="/shows.php" class="active">Craft Shows</a></li>
                    <li><a href="/news.php">News</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Page Header -->
    <section class="page-header">
        <div class="page-header-content">
            <?php if (!empty($siteLogo)): ?>
                <img src="<?php echo htmlspecialchars($siteLogo); ?>"
                     alt="<?php echo htmlspecialchars($siteTitle); ?> Logo"
                     class="hero-logo">
            <?php endif; ?>
            <div class="page-header-text">
                <h1>Upcoming Craft Shows</h1>
                <p>Find us at these local events and see our latest 3D printed creations in person!</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container main-content">
        <?php if (!empty($upcomingShows)): ?>
            <div class="shows-grid">
                <?php foreach ($upcomingShows as $show): ?>
                    <div class="show-card">
                        <div class="show-date">
                            <?php echo date('F j, Y', strtotime($show['event_date'])); ?>
                        </div>
                        
                        <h2 class="show-title"><?php echo htmlspecialchars($show['title']); ?></h2>
                        
                        <div class="show-location">
                            <?php echo htmlspecialchars($show['location']); ?>
                        </div>
                        
                        <?php if (!empty($show['description'])): ?>
                            <div class="show-description">
                                <?php echo nl2br(htmlspecialchars($show['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="show-meta">
                            <span class="created-date">
                                Added <?php echo date('M j, Y', strtotime($show['created_at'])); ?>
                            </span>
                            <span class="days-until">
                                <?php 
                                $eventDate = new DateTime($show['event_date']);
                                $today = new DateTime();
                                $daysUntil = $today->diff($eventDate)->days;
                                
                                if ($eventDate > $today) {
                                    if ($daysUntil == 0) {
                                        echo "Today!";
                                    } elseif ($daysUntil == 1) {
                                        echo "Tomorrow";
                                    } else {
                                        echo $daysUntil . " days away";
                                    }
                                } else {
                                    echo "Event passed";
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-shows">
                <div class="no-shows-icon">📅</div>
                <h2>No Upcoming Shows</h2>
                <p>We don't have any craft shows scheduled at the moment, but check back soon! We're always looking for new events to participate in.</p>
                <a href="/" class="back-home">Back to Home</a>
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