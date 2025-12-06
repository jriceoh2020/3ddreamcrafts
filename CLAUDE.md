# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

3DDreamCrafts is a PHP-based craft vendor website featuring:
- Public-facing pages for showcasing 3D printed crafts and event listings
- Admin panel for content management (featured prints, news articles, craft shows)
- SQLite database backend with comprehensive security and caching systems

**Tech Stack**: PHP 8.x, SQLite 3, vanilla JavaScript/CSS (no frameworks)

## Directory Structure

```
public/              # Public web root - configure web server to serve from here
├── index.php        # Homepage
├── shows.php        # Craft shows listing
├── news.php         # News articles
├── assets/          # CSS, JS, images
└── uploads/         # User-uploaded content (must be writable)

admin/               # Admin panel (protected by authentication)
├── index.php        # Admin dashboard
├── login.php        # Admin login
├── manage/          # Content management pages
│   ├── featured-prints.php
│   ├── news-articles.php
│   ├── craft-shows.php
│   └── uploads.php
└── settings/        # Site configuration pages

includes/            # Core PHP classes and utilities
├── config.php       # ConfigManager class + application constants
├── database.php     # DatabaseManager class (singleton)
├── auth.php         # AuthManager class - authentication & sessions
├── security.php     # SecurityManager class - rate limiting, input validation
├── content.php      # ContentManager class - public content retrieval
├── cache.php        # CacheManager class - file-based caching
├── performance.php  # PerformanceMonitor class - metrics tracking
├── upload.php       # File upload handling
└── functions.php    # Utility functions

database/
├── craftsite.db     # SQLite database (auto-created by deployment scripts)
├── schema.sql       # Database schema definition
├── deploy.sh/.bat   # Database initialization scripts
└── design_backups/  # Automatic design backups

scripts/             # Shell scripts for operations
cache/               # File-based cache storage (must be writable)
config/              # Configuration files (PHP ini, crontab)
tests/               # Test suites and documentation
```

## Common Commands

### Database Setup

Initialize the database from scratch:
```bash
# On Unix/Linux/macOS
./database/deploy.sh

# On Windows
database\deploy.bat

# Via PHP (alternative)
php database/init_database.php
```

### Verification

Verify installation and setup:
```bash
php verify_setup.php
```

### Maintenance Scripts

```bash
# Backup database
./scripts/backup_database.sh

# Restore database
./scripts/restore_database.sh

# Health check
./scripts/health_check.sh

# System monitoring
./scripts/monitor.sh

# Apply performance optimizations
php database/apply_optimizations.php
```

### Testing

Run test suites:
```bash
# Individual test categories
php tests/run_tests.php
php tests/integration_test.php
php tests/comprehensive_test_suite.php
php tests/cross_browser_compatibility_test.php
php tests/mobile_responsiveness_test.php
php tests/automated_security_scan_test.php

# Verify database functionality
php tests/verify_database.php
```

### Development Server

```bash
# Start PHP built-in server from public directory
php -S localhost:8000 -t public/
```

## Architecture Patterns

### Singleton Pattern for Core Managers

All core manager classes use the singleton pattern:
```php
$db = DatabaseManager::getInstance();
$config = ConfigManager::getInstance();
$auth = AuthManager::getInstance();
$security = SecurityManager::getInstance();
```

### Database Access Layer

All database operations go through `DatabaseManager`:
- `query($sql, $params)` - SELECT queries, returns array of rows
- `queryOne($sql, $params)` - Single row SELECT, returns single array
- `execute($sql, $params)` - INSERT/UPDATE/DELETE, returns last insert ID
- All queries use prepared statements (NEVER concatenate SQL)

### Configuration System

Two-tier configuration:
1. **Constants** (`includes/config.php`): Application-wide constants (DB_PATH, SESSION_TIMEOUT, etc.)
2. **ConfigManager**: Database-backed dynamic settings accessible via `$config->get()` / `$config->set()`

### Security Layer

Multiple security mechanisms:
- **CSRF Protection**: Automatic token generation and validation via `SecurityManager::generateCSRFToken()` / `validateCSRFToken()`
- **Rate Limiting**: Login attempt tracking per IP address (MAX_LOGIN_ATTEMPTS, LOGIN_RATE_LIMIT_WINDOW)
- **Input Sanitization**: All user input passes through `SecurityManager::sanitizeInput()` and `escapeOutput()`
- **Session Security**: Automatic session regeneration, httponly cookies, strict mode
- **XSS Prevention**: Always use `htmlspecialchars()` or `SecurityManager::escapeOutput()` for output

### Caching System

Two-level caching:
1. **CacheManager**: Generic file-based cache with TTL support
2. **ContentCache**: Specialized cache for database content (featured prints, news, shows)

Cache invalidation happens automatically on content updates in admin panel.

### Authentication Flow

1. User submits login via `admin/login.php`
2. `AuthManager::login($username, $password)` validates credentials
3. Rate limiting checked via `SecurityManager::checkRateLimit()`
4. On success: session created, security log entry, redirect to dashboard
5. Session timeout: SESSION_TIMEOUT (default 3600 seconds)
6. Session regeneration: Every SESSION_REGENERATE_INTERVAL (300 seconds)

## Database Schema

Core tables (see `database/schema.sql` for full schema):
- `admin_users` - Admin authentication (username, password_hash)
- `featured_prints` - Homepage featured print carousel
- `craft_shows` - Event listings (title, event_date, location)
- `news_articles` - Blog/news content
- `settings` - Dynamic configuration key-value pairs
- `login_attempts` - Rate limiting (auto-created by SecurityManager)
- `security_log` - Security event tracking (auto-created by SecurityManager)

### Default Admin Credentials (Change Immediately!)
- Username: `admin`
- Password: `admin123`

## Important Configuration Constants

See `includes/config.php` for all constants:
- `DB_PATH`: Database file location
- `UPLOAD_PATH`: User upload directory
- `MAX_UPLOAD_SIZE`: 5MB default
- `ALLOWED_IMAGE_TYPES`: ['jpg', 'jpeg', 'png', 'gif', 'webp']
- `SESSION_TIMEOUT`: 3600 seconds (1 hour)
- `MAX_LOGIN_ATTEMPTS`: 5 attempts
- `LOGIN_RATE_LIMIT_WINDOW`: 900 seconds (15 minutes)
- `DEBUG_MODE`: Set to `false` for production

## Security Best Practices

When modifying code:
1. **Never concatenate user input into SQL** - Always use prepared statements via DatabaseManager
2. **Always validate CSRF tokens** on state-changing operations (POST/PUT/DELETE)
3. **Escape all output** - Use `htmlspecialchars()` or `SecurityManager::escapeOutput()`
4. **Rate limit sensitive operations** - Check `SecurityManager::checkRateLimit()` before authentication
5. **Log security events** - Use `SecurityManager::logSecurityEvent()` for audit trail
6. **Validate file uploads** - Use `includes/upload.php` functions, never trust client-provided filenames/types
7. **Check authentication** - Verify `AuthManager::isAuthenticated()` before admin operations

## Performance Considerations

- **Database**: Uses SQLite WAL mode for better concurrency
- **Caching**: Content is cached with configurable TTL; invalidates on updates
- **Performance Monitoring**: `PerformanceMonitor` tracks page load times, queries, memory usage
- **Optimization**: Run `database/apply_optimizations.php` to apply database indices and optimizations

## File Permissions

Production deployment requires:
- `database/` directory: writable by web server
- `public/uploads/` directory: writable by web server
- `cache/` directory: writable by web server
- `logs/` directory: writable by web server (auto-created)

## Web Server Configuration

Configure web server to:
1. Serve from `public/` directory as document root
2. Route all requests through appropriate PHP files
3. Deny direct access to `includes/`, `database/`, `cache/`, `config/`, `scripts/`, `tests/`
4. Enable `.htaccess` overrides for Apache (sample files present in `public/.htaccess`, `apache/` directory)
5. For nginx, use configuration in `nginx/` directory

## Testing Infrastructure

Comprehensive test coverage includes:
- Unit tests for all core manager classes
- Integration tests for complete workflows
- Cross-browser compatibility tests
- Mobile responsiveness tests
- Automated security scanning
- User acceptance test scenarios (see `tests/user_acceptance_test_scenarios.md`)

All tests use a separate test database to avoid affecting production data.

## Error Handling

- **Debug Mode** (development): Full error display via `DEBUG_MODE = true`
- **Production**: Errors logged to `logs/` directory, generic messages to users
- Error handler in `includes/error-handler.php` captures and logs all errors
- Security events logged separately via `SecurityManager::logSecurityEvent()`

## Content Management Workflow

All content managed through admin panel:
1. Login at `/admin/login.php`
2. Dashboard at `/admin/index.php` shows content overview
3. Content management:
   - Featured Prints: `/admin/manage/featured-prints.php`
   - News Articles: `/admin/manage/news-articles.php`
   - Craft Shows: `/admin/manage/craft-shows.php`
   - Uploads: `/admin/manage/uploads.php`
4. Cache automatically invalidated on content updates
5. Changes reflect immediately on public pages

## Design System Backups

The system automatically backs up design configurations:
- Location: `database/design_backups/`
- Automatic backups on design changes
- Restore via `includes/design-backup.php` functions
