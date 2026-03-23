<?php
// admin/import_logs.php
$page_title = "Import Logs";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_admin();

$logs_dir = '../uploads/imports/logs/';
$logs = [];

if (is_dir($logs_dir)) {
    $files = glob($logs_dir . '*.json');
    rsort($files); // Sort by newest first
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $logs[] = json_decode($content, true);
    }
}

require_once 'header.php';
?>

<div class="admin-main">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Import History</h1>
        <p>View past product imports</p>
    </div>

    <div class="card">
        <?php if (empty($logs)): ?>
            <p>No import logs found.</p>
        <?php else: ?>
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Filename</th>
                        <th>Total</th>
                        <th>Imported</th>
                        <th>Updated</th>
                        <th>Failed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i:s', strtotime($log['timestamp'])) ?></td>
                            <td><?= htmlspecialchars($log['filename']) ?></td>
                            <td><?= $log['results']['total'] ?></td>
                            <td class="success"><?= $log['results']['imported'] ?></td>
                            <td class="info"><?= $log['results']['updated'] ?></td>
                            <td class="danger"><?= $log['results']['failed'] ?></td>
                            <td>
                                <a href="view_import.php?id=<?= $log['import_id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>