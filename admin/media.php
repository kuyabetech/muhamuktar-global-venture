<?php
// admin/media.php - Media Library

$page_title = "Media Library";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();
// Helper: Format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS media (
            id INT PRIMARY KEY AUTO_INCREMENT,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size INT NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            dimensions VARCHAR(50),
            alt_text VARCHAR(255),
            caption TEXT,
            description TEXT,
            uploaded_by INT,
            is_image TINYINT(1) DEFAULT 0,
            width INT DEFAULT 0,
            height INT DEFAULT 0,
            download_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (file_type),
            INDEX idx_is_image (is_image),
            INDEX idx_uploaded_by (uploaded_by)
        )
    ");
} catch (Exception $e) {
    error_log("Media table error: " . $e->getMessage());
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
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 24;
$offset = ($page - 1) * $limit;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['files'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $upload_dir = '../uploads/media/';
        $thumb_dir = '../uploads/media/thumbs/';
        
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        if (!is_dir($thumb_dir)) mkdir($thumb_dir, 0755, true);
        
        $files = $_FILES['files'];
        $uploaded = 0;
        $failed = 0;
        
        // Handle multiple file upload
        if (is_array($files['name'])) {
            $file_count = count($files['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === 0) {
                    if (uploadMediaFile($files['tmp_name'][$i], $files['name'][$i], $files['type'][$i], $files['size'][$i])) {
                        $uploaded++;
                    } else {
                        $failed++;
                    }
                }
            }
        } else {
            // Single file upload
            if ($files['error'] === 0) {
                if (uploadMediaFile($files['tmp_name'], $files['name'], $files['type'], $files['size'])) {
                    $uploaded++;
                } else {
                    $failed++;
                }
            }
        }
        
        if ($uploaded > 0) {
            $success_msg = "$uploaded file(s) uploaded successfully";
            if ($failed > 0) {
                $success_msg .= ", $failed file(s) failed";
            }
        } else {
            $error_msg = "No files were uploaded";
        }
    }
}

// Handle file deletion
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT filename, file_path FROM media WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch();
        
        if ($file) {
            // Delete main file
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
            
            // Delete thumbnail if exists
            $thumb_path = dirname($file['file_path']) . '/thumbs/' . $file['filename'];
            if (file_exists($thumb_path)) {
                unlink($thumb_path);
            }
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
            $stmt->execute([$id]);
            
            $success_msg = "File deleted successfully";
        }
    } catch (Exception $e) {
        $error_msg = "Error deleting file: " . $e->getMessage();
    }
    header("Location: media.php?success=" . urlencode($success_msg));
    exit;
}

// Handle bulk delete
if ($action === 'bulk_delete' && isset($_POST['selected'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $selected = $_POST['selected'];
        $deleted = 0;
        
        foreach ($selected as $file_id) {
            $stmt = $pdo->prepare("SELECT filename, file_path FROM media WHERE id = ?");
            $stmt->execute([$file_id]);
            $file = $stmt->fetch();
            
            if ($file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
                $thumb_path = dirname($file['file_path']) . '/thumbs/' . $file['filename'];
                if (file_exists($thumb_path)) {
                    unlink($thumb_path);
                }
                
                $stmt = $pdo->prepare("DELETE FROM media WHERE id = ?");
                $stmt->execute([$file_id]);
                $deleted++;
            }
        }
        
        $success_msg = "$deleted file(s) deleted successfully";
    }
    header("Location: media.php?success=" . urlencode($success_msg));
    exit;
}

// Handle file edit (update metadata)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $id > 0) {
    $alt_text = trim($_POST['alt_text'] ?? '');
    $caption = trim($_POST['caption'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE media SET alt_text = ?, caption = ?, description = ? WHERE id = ?");
        $stmt->execute([$alt_text, $caption, $description, $id]);
        $success_msg = "File metadata updated successfully";
    } catch (Exception $e) {
        $error_msg = "Error updating metadata: " . $e->getMessage();
    }
    header("Location: media.php?action=edit&id=$id&success=" . urlencode($success_msg));
    exit;
}

// Upload helper function
function uploadMediaFile($tmp_name, $original_name, $type, $size) {
    global $pdo;
    
    $upload_dir = '../uploads/media/';
    $thumb_dir = '../uploads/media/thumbs/';
    
    // Generate unique filename
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Check if image
    $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    $width = 0;
    $height = 0;
    $dimensions = null;
    
    if (move_uploaded_file($tmp_name, $filepath)) {
        // Get image dimensions if applicable
        if ($is_image && $ext !== 'svg') {
            list($width, $height) = getimagesize($filepath);
            $dimensions = $width . 'x' . $height;
            
            // Create thumbnail
            createThumbnail($filepath, $thumb_dir . $filename, 300, 300);
        }
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO media (filename, original_name, file_path, file_size, file_type, mime_type, 
                              dimensions, is_image, width, height, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $file_type = $is_image ? 'image' : 'document';
        if (in_array($ext, ['mp4', 'avi', 'mov', 'wmv'])) $file_type = 'video';
        if (in_array($ext, ['mp3', 'wav', 'ogg'])) $file_type = 'audio';
        
        $stmt->execute([
            $filename, $original_name, $filepath, $size, $file_type, $type,
            $dimensions, $is_image ? 1 : 0, $width, $height, $_SESSION['user_id']
        ]);
        
        return true;
    }
    
    return false;
}

// Create thumbnail function
function createThumbnail($source, $destination, $max_width, $max_height) {
    list($width, $height, $type) = getimagesize($source);
    
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = $width * $ratio;
    $new_height = $height * $ratio;
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src_img = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $src_img = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $src_img = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $src_img = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    $dst_img = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($dst_img, imagecolorallocatealpha($dst_img, 0, 0, 0, 127));
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
    }
    
    imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst_img, $destination, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst_img, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($dst_img, $destination);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($dst_img, $destination, 90);
            break;
    }
    
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    return true;
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($type_filter)) {
    $where[] = "file_type = ?";
    $params[] = $type_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM media $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_files = $count_stmt->fetchColumn();
$total_pages = ceil($total_files / $limit);

// Fetch media files
$sql = "
    SELECT m.*, u.full_name AS uploader_name
    FROM media m
    LEFT JOIN users u ON m.uploaded_by = u.id
    $where_sql
    ORDER BY m.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$media_files = $stmt->fetchAll();

// Get file type stats
$stats = [
    'total' => $total_files,
    'images' => $pdo->query("SELECT COUNT(*) FROM media WHERE file_type = 'image'")->fetchColumn(),
    'documents' => $pdo->query("SELECT COUNT(*) FROM media WHERE file_type = 'document'")->fetchColumn(),
    'videos' => $pdo->query("SELECT COUNT(*) FROM media WHERE file_type = 'video'")->fetchColumn(),
    'audio' => $pdo->query("SELECT COUNT(*) FROM media WHERE file_type = 'audio'")->fetchColumn(),
    'total_size' => $pdo->query("SELECT SUM(file_size) FROM media")->fetchColumn() ?: 0,
];

// Get single file for editing
$edit_file = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
    $stmt->execute([$id]);
    $edit_file = $stmt->fetch();
}

require_once 'header.php';
?>

<style>
/* Responsive Media Library */
.media-container {
    padding: clamp(1.2rem, 4vw, 2.5rem);
    max-width: 1600px;
    margin: 0 auto;
}

/* Header */
.media-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.media-header h1 {
    font-size: clamp(1.8rem, 6vw, 2.3rem);
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: clamp(1rem, 2vw, 1.6rem);
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.8rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-value {
    font-size: clamp(1.8rem, 5vw, 2.4rem);
    font-weight: 700;
}

.stat-label {
    font-size: 0.95rem;
    color: var(--admin-gray);
}

/* Search & Filters */
.search-filter {
    background: white;
    padding: clamp(1.2rem, 2vw, 1.5rem);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

/* Media Grid */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: clamp(1rem, 2vw, 1.5rem);
}

.media-item {
    background: white;
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s;
    position: relative;
}

.media-item:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    transform: translateY(-4px);
}

.media-preview {
    position: relative;
    height: 180px;
    background: var(--admin-light);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.media-thumb {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.media-item:hover .media-thumb {
    transform: scale(1.05);
}

.media-icon {
    text-align: center;
    color: var(--admin-gray);
    font-size: 3rem;
}

.file-ext {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
}

.media-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s;
}

.media-item:hover .media-actions {
    opacity: 1;
}

.media-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 0.875rem;
    transition: background 0.2s;
}

.media-action-btn:hover {
    background: rgba(0, 0, 0, 0.8);
}

.media-checkbox {
    position: absolute;
    top: 0.75rem;
    left: 0.75rem;
    z-index: 10;
    width: 18px;
    height: 18px;
}

.media-info {
    padding: 0.9rem;
}

.media-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.media-meta {
    font-size: 0.8rem;
    color: var(--admin-gray);
    margin-bottom: 0.25rem;
}

.media-date {
    font-size: 0.8rem;
    color: var(--admin-gray);
}

/* Upload Modal */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1rem;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: clamp(1.5rem, 4vw, 2rem);
    width: 100%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    cursor: pointer;
    color: var(--admin-gray);
}

.upload-area {
    border: 2px dashed var(--admin-border);
    border-radius: 12px;
    padding: clamp(2rem, 5vw, 3rem);
    text-align: center;
    background: var(--admin-light);
    cursor: pointer;
    transition: all 0.3s;
}

.upload-area:hover,
.upload-area.dragover {
    border-color: var(--admin-primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.upload-area i {
    font-size: 4rem;
    color: var(--admin-primary);
    margin-bottom: 1rem;
}

/* Selected Files */
.selected-files {
    margin-top: 1.5rem;
    max-height: 200px;
    overflow-y: auto;
}

.selected-file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    background: white;
}

/* Bulk Actions */
.bulk-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-link {
    padding: 0.6rem 1rem;
    background: #f3f4f6;
    color: var(--admin-dark);
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
}

.page-link.active,
.page-link:hover {
    background: var(--admin-primary);
    color: white;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }
}

@media (max-width: 768px) {
    .media-container {
        padding: 1.2rem 1.5rem;
    }

    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }

    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }

    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .media-container {
        padding: 1rem 1.2rem;
    }

    .media-grid {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }

    .modal-content {
        padding: 1.5rem;
    }
}
</style>

<div class="media-container">

    <!-- Page Header -->
    <div class="media-header">
        <div>
            <h1 style="font-size: clamp(1.8rem, 6vw, 2.3rem); margin-bottom: 0.5rem;">
                <i class="fas fa-images"></i> Media Library
            </h1>
            <p style="color: var(--admin-gray);">Upload and manage images, documents, and media files</p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">
            <i class="fas fa-upload"></i> Upload Files
        </button>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total Files</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-database"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['images']) ?></div>
                    <div class="stat-label">Images</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-image"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= formatBytes($stats['total_size']) ?></div>
                    <div class="stat-label">Total Size</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-hdd"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <?php
                    $recent_count = $pdo->query("SELECT COUNT(*) FROM media WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
                    ?>
                    <div class="stat-value"><?= number_format($recent_count) ?></div>
                    <div class="stat-label">Uploaded (7 days)</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="search-filter">
        <form method="get">
            <div class="filter-grid">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search by filename, alt text, or caption..." class="form-control">

                <select name="type" class="form-control">
                    <option value="">All File Types</option>
                    <option value="image" <?= $type_filter === 'image' ? 'selected' : '' ?>>Images</option>
                    <option value="document" <?= $type_filter === 'document' ? 'selected' : '' ?>>Documents</option>
                    <option value="video" <?= $type_filter === 'video' ? 'selected' : '' ?>>Videos</option>
                    <option value="audio" <?= $type_filter === 'audio' ? 'selected' : '' ?>>Audio</option>
                </select>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="media.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($edit_file): ?>
        <!-- Edit File Metadata -->
        <div class="blog-form-card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-edit"></i> Edit File Metadata
            </h2>
            
            <div style="display: grid; grid-template-columns: minmax(180px, 300px) 1fr; gap: 2rem;">
                <!-- File Preview -->
                <div>
                    <?php if ($edit_file['is_image']): ?>
                        <img src="<?= BASE_URL ?>uploads/media/thumbs/<?= htmlspecialchars($edit_file['filename']) ?>" 
                             alt="<?= htmlspecialchars($edit_file['alt_text'] ?? $edit_file['original_name']) ?>"
                             style="width: 100%; border-radius: 12px; border: 1px solid var(--admin-border);">
                    <?php else: ?>
                        <div style="height: 200px; background: var(--admin-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                            <i class="fas fa-file fa-5x" style="color: var(--admin-gray);"></i>
                            <div style="margin-top: 1rem; font-size: 1.2rem; font-weight: 600;">
                                <?= strtoupper(pathinfo($edit_file['filename'], PATHINFO_EXTENSION)) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Edit Form -->
                <div>
                    <form method="post" action="?action=edit&id=<?= $edit_file['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Filename</label>
                            <input type="text" value="<?= htmlspecialchars($edit_file['original_name']) ?>" 
                                   class="form-control" readonly disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alt Text</label>
                            <input type="text" name="alt_text" value="<?= htmlspecialchars($edit_file['alt_text'] ?? '') ?>" 
                                   class="form-control" placeholder="Alternative text for accessibility">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Caption</label>
                            <input type="text" name="caption" value="<?= htmlspecialchars($edit_file['caption'] ?? '') ?>" 
                                   class="form-control" placeholder="Image caption">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="4" class="form-textarea form-control" 
                                      placeholder="Detailed description"><?= htmlspecialchars($edit_file['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="media.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                    
                    <!-- File Info -->
                    <div style="margin-top: 2.5rem; padding: 1.5rem; background: var(--admin-light); border-radius: 12px;">
                        <h4 style="margin-bottom: 1rem;">File Information</h4>
                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.75rem;">
                            <div style="font-weight: 600;">File Size:</div>
                            <div><?= formatBytes($edit_file['file_size']) ?></div>
                            
                            <div style="font-weight: 600;">File Type:</div>
                            <div><?= ucfirst($edit_file['file_type']) ?></div>
                            
                            <?php if ($edit_file['dimensions']): ?>
                            <div style="font-weight: 600;">Dimensions:</div>
                            <div><?= $edit_file['dimensions'] ?> px</div>
                            <?php endif; ?>
                            
                            <div style="font-weight: 600;">Uploaded:</div>
                            <div><?= date('M d, Y H:i', strtotime($edit_file['created_at'])) ?></div>
                            
                            <div style="font-weight: 600;">Uploaded By:</div>
                            <div><?= htmlspecialchars($edit_file['uploader_name'] ?? 'Unknown') ?></div>
                            
                            <div style="font-weight: 600;">Downloads:</div>
                            <div><?= number_format($edit_file['download_count']) ?></div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="<?= BASE_URL ?>uploads/media/<?= htmlspecialchars($edit_file['filename']) ?>" 
                               target="_blank" class="btn btn-secondary btn-sm">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php if ($edit_file['is_image']): ?>
                                <button class="btn btn-secondary btn-sm" onclick="copyImageURL('<?= BASE_URL ?>uploads/media/<?= htmlspecialchars($edit_file['filename']) ?>')">
                                    <i class="fas fa-link"></i> Copy URL
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Media Grid -->
        <div class="table-wrapper">
            <form method="post" action="?action=bulk_delete" id="bulkForm" onsubmit="return confirmBulkDelete()">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="bulk-actions">
                    <div>
                        <span id="selectedCount">0</span> files selected
                    </div>
                    <button type="submit" class="btn btn-danger" id="deleteSelected" disabled>
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>

                <?php if (empty($media_files)): ?>
                    <div style="text-align: center; padding: 6rem 2rem;">
                        <i class="fas fa-images" style="font-size: 5rem; color: var(--admin-gray); margin-bottom: 1.5rem; display: block;"></i>
                        <h3>No media files found</h3>
                        <p style="margin: 1rem 0 2rem;">Upload your first file to get started</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">
                            <i class="fas fa-upload"></i> Upload Files
                        </button>
                    </div>
                <?php else: ?>
                    <div class="media-grid">
                        <?php foreach ($media_files as $file): ?>
                            <div class="media-item" data-id="<?= $file['id'] ?>">
                                <div class="media-preview">
                                    <input type="checkbox" name="selected[]" value="<?= $file['id'] ?>" 
                                           class="media-checkbox" onchange="updateSelectedCount()">
                                    
                                    <?php if ($file['is_image']): ?>
                                        <img src="<?= BASE_URL ?>uploads/media/thumbs/<?= htmlspecialchars($file['filename']) ?>" 
                                             alt="<?= htmlspecialchars($file['alt_text'] ?? $file['original_name']) ?>"
                                             class="media-thumb">
                                    <?php else: ?>
                                        <div class="media-icon">
                                            <?php
                                            $ext = pathinfo($file['filename'], PATHINFO_EXTENSION);
                                            $icon = 'fa-file';
                                            if (in_array($ext, ['pdf'])) $icon = 'fa-file-pdf';
                                            elseif (in_array($ext, ['doc','docx'])) $icon = 'fa-file-word';
                                            elseif (in_array($ext, ['xls','xlsx'])) $icon = 'fa-file-excel';
                                            elseif (in_array($ext, ['zip','rar','7z'])) $icon = 'fa-file-archive';
                                            elseif (in_array($ext, ['mp4','avi','mov'])) $icon = 'fa-file-video';
                                            elseif (in_array($ext, ['mp3','wav'])) $icon = 'fa-file-audio';
                                            ?>
                                            <i class="fas <?= $icon ?> fa-4x"></i>
                                            <div class="file-ext"><?= strtoupper($ext) ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="media-actions">
                                        <a href="?action=edit&id=<?= $file['id'] ?>" class="media-action-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?action=delete&id=<?= $file['id'] ?>" class="media-action-btn" 
                                           onclick="return confirm('Delete this file?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>uploads/media/<?= htmlspecialchars($file['filename']) ?>" 
                                           target="_blank" class="media-action-btn" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="media-info">
                                    <div class="media-name" title="<?= htmlspecialchars($file['original_name']) ?>">
                                        <?= htmlspecialchars(substr($file['original_name'], 0, 30)) . (strlen($file['original_name']) > 30 ? '...' : '') ?>
                                    </div>
                                    <div class="media-meta">
                                        <span><?= formatBytes($file['file_size']) ?></span>
                                        <?php if ($file['dimensions']): ?>
                                            <span> • <?= $file['dimensions'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="media-date">
                                        <?= date('M d, Y', strtotime($file['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=1&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>" class="page-link">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>" class="page-link">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>" 
                                   class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>" class="page-link">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>" class="page-link">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Upload Files</h2>
                <button class="modal-close" onclick="document.getElementById('uploadModal').style.display='none'">×</button>
            </div>
            
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="upload-area" id="dropZone">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drag & Drop Files Here</h3>
                    <p>or</p>
                    <label for="fileInput" class="btn btn-primary" style="cursor: pointer;">
                        Browse Files
                    </label>
                    <input type="file" name="files[]" id="fileInput" multiple style="display: none;" onchange="handleFileSelect()">
                    <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--admin-gray);">
                        Maximum file size: 50MB per file • Images, PDFs, Videos, Audio
                    </p>
                </div>
                
                <div id="fileList" class="selected-files" style="display: none;">
                    <h4>Selected Files</h4>
                    <div id="selectedFiles"></div>
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload Files
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('uploadModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Drag & Drop + File Handling
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');

    if (dropZone && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, preventDefaults));
        ['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, highlight));
        ['dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, unhighlight));
        dropZone.addEventListener('drop', handleDrop);
        dropZone.addEventListener('click', () => fileInput.click());
    }

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight() { dropZone.classList.add('dragover'); }
    function unhighlight() { dropZone.classList.remove('dragover'); }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        fileInput.files = dt.files;
        handleFileSelect();
    }

    window.handleFileSelect = function() {
        const files =