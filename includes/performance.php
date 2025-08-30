<?php
/**
 * Performance Monitoring and Optimization
 * Tracks page load times, database queries, and system performance
 */

class PerformanceMonitor {
    private static $instance = null;
    private $startTime;
    private $queries = [];
    private $memoryStart;
    private $logFile;
    
    private function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
        $this->logFile = __DIR__ . '/../logs/performance.log';
        $this->ensureLogDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Start timing a specific operation
     */
    public function startTimer($name) {
        $this->timers[$name] = microtime(true);
    }
    
    /**
     * End timing and return duration
     */
    public function endTimer($name) {
        if (!isset($this->timers[$name])) {
            return 0;
        }
        
        $duration = microtime(true) - $this->timers[$name];
        unset($this->timers[$name]);
        return $duration;
    }
    
    /**
     * Log a database query for performance tracking
     */
    public function logQuery($sql, $params = [], $duration = 0) {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Get current page load time
     */
    public function getPageLoadTime() {
        return microtime(true) - $this->startTime;
    }
    
    /**
     * Get memory usage
     */
    public function getMemoryUsage() {
        return [
            'current' => memory_get_usage(),
            'peak' => memory_get_peak_usage(),
            'start' => $this->memoryStart,
            'used' => memory_get_usage() - $this->memoryStart
        ];
    }
    
    /**
     * Get query statistics
     */
    public function getQueryStats() {
        $totalQueries = count($this->queries);
        $totalTime = array_sum(array_column($this->queries, 'duration'));
        $avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;
        
        return [
            'total_queries' => $totalQueries,
            'total_time' => $totalTime,
            'average_time' => $avgTime,
            'queries' => $this->queries
        ];
    }
    
    /**
     * Get comprehensive performance report
     */
    public function getPerformanceReport() {
        $memory = $this->getMemoryUsage();
        $queries = $this->getQueryStats();
        
        return [
            'page_load_time' => $this->getPageLoadTime(),
            'memory_usage' => $memory,
            'query_stats' => $queries,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
    }
    
    /**
     * Log performance data to file
     */
    public function logPerformance($threshold = 3.0) {
        $report = $this->getPerformanceReport();
        
        // Only log if page load time exceeds threshold or if there are many queries
        if ($report['page_load_time'] > $threshold || $report['query_stats']['total_queries'] > 10) {
            $logEntry = sprintf(
                "[%s] URL: %s | Load Time: %.3fs | Memory: %s | Queries: %d (%.3fs total)\n",
                $report['timestamp'],
                $report['url'],
                $report['page_load_time'],
                $this->formatBytes($report['memory_usage']['used']),
                $report['query_stats']['total_queries'],
                $report['query_stats']['total_time']
            );
            
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Format bytes for human reading
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get performance statistics from log file
     */
    public function getPerformanceStats($days = 7) {
        if (!file_exists($this->logFile)) {
            return null;
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $stats = [
            'total_requests' => 0,
            'slow_requests' => 0,
            'average_load_time' => 0,
            'max_load_time' => 0,
            'average_queries' => 0,
            'max_queries' => 0,
            'urls' => []
        ];
        
        $loadTimes = [];
        $queryCounts = [];
        
        foreach ($lines as $line) {
            if (strpos($line, $cutoffDate) === false && $cutoffDate !== date('Y-m-d')) {
                continue;
            }
            
            // Parse log entry
            if (preg_match('/Load Time: ([\d.]+)s.*Queries: (\d+)/', $line, $matches)) {
                $loadTime = (float)$matches[1];
                $queryCount = (int)$matches[2];
                
                $loadTimes[] = $loadTime;
                $queryCounts[] = $queryCount;
                
                $stats['total_requests']++;
                if ($loadTime > 3.0) {
                    $stats['slow_requests']++;
                }
                
                // Track URL performance
                if (preg_match('/URL: ([^\s|]+)/', $line, $urlMatch)) {
                    $url = $urlMatch[1];
                    if (!isset($stats['urls'][$url])) {
                        $stats['urls'][$url] = ['count' => 0, 'avg_time' => 0, 'total_time' => 0];
                    }
                    $stats['urls'][$url]['count']++;
                    $stats['urls'][$url]['total_time'] += $loadTime;
                    $stats['urls'][$url]['avg_time'] = $stats['urls'][$url]['total_time'] / $stats['urls'][$url]['count'];
                }
            }
        }
        
        if (!empty($loadTimes)) {
            $stats['average_load_time'] = array_sum($loadTimes) / count($loadTimes);
            $stats['max_load_time'] = max($loadTimes);
        }
        
        if (!empty($queryCounts)) {
            $stats['average_queries'] = array_sum($queryCounts) / count($queryCounts);
            $stats['max_queries'] = max($queryCounts);
        }
        
        return $stats;
    }
    
    /**
     * Clean old performance logs
     */
    public function cleanOldLogs($days = 30) {
        if (!file_exists($this->logFile)) {
            return 0;
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        $keptLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoffDate) {
                    $keptLines[] = $line;
                }
            }
        }
        
        $removedLines = count($lines) - count($keptLines);
        
        if ($removedLines > 0) {
            file_put_contents($this->logFile, implode("\n", $keptLines) . "\n");
        }
        
        return $removedLines;
    }
}

/**
 * Enhanced DatabaseManager with performance monitoring
 */
class PerformantDatabaseManager extends DatabaseManager {
    private $monitor;
    
    public function __construct() {
        parent::__construct();
        $this->monitor = PerformanceMonitor::getInstance();
    }
    
    /**
     * Execute query with performance monitoring
     */
    public function query($sql, $params = []) {
        $startTime = microtime(true);
        $result = parent::query($sql, $params);
        $duration = microtime(true) - $startTime;
        
        $this->monitor->logQuery($sql, $params, $duration);
        
        return $result;
    }
    
    /**
     * Execute statement with performance monitoring
     */
    public function execute($sql, $params = []) {
        $startTime = microtime(true);
        $result = parent::execute($sql, $params);
        $duration = microtime(true) - $startTime;
        
        $this->monitor->logQuery($sql, $params, $duration);
        
        return $result;
    }
}

/**
 * Image optimization functions
 */
class ImageOptimizer {
    
    /**
     * Optimize uploaded image
     */
    public static function optimizeImage($sourcePath, $targetPath = null, $maxWidth = 1200, $quality = 85) {
        if ($targetPath === null) {
            $targetPath = $sourcePath;
        }
        
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Skip optimization if image is already small enough
        if ($width <= $maxWidth && filesize($sourcePath) < 500000) { // 500KB
            if ($sourcePath !== $targetPath) {
                copy($sourcePath, $targetPath);
            }
            return true;
        }
        
        // Calculate new dimensions
        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = intval($height * ($maxWidth / $width));
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Create image resource based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create new image
        $target = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        $success = false;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $success = imagejpeg($target, $targetPath, $quality);
                break;
            case IMAGETYPE_PNG:
                $success = imagepng($target, $targetPath, 9);
                break;
            case IMAGETYPE_GIF:
                $success = imagegif($target, $targetPath);
                break;
        }
        
        // Clean up
        imagedestroy($source);
        imagedestroy($target);
        
        return $success;
    }
    
    /**
     * Generate WebP version of image if supported
     */
    public static function generateWebP($sourcePath, $quality = 85) {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Create source image
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Generate WebP filename
        $pathInfo = pathinfo($sourcePath);
        $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
        
        // Save as WebP
        $success = imagewebp($source, $webpPath, $quality);
        imagedestroy($source);
        
        return $success ? $webpPath : false;
    }
}

/**
 * Lazy loading helper functions
 */
function generateLazyLoadingImage($src, $alt = '', $class = '', $width = null, $height = null) {
    $attributes = [
        'data-src="' . htmlspecialchars($src) . '"',
        'alt="' . htmlspecialchars($alt) . '"',
        'class="lazy-load ' . htmlspecialchars($class) . '"',
        'loading="lazy"'
    ];
    
    if ($width) {
        $attributes[] = 'width="' . intval($width) . '"';
    }
    
    if ($height) {
        $attributes[] = 'height="' . intval($height) . '"';
    }
    
    // Use a 1x1 transparent pixel as placeholder
    $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    $attributes[] = 'src="' . $placeholder . '"';
    
    return '<img ' . implode(' ', $attributes) . '>';
}

/**
 * Performance optimization middleware
 */
function initializePerformanceOptimizations() {
    // Start performance monitoring
    $monitor = PerformanceMonitor::getInstance();
    
    // Register shutdown function to log performance
    register_shutdown_function(function() use ($monitor) {
        $monitor->logPerformance();
    });
    
    // Enable output compression if not already enabled
    if (!ob_get_level() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
        ob_start('ob_gzhandler');
    }
    
    // Set cache headers for static assets
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$/i', $requestUri)) {
        header('Cache-Control: public, max-age=31536000'); // 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    }
}