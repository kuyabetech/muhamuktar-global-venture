<?php
// admin/analytics.php - Analytics Dashboard

$page_title = "Analytics";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php';

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
    $dates[] = $start->format('M d');
    $totals[] = 0;
    $start->modify('+1 day');
}

foreach ($sales_data as $row) {
    $key = array_search(date('M d', strtotime($row['date'])), $dates);
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

<style>
.analytics-main {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1600px;
    margin: 0 auto;
}

/* Key Metrics */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: clamp(1rem, 2vw, 1.6rem);
    margin-bottom: 3rem;
}

.metric-card {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.8rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.metric-title {
    font-size: 1.05rem;
    color: #6b7280;
    margin-bottom: 0.6rem;
}

.metric-value {
    font-size: clamp(1.8rem, 5vw, 2.4rem);
    font-weight: 700;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(480px, 1fr));
    gap: clamp(1.5rem, 3vw, 2.5rem);
    margin-bottom: 3rem;
}

.chart-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.chart-card h3 {
    margin-bottom: 1.2rem;
    font-size: 1.25rem;
    color: var(--admin-dark);
}

.chart-container {
    position: relative;
    height: clamp(220px, 45vh, 340px);
}

/* Tables */
.responsive-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 100%;
}

.responsive-table th,
.responsive-table td {
    padding: clamp(0.75rem, 1.8vw, 1rem);
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.responsive-table th {
    background: #f8f9fc;
    font-weight: 600;
    white-space: nowrap;
}

/* Mobile: stacked layout for tables */
@media screen and (max-width: 768px) {
    .responsive-table thead {
        display: none;
    }

    .responsive-table tr {
        display: block;
        margin-bottom: 1.25rem;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }

    .responsive-table td {
        display: block;
        text-align: right;
        border: none;
        padding: 0.9rem 1.2rem;
        position: relative;
        border-bottom: 1px solid #e5e7eb;
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
        color: #6b7280;
        text-align: left;
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

/* Scroll behavior when needed */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scroll-behavior: smooth;
    margin: 0 -1rem;
    padding: 0 1rem;
}

/* Charts */
.chart-container canvas {
    width: 100% !important;
    height: 100% !important;
}

/* Breakpoints */
@media (max-width: 1024px) {
    .charts-grid,
    .metrics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .analytics-main {
        padding: 1.2rem 1.5rem;
    }
}

@media (max-width: 480px) {
    .analytics-main {
        padding: 1rem 1.2rem;
    }
}
</style>

<main class="analytics-main">

    <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 2rem;">Analytics Overview</h1>

    <!-- Key Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <h3 class="metric-title">Total Revenue</h3>
            <p class="metric-value">₦<?= number_format($total_revenue) ?></p>
        </div>

        <div class="metric-card">
            <h3 class="metric-title">Total Orders</h3>
            <p class="metric-value"><?= number_format($total_orders) ?></p>
        </div>

        <div class="metric-card">
            <h3 class="metric-title">Avg Order Value</h3>
            <p class="metric-value">₦<?= number_format($avg_order, 2) ?></p>
        </div>

        <div class="metric-card">
            <h3 class="metric-title">Customers</h3>
            <p class="metric-value"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn() ?? 0 ?></p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="charts-grid">
        <!-- Sales over last 30 days -->
        <div class="chart-card">
            <h3>Sales Last 30 Days</h3>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Order Status Breakdown -->
        <div class="chart-card">
            <h3>Order Status Distribution</h3>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Products & Customers -->
    <div class="charts-grid"> <!-- reuse same grid class for consistency -->

        <!-- Top 5 Products -->
        <div class="chart-card">
            <h3>Top 5 Products by Revenue</h3>
            <?php if (empty($top_products)): ?>
                <p style="text-align:center; color:#6b7280; padding:2rem 0;">No sales data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="responsive-table">
                        <thead>
                            <tr style="background:#f8f9fc;">
                                <th data-label="Product">Product</th>
                                <th data-label="Revenue">Revenue</th>
                                <th data-label="Qty Sold">Qty Sold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_products as $p): ?>
                                <tr>
                                    <td data-label="Product"><?= htmlspecialchars($p['name']) ?></td>
                                    <td data-label="Revenue" style="text-align:right; font-weight:600;">
                                        ₦<?= number_format($p['revenue']) ?>
                                    </td>
                                    <td data-label="Qty Sold" style="text-align:center;"><?= $p['qty'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top 5 Customers -->
        <div class="chart-card">
            <h3>Top 5 Customers by Spend</h3>
            <?php if (empty($top_customers)): ?>
                <p style="text-align:center; color:#6b7280; padding:2rem 0;">No customer data yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="responsive-table">
                        <thead>
                            <tr style="background:#f8f9fc;">
                                <th data-label="Customer">Customer</th>
                                <th data-label="Total Spent">Total Spent</th>
                                <th data-label="Orders">Orders</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_customers as $c): ?>
                                <tr>
                                    <td data-label="Customer"><?= htmlspecialchars($c['full_name'] ?: $c['email']) ?></td>
                                    <td data-label="Total Spent" style="text-align:right; font-weight:600;">
                                        ₦<?= number_format($c['total_spent']) ?>
                                    </td>
                                    <td data-label="Orders" style="text-align:center;"><?= $c['order_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

</main>

<!-- Chart.js -->
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
        maintainAspectRatio: false,
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
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>