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

<style>
/* Base Admin Styles */
.admin-main {
    padding: 2rem;
    max-width: 1600px;
    margin: 0 auto;
}

/* Responsive Typography */
h1 { font-size: clamp(1.8rem, 4vw, 2rem); }
h2 { font-size: clamp(1.3rem, 3vw, 1.5rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.25rem); }

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.page-header h1 {
    margin-bottom: 0.5rem;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

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

/* Card Component */
.card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 2rem;
}

/* Charts Row */
.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.chart-container {
    height: 250px;
    position: relative;
}

/* Level Badges */
.level-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
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

/* Form Elements */
.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 1rem;
    margin-bottom: 1rem;
}

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

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Bulk Actions Bar */
.bulk-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.selected-info {
    background: #f3f4f6;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    color: #1f2937;
}

.selected-info span {
    font-weight: 700;
    color: #4f46e5;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
    margin: 1rem 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
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
    vertical-align: middle;
}

tr:hover {
    background: #f9fafb;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Checkbox */
.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4f46e5;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 2rem;
}

.pagination-links {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-link {
    padding: 0.75rem 1rem;
    background: white;
    color: #1f2937;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 45px;
    text-align: center;
    border: 2px solid #e5e7eb;
}

.page-link:hover {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.page-link.active {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border-color: #4f46e5;
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
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e5e7eb;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #ef4444;
}

/* Detail Rows */
.detail-row {
    padding: 0.75rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.detail-label {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.detail-value {
    color: #4b5563;
    word-break: break-word;
    font-size: 0.95rem;
}

/* Level Distribution */
.level-distribution {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin-top: 1rem;
}

.level-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border-radius: 8px;
    background: #f9fafb;
}

.level-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

.level-text {
    font-size: 0.85rem;
    color: #1f2937;
}

.level-count {
    margin-left: auto;
    font-weight: 600;
    color: #4f46e5;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #e5e7eb;
}

/* Responsive Breakpoints */
@media (max-width: 1200px) {
    .form-grid {
        grid-template-columns: 2fr 1fr 1fr;
    }
    
    .charts-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

@media (max-width: 1024px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .form-actions .btn {
        flex: 1;
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 1.25rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
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
    
    .card {
        padding: 1.25rem;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .selected-info {
        text-align: center;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
    
    .pagination-links {
        gap: 0.35rem;
    }
    
    .page-link {
        padding: 0.6rem 0.8rem;
        min-width: 38px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
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
    
    .modal-content {
        padding: 1.5rem;
    }
    
    .level-distribution {
        grid-template-columns: 1fr;
    }
    
    .pagination-links {
        gap: 0.25rem;
    }
    
    .page-link {
        padding: 0.5rem 0.7rem;
        min-width: 35px;
        font-size: 0.8rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .page-header, .stat-card, .card {
        background: #1f2937;
        border-color: #374151;
    }
    
    .stat-value {
        color: #f3f4f6;
    }
    
    .stat-label {
        color: #9ca3af;
    }
    
    .form-label {
        color: #e5e7eb;
    }
    
    .form-control {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    
    .form-control:focus {
        border-color: #818cf8;
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
    
    .selected-info {
        background: #374151;
        color: #e5e7eb;
    }
    
    .page-link {
        background: #374151;
        border-color: #4b5563;
        color: #d1d5db;
    }
    
    .page-link:hover {
        background: #4f46e5;
        color: white;
    }
    
    .modal-content {
        background: #1f2937;
        border-color: #374151;
    }
    
    .modal-header {
        border-bottom-color: #374151;
    }
    
    .detail-row {
        border-bottom-color: #374151;
    }
    
    .detail-label {
        color: #e5e7eb;
    }
    
    .detail-value {
        color: #9ca3af;
    }
    
    .level-item {
        background: #374151;
    }
    
    .level-text {
        color: #e5e7eb;
    }
}

/* Print Styles */
@media print {
    .btn, .action-buttons, .pagination, .modal {
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
    <div class="page-header">
        <div>
            <h1><i class="fas fa-history"></i> System Logs</h1>
            <p style="color: #6b7280; margin-top: 0.5rem;">View and monitor system activity and errors</p>
        </div>
        <div class="header-actions">
            <a href="?action=export&format=csv&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
               class="btn btn-secondary">
                <i class="fas fa-download"></i> CSV
            </a>
            <a href="?action=export&format=json&level=<?= urlencode($level_filter) ?>&user=<?= urlencode($user_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
               class="btn btn-secondary">
                <i class="fas fa-file-code"></i> JSON
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
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
    <div class="charts-row">
        <!-- Activity Chart -->
        <div class="card">
            <h3 style="margin-bottom: 1.5rem;">Activity (Last 7 Days)</h3>
            <div class="chart-container" id="activityChart"></div>
        </div>

        <!-- Level Distribution -->
        <div class="card">
            <h3 style="margin-bottom: 1.5rem;">Log Levels</h3>
            <div class="chart-container" id="levelChart"></div>
            <div class="level-distribution">
                <?php foreach ($level_data as $level): ?>
                    <div class="level-item">
                        <div class="level-color" style="background: <?= $level['color'] ?>;"></div>
                        <span class="level-text"><?= ucfirst($level['level']) ?></span>
                        <span class="level-count"><?= number_format($level['count']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <form method="get" id="filterForm">
            <div class="form-grid">
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
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
                </div>

                <div>
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
                </div>

                <div class="form-actions">
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
            
            <div class="bulk-actions">
                <div class="selected-info">
                    <span id="selectedCount">0</span> entries selected
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-danger" id="deleteSelected" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <a href="?action=clear_all&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to clear ALL logs? This action cannot be undone.')">
                        <i class="fas fa-trash-alt"></i> Clear All
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="selectAll" class="table-checkbox"></th>
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
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p>No log entries found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected[]" value="<?= $log['id'] ?>" class="log-checkbox table-checkbox">
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
            <div class="pagination">
                <div class="pagination-links">
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
            </div>
        <?php endif; ?>
    </div>

    <!-- Log Details Modal -->
    <div class="modal" id="logModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Log Entry Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div id="logDetails"></div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

function initActivityChart() {
    const container = document.getElementById('activityChart');
    if (!container) return;
    
    const ctx = document.createElement('canvas');
    container.appendChild(ctx);
    
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
                tension: 0.4,
                pointBackgroundColor: '#4f46e5',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 4
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
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#1f2937',
                    titleColor: '#f3f4f6',
                    bodyColor: '#d1d5db',
                    borderColor: '#374151',
                    borderWidth: 1
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            return Number.isInteger(value) ? value : null;
                        }
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
    const container = document.getElementById('levelChart');
    if (!container) return;
    
    const ctx = document.createElement('canvas');
    container.appendChild(ctx);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($level_data, 'level')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($level_data, 'count')) ?>,
                backgroundColor: <?= json_encode(array_column($level_data, 'color')) ?>,
                borderWidth: 0,
                hoverOffset: 4
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
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
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
            <div class="detail-value" style="word-break: break-all;">${log.request_url || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Referer</div>
            <div class="detail-value" style="word-break: break-all;">${log.referer || '-'}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">User Agent</div>
            <div class="detail-value" style="font-size: 0.875rem; word-break: break-all;">${log.user_agent || '-'}</div>
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

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.querySelector('input[name="search"]').focus();
    }
    
    // Ctrl/Cmd + A to select all (when not in input)
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = !selectAll.checked;
            const event = new Event('change');
            selectAll.dispatchEvent(event);
        }
    }
});
</script>