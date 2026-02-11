<?php
// actions/add_to_cart.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Start session
session_start();

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data (handles both form data and JSON)
if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    // JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 1);
} else {
    // Form data
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
}

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
    // Check product exists and is active
    $stmt = $pdo->prepare("SELECT id, name, stock, price FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found or unavailable');
    }
    
    // Check stock
    if ($product['stock'] < $quantity) {
        throw new Exception("Only {$product['stock']} items available in stock");
    }
    
    // Check if already in cart
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND user_id = ?");
        $stmt->execute([$product_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND session_id = ?");
        $stmt->execute([$product_id, $session_id]);
    }
    
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Begin transaction
    $pdo->beginTransaction();
    
    if ($cart_item) {
        // Update existing item
        $new_qty = $cart_item['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_qty, $cart_item['id']]);
    } else {
        // Insert new item
        $stmt = $pdo->prepare("
            INSERT INTO cart (user_id, session_id, product_id, quantity, price, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $session_id, $product_id, $quantity, $product['price']]);
    }
    
    // Update product stock
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stmt->execute([$quantity, $product_id]);
    
    // Get cart stats
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as items_count, SUM(quantity) as total_quantity FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as items_count, SUM(quantity) as total_quantity FROM cart WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }
    
    $cart_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Added {$quantity} item(s) of '{$product['name']}' to cart",
        'cart' => [
            'items_count' => (int)($cart_stats['items_count'] ?? 0),
            'total_quantity' => (int)($cart_stats['total_quantity'] ?? 0)
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Cart Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}