<?php
// pages/track-order.php

$page_title = "Track Your Order";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Optional: require login or allow guest tracking via reference
require_login(); // or remove if you want guest tracking

$user_id = $_SESSION['user_id'] ?? 0;

$search_ref = trim($_GET['reference'] ?? $_POST['reference'] ?? '');

$order = null;
$items = [];

if ($search_ref) {
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.reference = ? AND o.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$search_ref, $user_id]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("
            SELECT oi.*, p.name
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $items = $itemStmt->fetchAll();
    }
}
?>

<main class="container" style="padding:2.5rem 0;">

  <h1 style="font-size:2.4rem; margin-bottom:2rem;">Track Your Order</h1>

  <div style="max-width:700px; margin:0 auto;">

    <!-- Search Form -->
    <form method="get" style="margin-bottom:3rem;">
      <div style="display:flex; gap:1rem;">
        <input type="text" name="reference" value="<?= htmlspecialchars($search_ref) ?>" placeholder="Enter your order reference (e.g. MGV-...)" required style="
          flex:1;
          padding:1rem;
          border:1px solid var(--border);
          border-radius:8px;
          font-size:1.1rem;
        ">
        <button type="submit" style="
          padding:1rem 2rem;
          background:var(--primary);
          color:white;
          border:none;
          border-radius:8px;
          font-weight:600;
          cursor:pointer;
        ">Track Order</button>
      </div>
    </form>

    <?php if ($search_ref && !$order): ?>
      <div style="
        background:#fee2e2;
        color:#991b1b;
        padding:1.5rem;
        border-radius:10px;
        text-align:center;
      ">
        No order found with reference <strong><?= htmlspecialchars($search_ref) ?></strong>.<br>
        Please check the number and try again.
      </div>
    <?php elseif ($order): ?>

      <!-- Order Summary -->
      <div style="background:white; border-radius:12px; padding:2rem; box-shadow:0 4px 16px rgba(0,0,0,0.08); margin-bottom:2rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <h2 style="margin:0; font-size:1.8rem;">Order #<?= htmlspecialchars($order['reference']) ?></h2>
          <span style="
            background: #d1fae5;
            color: #065f46;
            padding: 0.5rem 1.2rem;
            border-radius: 999px;
            font-weight: 600;
          ">
            <?= ucfirst($order['status']) ?>
          </span>
        </div>

        <div style="margin-bottom:1.5rem;">
          <strong>Placed on:</strong> <?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?><br>
          <strong>Total:</strong> ₦<?= number_format($order['total_amount']) ?>
        </div>

        <!-- Progress Bar -->
        <?php
          $steps = ['pending' => 0, 'paid' => 25, 'processing' => 50, 'shipped' => 75, 'delivered' => 100];
          $progress = $steps[$order['status']] ?? 0;
        ?>
        <div style="margin:2rem 0;">
          <div style="background:#e5e7eb; height:12px; border-radius:6px; overflow:hidden;">
            <div style="background:var(--primary); width:<?= $progress ?>%; height:100%; transition:width 0.6s;"></div>
          </div>
          <div style="display:flex; justify-content:space-between; margin-top:0.8rem; font-size:0.95rem; color:#6b7280;">
            <span>Order Placed</span>
            <span>Processing</span>
            <span>Shipped</span>
            <span>Delivered</span>
          </div>
        </div>

        <!-- Items -->
        <h3 style="margin:2rem 0 1rem;">Items in this order</h3>
        <?php foreach ($items as $item): ?>
          <div style="display:flex; justify-content:space-between; padding:1rem 0; border-bottom:1px solid var(--border);">
            <div>
              <strong><?= htmlspecialchars($item['name']) ?></strong><br>
              <small>Qty: <?= $item['quantity'] ?></small>
            </div>
            <div style="font-weight:600;">
              ₦<?= number_format($item['price_at_time'] * $item['quantity']) ?>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Shipping & Notes -->
        <div style="margin-top:2rem;">
          <strong>Shipping Address:</strong><br>
          <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
        </div>

        <?php if ($order['order_notes']): ?>
          <div style="margin-top:1.5rem;">
            <strong>Notes:</strong><br>
            <?= nl2br(htmlspecialchars($order['order_notes'])) ?>
          </div>
        <?php endif; ?>

        <?php if ($order['tracking_number']): ?>
          <div style="margin-top:1.5rem; font-weight:600; color:var(--success);">
            Tracking Number: <?= htmlspecialchars($order['tracking_number']) ?>
          </div>
        <?php endif; ?>
      </div>

    <?php endif; ?>

  </div>

</main>

<?php require_once '../includes/footer.php'; ?>