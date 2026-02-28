<?php
// admin/pages.php - Static Page Management

$page_title = "Manage Pages";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            content LONGTEXT,
            excerpt TEXT,
            featured_image VARCHAR(255),
            template VARCHAR(100) DEFAULT 'default',
            status ENUM('published','draft','private') DEFAULT 'draft',
            parent_id INT DEFAULT NULL,
            menu_order INT DEFAULT 0,
            show_in_menu TINYINT(1) DEFAULT 1,
            allow_comments TINYINT(1) DEFAULT 0,
            view_count INT DEFAULT 0,
            author_id INT,
            meta_title VARCHAR(255),
            meta_description TEXT,
            meta_keywords TEXT,
            published_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_slug (slug),
            INDEX idx_parent (parent_id),
            FOREIGN KEY (parent_id) REFERENCES pages(id) ON DELETE SET NULL
        )
    ");
} catch (Exception $e) {
    error_log("Pages table error: " . $e->getMessage());
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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = trim($_POST['excerpt'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $template = $_POST['template'] ?? 'default';
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $menu_order = (int)($_POST['menu_order'] ?? 0);
        $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
        $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');
        $published_at = !empty($_POST['published_at']) ? $_POST['published_at'] : null;

        // Generate slug
        $slug = createSlug($title);
        
        // Check for unique slug
        $check_slug = $pdo->prepare("SELECT id FROM pages WHERE slug = ?" . ($id ? " AND id != ?" : ""));
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
            $errors[] = "Page title is required";
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO pages (title, slug, content, excerpt, status, template, parent_id, 
                                          menu_order, show_in_menu, allow_comments, author_id, 
                                          meta_title, meta_description, meta_keywords, published_at, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $title, $slug, $content, $excerpt, $status, $template, $parent_id,
                        $menu_order, $show_in_menu, $allow_comments, $_SESSION['user_id'],
                        $meta_title, $meta_description, $meta_keywords, $published_at
                    ]);
                    
                    // Handle featured image upload
                    if (!empty($_FILES['featured_image']['name'])) {
                        $page_id = $pdo->lastInsertId();
                        $upload_dir = '../uploads/pages/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                        $filename = $page_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE pages SET featured_image = ? WHERE id = ?");
                            $stmt->execute([$filename, $page_id]);
                        }
                    }
                    
                    $success_msg = "Page created successfully";
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE pages SET 
                            title = ?, slug = ?, content = ?, excerpt = ?, status = ?, 
                            template = ?, parent_id = ?, menu_order = ?, show_in_menu = ?, 
                            allow_comments = ?, meta_title = ?, meta_description = ?, 
                            meta_keywords = ?, published_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $slug, $content, $excerpt, $status, $template, $parent_id,
                        $menu_order, $show_in_menu, $allow_comments, $meta_title, 
                        $meta_description, $meta_keywords, $published_at, $id
                    ]);
                    
                    // Handle featured image upload
                    if (!empty($_FILES['featured_image']['name'])) {
                        $upload_dir = '../uploads/pages/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        // Delete old image
                        $stmt = $pdo->prepare("SELECT featured_image FROM pages WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_image = $stmt->fetchColumn();
                        if ($old_image && file_exists($upload_dir . $old_image)) {
                            unlink($upload_dir . $old_image);
                        }
                        
                        $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                        $filename = $id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE pages SET featured_image = ? WHERE id = ?");
                            $stmt->execute([$filename, $id]);
                        }
                    }
                    
                    $success_msg = "Page updated successfully";
                    
                } elseif ($_POST['action'] === 'delete' && $id > 0) {
                    // Delete featured image
                    $stmt = $pdo->prepare("SELECT featured_image FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $image = $stmt->fetchColumn();
                    if ($image && file_exists('../uploads/pages/' . $image)) {
                        unlink('../uploads/pages/' . $image);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Page deleted successfully";
                }
                
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }
    
    if (!empty($success_msg)) {
        header("Location: pages.php?success=" . urlencode($success_msg));
    } else {
        header("Location: pages.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE ? OR content LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch all pages
$pages = $pdo->query("
    SELECT p.*, 
           (SELECT title FROM pages WHERE id = p.parent_id) AS parent_title,
           u.full_name AS author_name
    FROM pages p
    LEFT JOIN users u ON p.author_id = u.id
    ORDER BY p.menu_order ASC, p.title ASC
")->fetchAll();

// Build page tree
function buildPageTree($pages, $parent_id = null, $depth = 0) {
    $tree = [];
    foreach ($pages as $page) {
        if ($page['parent_id'] == $parent_id) {
            $page['depth'] = $depth;
            $page['children'] = buildPageTree($pages, $page['id'], $depth + 1);
            $tree[] = $page;
        }
    }
    return $tree;
}

$page_tree = buildPageTree($pages);

// Get page for editing
$edit_page = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->execute([$id]);
    $edit_page = $stmt->fetch();
}

// Get page templates
$templates = ['default', 'full-width', 'sidebar-left', 'sidebar-right', 'landing', 'contact'];
require_once 'header.php';
?>

<style>
/* Responsive Pages Management */
.pages-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.pages-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.pages-header h1 {
    font-size: clamp(1.8rem, 6vw, 2.3rem);
    margin: 0;
}

/* Search */
.search-form {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.search-input-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 300px;
}

/* Form (Add/Edit) */
.page-form-card {
    background: white;
    padding: clamp(1.5rem, 3vw, 2rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.tab-nav {
    display: flex;
    gap: 1rem;
    border-bottom: 1px solid var(--admin-border);
    margin-bottom: 1.5rem;
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

/* Pages Table */
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

    /* Center some columns on mobile */
    .responsive-table td[data-label="Status"],
    .responsive-table td[data-label="Template"],
    .responsive-table td[data-label="Views"] {
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination a {
    padding: 0.6rem 1rem;
    background: #f3f4f6;
    color: var(--admin-dark);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
}

.pagination a.active,
.pagination a:hover {
    background: var(--admin-primary);
    color: white;
}

/* Overall responsive */
@media (max-width: 1024px) {
    .pages-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .pages-container {
        padding: 1.2rem 1.5rem;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .tab-nav {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .pages-container {
        padding: 1rem 1.2rem;
    }
}
</style>

<div class="pages-container">

    <!-- Page Header -->
    <div class="pages-header">
        <div>
            <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 0.5rem;">
                <i class="fas fa-file"></i> Manage Pages
            </h1>
            <p style="color: var(--admin-gray);">Create and manage static pages for your website</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Page
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Page Form -->
        <div class="page-form-card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_page ? 'edit' : 'plus' ?>"></i>
                <?= $edit_page ? 'Edit Page' : 'Create New Page' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_page ? 'edit' : 'add' ?>">
                <?php if ($edit_page): ?>
                    <input type="hidden" name="id" value="<?= $edit_page['id'] ?>">
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" data-tab="content">Content</button>
                    <button type="button" class="tab-btn" data-tab="settings">Settings</button>
                    <button type="button" class="tab-btn" data-tab="seo">SEO</button>
                </div>

                <!-- Content Tab -->
                <div class="tab-content active" id="contentTab">
                    <div class="form-group">
                        <label class="form-label">Page Title *</label>
                        <input type="text" name="title" 
                               value="<?= htmlspecialchars($edit_page['title'] ?? '') ?>" 
                               required class="form-control" placeholder="Enter page title">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Content</label>
                        <textarea name="content" id="pageContent" rows="20" class="form-textarea form-control" 
                                  placeholder="Page content..."><?= htmlspecialchars($edit_page['content'] ?? '') ?></textarea>
                        <small style="color: var(--admin-gray);">HTML and basic formatting allowed</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Excerpt</label>
                        <textarea name="excerpt" rows="3" class="form-control" 
                                  placeholder="Short description / excerpt"><?= htmlspecialchars($edit_page['excerpt'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Featured Image</label>
                        <?php if ($edit_page && !empty($edit_page['featured_image'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?= BASE_URL ?>uploads/pages/<?= htmlspecialchars($edit_page['featured_image']) ?>" 
                                     alt="" class="logo-preview">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="featured_image" accept="image/*" class="form-control">
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="tab-content" id="settingsTab" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="published" <?= ($edit_page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                                <option value="draft" <?= ($edit_page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="private" <?= ($edit_page['status'] ?? '') === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Template</label>
                            <select name="template" class="form-control">
                                <?php foreach ($templates as $t): ?>
                                    <option value="<?= $t ?>" <?= ($edit_page['template'] ?? 'default') === $t ? 'selected' : '' ?>>
                                        <?= ucfirst(str_replace('-', ' ', $t)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Parent Page</label>
                            <select name="parent_id" class="form-control">
                                <option value="">No Parent (Top Level)</option>
                                <?php foreach ($pages as $p): ?>
                                    <?php if (!$edit_page || $p['id'] != $edit_page['id']): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($edit_page['parent_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
                                            <?= str_repeat('—', $p['depth'] ?? 0) ?> <?= htmlspecialchars($p['title']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Menu Order</label>
                            <input type="number" name="menu_order" 
                                   value="<?= htmlspecialchars($edit_page['menu_order'] ?? 0) ?>" 
                                   class="form-control" min="0">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="show_in_menu" value="1" 
                                       <?= ($edit_page['show_in_menu'] ?? 1) ? 'checked' : '' ?>>
                                Show in Menu
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="allow_comments" value="1" 
                                       <?= ($edit_page['allow_comments'] ?? 0) ? 'checked' : '' ?>>
                                Allow Comments
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Publish Date</label>
                        <input type="datetime-local" name="published_at" 
                               value="<?= isset($edit_page['published_at']) ? date('Y-m-d\TH:i', strtotime($edit_page['published_at'])) : '' ?>" 
                               class="form-control">
                    </div>
                </div>

                <!-- SEO Tab -->
                <div class="tab-content" id="seoTab" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" 
                               value="<?= htmlspecialchars($edit_page['meta_title'] ?? '') ?>" 
                               class="form-control" placeholder="SEO title">
                        <div id="metaTitleCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" rows="3" class="form-control" 
                                  placeholder="SEO description"><?= htmlspecialchars($edit_page['meta_description'] ?? '') ?></textarea>
                        <div id="metaDescCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="meta_keywords" 
                               value="<?= htmlspecialchars($edit_page['meta_keywords'] ?? '') ?>" 
                               class="form-control" placeholder="keyword1, keyword2, keyword3">
                    </div>
                </div>

                <!-- Form Actions -->
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_page ? 'Update Page' : 'Create Page' ?>
                    </button>
                    <?php if ($edit_page): ?>
                        <a href="pages.php" class="btn btn-secondary">Cancel</a>
                        <a href="<?= BASE_URL ?>page/<?= $edit_page['slug'] ?>" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> Preview
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Search -->
        <div class="search-form">
            <form method="get">
                <div class="search-input-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search pages..." class="form-control search-input">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="pages.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Pages List -->
        <div class="table-wrapper">
            <?php if (empty($pages)): ?>
                <div style="padding: 4rem; text-align: center; color: var(--admin-gray);">
                    <i class="fas fa-file" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    No pages found
                </div>
            <?php else: ?>
                <table class="responsive-table">
                    <thead>
                        <tr style="background:#f8f9fc;">
                            <th data-label="Title">Title</th>
                            <th data-label="Slug">Slug</th>
                            <th data-label="Status">Status</th>
                            <th data-label="Template">Template</th>
                            <th data-label="Author">Author</th>
                            <th data-label="Views">Views</th>
                            <th data-label="Last Updated">Last Updated</th>
                            <th data-label="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $p): ?>
                            <tr>
                                <td data-label="Title">
                                    <div style="font-weight: 600;">
                                        <?= str_repeat('—', $p['depth'] ?? 0) ?> 
                                        <?= htmlspecialchars($p['title']) ?>
                                    </div>
                                    <?php if (!empty($p['excerpt'])): ?>
                                        <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                            <?= htmlspecialchars(substr($p['excerpt'], 0, 100)) ?>...
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Slug"><code><?= htmlspecialchars($p['slug']) ?></code></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= $p['status'] ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Template"><?= ucfirst(str_replace('-', ' ', $p['template'])) ?></td>
                                <td data-label="Author"><?= htmlspecialchars($p['author_name'] ?? 'Unknown') ?></td>
                                <td data-label="Views" style="text-align:center;"><?= number_format($p['view_count']) ?></td>
                                <td data-label="Last Updated">
                                    <?= date('M d, Y', strtotime($p['updated_at'] ?? $p['created_at'])) ?>
                                </td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>page/<?= $p['slug'] ?>" target="_blank" class="btn btn-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Delete page: <?= addslashes($p['title']) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
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
    <?php endif; ?>
</div>

<style>
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.status-published {
    background: #d1fae5;
    color: #065f46;
}

.status-draft {
    background: #f3f4f6;
    color: #374151;
}

.status-private {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab') + 'Tab';
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.style.display = 'none');
            
            this.classList.add('active');
            document.getElementById(tabId).style.display = 'block';
        });
    });

    // SEO character counters
    const metaTitle = document.querySelector('[name="meta_title"]');
    const metaDesc = document.querySelector('[name="meta_description"]');
    
    if (metaTitle) {
        metaTitle.addEventListener('input', function() {
            const len = this.value.length;
            const el = document.getElementById('metaTitleCount');
            if (el) {
                el.textContent = len + ' characters';
                el.style.color = len > 60 ? 'var(--admin-danger)' : 'var(--admin-success)';
            }
        });
    }
    
    if (metaDesc) {
        metaDesc.addEventListener('input', function() {
            const len = this.value.length;
            const el = document.getElementById('metaDescCount');
            if (el) {
                el.textContent = len + ' characters';
                el.style.color = len > 160 ? 'var(--admin-danger)' : 'var(--admin-success)';
            }
        });
    }
});
</script>