<?php
// admin/products.php - Manage Products

$page_title = "Manage Products";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'admin-header.php';

// Admin only
require_admin();

// Check database structure and create missing columns
function checkDatabaseStructure() {
    global $pdo;
    
    $required_columns = [
        'brand' => 'VARCHAR(255) NULL',
        'sku' => 'VARCHAR(100) NULL',
        'upc' => 'VARCHAR(50) NULL',
        'ean' => 'VARCHAR(50) NULL',
        'model' => 'VARCHAR(100) NULL',
        'weight' => 'DECIMAL(10,2) NULL',
        'dimensions' => 'VARCHAR(50) NULL',
        'short_description' => 'TEXT NULL',
        'cost_price' => 'DECIMAL(10,2) NULL',
        'featured' => 'TINYINT(1) DEFAULT 0',
        'virtual' => 'TINYINT(1) DEFAULT 0',
        'downloadable' => 'TINYINT(1) DEFAULT 0',
        'taxable' => 'TINYINT(1) DEFAULT 1',
        'meta_title' => 'VARCHAR(255) NULL',
        'meta_description' => 'TEXT NULL',
        'meta_keywords' => 'TEXT NULL'
    ];
    
    try {
        // Check if products table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
        if (!$stmt->fetch()) {
            // Create products table if it doesn't exist
            $pdo->exec("
                CREATE TABLE products (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    slug VARCHAR(255) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL,
                    discount_price DECIMAL(10,2) NULL,
                    stock INT DEFAULT 0,
                    category_id INT NULL,
                    status ENUM('draft','active','inactive') DEFAULT 'draft',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_category (category_id),
                    INDEX idx_status (status)
                )
            ");
        }
        
        // Check for each required column and add if missing
        $stmt = $pdo->query("DESCRIBE products");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                $pdo->exec("ALTER TABLE products ADD COLUMN $column $definition");
            }
        }
        
    } catch (Exception $e) {
        // Log error but continue
        error_log("Database structure check failed: " . $e->getMessage());
    }
}

// Run database check
checkDatabaseStructure();

// Handle actions
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $slug          = createSlug($name);
    $description   = $_POST['description'] ?? '';
    $short_description = $_POST['short_description'] ?? '';
    $price         = (float)($_POST['price'] ?? 0);
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $cost_price    = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : null;
    $stock         = (int)($_POST['stock'] ?? 0);
    $sku           = trim($_POST['sku'] ?? '');
    $upc           = trim($_POST['upc'] ?? '');
    $ean           = trim($_POST['ean'] ?? '');
    $brand         = trim($_POST['brand'] ?? '');
    $model         = trim($_POST['model'] ?? '');
    $weight        = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $dimensions    = trim($_POST['dimensions'] ?? '');
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $status        = $_POST['status'] ?? 'draft';
    $featured      = isset($_POST['featured']) ? 1 : 0;
    $virtual       = isset($_POST['virtual']) ? 1 : 0;
    $downloadable  = isset($_POST['downloadable']) ? 1 : 0;
    $taxable       = isset($_POST['taxable']) ? 1 : 1;
    $meta_title    = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');

    // Validate required fields
    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if ($price <= 0) $errors[] = "Price must be greater than 0";
    if ($stock < 0) $errors[] = "Stock cannot be negative";
    if ($discount_price && $discount_price >= $price) {
        $errors[] = "Discount price must be less than regular price";
    }
    if ($cost_price && $cost_price > $price) {
        $errors[] = "Cost price cannot be greater than selling price";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'add') {
                // Build dynamic SQL query based on available columns
                $columns = [
                    'name', 'slug', 'description', 'price', 'discount_price', 'stock',
                    'category_id', 'status', 'created_at'
                ];
                $placeholders = array_fill(0, count($columns), '?');
                $values = [
                    $name, $slug, $description, $price, $discount_price, $stock,
                    $category_id, $status, date('Y-m-d H:i:s')
                ];
                
                // Add optional columns if they have values
                $optional_columns = [
                    'short_description' => $short_description,
                    'cost_price' => $cost_price,
                    'sku' => $sku,
                    'upc' => $upc,
                    'ean' => $ean,
                    'brand' => $brand,
                    'model' => $model,
                    'weight' => $weight,
                    'dimensions' => $dimensions,
                    'featured' => $featured,
                    'virtual' => $virtual,
                    'downloadable' => $downloadable,
                    'taxable' => $taxable,
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
                
                $sql = "INSERT INTO products (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $product_id = $pdo->lastInsertId();
                $message = "Product added successfully!";
                $success = true;
                
            } elseif ($action === 'edit' && $id > 0) {
                // Build dynamic UPDATE query
                $sql = "UPDATE products SET 
                        name=?, slug=?, description=?, price=?, discount_price=?, stock=?,
                        category_id=?, status=?, updated_at=?";
                $values = [
                    $name, $slug, $description, $price, $discount_price, $stock,
                    $category_id, $status, date('Y-m-d H:i:s')
                ];
                
                // Add optional columns
                $optional_updates = [
                    'short_description' => $short_description,
                    'cost_price' => $cost_price,
                    'sku' => $sku,
                    'upc' => $upc,
                    'ean' => $ean,
                    'brand' => $brand,
                    'model' => $model,
                    'weight' => $weight,
                    'dimensions' => $dimensions,
                    'featured' => $featured,
                    'virtual' => $virtual,
                    'downloadable' => $downloadable,
                    'taxable' => $taxable,
                    'meta_title' => $meta_title,
                    'meta_description' => $meta_description,
                    'meta_keywords' => $meta_keywords
                ];
                
                foreach ($optional_updates as $col => $value) {
                    if ($value !== null) {
                        $sql .= ", $col=?";
                        $values[] = $value;
                    }
                }
                
                $sql .= " WHERE id=?";
                $values[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $product_id = $id;
                $message = "Product updated successfully!";
                $success = true;
            }

            // Handle image uploads if success
            if ($success && !empty($_FILES['images']['name'][0])) {
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if (!is_dir($upload_dir . 'thumbs/')) mkdir($upload_dir . 'thumbs/', 0755, true);
                
                $max_files = 10;
                $uploaded_count = 0;

                // Check if product_images table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
                if (!$stmt->fetch()) {
                    $pdo->exec("
                        CREATE TABLE product_images (
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
                }

                // Get existing images count
                $existing_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                $existing_stmt->execute([$product_id]);
                $existing_count = $existing_stmt->fetchColumn();

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($uploaded_count >= $max_files) break;
                    if ($existing_count + $uploaded_count >= $max_files) break;
                    
                    if ($_FILES['images']['error'][$key] === 0) {
                        // Validate file type and size
                        $file_type = mime_content_type($tmp_name);
                        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        $max_size = 10 * 1024 * 1024; // 10MB
                        
                        if (!in_array($file_type, $allowed_types)) {
                            continue;
                        }
                        
                        if ($_FILES['images']['size'][$key] > $max_size) {
                            continue;
                        }

                        $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                        $filename = $product_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;

                        if (move_uploaded_file($tmp_name, $dest)) {
                            // Create thumbnail if function exists
                            if (function_exists('createThumbnail')) {
                                createThumbnail($dest, $upload_dir . 'thumbs/' . $filename, 300, 300);
                            } else {
                                // Simple copy if thumbnail function doesn't exist
                                copy($dest, $upload_dir . 'thumbs/' . $filename);
                            }
                            
                            // Check if this should be main image
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

            // Handle attributes if table exists
            if ($success && isset($_POST['attribute_name']) && isset($_POST['attribute_value'])) {
                // Check if product_attributes table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'product_attributes'");
                if ($stmt->fetch()) {
                    // Delete existing attributes
                    $pdo->prepare("DELETE FROM product_attributes WHERE product_id = ?")->execute([$product_id]);
                    
                    // Insert new attributes
                    $attrStmt = $pdo->prepare("
                        INSERT INTO product_attributes (product_id, attribute_name, attribute_value, display_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    foreach ($_POST['attribute_name'] as $index => $attr_name) {
                        $attr_value = $_POST['attribute_value'][$index] ?? '';
                        if (!empty($attr_name) && !empty($attr_value)) {
                            $attrStmt->execute([$product_id, trim($attr_name), trim($attr_value), $index]);
                        }
                    }
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
        header("Location: " . BASE_URL . "admin/products.php?success=" . urlencode($message));
    } else {
        header("Location: " . BASE_URL . "admin/products.php?error=" . urlencode($message));
    }
    exit;
}

// ... rest of your code continues with similar adjustments ...

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Fetch brands - with error handling
try {
    $brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $brands = [];
}

// For edit mode - with safe column access
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $edit_product = $stmt->fetch();
    
    if ($edit_product) {
        // Safely access columns with defaults
        $edit_product = array_merge([
            'brand' => '',
            'sku' => '',
            'upc' => '',
            'ean' => '',
            'model' => '',
            'weight' => null,
            'dimensions' => '',
            'short_description' => '',
            'cost_price' => null,
            'featured' => 0,
            'virtual' => 0,
            'downloadable' => 0,
            'taxable' => 1,
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => ''
        ], $edit_product);
        
        // Fetch product images if table exists
        try {
            $stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order");
            $stmt->execute([$id]);
            $product_images = $stmt->fetchAll();
        } catch (Exception $e) {
            $product_images = [];
        }
        
        // Fetch product attributes if table exists
        try {
            $stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ? ORDER BY display_order");
            $stmt->execute([$id]);
            $product_attributes = $stmt->fetchAll();
        } catch (Exception $e) {
            $product_attributes = [];
        }
    }
}

?>

