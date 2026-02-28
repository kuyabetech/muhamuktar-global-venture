<?php
// admin/brands.php - Brand Management

$page_title = "Manage Brands";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS brands (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL UNIQUE,
            slug VARCHAR(255) NOT NULL UNIQUE,
            logo VARCHAR(255) DEFAULT NULL,
            description TEXT,
            website VARCHAR(255) DEFAULT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            featured TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            meta_title VARCHAR(255) DEFAULT NULL,
            meta_description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_featured (featured)
        )
    ");
} catch (Exception $e) {
    error_log("Brands table error: " . $e->getMessage());
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
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $display_order = (int)($_POST['display_order'] ?? 0);
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        
        // Generate slug
        $slug = createSlug($name);
        
        $errors = [];
        if (empty($name)) {
            $errors[] = "Brand name is required";
        }
        
        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    // Check for existing brand
                    $check = $pdo->prepare("SELECT id FROM brands WHERE name = ? OR slug = ?");
                    $check->execute([$name, $slug]);
                    if ($check->fetch()) {
                        $errors[] = "A brand with this name already exists";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO brands (name, slug, description, website, status, featured, display_order, meta_title, meta_description)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $slug, $description, $website, $status, $featured, $display_order, $meta_title, $meta_description]);
                        
                        // Handle logo upload
                        if (!empty($_FILES['logo']['name'])) {
                            $brand_id = $pdo->lastInsertId();
                            $upload_dir = '../uploads/brands/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                            
                            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                            $filename = $brand_id . '_' . uniqid() . '.' . $ext;
                            $dest = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                                $stmt = $pdo->prepare("UPDATE brands SET logo = ? WHERE id = ?");
                                $stmt->execute([$filename, $brand_id]);
                            }
                        }
                        
                        $success_msg = "Brand added successfully";
                    }
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    // Check for existing brand (excluding current)
                    $check = $pdo->prepare("SELECT id FROM brands WHERE (name = ? OR slug = ?) AND id != ?");
                    $check->execute([$name, $slug, $id]);
                    if ($check->fetch()) {
                        $errors[] = "Another brand already uses this name";
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE brands SET 
                                name = ?, slug = ?, description = ?, website = ?, 
                                status = ?, featured = ?, display_order = ?, 
                                meta_title = ?, meta_description = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $slug, $description, $website, $status, $featured, $display_order, $meta_title, $meta_description, $id]);
                        
                        // Handle logo upload
                        if (!empty($_FILES['logo']['name'])) {
                            $upload_dir = '../uploads/brands/';
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                            
                            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                            $filename = $id . '_' . uniqid() . '.' . $ext;
                            $dest = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                                // Delete old logo
                                $stmt = $pdo->prepare("SELECT logo FROM brands WHERE id = ?");
                                $stmt->execute([$id]);
                                $old_logo = $stmt->fetchColumn();
                                if ($old_logo && file_exists($upload_dir . $old_logo)) {
                                    unlink($upload_dir . $old_logo);
                                }
                                
                                $stmt = $pdo->prepare("UPDATE brands SET logo = ? WHERE id = ?");
                                $stmt->execute([$filename, $id]);
                            }
                        }
                        
                        $success_msg = "Brand updated successfully";
                    }
                    
                } elseif ($_POST['action'] === 'delete' && $id > 0) {
                    // Check if brand is used in products
                    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand = (SELECT name FROM brands WHERE id = ?)");
                    $check->execute([$id]);
                    if ($check->fetchColumn() > 0) {
                        $errors[] = "Cannot delete: This brand is used by products";
                    } else {
                        // Delete logo file
                        $stmt = $pdo->prepare("SELECT logo FROM brands WHERE id = ?");
                        $stmt->execute([$id]);
                        $logo = $stmt->fetchColumn();
                        if ($logo && file_exists('../uploads/brands/' . $logo)) {
                            unlink('../uploads/brands/' . $logo);
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
                        $stmt->execute([$id]);
                        $success_msg = "Brand deleted successfully";
                    }
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
        header("Location: brands.php?success=" . urlencode($success_msg));
    } else {
        header("Location: brands.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Handle logo deletion
if ($action === 'delete_logo' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT logo FROM brands WHERE id = ?");
        $stmt->execute([$id]);
        $logo = $stmt->fetchColumn();
        
        if ($logo && file_exists('../uploads/brands/' . $logo)) {
            unlink('../uploads/brands/' . $logo);
        }
        
        $stmt = $pdo->prepare("UPDATE brands SET logo = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        $success_msg = "Logo deleted";
    } catch (Exception $e) {
        $error_msg = "Error deleting logo: " . $e->getMessage();
    }
    
    header("Location: brands.php?action=edit&id=$id&success=" . urlencode($success_msg));
    exit;
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch brands
$sql = "
    SELECT b.*, 
           (SELECT COUNT(*) FROM products WHERE brand = b.name) AS product_count
    FROM brands b
    $where_sql
    ORDER BY b.display_order ASC, b.name ASC
";

$brands = $pdo->query($sql)->fetchAll();

// Get brand for editing
$edit_brand = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
    $stmt->execute([$id]);
    $edit_brand = $stmt->fetch();
}
require_once 'header.php';
?>

<style>
/* Responsive Brands Page */
.brands-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.brands-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.brands-header h1 {
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
.brand-form-card {
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
}

.logo-preview {
    max-width: 200px;
    max-height: 100px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    margin: 0.5rem 0;
}

/* Brands Table */
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
    .responsive-table td[data-label="Products"],
    .responsive-table td[data-label="Status"],
    .responsive-table td[data-label="Featured"],
    .responsive-table td[data-label="Order"] {
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
    .brands-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .brands-container {
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
    .brands-container {
        padding: 1rem 1.2rem;
    }
}
</style>

<div class="brands-container">

    <!-- Page Header -->
    <div class="brands-header">
        <div>
            <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 0.5rem;">
                <i class="fas fa-copyright"></i> Manage Brands
            </h1>
            <p style="color: var(--admin-gray);">Add and manage product brands</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Brand
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
        <!-- Add/Edit Brand Form -->
        <div class="brand-form-card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_brand ? 'edit' : 'plus' ?>"></i>
                <?= $edit_brand ? 'Edit Brand' : 'Add New Brand' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_brand ? 'edit' : 'add' ?>">
                <?php if ($edit_brand): ?>
                    <input type="hidden" name="id" value="<?= $edit_brand['id'] ?>">
                <?php endif; ?>

                <!-- Tabs -->
                <div class="tab-nav">
                    <button type="button" class="tab-btn active" data-tab="general">General</button>
                    <button type="button" class="tab-btn" data-tab="seo">SEO</button>
                </div>

                <!-- General Tab -->
                <div class="tab-content active" id="generalTab">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Brand Name *</label>
                            <input type="text" name="name" 
                                   value="<?= htmlspecialchars($edit_brand['name'] ?? '') ?>" 
                                   required class="form-control" placeholder="e.g., Nike, Samsung">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Website</label>
                            <input type="url" name="website" 
                                   value="<?= htmlspecialchars($edit_brand['website'] ?? '') ?>" 
                                   class="form-control" placeholder="https://example.com">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= ($edit_brand['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_brand['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" 
                                   value="<?= htmlspecialchars($edit_brand['display_order'] ?? 0) ?>" 
                                   class="form-control" min="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="featured" value="1" 
                                       <?= ($edit_brand['featured'] ?? 0) ? 'checked' : '' ?>>
                                Featured Brand
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Brand Logo</label>
                        <?php if ($edit_brand && !empty($edit_brand['logo'])): ?>
                            <div style="margin-bottom: 1rem; position: relative; display: inline-block;">
                                <img src="<?= BASE_URL ?>uploads/brands/<?= htmlspecialchars($edit_brand['logo']) ?>" 
                                     alt="" class="logo-preview">
                                <a href="?action=delete_logo&id=<?= $edit_brand['id'] ?>" 
                                   class="btn btn-danger" 
                                   style="position: absolute; top: -10px; right: -10px; padding: 0.25rem 0.5rem;"
                                   onclick="return confirm('Delete this logo?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" class="form-control">
                        <small>Recommended size: 200x100px. JPG, PNG, WebP allowed.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="5" class="form-textarea form-control" 
                                  placeholder="Brand description..."><?= htmlspecialchars($edit_brand['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SEO Tab -->
                <div class="tab-content" id="seoTab" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" 
                               value="<?= htmlspecialchars($edit_brand['meta_title'] ?? '') ?>" 
                               class="form-control" placeholder="SEO title">
                        <div id="metaTitleCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" rows="3" class="form-control" 
                                  placeholder="SEO description"><?= htmlspecialchars($edit_brand['meta_description'] ?? '') ?></textarea>
                        <div id="metaDescCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_brand ? 'Update Brand' : 'Add Brand' ?>
                    </button>
                    <?php if ($edit_brand): ?>
                        <a href="brands.php" class="btn btn-secondary">Cancel</a>
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
                           placeholder="Search brands..." class="form-control search-input">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="brands.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Brands List -->
        <div class="table-wrapper">
            <?php if (empty($brands)): ?>
                <div style="padding: 4rem; text-align: center; color: var(--admin-gray);">
                    <i class="fas fa-copyright" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                    No brands found
                </div>
            <?php else: ?>
                <table class="responsive-table">
                    <thead>
                        <tr style="background:#f8f9fc;">
                            <th data-label="Logo">Logo</th>
                            <th data-label="Brand Name">Brand Name</th>
                            <th data-label="Website">Website</th>
                            <th data-label="Products">Products</th>
                            <th data-label="Status">Status</th>
                            <th data-label="Featured">Featured</th>
                            <th data-label="Order">Order</th>
                            <th data-label="Actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($brands as $b): ?>
                            <tr>
                                <td data-label="Logo">
                                    <?php if (!empty($b['logo'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/brands/<?= htmlspecialchars($b['logo']) ?>" 
                                             alt="" style="max-width: 80px; max-height: 40px;">
                                    <?php else: ?>
                                        <span style="color: var(--admin-gray);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Brand Name">
                                    <strong><?= htmlspecialchars($b['name']) ?></strong>
                                    <?php if (!empty($b['description'])): ?>
                                        <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                            <?= htmlspecialchars(substr($b['description'], 0, 50)) ?>...
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Website">
                                    <?php if (!empty($b['website'])): ?>
                                        <a href="<?= htmlspecialchars($b['website']) ?>" target="_blank">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td data-label="Products" style="text-align:center;">
                                    <span class="product-count-badge <?= $b['product_count'] > 0 ? 'has-products' : 'no-products' ?>">
                                        <?= number_format($b['product_count']) ?>
                                    </span>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= $b['status'] ?>">
                                        <?= ucfirst($b['status']) ?>
                                    </span>
                                </td>
                                <td data-label="Featured" style="text-align:center;">
                                    <?php if ($b['featured']): ?>
                                        <i class="fas fa-star" style="color: var(--admin-warning);"></i>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td data-label="Order" style="text-align:center;"><?= $b['display_order'] ?></td>
                                <td data-label="Actions">
                                    <div class="action-buttons">
                                        <a href="?action=edit&id=<?= $b['id'] ?>" 
                                           class="btn btn-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('Delete brand: <?= addslashes($b['name']) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
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
.product-count-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.product-count-badge.has-products {
    background: #d1fae5;
    color: #065f46;
}

.product-count-badge.no-products {
    background: #f3f4f6;
    color: #6b7280;
}

.status-badge {
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active { background: #d1fae5; color: #065f46; }
.status-inactive { background: #fee2e2; color: #991b1b; }
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
    const titleCount = document.getElementById('metaTitleCount');
    const descCount = document.getElementById('metaDescCount');
    
    if (metaTitle && titleCount) {
        metaTitle.addEventListener('input', function() {
            const len = this.value.length;
            titleCount.textContent = len + ' characters';
            titleCount.style.color = len > 60 ? 'var(--admin-danger)' : 'var(--admin-success)';
        });
    }
    
    if (metaDesc && descCount) {
        metaDesc.addEventListener('input', function() {
            const len = this.value.length;
            descCount.textContent = len + ' characters';
            descCount.style.color = len > 160 ? 'var(--admin-danger)' : 'var(--admin-success)';
        });
    }
});
</script>