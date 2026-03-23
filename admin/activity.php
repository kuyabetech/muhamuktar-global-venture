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

<style>
/* Base Admin Styles */
.admin-main {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Responsive Typography */
h1 { font-size: clamp(1.8rem, 4vw, 2rem); }
h2 { font-size: clamp(1.3rem, 3vw, 1.5rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.25rem); }

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.2;
}

.stat-label {
    color: #6b7280;
    font-size: 0.9rem;
    margin-top: 0.25rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.primary { background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; }
.stat-icon.success { background: linear-gradient(135deg, #10b981, #34d399); color: white; }
.stat-icon.warning { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; }
.stat-icon.info { background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; }

/* Card Component */
.card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
    transition: box-shadow 0.3s;
}

.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

@keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Form Elements */
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-control:hover {
    border-color: #9ca3af;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(75, 85, 99, 0.3);
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
    margin: 1rem 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    text-align: left;
    padding: 1rem;
    background: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    font-size: 0.85rem;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
}

td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    color: #1f2937;
    font-size: 0.9rem;
}

tr:hover {
    background: #f9fafb;
}

/* Browser Icons */
.fa-chrome, .fa-firefox, .fa-safari, .fa-edge, .fa-internet-explorer {
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #4b5563;
    text-decoration: none;
    transition: all 0.2s;
    min-width: 40px;
    text-align: center;
}

.page-link:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    transform: translateY(-2px);
}

.page-link.active {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

/* Summary Grid */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.summary-item {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 10px;
    transition: transform 0.2s;
}

.summary-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.summary-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.summary-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
}

/* Filter Section */
.filter-section {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 1rem;
    align-items: end;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .filter-actions .btn {
        flex: 1;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .card {
        padding: 1.25rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .table-responsive {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        border-radius: 0;
    }
    
    th, td {
        padding: 0.75rem;
    }
    
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .card {
        padding: 1rem;
        border-radius: 12px;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 1.25rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .form-label {
        font-size: 0.9rem;
    }
    
    .form-control {
        padding: 0.6rem 0.75rem;
    }
    
    .btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination {
        gap: 0.25rem;
    }
    
    .page-link {
        padding: 0.5rem 0.75rem;
        min-width: 36px;
        font-size: 0.85rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .stat-card, .card {
        background: #1f2937;
        border-color: #374151;
    }
    
    .stat-value {
        color: #f3f4f6;
    }
    
    .stat-label {
        color: #9ca3af;
    }
    
    .form-control {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    
    table {
        background: #1f2937;
    }
    
    th {
        background: #374151;
        color: #e5e7eb;
    }
    
    td {
        color: #d1d5db;
    }
    
    tr:hover {
        background: #374151;
    }
    
    .summary-item {
        background: #374151;
    }
    
    .summary-label {
        color: #9ca3af;
    }
    
    .summary-value {
        color: #f3f4f6;
    }
    
    .page-link {
        background: #374151;
        border-color: #4b5563;
        color: #d1d5db;
    }
    
    .page-link:hover {
        background: #4b5563;
    }
}

/* Print Styles */
@media print {
    .btn, .filter-section, .pagination {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #000;
    }
    
    table {
        border-collapse: collapse;
    }
    
    th, td {
        border: 1px solid #000;
    }
}
</style>

<div class="admin-main">
    
    <!-- Page Header -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="margin-bottom: 0.5rem;">
                <i class="fas fa-history"></i> My Activity Log
            </h1>
            <p style="color: #6b7280;">Track your personal activity and login history</p>
        </div>
        <a href="?export=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> <span class="hide-mobile">Export</span>
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
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
    <div class="filter-section">
        <form method="get" id="filterForm">
            <div class="filter-grid">
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

                <div class="filter-actions">
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
        <div class="table-responsive">
            <table>
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
                            <td colspan="5" class="empty-state" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-history" style="font-size: 3rem; color: #9ca3af; margin-bottom: 1rem; display: block;"></i>
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
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link" title="First Page"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link" title="Previous Page"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link" title="Next Page"><i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link" title="Last Page"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Card -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;">Activity Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Most Active Day</div>
                <div class="summary-value"><?= htmlspecialchars($summary['most_active_day'] ?? 'N/A') ?></div>
            </div>

            <div class="summary-item">
                <div class="summary-label">Most Common Action</div>
                <div class="summary-value"><?= htmlspecialchars($summary['most_common_action'] ?? 'N/A') ?></div>
            </div>

            <div class="summary-item">
                <div class="summary-label">First Activity</div>
                <div class="summary-value"><?= htmlspecialchars($summary['first_activity'] ?? 'N/A') ?></div>
            </div>

            <div class="summary-item">
                <div class="summary-label">Activity Streak</div>
                <div class="summary-value">
                    <?= isset($summary['streak']) ? $summary['streak'] . ' days (last 30)' : 'N/A' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make table responsive
    const tables = document.querySelectorAll('table');
    tables.forEach(table => {
        if (!table.parentNode.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    // Handle mobile view adjustments
    function handleMobileView() {
        const hideMobile = document.querySelectorAll('.hide-mobile');
        if (window.innerWidth <= 768) {
            hideMobile.forEach(el => el.style.display = 'none');
        } else {
            hideMobile.forEach(el => el.style.display = '');
        }
    }

    handleMobileView();
    
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(handleMobileView, 250);
    });

    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Auto-submit filter when date changes (optional)
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    });
});
</script>

