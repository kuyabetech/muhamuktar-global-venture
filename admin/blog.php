<?php
// admin/blog.php - Blog Management

$page_title = "Blog Management";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Initialize database tables
try {
    // Blog posts table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_posts (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            content LONGTEXT,
            excerpt TEXT,
            featured_image VARCHAR(255),
            categories VARCHAR(255),
            tags TEXT,
            status ENUM('published','draft','pending') DEFAULT 'draft',
            allow_comments TINYINT(1) DEFAULT 1,
            view_count INT DEFAULT 0,
            author_id INT,
            published_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_published (published_at)
        )
    ");

    // Blog categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            post_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Blog comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_comments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            post_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            author_name VARCHAR(255) NOT NULL,
            author_email VARCHAR(255),
            author_website VARCHAR(255),
            content TEXT NOT NULL,
            status ENUM('approved','pending','spam','trash') DEFAULT 'pending',
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    error_log("Blog tables error: " . $e->getMessage());
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle actions
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = trim($_POST['excerpt'] ?? '');
        $categories = $_POST['categories'] ?? '';
        $tags = trim($_POST['tags'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
        $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : null;

        // Generate slug
        $slug = createSlug($title);
        
        // Check for unique slug
        $check_slug = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ?" . ($id ? " AND id != ?" : ""));
        if ($id) {
            $check_slug->execute([$slug, $id]);
        } else {
            $check_slug->execute([$slug]);
        }
        
        if ($check_slug->fetch()) {
            $slug = $slug . '-' . uniqid();
        }

        $errors = [];
        if (empty($title)) {
            $errors[] = "Post title is required";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO blog_posts (title, slug, content, excerpt, categories, tags, status, 
                                              allow_comments, author_id, published_at, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $title, $slug, $content, $excerpt, $categories, $tags, $status,
                        $allow_comments, $_SESSION['user_id'], $published_at
                    ]);
                    $post_id = $pdo->lastInsertId();
                    
                    // Handle featured image upload
                    if (!empty($_FILES['featured_image']['name'])) {
                        $upload_dir = '../uploads/blog/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                        $filename = $post_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE blog_posts SET featured_image = ? WHERE id = ?");
                            $stmt->execute([$filename, $post_id]);
                        }
                    }
                    
                    $success_msg = "Blog post created successfully";
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE blog_posts SET 
                            title = ?, slug = ?, content = ?, excerpt = ?, categories = ?, 
                            tags = ?, status = ?, allow_comments = ?, published_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $slug, $content, $excerpt, $categories, $tags, 
                        $status, $allow_comments, $published_at, $id
                    ]);
                    
                    // Handle featured image upload
                    if (!empty($_FILES['featured_image']['name'])) {
                        $upload_dir = '../uploads/blog/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        // Delete old image
                        $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_image = $stmt->fetchColumn();
                        if ($old_image && file_exists($upload_dir . $old_image)) {
                            unlink($upload_dir . $old_image);
                        }
                        
                        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                        $filename = $id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE blog_posts SET featured_image = ? WHERE id = ?");
                            $stmt->execute([$filename, $id]);
                        }
                    }
                    
                    $success_msg = "Blog post updated successfully";
                    
                } elseif ($_POST['action'] === 'delete' && $id > 0) {
                    // Delete featured image
                    $stmt = $pdo->prepare("SELECT featured_image FROM blog_posts WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();
                    if ($image && file_exists('../uploads/blog/' . $image)) {
                        unlink('../uploads/blog/' . $image);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Blog post deleted successfully";
                }

                // Update category post counts
                if ($_POST['action'] !== 'delete') {
                    $pdo->exec("
                        UPDATE blog_categories c 
                        SET post_count = (
                            SELECT COUNT(*) FROM blog_posts 
                            WHERE FIND_IN_SET(c.name, categories) > 0 
                            AND status = 'published'
                        )
                    ");
                }

                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }
    
    if (!empty($success_msg)) {
        header("Location: blog.php?success=" . urlencode($success_msg));
    } else {
        header("Location: blog.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Handle category actions
if ($action === 'add_category' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_name = trim($_POST['category_name'] ?? '');
    $cat_desc = trim($_POST['category_description'] ?? '');
    
    if (!empty($cat_name)) {
        $cat_slug = createSlug($cat_name);
        try {
            $stmt = $pdo->prepare("INSERT INTO blog_categories (name, slug, description) VALUES (?, ?, ?)");
            $stmt->execute([$cat_name, $cat_slug, $cat_desc]);
            $success_msg = "Category added successfully";
        } catch (Exception $e) {
            $error_msg = "Error adding category: " . $e->getMessage();
        }
    }
    header("Location: blog.php?success=" . urlencode($success_msg));
    exit;
}

// Handle delete category
if ($action === 'delete_category' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM blog_categories WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "Category deleted successfully";
    } catch (Exception $e) {
        $error_msg = "Error deleting category: " . $e->getMessage();
    }
    header("Location: blog.php?success=" . urlencode($success_msg));
    exit;
}

// Handle comment actions
if ($action === 'approve_comment' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE blog_comments SET status = 'approved' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: blog.php?tab=comments&success=Comment approved");
    exit;
}

if ($action === 'spam_comment' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE blog_comments SET status = 'spam' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: blog.php?tab=comments&success=Comment marked as spam");
    exit;
}

if ($action === 'delete_comment' && $id > 0) {
    $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: blog.php?tab=comments&success=Comment deleted");
    exit;
}

// Build search query for posts
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE ? OR content LIKE ? OR tags LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($category_filter)) {
    $where[] = "categories LIKE ?";
    $params[] = "%$category_filter%";
}

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch blog posts
$posts = $pdo->prepare("
    SELECT p.*, u.full_name AS author_name,
           (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND status = 'approved') AS comment_count
    FROM blog_posts p
    LEFT JOIN users u ON p.author_id = u.id
    $where_sql
    ORDER BY p.published_at DESC, p.created_at DESC
");
$posts->execute($params);
$posts = $posts->fetchAll();

// Fetch categories
$categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll();

// Fetch recent comments
$comments = $pdo->prepare("
    SELECT c.*, p.title AS post_title
    FROM blog_comments c
    JOIN blog_posts p ON c.post_id = p.id
    ORDER BY c.created_at DESC
    LIMIT 20
");
$comments->execute();
$comments = $comments->fetchAll();

// Get blog stats
$stats = [
    'total_posts' => $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn(),
    'drafts' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'pending'")->fetchColumn(),
    'total_comments' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'approved'")->fetchColumn(),
    'pending_comments' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'pending'")->fetchColumn(),
    'spam_comments' => $pdo->query("SELECT COUNT(*) FROM blog_comments WHERE status = 'spam'")->fetchColumn(),
    'total_categories' => count($categories)
];

// Get post for editing
$edit_post = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    $edit_post = $stmt->fetch();
}

// Current tab
$current_tab = $_GET['tab'] ?? 'posts';
require_once 'header.php';
?>

<style>
/* Responsive Blog Management */
.blog-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.blog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.blog-header h1 {
    font-size: clamp(1.8rem, 6vw, 2.3rem);
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: clamp(1rem, 2vw, 1.6rem);
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.8rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-value {
    font-size: clamp(1.8rem, 5vw, 2.4rem);
    font-weight: 700;
}

.stat-label {
    font-size: 0.95rem;
    color: var(--admin-gray);
}

/* Tabs */
.tab-nav {
    display: flex;
    gap: 1rem;
    border-bottom: 1px solid var(--admin-border);
    margin-bottom: 2rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--admin-gray);
    cursor: pointer;
    white-space: nowrap;
}

.tab-btn.active {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-primary);
}

/* Search & Filter */
.search-filter {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

/* Form (Add/Edit Post) */
.blog-form-card {
    background: white;
    padding: clamp(1.5rem, 3vw, 2rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: clamp(1rem, 2vw, 1.5rem);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.form-control,
.form-textarea {
    width: 100%;
    padding: 0.9rem;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    font-size: 1rem;
}

.logo-preview {
    max-width: 300px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    margin: 0.5rem 0;
}

/* Table (Posts & Comments) */
.table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.responsive-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 100%;
}

.responsive-table th,
.responsive-table td {
    padding: clamp(0.8rem, 1.8vw, 1.2rem);
    text-align: left;
    border-bottom: 1px solid var(--admin-border);
}

.responsive-table th {
    background: #f8f9fc;
    font-weight: 600;
    white-space: nowrap;
}

/* Mobile: stacked card layout */
@media screen and (max-width: 768px) {
    .responsive-table thead {
        display: none;
    }

    .responsive-table tr {
        display: block;
        margin-bottom: 1.3rem;
        border: 1px solid var(--admin-border);
        border-radius: 10px;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .responsive-table td {
        display: block;
        text-align: right;
        border: none;
        padding: 0.9rem 1.2rem;
        position: relative;
        border-bottom: 1px solid var(--admin-border);
    }

    .responsive-table td:last-child {
        border-bottom: 0;
    }

    .responsive-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 1.2rem;
        width: 45%;
        font-weight: 600;
        color: var(--admin-gray);
        text-align: left;
    }

    /* Center badges and status on mobile */
    .responsive-table td[data-label="Status"],
    .responsive-table td[data-label="Views"],
    .responsive-table td[data-label="Comments"] {
        text-align: center;
    }
}

@media screen and (max-width: 480px) {
    .responsive-table td {
        padding: 0.75rem 1rem;
        font-size: 0.92rem;
    }

    .responsive-table td::before {
        width: 50%;
    }
}

/* Overall responsive */
@media (max-width: 1024px) {
    .blog-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .blog-container {
        padding: 1.2rem 1.5rem;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .tab-nav {
        flex-wrap: wrap;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (max-width: 480px) {
    .blog-container {
        padding: 1rem 1.2rem;
    }
}
</style>

<div class="blog-container">

    <!-- Page Header -->
    <div class="blog-header">
        <div>
            <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 0.5rem;">
                <i class="fas fa-blog"></i> Blog Management
            </h1>
            <p style="color: var(--admin-gray);">Manage blog posts, categories, and comments</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Post
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total_posts']) ?></div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-file-alt"></i></div>
            </div>
            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                <?= number_format($stats['published']) ?> published • <?= number_format($stats['drafts']) ?> drafts
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total_comments']) ?></div>
                    <div class="stat-label">Comments</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-comments"></i></div>
            </div>
            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                <?= number_format($stats['pending_comments']) ?> pending • <?= number_format($stats['spam_comments']) ?> spam
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total_categories']) ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-tags"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <?php
                    $recent_views = $pdo->query("SELECT SUM(view_count) FROM blog_posts WHERE published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?: 0;
                    ?>
                    <div class="stat-value"><?= number_format($recent_views) ?></div>
                    <div class="stat-label">Views (30 days)</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="tab-nav">
        <a href="?tab=posts" class="tab-btn <?= $current_tab === 'posts' ? 'active' : '' ?>">Posts</a>
        <a href="?tab=categories" class="tab-btn <?= $current_tab === 'categories' ? 'active' : '' ?>">Categories</a>
        <a href="?tab=comments" class="tab-btn <?= $current_tab === 'comments' ? 'active' : '' ?>">Comments</a>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Post Form -->
        <div class="blog-form-card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_post ? 'edit' : 'plus' ?>"></i>
                <?= $edit_post ? 'Edit Post' : 'Create New Post' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_post ? 'edit' : 'add' ?>">
                <?php if ($edit_post): ?>
                    <input type="hidden" name="id" value="<?= $edit_post['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Post Title *</label>
                    <input type="text" name="title" 
                           value="<?= htmlspecialchars($edit_post['title'] ?? '') ?>" 
                           required class="form-control" placeholder="Enter post title">
                </div>

                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" id="postContent" rows="20" class="form-textarea form-control" 
                              placeholder="Write your blog post..."><?= htmlspecialchars($edit_post['content'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" rows="3" class="form-control" 
                              placeholder="Short excerpt / summary"><?= htmlspecialchars($edit_post['excerpt'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Categories</label>
                        <select name="categories" class="form-control" multiple size="5">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name']) ?>" 
                                    <?= $edit_post && strpos($edit_post['categories'] ?? '', $cat['name']) !== false ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl to select multiple</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" 
                               value="<?= htmlspecialchars($edit_post['tags'] ?? '') ?>" 
                               class="form-control" placeholder="tag1, tag2, tag3">
                        <small>Comma separated</small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="draft" <?= ($edit_post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="pending" <?= ($edit_post['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="published" <?= ($edit_post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="published_at" 
                               value="<?= isset($edit_post['published_at']) ? date('Y-m-d\TH:i', strtotime($edit_post['published_at'])) : '' ?>" 
                               class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="allow_comments" value="1" 
                               <?= ($edit_post['allow_comments'] ?? 1) ? 'checked' : '' ?>>
                        Allow Comments
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label">Featured Image</label>
                    <?php if ($edit_post && !empty($edit_post['featured_image'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <img src="<?= BASE_URL ?>uploads/blog/<?= htmlspecialchars($edit_post['featured_image']) ?>" 
                                 alt="" class="logo-preview">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="featured_image" accept="image/*" class="form-control">
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_post ? 'Update Post' : 'Publish Post' ?>
                    </button>
                    <?php if ($edit_post): ?>
                        <a href="blog.php" class="btn btn-secondary">Cancel</a>
                        <?php if ($edit_post['status'] === 'published'): ?>
                            <a href="<?= BASE_URL ?>blog/<?= $edit_post['slug'] ?>" target="_blank" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php elseif ($current_tab === 'posts'): ?>
        <!-- Posts List -->
        <div class="table-wrapper">
            <!-- Search & Filter -->
            <form method="get" class="search-filter">
                <input type="hidden" name="tab" value="posts">
                <div class="filter-grid">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search posts..." class="form-control">

                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $category_filter === $cat['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>

                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="blog.php?tab=posts" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <?php if (empty($posts)): ?>
                <div style="padding: 4rem; text-align: center; color: var(--admin-gray);">
                    <i class="fas fa-blog" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    No blog posts found
                </div>
            <?php else: ?>
                <table class="responsive-table">
                    <thead>
                        <tr style="background:#f8f9fc;">
                            <th data-label="Title">Title</th>
                            <th data-label="Categories">Categories</th>
                            <th data-label="Author">Author</th>
                            <th data-label="Date">Date</th>
                            <th data-label="Views">Views</th>
                            <th data-label="Comments">Comments</th>
                            <th data-label="Status">Status</th>
                            <th data-label="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td data-label="Title">
                                    <div style="font-weight: 600;"><?= htmlspecialchars($post['title']) ?></div>
                                    <?php if (!empty($post['excerpt'])): ?>
                                        <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                            <?= htmlspecialchars(substr($post['excerpt'], 0, 100)) ?>...
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Categories">
                                    <?php 
                                    $post_cats = explode(',', $post['categories'] ?? '');
                                    foreach (array_slice($post_cats, 0, 2) as $cat): 
                                        if (!empty(trim($cat))): ?>
                                            <span class="category-badge"><?= htmlspecialchars(trim($cat)) ?></span>
                                        <?php endif;
                                    endforeach;
                                    if (count($post_cats) > 2): ?>
                                        <span class="category-badge">+<?= count($post_cats) - 2 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Author"><?= htmlspecialchars($post['author_name'] ?? 'Unknown') ?></td>
                                <td data-label="Date">
                                    <?= $post['published_at'] ? date('M d, Y', strtotime($post['published_at'])) : 'Not published' ?>
                                </td>
                                <td data-label="Views" style="text-align:center;"><?= number_format($post['view_count']) ?></td>
                                <td data-label="Comments" style="text-align:center;"><?= number_format($post['comment_count']) ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= $post['status'] ?>">
                                        <?= ucfirst($post['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="?action=edit&id=<?= $post['id'] ?>" class="btn btn-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($post['status'] === 'published'): ?>
                                            <a href="<?= BASE_URL ?>blog/<?= $post['slug'] ?>" target="_blank" class="btn btn-secondary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Delete post: <?= addslashes($post['title']) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $post['id'] ?>">
                                            <button type="submit" class="btn btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($current_tab === 'categories'): ?>
        <!-- Categories Management -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
            <!-- Add Category Form -->
            <div class="blog-form-card">
                <h3 style="margin-bottom: 1.5rem;">Add New Category</h3>
                <form method="post" action="?action=add_category">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="category_description" rows="3" class="form-control"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </form>
            </div>

            <!-- Categories List -->
            <div class="table-wrapper">
                <h3 style="padding: 1.5rem 1.5rem 0; margin-bottom: 1rem;">All Categories</h3>
                <table class="responsive-table">
                    <thead>
                        <tr>
                            <th data-label="Name">Name</th>
                            <th data-label="Slug">Slug</th>
                            <th data-label="Posts">Posts</th>
                            <th data-label="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td data-label="Name"><?= htmlspecialchars($cat['name']) ?></td>
                                <td data-label="Slug"><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                <td data-label="Posts" style="text-align:center;"><?= number_format($cat['post_count']) ?></td>
                                <td data-label="Actions">
                                    <a href="?action=delete_category&id=<?= $cat['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete category: <?= addslashes($cat['name']) ?>?')">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($current_tab === 'comments'): ?>
        <!-- Comments Management -->
        <div class="table-wrapper">
            <h3 style="padding: 1.5rem 1.5rem 0; margin-bottom: 1rem;">Recent Comments</h3>
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th data-label="Author">Author</th>
                        <th data-label="Comment">Comment</th>
                        <th data-label="Post">Post</th>
                        <th data-label="Date">Date</th>
                        <th data-label="Status">Status</th>
                        <th data-label="Actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td data-label="Author">
                                <div><strong><?= htmlspecialchars($comment['author_name']) ?></strong></div>
                                <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                    <?= htmlspecialchars($comment['author_email']) ?>
                                </div>
                            </td>
                            <td data-label="Comment">
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars(substr($comment['content'], 0, 100)) ?>...
                                </div>
                            </td>
                            <td data-label="Post"><?= htmlspecialchars($comment['post_title']) ?></td>
                            <td data-label="Date"><?= date('M d, Y', strtotime($comment['created_at'])) ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?= $comment['status'] ?>">
                                    <?= ucfirst($comment['status']) ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <?php if ($comment['status'] !== 'approved'): ?>
                                        <a href="?action=approve_comment&id=<?= $comment['id'] ?>" 
                                           class="btn btn-success btn-sm">Approve</a>
                                    <?php endif; ?>
                                    <?php if ($comment['status'] !== 'spam'): ?>
                                        <a href="?action=spam_comment&id=<?= $comment['id'] ?>" 
                                           class="btn btn-warning btn-sm">Spam</a>
                                    <?php endif; ?>
                                    <a href="?action=delete_comment&id=<?= $comment['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this comment?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.category-badge {
    display: inline-block;
    background: var(--admin-light);
    color: var(--admin-dark);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-right: 0.25rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.status-published { background: #d1fae5; color: #065f46; }
.status-draft     { background: #f3f4f6; color: #374151; }
.status-pending   { background: #fef3c7; color: #92400e; }
.status-approved  { background: #d1fae5; color: #065f46; }
.status-spam      { background: #fee2e2; color: #991b1b; }
.status-trash     { background: #6b7280; color: white; }

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

<script>
// Tab switching for form (if used)
document.addEventListener('DOMContentLoaded', function() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabBtns.length > 0) {
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab') + 'Tab';
                
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.style.display = 'none');
                
                this.classList.add('active');
                document.getElementById(tabId).style.display = 'block';
            });
        });
    }
});
</script>