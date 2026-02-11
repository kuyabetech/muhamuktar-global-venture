<?php
// pages/cancel-order.php - Cancel Order

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in
require_login();

$order_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "pages/orders.php");
    exit;
}

try {
    // Check if order exists and belongs to user
    $stmt = $pdo->prepare("
        SELECT id, status, order_status, payment_status 
        FROM orders 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header("Location: " . BASE_URL . "pages/orders.php?error=Order not found");
        exit;
    }
    
    // Check if order can be cancelled
    $current_status = $order['status'] ?? $order['order_status'];
    $cancellable_statuses = ['pending', 'processing'];
    
    if (!in_array($current_status, $cancellable_statuses)) {
        header("Location: " . BASE_URL . "pages/order-detail.php?id=" . $order_id . "&error=Cannot cancel order in current status");
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled', 
            order_status = 'cancelled',
            updated_at = NOW(),
            cancelled_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    
    // Add tracking event
    $stmt = $pdo->prepare("
        INSERT INTO order_tracking (order_id, status, description, is_current)
        VALUES (?, 'cancelled', 'Order was cancelled by customer.', 1)
    ");
    $stmt->execute([$order_id]);
    
    // Mark previous tracking events as not current
    $stmt = $pdo->prepare("
        UPDATE order_tracking 
        SET is_current = 0 
        WHERE order_id = ? AND id != ?
    ");
    $stmt->execute([$order_id, $pdo->lastInsertId()]);
    
    // Restore product stock if needed
    $stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, oi.variant_id
        FROM order_items oi
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    foreach ($order_items as $item) {
        if (!empty($item['variant_id'])) {
            // Restore variant stock
            $stmt = $pdo->prepare("
                UPDATE product_variants 
                SET stock = stock + ? 
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['variant_id']]);
        } else {
            // Restore product stock
            $stmt = $pdo->prepare("
                UPDATE product_inventory 
                SET stock = stock + ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Send cancellation email
    send_order_cancellation_email($order_id);
    
    // Redirect with success message
    header("Location: " . BASE_URL . "pages/order-detail.php?id=" . $order_id . "&success=Order cancelled successfully");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: " . BASE_URL . "pages/order-detail.php?id=" . $order_id . "&error=Cancellation failed");
    exit;
}
?>