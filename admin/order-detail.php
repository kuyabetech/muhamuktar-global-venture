<?php
// admin/order-detail.php?id=123

$page_title = "Order Details";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only + valid order ID
require_admin();

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header("Location: " . BASE_URL . "admin/orders.php");
    exit;
}

// Fetch order - using the correct table structure
$stmt = $pdo->prepare("
    SELECT o.* 
    FROM orders o
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: " . BASE_URL . "admin/orders.php");
    exit;
}

// Fetch order items
$itemStmt = $pdo->prepare("
    SELECT oi.*, p.name, p.slug, pi.filename as image, p.price as current_price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
    WHERE oi.order_id = ?
");
$itemStmt->execute([$order_id]);
$items = $itemStmt->fetchAll();

// Handle status update or note
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'] ?? $order['status'];
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($new_status !== $order['status']) {
        // Update both status fields for consistency
        $pdo->prepare("UPDATE orders SET status = ?, order_status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$new_status, $new_status, $order_id]);
    }

    if ($tracking_number || $admin_note) {
        if ($tracking_number && $tracking_number !== $order['tracking_number']) {
            $pdo->prepare("UPDATE orders SET tracking_number = ? WHERE id = ?")
                ->execute([$tracking_number, $order_id]);
        }
        
        if ($admin_note) {
            // Append to admin notes if column exists, otherwise use order_notes
            $pdo->prepare("UPDATE orders SET order_notes = CONCAT(COALESCE(order_notes,''), '\nAdmin Note [', NOW(), ']: ', ?) WHERE id = ?")
                ->execute([$admin_note, $order_id]);
        }
    }

    $message = "Order updated successfully.";
    // Refresh order data
    header("Location: ?id=$order_id&msg=" . urlencode($message));
    exit;
}

// Handle order cancellation
if (isset($_GET['cancel'])) {
    $pdo->prepare("UPDATE orders SET status = 'cancelled', order_status = 'cancelled', updated_at = NOW() WHERE id = ?")
        ->execute([$order_id]);
    header("Location: ?id=$order_id&msg=" . urlencode("Order cancelled successfully."));
    exit;
}

// Determine which status field to use (prefer 'status' field)
$current_status = !empty($order['status']) ? $order['status'] : ($order['order_status'] ?? 'pending');
$payment_status = $order['payment_status'] ?? 'pending';

// Status color mapping
$status_color = match($current_status) {
    'pending'    => '#f59e0b',
    'paid'       => '#10b981',
    'processing' => '#3b82f6',
    'shipped'    => '#8b5cf6',
    'delivered'  => '#047857',
    'completed'  => '#047857',
    'cancelled'  => '#ef4444',
    default      => '#6b7280'
};

// Payment status color
$payment_color = match($payment_status) {
    'pending'    => '#f59e0b',
    'paid'       => '#10b981',
    'failed'     => '#ef4444',
    'refunded'   => '#8b5cf6',
    default      => '#6b7280'
};

// Helper function for safe output
function safe_html($value) {
    return $value !== null ? htmlspecialchars($value) : '';
}

require_once 'header.php';
?>

<style>
/* Order Detail Custom Styles */
.order-header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.order-header-left h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 0.5rem;
}

.order-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--admin-gray);
    font-size: 0.9rem;
}

.order-status-badges {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.order-status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.order-header-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.order-total {
    text-align: right;
}

.order-amount {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--admin-primary);
    line-height: 1;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    background: var(--admin-primary);
    color: white;
    text-decoration: none;
    border-radius: 0.5rem;
    font-weight: 500;
    transition: var(--transition);
}

.back-btn:hover {
    background: var(--admin-primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

/* Order Layout */
.order-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .order-layout {
        grid-template-columns: 1fr;
    }
}

/* Order Cards */
.order-card {
    background: white;
    border-radius: 0.75rem;
    box-shadow: var(--admin-shadow);
    overflow: hidden;
    transition: var(--transition);
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow-lg);
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--admin-border);
    background: linear-gradient(90deg, var(--admin-light), white);
}

.card-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--admin-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-header-icon {
    width: 40px;
    height: 40px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.1rem;
}

.card-body {
    padding: 1.5rem;
}

/* Customer Info Grid */
.customer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .customer-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

.info-section h3 {
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--admin-gray);
    margin-bottom: 1rem;
    font-weight: 600;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.info-icon {
    color: var(--admin-primary);
    font-size: 1.1rem;
    margin-top: 0.2rem;
    min-width: 20px;
}

.info-content {
    flex: 1;
}

.info-label {
    font-weight: 500;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
}

.info-value {
    color: var(--admin-gray);
    font-size: 0.9rem;
    line-height: 1.5;
}

/* Tracking Info */
.tracking-info {
    background: linear-gradient(90deg, #f0f9ff, #e0f2fe);
    border: 1px solid #bae6fd;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1.5rem;
}

.tracking-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #0369a1;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.tracking-number {
    font-family: 'Courier New', monospace;
    background: white;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    border: 1px solid #7dd3fc;
    color: #0c4a6e;
    font-weight: 600;
}

/* Order Notes */
.order-notes-box {
    background: linear-gradient(90deg, #fffbeb, #fef3c7);
    border: 1px solid #fcd34d;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1.5rem;
}

.notes-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #92400e;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.notes-content {
    color: #78350f;
    line-height: 1.6;
    font-size: 0.9rem;
}

/* Order Items Table */
.items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.items-total {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-primary);
}

.items-table-container {
    overflow-x: auto;
    margin-bottom: 1.5rem;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.items-table thead {
    background: var(--admin-light);
}

.items-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--admin-dark);
    border-bottom: 2px solid var(--admin-border);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.items-table th.text-right {
    text-align: right;
}

.items-table th.text-center {
    text-align: center;
}

.items-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
    vertical-align: top;
}

.items-table tr:last-child td {
    border-bottom: none;
}

.items-table tr:hover {
    background: var(--admin-light);
}

/* Product Cell */
.product-cell {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.product-image {
    width: 60px;
    height: 60px;
    border-radius: 0.5rem;
    object-fit: cover;
    border: 1px solid var(--admin-border);
}

.product-image-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 0.5rem;
    background: var(--admin-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--admin-gray);
    border: 1px solid var(--admin-border);
}

.product-info {
    flex: 1;
}

.product-name {
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
    display: block;
    text-decoration: none;
    transition: var(--transition);
}

.product-name:hover {
    color: var(--admin-primary);
}

.product-variant {
    font-size: 0.75rem;
    color: var(--admin-gray);
    background: var(--admin-light);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
}

/* Quantity Badge */
.quantity-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: var(--admin-primary);
    color: white;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Price Cells */
.price-cell {
    font-weight: 600;
    color: var(--admin-dark);
}

.subtotal-cell {
    font-weight: 700;
    color: var(--admin-primary);
}

/* Order Summary */
.order-summary {
    background: var(--admin-light);
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-top: 2rem;
    border: 1px solid var(--admin-border);
}

.summary-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--admin-border);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    color: var(--admin-gray);
    font-size: 0.9rem;
}

.summary-total {
    display: flex;
    justify-content: space-between;
    padding: 1rem 0;
    margin-top: 1rem;
    border-top: 2px solid var(--admin-border);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-primary);
}

/* Order Actions Sidebar */
.order-sidebar {
    position: sticky;
    top: calc(var(--header-height) + 2rem);
}

.status-display {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.current-status {
    padding: 0.75rem 1.25rem;
    background: var(--status-color);
    color: white;
    border-radius: 0.5rem;
    font-weight: 700;
    font-size: 0.9rem;
    min-width: 100px;
    text-align: center;
}

.status-arrow {
    color: var(--admin-gray);
    font-weight: 500;
}

.status-select {
    flex: 1;
    min-width: 200px;
    padding: 0.75rem;
    border: 1px solid var(--admin-border);
    border-radius: 0.5rem;
    background: white;
    color: var(--admin-dark);
    font-weight: 500;
    transition: var(--transition);
}

.status-select:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Form Elements */
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--admin-dark);
    font-size: 0.9rem;
}

.form-label-help {
    font-weight: 400;
    color: var(--admin-gray);
    font-size: 0.8rem;
    margin-left: 0.5rem;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--admin-border);
    border-radius: 0.5rem;
    background: white;
    color: var(--admin-dark);
    transition: var(--transition);
    font-family: inherit;
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-textarea {
    min-height: 100px;
    resize: vertical;
}

.update-btn {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.update-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow-lg);
}

/* Payment Info */
.payment-info {
    background: var(--admin-light);
    border-radius: 0.5rem;
    padding: 1rem;
    margin-top: 1.5rem;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    font-size: 0.85rem;
}

.payment-label {
    color: var(--admin-gray);
}

.payment-value {
    font-weight: 500;
    color: var(--admin-dark);
}

/* Danger Zone */
.danger-zone {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid #fee2e2;
}

.danger-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--admin-danger);
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.danger-description {
    color: var(--admin-gray);
    font-size: 0.85rem;
    margin-bottom: 1rem;
    line-height: 1.5;
}

.cancel-btn {
    width: 100%;
    padding: 0.875rem;
    background: var(--admin-danger);
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.cancel-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}

/* Order Metadata */
.order-meta-info {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--admin-border);
}

.meta-grid {
    display: grid;
    gap: 0.5rem;
}

.meta-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
}

.meta-label {
    color: var(--admin-gray);
}

.meta-value {
    font-weight: 500;
    color: var(--admin-dark);
}

/* No Items Message */
.no-items {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--admin-gray);
}

.no-items-icon {
    font-size: 3rem;
    color: var(--admin-border);
    margin-bottom: 1rem;
}

/* Success Message */
.success-message {
    background: linear-gradient(90deg, #d1fae5, #ecfdf5);
    border-left: 4px solid var(--admin-secondary);
    color: #065f46;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
</style>

<script>
// Set CSS variable for status color
document.documentElement.style.setProperty('--status-color', '<?= $status_color ?>');
</script>

<main class="admin-main">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <a href="<?= BASE_URL ?>admin/orders.php">Orders</a>
        <i class="fas fa-chevron-right"></i>
        <span>Order #<?= safe_html($order['order_number'] ?? $order['id']) ?></span>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?= safe_html($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <!-- Order Header -->
    <div class="order-header-container">
        <div class="order-header-left">
            <h1>Order #<?= safe_html($order['order_number'] ?? $order['id']) ?></h1>
            <div class="order-meta">
                <span><i class="far fa-clock"></i> <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
                <span>•</span>
                <span><?= $order['item_count'] ?? count($items) ?> item(s)</span>
            </div>
            <div class="order-status-badges">
                <span class="order-status-badge" style="background: <?= $status_color ?>">
                    <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                    <?= ucfirst($current_status) ?>
                </span>
                <span class="order-status-badge" style="background: <?= $payment_color ?>">
                    <i class="fas fa-credit-card" style="font-size: 0.7rem;"></i>
                    <?= ucfirst($payment_status) ?>
                </span>
            </div>
        </div>
        <div class="order-header-right">
            <div class="order-total">
                <div class="order-amount">₦<?= number_format($order['total_amount'] ?? 0) ?></div>
                <small style="color: var(--admin-gray); font-size: 0.85rem;">Total Amount</small>
            </div>
            <a href="<?= BASE_URL ?>admin/orders.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
        </div>
    </div>

    <div class="order-layout">
        <!-- Left Column: Order Details & Items -->
        <div>
            <!-- Customer & Shipping Card -->
            <div class="order-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-user card-header-icon" style="background: linear-gradient(135deg, var(--admin-primary-light), var(--admin-primary));"></i>
                        Customer & Shipping Information
                    </h2>
                </div>
                <div class="card-body">
                    <div class="customer-grid">
                        <!-- Customer Info -->
                        <div class="info-section">
                            <h3><i class="fas fa-user-circle"></i> Customer Details</h3>
                            <div class="info-item">
                                <i class="fas fa-user info-icon"></i>
                                <div class="info-content">
                                    <div class="info-label">Name</div>
                                    <div class="info-value"><?= !empty($order['customer_name']) ? safe_html($order['customer_name']) : 'Guest' ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($order['customer_email'])): ?>
                            <div class="info-item">
                                <i class="fas fa-envelope info-icon"></i>
                                <div class="info-content">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?= safe_html($order['customer_email']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($order['customer_phone'])): ?>
                            <div class="info-item">
                                <i class="fas fa-phone info-icon"></i>
                                <div class="info-content">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?= safe_html($order['customer_phone']) ?></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="info-item">
                                <i class="fas fa-phone-slash info-icon"></i>
                                <div class="info-content">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value" style="color: var(--admin-gray); font-style: italic;">Not provided</div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Shipping Address -->
                        <div class="info-section">
                            <h3><i class="fas fa-shipping-fast"></i> Shipping Address</h3>
                            <?php if (!empty($order['shipping_address'])): ?>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt info-icon"></i>
                                    <div class="info-content">
                                        <div class="info-label">Address</div>
                                        <div class="info-value">
                                            <?= safe_html($order['shipping_address']) ?><br>
                                            <?php if (!empty($order['shipping_city']) || !empty($order['shipping_state'])): ?>
                                                <?= !empty($order['shipping_city']) ? safe_html($order['shipping_city']) . ', ' : '' ?>
                                                <?= !empty($order['shipping_state']) ? safe_html($order['shipping_state']) : '' ?>
                                                <?= !empty($order['shipping_postal_code']) ? ' • ' . safe_html($order['shipping_postal_code']) : '' ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt info-icon"></i>
                                    <div class="info-content">
                                        <div class="info-label">Address</div>
                                        <div class="info-value" style="color: var(--admin-gray); font-style: italic;">Not provided</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($order['tracking_number'])): ?>
                        <div class="tracking-info">
                            <div class="tracking-header">
                                <i class="fas fa-shipping-fast"></i>
                                Tracking Information
                            </div>
                            <div class="tracking-number"><?= safe_html($order['tracking_number']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($order['order_notes'])): ?>
                        <div class="order-notes-box">
                            <div class="notes-header">
                                <i class="fas fa-sticky-note"></i>
                                Order Notes
                            </div>
                            <div class="notes-content"><?= nl2br(safe_html($order['order_notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Items Card -->
            <div class="order-card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <div class="items-header">
                        <h2>
                            <i class="fas fa-shopping-bag card-header-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"></i>
                            Order Items
                        </h2>
                        <div class="items-total">₦<?= number_format($order['total_amount'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($items)): ?>
                        <div class="items-table-container">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-right">Unit Price</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                        <?php 
                                            $item_price = $item['price_at_time'] ?? $item['price'] ?? $item['current_price'] ?? 0;
                                            $quantity = $item['quantity'] ?? 1;
                                            $subtotal = $item_price * $quantity;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="product-cell">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="<?= BASE_URL . 'uploads/products/' . safe_html($item['image']) ?>" 
                                                             alt="<?= !empty($item['name']) ? safe_html($item['name']) : 'Product' ?>" 
                                                             class="product-image">
                                                    <?php else: ?>
                                                        <div class="product-image-placeholder">
                                                            <i class="fas fa-image"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="product-info">
                                                        <?php if (!empty($item['slug'])): ?>
                                                            <a href="<?= BASE_URL ?>product/<?= safe_html($item['slug']) ?>" 
                                                               class="product-name" 
                                                               target="_blank">
                                                                <?= !empty($item['name']) ? safe_html($item['name']) : 'Product #' . safe_html($item['product_id'] ?? 'N/A') ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <div class="product-name">
                                                                <?= !empty($item['name']) ? safe_html($item['name']) : 'Product #' . safe_html($item['product_id'] ?? 'N/A') ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['variant'])): ?>
                                                            <span class="product-variant"><?= safe_html($item['variant']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-right price-cell">
                                                ₦<?= number_format($item_price) ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="quantity-badge"><?= $quantity ?></span>
                                            </td>
                                            <td class="text-right subtotal-cell">
                                                ₦<?= number_format($subtotal) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Order Summary -->
                        <div class="order-summary">
                            <h3 class="summary-title">Order Summary</h3>
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>₦<?= number_format($order['subtotal'] ?? 0) ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span>₦<?= number_format($order['shipping_fee'] ?? 0) ?></span>
                            </div>
                            <?php if (!empty($order['tax']) && $order['tax'] > 0): ?>
                            <div class="summary-row">
                                <span>Tax:</span>
                                <span>₦<?= number_format($order['tax'] ?? 0) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="summary-total">
                                <span>Total:</span>
                                <span>₦<?= number_format($order['total_amount'] ?? 0) ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-items">
                            <div class="no-items-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No items found in this order</p>
                            <p style="color: var(--admin-gray);">This order appears to be empty</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Order Actions -->
        <div class="order-sidebar">
            <!-- Order Actions Card -->
            <div class="order-card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-edit card-header-icon" style="background: linear-gradient(135deg, #10b981, #34d399);"></i>
                        Order Actions
                    </h2>
                </div>
                <div class="card-body">
                    <form method="post">
                        <!-- Status Update -->
                        <div class="form-group">
                            <div class="status-display">
                                <div class="current-status"><?= ucfirst($current_status) ?></div>
                                <span class="status-arrow">→</span>
                                <select name="status" class="status-select">
                                    <option value="">Update Status</option>
                                    <option value="pending" <?= $current_status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                    <option value="paid" <?= $current_status === 'paid' ? 'selected' : '' ?>>✅ Paid</option>
                                    <option value="processing" <?= $current_status === 'processing' ? 'selected' : '' ?>>🔄 Processing</option>
                                    <option value="shipped" <?= $current_status === 'shipped' ? 'selected' : '' ?>>🚚 Shipped</option>
                                    <option value="delivered" <?= $current_status === 'delivered' ? 'selected' : '' ?>>📦 Delivered</option>
                                    <option value="completed" <?= $current_status === 'completed' ? 'selected' : '' ?>>🎯 Completed</option>
                                    <option value="cancelled" <?= $current_status === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tracking Number -->
                        <div class="form-group">
                            <label class="form-label">
                                Tracking Number
                                <span class="form-label-help">(optional)</span>
                            </label>
                            <input type="text" 
                                   name="tracking_number" 
                                   class="form-input" 
                                   value="<?= !empty($order['tracking_number']) ? safe_html($order['tracking_number']) : '' ?>" 
                                   placeholder="Enter tracking number...">
                        </div>

                        <!-- Admin Note -->
                        <div class="form-group">
                            <label class="form-label">
                                Admin Note
                                <span class="form-label-help">(internal use)</span>
                            </label>
                            <textarea name="admin_note" 
                                      class="form-textarea" 
                                      placeholder="Add internal note or reason for status change..."></textarea>
                        </div>

                        <!-- Update Button -->
                        <button type="submit" class="update-btn">
                            <i class="fas fa-save"></i>
                            Update Order
                        </button>
                    </form>

                    <!-- Payment Information -->
                    <div class="payment-info">
                        <div class="payment-row">
                            <span class="payment-label">Payment Status:</span>
                            <span class="payment-value" style="color: <?= $payment_color ?>; font-weight: 600;">
                                <?= ucfirst($payment_status) ?>
                            </span>
                        </div>
                        <?php if (!empty($order['payment_method'])): ?>
                        <div class="payment-row">
                            <span class="payment-label">Payment Method:</span>
                            <span class="payment-value"><?= safe_html($order['payment_method']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['payment_reference'])): ?>
                        <div class="payment-row">
                            <span class="payment-label">Payment Reference:</span>
                            <span class="payment-value" style="font-family: 'Courier New', monospace;"><?= safe_html($order['payment_reference']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['payment_date'])): ?>
                        <div class="payment-row">
                            <span class="payment-label">Payment Date:</span>
                            <span class="payment-value"><?= date('M j, Y H:i', strtotime($order['payment_date'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Danger Zone -->
                    <div class="danger-zone">
                        <div class="danger-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            Danger Zone
                        </div>
                        <div class="danger-description">
                            Cancelling an order cannot be undone. This action will notify the customer via email.
                        </div>
                        <button onclick="if(confirm('Are you sure you want to cancel this order? This cannot be undone.')) window.location='?id=<?= $order_id ?>&cancel=1'" 
                                class="cancel-btn">
                            <i class="fas fa-times-circle"></i>
                            Cancel Order
                        </button>
                    </div>

                    <!-- Order Metadata -->
                    <div class="order-meta-info">
                        <div class="meta-grid">
                            <div class="meta-row">
                                <span class="meta-label">Order ID:</span>
                                <span class="meta-value">#<?= $order_id ?></span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Order Number:</span>
                                <span class="meta-value"><?= safe_html($order['order_number'] ?? 'N/A') ?></span>
                            </div>
                            <?php if (!empty($order['reference'])): ?>
                            <div class="meta-row">
                                <span class="meta-label">Reference:</span>
                                <span class="meta-value"><?= safe_html($order['reference']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($order['user_id'])): ?>
                            <div class="meta-row">
                                <span class="meta-label">Customer ID:</span>
                                <span class="meta-value">#<?= $order['user_id'] ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="meta-row">
                                <span class="meta-label">Created:</span>
                                <span class="meta-value"><?= date('M j, Y H:i', strtotime($order['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>