<?php
/**
 * Admin Craft Shows Management
 * CRUD interface for managing craft show entries
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/security-init.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/content.php';
require_once __DIR__ . '/../../../includes/functions.php';

$auth = AuthManager::getInstance();
$auth->requireAuth();

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
            case 'create':
                // Validate and sanitize input
                $title = validateTextInput($_POST['title'] ?? '', 0, 255, true);
                $eventDate = validateDateSecure($_POST['event_date'] ?? '');
                $location = validateTextInput($_POST['location'] ?? '', 0, 255, true);
                $description = validateTextInput($_POST['description'] ?? '', 0, 1000, false);
                
                if (!$title || !$eventDate || !$location) {
                    $message = 'Please fill in all required fields with valid data.';
                    $messageType = 'error';
                    break;
                }
                
                $data = [
                    'title' => $title,
                    'event_date' => $eventDate,
                    'location' => $location,
                    'description' => $description ?: '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                $result = $adminManager->createContent('craft_shows', $data);
                if ($result) {
                    $message = 'Craft show created successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create craft show. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'event_date' => $_POST['event_date'] ?? '',
                    'location' => $_POST['location'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'is_active' => isset($_POST['is_active']) ? 1 : 0
                ];
                
                $result = $adminManager->updateContent('craft_shows', $id, $data);
                if ($result) {
                    $message = 'Craft show updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update craft show. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $result = $adminManager->deleteContent('craft_shows', $id);
                if ($result) {
                    $message = 'Craft show deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete craft show.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_active':
                $id = (int)($_POST['id'] ?? 0);
                $result = $adminManager->toggleActiveStatus('craft_shows', $id, 'is_active');
                if ($result) {
                    $message = 'Craft show status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update craft show status.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Handle GET actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);
    
    if ($action === 'delete' && $id > 0) {
        // Show delete confirmation
        $showToDelete = $adminManager->getContentById('craft_shows', $id);
    }
}

// Get all craft shows with pagination
$page = (int)($_GET['page'] ?? 1);
$showsData = $adminManager->getAllContent('craft_shows', $page, 10, 'event_date', 'ASC');
$shows = $showsData['content'];

// Get show for editing if edit mode
$editShow = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $editShow = $adminManager->getContentById('craft_shows', (int)$_GET['edit']);
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Craft Shows - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
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
        <h1>Manage Craft Shows</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
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
            <h2><?php echo $editShow ? 'Edit Craft Show' : 'Add New Craft Show'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="<?php echo $editShow ? 'update' : 'create'; ?>">
                <?php if ($editShow): ?>
                    <input type="hidden" name="id" value="<?php echo $editShow['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Show Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $editShow ? htmlspecialchars($editShow['title']) : ''; ?>"
                           placeholder="e.g., Spring Craft Fair">
                </div>
                
                <div class="form-group">
                    <label for="event_date">Event Date *</label>
                    <input type="date" id="event_date" name="event_date" required
                           value="<?php echo $editShow ? htmlspecialchars($editShow['event_date']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" required
                           value="<?php echo $editShow ? htmlspecialchars($editShow['location']) : ''; ?>"
                           placeholder="e.g., Community Center, 123 Main St, City, State">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" 
                              placeholder="Additional details about the craft show..."><?php echo $editShow ? htmlspecialchars($editShow['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" 
                               <?php echo (!$editShow || $editShow['is_active']) ? 'checked' : ''; ?>>
                        <label for="is_active">Active (show on website)</label>
                    </div>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editShow ? 'Update Show' : 'Add Show'; ?>
                    </button>
                    <?php if ($editShow): ?>
                        <a href="/admin/manage/craft-shows.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Shows List -->
        <div class="card">
            <h2>All Craft Shows (<?php echo $showsData['total_items']; ?>)</h2>
            
            <?php if (!empty($shows)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shows as $show): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($show['title']); ?></strong>
                                    <?php if (!empty($show['description'])): ?>
                                        <br><small style="color: #666;">
                                            <?php echo htmlspecialchars(strlen($show['description']) > 100 ? 
                                                substr($show['description'], 0, 100) . '...' : 
                                                $show['description']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $eventDate = new DateTime($show['event_date']);
                                    $today = new DateTime();
                                    echo date('M j, Y', strtotime($show['event_date']));
                                    
                                    if ($eventDate < $today) {
                                        echo '<br><small style="color: #dc3545;">Past event</small>';
                                    } elseif ($eventDate->format('Y-m-d') === $today->format('Y-m-d')) {
                                        echo '<br><small style="color: #28a745;">Today!</small>';
                                    } else {
                                        $daysUntil = $today->diff($eventDate)->days;
                                        echo '<br><small style="color: #667eea;">' . $daysUntil . ' days away</small>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($show['location']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $show['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $show['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($show['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?php echo $show['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?php echo $show['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $show['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $show['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this craft show? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $show['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($showsData['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $showsData['total_pages']; $i++): ?>
                            <?php if ($i === $showsData['current_page']): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No Craft Shows Yet</h3>
                    <p>Add your first craft show using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>