<?php
// admin/activity.php - Personal Activity Log

$page_title = "My Activity";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle actions
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Build search query
$where = ["user_id = ?"];
$params = [$user_id];

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where[] = "(action LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

$where_sql = "WHERE " . implode(" AND ", $where);

// Get total logs
$count_sql = "SELECT COUNT(*) FROM activity_logs $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = (int)$count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Fetch activity logs
$sql = "
    SELECT * FROM activity_logs
    $where_sql
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Statistics ---
$stats = [];

// Total activities
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['total'] = (int)$stmt->fetchColumn();

// Today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$user_id]);
$stats['today'] = (int)$stmt->fetchColumn();

// Last 7 days
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute([$user_id]);
$stats['week'] = (int)$stmt->fetchColumn();

// Last 30 days
$stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$user_id]);
$stats['month'] = (int)$stmt->fetchColumn();

// Unique IPs
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$stats['unique_ips'] = (int)$stmt->fetchColumn();

// --- Summary ---
$summary = [];

// Most Active Day
$stmt = $pdo->prepare("
    SELECT DATE(created_at) AS day, COUNT(*) AS total
    FROM activity_logs
    WHERE user_id = ?
    GROUP BY DATE(created_at)
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$summary['most_active_day'] = $row ? date('M d, Y', strtotime($row['day'])) . " ({$row['total']} activities)" : 'N/A';

// Most Common Action
$stmt = $pdo->prepare("
    SELECT action, COUNT(*) AS total
    FROM activity_logs
    WHERE user_id = ?
    GROUP BY action
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$summary['most_common_action'] = $row ? htmlspecialchars($row['action']) . " ({$row['total']} times)" : 'N/A';

// First Activity
$stmt = $pdo->prepare("SELECT MIN(created_at) FROM activity_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$first_date = $stmt->fetchColumn();
$summary['first_activity'] = $first_date ? date('M d, Y', strtotime($first_date)) : 'N/A';

// Activity Streak (last 30 days)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT DATE(created_at))
    FROM activity_logs
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute([$user_id]);
$summary['streak'] = (int)$stmt->fetchColumn();

require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-history"></i> My Activity Log
            </h1>
            <p style="color: var(--admin-gray);">Track your personal activity and login history</p>
        </div>
        <a href="?export=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['today']) ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['week']) ?></div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-calendar-week"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['unique_ips']) ?></div>
                    <div class="stat-label">Unique IPs</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-network-wired"></i></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 2rem;">
        <form method="get" id="filterForm">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div>
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search actions, details, IP..." class="form-control">
                </div>

                <div>
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
                </div>

                <div>
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="activity.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Activity Table -->
    <div class="card">
        <div style="overflow-x: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Device/Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-history" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                No activity found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($log['action']) ?></strong>
                                </td>
                                <td>
                                    <div style="max-width: 400px;">
                                        <?= htmlspecialchars($log['details'] ?? '-') ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                <td>
                                    <?php 
                                    $ua = $log['user_agent'] ?? '';
                                    if (strpos($ua, 'Chrome') !== false) echo '<i class="fab fa-chrome"></i> Chrome';
                                    elseif (strpos($ua, 'Firefox') !== false) echo '<i class="fab fa-firefox"></i> Firefox';
                                    elseif (strpos($ua, 'Safari') !== false) echo '<i class="fab fa-safari"></i> Safari';
                                    elseif (strpos($ua, 'Edge') !== false) echo '<i class="fab fa-edge"></i> Edge';
                                    elseif (strpos($ua, 'MSIE') !== false) echo '<i class="fas fa-internet-explorer"></i> IE';
                                    else echo '<i class="fas fa-question-circle"></i> Unknown';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=1&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Card -->
<div class="card" style="margin-top: 2rem;">
    <h3 style="margin-bottom: 1rem;">Activity Summary</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
        <div style="background: var(--admin-light); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: var(--admin-gray);">Most Active Day</div>
            <div style="font-size: 1.25rem; font-weight: 600;">
                <?= htmlspecialchars($summary['most_active_day'] ?? 'N/A') ?>
            </div>
        </div>

        <div style="background: var(--admin-light); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: var(--admin-gray);">Most Common Action</div>
            <div style="font-size: 1.25rem; font-weight: 600;">
                <?= htmlspecialchars($summary['most_common_action'] ?? 'N/A') ?>
            </div>
        </div>

        <div style="background: var(--admin-light); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: var(--admin-gray);">First Activity</div>
            <div style="font-size: 1.25rem; font-weight: 600;">
                <?= htmlspecialchars($summary['first_activity'] ?? 'N/A') ?>
            </div>
        </div>

        <div style="background: var(--admin-light); padding: 1rem; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: var(--admin-gray);">Activity Streak</div>
            <div style="font-size: 1.25rem; font-weight: 600;">
                <?= isset($summary['streak']) ? $summary['streak'] . ' days (last 30)' : 'N/A' ?>
            </div>
        </div>
    </div>
</div>
</div>

<style>
.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--admin-gray);
    cursor: pointer;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-border);
}

.tab-btn.active {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-primary);
}

.fa-chrome, .fa-firefox, .fa-safari, .fa-edge, .fa-internet-explorer {
    margin-right: 0.5rem;
}
</style>

