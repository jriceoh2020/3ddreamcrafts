<?php
/**
 * Utility Functions
 * General utility functions for the craft vendor website
 */

require_once __DIR__ . '/content.php';
require_once __DIR__ . '/security.php';

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Display format
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($date);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format datetime for display
 * @param string $datetime Datetime string
 * @param string $format Display format
 * @return string Formatted datetime
 */
function formatDateTime($datetime, $format = 'F j, Y g:i A') {
    if (empty($datetime)) {
        return '';
    }
    
    try {
        $dateObj = new DateTime($datetime);
        return $dateObj->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Truncate text to specified length
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 150, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get file extension from filename
 * @param string $filename Filename
 * @return string File extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if file is an allowed image type
 * @param string $filename Filename to check
 * @return bool True if allowed image type
 */
function isAllowedImageType($filename) {
    $extension = getFileExtension($filename);
    return in_array($extension, ALLOWED_IMAGE_TYPES);
}

/**
 * Generate unique filename to prevent conflicts
 * @param string $filename Original filename
 * @param string $directory Target directory
 * @return string Unique filename
 */
function generateUniqueFilename($filename, $directory) {
    $filename = sanitizeFilename($filename);
    $extension = getFileExtension($filename);
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    
    $counter = 1;
    $newFilename = $filename;
    
    while (file_exists($directory . '/' . $newFilename)) {
        $newFilename = $basename . '_' . $counter . '.' . $extension;
        $counter++;
    }
    
    return $newFilename;
}

/**
 * Create directory if it doesn't exist
 * @param string $directory Directory path
 * @param int $permissions Directory permissions
 * @return bool Success status
 */
function ensureDirectoryExists($directory, $permissions = 0755) {
    if (!is_dir($directory)) {
        return mkdir($directory, $permissions, true);
    }
    return true;
}

/**
 * Get human readable file size
 * @param int $bytes File size in bytes
 * @return string Human readable size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 * @param int $statusCode HTTP status code
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Check if request is POST
 * @return bool True if POST request
 */
function isPostRequest() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST data with optional default
 * @param string $key POST key
 * @param mixed $default Default value
 * @return mixed POST value or default
 */
function getPostData($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Get GET data with optional default
 * @param string $key GET key
 * @param mixed $default Default value
 * @return mixed GET value or default
 */
function getGetData($key, $default = null) {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Display flash message
 * @param string $message Message to display
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * @return array|null Flash message array or null
 */
function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}





?>