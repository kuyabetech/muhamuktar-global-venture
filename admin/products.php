<?php
// admin/products.php - Manage Products

$page_title = "Manage Products";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize all variables with defaults
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$message = '';
$success = false;
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$featured_filter = isset($_GET['featured']) ? (int)$_GET['featured'] : '';
$page = (int)($_GET['page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

// Initialize arrays
$products = [];
$categories = [];
$brands = [];
$edit_product = null;
$product_images = [];
$product_attributes = [];
$total_products = 0;
$total_pages = 0;
$low_stock = 0;

// Check and create necessary tables
function createProductsTable() {
    global $pdo;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                discount_price DECIMAL(10,2) NULL,
                cost_price DECIMAL(10,2) NULL,
                stock INT DEFAULT 0,
                category_id INT NULL,
                brand VARCHAR(255) NULL,
                sku VARCHAR(100) NULL,
                upc VARCHAR(50) NULL,
                ean VARCHAR(50) NULL,
                model VARCHAR(100) NULL,
                weight DECIMAL(10,2) NULL,
                dimensions VARCHAR(50) NULL,
                short_description TEXT NULL,
                status ENUM('draft','active','inactive') DEFAULT 'draft',
                featured TINYINT(1) DEFAULT 0,
                is_virtual TINYINT(1) DEFAULT 0,  -- Changed virtual to is_virtual
                downloadable TINYINT(1) DEFAULT 0,
                taxable TINYINT(1) DEFAULT 1,
                meta_title VARCHAR(255) NULL,
                meta_description TEXT NULL,
                meta_keywords TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category_id),
                INDEX idx_status (status),
                INDEX idx_featured (featured)
            )
        ");
    } catch (Exception $e) {
        error_log("Error creating products table: " . $e->getMessage());
    }
}

function createCategoriesTable() {
    global $pdo;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT,
                parent_id INT NULL,
                status ENUM('active','inactive') DEFAULT 'active',
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Exception $e) {
        error_log("Error creating categories table: " . $e->getMessage());
    }
}

function createProductImagesTable() {
    global $pdo;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_images (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                is_main TINYINT(1) DEFAULT 0,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
    } catch (Exception $e) {
        error_log("Error creating product_images table: " . $e->getMessage());
    }
}

function createProductAttributesTable() {
    global $pdo;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_attributes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                product_id INT NOT NULL,
                attribute_name VARCHAR(100) NOT NULL,
                attribute_value TEXT NOT NULL,
                display_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_product (product_id),
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            )
        ");
    } catch (Exception $e) {
        error_log("Error creating product_attributes table: " . $e->getMessage());
    }
}

// Initialize database tables
createProductsTable();
createCategoriesTable();
createProductImagesTable();
createProductAttributesTable();

// Fetch categories
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = createSlug($name);
    $description = $_POST['description'] ?? '';
    $short_description = $_POST['short_description'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $cost_price = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : null;
    $stock = (int)($_POST['stock'] ?? 0);
    $sku = trim($_POST['sku'] ?? '');
    $upc = trim($_POST['upc'] ?? '');
    $ean = trim($_POST['ean'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $dimensions = trim($_POST['dimensions'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_virtual = isset($_POST['is_virtual']) ? 1 : 0;
    $downloadable = isset($_POST['downloadable']) ? 1 : 0;
    $taxable = isset($_POST['taxable']) ? 1 : 1;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');

    // Validate
    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($stock < 0) $errors[] = "Stock cannot be negative";
    if ($discount_price && $discount_price >= $price) {
        $errors[] = "Discount price must be less than regular price";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        name, slug, description, short_description, price, discount_price, cost_price,
                        stock, sku, upc, ean, brand, model, weight, dimensions, category_id,
                        status, featured, is_virtual, downloadable, taxable, 
                        meta_title, meta_description, meta_keywords
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                
                $stmt->execute([
                    $name, $slug, $description, $short_description, $price, $discount_price, $cost_price,
                    $stock, $sku, $upc, $ean, $brand, $model, $weight, $dimensions, $category_id,
                    $status, $featured, $is_virtual, $downloadable, $taxable,
                    $meta_title, $meta_description, $meta_keywords
                ]);
                
                $product_id = $pdo->lastInsertId();
                $message = "Product added successfully!";
                $success = true;
                
            } elseif ($action === 'edit' && $id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE products SET 
                        name=?, slug=?, description=?, short_description=?, price=?, discount_price=?, cost_price=?,
                        stock=?, sku=?, upc=?, ean=?, brand=?, model=?, weight=?, dimensions=?, category_id=?,
                        status=?, featured=?, is_virtual=?, downloadable=?, taxable=?,
                        meta_title=?, meta_description=?, meta_keywords=?, updated_at=NOW()
                    WHERE id=?
                ");
                
                $stmt->execute([
                    $name, $slug, $description, $short_description, $price, $discount_price, $cost_price,
                    $stock, $sku, $upc, $ean, $brand, $model, $weight, $dimensions, $category_id,
                    $status, $featured, $is_virtual, $downloadable, $taxable,
                    $meta_title, $meta_description, $meta_keywords, $id
                ]);
                
                $product_id = $id;
                $message = "Product updated successfully!";
                $success = true;
            }

            // Handle image uploads
            if ($success && !empty($_FILES['images']['name'][0])) {
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (!is_dir($upload_dir . 'thumbs/')) mkdir($upload_dir . 'thumbs/', 0755, true);
                
                $uploaded_count = 0;
                $existing_count = 0;

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === 0) {
                        $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                        $filename = $product_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;

                        if (move_uploaded_file($tmp_name, $dest)) {
                            // Create thumbnail
                            copy($dest, $upload_dir . 'thumbs/' . $filename);
                            
                            $is_main = ($key === 0 && $existing_count === 0) ? 1 : 0;
                            
                            $imgStmt = $pdo->prepare("
                                INSERT INTO product_images (product_id, filename, is_main, display_order) 
                                VALUES (?, ?, ?, ?)
                            ");
                            
                            $display_order = $existing_count + $uploaded_count + 1;
                            $imgStmt->execute([$product_id, $filename, $is_main, $display_order]);
                            $uploaded_count++;
                        }
                    }
                }
                
                if ($uploaded_count > 0) {
                    $message .= " ($uploaded_count images uploaded)";
                }
            }

            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $success = false;
        }
    } else {
        $message = implode("<br>", $errors);
    }
    
    if ($success) {
        header("Location: products.php?success=" . urlencode($message));
        exit;
    } else {
        header("Location: products.php?error=" . urlencode($message));
        exit;
    }
}

// Handle delete action
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Product deleted successfully";
        header("Location: products.php?success=" . urlencode($message));
        exit;
    } catch (Exception $e) {
        $message = "Error deleting product: " . $e->getMessage();
        header("Location: products.php?error=" . urlencode($message));
        exit;
    }
}

// For edit mode
if ($action === 'edit' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_product) {
            // Fetch product images
            $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order");
            $stmt->execute([$id]);
            $product_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch product attributes
            $stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ? ORDER BY display_order");
            $stmt->execute([$id]);
            $product_attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Silently fail - product may not exist
    }
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($category_filter)) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($stock_filter === 'low') {
    $where[] = "p.stock < 10 AND p.stock > 0";
} elseif ($stock_filter === 'out') {
    $where[] = "p.stock = 0";
} elseif ($stock_filter === 'in') {
    $where[] = "p.stock >= 10";
}

if ($featured_filter !== '') {
    $where[] = "p.featured = ?";
    $params[] = $featured_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total products
try {
    $count_sql = "SELECT COUNT(*) FROM products p $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = (int)$count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);
} catch (Exception $e) {
    $total_products = 0;
    $total_pages = 0;
}

// Fetch products
try {
    $products_sql = "
        SELECT p.*, c.name AS category_name,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $products_stmt = $pdo->prepare($products_sql);
    $products_stmt->execute($params);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

// Get low stock count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10 AND stock > 0");
    $low_stock = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $low_stock = 0;
}

// Get active products count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $active_products = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $active_products = 0;
}

// Get featured products count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE featured = 1");
    $featured_products = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $featured_products = 0;
}
require_once 'header.php';
?>

 

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-box"></i> Manage Products
            </h1>
            <p style="color: var(--admin-gray);">Add, edit, and manage your product catalog</p>
        </div>
        <a href="?action=add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus"></i> Add New Product
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

    <!-- Search & Filters -->
    <?php if ($action !== 'add' && $action !== 'edit'): ?>
    <div class="card" style="margin-bottom: 2rem;">
        <form method="get" action="" id="filterForm">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Search Products</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Name, SKU, Brand..." class="form-control">
                </div>
                
                <div>
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Stock Status</label>
                    <select name="stock" class="form-control">
                        <option value="">All Stock</option>
                        <option value="in" <?= $stock_filter === 'in' ? 'selected' : '' ?>>In Stock (≥10)</option>
                        <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock (<10)</option>
                        <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Featured</label>
                    <select name="featured" class="form-control">
                        <option value="">All</option>
                        <option value="1" <?= $featured_filter === '1' ? 'selected' : '' ?>>Featured Only</option>
                        <option value="0" <?= $featured_filter === '0' ? 'selected' : '' ?>>Not Featured</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
        </form>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($total_products) ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($active_products) ?></div>
                    <div class="stat-label">Active Products</div>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($featured_products) ?></div>
                    <div class="stat-label">Featured Products</div>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-star"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($low_stock) ?></div>
                    <div class="stat-label">Low Stock</div>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Product Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                <i class="fas fa-<?= $edit_product ? 'edit' : 'plus' ?>"></i>
                <?= $edit_product ? 'Edit Product' : 'Add New Product' ?>
            </h2>

            <form method="post" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="action" value="<?= $edit_product ? 'edit' : 'add' ?>">
                <?php if ($edit_product): ?>
                    <input type="hidden" name="id" value="<?= $edit_product['id'] ?>">
                <?php endif; ?>

                <!-- Tabs -->
                <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 1rem; overflow-x: auto;">
                        <button type="button" class="tab-btn active" data-tab="general">General</button>
                        <button type="button" class="tab-btn" data-tab="pricing">Pricing</button>
                        <button type="button" class="tab-btn" data-tab="inventory">Inventory</button>
                        <button type="button" class="tab-btn" data-tab="images">Images</button>
                        <button type="button" class="tab-btn" data-tab="shipping">Shipping</button>
                    </div>
                </div>

                <!-- General Tab -->
                <div class="tab-content active" id="generalTab">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="name" 
                                   value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>" 
                                   required class="form-control" placeholder="Enter product name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" 
                                   value="<?= htmlspecialchars($edit_product['sku'] ?? '') ?>" 
                                   class="form-control" placeholder="SKU-001">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" 
                                   value="<?= htmlspecialchars($edit_product['brand'] ?? '') ?>" 
                                   class="form-control" placeholder="Brand name">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" 
                                        <?= ($edit_product['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" 
                                   value="<?= htmlspecialchars($edit_product['model'] ?? '') ?>" 
                                   class="form-control" placeholder="Model number">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="draft" <?= ($edit_product['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="active" <?= ($edit_product['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($edit_product['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" rows="3" class="form-control" 
                                  placeholder="Brief product description (shown in listings)"><?= htmlspecialchars($edit_product['short_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Full Description *</label>
                        <textarea name="description" rows="8" class="form-control" 
                                  placeholder="Detailed product description" required><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="featured" value="1" 
                                       <?= ($edit_product['featured'] ?? 0) ? 'checked' : '' ?>>
                                Featured Product
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Pricing Tab -->
                <div class="tab-content" id="pricingTab" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Regular Price (₦) *</label>
                            <input type="number" name="price" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_product['price'] ?? '') ?>" 
                                   required class="form-control" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sale Price (₦)</label>
                            <input type="number" name="discount_price" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_product['discount_price'] ?? '') ?>" 
                                   class="form-control" placeholder="Optional">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cost Price (₦)</label>
                            <input type="number" name="cost_price" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_product['cost_price'] ?? '') ?>" 
                                   class="form-control" placeholder="Cost price">
                        </div>
                    </div>
                </div>

                <!-- Inventory Tab -->
                <div class="tab-content" id="inventoryTab" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Stock Quantity</label>
                            <input type="number" name="stock" min="0" 
                                   value="<?= htmlspecialchars($edit_product['stock'] ?? 0) ?>" 
                                   class="form-control" id="stockInput">
                        </div>

                        <div class="form-group">
                            <label class="form-label">UPC</label>
                            <input type="text" name="upc" 
                                   value="<?= htmlspecialchars($edit_product['upc'] ?? '') ?>" 
                                   class="form-control" placeholder="UPC barcode">
                        </div>

                        <div class="form-group">
                            <label class="form-label">EAN</label>
                            <input type="text" name="ean" 
                                   value="<?= htmlspecialchars($edit_product['ean'] ?? '') ?>" 
                                   class="form-control" placeholder="EAN barcode">
                        </div>
                    </div>
                </div>

                <!-- Images Tab -->
                <div class="tab-content" id="imagesTab" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Product Images</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="form-control" id="imageUpload">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.5rem;">
                            Max 10 images, 10MB each. JPG, PNG, WebP, GIF allowed.
                        </small>
                        
                        <?php if (!empty($product_images)): ?>
                            <div id="imagePreview" class="image-gallery" style="margin-top: 1rem;">
                                <?php foreach ($product_images as $img): ?>
                                    <div class="image-item">
                                        <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($img['filename']) ?>" 
                                             alt="Product Image">
                                        <div class="image-actions">
                                            <?php if ($img['is_main']): ?>
                                                <span class="main-image-badge">Main</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Shipping Tab -->
                <div class="tab-content" id="shippingTab" style="display: none;">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_product['weight'] ?? '') ?>" 
                                   class="form-control" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dimensions (L×W×H in cm)</label>
                            <input type="text" name="dimensions" 
                                   value="<?= htmlspecialchars($edit_product['dimensions'] ?? '') ?>" 
                                   class="form-control" placeholder="e.g., 10×5×2">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--admin-border);">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <?= $edit_product ? 'Update Product' : 'Add Product' ?>
                    </button>
                    
                    <?php if ($edit_product): ?>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="?action=delete&id=<?= $edit_product['id'] ?>" 
                           class="btn btn-danger" 
                           onclick="return confirm('Delete this product? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Product
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        <!-- Product List -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="margin: 0; color: var(--admin-dark);">
                    <i class="fas fa-list"></i> All Products (<?= number_format($total_products) ?>)
                </h2>
                <div>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New
                    </a>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div style="padding: 3rem; text-align: center; color: var(--admin-gray);">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No products found</h3>
                    <p>Add your first product or adjust your filters</p>
                    <a href="?action=add" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Add Your First Product
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--admin-light);">
                                <th style="padding: 1rem; text-align: left;">Image</th>
                                <th style="padding: 1rem; text-align: left;">Name</th>
                                <th style="padding: 1rem; text-align: left;">SKU</th>
                                <th style="padding: 1rem; text-align: left;">Category</th>
                                <th style="padding: 1rem; text-align: right;">Price</th>
                                <th style="padding: 1rem; text-align: center;">Stock</th>
                                <th style="padding: 1rem; text-align: center;">Status</th>
                                <th style="padding: 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <td style="padding: 1rem;">
                                        <?php if (!empty($p['main_image'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($p['main_image']) ?>" 
                                                 alt="" class="product-image" 
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: var(--admin-light); border-radius: 6px; 
                                                        display: flex; align-items: center; justify-content: center; color: var(--admin-gray);">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <div style="font-weight: 600; margin-bottom: 0.25rem;"><?= htmlspecialchars($p['name']) ?></div>
                                        <?php if (!empty($p['brand'])): ?>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray);"><?= htmlspecialchars($p['brand']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($p['featured'])): ?>
                                            <span class="featured-badge">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-family: monospace; font-size: 0.9rem;">
                                        <?= htmlspecialchars($p['sku'] ?? '-') ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <?php if (!empty($p['discount_price'])): ?>
                                            <div style="color: var(--admin-danger); font-weight: 700;">
                                                ₦<?= number_format($p['discount_price'], 2) ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray); text-decoration: line-through;">
                                                ₦<?= number_format($p['price'], 2) ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-weight: 700;">₦<?= number_format($p['price'], 2) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px; font-weight: 600; 
                                                  background: <?= $p['stock'] > 10 ? 'var(--admin-success)' : ($p['stock'] > 0 ? 'var(--admin-warning)' : 'var(--admin-danger)') ?>20;
                                                  color: <?= $p['stock'] > 10 ? 'var(--admin-success)' : ($p['stock'] > 0 ? 'var(--admin-warning)' : 'var(--admin-danger)') ?>;">
                                            <?= $p['stock'] ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: center;">
                                        <span class="status-badge status-<?= $p['status'] ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <div class="action-buttons">
                                            <a href="?action=edit&id=<?= $p['id'] ?>" 
                                               class="btn btn-secondary" 
                                               style="padding: 0.5rem; width: 36px; height: 36px;"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?action=delete&id=<?= $p['id'] ?>" 
                                               class="btn btn-danger" 
                                               style="padding: 0.5rem; width: 36px; height: 36px;"
                                               title="Delete"
                                               onclick="return confirm('Delete product: <?= addslashes($p['name']) ?>?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination" style="margin-top: 2rem;">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?><?= !empty($stock_filter) ? '&stock=' . $stock_filter : '' ?><?= $featured_filter !== '' ? '&featured=' . $featured_filter : '' ?>" 
                               class="page-link">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?><?= !empty($stock_filter) ? '&stock=' . $stock_filter : '' ?><?= $featured_filter !== '' ? '&featured=' . $featured_filter : '' ?>" 
                               class="page-link">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?><?= !empty($stock_filter) ? '&stock=' . $stock_filter : '' ?><?= $featured_filter !== '' ? '&featured=' . $featured_filter : '' ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?><?= !empty($stock_filter) ? '&stock=' . $stock_filter : '' ?><?= $featured_filter !== '' ? '&featured=' . $featured_filter : '' ?>" 
                               class="page-link">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($category_filter) ? '&category=' . $category_filter : '' ?><?= !empty($status_filter) ? '&status=' . $status_filter : '' ?><?= !empty($stock_filter) ? '&stock=' . $stock_filter : '' ?><?= $featured_filter !== '' ? '&featured=' . $featured_filter : '' ?>" 
                               class="page-link">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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

/* Product Image */
.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: white;
}

/* Image Gallery */
.image-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.image-item {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    aspect-ratio: 1/1;
}

.image-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.image-item:hover img {
    transform: scale(1.05);
}

.image-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.image-item:hover .image-actions {
    opacity: 1;
}

.image-action-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.image-action-btn:hover {
    background: rgba(0, 0, 0, 0.8);
    transform: scale(1.1);
}

.main-image-badge {
    position: absolute;
    bottom: 0.5rem;
    left: 0.5rem;
    background: #4f46e5;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
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

.status-draft {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
}

/* Featured Badge */
.featured-badge {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #92400e;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
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
}

/* Animation for form elements */
.form-control,
.btn,
.tab-btn,
.stat-card,
.page-link,
.image-item {
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
    .pagination {
        display: none;
    }
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
            
            // Update active tab button
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Show active tab content
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.style.display = 'block';
                activeTab.classList.add('active');
            }
        });
    });

    // Form validation
    const productForm = document.getElementById('productForm');
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const priceInput = this.querySelector('[name="price"]');
            const discountInput = this.querySelector('[name="discount_price"]');
            
            const price = parseFloat(priceInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            
            if (price <= 0) {
                e.preventDefault();
                alert('Price must be greater than 0');
                priceInput.focus();
                return false;
            }
            
            if (discount > 0 && discount >= price) {
                e.preventDefault();
                alert('Sale price must be less than regular price');
                discountInput.focus();
                return false;
            }
            
            return true;
        });
    }
});
</script>

