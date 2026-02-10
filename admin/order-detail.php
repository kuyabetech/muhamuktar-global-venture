<?php
// admin/order-detail.php?id=123

$page_title = "Order Details";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Admin only + valid order ID
require_admin();

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header("Location: " . BASE_URL . "admin/orders.php");
    exit;
}

// Fetch order
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email, u.phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
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
    SELECT oi.*, p.name, p.slug
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
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
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$new_status, $order_id]);
    }

    if ($tracking_number || $admin_note) {
        // For simplicity: append to a notes field (add column if needed: admin_notes TEXT)
        $pdo->prepare("UPDATE orders SET tracking_number = ?, admin_notes = CONCAT(COALESCE(admin_notes,''), '\n', ?) WHERE id = ?")
            ->execute([$tracking_number, date('Y-m-d H:i') . " - $admin_note", $order_id]);
    }

    $message = "Order updated successfully.";
    // Refresh order data
    header("Location: ?id=$order_id&msg=" . urlencode($message));
    exit;
}

// Get updated order after potential update
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Status options
$statuses = ['pending','paid','processing','shipped','delivered','completed','cancelled'];
?>

<main style="margin-left:260px; padding:2rem;">

  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
    <h1 style="font-size:2.3rem; margin:0;">
      Order #<?= htmlspecialchars($order['reference']) ?>
    </h1>
    <a href="<?= BASE_URL ?>admin/orders.php" style="color:var(--primary); font-weight:600;">← Back to Orders</a>
  </div>

  <?php if (isset($_GET['msg'])): ?>
    <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:2rem;">
      <?= htmlspecialchars($_GET['msg']) ?>
    </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:2fr 1fr; gap:2.5rem;">

    <!-- Left: Order Info & Items -->
    <div>

      <!-- Customer & Shipping -->
      <div style="background:white; border-radius:12px; padding:1.8rem; margin-bottom:2rem; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
        <h2 style="font-size:1.5rem; margin-bottom:1.2rem;">Customer & Shipping</h2>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
          <div>
            <strong>Customer:</strong><br>
            <?= htmlspecialchars($order['full_name'] ?? 'Guest') ?><br>
            <small><?= htmlspecialchars($order['email']) ?></small><br>
            <small><?= htmlspecialchars($order['phone'] ?? 'No phone') ?></small>
          </div>
          <div>
            <strong>Shipping Address:</strong><br>
            <?= nl2br(htmlspecialchars($order['shipping_address'] ?? 'Not provided')) ?>
          </div>
        </div>

        <?php if ($order['order_notes']): ?>
          <div style="margin-top:1.5rem; padding:1rem; background:#f8f9fc; border-radius:8px;">
            <strong>Customer Notes:</strong><br>
            <?= nl2br(htmlspecialchars($order['order_notes'])) ?>
          </div>
        <?php endif; ?>

        <?php if ($order['tracking_number']): ?>
          <div style="margin-top:1.2rem; font-weight:600; color:var(--success);">
            Tracking: <?= htmlspecialchars($order['tracking_number']) ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Order Items -->
      <div style="background:white; border-radius:12px; padding:1.8rem; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
        <h2 style="font-size:1.5rem; margin-bottom:1.2rem;">Order Items</h2>

        <?php foreach ($items as $item): ?>
          <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:1rem 0;
            border-bottom:1px solid var(--border);
          ">
            <div style="flex:1;">
              <strong><?= htmlspecialchars($item['name']) ?></strong><br>
              <small>Qty: <?= $item['quantity'] ?></small>
            </div>
            <div style="font-weight:600; color:#ef4444;">
              ₦<?= number_format($item['price_at_time'] * $item['quantity']) ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div style="margin-top:1.5rem; text-align:right;">
          <strong style="font-size:1.4rem;">
            Total: ₦<?= number_format($order['total_amount']) ?>
          </strong>
        </div>
      </div>
    </div>

    <!-- Right: Actions & Status -->
    <aside style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); height:fit-content; position:sticky; top:2rem;">

      <h2 style="font-size:1.5rem; margin-bottom:1.5rem;">Order Actions</h2>

      <form method="post">
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Current Status</label>
          <select name="status" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
            <?php foreach (['pending','paid','processing','shipped','delivered','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                <?= ucfirst($s) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Tracking Number (optional)</label>
          <input type="text" name="tracking_number" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        </div>

        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Admin Note (internal)</label>
          <textarea name="admin_note" rows="3" placeholder="Add internal note or reason for change..." style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;"></textarea>
        </div>

        <button type="submit" style="
          width:100%;
          padding:1.1rem;
          background:var(--primary);
          color:white;
          border:none;
          border-radius:10px;
          font-size:1.1rem;
          font-weight:600;
          cursor:pointer;
        ">
          Update Order
        </button>
      </form>

      <!-- Danger zone -->
      <div style="margin-top:2.5rem; padding-top:1.5rem; border-top:1px solid #fee2e2;">
        <h3 style="color:var(--danger); margin-bottom:1rem;">Danger Zone</h3>
        <button onclick="if(confirm('Cancel this order? This cannot be undone.')) window.location='?id=<?= $order_id ?>&cancel=1'" style="
          width:100%;
          padding:0.9rem;
          background:#dc2626;
          color:white;
          border:none;
          border-radius:8px;
          cursor:pointer;
        ">
          Cancel Order
        </button>
      </div>

    </aside>

  </div>

</main>

<?php require_once '../includes/footer.php'; ?>