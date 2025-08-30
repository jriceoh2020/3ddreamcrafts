<?php
/**
 * Admin General Settings
 * CRUD interface for managing general site settings
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/../../includes/functions.php';

$auth = AuthManager::getInstance();
$auth->requireAuth();

$adminManager = new AdminManager();
$config = ConfigManager::getInstance();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $settings = [
            'site_title' => validateTextInput($_POST['site_title'] ?? '', 1, 255, true),
            'facebook_url' => validateUrl($_POST['facebook_url'] ?? ''),
            'instagram_url' => validateUrl($_POST['instagram_url'] ?? ''),
            'items_per_page' => validateInteger($_POST['items_per_page'] ?? '', 1, 100),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0'
        ];
        
        // Validate required fields
        $errors = [];
        if ($settings['site_title'] === null) {
            $errors[] = 'Site title is required and must be 1-255 characters.';
        }
        if ($settings['items_per_page'] === null) {
            $errors[] = 'Items per page must be a number between 1 and 100.';
        }
        if (!empty($_POST['facebook_url']) && $settings['facebook_url'] === null) {
            $errors[] = 'Facebook URL must be a valid URL.';
        }
        if (!empty($_POST['instagram_url']) && $settings['instagram_url'] === null) {
            $errors[] = 'Instagram URL must be a valid URL.';
        }
        
        if (empty($errors)) {
            // Remove null values (empty optional fields)
            $settings = array_filter($settings, function($value) {
                return $value !== null;
            });
            
            $result = $adminManager->updateSettings($settings);
            if ($result) {
                $message = 'Settings updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to update settings. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = implode(' ', $errors);
            $messageType = 'error';
        }
    }
}

// Get current settings
$currentSettings = $adminManager->getSettings();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Settings - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
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
            max-width: 800px;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .form-help {
            font-size: 14px;
            color: #666;
            margin-top: 0.25rem;
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
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .warning-box h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }
        
        .warning-box p {
            color: #856404;
            margin: 0;
            font-size: 14px;
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
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>General Settings</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
            <a href="/admin/settings/design.php">Design Settings</a>
            <a href="/">View Site</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>General Settings</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Site Information -->
                <div class="settings-section">
                    <h3>Site Information</h3>
                    
                    <div class="form-group">
                        <label for="site_title">Site Title *</label>
                        <input type="text" id="site_title" name="site_title" required 
                               value="<?php echo htmlspecialchars($currentSettings['site_title']); ?>"
                               placeholder="e.g., 3DDreamCrafts">
                        <div class="form-help">This appears in the browser title and throughout the site.</div>
                    </div>
                </div>
                
                <!-- Social Media -->
                <div class="settings-section">
                    <h3>Social Media Links</h3>
                    
                    <div class="form-group">
                        <label for="facebook_url">Facebook URL</label>
                        <input type="url" id="facebook_url" name="facebook_url" 
                               value="<?php echo htmlspecialchars($config->get('facebook_url', '')); ?>"
                               placeholder="https://facebook.com/your-page">
                        <div class="form-help">Full URL to your Facebook page (optional).</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="instagram_url">Instagram URL</label>
                        <input type="url" id="instagram_url" name="instagram_url" 
                               value="<?php echo htmlspecialchars($config->get('instagram_url', '')); ?>"
                               placeholder="https://instagram.com/your-account">
                        <div class="form-help">Full URL to your Instagram account (optional).</div>
                    </div>
                </div>
                
                <!-- Display Settings -->
                <div class="settings-section">
                    <h3>Display Settings</h3>
                    
                    <div class="form-group">
                        <label for="items_per_page">Items Per Page</label>
                        <input type="number" id="items_per_page" name="items_per_page" 
                               min="1" max="100" required
                               value="<?php echo (int)$config->get('items_per_page', ITEMS_PER_PAGE); ?>">
                        <div class="form-help">Number of items to display per page in listings (1-100).</div>
                    </div>
                </div>
                
                <!-- Maintenance Mode -->
                <div class="settings-section">
                    <h3>Maintenance Mode</h3>
                    
                    <div class="warning-box">
                        <h4>⚠️ Warning</h4>
                        <p>Enabling maintenance mode will make the public website inaccessible to visitors. Only administrators will be able to access the site.</p>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                   <?php echo $currentSettings['maintenance_mode'] ? 'checked' : ''; ?>>
                            <label for="maintenance_mode">Enable Maintenance Mode</label>
                        </div>
                        <div class="form-help">When enabled, visitors will see a maintenance message instead of the normal website.</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <a href="/admin/" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>