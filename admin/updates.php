<?php
// admin/updates.php - System Updates Management

$page_title = "System Updates";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Initialize updates table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_updates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            version VARCHAR(50) NOT NULL,
            release_date DATE,
            description TEXT,
            type ENUM('major','minor','security','patch') DEFAULT 'patch',
            status ENUM('pending','installed','failed','rolled_back') DEFAULT 'pending',
            installed_at TIMESTAMP NULL,
            installed_by INT,
            backup_file VARCHAR(255),
            changelog TEXT,
            files_modified TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_version (version),
            INDEX idx_status (status)
        )
    ");
} catch (Exception $e) {
    error_log("Updates table error: " . $e->getMessage());
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle actions
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Current system version
$current_version = '1.0.0';

// Get current version from database or settings
$stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'system_version'");
$stmt->execute();
$db_version = $stmt->fetchColumn();
if ($db_version) {
    $current_version = $db_version;
}

// Check for updates (simulated - in production, this would ping your update server)
function checkForUpdates($current_version) {
    // Simulate checking for updates
    // In production, this would make an API call to your update server
    
    $updates_available = [];
    
    // Simulated available updates
    $available_updates = [
        [
            'version' => '1.1.0',
            'release_date' => '2024-02-15',
            'type' => 'minor',
            'description' => 'New features and improvements',
            'changelog' => [
                'Added product reviews system',
                'Improved search functionality',
                'Enhanced mobile responsiveness',
                'Fixed checkout bugs'
            ],
            'files_modified' => 15,
            'size' => '2.5 MB',
            'required' => false
        ],
        [
            'version' => '1.0.5',
            'release_date' => '2024-02-01',
            'type' => 'security',
            'description' => 'Security patches and bug fixes',
            'changelog' => [
                'Fixed XSS vulnerability in forms',
                'Updated payment gateway security',
                'Patched SQL injection risk',
                'Improved session handling'
            ],
            'files_modified' => 8,
            'size' => '1.2 MB',
            'required' => true
        ],
        [
            'version' => '1.0.3',
            'release_date' => '2024-01-20',
            'type' => 'patch',
            'description' => 'Bug fixes and performance improvements',
            'changelog' => [
                'Fixed cart calculation error',
                'Improved database query performance',
                'Fixed image upload issues',
                'Updated language files'
            ],
            'files_modified' => 5,
            'size' => '0.8 MB',
            'required' => false
        ]
    ];
    
    // Filter updates newer than current version
    foreach ($available_updates as $update) {
        if (version_compare($update['version'], $current_version, '>')) {
            $updates_available[] = $update;
        }
    }
    
    return $updates_available;
}

// Handle manual update check
if ($action === 'check') {
    $updates_available = checkForUpdates($current_version);
    
    if (empty($updates_available)) {
        $success_msg = "Your system is up to date! (v{$current_version})";
    } else {
        $success_msg = count($updates_available) . " update(s) available";
    }
    
    // Store in session for display
    $_SESSION['updates_available'] = $updates_available;
    
    header("Location: updates.php?success=" . urlencode($success_msg));
    exit;
}

// Handle update installation
if ($action === 'install' && isset($_POST['install_update'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $version = $_POST['version'] ?? '';
        $create_backup = isset($_POST['create_backup']);
        $maintenance_mode = isset($_POST['maintenance_mode']);
        
        try {
            // Enable maintenance mode if requested
            if ($maintenance_mode) {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('maintenance_mode', '1') ON DUPLICATE KEY UPDATE value = '1'");
                $stmt->execute();
            }
            
            // Create backup if requested
            if ($create_backup) {
                $backup_file = createSystemBackup();
            }
            
            // Simulate update process
            // In production, this would actually download and apply the update
            sleep(3); // Simulate processing
            
            // Log the update
            $stmt = $pdo->prepare("
                INSERT INTO system_updates (version, description, type, status, installed_at, installed_by, backup_file)
                VALUES (?, ?, ?, 'installed', NOW(), ?, ?)
            ");
            $stmt->execute([
                $version,
                "System updated to version {$version}",
                $_POST['update_type'] ?? 'patch',
                $_SESSION['user_id'],
                $backup_file ?? null
            ]);
            
            // Update system version
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('system_version', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$version, $version]);
            
            // Disable maintenance mode
            if ($maintenance_mode) {
                $stmt = $pdo->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
                $stmt->execute();
            }
            
            $success_msg = "Successfully updated to version {$version}!";
            
            // Log activity
            logActivity($_SESSION['user_id'], "System updated to version {$version}");
            
        } catch (Exception $e) {
            $error_msg = "Update failed: " . $e->getMessage();
            
            // Disable maintenance mode on failure
            if ($maintenance_mode) {
                $stmt = $pdo->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
                $stmt->execute();
            }
        }
    }
    header("Location: updates.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle rollback
if ($action === 'rollback' && $id > 0) {
    if (!hash_equals($csrf_token, $_GET['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        try {
            // Get update info
            $stmt = $pdo->prepare("SELECT * FROM system_updates WHERE id = ?");
            $stmt->execute([$id]);
            $update = $stmt->fetch();
            
            if ($update) {
                // Enable maintenance mode
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('maintenance_mode', '1') ON DUPLICATE KEY UPDATE value = '1'");
                $stmt->execute();
                
                // Simulate rollback process
                sleep(2);
                
                // Mark as rolled back
                $stmt = $pdo->prepare("UPDATE system_updates SET status = 'rolled_back' WHERE id = ?");
                $stmt->execute([$id]);
                
                // Revert version
                $prev_version = '1.0.0'; // Get previous version from history
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, value) VALUES ('system_version', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$prev_version, $prev_version]);
                
                // Disable maintenance mode
                $stmt = $pdo->prepare("UPDATE settings SET value = '0' WHERE `key` = 'maintenance_mode'");
                $stmt->execute();
                
                $success_msg = "Successfully rolled back update #{$id}";
                
                logActivity($_SESSION['user_id'], "Rolled back update #{$id}");
            }
        } catch (Exception $e) {
            $error_msg = "Rollback failed: " . $e->getMessage();
        }
    }
    header("Location: updates.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle clear update list
if ($action === 'clear_updates') {
    unset($_SESSION['updates_available']);
    header("Location: updates.php");
    exit;
}

// Get update history
$update_history = $pdo->query("
    SELECT u.*, us.full_name as installed_by_name
    FROM system_updates u
    LEFT JOIN users us ON u.installed_by = us.id
    ORDER BY u.installed_at DESC
    LIMIT 20
")->fetchAll();

// Get updates from session
$updates_available = $_SESSION['updates_available'] ?? [];

// System information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->query("SELECT VERSION()")->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_os' => php_uname('s') . ' ' . php_uname('r'),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Yes' : 'No',
    'curl_enabled' => function_exists('curl_version') ? 'Yes' : 'No',
    'zip_enabled' => class_exists('ZipArchive') ? 'Yes' : 'No',
    'gd_enabled' => extension_loaded('gd') ? 'Yes' : 'No'
];

// Helper function to create backup
function createSystemBackup() {
    global $backup_dir;
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . "pre_update_backup_{$timestamp}.zip";
    
    // In production, this would create a full system backup
    // For now, just return a simulated filename
    
    return basename($backup_file);
}

// Helper function to log activity
function logActivity($user_id, $action) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, ip_address, user_agent, created_at)
            SELECT ?, full_name, ?, ?, ?, NOW()
            FROM users WHERE id = ?
        ");
        $stmt->execute([
            $user_id,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $user_id
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
    max-width: 1400px;
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

/* Current Version Card */
.version-card {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 10px 30px rgba(79, 70, 229, 0.3);
}

.version-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.version-info h2 {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.version-number {
    font-size: clamp(2rem, 5vw, 3rem);
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.version-status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    backdrop-filter: blur(5px);
}

.version-status i {
    font-size: 1.5rem;
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

/* Card Component */
.card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

/* Update Type Badges */
.update-type {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.type-major {
    background: #dbeafe;
    color: #1e40af;
}

.type-minor {
    background: #d1fae5;
    color: #065f46;
}

.type-security {
    background: #fee2e2;
    color: #991b1b;
}

.type-patch {
    background: #f3f4f6;
    color: #374151;
}

/* Required Badge */
.required-badge {
    background: #ef4444;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-left: 0.5rem;
    display: inline-block;
}

/* Status Badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-installed {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-rolled_back {
    background: #f3f4f6;
    color: #374151;
}

/* Tabs */
.tabs-container {
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 2rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tabs-scroll {
    display: flex;
    gap: 0.5rem;
    padding: 0 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    font-size: 0.95rem;
}

.tab-btn:hover {
    color: #4f46e5;
    border-bottom-color: #e5e7eb;
}

.tab-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

.tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Update Card */
.update-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    border-left-width: 4px;
    transition: all 0.3s;
}

.update-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.update-card.required {
    border-left-color: #ef4444;
}

.update-card.optional {
    border-left-color: #4f46e5;
}

.update-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.update-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.update-title h2 {
    margin: 0;
    font-size: 1.3rem;
}

.update-meta {
    display: flex;
    gap: 1rem;
    color: #6b7280;
    font-size: 0.9rem;
    flex-wrap: wrap;
}

.update-meta i {
    margin-right: 0.25rem;
}

.update-description {
    margin-bottom: 1.5rem;
    color: #1f2937;
    line-height: 1.6;
}

.changelog-box {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.changelog-box h4 {
    margin-bottom: 0.75rem;
    color: #1f2937;
}

.changelog-box ul {
    margin: 0;
    padding-left: 1.5rem;
}

.changelog-box li {
    margin-bottom: 0.25rem;
    color: #4b5563;
}

.update-options {
    display: flex;
    gap: 2rem;
    align-items: center;
    flex-wrap: wrap;
}

.update-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.update-option input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #4f46e5;
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

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
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
    vertical-align: middle;
}

tr:hover {
    background: #f9fafb;
}

/* System Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.info-item {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 10px;
    transition: transform 0.2s;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.info-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    word-break: break-word;
}

/* Requirements Grid */
.requirements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-top: 1rem;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 8px;
    font-size: 0.9rem;
}

.requirement-item i {
    font-size: 1rem;
}

.requirement-item.passed i {
    color: #10b981;
}

.requirement-item.failed i {
    color: #ef4444;
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
@media (max-width: 1024px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .version-content {
        flex-direction: column;
        text-align: center;
    }
    
    .update-header {
        flex-direction: column;
    }
    
    .update-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
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
    
    .version-card {
        padding: 1.5rem;
    }
    
    .card {
        padding: 1.25rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .update-options {
        width: 100%;
    }
    
    .update-option {
        width: 100%;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .requirements-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs-scroll {
        padding-bottom: 0.25rem;
    }
    
    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    .table-responsive {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        border-radius: 0;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .version-number {
        font-size: 2rem;
    }
    
    .version-status {
        padding: 0.5rem 1rem;
    }
    
    .update-title h2 {
        font-size: 1.1rem;
    }
    
    .update-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .modal-content {
        padding: 1.5rem;
    }
    
    .info-item {
        padding: 0.75rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .page-header, .card, .update-card, .modal-content {
        background: #1f2937;
        border-color: #374151;
    }
    
    .info-item, .changelog-box {
        background: #374151;
    }
    
    .info-label {
        color: #9ca3af;
    }
    
    .info-value {
        color: #f3f4f6;
    }
    
    .update-description {
        color: #d1d5db;
    }
    
    .changelog-box li {
        color: #9ca3af;
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
    
    .requirement-item {
        background: #374151;
    }
    
    .tab-btn {
        color: #9ca3af;
    }
    
    .tab-btn:hover {
        color: #818cf8;
    }
    
    .tab-btn.active {
        color: #818cf8;
        border-bottom-color: #818cf8;
    }
}

/* Print Styles */
@media print {
    .btn, .header-actions, .update-options, .modal {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #000;
        page-break-inside: avoid;
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
            <h1><i class="fas fa-sync-alt"></i> System Updates</h1>
            <p style="color: #6b7280; margin-top: 0.5rem;">Manage system updates and view version history</p>
        </div>
        <div class="header-actions">
            <a href="?action=check" class="btn btn-primary">
                <i class="fas fa-search"></i> Check for Updates
            </a>
            <?php if (!empty($updates_available)): ?>
                <a href="?action=clear_updates" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear List
                </a>
            <?php endif; ?>
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

    <!-- Current Version Card -->
    <div class="version-card">
        <div class="version-content">
            <div class="version-info">
                <h2>Current Version</h2>
                <div class="version-number"><?= $current_version ?></div>
            </div>
            <div class="version-status">
                <?php if (empty($updates_available)): ?>
                    <i class="fas fa-check-circle"></i>
                    <span>System is up to date</span>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= count($updates_available) ?> update(s) available</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-scroll">
            <button type="button" class="tab-btn active" data-tab="available">Available Updates</button>
            <button type="button" class="tab-btn" data-tab="history">Update History</button>
            <button type="button" class="tab-btn" data-tab="system">System Info</button>
        </div>
    </div>

    <!-- Available Updates Tab -->
    <div class="tab-content active" id="availableTab">
        <?php if (empty($updates_available)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h2>No Updates Available</h2>
                <p style="margin-bottom: 2rem;">Your system is running the latest version (<?= $current_version ?>)</p>
                <a href="?action=check" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Check Again
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($updates_available as $update): ?>
                <div class="update-card <?= $update['required'] ? 'required' : 'optional' ?>">
                    <div class="update-header">
                        <div class="update-title">
                            <h2>Version <?= $update['version'] ?></h2>
                            <?php if ($update['required']): ?>
                                <span class="required-badge">Required</span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="showUpdateDetails(<?= htmlspecialchars(json_encode($update)) ?>)">
                            <i class="fas fa-info-circle"></i> View Details
                        </button>
                    </div>

                    <div class="update-meta">
                        <span><i class="fas fa-calendar"></i> Released: <?= $update['release_date'] ?></span>
                        <span><i class="fas fa-tag"></i> <?= ucfirst($update['type']) ?> Update</span>
                        <span><i class="fas fa-database"></i> Size: <?= $update['size'] ?></span>
                    </div>

                    <p class="update-description"><?= $update['description'] ?></p>

                    <div class="changelog-box">
                        <h4>Changelog:</h4>
                        <ul>
                            <?php foreach ($update['changelog'] as $item): ?>
                                <li><?= htmlspecialchars($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <form method="post" action="?action=install" onsubmit="return confirmUpdate(this)">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="install_update" value="1">
                        <input type="hidden" name="version" value="<?= $update['version'] ?>">
                        <input type="hidden" name="update_type" value="<?= $update['type'] ?>">

                        <div class="update-options">
                            <label class="update-option">
                                <input type="checkbox" name="create_backup" value="1" checked>
                                <span>Create backup before update</span>
                            </label>

                            <label class="update-option">
                                <input type="checkbox" name="maintenance_mode" value="1" checked>
                                <span>Enable maintenance mode during update</span>
                            </label>

                            <button type="submit" class="btn btn-<?= $update['required'] ? 'danger' : 'primary' ?>">
                                <i class="fas fa-download"></i> Install Update
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Update History Tab -->
    <div class="tab-content" id="historyTab" style="display: none;">
        <div class="card">
            <?php if (empty($update_history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No update history found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Installed</th>
                                <th>Installed By</th>
                                <th>Backup</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($update_history as $update): ?>
                                <tr>
                                    <td><strong>v<?= $update['version'] ?></strong></td>
                                    <td>
                                        <span class="update-type type-<?= $update['type'] ?>">
                                            <?= ucfirst($update['type']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $update['status'] ?>">
                                            <?= ucfirst($update['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $update['installed_at'] ? date('M d, Y H:i', strtotime($update['installed_at'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($update['installed_by_name'] ?? 'System') ?></td>
                                    <td>
                                        <?php if ($update['backup_file']): ?>
                                            <a href="../uploads/backups/<?= urlencode($update['backup_file']) ?>" 
                                               class="btn btn-secondary btn-sm">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($update['status'] === 'installed' && $update['version'] !== $current_version): ?>
                                            <a href="?action=rollback&id=<?= $update['id'] ?>&csrf_token=<?= $csrf_token ?>" 
                                               class="btn btn-warning btn-sm"
                                               onclick="return confirm('Rollback to version <?= $update['version'] ?>? This may cause data loss.')">
                                                <i class="fas fa-undo"></i> Rollback
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- System Info Tab -->
    <div class="tab-content" id="systemTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">System Information</h2>

            <div class="info-grid">
                <?php foreach ($system_info as $key => $value): ?>
                    <div class="info-item">
                        <div class="info-label"><?= str_replace('_', ' ', $key) ?></div>
                        <div class="info-value"><?= htmlspecialchars($value) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3 style="margin: 2rem 0 1rem;">Requirements Check</h3>
            <?php
            $requirements = [
                'PHP Version >= 7.4' => version_compare(phpversion(), '7.4', '>='),
                'MySQL Version >= 5.7' => version_compare($system_info['mysql_version'], '5.7', '>='),
                'CURL Extension' => $system_info['curl_enabled'] === 'Yes',
                'ZIP Extension' => $system_info['zip_enabled'] === 'Yes',
                'GD Extension' => $system_info['gd_enabled'] === 'Yes',
                'Memory Limit >= 128M' => convertToBytes(ini_get('memory_limit')) >= 134217728,
                'Upload Max Filesize >= 32M' => convertToBytes(ini_get('upload_max_filesize')) >= 33554432,
                'Post Max Size >= 32M' => convertToBytes(ini_get('post_max_size')) >= 33554432,
                'Max Execution Time >= 30' => ini_get('max_execution_time') >= 30,
            ];

            function convertToBytes($value) {
                $value = trim($value);
                $last = strtolower($value[strlen($value)-1]);
                $value = (int)$value;
                switch($last) {
                    case 'g': $value *= 1024;
                    case 'm': $value *= 1024;
                    case 'k': $value *= 1024;
                }
                return $value;
            }
            ?>

            <div class="requirements-grid">
                <?php foreach ($requirements as $req => $passed): ?>
                    <div class="requirement-item <?= $passed ? 'passed' : 'failed' ?>">
                        <i class="fas fa-<?= $passed ? 'check-circle' : 'times-circle' ?>"></i>
                        <span><?= htmlspecialchars($req) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Update Details Modal -->
<div class="modal" id="updateModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div id="updateDetails"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    function showTab(tabId) {
        tabBtns.forEach(b => b.classList.remove('active'));
        tabContents.forEach(c => c.style.display = 'none');

        const activeBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        const activeTab = document.getElementById(tabId + 'Tab');
        if (activeTab) {
            activeTab.style.display = 'block';
        }
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            showTab(tabId);
            history.pushState(null, null, '#' + tabId);
        });
    });

    // Check URL hash for tab
    const hash = window.location.hash.substring(1);
    if (hash && ['available', 'history', 'system'].includes(hash)) {
        showTab(hash);
    }

    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

function showUpdateDetails(update) {
    const modal = document.getElementById('updateModal');
    const details = document.getElementById('updateDetails');

    let changelogHtml = '';
    if (update.changelog && update.changelog.length > 0) {
        changelogHtml = '<div class="detail-row"><div class="detail-label">Changelog:</div><div><ul style="margin: 0; padding-left: 1.5rem;">';
        update.changelog.forEach(item => {
            changelogHtml += `<li style="margin-bottom: 0.25rem;">${item}</li>`;
        });
        changelogHtml += '</ul></div></div>';
    }

    details.innerHTML = `
        <div class="detail-row">
            <div class="detail-label">Version</div>
            <div class="detail-value">${update.version} ${update.required ? '<span class="required-badge">Required</span>' : ''}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Release Date</div>
            <div class="detail-value">${update.release_date}</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Type</div>
            <div class="detail-value"><span class="update-type type-${update.type}">${update.type.toUpperCase()}</span></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Description</div>
            <div class="detail-value">${update.description}</div>
        </div>
        ${changelogHtml}
        <div class="detail-row">
            <div class="detail-label">Files Modified</div>
            <div class="detail-value">${update.files_modified} files</div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Download Size</div>
            <div class="detail-value">${update.size}</div>
        </div>
    `;

    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('updateModal').style.display = 'none';
}

function confirmUpdate(form) {
    const version = form.version.value;
    const backup = form.create_backup.checked;

    let message = `Install update to version ${version}?\n\n`;
    message += `• ${backup ? '✓ Backup will be created' : '✗ No backup will be created'}\n`;
    message += `• ${form.maintenance_mode.checked ? '✓ Maintenance mode will be enabled' : '✗ Maintenance mode will not be enabled'}\n\n`;
    message += 'During the update, the site may be temporarily unavailable. Continue?';

    return confirm(message);
}

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + C to check for updates
    if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
        e.preventDefault();
        window.location.href = '?action=check';
    }
});
</script>