<?php
// pages/order-detail.php - Customer Order Details

$page_title = "Order Details";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Must be logged in
require_login();

$order_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "pages/orders.php");
    exit;
}

try {
    // Fetch order details with user and shipping info
    $stmt = $pdo->prepare("
        SELECT o.*, 
               u.full_name, u.email, u.phone,
               a.address_line1, a.address_line2, a.city, a.state, a.country, a.postal_code,
               p.name as payment_method_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses a ON o.shipping_address_id = a.id
        LEFT JOIN payment_methods p ON o.payment_method_id = p.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: " . BASE_URL . "pages/orders.php?error=Order not found");
        exit;
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, 
               p.name, p.sku, p.image, p.slug,
               pi.price as current_price,
               pi.discount_price
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_inventory pi ON p.id = pi.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch tracking history
    $stmt = $pdo->prepare("
        SELECT * FROM order_tracking 
        WHERE order_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$order_id]);
    $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate order totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += ($item['price'] * $item['quantity']);
    }
    
} catch (Exception $e) {
    $error = "Could not load order details. Please try again later.";
}
?>

<style>
.order-detail-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
    min-height: 70vh;
}

.page-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.back-link {
    color: var(--admin-primary);
    text-decoration: none;
    font-size: 1rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.back-link:hover {
    color: var(--admin-primary-dark);
    text-decoration: underline;
}

/* Order Status Banner */
.order-status-banner {
    background: linear-gradient(90deg, #f0f9ff, #e0f2fe);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #bae6fd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--admin-primary);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
}

.status-details h3 {
    color: var(--admin-dark);
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
}

.status-details p {
    color: var(--admin-gray);
    margin: 0;
}

.status-badges {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.875rem;
}

.order-status-badge {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
}

.payment-status-badge {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

/* Order Info Cards */
.order-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 1.5rem;
    border: 1px solid var(--admin-border);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--admin-border);
}

.card-header i {
    color: var(--admin-primary);
    font-size: 1.25rem;
}

.card-header h3 {
    margin: 0;
    color: var(--admin-dark);
    font-size: 1.1rem;
}

.info-grid {
    display: grid;
    gap: 0.75rem;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.info-label {
    color: var(--admin-gray);
    font-size: 0.875rem;
    font-weight: 500;
}

.info-value {
    color: var(--admin-dark);
    font-weight: 500;
    text-align: right;
    max-width: 60%;
}

.info-value strong {
    color: var(--admin-primary);
}

.address-lines {
    color: var(--admin-dark);
    line-height: 1.5;
}

.address-lines p {
    margin: 0.25rem 0;
}

/* Order Items */
.order-items-card {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--admin-border);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.items-table th {
    background: var(--admin-light);
    color: var(--admin-dark);
    text-align: left;
    padding: 1rem;
    font-weight: 600;
    border-bottom: 2px solid var(--admin-border);
}

.items-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
    vertical-align: top;
}

.item-product {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 0.5rem;
    overflow: hidden;
    background: var(--admin-light);
    flex-shrink: 0;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info h4 {
    margin: 0 0 0.5rem 0;
    color: var(--admin-dark);
}

.product-info p {
    margin: 0.25rem 0;
    color: var(--admin-gray);
    font-size: 0.875rem;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

/* Order Summary */
.order-summary-card {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--admin-border);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.summary-row:last-child {
    border-bottom: none;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-primary);
    padding-top: 1rem;
    margin-top: 0.5rem;
}

.summary-label {
    color: var(--admin-gray);
}

.summary-value {
    color: var(--admin-dark);
    font-weight: 500;
}

/* Tracking Timeline */
.tracking-timeline-card {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid var(--admin-border);
}

.timeline {
    position: relative;
    padding-left: 2rem;
    margin-top: 1rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    padding: 1rem 0;
    padding-left: 2rem;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -12px;
    top: 20px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--admin-primary);
    border: 3px solid white;
    box-shadow: 0 0 0 3px #e5e7eb;
}

.timeline-item.completed::before {
    background: #10b981;
}

.timeline-item.current::before {
    background: var(--admin-primary);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
    100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
}

.timeline-content {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid var(--admin-primary);
}

.timeline-item.completed .timeline-content {
    border-left-color: #10b981;
}

.timeline-item.current .timeline-content {
    border-left-color: var(--admin-primary);
}

.timeline-status {
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
}

.timeline-time {
    color: var(--admin-gray);
    font-size: 0.875rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.primary-btn {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
}

.primary-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.secondary-btn {
    background: var(--admin-light);
    color: var(--admin-dark);
    border: 1px solid var(--admin-border);
}

.secondary-btn:hover {
    background: var(--admin-border);
    transform: translateY(-2px);
}

.success-btn {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.success-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.warning-btn {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.warning-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.danger-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    cursor: pointer;
}

.danger-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--admin-gray);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .order-detail-container {
        padding: 1rem;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
    
    .order-status-banner {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .items-table {
        display: block;
        overflow-x: auto;
    }
    
    .item-product {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .product-image {
        width: 80px;
        height: 80px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .order-info-grid {
        grid-template-columns: 1fr;
    }
    
    .info-card {
        padding: 1rem;
    }
    
    .status-badges {
        flex-direction: column;
        width: 100%;
    }
    
    .status-badge {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="order-detail-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-file-invoice"></i>
                Order #<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?>
            </h1>
            <a href="<?= BASE_URL ?>pages/orders.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-alert" style="
            background: linear-gradient(90deg, #fee2e2, #fef2f2);
            border-left: 4px solid var(--admin-danger);
            color: #991b1b;
            padding: 1.2rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        ">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Order Status Banner -->
    <div class="order-status-banner">
        <div class="status-info">
            <div class="status-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="status-details">
                <h3>Order Status</h3>
                <p>Last updated: <?= date('F j, Y h:i A', strtotime($order['updated_at'] ?? $order['created_at'])) ?></p>
            </div>
        </div>
        <div class="status-badges">
            <span class="status-badge order-status-badge">
                <i class="fas fa-circle"></i>
                <?= ucfirst(str_replace('_', ' ', $order['status'] ?? $order['order_status'])) ?>
            </span>
            <span class="status-badge payment-status-badge">
                <i class="fas fa-credit-card"></i>
                <?= ucfirst($order['payment_status'] ?? 'pending') ?>
            </span>
        </div>
    </div>

    <!-- Order Information Grid -->
    <div class="order-info-grid">
        <!-- Customer Information -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-user"></i>
                <h3>Customer Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Customer Name</span>
                    <span class="info-value"><?= htmlspecialchars($order['full_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address</span>
                    <span class="info-value"><?= htmlspecialchars($order['email']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value"><?= htmlspecialchars($order['phone']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer ID</span>
                    <span class="info-value">#<?= $order['user_id'] ?></span>
                </div>
            </div>
        </div>

        <!-- Shipping Information -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-truck"></i>
                <h3>Shipping Information</h3>
            </div>
            <div class="info-grid">
                <?php if (!empty($order['address_line1'])): ?>
                    <div class="address-lines">
                        <p><strong><?= htmlspecialchars($order['full_name']) ?></strong></p>
                        <p><?= htmlspecialchars($order['address_line1']) ?></p>
                        <?php if (!empty($order['address_line2'])): ?>
                            <p><?= htmlspecialchars($order['address_line2']) ?></p>
                        <?php endif; ?>
                        <p>
                            <?= htmlspecialchars($order['city']) ?>, 
                            <?= htmlspecialchars($order['state']) ?> 
                            <?= htmlspecialchars($order['postal_code']) ?>
                        </p>
                        <p><?= htmlspecialchars($order['country']) ?></p>
                    </div>
                <?php else: ?>
                    <p style="color: var(--admin-gray); font-style: italic;">No shipping address provided</p>
                <?php endif; ?>
                <?php if (!empty($order['shipping_method'])): ?>
                    <div class="info-row">
                        <span class="info-label">Shipping Method</span>
                        <span class="info-value"><?= htmlspecialchars($order['shipping_method']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['tracking_number'])): ?>
                    <div class="info-row">
                        <span class="info-label">Tracking Number</span>
                        <span class="info-value">
                            <strong><?= htmlspecialchars($order['tracking_number']) ?></strong>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Information -->
        <div class="info-card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i>
                <h3>Order Information</h3>
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?= date('F j, Y h:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Number</span>
                    <span class="info-value">#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value"><?= htmlspecialchars($order['payment_method_name'] ?? $order['payment_method'] ?? 'Not specified') ?></span>
                </div>
                <?php if (!empty($order['payment_reference'])): ?>
                    <div class="info-row">
                        <span class="info-label">Payment Reference</span>
                        <span class="info-value"><?= htmlspecialchars($order['payment_reference']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($order['payment_date'])): ?>
                    <div class="info-row">
                        <span class="info-label">Payment Date</span>
                        <span class="info-value"><?= date('F j, Y', strtotime($order['payment_date'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="order-items-card">
        <div class="card-header">
            <i class="fas fa-boxes"></i>
            <h3>Order Items (<?= count($items) ?>)</h3>
        </div>
        
        <?php if (!empty($items)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Price</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $item_total = $item['price'] * $item['quantity'];
                            $product_url = BASE_URL . 'pages/product.php?slug=' . urlencode($item['slug'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <div class="item-product">
                                    <div class="product-image">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?= BASE_URL . 'uploads/products/' . htmlspecialchars($item['image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                                 onerror="this.src='<?= BASE_URL ?>assets/images/placeholder.png'">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f3f4f6; color: #9ca3af;">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h4>
                                            <a href="<?= $product_url ?>" style="color: var(--admin-primary); text-decoration: none;">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        </h4>
                                        <?php if (!empty($item['sku'])): ?>
                                            <p>SKU: <?= htmlspecialchars($item['sku']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['variant'])): ?>
                                            <p>Variant: <?= htmlspecialchars($item['variant']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right">₦<?= number_format($item['price'], 2) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-right">₦<?= number_format($item_total, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No items found in this order</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Summary & Timeline Side by Side -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Order Summary -->
        <div class="order-summary-card">
            <div class="card-header">
                <i class="fas fa-receipt"></i>
                <h3>Order Summary</h3>
            </div>
            <div class="summary-row">
                <span class="summary-label">Subtotal</span>
                <span class="summary-value">₦<?= number_format($order['subtotal'] ?? $subtotal, 2) ?></span>
            </div>
            <?php if (!empty($order['shipping_fee']) && $order['shipping_fee'] > 0): ?>
                <div class="summary-row">
                    <span class="summary-label">Shipping Fee</span>
                    <span class="summary-value">₦<?= number_format($order['shipping_fee'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['tax']) && $order['tax'] > 0): ?>
                <div class="summary-row">
                    <span class="summary-label">Tax (<?= $order['tax_rate'] ?? '0' ?>%)</span>
                    <span class="summary-value">₦<?= number_format($order['tax'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['discount']) && $order['discount'] > 0): ?>
                <div class="summary-row" style="color: #10b981;">
                    <span class="summary-label">Discount</span>
                    <span class="summary-value">-₦<?= number_format($order['discount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row">
                <span class="summary-label">Total Amount</span>
                <span class="summary-value" style="color: var(--admin-primary); font-weight: 700;">
                    ₦<?= number_format($order['total_amount'], 2) ?>
                </span>
            </div>
        </div>

        <!-- Tracking Timeline -->
        <div class="tracking-timeline-card">
            <div class="card-header">
                <i class="fas fa-map-signs"></i>
                <h3>Order Journey</h3>
            </div>
            
            <?php if (!empty($tracking_history)): ?>
                <div class="timeline">
                    <?php 
                    $current_found = false;
                    foreach ($tracking_history as $index => $event): 
                        $is_completed = $index > 0;
                        $is_current = !$current_found && ($event['is_current'] ?? ($index === 0));
                        if ($is_current) $current_found = true;
                    ?>
                        <div class="timeline-item <?= $is_current ? 'current' : ($is_completed ? 'completed' : 'pending') ?>">
                            <div class="timeline-content">
                                <div class="timeline-status">
                                    <?= htmlspecialchars($event['status']) ?>
                                </div>
                                <?php if (!empty($event['location'])): ?>
                                    <div style="color: var(--admin-gray); font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <i class="fas fa-map-pin"></i>
                                        <?= htmlspecialchars($event['location']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($event['description'])): ?>
                                    <div style="color: var(--admin-dark); font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($event['description']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="timeline-time">
                                    <i class="far fa-clock"></i>
                                    <?= date('M j, Y h:i A', strtotime($event['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Default Timeline -->
                <div class="timeline">
                    <?php
                    $order_status = $order['status'] ?? $order['order_status'];
                    $stages = [
                        ['status' => 'Order Placed', 'active' => true],
                        ['status' => 'Processing', 'active' => in_array($order_status, ['processing', 'shipped', 'delivered', 'completed'])],
                        ['status' => 'Shipped', 'active' => in_array($order_status, ['shipped', 'delivered', 'completed'])],
                        ['status' => 'Out for Delivery', 'active' => in_array($order_status, ['delivered', 'completed'])],
                        ['status' => 'Delivered', 'active' => in_array($order_status, ['delivered', 'completed'])],
                    ];
                    
                    foreach ($stages as $index => $stage):
                        $is_current = false;
                        $is_completed = $stage['active'];
                        
                        if ($order_status === 'cancelled') {
                            $is_current = false;
                            $is_completed = false;
                        }
                    ?>
                        <div class="timeline-item <?= $is_current ? 'current' : ($is_completed ? 'completed' : 'pending') ?>">
                            <div class="timeline-content">
                                <div class="timeline-status">
                                    <?= $stage['status'] ?>
                                </div>
                                <div class="timeline-time">
                                    <?php 
                                    if ($is_completed) {
                                        echo 'Completed';
                                    } elseif ($is_current) {
                                        echo 'In progress';
                                    } else {
                                        echo 'Pending';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($order['tracking_number'])): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--admin-border);">
                    <a href="<?= BASE_URL ?>pages/track-order.php?tracking=<?= urlencode($order['tracking_number']) ?>&order_id=<?= $order['id'] ?>" 
                       class="action-btn secondary-btn" style="display: inline-flex; width: auto;">
                        <i class="fas fa-map-marked-alt"></i>
                        View Detailed Tracking
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="<?= BASE_URL ?>pages/invoice.php?id=<?= $order['id'] ?>" target="_blank" class="action-btn primary-btn">
            <i class="fas fa-file-invoice"></i>
            Download Invoice
        </a>
        
        <?php if (!empty($order['tracking_number'])): ?>
            <a href="<?= BASE_URL ?>pages/track-order.php?tracking=<?= urlencode($order['tracking_number']) ?>&order_id=<?= $order['id'] ?>" class="action-btn secondary-btn">
                <i class="fas fa-shipping-fast"></i>
                Track Order
            </a>
        <?php endif; ?>
        
        <?php if (in_array($order['status'] ?? $order['order_status'], ['pending', 'processing'])): ?>
            <button onclick="if(confirm('Are you sure you want to cancel this order?')) window.location='<?= BASE_URL ?>pages/cancel-order.php?id=<?= $order['id'] ?>'" 
                    class="action-btn danger-btn">
                <i class="fas fa-times-circle"></i>
                Cancel Order
            </button>
        <?php elseif (in_array($order['status'] ?? $order['order_status'], ['delivered', 'completed'])): ?>
            <a href="<?= BASE_URL ?>pages/write-review.php?order_id=<?= $order['id'] ?>" class="action-btn warning-btn">
                <i class="fas fa-star"></i>
                Write Review
            </a>
        <?php endif; ?>
        
        <a href="<?= BASE_URL ?>pages/contact.php?subject=Order%20Inquiry%20-%20<?= urlencode($order['order_number'] ?? $order['id']) ?>&order_id=<?= $order['id'] ?>" 
           class="action-btn secondary-btn">
            <i class="fas fa-headset"></i>
            Contact Support
        </a>
        
        <?php if (!empty($order['payment_status']) && $order['payment_status'] === 'pending' && $order['status'] !== 'cancelled'): ?>
            <a href="<?= BASE_URL ?>pages/checkout.php?order_id=<?= $order['id'] ?>" class="action-btn success-btn">
                <i class="fas fa-credit-card"></i>
                Complete Payment
            </a>
        <?php endif; ?>
    </div>

    <!-- Customer Notes -->
    <?php if (!empty($order['customer_notes'])): ?>
        <div class="info-card" style="margin-top: 2rem;">
            <div class="card-header">
                <i class="fas fa-sticky-note"></i>
                <h3>Your Notes</h3>
            </div>
            <div style="padding: 1rem 0;">
                <p style="color: var(--admin-dark); line-height: 1.6; font-style: italic;">
                    "<?= htmlspecialchars($order['customer_notes']) ?>"
                </p>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to timeline items
    const timelineItems = document.querySelectorAll('.timeline-item.current');
    timelineItems.forEach(item => {
        setInterval(() => {
            item.style.transform = 'translateX(5px)';
            setTimeout(() => {
                item.style.transform = 'translateX(0)';
            }, 500);
        }, 2000);
    });
    
    // Auto-refresh page every 60 seconds for active orders
    const orderStatus = '<?= $order['status'] ?? $order['order_status'] ?>';
    const activeStatuses = ['pending', 'processing', 'shipped'];
    const deliveredStatuses = ['delivered', 'completed', 'cancelled'];
    
    if (activeStatuses.includes(orderStatus) && !deliveredStatuses.includes(orderStatus)) {
        setTimeout(() => {
            window.location.reload();
        }, 60000); // Refresh every 60 seconds
        
        // Show refresh notification
        const refreshNotice = document.createElement('div');
        refreshNotice.innerHTML = `
            <div style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--admin-primary);
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 0.5rem;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
                z-index: 1000;
                font-size: 0.875rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            ">
                <i class="fas fa-sync-alt fa-spin"></i>
                Auto-refreshing in <span id="countdown">60</span>s
            </div>
        `;
        document.body.appendChild(refreshNotice);
        
        let countdown = 60;
        const countdownInterval = setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            if (countdown <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }
    
    // Share order functionality
    const shareBtn = document.createElement('button');
    shareBtn.className = 'action-btn secondary-btn';
    shareBtn.innerHTML = '<i class="fas fa-share-alt"></i> Share Order';
    shareBtn.onclick = function() {
        const orderNumber = '<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?>';
        const shareText = `Check out my order #${orderNumber} on <?= htmlspecialchars($site_name) ?>\n\nView details: ${window.location.href}`;
        
        if (navigator.share) {
            navigator.share({
                title: 'My Order #' + orderNumber,
                text: shareText,
                url: window.location.href
            });
        } else {
            navigator.clipboard.writeText(shareText).then(() => {
                alert('Order link copied to clipboard!');
            });
        }
    };
    
    document.querySelector('.action-buttons').appendChild(shareBtn);
    
    // Print order details
    const printBtn = document.createElement('button');
    printBtn.className = 'action-btn secondary-btn';
    printBtn.innerHTML = '<i class="fas fa-print"></i> Print Order';
    printBtn.onclick = function() {
        window.print();
    };
    document.querySelector('.action-buttons').appendChild(printBtn);
});

// Add CSS for print
const style = document.createElement('style');
style.textContent = `
    @media print {
        .order-detail-container {
            padding: 0;
            max-width: 100%;
        }
        
        .action-buttons,
        .back-link,
        button,
        a[href]:not(.print-only) {
            display: none !important;
        }
        
        .info-card,
        .order-items-card,
        .order-summary-card,
        .tracking-timeline-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
            break-inside: avoid;
        }
        
        .page-title {
            font-size: 24px !important;
            margin-bottom: 20px !important;
        }
        
        .order-status-banner {
            background: #f8f9fa !important;
            border: 1px solid #ddd !important;
        }
        
        body {
            font-size: 12px !important;
            line-height: 1.4 !important;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php require_once '../includes/footer.php'; ?>