<?php
// admin/api/sidebar-stats.php

require_once '../../includes/config.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

try {
    // Pending products (adjust condition to match your definition)
    $pending_products = $pdo->query("
        SELECT COUNT(*) 
        FROM products 
        WHERE status IN ('pending', 'draft') 
           OR (stock < 10 AND stock > 0)
    ")->fetchColumn() ?? 0;

    // Pending orders
    $pending_orders = $pdo->query("
        SELECT COUNT(*) 
        FROM orders 
        WHERE status IN ('pending', 'processing')
    ")->fetchColumn() ?? 0;

    echo json_encode([
        'success' => true,
        'pending_products' => (int)$pending_products,
        'pending_orders'   => (int)$pending_orders
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}