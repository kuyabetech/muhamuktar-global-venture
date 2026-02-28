<?php
// admin/customers.php - Manage Customers/Users

$page_title = "Manage Customers";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php';

// Admin only
require_admin();

// Handle actions (block/unblock, delete)
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = (int)$_POST['user_id'];
    $new_status = $_POST['status'] ?? 'active'; // active / blocked

    if ($user_id > 0 && in_array($new_status, ['active', 'blocked'])) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'");
        $stmt->execute([$new_status, $user_id]);
        $message = "User status updated.";
    }
} elseif ($action === 'delete' && $id > 0) {
    // Check if user has orders
    $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        $message = "Cannot delete: User has placed orders. Cancel orders first.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
        $stmt->execute([$id]);
        $message = "Customer deleted successfully.";
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Build query
$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.created_at, u.status,
               COUNT(o.id) AS order_count
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.role = 'customer'";

$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter && in_array($status_filter, ['active', 'blocked'])) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$countSql = "SELECT COUNT(*) FROM users u WHERE u.role = 'customer'";
if ($search || $status_filter) {
    $countSql .= " AND " . substr($sql, strpos($sql, "WHERE") + 5, strpos($sql, "GROUP BY") - strpos($sql, "WHERE") - 5);
}

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<style>
/* Customers Page Responsive Styles */
.customers-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1400px;
    margin: 0 auto;
}

.filters-panel {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: clamp(1rem, 2vw, 1.5rem);
}

.filter-group {
    flex: 1;
    min-width: 220px;
}

.filter-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--admin-dark);
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 0.9rem;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    font-size: 1rem;
}

.filter-buttons {
    display: flex;
    gap: 1rem;
    align-self: flex-end;
}

.filter-submit,
.filter-reset {
    padding: 0.9rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    border: none;
}

.filter-submit {
    background: var(--admin-primary);
    color: white;
}

.filter-reset {
    background: var(--admin-gray);
    color: white;
}

/* Customers Table */
.table-wrapper {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.responsive-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 100%;
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

/* Mobile: stacked card layout */
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

    /* Center some columns on mobile */
    .responsive-table td[data-label="Orders"],
    .responsive-table td[data-label="Status"] {
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

/* General responsive */
@media (max-width: 1024px) {
    .filters-panel {
        flex-direction: column;
    }
}

@media (max-width: 768px) {
    .customers-container {
        padding: 1.2rem 1.5rem;
    }
}

@media (max-width: 480px) {
    .customers-container {
        padding: 1rem 1.2rem;
    }
}
</style>

<main class="customers-container">

    <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 2rem;">Manage Customers</h1>

    <?php if ($message): ?>
        <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:2rem;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-panel">
        <form method="GET" class="filter-group" style="flex:1; min-width:300px;">
            <label>Search (name, email, phone)</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Search..." class="filter-input">
        </form>

        <div class="filter-group" style="min-width:220px;">
            <label>Status</label>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
        </div>

        <div class="filter-buttons" style="align-self:flex-end;">
            <button type="submit" class="filter-submit">Apply</button>
            <button type="button" onclick="window.location='?'" class="filter-reset">Reset</button>
        </div>
    </form>

    <!-- Customers Table -->
    <div class="table-wrapper">
        <?php if (empty($customers)): ?>
            <div style="padding:4rem; text-align:center; color:#6b7280;">
                No customers found matching your filters.
            </div>
        <?php else: ?>
            <table class="responsive-table">
                <thead>
                    <tr style="background:#f8f9fc;">
                        <th data-label="Name / Email">Name / Email</th>
                        <th data-label="Phone">Phone</th>
                        <th data-label="Orders">Orders</th>
                        <th data-label="Joined">Joined</th>
                        <th data-label="Status">Status</th>
                        <th data-label="Actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $cust): ?>
                        <tr>
                            <td data-label="Name / Email">
                                <strong><?= htmlspecialchars($cust['full_name'] ?: 'No name') ?></strong><br>
                                <small style="color:#6b7280;"><?= htmlspecialchars($cust['email']) ?></small>
                            </td>
                            <td data-label="Phone"><?= htmlspecialchars($cust['phone'] ?: '—') ?></td>
                            <td data-label="Orders" style="text-align:center;">
                                <span style="
                                    background: #dbeafe;
                                    color: #1e40af;
                                    padding: 0.4rem 1rem;
                                    border-radius: 999px;
                                    font-size: 0.9rem;
                                    font-weight: 600;
                                ">
                                    <?= $cust['order_count'] ?>
                                </span>
                            </td>
                            <td data-label="Joined" style="color:#6b7280;">
                                <?= date('M d, Y', strtotime($cust['created_at'])) ?>
                            </td>
                            <td data-label="Status" style="text-align:center;">
                                <span style="
                                    background: <?= $cust['status'] === 'active' ? '#d1fae5' : '#fee2e2' ?>;
                                    color: <?= $cust['status'] === 'active' ? '#065f46' : '#991b1b' ?>;
                                    padding: 0.5rem 1rem;
                                    border-radius: 999px;
                                    font-size: 0.9rem;
                                    font-weight: 600;
                                ">
                                    <?= ucfirst($cust['status']) ?>
                                </span>
                            </td>
                            <td data-label="Actions" style="text-align:right;">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $cust['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px;">
                                        <option value="active" <?= $cust['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="blocked" <?= $cust['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>

                                <a href="?action=delete&id=<?= $cust['id'] ?>" 
                                   onclick="return confirm('Delete this customer? This cannot be undone.')" 
                                   style="margin-left:1rem; color:var(--admin-danger);">
                                    Delete
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" 
                           class="<?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</main>