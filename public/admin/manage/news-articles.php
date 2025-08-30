<?php
/**
 * Admin News Articles Management
 * CRUD interface for managing news articles with rich text editing
 */

require_once __DIR__ . '/../../../includes/config.php';
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
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'published_date' => $_POST['published_date'] ?? date('Y-m-d H:i:s'),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                $result = $adminManager->createContent('news_articles', $data);
                if ($result) {
                    $message = 'News article created successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create news article. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'title' => $_POST['title'] ?? '',
                    'content' => $_POST['content'] ?? '',
                    'published_date' => $_POST['published_date'] ?? date('Y-m-d H:i:s'),
                    'is_published' => isset($_POST['is_published']) ? 1 : 0
                ];
                
                $result = $adminManager->updateContent('news_articles', $id, $data);
                if ($result) {
                    $message = 'News article updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update news article. Please check your input.';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                $result = $adminManager->deleteContent('news_articles', $id);
                if ($result) {
                    $message = 'News article deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete news article.';
                    $messageType = 'error';
                }
                break;
                
            case 'toggle_published':
                $id = (int)($_POST['id'] ?? 0);
                $result = $adminManager->toggleActiveStatus('news_articles', $id, 'is_published');
                if ($result) {
                    $message = 'News article publication status updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update news article publication status.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all news articles with pagination
$page = (int)($_GET['page'] ?? 1);
$articlesData = $adminManager->getAllContent('news_articles', $page, 10, 'published_date', 'DESC');
$articles = $articlesData['content'];

// Get article for editing if edit mode
$editArticle = null;
if (isset($_GET['edit']) && $_GET['edit'] > 0) {
    $editArticle = $adminManager->getContentById('news_articles', (int)$_GET['edit']);
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News Articles - <?php echo htmlspecialchars(SITE_NAME); ?> Admin</title>
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
            height: 200px;
            resize: vertical;
            font-family: inherit;
        }
        
        .form-group textarea.rich-editor {
            height: 300px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .status-published {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-draft {
            background-color: #fff3cd;
            color: #856404;
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
        
        .article-preview {
            max-height: 100px;
            overflow: hidden;
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .editor-toolbar {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            padding: 0.5rem;
            border-radius: 4px 4px 0 0;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .editor-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }
        
        .editor-btn:hover {
            background-color: #e9ecef;
        }
        
        .editor-textarea {
            border-radius: 0 0 4px 4px !important;
            border-top: none !important;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .editor-toolbar {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage News Articles</h1>
        <div class="nav-links">
            <a href="/admin/">← Dashboard</a>
            <a href="/news.php" target="_blank">View News Page</a>
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
            <h2><?php echo $editArticle ? 'Edit News Article' : 'Add New News Article'; ?></h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="<?php echo $editArticle ? 'update' : 'create'; ?>">
                <?php if ($editArticle): ?>
                    <input type="hidden" name="id" value="<?php echo $editArticle['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Article Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $editArticle ? htmlspecialchars($editArticle['title']) : ''; ?>"
                           placeholder="e.g., New 3D Print Collection Available">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="published_date">Published Date *</label>
                        <input type="datetime-local" id="published_date" name="published_date" required
                               value="<?php echo $editArticle ? date('Y-m-d\TH:i', strtotime($editArticle['published_date'])) : date('Y-m-d\TH:i'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_published" name="is_published" 
                                   <?php echo (!$editArticle || $editArticle['is_published']) ? 'checked' : ''; ?>>
                            <label for="is_published">Published (visible on website)</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="content">Article Content *</label>
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" onclick="insertText('**', '**')" title="Bold">B</button>
                        <button type="button" class="editor-btn" onclick="insertText('*', '*')" title="Italic">I</button>
                        <button type="button" class="editor-btn" onclick="insertText('\n\n### ', '')" title="Heading">H</button>
                        <button type="button" class="editor-btn" onclick="insertText('\n- ', '')" title="List">•</button>
                        <button type="button" class="editor-btn" onclick="insertText('\n\n---\n\n', '')" title="Separator">—</button>
                        <button type="button" class="editor-btn" onclick="insertText('[', '](url)')" title="Link">🔗</button>
                    </div>
                    <textarea id="content" name="content" required class="rich-editor editor-textarea"
                              placeholder="Write your news article content here. You can use basic formatting:&#10;&#10;**Bold text**&#10;*Italic text*&#10;### Headings&#10;- List items&#10;&#10;Paragraphs are separated by blank lines."><?php echo $editArticle ? htmlspecialchars($editArticle['content']) : ''; ?></textarea>
                    <small style="color: #666; font-size: 12px; margin-top: 0.5rem; display: block;">
                        Tip: Use the toolbar buttons above to add formatting. Line breaks will be preserved when displayed.
                    </small>
                </div>
                
                <div class="actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editArticle ? 'Update Article' : 'Add Article'; ?>
                    </button>
                    <?php if ($editArticle): ?>
                        <a href="/admin/manage/news-articles.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Articles List -->
        <div class="card">
            <h2>All News Articles (<?php echo $articlesData['total_items']; ?>)</h2>
            
            <?php if (!empty($articles)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Published Date</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $article): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($article['title']); ?></strong>
                                    <div class="article-preview">
                                        <?php 
                                        $preview = strip_tags($article['content']);
                                        echo htmlspecialchars(strlen($preview) > 150 ? substr($preview, 0, 150) . '...' : $preview); 
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $publishedDate = new DateTime($article['published_date']);
                                    $now = new DateTime();
                                    echo date('M j, Y', strtotime($article['published_date']));
                                    echo '<br><small>' . date('g:i A', strtotime($article['published_date'])) . '</small>';
                                    
                                    if ($article['is_published'] && $publishedDate > $now) {
                                        echo '<br><small style="color: #ffc107;">Scheduled</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $article['is_published'] ? 'status-published' : 'status-draft'; ?>">
                                        <?php echo $article['is_published'] ? 'Published' : 'Draft'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($article['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?edit=<?php echo $article['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="toggle_published">
                                            <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $article['is_published'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $article['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this news article? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $article['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($articlesData['total_pages'] > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $articlesData['total_pages']; $i++): ?>
                            <?php if ($i === $articlesData['current_page']): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No News Articles Yet</h3>
                    <p>Add your first news article using the form above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Simple rich text editor functionality
        function insertText(startTag, endTag) {
            const textarea = document.getElementById('content');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            const replacement = startTag + selectedText + endTag;
            
            textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
            
            // Set cursor position
            const newCursorPos = start + startTag.length + selectedText.length;
            textarea.setSelectionRange(newCursorPos, newCursorPos);
            textarea.focus();
        }
        
        // Auto-resize textarea
        document.getElementById('content').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(300, this.scrollHeight) + 'px';
        });
        
        // Set current date/time as default for new articles
        if (!document.querySelector('input[name="id"]')) {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('published_date').value = now.toISOString().slice(0, 16);
        }
    </script>
</body>
</html>