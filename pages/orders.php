<?php
// pages/orders.php - Customer Order History

$page_title = "My Orders";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Must be logged in
require_login();

$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.reference, o.total_amount, o.status, o.created_at,
               COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
    $error = "Could not load orders. Please try again later.";
}
?>

<main class="container" style="padding: 2.5rem 0; min-height: 70vh;">

  <h1 style="font-size: 2.3rem; margin-bottom: 2rem; color: #111827;">
    My Orders
  </h1>

  <?php if (isset($error)): ?>
    <div style="background:#fee2e2; color:#dc2626; padding:1.2rem; border-radius:10px; margin-bottom:2rem;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>

    <div style="
      text-align: center;
      padding: 6rem 1rem;
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    ">
      <i class="fas fa-box-open" style="font-size: 7rem; color: #d1d5db; margin-bottom: 1.5rem;"></i>
      <h2 style="font-size: 1.8rem; margin-bottom: 1rem; color: #4b5563;">
        No orders yet
      </h2>
      <p style="font-size: 1.1rem; color: #6b7280; margin-bottom: 2rem;">
        When you place an order, it will appear here.
      </p>
      <a href="<?= BASE_URL ?>pages/products.php" style="
        display: inline-block;
        background: var(--primary);
        color: white;
        padding: 1rem 2.2rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        font-size: 1.1rem;
      ">Start Shopping</a>
    </div>

  <?php else: ?>

    <div style="display: flex; flex-direction: column; gap: 1.6rem;">

      <?php foreach ($orders as $order): ?>
        <?php
          $status_text = ucfirst(str_replace('_', ' ', $order['status']));
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

        <div style="
          background: white;
          border-radius: 12px;
          box-shadow: 0 2px 10px rgba(0,0,0,0.06);
          overflow: hidden;
          transition: all 0.2s;
        " onmouseover="this.style.boxShadow='0 8px 20px rgba(0,0,0,0.1)'">

          <div style="
            padding: 1.2rem 1.6rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
          ">
            <div>
              <strong style="font-size: 1.15rem;">Order #<?= htmlspecialchars($order['reference']) ?></strong><br>
              <small style="color: #6b7280;">
                Placed on <?= date('M d, Y • h:i A', strtotime($order['created_at'])) ?>
              </small>
            </div>

            <div style="text-align:right;">
              <span style="
                background: <?= $status_color ?>;
                color: white;
                padding: 0.4rem 1rem;
                border-radius: 999px;
                font-size: 0.9rem;
                font-weight: 600;
              ">
                <?= $status_text ?>
              </span>
            </div>
          </div>

          <div style="padding: 1.4rem 1.6rem;">
            <div style="margin-bottom: 1rem;">
              <strong><?= $order['item_count'] ?> item<?= $order['item_count'] > 1 ? 's' : '' ?></strong>
              • Total: <span style="font-weight:700; color:#ef4444;">₦<?= number_format($order['total_amount']) ?></span>
            </div>

            <a href="<?= BASE_URL ?>pages/order-detail.php?id=<?= $order['id'] ?>" style="
              display: inline-block;
              background: var(--primary);
              color: white;
              padding: 0.7rem 1.5rem;
              border-radius: 8px;
              text-decoration: none;
              font-weight: 600;
              transition: 0.2s;
            " onmouseover="this.style.background=var(--primary-dark)">
              View Order Details
            </a>
          </div>

        </div>
      <?php endforeach; ?>

    </div>

  <?php endif; ?>

</main>

<?php require_once '../includes/footer.php'; ?>