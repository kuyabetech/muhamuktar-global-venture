<?php
// actions/clear_cart.php
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
    $user_id = $_SESSION['user_id'] ?? null;
    $session_id = session_id();
    
    if ($user_id) {
        // Soft delete all cart items for logged-in user
        $stmt = $pdo->prepare("
            UPDATE carts 
            SET deleted_at = NOW() 
            WHERE user_id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$user_id]);
    } else {
        // Soft delete all cart items for guest
        $stmt = $pdo->prepare("
            UPDATE carts 
            SET deleted_at = NOW() 
            WHERE session_id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->execute([$session_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart cleared successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Clear cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to clear cart: ' . $e->getMessage()
    ]);
}
?>