<?php
// actions/remove_cart.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header FIRST
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Turn off display errors, log them instead

try {
    // Get cart ID
    $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
    
    if ($cart_id <= 0) {
        throw new Exception('Invalid cart item');
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    // Verify ownership
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE id = ? AND session_id = ?");
        $stmt->execute([$cart_id, $session_id]);
    }
    
    if (!$stmt->fetch()) {
        throw new Exception('Item not found or unauthorized');
    }
    
    // Soft delete
    $stmt = $pdo->prepare("UPDATE carts SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$cart_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Item removed from cart'
    ]);
    
} catch (Exception $e) {
    error_log("Remove cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>