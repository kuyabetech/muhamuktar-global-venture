<?php
// admin/payments.php - View Payment Transactions

$page_title = "Payments & Transactions";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Admin only
require_admin();

// Handle filters
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT p.*, o.reference AS order_ref, u.full_name AS customer_name, u.email
        FROM payments p
        LEFT JOIN orders o ON p.order_id = o.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";

$params = [];

if ($status_filter && in_array($status_filter, ['pending','successful','failed','refunded'])) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (p.reference LIKE ? OR o.reference LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$countSql = "SELECT COUNT(*) FROM payments p WHERE 1=1" . 
            (strpos($sql, "AND") !== false ? substr($sql, strpos($sql, "WHERE") + 5, strpos($sql, "ORDER") - strpos($sql, "WHERE") - 5) : "");
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<main style="margin-left:260px; padding:2rem;">

  <h1 style="font-size:2.3rem; margin-bottom:2rem;">Payments & Transactions</h1>

  <!-- Filters -->
  <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:2rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
    <div style="flex:1; min-width:300px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Search (reference / order / customer)</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
    </div>

    <div style="min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Status</label>
      <select name="status" onchange="window.location='?status='+this.value" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        <option value="">All</option>
        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="successful" <?= $status_filter === 'successful' ? 'selected' : '' ?>>Successful</option>
        <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="refunded" <?= $status_filter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
      </select>
    </div>

    <div style="align-self:flex-end;">
      <button onclick="window.location='?'" style="padding:0.9rem 1.5rem; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer;">
        Reset
      </button>
    </div>
  </div>

  <!-- Payments Table -->
  <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

    <?php if (empty($payments)): ?>
      <div style="padding:4rem; text-align:center; color:#6b7280;">
        No payment records found matching your filters.
      </div>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8f9fc;">
            <th style="padding:1.2rem; text-align:left;">Reference</th>
            <th style="padding:1.2rem; text-align:left;">Order</th>
            <th style="padding:1.2rem; text-align:left;">Customer</th>
            <th style="padding:1.2rem; text-align:right;">Amount</th>
            <th style="padding:1.2rem; text-align:center;">Status</th>
            <th style="padding:1.2rem; text-align:center;">Date</th>
            <th style="padding:1.2rem; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <?php
              $status_color = match($p['status']) {
                'pending'    => '#d97706',
                'successful' => '#059669',
                'failed'     => '#dc2626',
                'refunded'   => '#7c3aed',
                default      => '#6b7280'
              };
            ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:1.2rem; font-weight:500;">
                <?= htmlspecialchars($p['reference']) ?>
              </td>
              <td style="padding:1.2rem;">
                <?= htmlspecialchars($p['order_ref'] ?? '—') ?>
              </td>
              <td style="padding:1.2rem;">
                <?= htmlspecialchars($p['customer_name'] ?? $p['email'] ?? 'Guest') ?>
              </td>
              <td style="padding:1.2rem; text-align:right; font-weight:600;">
                ₦<?= number_format($p['amount']) ?>
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
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
              <td style="padding:1.2rem; color:#6b7280; text-align:center;">
                <?= date('M d, Y • H:i', strtotime($p['created_at'])) ?>
              </td>
              <td style="padding:1.2rem; text-align:right;">
                <a href="https://dashboard.paystack.com/#/transactions/<?= htmlspecialchars($p['reference']) ?>" target="_blank" style="color:var(--primary); font-weight:600;">
                  View in Paystack
                </a>
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