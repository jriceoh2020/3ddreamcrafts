<?php
/**
 * Database initialization script for 3DDreamCrafts website
 * Creates SQLite database with all required tables and sample data
 */

// Database file path
$dbPath = __DIR__ . '/craftsite.db';

try {
    // Create SQLite connection
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating database tables...\n";
    
    // Create settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_name TEXT UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create featured_prints table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS featured_prints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            image_path TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create craft_shows table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS craft_shows (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            event_date DATE NOT NULL,
            location TEXT NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create news_articles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS news_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            published_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_published BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create admin_users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "Tables created successfully!\n";
    echo "Inserting sample data...\n";
    
    // Insert sample settings
    $pdo->exec("
        INSERT OR IGNORE INTO settings (setting_name, setting_value) VALUES
        ('site_title', '3DDreamCrafts'),
        ('site_description', 'Quality 3D printed crafts for your home and gifts'),
        ('facebook_url', 'https://facebook.com/3ddreamcrafts'),
        ('instagram_url', 'https://instagram.com/3ddreamcrafts'),
        ('primary_color', '#2c3e50'),
        ('secondary_color', '#3498db'),
        ('font_family', 'Arial, sans-serif')
    ");
    
    // Insert sample featured print
    $pdo->exec("
        INSERT OR IGNORE INTO featured_prints (title, description, image_path, is_active) VALUES
        ('Dragon Figurine', 'Beautifully detailed dragon figurine printed in high-quality PLA plastic. Perfect for fantasy enthusiasts and collectors.', 'uploads/dragon-figurine.jpg', 1)
    ");
    
    // Insert sample craft shows
    $pdo->exec("
        INSERT OR IGNORE INTO craft_shows (title, event_date, location, description, is_active) VALUES
        ('Spring Craft Fair', '2025-04-15', 'Community Center, Main Street', 'Join us at the annual Spring Craft Fair featuring local artisans and makers.', 1),
        ('Makers Market', '2025-05-20', 'City Park Pavilion', 'Monthly makers market showcasing handmade goods and 3D printed items.', 1),
        ('Summer Festival', '2025-07-04', 'Downtown Square', 'Independence Day festival with crafts, food, and entertainment.', 1)
    ");
    
    // Insert sample news articles
    $pdo->exec("
        INSERT OR IGNORE INTO news_articles (title, content, published_date, is_published) VALUES
        ('Welcome to 3DDreamCrafts!', 'We are excited to launch our new website! Here you can stay updated on our latest creations, upcoming craft shows, and special announcements. Follow us on social media for daily updates and behind-the-scenes content.', '2025-01-15 10:00:00', 1),
        ('New Dragon Collection Available', 'Our popular dragon figurine collection has been expanded with three new designs! These intricate pieces are perfect for collectors and make great gifts. Visit us at our next craft show to see them in person.', '2025-01-20 14:30:00', 1),
        ('Spring Craft Fair Announcement', 'Mark your calendars! We will be participating in the Spring Craft Fair on April 15th at the Community Center. Come see our latest creations and meet the team behind 3DDreamCrafts.', '2025-02-01 09:00:00', 1)
    ");
    
    // Insert default admin user (password: admin123 - should be changed in production)
    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT OR IGNORE INTO admin_users (username, password_hash) VALUES
        ('admin', '$defaultPassword')
    ");
    
    echo "Sample data inserted successfully!\n";
    echo "Database initialization complete!\n";
    echo "Default admin credentials: username=admin, password=admin123\n";
    echo "IMPORTANT: Change the default password after first login!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>