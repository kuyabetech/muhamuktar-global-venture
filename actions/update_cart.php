<?php
// actions/update_cart.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header FIRST
header('Content-Type: application/json');

// Turn off display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($cart_id <= 0) {
        throw new Exception('Invalid cart item');
    }
    
    if ($quantity < 1 || $quantity > 99) {
        throw new Exception('Quantity must be between 1-99');
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    // Verify ownership and check stock
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT c.id, p.stock 
            FROM carts c
            JOIN products p ON c.product_id = p.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cart_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.id, p.stock 
            FROM carts c
            JOIN products p ON c.product_id = p.id
            WHERE c.id = ? AND c.session_id = ?
        ");
        $stmt->execute([$cart_id, $session_id]);
    }
    
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('Item not found');
    }
    
    if ($quantity > $item['stock']) {
        throw new Exception("Only {$item['stock']} items available");
    }
    
    // Update quantity
    $stmt = $pdo->prepare("UPDATE carts SET quantity = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$quantity, $cart_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Quantity updated'
    ]);
    
} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>