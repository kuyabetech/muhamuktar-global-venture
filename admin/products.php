<?php
// admin/products.php - Complete Product Management with Import Feature

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

// Initialize import variables
$import_log = [];
$import_stats = ['total' => 0, 'success' => 0, 'failed' => 0, 'skipped' => 0];
$import_errors = [];

// Helper function for creating slugs
if (!function_exists('createSlug')) {
    function createSlug($text) {
        // Replace non letter or digits with -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim
        $text = trim($text, '-');
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // Lowercase
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'product-' . uniqid();
        }
        
        return $text;
    }
}

// Create upload directory for imports
$import_dir = '../uploads/imports/';
if (!is_dir($import_dir)) {
    mkdir($import_dir, 0755, true);
}

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
                is_virtual TINYINT(1) DEFAULT 0,
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

// Handle CSV Import
if (isset($_POST['import_products']) && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file type
    $allowed_extensions = ['csv', 'txt', 'xls', 'xlsx'];
    if (!in_array($file_ext, $allowed_extensions)) {
        $import_errors[] = "Invalid file type. Please upload CSV or Excel file.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $import_errors[] = "File upload failed. Error code: " . $file['error'];
    } else {
        // Process CSV file
        if (($handle = fopen($file['tmp_name'], 'r')) !== FALSE) {
            // Get headers
            $headers = fgetcsv($handle);
            
            // Validate required columns
            $required_columns = ['name', 'price'];
            $missing_columns = array_diff($required_columns, array_map('strtolower', $headers));
            
            if (!empty($missing_columns)) {
                $import_errors[] = "Missing required columns: " . implode(', ', $missing_columns);
            } else {
                $row_number = 1;
                $pdo->beginTransaction();
                
                try {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $row_number++;
                        $import_stats['total']++;
                        
                        // Combine headers with data
                        $row_data = array_combine($headers, $data);
                        
                        // Skip empty rows
                        if (empty(array_filter($row_data))) {
                            $import_stats['skipped']++;
                            continue;
                        }
                        
                        // Validate required fields
                        $errors = [];
                        if (empty(trim($row_data['name'] ?? ''))) {
                            $errors[] = "Product name is required";
                        }
                        if (!is_numeric($row_data['price'] ?? '') || (float)$row_data['price'] <= 0) {
                            $errors[] = "Valid price is required";
                        }
                        
                        if (empty($errors)) {
                            try {
                                // Prepare product data
                                $name = trim($row_data['name']);
                                $slug = createSlug($name);
                                
                                // Check if product already exists (by SKU or name)
                                $check_sql = "SELECT id FROM products WHERE ";
                                $check_params = [];
                                
                                if (!empty($row_data['sku'])) {
                                    $check_sql .= "sku = ?";
                                    $check_params[] = trim($row_data['sku']);
                                } else {
                                    $check_sql .= "name = ?";
                                    $check_params[] = $name;
                                }
                                
                                $check_stmt = $pdo->prepare($check_sql);
                                $check_stmt->execute($check_params);
                                $existing = $check_stmt->fetch();
                                
                                if ($existing && empty($row_data['force_update'])) {
                                    $import_stats['skipped']++;
                                    $import_log[] = "Row $row_number: Product already exists - " . $name;
                                    continue;
                                }
                                
                                // Handle category
                                $category_id = null;
                                if (!empty($row_data['category'])) {
                                    $category_name = trim($row_data['category']);
                                    $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
                                    $cat_stmt->execute([$category_name, createSlug($category_name)]);
                                    $category = $cat_stmt->fetch();
                                    
                                    if ($category) {
                                        $category_id = $category['id'];
                                    } else if (isset($_POST['create_categories'])) {
                                        // Create new category
                                        $cat_slug = createSlug($category_name);
                                        $cat_stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                                        $cat_stmt->execute([$category_name, $cat_slug]);
                                        $category_id = $pdo->lastInsertId();
                                        $import_log[] = "Row $row_number: Created new category: $category_name";
                                    }
                                }
                                
                                // Prepare data for insert/update
                                $product_data = [
                                    'name' => $name,
                                    'slug' => $slug,
                                    'description' => $row_data['description'] ?? '',
                                    'short_description' => $row_data['short_description'] ?? '',
                                    'price' => (float)$row_data['price'],
                                    'discount_price' => !empty($row_data['discount_price']) ? (float)$row_data['discount_price'] : null,
                                    'cost_price' => !empty($row_data['cost_price']) ? (float)$row_data['cost_price'] : null,
                                    'stock' => (int)($row_data['stock'] ?? 0),
                                    'sku' => trim($row_data['sku'] ?? ''),
                                    'upc' => trim($row_data['upc'] ?? ''),
                                    'ean' => trim($row_data['ean'] ?? ''),
                                    'brand' => trim($row_data['brand'] ?? ''),
                                    'model' => trim($row_data['model'] ?? ''),
                                    'weight' => !empty($row_data['weight']) ? (float)$row_data['weight'] : null,
                                    'dimensions' => trim($row_data['dimensions'] ?? ''),
                                    'category_id' => $category_id,
                                    'status' => $row_data['status'] ?? 'draft',
                                    'featured' => isset($row_data['featured']) && strtolower($row_data['featured']) === 'yes' ? 1 : 0,
                                    'is_virtual' => isset($row_data['is_virtual']) && strtolower($row_data['is_virtual']) === 'yes' ? 1 : 0,
                                    'downloadable' => isset($row_data['downloadable']) && strtolower($row_data['downloadable']) === 'yes' ? 1 : 0,
                                    'taxable' => isset($row_data['taxable']) && strtolower($row_data['taxable']) === 'no' ? 0 : 1,
                                    'meta_title' => trim($row_data['meta_title'] ?? ''),
                                    'meta_description' => trim($row_data['meta_description'] ?? ''),
                                    'meta_keywords' => trim($row_data['meta_keywords'] ?? '')
                                ];
                                
                                if ($existing && isset($_POST['update_existing'])) {
                                    // Update existing product
                                    $sql = "UPDATE products SET 
                                            name=?, slug=?, description=?, short_description=?, 
                                            price=?, discount_price=?, cost_price=?, stock=?, sku=?, 
                                            upc=?, ean=?, brand=?, model=?, weight=?, dimensions=?, 
                                            category_id=?, status=?, featured=?, is_virtual=?, 
                                            downloadable=?, taxable=?, meta_title=?, meta_description=?, 
                                            meta_keywords=?, updated_at=NOW() 
                                            WHERE id=?";
                                    
                                    $params = array_values($product_data);
                                    $params[] = $existing['id'];
                                    
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute($params);
                                    
                                    $import_stats['success']++;
                                    $import_log[] = "Row $row_number: Updated product - " . $name;
                                } else if (!$existing) {
                                    // Insert new product
                                    $sql = "INSERT INTO products (
                                            name, slug, description, short_description, 
                                            price, discount_price, cost_price, stock, sku, 
                                            upc, ean, brand, model, weight, dimensions, 
                                            category_id, status, featured, is_virtual, 
                                            downloadable, taxable, meta_title, meta_description, meta_keywords
                                        ) VALUES (
                                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                                        )";
                                    
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($product_data));
                                    
                                    $import_stats['success']++;
                                    $import_log[] = "Row $row_number: Imported product - " . $name;
                                }
                                
                            } catch (Exception $e) {
                                $import_stats['failed']++;
                                $import_errors[] = "Row $row_number: " . $e->getMessage();
                            }
                        } else {
                            $import_stats['failed']++;
                            $import_errors[] = "Row $row_number: " . implode(', ', $errors);
                        }
                    }
                    
                    $pdo->commit();
                    $import_success = "Import completed! Success: {$import_stats['success']}, Failed: {$import_stats['failed']}, Skipped: {$import_stats['skipped']}";
                    
                    // Log last import
                    file_put_contents($import_dir . 'last_import.txt', date('Y-m-d H:i:s'));
                    file_put_contents($import_dir . 'import_' . date('Y-m-d_H-i-s') . '.log', 
                        "Import Date: " . date('Y-m-d H:i:s') . "\n" .
                        "Stats: " . json_encode($import_stats) . "\n" .
                        "Log: " . implode("\n", $import_log) . "\n" .
                        "Errors: " . implode("\n", $import_errors)
                    );
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $import_errors[] = "Import failed: " . $e->getMessage();
                }
            }
            fclose($handle);
        } else {
            $import_errors[] = "Could not open file for reading.";
        }
    }
}

// Handle template download
if (isset($_GET['download_template'])) {
    $template_type = $_GET['download_template'];
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="product_import_template.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Define columns based on template type
    if ($template_type === 'basic') {
        $columns = [
            'name', 'price', 'description', 'short_description', 'stock', 
            'sku', 'category', 'brand', 'status', 'featured'
        ];
        
        // Write headers
        fputcsv($output, $columns);
        
        // Write sample data
        $sample_data = [
            'Sample Product 1', '25000', 'This is a sample product description',
            'Short description', '100', 'SKU-001', 'Electronics', 'Samsung', 'active', 'yes'
        ];
        fputcsv($output, $sample_data);
        
    } else {
        $columns = [
            'name', 'price', 'discount_price', 'cost_price', 'description', 
            'short_description', 'stock', 'sku', 'upc', 'ean', 'brand', 
            'model', 'weight', 'dimensions', 'category', 'status', 'featured',
            'is_virtual', 'downloadable', 'taxable', 'meta_title', 
            'meta_description', 'meta_keywords', 'force_update'
        ];
        
        // Write headers
        fputcsv($output, $columns);
        
        // Write sample data
        $sample_data = [
            'Sample Product 1', '25000', '20000', '15000', 'This is a sample product description',
            'Short desc', '100', 'SKU-001', '123456789', '987654321', 'Sample Brand',
            'Model X', '1.5', '10x5x2', 'Electronics', 'active', 'yes',
            'no', 'no', 'yes', 'Sample Product Meta Title',
            'Meta description here', 'sample, product, test', 'yes'
        ];
        fputcsv($output, $sample_data);
    }
    
    fclose($output);
    exit;
}

// Handle POST for product add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_products'])) {
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

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === 0) {
                        $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                        $filename = $product_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;

                        if (move_uploaded_file($tmp_name, $dest)) {
                            // Create thumbnail
                            copy($dest, $upload_dir . 'thumbs/' . $filename);
                            
                            $is_main = ($key === 0) ? 1 : 0;
                            
                            $imgStmt = $pdo->prepare("
                                INSERT INTO product_images (product_id, filename, is_main, display_order) 
                                VALUES (?, ?, ?, ?)
                            ");
                            
                            $imgStmt->execute([$product_id, $filename, $is_main, $uploaded_count + 1]);
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

// Get last import date
$last_import = 'Never';
try {
    $import_log_file = '../uploads/imports/last_import.txt';
    if (file_exists($import_log_file)) {
        $last_import = date('d/m/Y H:i', filemtime($import_log_file));
    }
} catch (Exception $e) {
    $last_import = 'Never';
}

require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header with Import Button -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-box"></i> Manage Products
            </h1>
            <p style="color: var(--admin-gray);">Add, edit, import, and manage your product catalog</p>
        </div>
<!-- Page Header with Import Button -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
            <i class="fas fa-box"></i> Manage Products
        </h1>
        <p style="color: var(--admin-gray);">Add, edit, import, and manage your product catalog</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <a href="import_products.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-file-import"></i> Import Products CSV
        </a>
        <a href="?action=add" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-plus"></i> Add New Product
        </a>
        
    <a href="import_product_images.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-images"></i> Product Images
    </a>
<
    </div>
</div>
    </div>

    <!-- Import Messages -->
    <?php if (!empty($import_success)): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($import_success) ?>
            <?php if (!empty($import_log)): ?>
                <details style="margin-top: 0.5rem;">
                    <summary>View Import Log</summary>
                    <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                        <?php foreach ($import_log as $log): ?>
                            <li><?= htmlspecialchars($log) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($import_errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i> Import Errors:
            <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                <?php foreach ($import_errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= htmlspecialchars($last_import) ?></div>
                    <div class="stat-label">Last Import</div>
                </div>
                <div class="stat-icon info">
                    <i class="fas fa-calendar-alt"></i>
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

                    <div class="form-check">
                        <input type="checkbox" name="featured" value="1" id="featuredCheck" 
                               <?= ($edit_product['featured'] ?? 0) ? 'checked' : '' ?>>
                        <label for="featuredCheck">Featured Product</label>
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
                                   class="form-control">
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
                            <div class="image-gallery">
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
                                                 alt="" class="product-image">
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
                                                  background: <?= $p['stock'] > 10 ? '#10b981' : ($p['stock'] > 0 ? '#f59e0b' : '#ef4444') ?>20;
                                                  color: <?= $p['stock'] > 10 ? '#10b981' : ($p['stock'] > 0 ? '#f59e0b' : '#ef4444') ?>;">
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
                    <div class="pagination">
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

<!-- Import Modal -->
<div id="importModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; padding: 2rem; position: relative;">
        <button onclick="closeImportModal()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--admin-gray);">&times;</button>
        
        <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
            <i class="fas fa-file-import"></i> Import Products
        </h2>
        
        <!-- Template Download -->
        <div style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--admin-dark);">Download Templates</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="?download_template=basic" class="btn btn-secondary" style="flex: 1; text-align: center;">
                    <i class="fas fa-download"></i> Basic Template
                </a>
                <a href="?download_template=full" class="btn btn-secondary" style="flex: 1; text-align: center;">
                    <i class="fas fa-download"></i> Full Template
                </a>
            </div>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--admin-gray);">
                Download a template, fill in your product data, and upload it here.
            </p>
        </div>
        
        <!-- Import Form -->
        <form method="post" enctype="multipart/form-data" id="importForm">
            <div style="border: 2px dashed var(--admin-border); border-radius: 8px; padding: 2rem; text-align: center; margin-bottom: 1.5rem;" 
                 ondragover="event.preventDefault()" 
                 ondrop="handleDrop(event)">
                
                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--admin-primary); margin-bottom: 1rem;"></i>
                
                <div style="margin-bottom: 1rem;">
                    <label for="import_file" style="background: var(--admin-primary); color: white; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; display: inline-block;">
                        <i class="fas fa-folder-open"></i> Choose File
                    </label>
                    <input type="file" name="import_file" id="import_file" accept=".csv,.txt,.xls,.xlsx" style="display: none;" onchange="updateFileName(this)">
                </div>
                
                <p style="color: var(--admin-gray);" id="file_name_display">No file selected</p>
                <p style="font-size: 0.875rem; color: var(--admin-gray); margin-top: 0.5rem;">
                    Supported formats: CSV, Excel (.xls, .xlsx)<br>
                    Max file size: 10MB
                </p>
            </div>
            
            <!-- Import Options -->
            <div style="margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; margin-bottom: 0.5rem;">Import Options</h3>
                
                <label style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <input type="checkbox" name="update_existing" value="1">
                    <span>Update existing products (based on SKU or name)</span>
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="create_categories" value="1" checked>
                    <span>Auto-create missing categories</span>
                </label>
            </div>
            
            <!-- Instructions -->
            <details style="margin-bottom: 1.5rem; border: 1px solid var(--admin-border); border-radius: 8px; padding: 0.5rem;">
                <summary style="cursor: pointer; color: var(--admin-dark); font-weight: 600;">Import Instructions</summary>
                <div style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <ul style="list-style: disc; padding-left: 1.5rem; line-height: 1.6;">
                        <li><strong>Required columns:</strong> name, price</li>
                        <li>First row must contain column headers</li>
                        <li>Use the templates as a starting point</li>
                        <li>Set <code>force_update=yes</code> to update existing products</li>
                        <li>Categories will be created automatically if they don't exist</li>
                        <li>For featured products, use "yes" or "no" in the featured column</li>
                        <li>Maximum 1000 products per import (for performance)</li>
                    </ul>
                </div>
            </details>
            
            <!-- Form Actions -->
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeImportModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="import_products" class="btn btn-primary" onclick="return validateImportForm()">
                    <i class="fas fa-upload"></i> Start Import
                </button>
            </div>
        </form>
    </div>
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

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0;
}

.form-check input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
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

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
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

// Import Modal Functions
function openImportModal() {
    document.getElementById('importModal').style.display = 'flex';
}

function closeImportModal() {
    document.getElementById('importModal').style.display = 'none';
}

function updateFileName(input) {
    const display = document.getElementById('file_name_display');
    if (input.files.length > 0) {
        display.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(2) + ' KB)';
    } else {
        display.textContent = 'No file selected';
    }
}

function handleDrop(event) {
    event.preventDefault();
    const file = event.dataTransfer.files[0];
    const input = document.getElementById('import_file');
    input.files = event.dataTransfer.files;
    updateFileName(input);
}

function validateImportForm() {
    const fileInput = document.getElementById('import_file');
    if (fileInput.files.length === 0) {
        alert('Please select a file to import');
        return false;
    }
    
    const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
    if (fileSize > 10) {
        alert('File size exceeds 10MB limit');
        return false;
    }
    
    return confirm('Start importing products? This may take a few moments for large files.');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('importModal');
    if (event.target === modal) {
        closeImportModal();
    }
}
</script>

