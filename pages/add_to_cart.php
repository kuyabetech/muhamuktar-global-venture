<?php
// actions/add_to_cart.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

// Set defaults if not provided
$product_id = $product_id ?: 0;
$quantity = $quantity ?: 1;

// Basic validation
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($quantity <= 0 || $quantity > 99) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be between 1-99']);
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

try {
    // Check if product exists and is active
    $stmt = $pdo->prepare("SELECT id, name, stock, price FROM products WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found or unavailable');
    }
    
    // Check stock availability
    if ($product['stock'] < 1) {
        throw new Exception('Product is out of stock');
    }
    
    if ($product['stock'] < $quantity) {
        throw new Exception("Only {$product['stock']} items available in stock");
    }
    
    // Check if product is already in cart
    $sql = $user_id 
        ? "SELECT id, quantity FROM carts WHERE product_id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1"
        : "SELECT id, quantity FROM carts WHERE product_id = ? AND session_id = ? AND deleted_at IS NULL LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $params = $user_id ? [$product_id, $user_id] : [$product_id, $session_id];
    $stmt->execute($params);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    if ($cart_item) {
        // Update existing cart item quantity
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        // Check if new total exceeds stock
        if ($new_quantity > $product['stock']) {
            throw new Exception("Cannot add more than {$product['stock']} items total");
        }
        
        $stmt = $pdo->prepare("UPDATE carts SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_quantity, $cart_item['id']]);
        
        $action = 'updated';
    } else {
        // Insert new cart item
        $stmt = $pdo->prepare("
            INSERT INTO carts (user_id, session_id, product_id, quantity, price, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $session_id, $product_id, $quantity, $product['price']]);
        
        $action = 'added';
    }
    
    // Update product stock
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$quantity, $product_id]);
    
    // Get updated cart statistics
    $sql = $user_id 
        ? "SELECT COUNT(DISTINCT product_id) as items_count, SUM(quantity) as total_quantity FROM carts WHERE user_id = ? AND deleted_at IS NULL"
        : "SELECT COUNT(DISTINCT product_id) as items_count, SUM(quantity) as total_quantity FROM carts WHERE session_id = ? AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id ?: $session_id]);
    $cart_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    // Prepare success response
    $response = [
        'success' => true,
        'message' => "{$product['name']} {$action} to cart",
        'cart' => [
            'items_count' => (int)($cart_stats['items_count'] ?? 0),
            'total_quantity' => (int)($cart_stats['total_quantity'] ?? 0)
        ],
        'product' => [
            'id' => $product['id'],
            'name' => $product['name'],
            'remaining_stock' => $product['stock'] - $quantity
        ]
    ];
    
    // If user is logged in, include user data
    if ($user_id) {
        $response['user_id'] = $user_id;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Add to Cart Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}