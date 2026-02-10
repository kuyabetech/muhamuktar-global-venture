<?php
// admin/analytics.php - Analytics Dashboard

$page_title = "Analytics";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Admin only
require_admin();

// Quick stats
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status IN ('paid','processing','shipped','delivered','completed')")->fetchColumn() ?? 0;
$total_orders  = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?? 0;
$avg_order     = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Last 30 days sales (daily totals)
$sales_stmt = $pdo->prepare("
    SELECT DATE(created_at) AS date, SUM(total_amount) AS daily_total
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$sales_stmt->execute();
$sales_data = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fill missing days (last 30)
$dates = [];
$totals = [];
$start = new DateTime('-29 days');
$end   = new DateTime();
while ($start <= $end) {
    $dates[] = $start->format('Y-m-d');
    $totals[] = 0;
    $start->modify('+1 day');
}

foreach ($sales_data as $row) {
    $key = array_search($row['date'], $dates);
    if ($key !== false) $totals[$key] = (float)$row['daily_total'];
}

// Top 5 products by revenue
$top_products = $pdo->query("
    SELECT p.name, SUM(oi.quantity * oi.price_at_time) AS revenue, SUM(oi.quantity) AS qty
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status IN ('paid','processing','shipped','delivered','completed')
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();

// Top 5 customers by spend
$top_customers = $pdo->query("
    SELECT u.full_name, u.email, SUM(o.total_amount) AS total_spent, COUNT(o.id) AS order_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.status IN ('paid','processing','shipped','delivered','completed')
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 5
")->fetchAll();

// Order status breakdown
$status_breakdown = $pdo->query("
    SELECT status, COUNT(*) AS count
    FROM orders
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<main style="margin-left:260px; padding:2rem;">

  <h1 style="font-size:2.3rem; margin-bottom:2rem;">Analytics Overview</h1>

  <!-- Key Metrics -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1.6rem; margin-bottom:3rem;">
    <div style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="font-size:1.1rem; color:#6b7280; margin-bottom:0.6rem;">Total Revenue</h3>
      <p style="font-size:2.2rem; font-weight:700; color:var(--primary);">₦<?= number_format($total_revenue) ?></p>
    </div>

    <div style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="font-size:1.1rem; color:#6b7280; margin-bottom:0.6rem;">Total Orders</h3>
      <p style="font-size:2.2rem; font-weight:700; color:var(--success);"><?= number_format($total_orders) ?></p>
    </div>

    <div style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="font-size:1.1rem; color:#6b7280; margin-bottom:0.6rem;">Average Order Value</h3>
      <p style="font-size:2.2rem; font-weight:700; color:#d97706;">₦<?= number_format($avg_order, 2) ?></p>
    </div>

    <div style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="font-size:1.1rem; color:#6b7280; margin-bottom:0.6rem;">Customers</h3>
      <p style="font-size:2.2rem; font-weight:700; color:var(--primary-light);">
        <?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn() ?? 0 ?>
      </p>
    </div>
  </div>

  <!-- Charts Row -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(500px,1fr)); gap:2rem; margin-bottom:3rem;">

    <!-- Sales over last 30 days -->
    <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="margin-bottom:1.2rem;">Sales Last 30 Days</h3>
      <canvas id="salesChart" height="200"></canvas>
    </div>

    <!-- Order Status Breakdown -->
    <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="margin-bottom:1.2rem;">Order Status Distribution</h3>
      <canvas id="statusChart" height="200"></canvas>
    </div>

  </div>

  <!-- Top Products & Customers -->
  <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(500px,1fr)); gap:2rem;">

    <!-- Top 5 Products -->
    <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="margin-bottom:1.2rem;">Top 5 Products by Revenue</h3>
      <?php if (empty($top_products)): ?>
        <p style="text-align:center; color:#6b7280; padding:2rem 0;">No sales data yet.</p>
      <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f8f9fc;">
              <th style="padding:1rem; text-align:left;">Product</th>
              <th style="padding:1rem; text-align:right;">Revenue</th>
              <th style="padding:1rem; text-align:center;">Qty Sold</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_products as $p): ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:1rem;"><?= htmlspecialchars($p['name']) ?></td>
                <td style="padding:1rem; text-align:right; font-weight:600;">₦<?= number_format($p['revenue']) ?></td>
                <td style="padding:1rem; text-align:center;"><?= $p['qty'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- Top 5 Customers -->
    <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <h3 style="margin-bottom:1.2rem;">Top 5 Customers by Spend</h3>
      <?php if (empty($top_customers)): ?>
        <p style="text-align:center; color:#6b7280; padding:2rem 0;">No customer data yet.</p>
      <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr style="background:#f8f9fc;">
              <th style="padding:1rem; text-align:left;">Customer</th>
              <th style="padding:1rem; text-align:right;">Total Spent</th>
              <th style="padding:1rem; text-align:center;">Orders</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top_customers as $c): ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:1rem;"><?= htmlspecialchars($c['full_name'] ?: $c['email']) ?></td>
                <td style="padding:1rem; text-align:right; font-weight:600;">₦<?= number_format($c['total_spent']) ?></td>
                <td style="padding:1rem; text-align:center;"><?= $c['order_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </div>

  <!-- Chart.js CDN & Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Sales Line Chart (last 30 days)
    new Chart(document.getElementById('salesChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
          label: 'Daily Sales (₦)',
          data: <?= json_encode($totals) ?>,
          borderColor: 'rgba(30,64,175,1)',
          backgroundColor: 'rgba(30,64,175,0.2)',
          tension: 0.3,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // Status Pie Chart
    new Chart(document.getElementById('statusChart'), {
      type: 'pie',
      data: {
        labels: <?= json_encode(array_map('ucfirst', array_keys($status_breakdown))) ?>,
        datasets: [{
          data: <?= json_encode(array_values($status_breakdown)) ?>,
          backgroundColor: [
            '#d97706', '#059669', '#1e40af', '#7c3aed', '#047857', '#047857', '#dc2626'
          ],
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  </script>

</main>

<?php require_once '../includes/footer.php'; ?>