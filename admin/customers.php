<?php
// admin/customers.php - Manage Customers/Users

$page_title = "Manage Customers";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

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

$countSql = "SELECT COUNT(*) FROM users u WHERE u.role = 'customer'" . 
            (strpos($sql, "AND") !== false ? substr($sql, strpos($sql, "WHERE") + 5, strpos($sql, "GROUP BY") - strpos($sql, "WHERE") - 5) : "");
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<main style="margin-left:260px; padding:2rem;">

  <h1 style="font-size:2.3rem; margin-bottom:2rem;">Manage Customers</h1>

  <?php if ($message): ?>
    <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:2rem;">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:2rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
    <div style="flex:1; min-width:300px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Search (name, email, phone)</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
    </div>

    <div style="min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Status</label>
      <select name="status" onchange="window.location = '?status=' + this.value" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        <option value="">All</option>
        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="blocked" <?= $status_filter === 'blocked' ? 'selected' : '' ?>>Blocked</option>
      </select>
    </div>

    <div style="align-self:flex-end;">
      <button onclick="window.location='?'" style="padding:0.9rem 1.5rem; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer;">
        Reset
      </button>
    </div>
  </div>

  <!-- Customers Table -->
  <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

    <?php if (empty($customers)): ?>
      <div style="padding:4rem; text-align:center; color:#6b7280;">
        No customers found matching your filters.
      </div>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8f9fc;">
            <th style="padding:1.2rem; text-align:left;">Name / Email</th>
            <th style="padding:1.2rem; text-align:left;">Phone</th>
            <th style="padding:1.2rem; text-align:center;">Orders</th>
            <th style="padding:1.2rem; text-align:center;">Joined</th>
            <th style="padding:1.2rem; text-align:center;">Status</th>
            <th style="padding:1.2rem; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $cust): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:1.2rem;">
                <strong><?= htmlspecialchars($cust['full_name'] ?: 'No name') ?></strong><br>
                <small style="color:#6b7280;"><?= htmlspecialchars($cust['email']) ?></small>
              </td>
              <td style="padding:1.2rem;"><?= htmlspecialchars($cust['phone'] ?: '—') ?></td>
              <td style="padding:1.2rem; text-align:center;">
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
              <td style="padding:1.2rem; color:#6b7280;">
                <?= date('M d, Y', strtotime($cust['created_at'])) ?>
              </td>
              <td style="padding:1.2rem; text-align:center;">
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
              <td style="padding:1.2rem; text-align:right;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="user_id" value="<?= $cust['id'] ?>">
                  <select name="status" onchange="this.form.submit()" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px;">
                    <option value="active" <?= $cust['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $cust['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                  </select>
                  <input type="hidden" name="update_status" value="1">
                </form>

                <a href="?action=delete&id=<?= $cust['id'] ?>" onclick="return confirm('Delete this customer? This cannot be undone.')" style="margin-left:1rem; color:var(--danger);">Delete</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div style="padding:1.5rem; text-align:center;">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>" style="
              display:inline-block;
              padding:0.6rem 1rem;
              margin:0 0.3rem;
              background:<?= $page == $i ? 'var(--primary)' : '#f3f4f6' ?>;
              color:<?= $page == $i ? 'white' : 'inherit' ?>;
              border-radius:8px;
              text-decoration:none;
            ">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

</main>

<?php require_once '../includes/footer.php'; ?>