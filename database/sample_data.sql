-- Sample data for 3DDreamCrafts website
-- Insert default settings and sample content

-- Site configuration settings
INSERT OR IGNORE INTO settings (setting_name, setting_value) VALUES
('site_title', '3DDreamCrafts'),
('site_description', 'Quality 3D printed crafts for your home and gifts'),
('facebook_url', 'https://facebook.com/3ddreamcrafts'),
('instagram_url', 'https://instagram.com/3ddreamcrafts'),
('primary_color', '#2c3e50'),
('secondary_color', '#3498db'),
('font_family', 'Arial, sans-serif');

-- Sample featured print
INSERT OR IGNORE INTO featured_prints (title, description, image_path, is_active) VALUES
('Dragon Figurine', 'Beautifully detailed dragon figurine printed in high-quality PLA plastic. Perfect for fantasy enthusiasts and collectors.', 'uploads/dragon-figurine.jpg', 1);

-- Sample craft shows
INSERT OR IGNORE INTO craft_shows (title, event_date, location, description, is_active) VALUES
('Spring Craft Fair', '2025-04-15', 'Community Center, Main Street', 'Join us at the annual Spring Craft Fair featuring local artisans and makers.', 1),
('Makers Market', '2025-05-20', 'City Park Pavilion', 'Monthly makers market showcasing handmade goods and 3D printed items.', 1),
('Summer Festival', '2025-07-04', 'Downtown Square', 'Independence Day festival with crafts, food, and entertainment.', 1);

-- Sample news articles
INSERT OR IGNORE INTO news_articles (title, content, published_date, is_published) VALUES
('Welcome to 3DDreamCrafts!', 'We are excited to launch our new website! Here you can stay updated on our latest creations, upcoming craft shows, and special announcements. Follow us on social media for daily updates and behind-the-scenes content.', '2025-01-15 10:00:00', 1),
('New Dragon Collection Available', 'Our popular dragon figurine collection has been expanded with three new designs! These intricate pieces are perfect for collectors and make great gifts. Visit us at our next craft show to see them in person.', '2025-01-20 14:30:00', 1),
('Spring Craft Fair Announcement', 'Mark your calendars! We will be participating in the Spring Craft Fair on April 15th at the Community Center. Come see our latest creations and meet the team behind 3DDreamCrafts.', '2025-02-01 09:00:00', 1);

-- Default admin user (password: admin123 - MUST be changed in production)
-- Password hash for 'admin123' using PHP password_hash()
INSERT OR IGNORE INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');