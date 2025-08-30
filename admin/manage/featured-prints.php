<?php
/**
 * Admin Featured Prints Management
 * CRUD interface for managing featured prints with image upload integration
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/content.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/upload.php';

$auth = AuthManager::getInstance();
$auth->requireAuth();

$adminManager = new AdminManager();
$uploadManager = getUploadManager();
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
            case 'create':
                $imagePath = '';
                
                // Handle file upload if provided
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = $uploadManager->uploadFile($_FILES['image'], 'featured');
                    if ($uploadResult['success']) {
                        $imagePath = $uploadResult['path'];
                    } else {
                        $message = 'Upload failed: ' . htmlspecialchars($uploadResult['error']);
                        $messageType = 'error';
                        break;
                    }
                }
                
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'image_path' => $imagePath,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                $result = $adminManager->createContent('featured_prints', $data);
                if ($result) {
                    $message = 'Featured print created successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create featured print. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $existingPrint = $adminManager->getContentById('featured_prints', $id);
                
                if (!$existingPrint) {
                    $message = 'Featured print not found.';
                    $messageType = 'error';
                    break;
                }
                
                $imagePath = $existingPrint['image_path'];
                
                // Handle file upload if provided
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = $uploadManager->uploadFile($_FILES['image'], 'featured');
                    if ($uploadResult['success']) {
                        // Delete old image if it exists
                        if (!empty($existingPrint['image_path'])) {
                            $uploadManager->deleteFile($existingPrint['image_path']);
                        }
                        $imagePath = $uploadResult['path'];
                    } else {
                        $message = 'Upload failed: ' . htmlspecialchars($uploadResult['error']);
                        $messageType = 'error';
                        break;
                    }
                }
                
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'image_path' => $imagePath,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                $result = $adminManager->updateContent('featured_prints', $id, $data);
                if ($result) {
                    $message = 'Featured print updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update featured print. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $existingPrint = $adminManager->getContentById('featured_prints', $id);
                
                if ($existingPrint) {
                    // Delete associated image file
                    if (!empty($existingPrint['image_path'])) {
                        $uploadManager->deleteFile($existingPrint['image_path']);
                    }
                    
                    $result = $adminManager->deleteContent('featured_prints', $id);
                    if ($result) {
                        $message = 'Featured print deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to delete featured print.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Featured print not found.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_active':
                $id = (int)($_POST['id'] ?? 0);
                $result = $adminManager->toggleActiveStatus('featured_prints', $id, 'is_active');
                if ($result) {
                    $message = 'Featured print status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update featured print status.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all featured prints with pagination
$page = (int)($_GET['page'] ?? 1);
$printsData = $adminManager->getAllContent('featured_prints', $page, 10, 'updated_at', 'DESC');
$prints = $printsData['content'];

// Get print for editing if edit mode
$editPrint = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $editPrint = $adminManager->getContentById('featured_prints', (int)$_GET['edit']);
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Featured Prints - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
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
        
        .file-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            background-color: #fafafa;
            transition: border-color 0.2s;
        }
        
        .file-upload-area:hover {
            border-color: #667eea;
        }
        
        .file-upload-area.dragover {
            border-color: #667eea;
            background-color: #f0f4ff;
        }
        
        .current-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            margin-bottom: 1rem;
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
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 12px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        
        .pagination a:hover {
            background-color: #f0f0f0;
        }
        
        .pagination .current {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
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
        
        .image-preview {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .upload-info {
            background-color: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
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
            
            .table {
                font-size: 14px;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Featured Prints</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
            <a href="/admin/manage/uploads.php">Manage Uploads</a>
            <a href="/">View Site</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add/Edit Form -->
        <div class="card">
            <h2><?php echo $editPrint ? 'Edit Featured Print' : 'Add New Featured Print'; ?></h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="<?php echo $editPrint ? 'update' : 'create'; ?>">
                <?php if ($editPrint): ?>
                    <input type="hidden" name="id" value="<?php echo $editPrint['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Print Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $editPrint ? htmlspecialchars($editPrint['title']) : ''; ?>"
                           placeholder="e.g., Custom Dragon Figurine">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Brief description of the featured print"><?php echo $editPrint ? htmlspecialchars($editPrint['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="image">Image</label>
                    
                    <?php if ($editPrint && $editPrint['image_path']): ?>
                        <div style="margin-bottom: 1rem;">
                            <p><strong>Current Image:</strong></p>
                            <img src="/<?php echo htmlspecialchars($editPrint['image_path']); ?>" 
                                 alt="Current Image" class="current-image">
                        </div>
                    <?php endif; ?>
                    
                    <div class="upload-info">
                        <strong>Upload Requirements:</strong> 
                        <?php echo implode(', ', ALLOWED_IMAGE_TYPES); ?> files, 
                        max <?php echo formatFileSize(MAX_UPLOAD_SIZE); ?>
                    </div>
                    
                    <div class="file-upload-area" id="uploadArea">
                        <input type="file" id="image" name="image" accept="image/*" style="display: none;">
                        <div id="uploadText">
                            <p><strong>Click to select an image</strong> or drag and drop here</p>
                            <p style="color: #666; font-size: 14px; margin-top: 0.5rem;">
                                <?php echo $editPrint ? 'Leave empty to keep current image' : 'Required for new featured print'; ?>
                            </p>
                        </div>
                        <div id="imagePreview" style="display: none;">
                            <img id="previewImg" style="max-width: 200px; max-height: 200px; border-radius: 4px;">
                            <p id="imageName" style="margin-top: 0.5rem; font-weight: 500;"></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo (!$editPrint || $editPrint['is_active']) ? 'checked' : ''; ?>>
                        <label for="is_active">Active (visible on website)</label>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editPrint ? 'Update Featured Print' : 'Add Featured Print'; ?>
                    </button>
                    <?php if ($editPrint): ?>
                        <a href="/admin/manage/featured-prints.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Featured Prints List -->
        <div class="card">
            <h2>All Featured Prints (<?php echo $printsData['total_items']; ?>)</h2>
            
            <?php if (!empty($prints)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prints as $print): ?>
                            <tr>
                                <td>
                                    <?php if ($print['image_path']): ?>
                                        <img src="/<?php echo htmlspecialchars($print['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($print['title']); ?>" 
                                             class="image-preview">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 80px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px;">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($print['title']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $desc = htmlspecialchars($print['description']);
                                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc; 
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $print['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $print['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDateTime($print['updated_at']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?php echo $print['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?php echo $print['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $print['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $print['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this featured print? This will also delete the associated image file.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $print['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($printsData['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $printsData['total_pages']; $i++): ?>
                            <?php if ($i === $printsData['current_page']): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Featured Prints Yet</h3>
                    <p>Add your first featured print using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('image');
        const uploadText = document.getElementById('uploadText');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        const imageName = document.getElementById('imageName');
        
        // Click to upload
        uploadArea.addEventListener('click', () => {
            imageInput.click();
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
                imageInput.files = files;
                handleImageSelect(files[0]);
            }
        });
        
        // File selection
        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImageSelect(e.target.files[0]);
            }
        });
        
        function handleImageSelect(file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    imageName.textContent = file.name;
                    uploadText.style.display = 'none';
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>