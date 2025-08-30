<?php
/**
 * Admin File Upload Management
 * Interface for managing uploaded files and images
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

$auth = AuthManager::getInstance();
$auth->requireAuth();

$uploadManager = getUploadManager();
$adminManager = new AdminManager();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'upload':
                if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $subfolder = $_POST['subfolder'] ?? '';
                    $result = $uploadManager->uploadFile($_FILES['file'], $subfolder);
                    
                    if ($result['success']) {
                        $message = 'File uploaded successfully: ' . htmlspecialchars($result['filename']);
                        $messageType = 'success';
                    } else {
                        $message = 'Upload failed: ' . htmlspecialchars($result['error']);
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Please select a file to upload.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $filePath = $_POST['file_path'] ?? '';
                if (!empty($filePath)) {
                    $result = $uploadManager->deleteFile($filePath);
                    if ($result) {
                        $message = 'File deleted successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete file.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Invalid file path.';
                    $messageType = 'error';
                }
                break;
                
            case 'set_featured':
                $filePath = $_POST['file_path'] ?? '';
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                
                if (!empty($filePath) && !empty($title)) {
                    // First, deactivate current featured print
                    $db = DatabaseManager::getInstance();
                    $db->execute("UPDATE featured_prints SET is_active = 0");
                    
                    // Create new featured print
                    $data = [
                        'title' => $title,
                        'description' => $description,
                        'image_path' => $filePath,
                        'is_active' => 1
                    ];
                    
                    $result = $adminManager->createContent('featured_prints', $data);
                    if ($result) {
                        $message = 'Featured print set successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to set featured print.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Please provide a title for the featured print.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get uploaded files
$subfolder = $_GET['folder'] ?? '';
$files = $uploadManager->listFiles($subfolder);

// Get current featured print
$currentFeatured = (new ContentManager())->getFeaturedPrint();

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Uploads - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
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
            max-width: 1200px;
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
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background-color: #fafafa;
            transition: border-color 0.2s;
        }
        
        .upload-area:hover {
            border-color: #667eea;
        }
        
        .upload-area.dragover {
            border-color: #667eea;
            background-color: #f0f4ff;
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
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 12px;
        }
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .file-item {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .file-item:hover {
            transform: translateY(-2px);
        }
        
        .file-preview {
            width: 100%;
            height: 200px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .file-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .file-info {
            padding: 1rem;
        }
        
        .file-name {
            font-weight: 500;
            margin-bottom: 0.5rem;
            word-break: break-all;
        }
        
        .file-details {
            font-size: 12px;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .featured-badge {
            background-color: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: inline-block;
        }
        
        .upload-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .upload-info h4 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }
        
        .upload-info ul {
            margin-left: 1.5rem;
            color: #333;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal h3 {
            margin-bottom: 1rem;
            color: #333;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
            color: #333;
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
            
            .file-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .file-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Uploads</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
            <a href="/admin/manage/featured-prints.php">Featured Prints</a>
            <a href="/">View Site</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Upload Form -->
        <div class="card">
            <h2>Upload New File</h2>
            
            <div class="upload-info">
                <h4>Upload Requirements:</h4>
                <ul>
                    <li>Allowed file types: <?php echo implode(', ', ALLOWED_IMAGE_TYPES); ?></li>
                    <li>Maximum file size: <?php echo formatFileSize(MAX_UPLOAD_SIZE); ?></li>
                    <li>Images will be automatically resized if larger than 2048x2048 pixels</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="upload">
                
                <div class="form-group">
                    <label for="subfolder">Folder (optional)</label>
                    <select id="subfolder" name="subfolder">
                        <option value="">Root folder</option>
                        <option value="featured">Featured prints</option>
                        <option value="gallery">Gallery</option>
                        <option value="misc">Miscellaneous</option>
                    </select>
                </div>
                
                <div class="upload-area" id="uploadArea">
                    <input type="file" id="file" name="file" accept="image/*" required style="display: none;">
                    <div id="uploadText">
                        <p><strong>Click to select a file</strong> or drag and drop here</p>
                        <p style="color: #666; font-size: 14px; margin-top: 0.5rem;">
                            Supported formats: JPG, PNG, GIF, WebP
                        </p>
                    </div>
                    <div id="filePreview" style="display: none;">
                        <img id="previewImage" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                        <p id="fileName" style="margin-top: 0.5rem; font-weight: 500;"></p>
                    </div>
                </div>
                
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
        
        <!-- Current Featured Print -->
        <?php if ($currentFeatured): ?>
            <div class="card">
                <h2>Current Featured Print</h2>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <?php if ($currentFeatured['image_path']): ?>
                        <img src="/<?php echo htmlspecialchars($currentFeatured['image_path']); ?>" 
                             alt="Featured Print" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;">
                    <?php endif; ?>
                    <div>
                        <h3><?php echo htmlspecialchars($currentFeatured['title']); ?></h3>
                        <p><?php echo htmlspecialchars($currentFeatured['description']); ?></p>
                        <small style="color: #666;">Updated: <?php echo formatDateTime($currentFeatured['updated_at']); ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Files List -->
        <div class="card">
            <h2>Uploaded Files (<?php echo count($files); ?>)</h2>
            
            <?php if (!empty($files)): ?>
                <div class="file-grid">
                    <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <?php if ($currentFeatured && $currentFeatured['image_path'] === $file['path']): ?>
                                <div class="featured-badge">Current Featured</div>
                            <?php endif; ?>
                            
                            <div class="file-preview">
                                <img src="/<?php echo htmlspecialchars($file['path']); ?>" 
                                     alt="<?php echo htmlspecialchars(basename($file['path'])); ?>"
                                     loading="lazy">
                            </div>
                            
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars(basename($file['path'])); ?></div>
                                <div class="file-details">
                                    <?php if (isset($file['dimensions'])): ?>
                                        <?php echo $file['dimensions']['width']; ?>×<?php echo $file['dimensions']['height']; ?>px<br>
                                    <?php endif; ?>
                                    <?php echo formatFileSize($file['size']); ?><br>
                                    <?php echo formatDateTime(date('Y-m-d H:i:s', $file['modified'])); ?>
                                </div>
                                
                                <div class="file-actions">
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="setFeatured('<?php echo htmlspecialchars($file['path']); ?>')">
                                        Set Featured
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="deleteFile('<?php echo htmlspecialchars($file['path']); ?>')">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Files Uploaded</h3>
                    <p>Upload your first image using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Set Featured Modal -->
    <div id="featuredModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeFeaturedModal()">&times;</span>
            <h3>Set as Featured Print</h3>
            <form method="POST" id="featuredForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="set_featured">
                <input type="hidden" name="file_path" id="featuredFilePath">
                
                <div class="form-group">
                    <label for="featuredTitle">Title *</label>
                    <input type="text" id="featuredTitle" name="title" required 
                           placeholder="e.g., Custom Dragon Figurine">
                </div>
                
                <div class="form-group">
                    <label for="featuredDescription">Description</label>
                    <input type="text" id="featuredDescription" name="description" 
                           placeholder="Brief description of the featured print">
                </div>
                
                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success">Set as Featured</button>
                    <button type="button" class="btn btn-secondary" onclick="closeFeaturedModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('file');
        const uploadText = document.getElementById('uploadText');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        const fileName = document.getElementById('fileName');
        
        // Click to upload
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect(files[0]);
            }
        });
        
        // File selection
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
        
        function handleFileSelect(file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    fileName.textContent = file.name;
                    uploadText.style.display = 'none';
                    filePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        // Featured print modal
        function setFeatured(filePath) {
            document.getElementById('featuredFilePath').value = filePath;
            document.getElementById('featuredModal').style.display = 'block';
        }
        
        function closeFeaturedModal() {
            document.getElementById('featuredModal').style.display = 'none';
            document.getElementById('featuredForm').reset();
        }
        
        // Delete file
        function deleteFile(filePath) {
            if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="file_path" value="${filePath}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modal = document.getElementById('featuredModal');
            if (e.target === modal) {
                closeFeaturedModal();
            }
        });
    </script>
</body>
</html>