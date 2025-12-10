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

public/admin/        # Admin panel (protected by authentication)
├── index.php        # Admin dashboard
├── login.php        # Admin login
├── manage/          # Content management pages
│   ├── featured-prints.php
│   ├── news-articles.php
│   ├── craft-shows.php
│   ├── uploads.php
│   └── cache.php
└── settings/        # Site configuration pages
    ├── general.php
    └── design.php

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
# Core functionality tests
php tests/run_tests.php                          # Database, config, upload tests
php tests/verify_database.php                    # Database connectivity & schema

# Feature-specific tests
php tests/run_content_tests.php                  # Content management tests
php tests/run_news_tests.php                     # News system tests
php tests/run_admin_tests.php                    # Admin dashboard tests
php tests/run_design_tests.php                   # Design system tests
php tests/run_security_tests.php                 # Security features tests
php tests/run_performance_tests.php              # Performance monitoring tests

# Integration and comprehensive tests
php tests/integration_test.php                   # Full system integration
php tests/comprehensive_test_suite.php           # Complete test coverage
php tests/final_integration_test.php             # Final system validation

# Specialized tests
php tests/cross_browser_compatibility_test.php   # Browser compatibility
php tests/mobile_responsiveness_test.php         # Mobile responsiveness
php tests/automated_security_scan_test.php       # Security scanning
```

### Development Server

```bash
# Start PHP built-in server from public directory
php -S localhost:8000 -t public/
```

### Production Deployment (AWS EC2 Ubuntu)

Automated deployment to Ubuntu server:
```bash
# Run the deployment script
sudo ./deploy_to_ubuntu.sh your-domain-or-ip

# See DEPLOYMENT_GUIDE.md for detailed instructions
```

The `deploy_to_ubuntu.sh` script automates:
- Apache and PHP 8.1 installation
- SQLite3 setup
- Directory structure and permissions
- Database initialization
- Virtual host configuration
- Firewall setup (UFW)
- Automated maintenance tasks

## Architecture Patterns

### Singleton Pattern for Core Managers

All core manager classes use the singleton pattern:
```php
$db = DatabaseManager::getInstance();
$config = ConfigManager::getInstance();
$auth = AuthManager::getInstance();
$security = SecurityManager::getInstance();
$cache = CacheManager::getInstance();
```

### Content Management Class Hierarchy

Content is handled through a two-tier class system in `includes/content.php`:
1. **ContentManager**: Read-only public content retrieval (featured prints, news, craft shows)
2. **AdminManager** (extends ContentManager): Full CRUD operations with validation and cache invalidation

The AdminManager provides:
- `createContent($table, $data)` - Create new content with automatic validation
- `updateContent($table, $id, $data)` - Update existing content
- `deleteContent($table, $id)` - Delete content
- `getAllContent($table, $page, $perPage)` - Paginated content listing
- Automatic cache invalidation on all modifications
- Table-specific validation (validateFeaturedPrintData, validateCraftShowData, etc.)

### Database Access Layer

All database operations go through `DatabaseManager` (includes/database.php):
- `query($sql, $params)` - SELECT queries, returns array of rows
- `queryOne($sql, $params)` - Single row SELECT, returns single array
- `execute($sql, $params)` - INSERT/UPDATE/DELETE, returns last insert ID for INSERTs
- `tableExists($tableName)` - Check if a table exists
- `beginTransaction()` / `commit()` / `rollback()` - Transaction support
- All queries use prepared statements (NEVER concatenate SQL)
- SQLite-specific optimizations: WAL mode, NORMAL synchronous mode

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

Two-level caching system in `includes/cache.php`:
1. **CacheManager**: Generic file-based cache with TTL support
   - `set($key, $data, $ttl)` - Store data with time-to-live
   - `get($key)` - Retrieve cached data (returns null if expired)
   - `remember($key, $callback, $ttl)` - Get from cache or execute callback
   - `clear()` / `cleanExpired()` - Cache maintenance
2. **ContentCache**: Specialized cache for database content
   - Caches featured prints, news articles, craft shows
   - Automatic TTL: 30 minutes for content, 1 hour for settings
   - `invalidateContent($type)` - Invalidate specific content type caches

Cache invalidation happens automatically on content updates in admin panel via AdminManager.

### Authentication Flow

Complete authentication flow in `includes/auth.php`:
1. User submits login via `public/admin/login.php`
2. `AuthManager::login($username, $password)` validates credentials
3. Rate limiting checked via `SecurityManager::isRateLimited()`
4. Password verification with timing attack prevention (dummy hash for non-existent users)
5. Suspicious activity detection (rapid requests, multiple usernames from same IP)
6. On success:
   - Session created with `session_regenerate_id()`
   - CSRF token generated
   - Login attempt recorded via `SecurityManager::recordLoginAttempt()`
   - Security event logged
7. Session management:
   - Timeout: SESSION_TIMEOUT (default 3600 seconds)
   - Auto-regeneration: Every SESSION_REGENERATE_INTERVAL (300 seconds)
   - Activity tracking for timeout detection

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

Performance monitoring and optimization in `includes/performance.php`:
- **Database**: SQLite WAL mode for better concurrency, NORMAL synchronous mode
- **Caching**: Content cached with configurable TTL; automatic invalidation on updates
- **Performance Monitoring**: `PerformanceMonitor` singleton tracks:
  - Page load times with threshold logging (default: >3s)
  - Database query counts and execution times
  - Memory usage (current, peak, delta from start)
  - Per-URL performance statistics
  - Logs to `logs/performance.log` when thresholds exceeded
- **Image Optimization**: `ImageOptimizer` class provides:
  - Automatic resizing (max width: 1200px)
  - Quality adjustment (default: 85% for JPEG)
  - WebP conversion support
  - Lazy loading helper functions
- **Database Optimization**: Run `php database/apply_optimizations.php` to apply indices

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
1. Login at `/public/admin/login.php`
2. Dashboard at `/public/admin/index.php` shows content overview
3. Content management:
   - Featured Prints: `/public/admin/manage/featured-prints.php`
   - News Articles: `/public/admin/manage/news-articles.php`
   - Craft Shows: `/public/admin/manage/craft-shows.php`
   - Uploads: `/public/admin/manage/uploads.php`
   - Cache Management: `/public/admin/manage/cache.php`
4. Settings:
   - General Settings: `/public/admin/settings/general.php`
   - Design Settings: `/public/admin/settings/design.php`
5. CRUD operations flow through `AdminManager` class:
   - All data validated with table-specific validation methods
   - Cache automatically invalidated on content updates
   - CSRF tokens validated on all state-changing operations
6. Changes reflect immediately on public pages (cache invalidation ensures fresh data)

## Input Validation and Sanitization

Comprehensive validation functions in `includes/content.php` and `includes/security.php`:

**Text and Data Validation:**
- `validateTextInput($input, $minLength, $maxLength, $required)` - Sanitize and length-check text
- `validateInteger($value, $min, $max)` - Validate numeric input with range checking
- `validateEmail($email)` - Email format validation
- `validateUrl($url)` - URL format validation
- `validateHexColor($color)` - Hex color code validation (#RRGGBB)
- `validateDateSecure($date, $format)` - Date validation with null byte removal

**File Handling:**
- `sanitizeFilename($filename)` - Remove path info and special characters
- `validateFileUpload($file)` - Comprehensive file upload security checks (size, type, MIME)

**Output Escaping (XSS Prevention):**
- `escapeHtml($string)` - HTML context escaping
- `escapeHtmlAttr($string)` - HTML attribute context
- `escapeJs($string)` - JavaScript context with JSON encoding
- `escapeUrl($string)` - URL encoding
- `cleanHtmlContent($html, $allowedTags)` - Strip unwanted HTML tags, remove dangerous URLs

**CSRF Protection:**
- `generateCSRFToken()` - Create session-based CSRF token
- `validateCSRFToken($token)` - Validate token with timing-safe comparison

## Design System Backups

The system automatically backs up design configurations:
- Location: `database/design_backups/`
- Automatic backups on design changes
- Restore via `includes/design-backup.php` functions
