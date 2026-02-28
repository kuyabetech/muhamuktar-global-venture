<?php
// admin/orders.php - Manage All Orders

$page_title = "Manage Orders";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php';

// Admin only
require_admin();

// Handle status update for multiple orders
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['status'] ?? '';
        
        if ($order_id > 0 && in_array($new_status, ['pending','paid','processing','shipped','delivered','completed','cancelled'])) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            $message = "Order status updated successfully.";
        }
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['selected_orders'])) {
        $selected_orders = $_POST['selected_orders'];
        $bulk_action = $_POST['bulk_action'];
        
        if (!empty($selected_orders) && in_array($bulk_action, ['delete', 'pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'])) {
            if ($bulk_action === 'delete') {
                // Delete selected orders
                $placeholders = implode(',', array_fill(0, count($selected_orders), '?'));
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
                $stmt->execute($selected_orders);
                $message = count($selected_orders) . " order(s) deleted successfully.";
            } else {
                // Update status for selected orders
                $placeholders = implode(',', array_fill(0, count($selected_orders), '?'));
                $params = array_merge([$bulk_action], $selected_orders);
                $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($params);
                $message = count($selected_orders) . " order(s) status updated to " . ucfirst($bulk_action) . ".";
            }
        }
    }
}

// Filters
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT 
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.customer_phone,
            o.total_amount,
            o.status,
            o.order_status,
            o.payment_status,
            o.shipping_city,
            o.shipping_state,
            o.tracking_number,
            o.created_at,
            COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE 1=1";

$params = [];
$conditions = [];

if ($status_filter) {
    $conditions[] = "(o.status = ? OR o.order_status = ?)";
    $params[] = $status_filter;
    $params[] = $status_filter;
}

if ($search) {
    $conditions[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($date_from) {
    $conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count
$countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o WHERE 1=1";
if (!empty($conditions)) {
    $countSql .= " AND " . implode(" AND ", $conditions);
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute(array_slice($params, 0, count($params)/2)); // Adjust for duplicate conditions
$total = $countStmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

// Get paginated results
$sql .= " LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status options
$statuses = ['pending','paid','processing','shipped','delivered','completed','cancelled'];
?>

<style>
/* Orders Page Custom Styles */
.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.orders-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.filter-btn, .export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    text-decoration: none;
    border: 1px solid transparent;
}

.filter-btn {
    background: white;
    color: var(--admin-dark);
    border-color: var(--admin-border);
}

.filter-btn:hover {
    background: var(--admin-light);
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

.export-btn {
    background: linear-gradient(135deg, var(--admin-secondary), #34d399);
    color: white;
    border: none;
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
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

/* Filters Panel */
.filters-panel {
    background: white;
    padding: 1.5rem;
    border-radius: 0.75rem;
    box-shadow: var(--admin-shadow);
    margin-bottom: 1.5rem;
    border: 1px solid var(--admin-border);
    display: none;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--admin-dark);
    font-size: 0.9rem;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid var(--admin-border);
    border-radius: 0.5rem;
    background: white;
    color: var(--admin-dark);
    transition: var(--transition);
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.filter-buttons {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.filter-submit {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    flex: 1;
}

.filter-submit:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

.filter-clear {
    padding: 0.75rem 1.5rem;
    background: var(--admin-gray);
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.filter-clear:hover {
    background: #6b7280;
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    padding: 1rem;
    border-radius: 0.75rem;
    box-shadow: var(--admin-shadow);
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: center;
}

.bulk-select {
    padding: 0.75rem;
    border: 1px solid var(--admin-border);
    border-radius: 0.5rem;
    background: white;
    color: var(--admin-dark);
    min-width: 200px;
}

.bulk-apply {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.bulk-apply:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

.total-count {
    margin-left: auto;
    color: var(--admin-gray);
    font-size: 0.875rem;
}

/* Orders Table */
.orders-table-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: var(--admin-shadow);
    overflow: hidden;
    margin-bottom: 2rem;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.orders-table thead {
    background: var(--admin-light);
}

.orders-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--admin-dark);
    border-bottom: 2px solid var(--admin-border);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.orders-table th.text-right {
    text-align: right;
}

.orders-table th.text-center {
    text-align: center;
}

.orders-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--admin-border);
    vertical-align: top;
}

.orders-table tr:last-child td {
    border-bottom: none;
}

.orders-table tr:hover {
    background: var(--admin-light);
}

/* Checkbox Column */
.checkbox-cell {
    width: 40px;
}

.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--admin-primary);
}

/* Order Number Cell */
.order-number {
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
    display: block;
    text-decoration: none;
    transition: var(--transition);
}

.order-number:hover {
    color: var(--admin-primary);
}

.item-count {
    font-size: 0.75rem;
    color: var(--admin-gray);
    background: var(--admin-light);
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    display: inline-block;
}

/* Customer Cell */
.customer-name {
    font-weight: 500;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
}

.customer-details {
    font-size: 0.75rem;
    color: var(--admin-gray);
    line-height: 1.4;
}

/* Amount Cell */
.order-amount {
    font-weight: 600;
    color: var(--admin-dark);
    text-align: right;
}

/* Status Cell */
.status-container {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-items: center;
}

.order-status-badge {
    padding: 0.4rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    min-width: 80px;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
}

.payment-status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 500;
    color: white;
    min-width: 60px;
    text-align: center;
}

/* Location Cell */
.location-info {
    color: var(--admin-gray);
    font-size: 0.875rem;
}

.location-empty {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.875rem;
}

/* Date Cell */
.order-date {
    color: var(--admin-gray);
    font-size: 0.875rem;
    line-height: 1.4;
}

.order-time {
    color: #9ca3af;
    font-size: 0.75rem;
}

/* Actions Cell */
.actions-container {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.quick-status-form {
    display: inline;
}

.quick-status-select {
    padding: 0.5rem;
    border: 1px solid var(--admin-border);
    border-radius: 0.5rem;
    background: white;
    color: var(--admin-dark);
    font-size: 0.75rem;
    cursor: pointer;
    transition: var(--transition);
}

.quick-status-select:focus {
    outline: none;
    border-color: var(--admin-primary);
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1rem;
    background: var(--admin-light);
    color: var(--admin-dark);
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.75rem;
    transition: var(--transition);
}

.view-btn:hover {
    background: var(--admin-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

/* Empty State */
.empty-state {
    padding: 4rem 1.5rem;
    text-align: center;
    color: var(--admin-gray);
}

.empty-icon {
    font-size: 3rem;
    color: var(--admin-border);
    margin-bottom: 1rem;
}

.empty-title {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
    color: var(--admin-dark);
}

.empty-description {
    color: var(--admin-gray);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-links {
    display: flex;
    gap: 0.5rem;
}

.pagination-link {
    padding: 0.75rem 1rem;
    background: var(--admin-light);
    color: var(--admin-dark);
    border-radius: 0.5rem;
    text-decoration: none;
    transition: var(--transition);
    font-size: 0.875rem;
    font-weight: 500;
    min-width: 40px;
    text-align: center;
}

.pagination-link:hover {
    background: var(--admin-primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow);
}

.pagination-link.active {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.pagination-link.disabled:hover {
    background: var(--admin-light);
    color: var(--admin-dark);
    transform: none;
    box-shadow: none;
}

/* Responsive */
@media (max-width: 768px) {
    .orders-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .filter-btn, .export-btn {
        flex: 1;
        justify-content: center;
    }
    
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        flex-direction: column;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-select, .bulk-apply {
        width: 100%;
    }
    
    .total-count {
        margin-left: 0;
        text-align: center;
    }
    
    .orders-table-container {
        border-radius: 0;
        margin: 0 -1rem;
        width: calc(100% + 2rem);
    }
    
    .pagination-links {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>

<main class="admin-main" style="margin:10px;">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <span>Manage Orders</span>
    </div>

    <!-- Header -->
    <div class="orders-header">
        <h1>Manage Orders</h1>
        <div class="header-actions">
            <button class="filter-btn" onclick="toggleFilters()">
                <i class="fas fa-filter"></i>
                Filters
            </button>
            <a href="?export=csv" class="export-btn">
                <i class="fas fa-download"></i>
                Export CSV
            </a>
        </div>
    </div>

    <?php if (isset($message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filters Panel -->
    <div id="filtersPanel" class="filters-panel">
        <form method="GET" class="filters-grid">
            <!-- Search -->
            <div class="filter-group">
                <label>Search</label>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Order number, customer name, email, phone..."
                       class="filter-input">
            </div>
            
            <!-- Status Filter -->
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="filter-select">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range -->
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" 
                       name="date_from" 
                       value="<?= htmlspecialchars($date_from) ?>" 
                       class="filter-input">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" 
                       name="date_to" 
                       value="<?= htmlspecialchars($date_to) ?>" 
                       class="filter-input">
            </div>
            
            <!-- Filter Buttons -->
            <div class="filter-group filter-buttons">
                <button type="submit" class="filter-submit">
                    Apply Filters
                </button>
                <button type="button" onclick="window.location='?'" class="filter-clear">
                    Clear Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <form method="POST" id="bulkForm" class="bulk-actions">
        <select name="bulk_action" class="bulk-select">
            <option value="">Bulk Actions</option>
            <option value="pending">Mark as Pending</option>
            <option value="processing">Mark as Processing</option>
            <option value="shipped">Mark as Shipped</option>
            <option value="delivered">Mark as Delivered</option>
            <option value="completed">Mark as Completed</option>
            <option value="cancelled">Mark as Cancelled</option>
            <option value="delete">Delete Selected</option>
        </select>
        <button type="submit" onclick="return confirmBulkAction()" class="bulk-apply">
            Apply
        </button>
        <div class="total-count">
            <?= number_format($total) ?> order(s) found
        </div>
    </form>

    <!-- Orders Table -->
    <div class="orders-table-container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3 class="empty-title">No orders found</h3>
                <p class="empty-description">
                    <?php if ($status_filter || $search || $date_from || $date_to): ?>
                        Try adjusting your filters to see more results.
                    <?php else: ?>
                        No orders have been placed yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="orders-table-wrapper">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="table-checkbox">
                            </th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                                // Determine which status to show
                                $display_status = !empty($order['status']) ? $order['status'] : $order['order_status'];
                                $status_color = match($display_status) {
                                    'pending'    => '#f59e0b',
                                    'paid'       => '#10b981',
                                    'processing' => '#3b82f6',
                                    'shipped'    => '#8b5cf6',
                                    'delivered'  => '#047857',
                                    'completed'  => '#047857',
                                    'cancelled'  => '#ef4444',
                                    default      => '#6b7280'
                                };
                                
                                $payment_color = match($order['payment_status'] ?? 'pending') {
                                    'pending'    => '#f59e0b',
                                    'paid'       => '#10b981',
                                    'failed'     => '#ef4444',
                                    default      => '#6b7280'
                                };
                            ?>
                            <tr>
                                <td class="checkbox-cell">
                                    <input type="checkbox" 
                                           name="selected_orders[]" 
                                           value="<?= $order['id'] ?>" 
                                           class="order-checkbox table-checkbox">
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?= $order['id'] ?>" class="order-number">
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </a>
                                    <span class="item-count"><?= $order['item_count'] ?> item(s)</span>
                                </td>
                                <td>
                                    <div class="customer-name"><?= htmlspecialchars($order['customer_name']) ?></div>
                                    <div class="customer-details">
                                        <?= htmlspecialchars($order['customer_email']) ?><br>
                                        <?php if ($order['customer_phone']): ?>
                                            <?= htmlspecialchars($order['customer_phone']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="order-amount">₦<?= number_format($order['total_amount']) ?></td>
                                <td>
                                    <div class="status-container">
                                        <span class="order-status-badge" style="background: <?= $status_color ?>">
                                            <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                            <?= ucfirst($display_status) ?>
                                        </span>
                                        <span class="payment-status-badge" style="background: <?= $payment_color ?>">
                                            <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($order['shipping_city'] || $order['shipping_state']): ?>
                                        <div class="location-info">
                                            <?= htmlspecialchars($order['shipping_city'] ?? '') ?><?= $order['shipping_city'] && $order['shipping_state'] ? ', ' : '' ?>
                                            <?= htmlspecialchars($order['shipping_state'] ?? '') ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="location-empty">Not specified</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="order-date">
                                        <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                        <div class="order-time"><?= date('H:i', strtotime($order['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="actions-container">
                                        <!-- Quick Status Update -->
                                        <form method="POST" class="quick-status-form">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="status" onchange="this.form.submit()" class="quick-status-select">
                                                <option value="">Quick update</option>
                                                <?php foreach ($statuses as $s): ?>
                                                    <option value="<?= $s ?>" <?= $display_status === $s ? 'selected' : '' ?>>
                                                        <?= ucfirst($s) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        
                                        <!-- View Button -->
                                        <a href="order-detail.php?id=<?= $order['id'] ?>" class="view-btn">
                                            <i class="fas fa-eye"></i>
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <div class="pagination-links">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="pagination-link">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="pagination-link <?= $page == $i ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="pagination-link">
                        Next →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function toggleFilters() {
    const panel = document.getElementById('filtersPanel');
    panel.style.display = panel.style.display === 'none' || panel.style.display === '' ? 'block' : 'none';
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.order-checkbox');
    const selectAll = document.getElementById('selectAll').checked;
    checkboxes.forEach(cb => cb.checked = selectAll);
}

function confirmBulkAction() {
    const selected = document.querySelectorAll('.order-checkbox:checked');
    const action = document.querySelector('[name="bulk_action"]').value;
    
    if (selected.length === 0) {
        alert('Please select at least one order.');
        return false;
    }
    
    if (!action) {
        alert('Please select a bulk action.');
        return false;
    }
    
    if (action === 'delete') {
        return confirm(`Are you sure you want to delete ${selected.length} order(s)? This action cannot be undone.`);
    }
    
    return confirm(`Are you sure you want to update ${selected.length} order(s) to "${action}"?`);
}

// Show filters panel if filters are active
<?php if ($status_filter || $search || $date_from || $date_to): ?>
document.getElementById('filtersPanel').style.display = 'block';
<?php endif; ?>

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F to toggle filters
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        toggleFilters();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) searchInput.focus();
    }
    
    // Ctrl/Cmd + A to select all
    if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
        e.preventDefault();
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = !selectAll.checked;
            toggleSelectAll();
        }
    }
});

// Add row click functionality for better UX
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('.orders-table tbody tr');
    rows.forEach(row => {
        // Skip clicks on checkboxes and links
        row.addEventListener('click', function(e) {
            if (e.target.type === 'checkbox' || e.target.tagName === 'A' || e.target.tagName === 'SELECT') {
                return;
            }
            
            const checkbox = row.querySelector('.order-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
            }
        });
    });
});
</script>

