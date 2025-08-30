-- Database Optimization Script
-- Adds indexes and optimizations for better performance

-- Index for featured prints active status lookup
CREATE INDEX IF NOT EXISTS idx_featured_prints_active 
ON featured_prints(is_active, updated_at DESC);

-- Index for craft shows date and active status lookup
CREATE INDEX IF NOT EXISTS idx_craft_shows_date_active 
ON craft_shows(is_active, event_date ASC);

-- Index for craft shows event date only (for date range queries)
CREATE INDEX IF NOT EXISTS idx_craft_shows_event_date 
ON craft_shows(event_date);

-- Index for news articles published status and date
CREATE INDEX IF NOT EXISTS idx_news_published_date 
ON news_articles(is_published, published_date DESC);

-- Index for news articles published date only (for pagination)
CREATE INDEX IF NOT EXISTS idx_news_published_date_only 
ON news_articles(published_date DESC);

-- Index for settings lookup by name
CREATE INDEX IF NOT EXISTS idx_settings_name 
ON settings(setting_name);

-- Index for admin users username lookup
CREATE INDEX IF NOT EXISTS idx_admin_users_username 
ON admin_users(username);

-- Analyze tables to update statistics for query optimizer
ANALYZE;

-- Set SQLite optimizations
PRAGMA optimize;