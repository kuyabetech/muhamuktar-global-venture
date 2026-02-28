<?php
// admin/backup.php - Backup & Restore

$page_title = "Backup & Restore";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize backup directory
$backup_dir = '../uploads/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle actions
$action = $_GET['action'] ?? '';
$file = $_GET['file'] ?? '';
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Handle backup creation
if ($action === 'create' && isset($_POST['create_backup'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        try {
            $tables = isset($_POST['tables']) ? $_POST['tables'] : [];
            $include_uploads = isset($_POST['include_uploads']);
            $compress = isset($_POST['compress']);
            
            $backup_file = createBackup($tables, $include_uploads, $compress);
            
            if ($backup_file) {
                $success_msg = "Backup created successfully: " . basename($backup_file);
                
                // Log activity
                logActivity($_SESSION['user_id'], "Created database backup: " . basename($backup_file));
            } else {
                $error_msg = "Failed to create backup";
            }
        } catch (Exception $e) {
            $error_msg = "Error creating backup: " . $e->getMessage();
        }
    }
    header("Location: backup.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle backup download
if ($action === 'download' && !empty($file)) {
    $file_path = $backup_dir . basename($file);
    
    if (file_exists($file_path)) {
        // Log activity
        logActivity($_SESSION['user_id'], "Downloaded backup: " . $file);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        $error_msg = "Backup file not found";
        header("Location: backup.php?error=" . urlencode($error_msg));
        exit;
    }
}

// Handle backup restore
if ($action === 'restore' && !empty($file) && isset($_POST['restore_backup'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $file_path = $backup_dir . basename($file);
        
        if (file_exists($file_path)) {
            try {
                // Confirm restore
                if (isset($_POST['confirm_restore'])) {
                    $result = restoreBackup($file_path);
                    
                    if ($result) {
                        $success_msg = "Database restored successfully from: " . basename($file);
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], "Restored database from backup: " . basename($file));
                    } else {
                        $error_msg = "Failed to restore database";
                    }
                } else {
                    $error_msg = "Please confirm restore operation";
                }
            } catch (Exception $e) {
                $error_msg = "Error restoring backup: " . $e->getMessage();
            }
        } else {
            $error_msg = "Backup file not found";
        }
    }
    header("Location: backup.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle backup deletion
if ($action === 'delete' && !empty($file)) {
    if (!hash_equals($csrf_token, $_GET['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $file_path = $backup_dir . basename($file);
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                $success_msg = "Backup deleted successfully: " . basename($file);
                
                // Log activity
                logActivity($_SESSION['user_id'], "Deleted backup: " . basename($file));
            } else {
                $error_msg = "Failed to delete backup file";
            }
        } else {
            $error_msg = "Backup file not found";
        }
    }
    header("Location: backup.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle scheduled backups
if ($action === 'schedule' && isset($_POST['save_schedule'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $frequency = $_POST['frequency'] ?? 'never';
        $time = $_POST['backup_time'] ?? '02:00';
        $retention = (int)($_POST['retention'] ?? 7);
        $tables = isset($_POST['scheduled_tables']) ? $_POST['scheduled_tables'] : [];
        $include_uploads = isset($_POST['scheduled_include_uploads']);
        
        // Save schedule to settings
        $schedule = [
            'frequency' => $frequency,
            'time' => $time,
            'retention' => $retention,
            'tables' => $tables,
            'include_uploads' => $include_uploads,
            'last_run' => null,
            'next_run' => calculateNextRun($frequency, $time)
        ];
        
        $schedule_json = json_encode($schedule);
        
        // Update settings table
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, value) VALUES ('backup_schedule', ?)
            ON DUPLICATE KEY UPDATE value = ?
        ");
        $stmt->execute([$schedule_json, $schedule_json]);
        
        $success_msg = "Backup schedule saved successfully";
        
        // Log activity
        logActivity($_SESSION['user_id'], "Updated backup schedule");
    }
    header("Location: backup.php?success=" . urlencode($success_msg));
    exit;
}

// Get current schedule
$schedule = null;
$stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'backup_schedule'");
$stmt->execute();
$schedule_json = $stmt->fetchColumn();
if ($schedule_json) {
    $schedule = json_decode($schedule_json, true);
}

// Get list of backup files
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($backup_dir . $file)) {
            $file_path = $backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }
    }
    
    // Sort by modified date (newest first)
    usort($backup_files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Get database tables
$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

// Calculate backup statistics
$total_backups = count($backup_files);
$total_size = array_sum(array_column($backup_files, 'size'));
$latest_backup = !empty($backup_files) ? $backup_files[0]['modified'] : null;
$oldest_backup = !empty($backup_files) ? end($backup_files)['modified'] : null;

// Helper function to create backup
function createBackup($selected_tables = [], $include_uploads = false, $compress = true) {
    global $pdo, $backup_dir, $db_name;
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_name = "backup_{$timestamp}";
    $backup_file = $backup_dir . $backup_name . '.sql';
    
    try {
        // Get all tables if none selected
        if (empty($selected_tables)) {
            $stmt = $pdo->query("SHOW TABLES");
            $selected_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        $output = "-- Muhamuktar Global Venture Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Tables: " . implode(', ', $selected_tables) . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($selected_tables as $table) {
            // Get create table syntax
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $create = $stmt->fetch(PDO::FETCH_ASSOC);
            $output .= "\n-- Table structure for table `$table`\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $create['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $output .= "-- Dumping data for table `$table`\n";
                
                foreach ($rows as $row) {
                    $columns = array_keys($row);
                    $values = array_values($row);
                    
                    // Escape values
                    foreach ($values as &$value) {
                        if ($value === null) {
                            $value = 'NULL';
                        } else {
                            $value = "'" . addslashes($value) . "'";
                        }
                    }
                    
                    $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $output .= "\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write SQL file
        file_put_contents($backup_file, $output);
        
        // Include uploads if requested
        if ($include_uploads) {
            $uploads_dir = '../uploads/';
            $backup_uploads_dir = $backup_dir . $backup_name . '_uploads/';
            
            if (is_dir($uploads_dir)) {
                // Create uploads backup directory
                if (!is_dir($backup_uploads_dir)) {
                    mkdir($backup_uploads_dir, 0755, true);
                }
                
                // Copy uploads recursively
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        $dest_dir = $backup_uploads_dir . $iterator->getSubPathName();
                        if (!is_dir($dest_dir)) {
                            mkdir($dest_dir, 0755, true);
                        }
                    } else {
                        copy($item, $backup_uploads_dir . $iterator->getSubPathName());
                    }
                }
            }
        }
        
        // Compress if requested
        if ($compress) {
            $zip_file = $backup_dir . $backup_name . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                // Add SQL file
                $zip->addFile($backup_file, $backup_name . '.sql');
                
                // Add uploads if backed up
                if ($include_uploads && isset($backup_uploads_dir) && is_dir($backup_uploads_dir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($backup_uploads_dir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );
                    
                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $file_path = $file->getRealPath();
                            $relative_path = 'uploads/' . substr($file_path, strlen($backup_uploads_dir));
                            $zip->addFile($file_path, $relative_path);
                        }
                    }
                }
                
                $zip->close();
                
                // Remove SQL file and uploads directory
                unlink($backup_file);
                if (isset($backup_uploads_dir) && is_dir($backup_uploads_dir)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($backup_uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    
                    foreach ($files as $fileinfo) {
                        if ($fileinfo->isDir()) {
                            rmdir($fileinfo->getRealPath());
                        } else {
                            unlink($fileinfo->getRealPath());
                        }
                    }
                    rmdir($backup_uploads_dir);
                }
                
                return $zip_file;
            }
        }
        
        return $backup_file;
        
    } catch (Exception $e) {
        error_log("Backup creation error: " . $e->getMessage());
        return false;
    }
}

// Helper function to restore backup
function restoreBackup($file_path) {
    global $pdo;
    
    try {
        // Check if it's a zip file
        $file_info = pathinfo($file_path);
        
        if ($file_info['extension'] === 'zip') {
            $zip = new ZipArchive();
            $extract_path = dirname($file_path) . '/extract_' . uniqid();
            
            if ($zip->open($file_path) === TRUE) {
                $zip->extractTo($extract_path);
                $zip->close();
                
                // Find SQL file
                $sql_file = null;
                $files = scandir($extract_path);
                foreach ($files as $f) {
                    if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
                        $sql_file = $extract_path . '/' . $f;
                        break;
                    }
                }
                
                if ($sql_file && file_exists($sql_file)) {
                    // Restore database
                    $sql = file_get_contents($sql_file);
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $pdo->exec($sql);
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    
                    // Restore uploads if present
                    $uploads_dir = $extract_path . '/uploads';
                    if (is_dir($uploads_dir)) {
                        $target_uploads = '../uploads/';
                        
                        $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        
                        foreach ($iterator as $item) {
                            $dest = $target_uploads . $iterator->getSubPathName();
                            if ($item->isDir()) {
                                if (!is_dir($dest)) {
                                    mkdir($dest, 0755, true);
                                }
                            } else {
                                copy($item, $dest);
                            }
                        }
                    }
                    
                    // Cleanup
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($extract_path, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    
                    foreach ($files as $fileinfo) {
                        if ($fileinfo->isDir()) {
                            rmdir($fileinfo->getRealPath());
                        } else {
                            unlink($fileinfo->getRealPath());
                        }
                    }
                    rmdir($extract_path);
                    
                    return true;
                }
            }
        } else {
            // Regular SQL file
            $sql = file_get_contents($file_path);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec($sql);
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return false;
    }
}

// Helper function to calculate next backup run
function calculateNextRun($frequency, $time) {
    if ($frequency === 'never') {
        return null;
    }
    
    $now = time();
    list($hour, $minute) = explode(':', $time);
    
    $today_run = mktime($hour, $minute, 0, date('m'), date('d'), date('Y'));
    
    if ($today_run < $now) {
        // Run is today but already passed, schedule for next period
        switch ($frequency) {
            case 'daily':
                return $today_run + 86400; // Tomorrow
            case 'weekly':
                return $today_run + 604800; // Next week
            case 'monthly':
                return $today_run + 2592000; // Next month
        }
    }
    
    return $today_run;
}

// Helper function to log activity
function logActivity($user_id, $action) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activity_log (user_id, action, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail
    }
}

// Format bytes helper
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-database"></i> Backup & Restore
            </h1>
            <p style="color: var(--admin-gray);">Create, download, and restore database backups</p>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Backup Statistics -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($total_backups) ?></div>
                    <div class="stat-label">Total Backups</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-copy"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= formatBytes($total_size) ?></div>
                    <div class="stat-label">Total Size</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-hdd"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= $latest_backup ? date('M d, Y', $latest_backup) : 'Never' ?></div>
                    <div class="stat-label">Latest Backup</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= count($tables) ?></div>
                    <div class="stat-label">Database Tables</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-table"></i></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 2rem;">
        <div style="display: flex; gap: 1rem;">
            <button type="button" class="tab-btn active" data-tab="create">Create Backup</button>
            <button type="button" class="tab-btn" data-tab="restore">Restore</button>
            <button type="button" class="tab-btn" data-tab="schedule">Schedule</button>
            <button type="button" class="tab-btn" data-tab="settings">Settings</button>
        </div>
    </div>

    <!-- Create Backup Tab -->
    <div class="tab-content active" id="createTab">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Create New Backup</h2>
            
            <form method="post" action="?action=create">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="create_backup" value="1">
                
                <div class="form-group">
                    <label class="form-label">Tables to Backup</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 300px; overflow-y: auto; padding: 1rem; border: 1px solid var(--admin-border); border-radius: 8px;">
                        <?php foreach ($tables as $table): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="tables[]" value="<?= htmlspecialchars($table) ?>" checked>
                                <?= htmlspecialchars($table) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllTables(true)">Select All</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="selectAllTables(false)">Deselect All</button>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="include_uploads" value="1">
                            Include Uploads Folder
                        </label>
                        <small>This may significantly increase backup size</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="compress" value="1" checked>
                            Compress Backup (ZIP)
                        </label>
                        <small>Recommended for smaller file size</small>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Create new backup? This may take a few moments.')">
                        <i class="fas fa-database"></i> Create Backup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore Tab -->
    <div class="tab-content" id="restoreTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Restore from Backup</h2>
            
            <?php if (empty($backup_files)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-database" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                    <p>No backup files found</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $backup): ?>
                                <tr>
                                    <td><?= htmlspecialchars($backup['name']) ?></td>
                                    <td><?= formatBytes($backup['size']) ?></td>
                                    <td><?= date('M d, Y H:i:s', $backup['modified']) ?></td>
                                    <td><?= strtoupper($backup['type']) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=download&file=<?= urlencode($backup['name']) ?>" 
                                               class="btn btn-success btn-sm" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <form method="post" action="?action=restore&file=<?= urlencode($backup['name'])?>" 
                                                  style="display: inline;" 
                                                  onsubmit="return confirmRestore()">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="restore_backup" value="1">
                                                <input type="hidden" name="confirm_restore" value="1">
                                                <button type="submit" class="btn btn-warning btn-sm" title="Restore">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                            
                                            <a href="?action=delete&file=<?= urlencode($backup['name']) ?>&csrf_token=<?= $csrf_token ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Delete this backup?')"
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 2rem;">
                    <p class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> Restoring a backup will overwrite your current database. 
                        This action cannot be undone. Make sure you have a recent backup before proceeding.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Tab -->
    <div class="tab-content" id="scheduleTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Automated Backup Schedule</h2>
            
            <form method="post" action="?action=schedule">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="save_schedule" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Backup Frequency</label>
                        <select name="frequency" class="form-control" id="backupFrequency">
                            <option value="never" <?= ($schedule['frequency'] ?? 'never') === 'never' ? 'selected' : '' ?>>Never (Manual Only)</option>
                            <option value="daily" <?= ($schedule['frequency'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= ($schedule['frequency'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= ($schedule['frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Backup Time</label>
                        <input type="time" name="backup_time" 
                               value="<?= htmlspecialchars($schedule['time'] ?? '02:00') ?>" 
                               class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Retention Period (days)</label>
                    <input type="number" name="retention" 
                           value="<?= htmlspecialchars($schedule['retention'] ?? 7) ?>" 
                           class="form-control" min="1" max="365">
                    <small>Number of days to keep backups before automatic deletion</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tables to Backup</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem; max-height: 200px; overflow-y: auto; padding: 1rem; border: 1px solid var(--admin-border); border-radius: 8px;">
                        <?php foreach ($tables as $table): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="scheduled_tables[]" value="<?= htmlspecialchars($table) ?>" 
                                       <?= empty($schedule['tables']) || in_array($table, $schedule['tables']) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($table) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="scheduled_include_uploads" value="1" 
                               <?= !empty($schedule['include_uploads']) ? 'checked' : '' ?>>
                        Include Uploads Folder in Scheduled Backups
                    </label>
                </div>
                
                <?php if (!empty($schedule['next_run'])): ?>
                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <i class="fas fa-clock"></i>
                        Next scheduled backup: <strong><?= date('M d, Y H:i:s', $schedule['next_run']) ?></strong>
                        <?php if (!empty($schedule['last_run'])): ?>
                            <br>Last backup: <?= date('M d, Y H:i:s', $schedule['last_run']) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Settings Tab -->
    <div class="tab-content" id="settingsTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Backup Settings</h2>
            
            <div class="form-group">
                <label class="form-label">Backup Directory</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" value="<?= htmlspecialchars($backup_dir) ?>" class="form-control" readonly>
                    <button class="btn btn-secondary" onclick="copyToClipboard('<?= $backup_dir ?>')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <small>Make sure this directory is writable by the web server</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Database Size</label>
                <div>
                    <?php
                    $db_size = 0;
                    foreach ($tables as $table) {
                        $stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
                        $status = $stmt->fetch(PDO::FETCH_ASSOC);
                        $db_size += $status['Data_length'] + $status['Index_length'];
                    }
                    ?>
                    <strong><?= formatBytes($db_size) ?></strong>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Backup Status</label>
                <div>
                    <?php if (is_writable($backup_dir)): ?>
                        <span class="status-badge status-success">Writable</span>
                    <?php else: ?>
                        <span class="status-badge status-danger">Not Writable</span>
                    <?php endif; ?>
                    
                    <?php if (function_exists('exec')): ?>
                        <span class="status-badge status-success">Exec Available</span>
                    <?php else: ?>
                        <span class="status-badge status-warning">Exec Not Available</span>
                    <?php endif; ?>
                    
                    <?php if (class_exists('ZipArchive')): ?>
                        <span class="status-badge status-success">ZipArchive Available</span>
                    <?php else: ?>
                        <span class="status-badge status-danger">ZipArchive Not Available</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="alert alert-info">
                <h4 style="margin-bottom: 0.5rem;">Cron Job Setup</h4>
                <p>To enable automated backups, add the following line to your crontab:</p>
                <code style="display: block; padding: 1rem; background: var(--admin-light); border-radius: 4px; margin: 1rem 0;">
                    * * * * * php <?= realpath(__DIR__ . '/cron/backup.php') ?> >/dev/null 2>&1
                </code>
                <p class="small">This will check for scheduled backups every minute.</p>
            </div>
        </div>
    </div>
</div>

<style>
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
    margin-right: 0.5rem;
}

.status-success {
    background: #d1fae5;
    color: #065f46;
}

.status-warning {
    background: #fef3c7;
    color: #92400e;
}

.status-danger {
    background: #fee2e2;
    color: #991b1b;
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

.tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
}

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab') + 'Tab';
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.style.display = 'none');
            
            this.classList.add('active');
            document.getElementById(tabId).style.display = 'block';
        });
    });
    
    // Check URL hash for tab
    const hash = window.location.hash.substring(1);
    if (hash) {
        const tab = document.querySelector(`[data-tab="${hash}"]`);
        if (tab) {
            tab.click();
        }
    }
});

function selectAllTables(select) {
    const checkboxes = document.querySelectorAll('input[name="tables[]"]');
    checkboxes.forEach(cb => cb.checked = select);
}

function confirmRestore() {
    return confirm('WARNING: Restoring will overwrite your current database!\n\nThis action cannot be undone.\n\nAre you ABSOLUTELY sure you want to proceed?');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}

// Update next backup time display
document.getElementById('backupFrequency')?.addEventListener('change', function() {
    const frequency = this.value;
    const timeInput = document.querySelector('[name="backup_time"]');
    
    if (frequency === 'never') {
        timeInput.disabled = true;
    } else {
        timeInput.disabled = false;
    }
});
</script>

