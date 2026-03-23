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
/* Base Admin Styles */
.admin-main {
    padding: 2rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #6b7280;
    flex-wrap: wrap;
}

.breadcrumb a {
    color: #4f46e5;
    text-decoration: none;
    transition: color 0.3s;
}

.breadcrumb a:hover {
    color: #4338ca;
    text-decoration: underline;
}

/* Responsive Typography */
h1 { font-size: clamp(1.8rem, 4vw, 2rem); }
h2 { font-size: clamp(1.3rem, 3vw, 1.5rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.25rem); }

/* Header Section */
.orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.orders-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.filter-btn, .export-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    border: none;
    font-size: 0.9rem;
}

.filter-btn {
    background: white;
    color: #1f2937;
    border: 2px solid #e5e7eb;
}

.filter-btn:hover {
    background: #f9fafb;
    border-color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.15);
}

.export-btn {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

/* Success Message */
.success-message {
    background: linear-gradient(90deg, #d1fae5, #ecfdf5);
    border-left: 4px solid #10b981;
    color: #065f46;
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Filters Panel */
.filters-panel {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    border: 1px solid #e5e7eb;
    display: none;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #1f2937;
    font-size: 0.9rem;
}

.filter-input, .filter-select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.filter-input:focus, .filter-select:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.filter-input:hover, .filter-select:hover {
    border-color: #9ca3af;
}

.filter-buttons {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.filter-submit {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
}

.filter-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.filter-clear {
    padding: 0.75rem 1.5rem;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.filter-clear:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

/* Bulk Actions */
.bulk-actions {
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid #e5e7eb;
}

.bulk-select {
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    background: white;
    color: #1f2937;
    min-width: 200px;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.bulk-select:focus {
    outline: none;
    border-color: #4f46e5;
}

.bulk-apply {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.bulk-apply:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.total-count {
    margin-left: auto;
    color: #6b7280;
    font-size: 0.95rem;
    background: #f3f4f6;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
}

/* Orders Table */
.orders-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
}

.orders-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

.orders-table thead {
    background: linear-gradient(90deg, #f9fafb, #f3f4f6);
}

.orders-table th {
    padding: 1.2rem 1rem;
    text-align: left;
    font-weight: 700;
    color: #1f2937;
    border-bottom: 2px solid #e5e7eb;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.orders-table th.text-right {
    text-align: right;
}

.orders-table th.text-center {
    text-align: center;
}

.orders-table td {
    padding: 1.2rem 1rem;
    border-bottom: 1px solid #e5e7eb;
    vertical-align: middle;
    font-size: 0.95rem;
}

.orders-table tr:last-child td {
    border-bottom: none;
}

.orders-table tbody tr {
    transition: background 0.3s;
}

.orders-table tbody tr:hover {
    background: #f9fafb;
}

/* Checkbox Column */
.checkbox-cell {
    width: 50px;
    text-align: center;
}

.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4f46e5;
    transition: all 0.2s;
}

.table-checkbox:hover {
    transform: scale(1.1);
}

/* Order Number Cell */
.order-number {
    font-weight: 700;
    color: #4f46e5;
    margin-bottom: 0.25rem;
    display: inline-block;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.order-number:hover {
    color: #4338ca;
    transform: translateX(2px);
}

.item-count {
    font-size: 0.75rem;
    color: #6b7280;
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    display: inline-block;
    margin-left: 0.5rem;
}

/* Customer Cell */
.customer-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.customer-details {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.5;
}

/* Amount Cell */
.order-amount {
    font-weight: 700;
    color: #1f2937;
    text-align: right;
    font-size: 1rem;
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
    min-width: 90px;
    text-align: center;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.payment-status-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
    min-width: 70px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Location Cell */
.location-info {
    color: #1f2937;
    font-size: 0.9rem;
    font-weight: 500;
}

.location-empty {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.85rem;
}

/* Date Cell */
.order-date {
    color: #1f2937;
    font-size: 0.9rem;
    line-height: 1.5;
    font-weight: 500;
}

.order-time {
    color: #6b7280;
    font-size: 0.8rem;
    font-weight: normal;
}

/* Actions Cell */
.actions-container {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    align-items: center;
}

.quick-status-form {
    display: inline;
}

.quick-status-select {
    padding: 0.6rem 0.8rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 120px;
}

.quick-status-select:focus {
    outline: none;
    border-color: #4f46e5;
}

.quick-status-select:hover {
    border-color: #9ca3af;
}

.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s;
    border: none;
}

.view-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

/* Empty State */
.empty-state {
    padding: 5rem 1.5rem;
    text-align: center;
    color: #6b7280;
}

.empty-icon {
    font-size: 4rem;
    color: #e5e7eb;
    margin-bottom: 1.5rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.empty-title {
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
    color: #1f2937;
    font-weight: 700;
}

.empty-description {
    color: #6b7280;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto;
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
    flex-wrap: wrap;
    justify-content: center;
}

.pagination-link {
    padding: 0.75rem 1rem;
    background: white;
    color: #1f2937;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 45px;
    text-align: center;
    border: 2px solid #e5e7eb;
}

.pagination-link:hover {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.pagination-link.active {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border-color: #4f46e5;
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Responsive Breakpoints */
@media (max-width: 1200px) {
    .orders-table {
        min-width: 1000px;
    }
    
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .orders-header {
        padding: 1.25rem;
    }
    
    .filters-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .orders-header {
        flex-direction: column;
        align-items: stretch;
        padding: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .filter-btn, .export-btn {
        flex: 1;
        justify-content: center;
        padding: 0.75rem;
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
        padding: 1rem;
    }
    
    .bulk-select, .bulk-apply {
        width: 100%;
    }
    
    .total-count {
        margin-left: 0;
        text-align: center;
    }
    
    .orders-table-container {
        border-radius: 10px;
        margin: 0;
    }
    
    .pagination-links {
        gap: 0.35rem;
    }
    
    .pagination-link {
        padding: 0.6rem 0.8rem;
        min-width: 38px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .orders-header h1 {
        font-size: 1.5rem;
    }
    
    .header-actions {
        flex-direction: column;
    }
    
    .filter-btn, .export-btn {
        width: 100%;
    }
    
    .orders-table {
        min-width: 800px;
    }
    
    .empty-icon {
        font-size: 3rem;
    }
    
    .empty-title {
        font-size: 1.1rem;
    }
    
    .empty-description {
        font-size: 0.9rem;
    }
    
    .pagination-links {
        gap: 0.25rem;
    }
    
    .pagination-link {
        padding: 0.5rem 0.7rem;
        min-width: 35px;
        font-size: 0.8rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .orders-header, .filters-panel, .bulk-actions, .orders-table-container {
        background: #1f2937;
        border-color: #374151;
    }
    
    .orders-header h1 {
        color: #f3f4f6;
    }
    
    .filter-btn {
        background: #374151;
        color: #f3f4f6;
        border-color: #4b5563;
    }
    
    .filter-btn:hover {
        background: #4b5563;
    }
    
    .total-count {
        background: #374151;
        color: #d1d5db;
    }
    
    .orders-table thead {
        background: #374151;
    }
    
    .orders-table th {
        color: #e5e7eb;
        border-bottom-color: #4b5563;
    }
    
    .orders-table td {
        border-bottom-color: #4b5563;
        color: #d1d5db;
    }
    
    .orders-table tbody tr:hover {
        background: #374151;
    }
    
    .customer-name, .order-amount, .order-number, .location-info, .order-date {
        color: #f3f4f6;
    }
    
    .customer-details, .order-time, .location-empty {
        color: #9ca3af;
    }
    
    .filter-input, .filter-select, .bulk-select, .quick-status-select {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    
    .filter-input:hover, .filter-select:hover, .bulk-select:hover, .quick-status-select:hover {
        border-color: #6b7280;
    }
    
    .filter-input:focus, .filter-select:focus, .bulk-select:focus, .quick-status-select:focus {
        border-color: #818cf8;
    }
    
    .pagination-link {
        background: #374151;
        border-color: #4b5563;
        color: #d1d5db;
    }
    
    .pagination-link:hover {
        background: #4f46e5;
        color: white;
    }
}

/* Print Styles */
@media print {
    .filter-btn, .export-btn, .bulk-actions, .actions-container, .pagination {
        display: none !important;
    }
    
    .orders-table-container {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    .orders-table th {
        background: #f3f4f6;
    }
}
</style>

<main class="admin-main">
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
                <span class="btn-text">Filters</span>
            </button>
            <a href="?export=csv" class="export-btn">
                <i class="fas fa-download"></i>
                <span class="btn-text">Export CSV</span>
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
    <div id="filtersPanel" class="filters-panel" <?= ($status_filter || $search || $date_from || $date_to) ? 'style="display:block;"' : '' ?>>
        <form method="GET" class="filters-grid">
            <!-- Search -->
            <div class="filter-group">
                <label>Search</label>
                <input type="text" 
                       name="search" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Order number, customer..."
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
                <label>From Date</label>
                <input type="date" 
                       name="date_from" 
                       value="<?= htmlspecialchars($date_from) ?>" 
                       class="filter-input">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
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
                <button type="button" onclick="window.location='?page=<?= $page ?>'" class="filter-clear">
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
                                            <span class="btn-text">View</span>
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
                        <i class="fas fa-chevron-left"></i>
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
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
function toggleFilters() {
    const panel = document.getElementById('filtersPanel');
    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'block';
        panel.style.animation = 'fadeIn 0.3s ease';
    } else {
        panel.style.display = 'none';
    }
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

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F to toggle filters
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        toggleFilters();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            setTimeout(() => searchInput.focus(), 100);
        }
    }
    
    // Ctrl/Cmd + A to select all
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
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
                
                // Highlight row when selected
                if (checkbox.checked) {
                    row.style.background = 'rgba(79, 70, 229, 0.1)';
                } else {
                    row.style.background = '';
                }
            }
        });
        
        // Check if checkbox is already checked
        const checkbox = row.querySelector('.order-checkbox');
        if (checkbox && checkbox.checked) {
            row.style.background = 'rgba(79, 70, 229, 0.1)';
        }
    });
    
    // Handle select all with highlighting
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const isChecked = this.checked;
            rows.forEach(row => {
                const checkbox = row.querySelector('.order-checkbox');
                if (checkbox) {
                    checkbox.checked = isChecked;
                    if (isChecked) {
                        row.style.background = 'rgba(79, 70, 229, 0.1)';
                    } else {
                        row.style.background = '';
                    }
                }
            });
        });
    }
});

// Handle responsive button text
function handleResponsiveText() {
    const btnTexts = document.querySelectorAll('.btn-text');
    if (window.innerWidth <= 480) {
        btnTexts.forEach(el => el.style.display = 'none');
    } else {
        btnTexts.forEach(el => el.style.display = '');
    }
}

window.addEventListener('load', handleResponsiveText);
window.addEventListener('resize', handleResponsiveText);
</script>