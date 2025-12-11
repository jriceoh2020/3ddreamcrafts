<?php
/**
 * Design Settings Backup System
 * Handles backup and restore of design customization settings
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

/**
 * DesignBackupManager Class
 * Manages backup and restore operations for design settings
 */
class DesignBackupManager {
    private $db;
    private $backupDir;
    
    public function __construct() {
        $this->db = DatabaseManager::getInstance();
        $this->backupDir = __DIR__ . '/../database/design_backups/';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create a backup of current design settings
     * @param string $name Optional backup name
     * @return string|false Backup filename on success, false on failure
     */
    public function createBackup($name = null) {
        try {
            // Get current design settings
            $settings = $this->getCurrentDesignSettings();
            
            if (empty($settings)) {
                throw new Exception("No design settings found to backup");
            }
            
            // Generate backup filename
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = $name ? sanitize_filename($name) : 'auto_backup';
            $filename = "{$backupName}_{$timestamp}.json";
            $filepath = $this->backupDir . $filename;
            
            // Create backup data structure
            $backupData = [
                'created_at' => date('Y-m-d H:i:s'),
                'name' => $backupName,
                'description' => $name ? "Manual backup: $name" : "Automatic backup",
                'settings' => $settings,
                'version' => '1.0'
            ];
            
            // Save backup to file
            $jsonData = json_encode($backupData, JSON_PRETTY_PRINT);
            if (file_put_contents($filepath, $jsonData) === false) {
                throw new Exception("Failed to write backup file");
            }
            
            // Clean up old automatic backups (keep last 10)
            if (!$name) {
                $this->cleanupOldBackups();
            }
            
            return $filename;
        } catch (Exception $e) {
            error_log("DesignBackupManager::createBackup() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Restore design settings from a backup
     * @param string $filename Backup filename
     * @return bool Success status
     */
    public function restoreBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Backup file not found: $filename");
            }
            
            // Read backup file
            $jsonData = file_get_contents($filepath);
            if ($jsonData === false) {
                throw new Exception("Failed to read backup file");
            }
            
            $backupData = json_decode($jsonData, true);
            if ($backupData === null) {
                throw new Exception("Invalid backup file format");
            }
            
            // Validate backup data structure
            if (!isset($backupData['settings']) || !is_array($backupData['settings'])) {
                throw new Exception("Invalid backup data structure");
            }
            
            // Create backup of current settings before restore
            $this->createBackup('pre_restore_' . date('Y-m-d_H-i-s'));
            
            // Restore settings
            $this->db->beginTransaction();
            
            foreach ($backupData['settings'] as $setting) {
                $this->db->execute(
                    "INSERT OR REPLACE INTO settings (setting_name, setting_value, updated_at) VALUES (?, ?, ?)",
                    [$setting['setting_name'], $setting['setting_value'], date('Y-m-d H:i:s')]
                );
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("DesignBackupManager::restoreBackup() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get list of available backups
     * @return array Array of backup information
     */
    public function getBackupList() {
        try {
            $backups = [];
            $files = glob($this->backupDir . '*.json');
            
            foreach ($files as $filepath) {
                $filename = basename($filepath);
                $jsonData = file_get_contents($filepath);
                
                if ($jsonData !== false) {
                    $backupData = json_decode($jsonData, true);
                    
                    if ($backupData !== null) {
                        $backups[] = [
                            'filename' => $filename,
                            'name' => $backupData['name'] ?? 'Unknown',
                            'description' => $backupData['description'] ?? '',
                            'created_at' => $backupData['created_at'] ?? date('Y-m-d H:i:s', filemtime($filepath)),
                            'size' => filesize($filepath),
                            'version' => $backupData['version'] ?? '1.0'
                        ];
                    }
                }
            }
            
            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return $backups;
        } catch (Exception $e) {
            error_log("DesignBackupManager::getBackupList() failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a backup file
     * @param string $filename Backup filename
     * @return bool Success status
     */
    public function deleteBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Backup file not found: $filename");
            }
            
            // Prevent deletion of system backups
            if (strpos($filename, 'pre_restore_') === 0) {
                throw new Exception("Cannot delete system backup files");
            }
            
            return unlink($filepath);
        } catch (Exception $e) {
            error_log("DesignBackupManager::deleteBackup() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get backup details
     * @param string $filename Backup filename
     * @return array|null Backup details or null if not found
     */
    public function getBackupDetails($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                return null;
            }
            
            $jsonData = file_get_contents($filepath);
            if ($jsonData === false) {
                return null;
            }
            
            $backupData = json_decode($jsonData, true);
            if ($backupData === null) {
                return null;
            }
            
            return [
                'filename' => $filename,
                'name' => $backupData['name'] ?? 'Unknown',
                'description' => $backupData['description'] ?? '',
                'created_at' => $backupData['created_at'] ?? date('Y-m-d H:i:s', filemtime($filepath)),
                'size' => filesize($filepath),
                'version' => $backupData['version'] ?? '1.0',
                'settings_count' => count($backupData['settings'] ?? []),
                'settings' => $backupData['settings'] ?? []
            ];
        } catch (Exception $e) {
            error_log("DesignBackupManager::getBackupDetails() failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Export backup as downloadable file
     * @param string $filename Backup filename
     * @return bool Success status
     */
    public function exportBackup($filename) {
        try {
            $filepath = $this->backupDir . $filename;
            
            if (!file_exists($filepath)) {
                throw new Exception("Backup file not found: $filename");
            }
            
            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            
            // Output file contents
            readfile($filepath);
            return true;
        } catch (Exception $e) {
            error_log("DesignBackupManager::exportBackup() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Import backup from uploaded file
     * @param array $uploadedFile $_FILES array element
     * @return string|false Imported filename on success, false on failure
     */
    public function importBackup($uploadedFile) {
        try {
            if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
                throw new Exception("Invalid uploaded file");
            }
            
            // Validate file type
            if ($uploadedFile['type'] !== 'application/json' && 
                pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) !== 'json') {
                throw new Exception("Invalid file type. Only JSON files are allowed.");
            }
            
            // Read and validate backup data
            $jsonData = file_get_contents($uploadedFile['tmp_name']);
            if ($jsonData === false) {
                throw new Exception("Failed to read uploaded file");
            }
            
            $backupData = json_decode($jsonData, true);
            if ($backupData === null) {
                throw new Exception("Invalid JSON format");
            }
            
            // Validate backup structure
            if (!isset($backupData['settings']) || !is_array($backupData['settings'])) {
                throw new Exception("Invalid backup structure");
            }
            
            // Generate unique filename
            $originalName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "imported_{$originalName}_{$timestamp}.json";
            $filepath = $this->backupDir . $filename;
            
            // Add import metadata
            $backupData['imported_at'] = date('Y-m-d H:i:s');
            $backupData['original_filename'] = $uploadedFile['name'];
            
            // Save imported backup
            $jsonData = json_encode($backupData, JSON_PRETTY_PRINT);
            if (file_put_contents($filepath, $jsonData) === false) {
                throw new Exception("Failed to save imported backup");
            }
            
            return $filename;
        } catch (Exception $e) {
            error_log("DesignBackupManager::importBackup() failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current design settings from database
     * @return array Current design settings
     */
    private function getCurrentDesignSettings() {
        $designSettings = [
            'theme_color',
            'accent_color',
            'font_family',
            'site_title',
            'site_logo'
        ];

        $settings = [];
        foreach ($designSettings as $settingName) {
            $result = $this->db->queryOne(
                "SELECT setting_name, setting_value, updated_at FROM settings WHERE setting_name = ?",
                [$settingName]
            );
            
            if ($result) {
                $settings[] = $result;
            }
        }
        
        return $settings;
    }
    
    /**
     * Clean up old automatic backups (keep last 10)
     */
    private function cleanupOldBackups() {
        try {
            $files = glob($this->backupDir . 'auto_backup_*.json');
            
            if (count($files) > 10) {
                // Sort by modification time (oldest first)
                usort($files, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                // Delete oldest files
                $filesToDelete = array_slice($files, 0, count($files) - 10);
                foreach ($filesToDelete as $file) {
                    unlink($file);
                }
            }
        } catch (Exception $e) {
            error_log("DesignBackupManager::cleanupOldBackups() failed: " . $e->getMessage());
        }
    }
}

/**
 * Sanitize filename for safe storage
 * @param string $filename Original filename
 * @return string Safe filename
 */
function sanitize_filename($filename) {
    // Remove path information
    $filename = basename($filename);
    
    // Remove special characters except dots, dashes, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    
    // Remove multiple consecutive underscores
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Trim underscores from start and end
    $filename = trim($filename, '_');
    
    // Ensure filename is not empty
    if (empty($filename)) {
        $filename = 'backup_' . time();
    }
    
    return $filename;
}

?>