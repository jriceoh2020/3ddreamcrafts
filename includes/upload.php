<?php
/**
 * File Upload Management System
 * Handles secure file uploads with validation and processing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * FileUploadManager Class
 * Handles secure file upload operations with validation and processing
 */
class FileUploadManager {
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct() {
        $this->uploadPath = UPLOAD_PATH;
        $this->maxFileSize = MAX_UPLOAD_SIZE;
        $this->allowedTypes = ALLOWED_IMAGE_TYPES;
        
        // Ensure upload directory exists
        $this->ensureUploadDirectory();
    }
    
    /**
     * Upload a file with validation and processing
     * @param array $file $_FILES array element
     * @param string $subfolder Optional subfolder within uploads
     * @return array Result array with success status and file info
     */
    public function uploadFile($file, $subfolder = '') {
        try {
            // Validate file upload
            $validation = $this->validateUpload($file);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Prepare target directory
            $targetDir = $this->uploadPath;
            if (!empty($subfolder)) {
                $targetDir .= trim($subfolder, '/') . '/';
                if (!$this->ensureDirectory($targetDir)) {
                    return [
                        'success' => false,
                        'error' => 'Failed to create upload directory'
                    ];
                }
            }
            
            // Generate safe filename
            $originalName = $file['name'];
            $safeFilename = $this->generateSafeFilename($originalName, $targetDir);
            $targetPath = $targetDir . $safeFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to move uploaded file'
                ];
            }
            
            // Set proper file permissions
            chmod($targetPath, 0644);
            
            // Process image if needed
            $processResult = $this->processImage($targetPath);
            if (!$processResult['success']) {
                // Clean up failed upload
                unlink($targetPath);
                return $processResult;
            }
            
            // Return success with file information
            $relativePath = 'uploads/' . ($subfolder ? trim($subfolder, '/') . '/' : '') . $safeFilename;
            
            return [
                'success' => true,
                'filename' => $safeFilename,
                'original_name' => $originalName,
                'path' => $relativePath,
                'full_path' => $targetPath,
                'size' => filesize($targetPath),
                'type' => $this->getImageType($targetPath),
                'dimensions' => $this->getImageDimensions($targetPath)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete an uploaded file
     * @param string $relativePath Relative path from uploads directory
     * @return bool Success status
     */
    public function deleteFile($relativePath) {
        try {
            // Validate path is within uploads directory
            if (!$this->isValidUploadPath($relativePath)) {
                return false;
            }
            
            $fullPath = $this->uploadPath . ltrim($relativePath, '/');
            
            if (file_exists($fullPath)) {
                return unlink($fullPath);
            }
            
            return true; // File doesn't exist, consider it deleted
        } catch (Exception $e) {
            error_log("FileUploadManager::deleteFile() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get file information
     * @param string $relativePath Relative path from uploads directory
     * @return array|null File information or null if not found
     */
    public function getFileInfo($relativePath) {
        try {
            if (!$this->isValidUploadPath($relativePath)) {
                return null;
            }
            
            $fullPath = $this->uploadPath . ltrim($relativePath, '/');
            
            if (!file_exists($fullPath)) {
                return null;
            }
            
            $info = [
                'path' => $relativePath,
                'full_path' => $fullPath,
                'size' => filesize($fullPath),
                'modified' => filemtime($fullPath),
                'type' => $this->getImageType($fullPath)
            ];
            
            // Add image dimensions if it's an image
            if ($this->isImageFile($fullPath)) {
                $info['dimensions'] = $this->getImageDimensions($fullPath);
            }
            
            return $info;
        } catch (Exception $e) {
            error_log("FileUploadManager::getFileInfo() failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * List all files in uploads directory
     * @param string $subfolder Optional subfolder to list
     * @return array Array of file information
     */
    public function listFiles($subfolder = '') {
        try {
            $targetDir = $this->uploadPath;
            if (!empty($subfolder)) {
                $targetDir .= trim($subfolder, '/') . '/';
            }
            
            if (!is_dir($targetDir)) {
                return [];
            }
            
            $files = [];
            $iterator = new DirectoryIterator($targetDir);
            
            foreach ($iterator as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }
                
                $filename = $file->getFilename();
                if ($filename === '.gitkeep') {
                    continue;
                }
                
                $relativePath = ($subfolder ? trim($subfolder, '/') . '/' : '') . $filename;
                $fileInfo = $this->getFileInfo($relativePath);
                
                if ($fileInfo) {
                    $files[] = $fileInfo;
                }
            }
            
            // Sort by modification time, newest first
            usort($files, function($a, $b) {
                return $b['modified'] - $a['modified'];
            });
            
            return $files;
        } catch (Exception $e) {
            error_log("FileUploadManager::listFiles() failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate file upload
     * @param array $file $_FILES array element
     * @return array Validation result
     */
    private function validateUpload($file) {
        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            return [
                'success' => false,
                'error' => 'Invalid file upload'
            ];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return [
                    'success' => false,
                    'error' => 'No file was uploaded'
                ];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return [
                    'success' => false,
                    'error' => 'File is too large. Maximum size: ' . formatFileSize($this->maxFileSize)
                ];
            default:
                return [
                    'success' => false,
                    'error' => 'Upload failed with error code: ' . $file['error']
                ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'error' => 'File is too large. Maximum size: ' . formatFileSize($this->maxFileSize)
            ];
        }
        
        // Check if file is empty
        if ($file['size'] === 0) {
            return [
                'success' => false,
                'error' => 'File is empty'
            ];
        }
        
        // Validate file type by extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return [
                'success' => false,
                'error' => 'Invalid file type. Allowed types: ' . implode(', ', $this->allowedTypes)
            ];
        }
        
        // Validate MIME type if fileinfo extension is available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowedMimeTypes = [
                'image/jpeg',
                'image/jpg', 
                'image/png',
                'image/gif',
                'image/webp'
            ];
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                return [
                    'success' => false,
                    'error' => 'Invalid file type detected'
                ];
            }
        }
        
        // Additional security check - verify it's actually an image if GD is available
        if (function_exists('getimagesize')) {
            if (!getimagesize($file['tmp_name'])) {
                return [
                    'success' => false,
                    'error' => 'File is not a valid image'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate a safe filename
     * @param string $originalName Original filename
     * @param string $targetDir Target directory
     * @return string Safe filename
     */
    private function generateSafeFilename($originalName, $targetDir) {
        // Sanitize the filename
        $filename = sanitizeFilename($originalName);
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $filename = 'upload_' . time() . '.' . $extension;
        }
        
        // Make filename unique if it already exists
        return generateUniqueFilename($filename, $targetDir);
    }
    
    /**
     * Process uploaded image
     * @param string $filePath Path to uploaded file
     * @return array Processing result
     */
    private function processImage($filePath) {
        try {
            // Skip image processing if GD extension is not available
            if (!function_exists('getimagesize')) {
                return ['success' => true];
            }
            
            // Get image information
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                return [
                    'success' => false,
                    'error' => 'Invalid image file'
                ];
            }
            
            // Check image dimensions (optional limits)
            $maxWidth = 2048;
            $maxHeight = 2048;
            
            if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
                // Resize image if too large and GD functions are available
                if (function_exists('imagecreatefromjpeg')) {
                    $resizeResult = $this->resizeImage($filePath, $maxWidth, $maxHeight);
                    if (!$resizeResult) {
                        return [
                            'success' => false,
                            'error' => 'Failed to resize image'
                        ];
                    }
                }
            }
            
            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Image processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Resize image if it exceeds maximum dimensions
     * @param string $filePath Path to image file
     * @param int $maxWidth Maximum width
     * @param int $maxHeight Maximum height
     * @return bool Success status
     */
    private function resizeImage($filePath, $maxWidth, $maxHeight) {
        try {
            $imageInfo = getimagesize($filePath);
            if (!$imageInfo) {
                return false;
            }
            
            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];
            
            // Calculate new dimensions maintaining aspect ratio
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);
            
            // Create image resource based on type
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($filePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($filePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($filePath);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($filePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // Create new image
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Preserve transparency for PNG and GIF
            if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_GIF) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
            
            // Resize image
            imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // Save resized image
            $result = false;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($newImage, $filePath, 90);
                    break;
                case IMAGETYPE_PNG:
                    $result = imagepng($newImage, $filePath, 9);
                    break;
                case IMAGETYPE_GIF:
                    $result = imagegif($newImage, $filePath);
                    break;
                case IMAGETYPE_WEBP:
                    $result = imagewebp($newImage, $filePath, 90);
                    break;
            }
            
            // Clean up memory
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            
            return $result;
        } catch (Exception $e) {
            error_log("FileUploadManager::resizeImage() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get image type from file
     * @param string $filePath Path to image file
     * @return string|null Image type or null if not an image
     */
    private function getImageType($filePath) {
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                return 'jpeg';
            case IMAGETYPE_PNG:
                return 'png';
            case IMAGETYPE_GIF:
                return 'gif';
            case IMAGETYPE_WEBP:
                return 'webp';
            default:
                return null;
        }
    }
    
    /**
     * Get image dimensions
     * @param string $filePath Path to image file
     * @return array|null Dimensions array or null if not an image
     */
    private function getImageDimensions($filePath) {
        if (!function_exists('getimagesize')) {
            return null;
        }
        
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            return null;
        }
        
        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1]
        ];
    }
    
    /**
     * Check if file is an image
     * @param string $filePath Path to file
     * @return bool True if file is an image
     */
    private function isImageFile($filePath) {
        if (!function_exists('getimagesize')) {
            // Fallback to extension check if GD not available
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            return in_array($extension, $this->allowedTypes);
        }
        
        return getimagesize($filePath) !== false;
    }
    
    /**
     * Validate that path is within uploads directory
     * @param string $path Path to validate
     * @return bool True if path is valid
     */
    private function isValidUploadPath($path) {
        // Remove any directory traversal attempts
        $cleanPath = str_replace(['../', '..\\'], '', $path);
        
        // Path should not start with / or contain absolute path indicators
        if (strpos($cleanPath, '/') === 0 || strpos($cleanPath, '\\') === 0 || strpos($cleanPath, ':') !== false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Ensure upload directory exists
     * @return bool Success status
     */
    private function ensureUploadDirectory() {
        return $this->ensureDirectory($this->uploadPath);
    }
    
    /**
     * Ensure directory exists with proper permissions
     * @param string $directory Directory path
     * @return bool Success status
     */
    private function ensureDirectory($directory) {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                return false;
            }
        }
        
        // Ensure directory is writable
        if (!is_writable($directory)) {
            chmod($directory, 0755);
        }
        
        return true;
    }
}

/**
 * Utility function to get upload manager instance
 * @return FileUploadManager
 */
function getUploadManager() {
    static $instance = null;
    if ($instance === null) {
        $instance = new FileUploadManager();
    }
    return $instance;
}
?>