<?php
// admin/dashboard.php - Admin Dashboard

$page_title = "Admin Dashboard";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php'; // Use admin header instead of regular header

// Admin only
require_admin();

// Fetch today's date range
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');

// Fetch yesterday's date range
$yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day'));
$yesterday_end = date('Y-m-d 23:59:59', strtotime('-1 day'));

// Fetch current month range
$month_start = date('Y-m-01 00:00:00');
$month_end = date('Y-m-t 23:59:59');

// Total Sales
$total_sales = $pdo->query("
    SELECT COALESCE(SUM(total_amount), 0) 
    FROM orders 
    WHERE status IN ('paid','processing','shipped','delivered','completed')
")->fetchColumn();

// Today's Sales
$today_sales = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) 
    FROM orders 
    WHERE status IN ('paid','processing','shipped','delivered','completed')
    AND created_at BETWEEN ? AND ?
");
$today_sales->execute([$today_start, $today_end]);
$today_sales = $today_sales->fetchColumn();

// Yesterday's Sales
$yesterday_sales = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) 
    FROM orders 
    WHERE status IN ('paid','processing','shipped','delivered','completed')
    AND created_at BETWEEN ? AND ?
");
$yesterday_sales->execute([$yesterday_start, $yesterday_end]);
$yesterday_sales = $yesterday_sales->fetchColumn();

// Monthly Sales
$monthly_sales = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) 
    FROM orders 
    WHERE status IN ('paid','processing','shipped','delivered','completed')
    AND created_at BETWEEN ? AND ?
");
$monthly_sales->execute([$month_start, $month_end]);
$monthly_sales = $monthly_sales->fetchColumn();

// Total Orders
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?? 0;

// Today's Orders
$today_orders = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
");
$today_orders->execute([$today_start, $today_end]);
$today_orders = $today_orders->fetchColumn();

// Pending Orders
$pending_orders = $pdo->query("
    SELECT COUNT(*) 
    FROM orders 
    WHERE status IN ('pending', 'processing')
")->fetchColumn() ?? 0;

// Total Products
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?? 0;

// Low Stock Products
$low_stock = $pdo->query("
    SELECT COUNT(*) 
    FROM products 
    WHERE stock < 10 AND stock > 0
")->fetchColumn() ?? 0;

// Out of Stock Products
$out_of_stock = $pdo->query("
    SELECT COUNT(*) 
    FROM products 
    WHERE stock = 0
")->fetchColumn() ?? 0;

// Total Customers
$total_customers = $pdo->query("
    SELECT COUNT(*) 
    FROM users 
    WHERE role = 'customer'
")->fetchColumn() ?? 0;

// New Customers (last 7 days)
$new_customers = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users 
    WHERE role = 'customer' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$new_customers->execute();
$new_customers = $new_customers->fetchColumn();

// Recent Orders (last 10)
$stmt = $pdo->prepare("
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at, 
           u.full_name, u.email, o.payment_method
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Products
$top_products = $pdo->query("
    SELECT p.id, p.name, p.price, p.discount_price,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS image,
           COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

// Recent Customers
$recent_customers = $pdo->query("
    SELECT id, full_name, email, created_at, phone
    FROM users 
    WHERE role = 'customer'
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();

// Sales Chart Data (Last 7 days)
$sales_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_start = $date . ' 00:00:00';
    $date_end = $date . ' 23:59:59';
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM orders 
        WHERE status IN ('paid','processing','shipped','delivered','completed')
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$date_start, $date_end]);
    $sales = $stmt->fetchColumn();
    
    $sales_data[] = [
        'date' => date('M d', strtotime($date)),
        'sales' => (float)$sales
    ];
}

// Order Status Distribution
$status_distribution = $pdo->query("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status
    ORDER BY count DESC
")->fetchAll();
?>

<div class="admin-main">
    
    <!-- Welcome Alert -->
    <div class="alert alert-success">
        <i class="fas fa-hand-wave"></i>
        Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>! Here's what's happening with your store today.
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value">₦<?= number_format($total_sales, 2) ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
            <div class="stat-change <?= $today_sales > $yesterday_sales ? 'positive' : 'negative' ?>">
                <i class="fas fa-<?= $today_sales > $yesterday_sales ? 'arrow-up' : 'arrow-down' ?>"></i>
                <?php 
                if ($yesterday_sales > 0) {
                    $change = (($today_sales - $yesterday_sales) / $yesterday_sales) * 100;
                    echo abs(round($change, 1)) . '% from yesterday';
                } else {
                    echo 'No comparison data';
                }
                ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($total_orders) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-change <?= $today_orders > 0 ? 'positive' : '' ?>">
                <i class="fas fa-arrow-up"></i>
                <?= number_format($today_orders) ?> today
            </div>
            <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--admin-warning);">
                <i class="fas fa-clock"></i> <?= number_format($pending_orders) ?> pending
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($total_products) ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="stat-change negative">
                <i class="fas fa-exclamation-triangle"></i>
                <?= number_format($low_stock) ?> low stock
            </div>
            <div style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--admin-danger);">
                <i class="fas fa-times-circle"></i> <?= number_format($out_of_stock) ?> out of stock
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($total_customers) ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            <div class="stat-change positive">
                <i class="fas fa-user-plus"></i>
                <?= number_format($new_customers) ?> new this week
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions" style="margin-bottom: 2rem;">
        <a href="products.php?action=add" class="action-btn">
            <i class="fas fa-plus-circle"></i>
            <span>Add New Product</span>
        </a>
        <a href="orders.php" class="action-btn">
            <i class="fas fa-clipboard-list"></i>
            <span>Manage Orders</span>
        </a>
        <a href="inventory.php" class="action-btn">
            <i class="fas fa-warehouse"></i>
            <span>Check Inventory</span>
        </a>
        <a href="customers.php" class="action-btn">
            <i class="fas fa-user-friends"></i>
            <span>View Customers</span>
        </a>
        <a href="analytics.php" class="action-btn">
            <i class="fas fa-chart-line"></i>
            <span>View Analytics</span>
        </a>
        <a href="settings.php" class="action-btn">
            <i class="fas fa-cogs"></i>
            <span>Store Settings</span>
        </a>
    </div>

    <!-- Main Content Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        
        <!-- Left Column: Recent Orders & Sales Chart -->
        <div>
            <!-- Recent Orders -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="margin: 0; color: var(--admin-dark);">
                        <i class="fas fa-shopping-bag"></i> Recent Orders
                    </h2>
                    <a href="orders.php" style="color: var(--admin-primary); text-decoration: none; font-weight: 600;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($recent_orders)): ?>
                    <div style="padding: 2rem; text-align: center; color: var(--admin-gray);">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>No orders yet</h3>
                        <p>Start selling to see orders here</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--admin-light);">
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Order #</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Customer</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Amount</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Status</th>
                                    <th style="padding: 1rem; text-align: left; font-weight: 600;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <?php
                                        $status_colors = [
                                            'pending'    => 'warning',
                                            'paid'       => 'success',
                                            'processing' => 'primary',
                                            'shipped'    => 'info',
                                            'delivered'  => 'success',
                                            'completed'  => 'success',
                                            'cancelled'  => 'danger'
                                        ];
                                        
                                        $status_color = $status_colors[$order['status']] ?? 'secondary';
                                        $status_class = "status-{$status_color}";
                                    ?>
                                    <tr style="border-bottom: 1px solid var(--admin-border);">
                                        <td style="padding: 1rem; font-weight: 600;">
                                            <a href="order-detail.php?id=<?= $order['id'] ?>" 
                                               style="color: var(--admin-primary); text-decoration: none;">
                                                #<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?>
                                            </a>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 500;"><?= htmlspecialchars($order['full_name'] ?? 'Guest') ?></div>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                                <?= htmlspecialchars($order['email'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; font-weight: 700;">
                                            ₦<?= number_format($order['total_amount'], 2) ?>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span class="status-badge <?= $status_class ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; color: var(--admin-gray);">
                                            <?= date('M d, h:i A', strtotime($order['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sales Chart -->
            <div class="card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-chart-line"></i> Sales Overview (Last 7 Days)
                </h2>
                <div id="salesChart" style="height: 300px; position: relative;">
                    <canvas id="salesChartCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Right Column: Top Products & Recent Customers -->
        <div>
            <!-- Top Selling Products -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-fire"></i> Top Selling Products
                </h2>
                
                <?php if (empty($top_products)): ?>
                    <div style="padding: 1.5rem; text-align: center; color: var(--admin-gray);">
                        <i class="fas fa-box-open" style="font-size: 2rem;"></i>
                        <p>No sales data yet</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($top_products as $product): ?>
                            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border-radius: 8px; background: var(--admin-light);">
                                <div style="width: 50px; height: 50px; border-radius: 6px; overflow: hidden; background: white; border: 1px solid var(--admin-border);">
                                    <?php if ($product['image']): ?>
                                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--admin-gray);">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($product['name']) ?></div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem;">
                                        <span style="font-size: 0.875rem; color: var(--admin-gray);">
                                            Sold: <?= number_format($product['total_sold']) ?>
                                        </span>
                                        <span style="font-weight: 700; color: var(--admin-primary);">
                                            ₦<?= number_format($product['discount_price'] ?? $product['price'], 2) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="products.php" style="color: var(--admin-primary); text-decoration: none; font-weight: 600;">
                            View All Products <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Customers -->
            <div class="card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-user-plus"></i> Recent Customers
                </h2>
                
                <?php if (empty($recent_customers)): ?>
                    <div style="padding: 1.5rem; text-align: center; color: var(--admin-gray);">
                        <i class="fas fa-users" style="font-size: 2rem;"></i>
                        <p>No customers yet</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($recent_customers as $customer): ?>
                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 8px; background: var(--admin-light);">
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                    <?= strtoupper(substr($customer['full_name'] ?? 'C', 0, 1)) ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($customer['full_name']) ?></div>
                                    <div style="font-size: 0.875rem; color: var(--admin-gray);"><?= htmlspecialchars($customer['email']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--admin-gray); margin-top: 0.25rem;">
                                        Joined <?= date('M d', strtotime($customer['created_at'])) ?>
                                    </div>
                                </div>
                                <a href="customer-detail.php?id=<?= $customer['id'] ?>" 
                                   style="color: var(--admin-primary); text-decoration: none; font-size: 1.25rem;">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <a href="customers.php" style="color: var(--admin-primary); text-decoration: none; font-weight: 600;">
                            View All Customers <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Status Distribution -->
            <div class="card" style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-chart-pie"></i> Order Status
                </h2>
                
                <?php if (empty($status_distribution)): ?>
                    <div style="padding: 1.5rem; text-align: center; color: var(--admin-gray);">
                        <i class="fas fa-chart-pie" style="font-size: 2rem;"></i>
                        <p>No order data yet</p>
                    </div>
                <?php else: ?>
                    <div id="statusChart" style="height: 200px; position: relative;">
                        <canvas id="statusChartCanvas"></canvas>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-top: 1rem;">
                        <?php foreach ($status_distribution as $status): ?>
                            <?php
                                $status_colors = [
                                    'pending'    => '#f59e0b',
                                    'paid'       => '#10b981',
                                    'processing' => '#3b82f6',
                                    'shipped'    => '#8b5cf6',
                                    'delivered'  => '#047857',
                                    'completed'  => '#047857',
                                    'cancelled'  => '#ef4444'
                                ];
                                
                                $color = $status_colors[$status['status']] ?? '#9ca3af';
                            ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem; border-radius: 6px; background: rgba(0,0,0,0.02);">
                                <div style="width: 12px; height: 12px; border-radius: 50%; background: <?= $color ?>;"></div>
                                <div style="font-size: 0.875rem; color: var(--admin-dark);">
                                    <?= ucfirst($status['status']) ?>
                                </div>
                                <div style="margin-left: auto; font-weight: 600; color: var(--admin-dark);">
                                    <?= number_format($status['count']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="card" style="margin-top: 2rem;">
        <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
            <i class="fas fa-info-circle"></i> System Information
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <div>
                <div style="font-size: 0.875rem; color: var(--admin-gray); margin-bottom: 0.25rem;">Server Time</div>
                <div style="font-weight: 600; color: var(--admin-dark);" id="serverTime">
                    Loading...
                </div>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: var(--admin-gray); margin-bottom: 0.25rem;">PHP Version</div>
                <div style="font-weight: 600; color: var(--admin-dark);"><?= phpversion() ?></div>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: var(--admin-gray); margin-bottom: 0.25rem;">Database</div>
                <div style="font-weight: 600; color: var(--admin-dark);">MySQL</div>
            </div>
            <div>
                <div style="font-size: 0.875rem; color: var(--admin-gray); margin-bottom: 0.25rem;">Store Uptime</div>
                <div style="font-weight: 600; color: var(--admin-dark);">
                    <?php
                    $uptime_file = '../data/uptime.txt';
                    if (file_exists($uptime_file)) {
                        $start_time = file_get_contents($uptime_file);
                        $uptime = time() - (int)$start_time;
                        $days = floor($uptime / 86400);
                        $hours = floor(($uptime % 86400) / 3600);
                        echo "{$days}d {$hours}h";
                    } else {
                        file_put_contents($uptime_file, time());
                        echo "Just started";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update server time
    function updateServerTime() {
        const now = new Date();
        const timeString = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
        document.getElementById('serverTime').textContent = timeString;
    }
    
    updateServerTime();
    setInterval(updateServerTime, 1000);

    // Sales Chart
    const salesCtx = document.getElementById('salesChartCanvas').getContext('2d');
    const salesData = <?= json_encode($sales_data) ?>;
    
    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: salesData.map(item => item.date),
            datasets: [{
                label: 'Sales (₦)',
                data: salesData.map(item => item.sales),
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₦' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        }
    });

    // Status Chart (Pie)
    const statusCtx = document.getElementById('statusChartCanvas').getContext('2d');
    const statusData = <?= json_encode($status_distribution) ?>;
    
    const statusColors = {
        'pending': '#f59e0b',
        'paid': '#10b981',
        'processing': '#3b82f6',
        'shipped': '#8b5cf6',
        'delivered': '#047857',
        'completed': '#047857',
        'cancelled': '#ef4444'
    };
    
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => ucfirst(item.status)),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: statusData.map(item => statusColors[item.status] || '#9ca3af'),
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '70%'
        }
    });

    // Helper function
    function ucfirst(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    // Auto-refresh dashboard every 60 seconds
    let refreshTimer = setTimeout(() => {
        window.location.reload();
    }, 60000);

    // Clear timer when leaving page
    window.addEventListener('beforeunload', () => {
        clearTimeout(refreshTimer);
    });

    // Keyboard shortcuts for dashboard
    document.addEventListener('keydown', function(e) {
        // Ctrl + R to refresh dashboard
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            window.location.reload();
        }
        
        // Ctrl + P to go to products
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.location.href = 'products.php';
        }
        
        // Ctrl + O to go to orders
        if (e.ctrlKey && e.key === 'o') {
            e.preventDefault();
            window.location.href = 'orders.php';
        }
    });

    // Show notification if there are low stock products
    <?php if ($low_stock > 0): ?>
    setTimeout(() => {
        showToast(`You have ${<?= $low_stock ?>} products with low stock. Check inventory soon.`, 'warning');
    }, 2000);
    <?php endif; ?>

    <?php if ($pending_orders > 0): ?>
    setTimeout(() => {
        showToast(`You have ${<?= $pending_orders ?>} pending orders requiring attention.`, 'info');
    }, 4000);
    <?php endif; ?>
});
</script>
