<?php
// admin/categories.php - Manage Categories

$page_title = "Manage Categories";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize variables
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$message = '';
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';
$search = $_GET['search'] ?? '';

// Check and create/update categories table
function initializeCategoriesTable() {
    global $pdo;
    
    try {
        // Check if categories table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
        if (!$stmt->fetch()) {
            // Create categories table if it doesn't exist
            $pdo->exec("
                CREATE TABLE categories (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL UNIQUE,
                    description TEXT,
                    status ENUM('active','inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status)
                )
            ");
        }
        
        // Check for existing columns
        $stmt = $pdo->query("DESCRIBE categories");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add missing columns
        $columns_to_add = [
            'parent_id' => "INT DEFAULT NULL",
            'display_order' => "INT DEFAULT 0",
            'image' => "VARCHAR(255) DEFAULT NULL",
            'meta_title' => "VARCHAR(255) DEFAULT NULL",
            'meta_description' => "TEXT DEFAULT NULL",
            'meta_keywords' => "TEXT DEFAULT NULL"
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                try {
                    $pdo->exec("ALTER TABLE categories ADD COLUMN $column $definition");
                    
                    // Add foreign key for parent_id
                    if ($column === 'parent_id') {
                        $pdo->exec("ALTER TABLE categories ADD INDEX idx_parent (parent_id)");
                        // Note: Foreign key will be added after both tables exist
                    }
                } catch (Exception $e) {
                    error_log("Failed to add column $column: " . $e->getMessage());
                }
            }
        }
        
        // Try to add foreign key constraint (may fail if categories table doesn't have data yet)
        try {
            $pdo->exec("
                ALTER TABLE categories 
                ADD CONSTRAINT fk_parent 
                FOREIGN KEY (parent_id) 
                REFERENCES categories(id) 
                ON DELETE SET NULL
            ");
        } catch (Exception $e) {
            // Foreign key may already exist or table may be empty
            error_log("Foreign key constraint note: " . $e->getMessage());
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Categories table initialization error: " . $e->getMessage());
        return false;
    }
}

// Initialize database
initializeCategoriesTable();

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $status = $_POST['status'] ?? 'active';
        $display_order = (int)($_POST['display_order'] ?? 0);
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_keywords = trim($_POST['meta_keywords'] ?? '');

        // Validation
        $errors = [];
        if (empty($name)) {
            $errors[] = "Category name is required.";
        }
        if (strlen($name) > 255) {
            $errors[] = "Category name must be less than 255 characters.";
        }
        if ($parent_id === $id) {
            $errors[] = "Category cannot be its own parent.";
        }

        if (empty($errors)) {
            // Generate slug
            $slug = createSlug($name);
            
            try {
                if ($_POST['action'] === 'add') {
                    // Check for existing slug
                    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
                    $check->execute([$slug]);
                    if ($check->fetch()) {
                        $errors[] = "A category with this name already exists.";
                    } else {
                        // Build dynamic SQL based on available columns
                        $columns = ['name', 'slug', 'description', 'status', 'display_order'];
                        $placeholders = array_fill(0, count($columns), '?');
                        $values = [$name, $slug, $description, $status, $display_order];
                        
                        // Add optional columns
                        $optional_columns = [
                            'parent_id' => $parent_id,
                            'meta_title' => $meta_title,
                            'meta_description' => $meta_description,
                            'meta_keywords' => $meta_keywords
                        ];
                        
                        foreach ($optional_columns as $col => $value) {
                            if ($value !== null && $value !== '') {
                                $columns[] = $col;
                                $placeholders[] = '?';
                                $values[] = $value;
                            }
                        }
                        
                        $sql = "INSERT INTO categories (" . implode(', ', $columns) . ") 
                                VALUES (" . implode(', ', $placeholders) . ")";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($values);
                        $success_msg = "Category added successfully!";
                    }
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    // Check for slug collision excluding current category
                    $check = $pdo->prepare("SELECT id FROM categories WHERE slug = ? AND id != ?");
                    $check->execute([$slug, $id]);
                    if ($check->fetch()) {
                        $errors[] = "Another category already uses this name.";
                    } else {
                        // Build dynamic UPDATE SQL
                        $sql = "UPDATE categories SET 
                                name = ?, slug = ?, description = ?, status = ?, display_order = ?";
                        $values = [$name, $slug, $description, $status, $display_order];
                        
                        // Add optional columns
                        $optional_updates = [
                            'parent_id' => $parent_id,
                            'meta_title' => $meta_title,
                            'meta_description' => $meta_description,
                            'meta_keywords' => $meta_keywords
                        ];
                        
                        foreach ($optional_updates as $col => $value) {
                            $sql .= ", $col = ?";
                            $values[] = $value;
                        }
                        
                        $sql .= " WHERE id = ?";
                        $values[] = $id;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($values);
                        $success_msg = "Category updated successfully!";
                    }
                    
                } elseif ($_POST['action'] === 'delete' && $id > 0) {
                    // Check for subcategories
                    $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
                    $check->execute([$id]);
                    if ($check->fetchColumn() > 0) {
                        $errors[] = "Cannot delete: This category has subcategories. Delete subcategories first.";
                    } else {
                        // Check for associated products
                        $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                        $check->execute([$id]);
                        if ($check->fetchColumn() > 0) {
                            $errors[] = "Cannot delete: This category has products assigned. Reassign or delete products first.";
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                            $stmt->execute([$id]);
                            $success_msg = "Category deleted successfully.";
                        }
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
    
    // Redirect with message
    if (!empty($success_msg)) {
        header("Location: categories.php?success=" . urlencode($success_msg));
    } else {
        header("Location: categories.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch all categories with hierarchy (if parent_id column exists)
try {
    // Check if parent_id column exists
    $check_column = $pdo->query("SHOW COLUMNS FROM categories LIKE 'parent_id'");
    $has_parent_id = $check_column->fetch() ? true : false;
    
    if ($has_parent_id) {
        $categories_sql = "
            SELECT c.*, 
                   p.name AS parent_name,
                   (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count,
                   (SELECT COUNT(*) FROM categories WHERE parent_id = c.id) AS subcategory_count
            FROM categories c
            LEFT JOIN categories p ON c.parent_id = p.id
            $where_sql
            ORDER BY c.display_order ASC, c.name ASC
        ";
    } else {
        // Fallback if parent_id column doesn't exist yet
        $categories_sql = "
            SELECT c.*, 
                   NULL as parent_name,
                   (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count,
                   0 AS subcategory_count
            FROM categories c
            $where_sql
            ORDER BY c.name ASC
        ";
    }
    
    $categories_stmt = $pdo->prepare($categories_sql);
    $categories_stmt->execute($params);
    $all_categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_categories = [];
}

// Build hierarchical categories (if parent_id exists)
function buildCategoryTree($categories, $parent_id = null, $depth = 0) {
    $tree = [];
    foreach ($categories as $category) {
        if (isset($category['parent_id']) && $category['parent_id'] == $parent_id) {
            $category['depth'] = $depth;
            $category['children'] = buildCategoryTree($categories, $category['id'], $depth + 1);
            $tree[] = $category;
        }
    }
    return $tree;
}

// Only build tree if we have parent_id data
$has_parent_data = false;
foreach ($all_categories as $cat) {
    if (isset($cat['parent_id'])) {
        $has_parent_data = true;
        break;
    }
}

if ($has_parent_data) {
    $categories_tree = buildCategoryTree($all_categories);
} else {
    // If no parent_id data, treat all as top-level
    $categories_tree = [];
    foreach ($all_categories as $cat) {
        $cat['depth'] = 0;
        $cat['children'] = [];
        $categories_tree[] = $cat;
    }
}

// Count stats
try {
    $total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $active_categories = $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn();
    
    // Check if products table exists
    $check_products = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($check_products->fetch()) {
        $categories_with_products = $pdo->query("SELECT COUNT(DISTINCT category_id) FROM products WHERE category_id IS NOT NULL")->fetchColumn();
    } else {
        $categories_with_products = 0;
    }
} catch (Exception $e) {
    $total_categories = $active_categories = $categories_with_products = 0;
}

// Load category for editing
$edit_cat = null;
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $edit_cat = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure all fields exist
        $edit_cat = array_merge([
            'parent_id' => null,
            'display_order' => 0,
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => ''
        ], $edit_cat);
    } catch (Exception $e) {
        $edit_cat = null;
    }
}

// Get parent categories for dropdown (excluding current category when editing)
$parent_categories = [];
try {
    $parent_sql = "SELECT id, name FROM categories WHERE status = 'active'";
    if ($edit_cat) {
        $parent_sql .= " AND id != ?";
        $stmt = $pdo->prepare($parent_sql);
        $stmt->execute([$edit_cat['id']]);
    } else {
        $stmt = $pdo->query($parent_sql);
    }
    $parent_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $parent_categories = [];
}
require_once 'header.php';
?>


<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-tags"></i> Manage Categories
            </h1>
            <p style="color: var(--admin-gray);">Organize your products with categories and subcategories</p>
        </div>
        <a href="?action=add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus"></i> Add New Category
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Category Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                <i class="fas fa-<?= $edit_cat ? 'edit' : 'plus' ?>"></i>
                <?= $edit_cat ? 'Edit Category' : 'Add New Category' ?>
            </h2>

            <form method="post" id="categoryForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="<?= $edit_cat ? 'edit' : 'add' ?>">
                <?php if ($edit_cat): ?>
                    <input type="hidden" name="id" value="<?= $edit_cat['id'] ?>">
                <?php endif; ?>

                <!-- Tabs -->
                <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 1rem; overflow-x: auto;">
                        <button type="button" class="tab-btn active" data-tab="general">General</button>
                        <button type="button" class="tab-btn" data-tab="seo">SEO</button>
                    </div>
                </div>

                <!-- General Tab -->
                <div class="tab-content active" id="generalTab">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="name" 
                                   value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>" 
                                   required class="form-control" placeholder="Enter category name" autofocus>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Parent Category</label>
                            <select name="parent_id" class="form-control">
                                <option value="">No Parent (Top Level)</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?= $parent['id'] ?>" 
                                        <?= ($edit_cat['parent_id'] ?? 0) == $parent['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($parent['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                                Select parent to create subcategory
                            </small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" min="0" 
                                   value="<?= htmlspecialchars($edit_cat['display_order'] ?? 0) ?>" 
                                   class="form-control">
                            <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                                Lower numbers appear first
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= ($edit_cat['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_cat['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="4" class="form-control" 
                                  placeholder="Describe this category (optional)"><?= htmlspecialchars($edit_cat['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SEO Tab -->
                <div class="tab-content" id="seoTab" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" 
                               value="<?= htmlspecialchars($edit_cat['meta_title'] ?? '') ?>" 
                               class="form-control" placeholder="SEO title (optional)">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            Recommended: 50-60 characters
                        </small>
                        <div id="metaTitleCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" rows="3" class="form-control" 
                                  placeholder="SEO description (optional)"><?= htmlspecialchars($edit_cat['meta_description'] ?? '') ?></textarea>
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            Recommended: 150-160 characters
                        </small>
                        <div id="metaDescCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="meta_keywords" 
                               value="<?= htmlspecialchars($edit_cat['meta_keywords'] ?? '') ?>" 
                               class="form-control" placeholder="keyword1, keyword2, keyword3">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            Separate keywords with commas
                        </small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--admin-border);">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= $edit_cat ? 'Update Category' : 'Add Category' ?>
                    </button>
                    
                    <?php if ($edit_cat): ?>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="?action=delete&id=<?= $edit_cat['id'] ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Delete this category? Products will lose category assignment.')">
                            <i class="fas fa-trash"></i> Delete Category
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Search & Filters -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="get" action="" id="filterForm">
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: end;">
                    <div>
                        <label class="form-label">Search Categories</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Search by name or description..." class="form-control">
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($total_categories) ?></div>
                        <div class="stat-label">Total Categories</div>
                    </div>
                    <div class="stat-icon primary">
                        <i class="fas fa-tags"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($active_categories) ?></div>
                        <div class="stat-label">Active Categories</div>
                    </div>
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= number_format($categories_with_products) ?></div>
                        <div class="stat-label">With Products</div>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <?php
                        $empty_categories = $pdo->query("
                            SELECT COUNT(*) 
                            FROM categories c 
                            LEFT JOIN products p ON c.id = p.category_id 
                            WHERE p.id IS NULL
                        ")->fetchColumn();
                        ?>
                        <div class="stat-value"><?= number_format($empty_categories) ?></div>
                        <div class="stat-label">Empty Categories</div>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories List -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; color: var(--admin-dark);">
                    <i class="fas fa-list"></i> All Categories (<?= number_format($total_categories) ?>)
                </h2>
                <div>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New
                    </a>
                </div>
            </div>

            <!-- Bulk Actions -->
            <form method="post" action="?action=bulk" id="bulkForm" style="margin-bottom: 1.5rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="bulk">
                
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <select name="bulk_action" class="form-control" style="width: 200px;">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="btn btn-secondary" id="applyBulkAction" disabled>
                        Apply
                    </button>
                    <div style="margin-left: auto; color: var(--admin-gray); font-size: 0.875rem;">
                        <span id="selectedCount">0</span> categories selected
                    </div>
                </div>

                <?php if (empty($all_categories)): ?>
                    <div style="padding: 3rem; text-align: center; color: var(--admin-gray);">
                        <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>No categories found</h3>
                        <p>Add your first category or adjust your search</p>
                        <a href="?action=add" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Add Your First Category
                        </a>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--admin-light);">
                                    <th style="padding: 1rem; text-align: left; width: 30px;">
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th style="padding: 1rem; text-align: left;">Name</th>
                                    <th style="padding: 1rem; text-align: left;">Parent</th>
                                    <th style="padding: 1rem; text-align: center;">Products</th>
                                    <th style="padding: 1rem; text-align: center;">Subcategories</th>
                                    <th style="padding: 1rem; text-align: center;">Status</th>
                                    <th style="padding: 1rem; text-align: center;">Order</th>
                                    <th style="padding: 1rem; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                function displayCategories($categories, $depth = 0) {
                                    global $csrf_token;
                                    $output = '';
                                    
                                    foreach ($categories as $cat) {
                                        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $depth);
                                        $has_children = !empty($cat['children']);
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--admin-border);">
                                            <td style="padding: 1rem;">
                                                <input type="checkbox" name="selected_categories[]" 
                                                       value="<?= $cat['id'] ?>" class="category-checkbox">
                                            </td>
                                            <td style="padding: 1rem;">
                                                <div style="font-weight: 600;">
                                                    <?= $indent ?>
                                                    <?php if ($has_children): ?>
                                                        <i class="fas fa-folder-tree" style="color: var(--admin-primary);"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-folder" style="color: var(--admin-warning);"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </div>
                                                <?php if (!empty($cat['description'])): ?>
                                                    <div style="font-size: 0.875rem; color: var(--admin-gray); margin-top: 0.25rem;">
                                                        <?= htmlspecialchars(substr($cat['description'], 0, 100)) . (strlen($cat['description']) > 100 ? '...' : '') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem; color: var(--admin-gray);">
                                                <?= htmlspecialchars($cat['parent_name'] ?? '—') ?>
                                            </td>
                                            <td style="padding: 1rem; text-align: center;">
                                                <span style="
                                                    display: inline-block;
                                                    padding: 0.25rem 0.75rem;
                                                    border-radius: 999px;
                                                    font-weight: 600;
                                                    background: <?= $cat['product_count'] > 0 ? 'var(--admin-success)20' : 'var(--admin-gray)20' ?>;
                                                    color: <?= $cat['product_count'] > 0 ? 'var(--admin-success)' : 'var(--admin-gray)' ?>;
                                                ">
                                                    <?= (int)$cat['product_count'] ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; text-align: center;">
                                                <?php if ($cat['subcategory_count'] > 0): ?>
                                                    <span style="
                                                        display: inline-block;
                                                        padding: 0.25rem 0.75rem;
                                                        border-radius: 999px;
                                                        font-weight: 600;
                                                        background: var(--admin-primary)20;
                                                        color: var(--admin-primary);
                                                    ">
                                                        <?= (int)$cat['subcategory_count'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--admin-gray);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 1rem; text-align: center;">
                                                <span class="status-badge status-<?= $cat['status'] ?>">
                                                    <?= ucfirst($cat['status']) ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; text-align: center; color: var(--admin-gray);">
                                                <?= $cat['display_order'] ?>
                                            </td>
                                            <td style="padding: 1rem; text-align: right;">
                                                <div class="action-buttons">
                                                    <a href="?action=edit&id=<?= $cat['id'] ?>" 
                                                       class="btn btn-secondary" 
                                                       style="padding: 0.5rem; width: 36px; height: 36px;"
                                                       title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="categories.php?action=delete&id=<?= $cat['id'] ?>" 
                                                       class="btn btn-danger" 
                                                       style="padding: 0.5rem; width: 36px; height: 36px;"
                                                       title="Delete"
                                                       onclick="return confirm('Delete category: <?= addslashes($cat['name']) ?>? Products will lose category assignment.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                        if ($has_children) {
                                            displayCategories($cat['children'], $depth + 1);
                                        }
                                    }
                                }
                                
                                displayCategories($categories_tree);
                                ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
/* Main Admin Layout */
.admin-main {
    margin-left: 260px;
    margin-top: 70px;
    padding: 2rem;
    background: #f8fafc;
    min-height: calc(100vh - 70px);
    transition: all 0.3s ease;
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    padding: 1.8rem;
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.9rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    color: #374151;
    background: white;
    transition: all 0.2s;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-control::placeholder {
    color: #9ca3af;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
    border: 1px solid #4f46e5;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #f87171);
    color: white;
    border: 1px solid #ef4444;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #ef4444);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.stat-label {
    font-size: 0.95rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.primary {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
}

.stat-icon.success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.stat-icon.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.stat-icon.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Tabs */
.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    font-size: 0.95rem;
}

.tab-btn:hover {
    color: #4f46e5;
    border-bottom-color: #e5e7eb;
}

.tab-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

.tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
    background: #f9fafb;
    white-space: nowrap;
}

td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
}

tr:hover {
    background: #f9fafb;
}

/* Category Tree Indentation */
.category-tree-item {
    position: relative;
}

.category-tree-indent {
    display: inline-block;
    width: 30px;
    text-align: center;
    color: #9ca3af;
}

.category-tree-icon {
    color: #9ca3af;
    margin-right: 8px;
    font-size: 1.1rem;
    transition: transform 0.2s;
}

.category-tree-icon.has-children {
    color: #4f46e5;
    cursor: pointer;
}

.category-tree-icon.has-children:hover {
    transform: scale(1.1);
}

/* Folder Icons */
.fa-folder-tree {
    color: #4f46e5;
    margin-right: 8px;
    font-size: 1.1rem;
}

.fa-folder {
    color: #f59e0b;
    margin-right: 8px;
    font-size: 1.1rem;
}

.fa-folder-open {
    color: #4f46e5;
    margin-right: 8px;
    font-size: 1.1rem;
}

/* Status Badges */
.status-badge {
    padding: 0.4rem 0.9rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 80px;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.action-buttons .btn {
    width: 36px;
    height: 36px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.5rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    text-decoration: none;
    color: #374151;
    transition: all 0.2s;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
}

.page-link:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.page-link.active {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    border-left: 4px solid;
    font-size: 0.95rem;
}

.alert-success {
    background: linear-gradient(90deg, #d1fae5, #ecfdf5);
    border-left-color: #10b981;
    color: #065f46;
}

.alert-danger {
    background: linear-gradient(90deg, #fee2e2, #fef2f2);
    border-left-color: #ef4444;
    color: #991b1b;
}

.alert i {
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Checkboxes and Radio Buttons */
input[type="checkbox"],
input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4f46e5;
}

/* Select Styling */
select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1.5em;
    padding-right: 2.5rem;
}

/* Textarea */
textarea.form-control {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

/* Product Count Badges */
.product-count-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 60px;
    text-align: center;
}

.product-count-badge.has-products {
    background: #d1fae5;
    color: #065f46;
}

.product-count-badge.no-products {
    background: #f3f4f6;
    color: #6b7280;
}

/* Subcategory Badge */
.subcategory-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.85rem;
    background: #dbeafe;
    color: #1e40af;
}

/* Category Hierarchy Lines */
.category-level-1 { padding-left: 0px; }
.category-level-2 { padding-left: 30px; }
.category-level-3 { padding-left: 60px; }
.category-level-4 { padding-left: 90px; }
.category-level-5 { padding-left: 120px; }

.category-level-indicator {
    position: absolute;
    left: 0;
    top: 50%;
    width: 20px;
    height: 2px;
    background: #e5e7eb;
    transform: translateY(-50%);
}

/* Drag and Drop Handle */
.drag-handle {
    cursor: move;
    color: #9ca3af;
    padding: 0.5rem;
    margin-right: 0.5rem;
}

.drag-handle:hover {
    color: #4f46e5;
}

/* Sortable Container */
.sortable-container {
    margin-bottom: 1.5rem;
}

.sortable-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    cursor: move;
    transition: all 0.2s;
}

.sortable-item:hover {
    background: #f9fafb;
    border-color: #d1d5db;
}

.sortable-item.sortable-ghost {
    opacity: 0.4;
    background: #f3f4f6;
}

.sortable-item.sortable-chosen {
    background: #dbeafe;
    border-color: #93c5fd;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .admin-main {
        margin-left: 0;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .card {
        padding: 1.25rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    table {
        font-size: 0.875rem;
    }
    
    th, td {
        padding: 0.75rem 0.5rem;
    }
    
    .btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
    
    .category-level-2 { padding-left: 20px; }
    .category-level-3 { padding-left: 40px; }
    .category-level-4 { padding-left: 60px; }
    .category-level-5 { padding-left: 80px; }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .card {
        padding: 1rem;
    }
    
    .form-control {
        padding: 0.75rem;
    }
    
    .tab-btn {
        font-size: 0.8rem;
        padding: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .category-level-2 { padding-left: 15px; }
    .category-level-3 { padding-left: 30px; }
    .category-level-4 { padding-left: 45px; }
    .category-level-5 { padding-left: 60px; }
}

/* Animation for form elements */
.form-control,
.btn,
.tab-btn,
.stat-card,
.page-link,
.sortable-item {
    transition: all 0.2s ease-in-out;
}

/* Focus states for accessibility */
.form-control:focus,
.btn:focus,
.tab-btn:focus {
    outline: 2px solid #4f46e5;
    outline-offset: 2px;
}

/* Loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #e5e7eb;
    border-top-color: #4f46e5;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Tree View Lines */
.tree-line {
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 1px;
    background: #e5e7eb;
}

.tree-line-horizontal {
    position: absolute;
    left: 15px;
    top: 50%;
    width: 10px;
    height: 1px;
    background: #e5e7eb;
    transform: translateY(-50%);
}

/* Empty State */
.empty-state {
    padding: 3rem;
    text-align: center;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #d1d5db;
}

.empty-state h3 {
    margin-bottom: 0.5rem;
    color: #374151;
}

.empty-state p {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

/* Color Variables */
:root {
    --admin-primary: #4f46e5;
    --admin-primary-dark: #4338ca;
    --admin-primary-light: #6366f1;
    --admin-secondary: #10b981;
    --admin-danger: #ef4444;
    --admin-warning: #f59e0b;
    --admin-info: #3b82f6;
    --admin-dark: #1f2937;
    --admin-light: #f9fafb;
    --admin-gray: #9ca3af;
    --admin-border: #e5e7eb;
    --admin-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --admin-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Print Styles */
@media print {
    .admin-main {
        margin: 0;
        padding: 0;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .btn,
    .tab-btn,
    .pagination,
    .action-buttons {
        display: none;
    }
}

/* Custom Scrollbar for Tables */
.table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 0 0 8px 8px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 0 0 8px 8px;
}

/* Tooltips */
[data-tooltip] {
    position: relative;
}

[data-tooltip]:hover::before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.5rem 0.75rem;
    background: #1f2937;
    color: white;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    z-index: 1000;
    margin-bottom: 5px;
}

[data-tooltip]:hover::after {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1f2937;
    margin-bottom: -5px;
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
            
            // Remove active class from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.style.display = 'block';
                activeTab.classList.add('active');
            }
        });
    });

    // SEO character counters
    const metaTitle = document.querySelector('[name="meta_title"]');
    const metaDesc = document.querySelector('[name="meta_description"]');
    
    function updateCharCounts() {
        const titleCount = document.getElementById('metaTitleCount');
        const descCount = document.getElementById('metaDescCount');
        
        if (metaTitle && titleCount) {
            const titleLength = metaTitle.value.length;
            titleCount.textContent = `${titleLength} characters`;
            titleCount.style.color = titleLength > 60 ? 'var(--admin-danger)' : 'var(--admin-success)';
        }
        
        if (metaDesc && descCount) {
            const descLength = metaDesc.value.length;
            descCount.textContent = `${descLength} characters`;
            descCount.style.color = descLength > 160 ? 'var(--admin-danger)' : 'var(--admin-success)';
        }
    }
    
    if (metaTitle) metaTitle.addEventListener('input', updateCharCounts);
    if (metaDesc) metaDesc.addEventListener('input', updateCharCounts);
    updateCharCounts();

    // Bulk actions
    const selectAll = document.getElementById('selectAll');
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const applyBulkAction = document.getElementById('applyBulkAction');
    const bulkForm = document.getElementById('bulkForm');
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.category-checkbox:checked').length;
        selectedCount.textContent = checked;
        applyBulkAction.disabled = checked === 0;
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            categoryCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
    }
    
    categoryCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    bulkForm.addEventListener('submit', function(e) {
        const action = this.querySelector('[name="bulk_action"]').value;
        const checked = document.querySelectorAll('.category-checkbox:checked').length;
        
        if (!action) {
            e.preventDefault();
            alert('Please select a bulk action');
            return;
        }
        
        if (checked === 0) {
            e.preventDefault();
            alert('Please select at least one category');
            return;
        }
        
        if (action === 'delete') {
            e.preventDefault();
            if (confirm(`Delete ${checked} selected category(s)? This cannot be undone. Products will lose category assignment.`)) {
                this.submit();
            }
        }
    });

    // Form validation
    const categoryForm = document.getElementById('categoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function(e) {
            const nameInput = this.querySelector('[name="name"]');
            const parentSelect = this.querySelector('[name="parent_id"]');
            
            // Validate name
            if (nameInput && nameInput.value.trim() === '') {
                e.preventDefault();
                alert('Category name is required');
                nameInput.focus();
                return false;
            }
            
            // Validate parent not self
            const currentId = this.querySelector('[name="id"]')?.value;
            if (currentId && parentSelect && parentSelect.value === currentId) {
                e.preventDefault();
                alert('Category cannot be its own parent');
                parentSelect.focus();
                return false;
            }
            
            return true;
        });
    }

    // Auto-slug generation
    const nameInput = document.querySelector('[name="name"]');
    if (nameInput) {
        nameInput.addEventListener('blur', function() {
            if (this.value.trim()) {
                // Generate slug (simplified)
                const slug = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                
                // You could update a slug field if you had one
                console.log('Generated slug:', slug);
            }
        });
    }

    // Expand/collapse category tree (if implemented)
    document.querySelectorAll('.fa-folder-tree').forEach(icon => {
        icon.addEventListener('click', function() {
            const row = this.closest('tr');
            const nextRow = row.nextElementSibling;
            
            if (nextRow && nextRow.querySelector('.category-checkbox')) {
                nextRow.style.display = nextRow.style.display === 'none' ? '' : 'none';
                this.classList.toggle('fa-folder-tree');
                this.classList.toggle('fa-folder-open');
            }
        });
    });
});
</script>

