<?php
// actions/remove_cart.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

session_start();

$cart_id = intval($_POST['cart_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

if ($cart_id <= 0) {
    header("Location: " . BASE_URL . "pages/cart.php");
    exit;
}

try {
    // Verify ownership
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE id = ? AND user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM carts WHERE id = ? AND session_id = ?");
        $stmt->execute([$cart_id, $session_id]);
    }
    
    if ($stmt->fetch()) {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE carts SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$cart_id]);
    }
    
} catch (Exception $e) {
    error_log("Remove cart error: " . $e->getMessage());
}

header("Location: " . BASE_URL . "pages/cart.php");
exit;
?>