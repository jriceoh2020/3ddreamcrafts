<?php
/**
 * Admin Design Settings
 * CRUD interface for managing design customization settings
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/design-backup.php';
require_once __DIR__ . '/../../includes/upload.php';

$auth = AuthManager::getInstance();
$auth->requireAuth();

$adminManager = new AdminManager();
$config = ConfigManager::getInstance();
$backupManager = new DesignBackupManager();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? 'update_settings';
        
        switch ($action) {
            case 'update_settings':
                // Create automatic backup before updating
                $backupManager->createBackup();

                $settings = [
                    'theme_color' => validateHexColor($_POST['theme_color'] ?? ''),
                    'accent_color' => validateHexColor($_POST['accent_color'] ?? ''),
                    'font_family' => validateTextInput($_POST['font_family'] ?? '', 1, 100, true)
                ];

                // Handle logo upload if provided
                if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                    $uploadManager = new FileUploadManager();
                    $uploadResult = $uploadManager->uploadFile($_FILES['site_logo'], 'logo');

                    if ($uploadResult['success']) {
                        $settings['site_logo'] = '/' . $uploadResult['path'];
                    } else {
                        $errors[] = 'Logo upload failed: ' . $uploadResult['error'];
                    }
                } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
                    // Remove logo if requested
                    $settings['site_logo'] = '';
                }

                // Validate required fields
                $errors = [];
                if ($settings['theme_color'] === null) {
                    $errors[] = 'Theme color must be a valid hex color code.';
                }
                if ($settings['accent_color'] === null) {
                    $errors[] = 'Accent color must be a valid hex color code.';
                }
                if ($settings['font_family'] === null) {
                    $errors[] = 'Font family is required.';
                }

                if (empty($errors)) {
                    $result = $adminManager->updateSettings($settings);
                    if ($result) {
                        $message = 'Design settings updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update design settings. Please try again.';
                        $messageType = 'error';
                    }
                } else {
                    $message = implode(' ', $errors);
                    $messageType = 'error';
                }
                break;
                
            case 'create_backup':
                $backupName = validateTextInput($_POST['backup_name'] ?? '', 1, 50, false);
                $filename = $backupManager->createBackup($backupName);
                if ($filename) {
                    $message = "Backup created successfully: $filename";
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create backup. Please try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'restore_backup':
                $filename = validateTextInput($_POST['backup_filename'] ?? '', 1, 255, true);
                if ($filename && $backupManager->restoreBackup($filename)) {
                    $message = 'Design settings restored successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to restore backup. Please try again.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete_backup':
                $filename = validateTextInput($_POST['backup_filename'] ?? '', 1, 255, true);
                if ($filename && $backupManager->deleteBackup($filename)) {
                    $message = 'Backup deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete backup. Please try again.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get current settings and backups
$currentSettings = $adminManager->getSettings();
$backupList = $backupManager->getBackupList();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design Settings - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .nav-links a:hover {
            background-color: #f0f0f0;
        }
        
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 2rem;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .form-help {
            font-size: 14px;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .color-input-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .color-input-group input[type="color"] {
            width: 60px;
            height: 40px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .color-input-group input[type="text"] {
            flex: 1;
            font-family: monospace;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5a6fd8;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .settings-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .settings-section h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .preview-section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .preview-content {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 2rem;
            background: #f9f9f9;
        }
        
        .preview-header {
            padding: 1rem 2rem;
            margin: -2rem -2rem 2rem -2rem;
            border-radius: 8px 8px 0 0;
            color: white;
            font-weight: 500;
        }
        
        .preview-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            margin-right: 1rem;
        }
        
        .font-preview {
            font-size: 1.2rem;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .backup-item:last-child {
            border-bottom: none;
        }
        
        .backup-info h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
            font-size: 1rem;
        }
        
        .backup-info p {
            margin: 0 0 0.5rem 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .backup-info small {
            color: #999;
            font-size: 0.8rem;
        }
        
        .backup-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .color-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .backup-item {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }
            
            .backup-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Design Settings</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
            <a href="/admin/settings/general.php">General Settings</a>
            <a href="/">View Site</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Live Preview -->
        <div class="preview-section">
            <h2>Live Preview</h2>
            <div class="preview-content" id="preview">
                <div class="preview-header" id="previewHeader" style="background-color: <?php echo $currentSettings['theme_color']; ?>;">
                    <?php echo htmlspecialchars($currentSettings['site_title']); ?>
                </div>
                <div class="font-preview" id="previewFont" style="font-family: <?php echo $currentSettings['font_family']; ?>;">
                    <h3>Sample Heading</h3>
                    <p>This is how your content will look with the selected font family. The quick brown fox jumps over the lazy dog.</p>
                    <button class="preview-button" id="previewButton" style="background-color: <?php echo $currentSettings['accent_color']; ?>;">
                        Sample Button
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card">
            <h2>Design Customization</h2>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Logo Upload -->
                <div class="settings-section">
                    <h3>Site Logo</h3>

                    <?php if (!empty($currentSettings['site_logo'])): ?>
                        <div class="form-group">
                            <label>Current Logo</label>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?php echo htmlspecialchars($currentSettings['site_logo']); ?>"
                                     alt="Site Logo"
                                     style="max-height: 100px; border: 1px solid #ddd; border-radius: 4px; padding: 0.5rem; background: white;">
                            </div>
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                                <input type="checkbox" name="remove_logo" value="1">
                                Remove current logo
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="site_logo">Upload New Logo</label>
                        <input type="file" id="site_logo" name="site_logo"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-help">Upload a logo image (JPG, PNG, GIF, or WebP). Recommended size: 200x100 pixels or similar aspect ratio.</div>
                    </div>
                </div>

                <!-- Colors -->
                <div class="settings-section">
                    <h3>Colors</h3>
                    
                    <div class="form-group">
                        <label for="theme_color">Primary Theme Color *</label>
                        <div class="color-input-group">
                            <input type="color" id="theme_color_picker" 
                                   value="<?php echo $currentSettings['theme_color']; ?>">
                            <input type="text" id="theme_color" name="theme_color" required
                                   value="<?php echo $currentSettings['theme_color']; ?>"
                                   pattern="^#[a-fA-F0-9]{6}$"
                                   placeholder="#2563eb">
                        </div>
                        <div class="form-help">Main color used for headers, navigation, and primary elements.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="accent_color">Accent Color *</label>
                        <div class="color-input-group">
                            <input type="color" id="accent_color_picker" 
                                   value="<?php echo $currentSettings['accent_color']; ?>">
                            <input type="text" id="accent_color" name="accent_color" required
                                   value="<?php echo $currentSettings['accent_color']; ?>"
                                   pattern="^#[a-fA-F0-9]{6}$"
                                   placeholder="#dc2626">
                        </div>
                        <div class="form-help">Secondary color used for buttons, links, and highlights.</div>
                    </div>
                </div>
                
                <!-- Typography -->
                <div class="settings-section">
                    <h3>Typography</h3>
                    
                    <div class="form-group">
                        <label for="font_family">Font Family *</label>
                        <select id="font_family" name="font_family" required>
                            <option value="Arial, sans-serif" <?php echo $currentSettings['font_family'] === 'Arial, sans-serif' ? 'selected' : ''; ?>>Arial</option>
                            <option value="Helvetica, Arial, sans-serif" <?php echo $currentSettings['font_family'] === 'Helvetica, Arial, sans-serif' ? 'selected' : ''; ?>>Helvetica</option>
                            <option value="'Times New Roman', Times, serif" <?php echo $currentSettings['font_family'] === "'Times New Roman', Times, serif" ? 'selected' : ''; ?>>Times New Roman</option>
                            <option value="Georgia, serif" <?php echo $currentSettings['font_family'] === 'Georgia, serif' ? 'selected' : ''; ?>>Georgia</option>
                            <option value="'Courier New', Courier, monospace" <?php echo $currentSettings['font_family'] === "'Courier New', Courier, monospace" ? 'selected' : ''; ?>>Courier New</option>
                            <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" <?php echo $currentSettings['font_family'] === "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" ? 'selected' : ''; ?>>System Default</option>
                            <option value="'Open Sans', sans-serif" <?php echo $currentSettings['font_family'] === "'Open Sans', sans-serif" ? 'selected' : ''; ?>>Open Sans</option>
                            <option value="'Roboto', sans-serif" <?php echo $currentSettings['font_family'] === "'Roboto', sans-serif" ? 'selected' : ''; ?>>Roboto</option>
                            <option value="'Lato', sans-serif" <?php echo $currentSettings['font_family'] === "'Lato', sans-serif" ? 'selected' : ''; ?>>Lato</option>
                        </select>
                        <div class="form-help">Font family used throughout the website.</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Design Settings</button>
                    <a href="/admin/" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
        
        <!-- Backup Management -->
        <div class="card">
            <h2>Backup Management</h2>
            
            <!-- Create Backup -->
            <div class="settings-section">
                <h3>Create Backup</h3>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="form-group">
                        <label for="backup_name">Backup Name (Optional)</label>
                        <input type="text" id="backup_name" name="backup_name" 
                               placeholder="e.g., Before Holiday Theme"
                               maxlength="50">
                        <div class="form-help">Leave empty for automatic naming</div>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">Create Backup</button>
                </form>
            </div>
            
            <!-- Backup List -->
            <div class="settings-section">
                <h3>Available Backups</h3>
                
                <?php if (empty($backupList)): ?>
                    <p>No backups available. Create your first backup above.</p>
                <?php else: ?>
                    <div class="backup-list">
                        <?php foreach ($backupList as $backup): ?>
                            <div class="backup-item">
                                <div class="backup-info">
                                    <h4><?php echo htmlspecialchars($backup['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($backup['description']); ?></p>
                                    <small>
                                        Created: <?php echo date('M j, Y g:i A', strtotime($backup['created_at'])); ?> | 
                                        Size: <?php echo number_format($backup['size'] / 1024, 1); ?> KB
                                    </small>
                                </div>
                                <div class="backup-actions">
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="restore_backup">
                                        <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" 
                                                onclick="return confirm('Are you sure you want to restore this backup? Current settings will be backed up automatically.')">
                                            Restore
                                        </button>
                                    </form>
                                    
                                    <?php if (strpos($backup['filename'], 'pre_restore_') !== 0): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this backup? This action cannot be undone.')">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Color picker synchronization
        const themeColorPicker = document.getElementById('theme_color_picker');
        const themeColorInput = document.getElementById('theme_color');
        const accentColorPicker = document.getElementById('accent_color_picker');
        const accentColorInput = document.getElementById('accent_color');
        const fontFamilySelect = document.getElementById('font_family');
        
        // Preview elements
        const previewHeader = document.getElementById('previewHeader');
        const previewButton = document.getElementById('previewButton');
        const previewFont = document.getElementById('previewFont');
        
        // Sync color picker with text input
        themeColorPicker.addEventListener('input', function() {
            themeColorInput.value = this.value;
            updatePreview();
        });
        
        themeColorInput.addEventListener('input', function() {
            if (this.value.match(/^#[a-fA-F0-9]{6}$/)) {
                themeColorPicker.value = this.value;
                updatePreview();
            }
        });
        
        accentColorPicker.addEventListener('input', function() {
            accentColorInput.value = this.value;
            updatePreview();
        });
        
        accentColorInput.addEventListener('input', function() {
            if (this.value.match(/^#[a-fA-F0-9]{6}$/)) {
                accentColorPicker.value = this.value;
                updatePreview();
            }
        });
        
        fontFamilySelect.addEventListener('change', function() {
            updatePreview();
        });
        
        function updatePreview() {
            const themeColor = themeColorInput.value;
            const accentColor = accentColorInput.value;
            const fontFamily = fontFamilySelect.value;
            
            if (themeColor.match(/^#[a-fA-F0-9]{6}$/)) {
                previewHeader.style.backgroundColor = themeColor;
            }
            
            if (accentColor.match(/^#[a-fA-F0-9]{6}$/)) {
                previewButton.style.backgroundColor = accentColor;
            }
            
            previewFont.style.fontFamily = fontFamily;
        }
        
        // Initialize preview
        updatePreview();
    </script>
</body>
</html>