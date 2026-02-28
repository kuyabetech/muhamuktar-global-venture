<?php
// admin/inventory.php - Inventory Management

$page_title = "Inventory Management";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php';

// Admin only
require_admin();

// Initialize variables
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $new_stock = (int)$_POST['stock'];
    $reason = trim($_POST['reason'] ?? 'Manual adjustment');
    
    try {
        $pdo->beginTransaction();
        
        // Get current stock
        $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        $old_stock = $product['stock'];
        
        // Update stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Log stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, old_stock, new_stock, change_amount, reason, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$product_id, $old_stock, $new_stock, ($new_stock - $old_stock), $reason]);
        
        $pdo->commit();
        $success_msg = "Stock updated for " . htmlspecialchars($product['name']);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error updating stock: " . $e->getMessage();
    }
}

// Handle bulk stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    $action = $_POST['bulk_action'] ?? '';
    $value = (int)($_POST['bulk_value'] ?? 0);
    $selected = $_POST['selected_products'] ?? [];
    
    if (!empty($selected) && $action && $value > 0) {
        try {
            $pdo->beginTransaction();
            
            foreach ($selected as $product_id) {
                $stmt = $pdo->prepare("SELECT stock, name FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                $old_stock = $product['stock'];
                
                if ($action === 'add') {
                    $new_stock = $old_stock + $value;
                } elseif ($action === 'subtract') {
                    $new_stock = max(0, $old_stock - $value);
                } elseif ($action === 'set') {
                    $new_stock = $value;
                }
                
                $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
                $stmt->execute([$new_stock, $product_id]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, old_stock, new_stock, change_amount, reason, created_at)
                    VALUES (?, ?, ?, ?, 'Bulk update', NOW())
                ");
                $stmt->execute([$product_id, $old_stock, $new_stock, ($new_stock - $old_stock)]);
            }
            
            $pdo->commit();
            $success_msg = "Bulk stock update completed successfully";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error in bulk update: " . $e->getMessage();
        }
    }
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($category_filter)) {
    $where[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low') {
    $where[] = "p.stock < 10 AND p.stock > 0";
} elseif ($stock_filter === 'out') {
    $where[] = "p.stock = 0";
} elseif ($stock_filter === 'in') {
    $where[] = "p.stock >= 10";
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM products p $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Fetch products with stock info
$sql = "
    SELECT p.*, c.name AS category_name,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS image,
           (SELECT SUM(quantity) FROM order_items WHERE product_id = p.id) AS total_sold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    $where_sql
    ORDER BY p.stock ASC, p.name ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get stock statistics
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'total_stock' => $pdo->query("SELECT SUM(stock) FROM products")->fetchColumn() ?: 0,
    'low_stock' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10 AND stock > 0")->fetchColumn(),
    'out_of_stock' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn(),
    'total_value' => $pdo->query("SELECT SUM(price * stock) FROM products")->fetchColumn() ?: 0
];

// Get recent stock movements
$movements = $pdo->query("
    SELECT sm.*, p.name AS product_name, p.sku
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    ORDER BY sm.created_at DESC
    LIMIT 20
")->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<style>
/* Responsive Inventory Page */
.inventory-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1600px;
    margin: 0 auto;
}

/* Header */
.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.inventory-header h1 {
    font-size: clamp(1.8rem, 6vw, 2.3rem);
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: clamp(1rem, 2vw, 1.6rem);
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.8rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-value {
    font-size: clamp(1.8rem, 5vw, 2.4rem);
    font-weight: 700;
}

.stat-label {
    font-size: 0.95rem;
    color: var(--admin-gray);
}

/* Filters & Bulk Panels */
.filters-panel,
.bulk-panel {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.filter-grid,
.bulk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: clamp(1rem, 2vw, 1.5rem);
    align-items: end;
}

.filter-group label,
.bulk-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--admin-dark);
}

/* Tables */
.table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.responsive-table {
    width: 100%;
    border-collapse: collapse;
}

.responsive-table th,
.responsive-table td {
    padding: clamp(0.8rem, 1.8vw, 1.2rem);
    text-align: left;
    border-bottom: 1px solid var(--admin-border);
}

.responsive-table th {
    background: #f8f9fc;
    font-weight: 600;
    white-space: nowrap;
}

/* Mobile: stacked card layout for tables */
@media screen and (max-width: 768px) {
    .responsive-table thead {
        display: none;
    }

    .responsive-table tr {
        display: block;
        margin-bottom: 1.3rem;
        border: 1px solid var(--admin-border);
        border-radius: 10px;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .responsive-table td {
        display: block;
        text-align: right;
        border: none;
        padding: 0.9rem 1.2rem;
        position: relative;
        border-bottom: 1px solid var(--admin-border);
    }

    .responsive-table td:last-child {
        border-bottom: 0;
    }

    .responsive-table td::before {
        content: attr(data-label);
        position: absolute;
        left: 1.2rem;
        width: 45%;
        font-weight: 600;
        color: var(--admin-gray);
        text-align: left;
    }

    /* Center key columns on mobile */
    .responsive-table td[data-label="Current Stock"],
    .responsive-table td[data-label="Sold"],
    .responsive-table td[data-label="Stock Value"] {
        text-align: center;
    }
}

@media screen and (max-width: 480px) {
    .responsive-table td {
        padding: 0.75rem 1rem;
        font-size: 0.92rem;
    }

    .responsive-table td::before {
        width: 50%;
    }
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination a {
    padding: 0.6rem 1rem;
    background: #f3f4f6;
    color: var(--admin-dark);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
}

.pagination a.active,
.pagination a:hover {
    background: var(--admin-primary);
    color: white;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .inventory-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .filter-grid,
    .bulk-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (max-width: 768px) {
    .inventory-container {
        padding: 1.2rem 1.5rem;
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .filter-grid,
    .bulk-grid {
        grid-template-columns: 1fr;
    }

    .filter-group,
    .bulk-group {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .inventory-container {
        padding: 1rem 1.2rem;
    }
}
</style>

<div class="inventory-container">

    <!-- Page Header -->
    <div class="inventory-header">
        <div>
            <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 0.5rem;">
                <i class="fas fa-warehouse"></i> Inventory Management
            </h1>
            <p style="color: var(--admin-gray);">Track and manage product stock levels</p>
        </div>
        <button class="btn btn-primary" onclick="exportInventory()">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>

    <!-- Messages -->
    <?php if (isset($success_msg) && $success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (isset($error_msg) && $error_msg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stock Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-box"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total_stock']) ?></div>
                    <div class="stat-label">Total Stock Units</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-cubes"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['low_stock']) ?></div>
                    <div class="stat-label">Low Stock Items</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['out_of_stock']) ?></div>
                    <div class="stat-label">Out of Stock</div>
                </div>
                <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">₦<?= number_format($stats['total_value']) ?></div>
                    <div class="stat-label">Inventory Value</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
    </div>

    <!-- Search & Filters Panel (now fully responsive) -->
    <div class="filters-panel">
        <form method="get" action="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="form-label">Search Products</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Name, SKU, Brand..." class="form-control">
                </div>
                
                <div class="filter-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="form-label">Stock Status</label>
                    <select name="stock" class="form-control">
                        <option value="">All Stock</option>
                        <option value="in" <?= $stock_filter === 'in' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low" <?= $stock_filter === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stock_filter === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                
                <div class="filter-group" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="inventory.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Update Panel -->
    <div class="bulk-panel">
        <h3 style="margin-bottom: 1rem;">Bulk Stock Update</h3>
        <form method="post" id="bulkForm">
            <div class="bulk-grid">
                <div class="filter-group">
                    <label class="form-label">Action</label>
                    <select name="bulk_action" class="form-control">
                        <option value="add">Add Stock</option>
                        <option value="subtract">Subtract Stock</option>
                        <option value="set">Set to</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="bulk_value" class="form-control" 
                           placeholder="Quantity" min="1" required>
                </div>
                
                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit" name="bulk_update" class="btn btn-primary" 
                            onclick="return confirmBulkUpdate()">
                        Apply to Selected
                    </button>
                    
                    <span style="color: var(--admin-gray);">
                        <span id="selectedCount">0</span> products selected
                    </span>
                </div>
            </div>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="table-wrapper">
        <?php if (empty($products)): ?>
            <div style="padding: 4rem; text-align: center; color: var(--admin-gray);">
                <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                No products found
            </div>
        <?php else: ?>
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="selectAll"></th>
                        <th data-label="Product">Product</th>
                        <th data-label="SKU">SKU</th>
                        <th data-label="Category">Category</th>
                        <th data-label="Current Stock">Current Stock</th>
                        <th data-label="Low Stock Alert">Low Stock Alert</th>
                        <th data-label="Sold">Sold</th>
                        <th data-label="Stock Value">Stock Value</th>
                        <th data-label="Actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php 
                        $stock_class = $p['stock'] == 0 ? 'danger' : ($p['stock'] < 10 ? 'warning' : 'success');
                        $stock_icon = $p['stock'] == 0 ? 'times-circle' : ($p['stock'] < 10 ? 'exclamation-triangle' : 'check-circle');
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="product-checkbox" value="<?= $p['id'] ?>">
                            </td>
                            <td data-label="Product">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($p['image']) ?>" 
                                             alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; background: var(--admin-light); border-radius: 6px; 
                                                    display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: var(--admin-gray);"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($p['name']) ?></div>
                                        <?php if (!empty($p['brand'])): ?>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                                <?= htmlspecialchars($p['brand']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="SKU"><?= htmlspecialchars($p['sku'] ?? '-') ?></td>
                            <td data-label="Category"><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
                            <td data-label="Current Stock">
                                <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: center;">
                                    <span style="font-weight: 700; font-size: 1.1rem; color: var(--admin-<?= $stock_class ?>);">
                                        <?= number_format($p['stock']) ?>
                                    </span>
                                    <i class="fas fa-<?= $stock_icon ?>" style="color: var(--admin-<?= $stock_class ?>);"></i>
                                </div>
                                <!-- Quick update form -->
                                <form method="post" style="margin-top: 0.5rem; display: flex; gap: 0.25rem; justify-content: center; flex-wrap: wrap;">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="number" name="stock" value="<?= $p['stock'] ?>" 
                                           min="0" style="width: 80px; padding: 0.25rem; border: 1px solid var(--admin-border); border-radius: 4px;">
                                    <button type="submit" name="update_stock" class="btn btn-secondary btn-sm" 
                                            style="padding: 0.25rem 0.5rem;">
                                        Update
                                    </button>
                                </form>
                            </td>
                            <td data-label="Low Stock Alert">
                                <?php if ($p['stock'] < 10 && $p['stock'] > 0): ?>
                                    <span class="status-badge status-warning">Low Stock</span>
                                <?php elseif ($p['stock'] == 0): ?>
                                    <span class="status-badge status-danger">Out of Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-success">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Sold" style="text-align:center; font-weight: 600;">
                                <?= number_format($p['total_sold'] ?? 0) ?>
                            </td>
                            <td data-label="Stock Value" style="text-align:center; font-weight: 600;">
                                ₦<?= number_format($p['price'] * $p['stock'], 2) ?>
                            </td>
                            <td data-label="Actions">
                                <a href="products.php?action=edit&id=<?= $p['id'] ?>" 
                                   class="btn btn-secondary btn-sm" style="padding: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&stock=<?= $stock_filter ?>" 
                       class="page-link"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&stock=<?= $stock_filter ?>" 
                       class="page-link"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&stock=<?= $stock_filter ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&stock=<?= $stock_filter ?>" 
                       class="page-link"><i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= $category_filter ?>&stock=<?= $stock_filter ?>" 
                       class="page-link"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Stock Movements -->
    <div class="table-wrapper" style="margin-top: 2rem;">
        <h3 style="padding: 1.5rem 1.5rem 0; margin-bottom: 1rem;">Recent Stock Movements</h3>
        <table class="responsive-table">
            <thead>
                <tr>
                    <th data-label="Date/Time">Date/Time</th>
                    <th data-label="Product">Product</th>
                    <th data-label="SKU">SKU</th>
                    <th data-label="Change">Change</th>
                    <th data-label="New Stock">New Stock</th>
                    <th data-label="Reason">Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td data-label="Date/Time"><?= date('M d, H:i', strtotime($m['created_at'])) ?></td>
                        <td data-label="Product"><?= htmlspecialchars($m['product_name']) ?></td>
                        <td data-label="SKU"><?= htmlspecialchars($m['sku'] ?? '-') ?></td>
                        <td data-label="Change" style="color: <?= $m['change_amount'] > 0 ? 'var(--admin-success)' : 'var(--admin-danger)' ?>; font-weight: 600;">
                            <?= $m['change_amount'] > 0 ? '+' : '' ?><?= $m['change_amount'] ?>
                        </td>
                        <td data-label="New Stock"><?= $m['new_stock'] ?></td>
                        <td data-label="Reason"><?= htmlspecialchars($m['reason']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Select All + Count
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.product-checkbox:checked').length;
        selectedCount.textContent = checked;
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }
    
    checkboxes.forEach(cb => cb.addEventListener('change', updateSelectedCount));
});

function confirmBulkUpdate() {
    const checked = document.querySelectorAll('.product-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one product');
        return false;
    }
    return confirm(`Update stock for ${checked} product(s)?`);
}

function exportInventory() {
    window.location.href = 'export-inventory.php?' + new URLSearchParams({
        search: '<?= addslashes($search) ?>',
        category: '<?= $category_filter ?>',
        stock: '<?= $stock_filter ?>'
    });
}
</script>