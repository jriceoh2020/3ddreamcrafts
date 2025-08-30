<?php
/**
 * Content Management Classes
 * Handles content retrieval and management for the craft vendor website
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';

/**
 * ContentManager Class
 * Handles public content retrieval from the database
 */
class ContentManager {
    protected $db;
    protected $config;
    protected $contentCache;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->config = ConfigManager::getInstance();
        $this->contentCache = new ContentCache();
    }
    
    /**
     * Get the currently active featured print
     * @return array|null Featured print data or null if none active
     */
    public function getFeaturedPrint() {
        return $this->contentCache->getFeaturedPrint(function() {
            try {
                $sql = "SELECT id, title, description, image_path, created_at, updated_at 
                        FROM featured_prints 
                        WHERE is_active = 1 
                        ORDER BY updated_at DESC 
                        LIMIT 1";
                
                return $this->db->queryOne($sql);
            } catch (Exception $e) {
                error_log("ContentManager::getFeaturedPrint() failed: " . $e->getMessage());
                return null;
            }
        });
    }
    
    /**
     * Get upcoming craft shows
     * @param int $limit Maximum number of shows to return
     * @return array Array of upcoming craft shows
     */
    public function getUpcomingShows($limit = 10) {
        return $this->contentCache->getUpcomingShows(function() use ($limit) {
            try {
                $sql = "SELECT id, title, event_date, location, description, created_at, updated_at 
                        FROM craft_shows 
                        WHERE is_active = 1 AND event_date >= date('now') 
                        ORDER BY event_date ASC 
                        LIMIT ?";
                
                return $this->db->query($sql, [$limit]);
            } catch (Exception $e) {
                error_log("ContentManager::getUpcomingShows() failed: " . $e->getMessage());
                return [];
            }
        }, $limit);
    }
    
    /**
     * Get recent news articles
     * @param int $limit Maximum number of articles to return
     * @return array Array of recent news articles
     */
    public function getRecentNews($limit = 5) {
        return $this->contentCache->getRecentNews(function() use ($limit) {
            try {
                $sql = "SELECT id, title, content, published_date, is_published, created_at, updated_at 
                        FROM news_articles 
                        WHERE is_published = 1 
                        ORDER BY published_date DESC 
                        LIMIT ?";
                
                return $this->db->query($sql, [$limit]);
            } catch (Exception $e) {
                error_log("ContentManager::getRecentNews() failed: " . $e->getMessage());
                return [];
            }
        }, $limit);
    }
    
    /**
     * Get paginated news articles
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array Array with 'articles' and 'total_pages'
     */
    public function getNewsWithPagination($page = 1, $perPage = null) {
        if ($perPage === null) {
            $perPage = (int)$this->config->get('items_per_page', ITEMS_PER_PAGE);
        }
        
        return $this->contentCache->getNewsWithPagination(function() use ($page, $perPage) {
            $offset = ($page - 1) * $perPage;
            
            try {
                // Get total count
                $countSql = "SELECT COUNT(*) as total FROM news_articles WHERE is_published = 1";
                $countResult = $this->db->queryOne($countSql);
                $totalItems = $countResult ? (int)$countResult['total'] : 0;
                $totalPages = ceil($totalItems / $perPage);
                
                // Get articles for current page
                $sql = "SELECT id, title, content, published_date, is_published, created_at, updated_at 
                        FROM news_articles 
                        WHERE is_published = 1 
                        ORDER BY published_date DESC 
                        LIMIT ? OFFSET ?";
                
                $articles = $this->db->query($sql, [$perPage, $offset]);
                
                return [
                    'articles' => $articles,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalItems,
                    'per_page' => $perPage
                ];
            } catch (Exception $e) {
                error_log("ContentManager::getNewsWithPagination() failed: " . $e->getMessage());
                return [
                    'articles' => [],
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_items' => 0,
                    'per_page' => $perPage
                ];
            }
        }, $page, $perPage);
    }
    
    /**
     * Get a single news article by ID
     * @param int $id Article ID
     * @return array|null Article data or null if not found
     */
    public function getNewsArticle($id) {
        try {
            $sql = "SELECT id, title, content, published_date, is_published, created_at, updated_at 
                    FROM news_articles 
                    WHERE id = ? AND is_published = 1";
            
            return $this->db->queryOne($sql, [$id]);
        } catch (Exception $e) {
            error_log("ContentManager::getNewsArticle() failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get site settings
     * @return array Array of site settings
     */
    public function getSettings() {
        return $this->contentCache->getSettings(function() {
            try {
                $this->config->loadSettings();
                return [
                    'site_title' => $this->config->get('site_title', SITE_NAME),
                    'theme_color' => $this->config->get('theme_color', '#2563eb'),
                    'accent_color' => $this->config->get('accent_color', '#dc2626'),
                    'font_family' => $this->config->get('font_family', 'Arial, sans-serif'),
                    'facebook_url' => $this->config->get('facebook_url', ''),
                    'instagram_url' => $this->config->get('instagram_url', ''),
                    'maintenance_mode' => (bool)$this->config->get('maintenance_mode', false)
                ];
            } catch (Exception $e) {
                error_log("ContentManager::getSettings() failed: " . $e->getMessage());
                return [
                    'site_title' => SITE_NAME,
                    'theme_color' => '#2563eb',
                    'accent_color' => '#dc2626',
                    'font_family' => 'Arial, sans-serif',
                    'facebook_url' => '',
                    'instagram_url' => '',
                    'maintenance_mode' => false
                ];
            }
        });
    }
}
/*
*
 * AdminManager Class
 * Extends ContentManager with CRUD operations for administrative functions
 */
class AdminManager extends ContentManager {
    
    /**
     * Create new content in specified table
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int|false Insert ID on success, false on failure
     */
    public function createContent($table, $data) {
        try {
            $this->validateTableName($table);
            $validatedData = $this->validateAndSanitizeData($table, $data);
            
            // Add timestamps
            $validatedData['created_at'] = date('Y-m-d H:i:s');
            $validatedData['updated_at'] = date('Y-m-d H:i:s');
            
            $columns = array_keys($validatedData);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $result = $this->db->execute($sql, array_values($validatedData));
            
            // Invalidate relevant caches
            if ($result !== false) {
                $this->invalidateCacheForTable($table);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("AdminManager::createContent() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update existing content
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function updateContent($table, $id, $data) {
        try {
            $this->validateTableName($table);
            $validatedData = $this->validateAndSanitizeData($table, $data, $id);
            
            // Add update timestamp
            $validatedData['updated_at'] = date('Y-m-d H:i:s');
            
            $setParts = [];
            $values = [];
            
            foreach ($validatedData as $column => $value) {
                $setParts[] = "$column = ?";
                $values[] = $value;
            }
            
            $values[] = $id; // Add ID for WHERE clause
            
            $sql = sprintf(
                "UPDATE %s SET %s WHERE id = ?",
                $table,
                implode(', ', $setParts)
            );
            
            $affectedRows = $this->db->execute($sql, $values);
            $success = $affectedRows > 0;
            
            // Invalidate relevant caches
            if ($success) {
                $this->invalidateCacheForTable($table);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("AdminManager::updateContent() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete content by ID
     * @param string $table Table name
     * @param int $id Record ID
     * @return bool Success status
     */
    public function deleteContent($table, $id) {
        try {
            $this->validateTableName($table);
            
            $sql = "DELETE FROM $table WHERE id = ?";
            $affectedRows = $this->db->execute($sql, [$id]);
            $success = $affectedRows > 0;
            
            // Invalidate relevant caches
            if ($success) {
                $this->invalidateCacheForTable($table);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("AdminManager::deleteContent() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get content by ID for editing
     * @param string $table Table name
     * @param int $id Record ID
     * @return array|null Content data or null if not found
     */
    public function getContentById($table, $id) {
        try {
            $this->validateTableName($table);
            
            $sql = "SELECT * FROM $table WHERE id = ?";
            return $this->db->queryOne($sql, [$id]);
        } catch (Exception $e) {
            error_log("AdminManager::getContentById() failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all content from a table with pagination
     * @param string $table Table name
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @param string $orderBy Order by column
     * @param string $orderDir Order direction (ASC/DESC)
     * @return array Array with content and pagination info
     */
    public function getAllContent($table, $page = 1, $perPage = null, $orderBy = 'id', $orderDir = 'DESC') {
        try {
            $this->validateTableName($table);
            
            if ($perPage === null) {
                $perPage = (int)$this->config->get('items_per_page', ITEMS_PER_PAGE);
            }
            
            $offset = ($page - 1) * $perPage;
            $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM $table";
            $countResult = $this->db->queryOne($countSql);
            $totalItems = $countResult ? (int)$countResult['total'] : 0;
            $totalPages = ceil($totalItems / $perPage);
            
            // Get content for current page
            $sql = "SELECT * FROM $table ORDER BY $orderBy $orderDir LIMIT ? OFFSET ?";
            $content = $this->db->query($sql, [$perPage, $offset]);
            
            return [
                'content' => $content,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'per_page' => $perPage
            ];
        } catch (Exception $e) {
            error_log("AdminManager::getAllContent() failed: " . $e->getMessage());
            return [
                'content' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_items' => 0,
                'per_page' => $perPage ?? ITEMS_PER_PAGE
            ];
        }
    }
    
    /**
     * Update site settings
     * @param array $settings Array of setting_name => setting_value pairs
     * @return bool Success status
     */
    public function updateSettings($settings) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $name => $value) {
                $sanitizedName = $this->sanitizeInput($name);
                $sanitizedValue = $this->sanitizeInput($value);
                
                if (!$this->isValidSettingName($sanitizedName)) {
                    throw new Exception("Invalid setting name: $sanitizedName");
                }
                
                $this->config->set($sanitizedName, $sanitizedValue);
            }
            
            $this->db->commit();
            
            // Invalidate settings cache
            $this->contentCache->invalidateContent('settings');
            
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("AdminManager::updateSettings() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle active status of content
     * @param string $table Table name
     * @param int $id Record ID
     * @param string $column Column name for active status
     * @return bool Success status
     */
    public function toggleActiveStatus($table, $id, $column = 'is_active') {
        try {
            $this->validateTableName($table);
            
            $sql = "UPDATE $table SET $column = NOT $column, updated_at = ? WHERE id = ?";
            $affectedRows = $this->db->execute($sql, [date('Y-m-d H:i:s'), $id]);
            
            return $affectedRows > 0;
        } catch (Exception $e) {
            error_log("AdminManager::toggleActiveStatus() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate table name against allowed tables
     * @param string $table Table name to validate
     * @throws Exception If table name is invalid
     */
    private function validateTableName($table) {
        $allowedTables = ['featured_prints', 'craft_shows', 'news_articles', 'settings'];
        
        if (!in_array($table, $allowedTables)) {
            throw new Exception("Invalid table name: $table");
        }
    }
    
    /**
     * Validate and sanitize data based on table schema
     * @param string $table Table name
     * @param array $data Data to validate
     * @param int|null $id Record ID for updates (null for inserts)
     * @return array Validated and sanitized data
     * @throws Exception If validation fails
     */
    private function validateAndSanitizeData($table, $data, $id = null) {
        $validatedData = [];
        
        switch ($table) {
            case 'featured_prints':
                $validatedData = $this->validateFeaturedPrintData($data, $id);
                break;
                
            case 'craft_shows':
                $validatedData = $this->validateCraftShowData($data, $id);
                break;
                
            case 'news_articles':
                $validatedData = $this->validateNewsArticleData($data, $id);
                break;
                
            case 'settings':
                $validatedData = $this->validateSettingsData($data, $id);
                break;
                
            default:
                throw new Exception("No validation rules for table: $table");
        }
        
        return $validatedData;
    }
    
    /**
     * Validate featured print data
     */
    private function validateFeaturedPrintData($data, $id = null) {
        $validated = [];
        
        // Title is required
        if (empty($data['title'])) {
            throw new Exception("Title is required for featured prints");
        }
        $validated['title'] = $this->sanitizeInput($data['title'], 255);
        
        // Description is optional
        if (isset($data['description'])) {
            $validated['description'] = $this->sanitizeInput($data['description'], 1000);
        }
        
        // Image path validation
        if (isset($data['image_path'])) {
            $validated['image_path'] = $this->validateImagePath($data['image_path']);
        }
        
        // Active status
        if (isset($data['is_active'])) {
            $validated['is_active'] = $this->validateBoolean($data['is_active']);
        }
        
        return $validated;
    }
    
    /**
     * Validate craft show data
     */
    private function validateCraftShowData($data, $id = null) {
        $validated = [];
        
        // Title is required
        if (empty($data['title'])) {
            throw new Exception("Title is required for craft shows");
        }
        $validated['title'] = $this->sanitizeInput($data['title'], 255);
        
        // Event date is required and must be valid
        if (empty($data['event_date'])) {
            throw new Exception("Event date is required for craft shows");
        }
        $validated['event_date'] = $this->validateDate($data['event_date']);
        
        // Location is required
        if (empty($data['location'])) {
            throw new Exception("Location is required for craft shows");
        }
        $validated['location'] = $this->sanitizeInput($data['location'], 255);
        
        // Description is optional
        if (isset($data['description'])) {
            $validated['description'] = $this->sanitizeInput($data['description'], 1000);
        }
        
        // Active status
        if (isset($data['is_active'])) {
            $validated['is_active'] = $this->validateBoolean($data['is_active']);
        }
        
        return $validated;
    }
    
    /**
     * Validate news article data
     */
    private function validateNewsArticleData($data, $id = null) {
        $validated = [];
        
        // Title is required
        if (empty($data['title'])) {
            throw new Exception("Title is required for news articles");
        }
        $validated['title'] = $this->sanitizeInput($data['title'], 255);
        
        // Content is required
        if (empty($data['content'])) {
            throw new Exception("Content is required for news articles");
        }
        $validated['content'] = $this->sanitizeInput($data['content'], 10000);
        
        // Published date
        if (isset($data['published_date'])) {
            $validated['published_date'] = $this->validateDateTime($data['published_date']);
        }
        
        // Published status
        if (isset($data['is_published'])) {
            $validated['is_published'] = $this->validateBoolean($data['is_published']);
        }
        
        return $validated;
    }
    
    /**
     * Validate settings data
     */
    private function validateSettingsData($data, $id = null) {
        $validated = [];
        
        // Setting name is required
        if (empty($data['setting_name'])) {
            throw new Exception("Setting name is required");
        }
        
        $settingName = $this->sanitizeInput($data['setting_name']);
        if (!$this->isValidSettingName($settingName)) {
            throw new Exception("Invalid setting name: $settingName");
        }
        $validated['setting_name'] = $settingName;
        
        // Setting value
        if (isset($data['setting_value'])) {
            $validated['setting_value'] = $this->sanitizeInput($data['setting_value'], 1000);
        }
        
        return $validated;
    }
    
    /**
     * Sanitize input data
     * @param string $input Input to sanitize
     * @param int $maxLength Maximum length (optional)
     * @return string Sanitized input
     */
    private function sanitizeInput($input, $maxLength = null) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Limit length if specified
        if ($maxLength !== null && strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        // HTML encode for output safety
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate boolean value
     * @param mixed $value Value to validate
     * @return int 1 for true, 0 for false
     */
    private function validateBoolean($value) {
        return $value ? 1 : 0;
    }
    
    /**
     * Validate date format
     * @param string $date Date string to validate
     * @return string Validated date in Y-m-d format
     * @throws Exception If date is invalid
     */
    private function validateDate($date) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new Exception("Invalid date format. Expected Y-m-d, got: $date");
        }
        return $date;
    }
    
    /**
     * Validate datetime format
     * @param string $datetime Datetime string to validate
     * @return string Validated datetime in Y-m-d H:i:s format
     * @throws Exception If datetime is invalid
     */
    private function validateDateTime($datetime) {
        $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        if (!$dateObj || $dateObj->format('Y-m-d H:i:s') !== $datetime) {
            throw new Exception("Invalid datetime format. Expected Y-m-d H:i:s, got: $datetime");
        }
        return $datetime;
    }
    
    /**
     * Validate image path
     * @param string $path Image path to validate
     * @return string Validated path
     * @throws Exception If path is invalid
     */
    private function validateImagePath($path) {
        // Remove any directory traversal attempts
        $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
        
        // Ensure path starts with uploads/
        if (!empty($path) && strpos($path, 'uploads/') !== 0) {
            $path = 'uploads/' . ltrim($path, '/');
        }
        
        // Validate file extension if path is not empty
        if (!empty($path)) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_IMAGE_TYPES)) {
                throw new Exception("Invalid image type. Allowed types: " . implode(', ', ALLOWED_IMAGE_TYPES));
            }
        }
        
        return $path;
    }
    
    /**
     * Check if setting name is valid
     * @param string $name Setting name to validate
     * @return bool True if valid
     */
    private function isValidSettingName($name) {
        $allowedSettings = [
            'site_title',
            'theme_color',
            'accent_color',
            'font_family',
            'items_per_page',
            'maintenance_mode',
            'schema_version',
            'facebook_url',
            'instagram_url'
        ];
        
        return in_array($name, $allowedSettings);
    }
    
    /**
     * Invalidate cache for specific table
     * @param string $table Table name
     */
    private function invalidateCacheForTable($table) {
        switch ($table) {
            case 'featured_prints':
                $this->contentCache->invalidateContent('featured_print');
                break;
            case 'craft_shows':
                $this->contentCache->invalidateContent('shows');
                break;
            case 'news_articles':
                $this->contentCache->invalidateContent('news');
                break;
            case 'settings':
                $this->contentCache->invalidateContent('settings');
                break;
        }
    }
}

/**
 * Input Validation and Sanitization Functions
 * Standalone functions for general input validation
 */

/**
 * Validate and sanitize text input
 * @param string $input Input to validate
 * @param int $minLength Minimum length
 * @param int $maxLength Maximum length
 * @param bool $required Whether field is required
 * @return string|null Sanitized input or null if invalid
 */
function validateTextInput($input, $minLength = 0, $maxLength = 255, $required = true) {
    if ($input === null || $input === '') {
        return $required ? null : '';
    }
    
    // Remove null bytes and trim
    $input = str_replace("\0", '', trim($input));
    
    // Check length constraints
    if (strlen($input) < $minLength || strlen($input) > $maxLength) {
        return null;
    }
    
    // HTML encode for safety
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return string|null Valid email or null if invalid
 */
function validateEmail($email) {
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Validate URL
 * @param string $url URL to validate
 * @return string|null Valid URL or null if invalid
 */
function validateUrl($url) {
    $url = trim($url);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

/**
 * Validate integer within range
 * @param mixed $value Value to validate
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return int|null Valid integer or null if invalid
 */
function validateInteger($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return null;
    }
    
    $int = (int)$value;
    
    if ($min !== null && $int < $min) {
        return null;
    }
    
    if ($max !== null && $int > $max) {
        return null;
    }
    
    return $int;
}

/**
 * Validate hex color code
 * @param string $color Color code to validate
 * @return string|null Valid color code or null if invalid
 */
function validateHexColor($color) {
    $color = trim($color);
    
    // Add # if missing
    if (strpos($color, '#') !== 0) {
        $color = '#' . $color;
    }
    
    // Validate hex color format
    if (preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
        return strtolower($color);
    }
    
    return null;
}

/**
 * Sanitize filename for safe storage
 * @param string $filename Original filename
 * @return string Safe filename
 */
function sanitizeFilename($filename) {
    // Remove path information
    $filename = basename($filename);
    
    // Remove special characters except dots and dashes
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Remove multiple consecutive underscores
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Trim underscores from start and end
    $filename = trim($filename, '_');
    
    // Ensure filename is not empty
    if (empty($filename)) {
        $filename = 'file_' . time();
    }
    
    return $filename;
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME] = $token;
    
    return $token;
}

/**
 * Validate CSRF token
 * @param string $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Clean and validate HTML content
 * @param string $html HTML content to clean
 * @param array $allowedTags Allowed HTML tags
 * @return string Cleaned HTML
 */
function cleanHtmlContent($html, $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'ol', 'ul', 'li', 'a']) {
    // Remove null bytes
    $html = str_replace("\0", '', $html);
    
    // Build allowed tags string for strip_tags
    $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
    
    // Strip unwanted tags
    $html = strip_tags($html, $allowedTagsString);
    
    // Remove javascript: and data: URLs from links
    $html = preg_replace('/href\s*=\s*["\']?\s*(javascript|data):/i', 'href="#"', $html);
    
    return $html;
}
?>