<?php
// pages/product.php?slug=example-product-slug

$page_title = "Product Detail";

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Debug: Log the incoming request
error_log("Product page accessed: slug=" . ($_GET['slug'] ?? 'empty') . ", id=" . ($_GET['id'] ?? '0'));

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get parameters
$slug = $_GET['slug'] ?? '';
$product_id_from_url = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug output (comment out in production)
echo "<!-- Debug: slug='$slug', id='$product_id_from_url' -->\n";

// If we have neither slug nor valid ID, redirect early
if (empty($slug) && $product_id_from_url <= 0) {
    error_log("Product page: No slug or ID provided, redirecting to products");
    header("Location: " . BASE_URL . "pages/products.php");
    exit;
}

try {
    $product = null;
    
    // 1. First try to get product by slug
    if (!empty($slug)) {
        error_log("Trying to find product by slug: $slug");
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            error_log("Found product by slug: {$product['id']} - {$product['name']}");
        } else {
            error_log("No product found by slug: $slug");
        }
    }
    
    // 2. If no product found by slug, try by ID
    if (!$product && $product_id_from_url > 0) {
        error_log("Trying to find product by ID: $product_id_from_url");
        
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name, p.slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$product_id_from_url]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            error_log("Found product by ID: {$product['id']} - {$product['name']}, slug: {$product['slug']}");
            
            // If we have a slug and came by ID, redirect to slug URL for SEO
            if (!empty($product['slug']) && empty($_GET['slug'])) {
                error_log("Redirecting to canonical slug URL: {$product['slug']}");
                header("Location: " . BASE_URL . "pages/product.php?slug=" . urlencode($product['slug']), true, 301);
                exit;
            }
        } else {
            error_log("No product found by ID: $product_id_from_url");
        }
    }
    
    // 3. Final check - product still not found
    if (!$product) {
        error_log("Product not found. Slug: '$slug', ID: $product_id_from_url");
        
        // Set error message and redirect
        $_SESSION['error_message'] = "Product not found. Please check the product URL.";
        header("Location: " . BASE_URL . "pages/products.php");
        exit;
    }
    
    // Debug: Product found
    echo "<!-- Debug: Product found - ID: {$product['id']}, Name: {$product['name']}, Slug: {$product['slug']} -->\n";
    
    // Update view count
    $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")
        ->execute([$product['id']]);
    
    // Get product images
    $imagesStmt = $pdo->prepare("
        SELECT filename FROM product_images 
        WHERE product_id = ? 
        ORDER BY is_main DESC, display_order ASC
    ");
    $imagesStmt->execute([$product['id']]);
    $product_images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($product_images) . " images for product {$product['id']}");
    
    // Get related products
    $relatedStmt = $pdo->prepare("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.views, p.brand,
               pi.filename AS main_image
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
        WHERE p.category_id = ? 
          AND p.id != ?
          AND p.status = 'active'
        ORDER BY p.views DESC, p.created_at DESC
        LIMIT 6
    ");
    $relatedStmt->execute([$product['category_id'], $product['id']]);
    $related_products = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Price calculations
    $display_price = $product['discount_price'] ?: $product['price'];
    $original_price = $product['discount_price'] ? $product['price'] : null;
    $discount_percent = $original_price
        ? round((($original_price - $display_price) / $original_price) * 100)
        : 0;

} catch (PDOException $e) {
    error_log("Product page PDO error: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error. Please try again later.";
    header("Location: " . BASE_URL . "pages/products.php");
    exit;
} catch (Exception $e) {
    error_log("Product page general error: " . $e->getMessage());
    $_SESSION['error_message'] = "An unexpected error occurred.";
    header("Location: " . BASE_URL . "pages/products.php");
    exit;
}

// Output headers
require_once '../includes/header.php';
?>

