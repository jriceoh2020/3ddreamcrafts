<?php
/**
 * Simple File-Based Caching System
 * Provides basic caching functionality for frequently accessed content
 */

class CacheManager {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 3600; // 1 hour default TTL
    
    private function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->ensureCacheDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0755, true)) {
                throw new Exception("Failed to create cache directory: " . $this->cacheDir);
            }
        }
        
        // Create .htaccess to prevent direct access
        $htaccessPath = $this->cacheDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
    }
    
    /**
     * Generate cache key from string
     */
    private function generateKey($key) {
        return md5($key);
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($key) {
        return $this->cacheDir . $this->generateKey($key) . '.cache';
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $ttl = null) {
        try {
            if ($ttl === null) {
                $ttl = $this->defaultTTL;
            }
            
            $cacheData = [
                'data' => $data,
                'expires' => time() + $ttl,
                'created' => time()
            ];
            
            $filePath = $this->getCacheFilePath($key);
            $serialized = serialize($cacheData);
            
            // Use atomic write with temporary file
            $tempPath = $filePath . '.tmp';
            if (file_put_contents($tempPath, $serialized, LOCK_EX) !== false) {
                return rename($tempPath, $filePath);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Cache set failed for key '$key': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Retrieve data from cache
     */
    public function get($key) {
        try {
            $filePath = $this->getCacheFilePath($key);
            
            if (!file_exists($filePath)) {
                return null;
            }
            
            $content = file_get_contents($filePath);
            if ($content === false) {
                return null;
            }
            
            $cacheData = unserialize($content);
            
            if (!$cacheData || !isset($cacheData['expires'])) {
                $this->delete($key);
                return null;
            }
            
            // Check if cache has expired
            if (time() > $cacheData['expires']) {
                $this->delete($key);
                return null;
            }
            
            return $cacheData['data'];
        } catch (Exception $e) {
            error_log("Cache get failed for key '$key': " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if cache key exists and is valid
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Delete cache entry
     */
    public function delete($key) {
        $filePath = $this->getCacheFilePath($key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    }
    
    /**
     * Clear all cache entries
     */
    public function clear() {
        $files = glob($this->cacheDir . '*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Clean expired cache entries
     */
    public function cleanExpired() {
        $files = glob($this->cacheDir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $cacheData = unserialize(file_get_contents($file));
            
            if (!$cacheData || !isset($cacheData['expires']) || time() > $cacheData['expires']) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '*.cache');
        $totalSize = 0;
        $validEntries = 0;
        $expiredEntries = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $cacheData = unserialize(file_get_contents($file));
            if ($cacheData && isset($cacheData['expires'])) {
                if (time() > $cacheData['expires']) {
                    $expiredEntries++;
                } else {
                    $validEntries++;
                }
            }
        }
        
        return [
            'total_entries' => count($files),
            'valid_entries' => $validEntries,
            'expired_entries' => $expiredEntries,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }
    
    /**
     * Remember pattern - get from cache or execute callback and cache result
     */
    public function remember($key, $callback, $ttl = null) {
        $data = $this->get($key);
        
        if ($data !== null) {
            return $data;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }
}

/**
 * Content-specific caching functions
 */
class ContentCache {
    private $cache;
    private $contentTTL = 1800; // 30 minutes for content
    private $settingsTTL = 3600; // 1 hour for settings
    
    public function __construct() {
        $this->cache = CacheManager::getInstance();
    }
    
    /**
     * Cache featured print data
     */
    public function getFeaturedPrint($callback) {
        return $this->cache->remember('featured_print', $callback, $this->contentTTL);
    }
    
    /**
     * Cache upcoming shows
     */
    public function getUpcomingShows($callback, $limit = 10) {
        $key = "upcoming_shows_{$limit}";
        return $this->cache->remember($key, $callback, $this->contentTTL);
    }
    
    /**
     * Cache recent news
     */
    public function getRecentNews($callback, $limit = 5) {
        $key = "recent_news_{$limit}";
        return $this->cache->remember($key, $callback, $this->contentTTL);
    }
    
    /**
     * Cache news with pagination
     */
    public function getNewsWithPagination($callback, $page = 1, $perPage = 10) {
        $key = "news_page_{$page}_{$perPage}";
        return $this->cache->remember($key, $callback, $this->contentTTL);
    }
    
    /**
     * Cache site settings
     */
    public function getSettings($callback) {
        return $this->cache->remember('site_settings', $callback, $this->settingsTTL);
    }
    
    /**
     * Invalidate content caches when content is updated
     */
    public function invalidateContent($type = 'all') {
        switch ($type) {
            case 'featured_print':
                $this->cache->delete('featured_print');
                break;
                
            case 'shows':
                // Clear all show-related caches
                for ($i = 1; $i <= 20; $i++) {
                    $this->cache->delete("upcoming_shows_{$i}");
                }
                break;
                
            case 'news':
                // Clear all news-related caches
                for ($page = 1; $page <= 10; $page++) {
                    for ($perPage = 5; $perPage <= 20; $perPage += 5) {
                        $this->cache->delete("news_page_{$page}_{$perPage}");
                    }
                }
                for ($i = 1; $i <= 20; $i++) {
                    $this->cache->delete("recent_news_{$i}");
                }
                break;
                
            case 'settings':
                $this->cache->delete('site_settings');
                break;
                
            case 'all':
            default:
                $this->cache->clear();
                break;
        }
    }
}