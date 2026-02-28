<?php
// admin/logs.php - System Logs Management

$page_title = "System Logs";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize activity log table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            user_name VARCHAR(255),
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_method VARCHAR(10),
            request_url VARCHAR(500),
            referer VARCHAR(500),
            level ENUM('info','warning','error','critical') DEFAULT 'info',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_level (level),
            INDEX idx_created (created_at),
            INDEX idx_ip (ip_address)
        )
    ");
} catch (Exception $e) {
    error_log("Activity logs table error: " . $e->getMessage());
}

// Handle actions
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Filters
$level_filter = $_GET['level'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Handle log deletion
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "Log entry deleted successfully";
    } catch (Exception $e) {
        $error_msg = "Error deleting log: " . $e->getMessage();
    }
    header("Location: logs.php?success=" . urlencode($success_msg));
    exit;
}

// Handle bulk delete
if ($action === 'bulk_delete' && isset($_POST['selected'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $selected = $_POST['selected'];
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        
        try {
            $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
            $stmt->execute($selected);
            $success_msg = count($selected) . " log entries deleted successfully";
        } catch (Exception $e) {
            $error_msg = "Error deleting logs: " . $e->getMessage();
        }
    }
    header("Location: logs.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle clear all logs
if ($action === 'clear_all' && isset($_GET['confirm'])) {
    if ($_GET['confirm'] === 'yes') {
        try {
            // Option 1: Truncate table (faster)
            $pdo->exec("TRUNCATE TABLE activity_logs");
            
            // Option 2: Delete with condition (if you want to keep recent)
            // $pdo->exec("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            
            $success_msg = "All logs cleared successfully";
            
            // Log this action
            logActivity($_SESSION['user_id'], "Cleared all system logs", 'warning');
        } catch (Exception $e) {
            $error_msg = "Error clearing logs: " . $e->getMessage();
        }
    }
    header("Location: logs.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle export
if ($action === 'export') {
    $format = $_GET['format'] ?? 'csv';
    
    // Build query for export
    $where = [];
    $params = [];
    
    if (!empty($level_filter)) {
        $where[] = "level = ?";
        $params[] = $level_filter;
    }
    
    if (!empty($user_filter)) {
        $where[] = "user_id = ?";
        $params[] = $user_filter;
    }
    
    if (!empty($date_from)) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $date_to;
    }
    
    if (!empty($search)) {
        $where[] = "(action LIKE ? OR details LIKE ? OR ip_address LIKE ? OR user_name LIKE ?)";
        $search_term = "%$search%";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
    }
    
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'csv') {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'User', 'Action', 'Details', 'Level', 'IP Address', 'Date', 'Request URL', 'Method']);
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_name'] ?? 'System',
                $log['action'],
                $log['details'],
                $log['level'],
                $log['ip_address'],
                $log['created_at'],
                $log['request_url'],
                $log['request_method']
            ]);
        }
        
        fclose($output);
        exit;
        
    } elseif ($format === 'json') {
        // Export as JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d_H-i-s') . '.json"');
        
        echo json_encode($logs, JSON_PRETTY_PRINT);
        exit;
    }
}

// Build search query
$where = [];
$params = [];

if (!empty($level_filter)) {
    $where[] = "level = ?";
    $params[] = $level_filter;
}

if (!empty($user_filter)) {
    $where[] = "user_id = ?";
    $params[] = $user_filter;
}

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where[] = "(action LIKE ? OR details LIKE ? OR ip_address LIKE ? OR user_name LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM activity_logs $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Fetch logs
$sql = "
    SELECT * FROM activity_logs 
    $where_sql 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
    'today' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'week' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'month' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'info' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE level = 'info'")->fetchColumn(),
    'warning' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE level = 'warning'")->fetchColumn(),
    'error' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE level = 'error'")->fetchColumn(),
    'critical' => $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE level = 'critical'")->fetchColumn(),
    'unique_ips' => $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM activity_logs")->fetchColumn(),
    'unique_users' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL")->fetchColumn(),
];

// Get users for filter
$users = $pdo->query("SELECT DISTINCT user_id, user_name FROM activity_logs WHERE user_id IS NOT NULL ORDER BY user_name")->fetchAll();

// Get chart data (last 7 days)
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $chart_data[] = [
        'date' => date('M d', strtotime($date)),
        'count' => $stmt->fetchColumn()
    ];
}

// Get level distribution
$level_data = [];
$level_colors = [
    'info' => '#3b82f6',
    'warning' => '#f59e0b',
    'error' => '#ef4444',
    'critical' => '#dc2626'
];

foreach (['info', 'warning', 'error', 'critical'] as $level) {
    $level_data[] = [
        'level' => $level,
        'count' => $stats[$level],
        'color' => $level_colors[$level]
    ];
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper function to log activity
function logActivity($user_id, $action, $level = 'info', $details = null) {
    global $pdo;
    
    try {
        // Get user name
        $user_name = null;
        if ($user_id) {
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_name = $stmt->fetchColumn();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, details, level, ip_address, user_agent, request_method, request_url, referer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $user_name,
            $action,
            $details,
            $level,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (Exception $e) {
        // Silently fail
    }
}
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-history"></i> System Logs
            </h1>
            <p style="color: var(--admin-gray);">View and monitor system activity and errors</p>
        </div>
        <div>
            <a href="?action=export&format=csv&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
               class="btn btn-secondary">
                <i class="fas fa-download"></i> Export CSV
            </a>
            <a href="?action=export&format=json&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
               class="btn btn-secondary">
                <i class="fas fa-file-code"></i> Export JSON
            </a>
        </div>
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
                    <div class="stat-label">Total Events</div>
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

    <!-- Charts Row -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <!-- Activity Chart -->
        <div class="card">
            <h3 style="margin-bottom: 1.5rem;">Activity (Last 7 Days)</h3>
            <div style="height: 200px;" id="activityChart"></div>
        </div>

        <!-- Level Distribution -->
        <div class="card">
            <h3 style="margin-bottom: 1.5rem;">Log Levels</h3>
            <div style="height: 200px;" id="levelChart"></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 1rem;">
                <?php foreach ($level_data as $level): ?>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 12px; height: 12px; border-radius: 3px; background: <?= $level['color'] ?>;"></div>
                        <span style="font-size: 0.875rem; color: var(--admin-dark);">
                            <?= ucfirst($level['level']) ?>: <?= number_format($level['count']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom: 2rem;">
        <form method="get" id="filterForm">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search action, details, IP..." class="form-control">
                </div>

                <div>
                    <label class="form-label">Level</label>
                    <select name="level" class="form-control">
                        <option value="">All Levels</option>
                        <option value="info" <?= $level_filter === 'info' ? 'selected' : '' ?>>Info</option>
                        <option value="warning" <?= $level_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
                        <option value="error" <?= $level_filter === 'error' ? 'selected' : '' ?>>Error</option>
                        <option value="critical" <?= $level_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">User</label>
                    <select name="user" class="form-control">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['user_id'] ?>" <?= $user_filter == $user['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['user_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
                </div>

                <div>
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="logs.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <!-- Bulk Actions -->
        <form method="post" action="?action=bulk_delete" id="bulkForm" onsubmit="return confirmBulkDelete()">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <div>
                    <span id="selectedCount">0</span> entries selected
                </div>
                <div>
                    <button type="submit" class="btn btn-danger" id="deleteSelected" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <a href="?action=clear_all&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to clear ALL logs? This action cannot be undone.')">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </a>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="selectAll"></th>
                            <th>Time</th>
                            <th>Level</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-history" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                    No log entries found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected[]" value="<?= $log['id'] ?>" class="log-checkbox">
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="level-badge level-<?= $log['level'] ?>">
                                            <?= ucfirst($log['level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($log['user_name'] ?? 'System') ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($log['action']) ?></strong>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px; max-height: 60px; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($log['details'] ?? '-') ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-secondary btn-sm" 
                                                    onclick="showLogDetails(<?= htmlspecialchars(json_encode($log)) ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?action=delete&id=<?= $log['id'] ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Delete this log entry?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=1&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-double-left"></i></a>
                    <a href="?page=<?= $page - 1 ?>&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-right"></i></a>
                    <a href="?page=<?= $total_pages ?>&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                       class="page-link"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Log Details Modal -->
    <div class="modal" id="logModal" style="display: none;">
        <div class="modal-content" style="max-width: 600px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Log Entry Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div id="logDetails"></div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.level-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.level-info {
    background: #dbeafe;
    color: #1e40af;
}

.level-warning {
    background: #fef3c7;
    color: #92400e;
}

.level-error {
    background: #fee2e2;
    color: #991b1b;
}

.level-critical {
    background: #7f1d1d;
    color: white;
}

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--admin-gray);
}

.modal-close:hover {
    color: var(--admin-danger);
}

.detail-row {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.detail-label {
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
}

.detail-value {
    color: var(--admin-gray);
    word-break: break-word;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initActivityChart();
    initLevelChart();

    // Select All functionality
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.log-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelected = document.getElementById('deleteSelected');
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.log-checkbox:checked').length;
        selectedCount.textContent = checked;
        deleteSelected.disabled = checked === 0;
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
});

function initActivityChart() {
    const ctx = document.createElement('canvas');
    document.getElementById('activityChart').appendChild(ctx);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($chart_data, 'date')) ?>,
            datasets: [{
                label: 'Activity',
                data: <?= json_encode(array_column($chart_data, 'count')) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
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
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

function initLevelChart() {
    const ctx = document.createElement('canvas');
    document.getElementById('levelChart').appendChild(ctx);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($level_data, 'level')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($level_data, 'count')) ?>,
                backgroundColor: <?= json_encode(array_column($level_data, 'color')) ?>,
                borderWidth: 0
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
            cutout: '60%'
        }
    });
}

function showLogDetails(log) {
    const modal = document.getElementById('logModal');
    const details = document.getElementById('logDetails');
    
    details.innerHTML = `
        <div class="detail-row">
            <div class="detail-label">ID</div>
            <div class="detail-value">${log.id}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Time</div>
            <div class="detail-value">${log.created_at}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Level</div>
            <div class="detail-value"><span class="level-badge level-${log.level}">${log.level.toUpperCase()}</span></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">User</div>
            <div class="detail-value">${log.user_name || 'System'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Action</div>
            <div class="detail-value">${log.action}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Details</div>
            <div class="detail-value">${log.details || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">IP Address</div>
            <div class="detail-value">${log.ip_address || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Request Method</div>
            <div class="detail-value">${log.request_method || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Request URL</div>
            <div class="detail-value">${log.request_url || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Referer</div>
            <div class="detail-value">${log.referer || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">User Agent</div>
            <div class="detail-value" style="font-size: 0.875rem;">${log.user_agent || '-'}</div>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('logModal').style.display = 'none';
}

function confirmBulkDelete() {
    const count = document.querySelectorAll('.log-checkbox:checked').length;
    if (count === 0) {
        alert('No log entries selected');
        return false;
    }
    return confirm(`Delete ${count} log entries?`);
}

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
