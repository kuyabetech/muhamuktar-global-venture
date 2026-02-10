<?php
// admin/orders.php - Manage Orders

$page_title = "Manage Orders";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Admin only
require_admin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'] ?? '';

    if ($order_id > 0 && in_array($new_status, ['pending','paid','processing','shipped','delivered','completed','cancelled'])) {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $message = "Order status updated successfully.";
    } else {
        $message = "Invalid request.";
    }
}

// Filters
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT o.id, o.reference, o.total_amount, o.status, o.created_at, u.full_name, u.email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (o.reference LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.created_at DESC";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1" . substr($sql, strpos($sql, "WHERE") + 5, strpos($sql, "ORDER") - strpos($sql, "WHERE") - 5);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Status options
$statuses = ['pending','paid','processing','shipped','delivered','completed','cancelled'];
?>

<main style="margin-left:260px; padding:2rem;">

  <h1 style="font-size:2.3rem; margin-bottom:1.5rem;">Manage Orders</h1>

  <?php if (isset($message)): ?>
    <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:1.5rem;">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:2rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
    <div style="flex:1; min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Search (reference / customer)</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
    </div>

    <div style="flex:1; min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Filter by Status</label>
      <select name="status" onchange="window.location = '?status=' + this.value" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $status_filter === $s ? 'selected' : '' ?>>
            <?= ucfirst($s) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="align-self:flex-end;">
      <button onclick="window.location='?'" style="padding:0.9rem 1.5rem; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer;">
        Reset Filters
      </button>
    </div>
  </div>

  <!-- Orders Table -->
  <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

    <?php if (empty($orders)): ?>
      <div style="padding:4rem; text-align:center; color:#6b7280;">
        No orders found matching your filters.
      </div>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8f9fc;">
            <th style="padding:1.2rem; text-align:left;">Order ID</th>
            <th style="padding:1.2rem; text-align:left;">Customer</th>
            <th style="padding:1.2rem; text-align:right;">Amount</th>
            <th style="padding:1.2rem; text-align:center;">Status</th>
            <th style="padding:1.2rem; text-align:left;">Date</th>
            <th style="padding:1.2rem; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <?php
              $status_color = match($order['status']) {
                'pending'    => '#d97706',
                'paid'       => '#059669',
                'processing' => '#1e40af',
                'shipped'    => '#7c3aed',
                'delivered'  => '#047857',
                'completed'  => '#047857',
                'cancelled'  => '#dc2626',
                default      => '#6b7280'
              };
            ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:1.2rem; font-weight:500;">
                <?= htmlspecialchars($order['reference']) ?>
              </td>
              <td style="padding:1.2rem;">
                <?= htmlspecialchars($order['full_name'] ?? $order['email'] ?? 'Guest') ?>
              </td>
              <td style="padding:1.2rem; text-align:right; font-weight:600;">
                ₦<?= number_format($order['total_amount']) ?>
              </td>
              <td style="padding:1.2rem; text-align:center;">
                <span style="
                  background: <?= $status_color ?>;
                  color: white;
                  padding: 0.5rem 1rem;
                  border-radius: 999px;
                  font-size: 0.9rem;
                  font-weight: 600;
                ">
                  <?= ucfirst($order['status']) ?>
                </span>
              </td>
              <td style="padding:1.2rem; color:#6b7280;">
                <?= date('M d, Y • H:i', strtotime($order['created_at'])) ?>
              </td>
              <td style="padding:1.2rem; text-align:right;">
                <form method="post" style="display:inline;">
                  <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                  <select name="status" onchange="this.form.submit()" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px;">
                    <?php foreach ($statuses as $s): ?>
                      <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                        <?= ucfirst($s) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="hidden" name="update_status" value="1">
                </form>

                <a href="<?= BASE_URL ?>admin/order-detail.php?id=<?= $order['id'] ?>" style="margin-left:1rem; color:var(--primary); font-weight:600;">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination (simple) -->
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