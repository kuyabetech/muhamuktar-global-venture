<?php
// admin/import_product_images.php - Import product images

$page_title = "Import Product Images";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Create necessary directories
$base_upload_dir = '../uploads/products/';
$dirs = ['', 'thumbs/', 'temp/', 'original/'];
foreach ($dirs as $dir) {
    $path = $base_upload_dir . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Initialize variables
$action = $_GET['action'] ?? 'upload';
$error = '';
$success = '';

// ============================================
// FUNCTION DEFINITIONS (All with correct parameter order)
// ============================================

/**
 * Create thumbnail from source image
 * Required: $source, $destination
 * Optional: $width, $height
 */
function createThumbnail($source, $destination, $width = 300, $height = 300) {
    if (!file_exists($source)) {
        error_log("Source file not found: " . $source);
        return false;
    }
    
    list($src_width, $src_height, $type) = @getimagesize($source);
    
    if (!$src_width || !$src_height) {
        error_log("Invalid image file: " . $source);
        return false;
    }
    
    // Create image resource based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src_img = @imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $src_img = @imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $src_img = @imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $src_img = @imagecreatefromwebp($source);
            } else {
                error_log("WebP support not enabled");
                return false;
            }
            break;
        default:
            error_log("Unsupported image type: " . $type);
            return false;
    }
    
    if (!$src_img) {
        error_log("Failed to create image resource");
        return false;
    }
    
    // Calculate dimensions
    $src_ratio = $src_width / $src_height;
    $dst_ratio = $width / $height;
    
    if ($src_ratio > $dst_ratio) {
        $new_width = $width;
        $new_height = $width / $src_ratio;
    } else {
        $new_height = $height;
        $new_width = $height * $src_ratio;
    }
    
    // Create thumbnail
    $dst_img = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
        $transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
        imagefilledrectangle($dst_img, 0, 0, $width, $height, $transparent);
    }
    
    // Preserve transparency for GIF
    if ($type == IMAGETYPE_GIF) {
        $transparent_index = imagecolortransparent($src_img);
        if ($transparent_index >= 0) {
            $transparent_color = imagecolorsforindex($src_img, $transparent_index);
            $transparent_index = imagecolorallocate($dst_img, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
            imagefill($dst_img, 0, 0, $transparent_index);
            imagecolortransparent($dst_img, $transparent_index);
        }
    }
    
    // Calculate position
    $dst_x = max(0, ($width - $new_width) / 2);
    $dst_y = max(0, ($height - $new_height) / 2);
    
    // Resize
    imagecopyresampled($dst_img, $src_img, 
                       (int)$dst_x, (int)$dst_y, 0, 0, 
                       (int)$new_width, (int)$new_height, 
                       $src_width, $src_height);
    
    // Save
    $success = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($dst_img, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($dst_img, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($dst_img, $destination);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($dst_img, $destination, 85);
            }
            break;
    }
    
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    return $success;
}

/**
 * Upload product image
 * Required: $product_id, $pdo, $filepath
 * Optional: $is_main
 */
function uploadProductImage($product_id, $pdo, $filepath, $is_main = 0) {
    $upload_dir = '../uploads/products/';
    $thumbs_dir = $upload_dir . 'thumbs/';
    
    // Create directories if they don't exist
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (!is_dir($thumbs_dir)) mkdir($thumbs_dir, 0755, true);
    
    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $filename = $product_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    $dest = $upload_dir . $filename;
    
    if (copy($filepath, $dest)) {
        // Create thumbnail
        createThumbnail($dest, $thumbs_dir . $filename);
        
        try {
            // Save to database
            $stmt = $pdo->prepare("
                INSERT INTO product_images (product_id, filename, is_main, display_order, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            // Get next display order
            $order_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
            $order_stmt->execute([$product_id]);
            $order = $order_stmt->fetchColumn() + 1;
            
            $stmt->execute([$product_id, $filename, $is_main, $order]);
            
            // If this is main image, unset other main images
            if ($is_main) {
                $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ? AND filename != ?")
                    ->execute([$product_id, $filename]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error saving image to database: " . $e->getMessage());
            // Delete the file if database insert failed
            if (file_exists($dest)) unlink($dest);
            if (file_exists($thumbs_dir . $filename)) unlink($thumbs_dir . $filename);
            return false;
        }
    }
    
    return false;
}

/**
 * Match image filename to product
 * Required: $filename, $pdo
 */
function matchImageToProduct($filename, $pdo) {
    // Remove extension
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    // Remove _main, _1, _2, etc.
    $base = preg_replace('/_[0-9]+$|_main$|_detail$|_side$/', '', $name);
    
    try {
        // Try to match by EAN (13 digits)
        if (preg_match('/^\d{13}$/', $base)) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE ean = ?");
            $stmt->execute([$base]);
            if ($row = $stmt->fetch()) return $row['id'];
        }
        
        // Try by SKU
        if (!empty($base)) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
            $stmt->execute([$base]);
            if ($row = $stmt->fetch()) return $row['id'];
        }
        
        // Try by ID (if numeric)
        if (is_numeric($base)) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$base]);
            if ($row = $stmt->fetch()) return $row['id'];
        }
        
        // Try by name slug
        $slug = createSlug($base);
        $stmt = $pdo->prepare("SELECT id FROM products WHERE slug LIKE ? OR name LIKE ?");
        $search_term = "%$slug%";
        $stmt->execute([$search_term, $search_term]);
        if ($row = $stmt->fetch()) return $row['id'];
        
    } catch (Exception $e) {
        error_log("Error matching image to product: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Process extracted images from ZIP
 * Required: $temp_dir, $pdo
 * Optional: $create_thumbs
 */
function processExtractedImages($temp_dir, $pdo, $create_thumbs = true) {
    $result = [
        'processed' => 0,
        'matched' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    // Get all image files
    $files = glob($temp_dir . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE);
    
    foreach ($files as $file) {
        $filename = basename($file);
        $result['processed']++;
        
        // Try to match by filename
        $product_id = matchImageToProduct($filename, $pdo);
        
        if ($product_id) {
            // Determine if main image
            $is_main = (strpos($filename, '_main') !== false || 
                       strpos($filename, '_1.') !== false || 
                       $result['matched'] === 0) ? 1 : 0;
            
            // Upload image
            if (uploadProductImage($product_id, $pdo, $file, $is_main)) {
                $result['matched']++;
                $result['details'][] = "✅ $filename → Product ID $product_id" . ($is_main ? " (main)" : "");
            } else {
                $result['failed']++;
                $result['details'][] = "❌ Failed to upload $filename";
            }
        } else {
            $result['failed']++;
            $result['details'][] = "❌ Could not match $filename to any product";
        }
    }
    
    // Cleanup
    array_map('unlink', glob("$temp_dir/*.*"));
    rmdir($temp_dir);
    
    return $result;
}

/**
 * Download and save image from URL
 * Required: $queue_item, $pdo
 */
function downloadAndSaveImage($queue_item, $pdo) {
    $upload_dir = '../uploads/products/';
    $thumbs_dir = $upload_dir . 'thumbs/';
    
    // Create directories if they don't exist
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (!is_dir($thumbs_dir)) mkdir($thumbs_dir, 0755, true);
    
    // Get image content
    $ch = curl_init($queue_item['image_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($http_code === 200 && $image_data && strlen($image_data) > 100) {
        // Determine file extension from content type or URL
        $ext = 'jpg'; // default
        
        if (strpos($content_type, 'jpeg') !== false || strpos($content_type, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($content_type, 'png') !== false) {
            $ext = 'png';
        } elseif (strpos($content_type, 'gif') !== false) {
            $ext = 'gif';
        } elseif (strpos($content_type, 'webp') !== false) {
            $ext = 'webp';
        } else {
            // Try to get from URL
            $url_ext = strtolower(pathinfo(parse_url($queue_item['image_url'], PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($url_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $ext = $url_ext;
            }
        }
        
        // Generate filename
        $filename = $queue_item['product_id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        // Save image
        if (file_put_contents($dest, $image_data)) {
            // Create thumbnail
            if (createThumbnail($dest, $thumbs_dir . $filename)) {
                try {
                    // Save to product_images
                    $img_stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, filename, is_main, display_order, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    
                    $img_stmt->execute([
                        $queue_item['product_id'], 
                        $filename, 
                        $queue_item['is_main'] ?? 0,
                        $queue_item['display_order'] ?? 1
                    ]);
                    
                    // Update queue status
                    $update = $pdo->prepare("UPDATE product_image_queue SET status = 'downloaded', local_path = ?, updated_at = NOW() WHERE id = ?");
                    $update->execute([$filename, $queue_item['id']]);
                    
                    // If main image, unset others
                    if (!empty($queue_item['is_main'])) {
                        $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ? AND filename != ?")
                            ->execute([$queue_item['product_id'], $filename]);
                    }
                    
                    return true;
                    
                } catch (Exception $e) {
                    error_log("Database error in downloadAndSaveImage: " . $e->getMessage());
                }
            }
        }
    }
    
    // Update status to failed
    try {
        $pdo->prepare("UPDATE product_image_queue SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?")
            ->execute(["HTTP $http_code - " . substr($content_type, 0, 50), $queue_item['id']]);
    } catch (Exception $e) {
        error_log("Error updating queue status: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Get image statistics
 * Required: $pdo
 */
function getImageStats($pdo) {
    $stats = [
        'total_products' => 0,
        'products_with_images' => 0,
        'products_without_images' => 0,
        'total_images' => 0,
        'pending_downloads' => 0
    ];
    
    try {
        // Total products
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        $stats['total_products'] = (int)$stmt->fetchColumn();
        
        // Products with images
        $stmt = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM product_images");
        $stats['products_with_images'] = (int)$stmt->fetchColumn();
        
        // Products without images
        $stats['products_without_images'] = $stats['total_products'] - $stats['products_with_images'];
        
        // Total images
        $stmt = $pdo->query("SELECT COUNT(*) FROM product_images");
        $stats['total_images'] = (int)$stmt->fetchColumn();
        
        // Pending downloads
        $stmt = $pdo->query("SELECT COUNT(*) FROM product_image_queue WHERE status = 'pending'");
        $stats['pending_downloads'] = (int)$stmt->fetchColumn();
        
    } catch (Exception $e) {
        error_log("Error getting image stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Validate image file
 * Required: $file
 */
function validateImageFile($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return "File size exceeds 10MB limit";
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return "Invalid file type. Allowed: JPG, PNG, GIF, WEBP";
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        return "Invalid file extension";
    }
    
    return true;
}

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// Handle ZIP upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images_zip'])) {
    $file = $_FILES['images_zip'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Check file type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext === 'zip') {
            $zip = new ZipArchive;
            $temp_dir = $base_upload_dir . 'temp/' . uniqid() . '/';
            mkdir($temp_dir, 0755, true);
            
            if ($zip->open($file['tmp_name']) === TRUE) {
                $zip->extractTo($temp_dir);
                $zip->close();
                
                // Process extracted images
                $create_thumbs = isset($_POST['create_thumbs']);
                $result = processExtractedImages($temp_dir, $pdo, $create_thumbs);
                
                $_SESSION['import_result'] = $result;
                $success = "ZIP file processed: {$result['matched']} images matched, {$result['failed']} failed";
                
                // Store in session for results page
                $_SESSION['import_success'] = $success;
                $_SESSION['import_details'] = $result['details'];
                
                header("Location: import_product_images.php?action=results");
                exit;
            } else {
                $error = "Failed to open ZIP file.";
            }
        } else {
            $error = "Please upload a valid ZIP file.";
        }
    } else {
        $error = "File upload failed. Error code: " . $file['error'];
    }
}

// Handle mapping CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['mapping_csv'])) {
    $file = $_FILES['mapping_csv'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext === 'csv') {
            $result = processImageMappingCSV($file['tmp_name'], $pdo);
            
            if ($result['success']) {
                $success = "CSV processed: " . $result['queued'] . " images queued for download";
                $_SESSION['import_success'] = $success;
            } else {
                $error = "Error processing CSV: " . $result['error'];
            }
        } else {
            $error = "Please upload a valid CSV file.";
        }
    } else {
        $error = "File upload failed. Error code: " . $file['error'];
    }
}

// Handle download all pending images
if ($action === 'download_all') {
    $stmt = $pdo->query("SELECT * FROM product_image_queue WHERE status = 'pending'");
    $pending = $stmt->fetchAll();
    
    $downloaded = 0;
    $failed = 0;
    
    foreach ($pending as $item) {
        if (downloadAndSaveImage($item, $pdo)) {
            $downloaded++;
        } else {
            $failed++;
        }
    }
    
    $success = "Downloaded $downloaded images, $failed failed";
    $_SESSION['import_success'] = $success;
    
    header("Location: import_product_images.php");
    exit;
}

// Handle single image download
if ($action === 'download_single' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM product_image_queue WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $item = $stmt->fetch();
    
    if ($item && downloadAndSaveImage($item, $pdo)) {
        $success = "Image downloaded successfully";
    } else {
        $error = "Failed to download image";
    }
}

// Process image mapping CSV
function processImageMappingCSV($filepath, $pdo) {
    $result = [
        'success' => false,
        'queued' => 0,
        'error' => ''
    ];
    
    if (($handle = fopen($filepath, 'r')) !== FALSE) {
        // Get headers
        $headers = fgetcsv($handle);
        $headers = array_map('strtolower', $headers);
        
        // Check required columns
        $required = ['product_identifier', 'image_url'];
        $missing = array_diff($required, $headers);
        
        if (empty($missing)) {
            $row = 1;
            $queued = 0;
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_data = array_combine($headers, $data);
                
                // Find product by identifier
                $product_id = findProductByIdentifier($row_data['product_identifier'], $pdo);
                
                if ($product_id) {
                    // Insert into queue
                    $stmt = $pdo->prepare("
                        INSERT INTO product_image_queue 
                        (product_id, product_identifier, identifier_type, image_url, is_main, display_order, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    $stmt->execute([
                        $product_id,
                        $row_data['product_identifier'],
                        $row_data['identifier_type'] ?? 'name',
                        $row_data['image_url'],
                        $row_data['is_main'] ?? 0,
                        $row_data['display_order'] ?? 1
                    ]);
                    
                    $queued++;
                }
                
                $row++;
            }
            
            $result['success'] = true;
            $result['queued'] = $queued;
        } else {
            $result['error'] = "Missing columns: " . implode(', ', $missing);
        }
        
        fclose($handle);
    } else {
        $result['error'] = "Could not open CSV file";
    }
    
    return $result;
}

// Find product by identifier
function findProductByIdentifier($identifier, $pdo) {
    try {
        // Try by ID
        if (is_numeric($identifier)) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$identifier]);
            if ($row = $stmt->fetch()) return $row['id'];
        }
        
        // Try by SKU
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$identifier]);
        if ($row = $stmt->fetch()) return $row['id'];
        
        // Try by EAN
        $stmt = $pdo->prepare("SELECT id FROM products WHERE ean = ?");
        $stmt->execute([$identifier]);
        if ($row = $stmt->fetch()) return $row['id'];
        
        // Try by name
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name LIKE ?");
        $stmt->execute(["%$identifier%"]);
        if ($row = $stmt->fetch()) return $row['id'];
        
    } catch (Exception $e) {
        error_log("Error finding product: " . $e->getMessage());
    }
    
    return null;
}

// Get statistics
$stats = getImageStats($pdo);

// Get products without images
$stmt = $pdo->query("
    SELECT p.id, p.name, p.ean, p.sku 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id 
    WHERE pi.id IS NULL 
    LIMIT 20
");
$products_no_images = $stmt->fetchAll();

// Get pending downloads
$stmt = $pdo->query("
    SELECT piq.*, p.name as product_name 
    FROM product_image_queue piq 
    LEFT JOIN products p ON piq.product_id = p.id 
    WHERE piq.status = 'pending'
    ORDER BY piq.created_at DESC
");
$pending_downloads = $stmt->fetchAll();

require_once 'header.php';
?>

<div class="admin-main">
    <div class="page-header">
        <h1><i class="fas fa-images"></i> Import Product Images</h1>
        <p>Upload and manage product images in bulk</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['import_success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['import_success']) ?>
        </div>
        <?php if (isset($_SESSION['import_details'])): ?>
            <div class="card" style="margin-bottom: 1rem;">
                <h4>Import Details</h4>
                <div style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <?php foreach ($_SESSION['import_details'] as $detail): ?>
                        <div style="padding: 0.25rem 0; border-bottom: 1px solid #e5e7eb;">
                            <?= $detail ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php 
        // Clear session
        unset($_SESSION['import_success']);
        unset($_SESSION['import_details']);
        unset($_SESSION['import_result']);
        ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total_products']) ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card success">
            <div class="stat-value"><?= number_format($stats['products_with_images']) ?></div>
            <div class="stat-label">With Images</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?= number_format($stats['products_without_images']) ?></div>
            <div class="stat-label">Missing Images</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?= number_format($stats['total_images']) ?></div>
            <div class="stat-label">Total Images</div>
        </div>
        <div class="stat-card pending">
            <div class="stat-value"><?= number_format($stats['pending_downloads']) ?></div>
            <div class="stat-label">Pending Downloads</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-btn <?= $action === 'upload' ? 'active' : '' ?>" onclick="showTab('upload')">
                <i class="fas fa-file-archive"></i> 📦 Upload ZIP
            </button>
            <button class="tab-btn" onclick="showTab('csv')">
                <i class="fas fa-table"></i> 📊 Upload Mapping CSV
            </button>
            <button class="tab-btn" onclick="showTab('manual')">
                <i class="fas fa-hand-pointer"></i> 🔗 Manual Mapping
            </button>
            <button class="tab-btn" onclick="showTab('queue')">
                <i class="fas fa-clock"></i> ⏳ Image Queue (<?= $stats['pending_downloads'] ?>)
            </button>
        </div>
    </div>

    <!-- Tab: ZIP Upload -->
    <div id="tab-upload" class="tab-content <?= $action === 'upload' ? 'active' : '' ?>">
        <div class="card">
            <h2><i class="fas fa-file-archive"></i> Upload ZIP with Images</h2>
            <p>Upload a ZIP file containing product images. Name files using product EAN, SKU, or ID.</p>
            
            <div class="info-box">
                <h4>📁 Naming Convention:</h4>
                <ul>
                    <li><strong>By EAN:</strong> 2091465262179.jpg, 2091465262179_1.jpg (multiple images)</li>
                    <li><strong>By SKU:</strong> SKU-001.png, SKU-001_2.jpg</li>
                    <li><strong>By Product ID:</strong> 56_main.jpg, 56_1.jpg</li>
                    <li><strong>By Product Name:</strong> compact-printer-air.jpg (slug format)</li>
                </ul>
                <p class="note">First image (or image with "_main") will be set as main product image.</p>
            </div>

            <form method="post" enctype="multipart/form-data" class="upload-form">
                <div class="upload-area" id="zipUploadArea">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drag & Drop ZIP file here</h3>
                    <p>or click to browse</p>
                    <input type="file" name="images_zip" id="images_zip" accept=".zip" style="display: none;" required>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('images_zip').click()">
                        <i class="fas fa-folder-open"></i> Choose ZIP File
                    </button>
                    <span id="zip_file_name" class="file-name"></span>
                </div>
                
                <div class="options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="auto_match" value="1" checked>
                        Auto-match images to products (by EAN, SKU, name)
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="create_thumbs" value="1" checked>
                        Create thumbnails automatically
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    <i class="fas fa-upload"></i> Upload and Process
                </button>
            </form>
        </div>

        <div class="card">
            <h3>Example ZIP Structure</h3>
            <pre class="code-block">
products-images.zip
├── 2091465262179.jpg                 (Main image for EAN: 2091465262179)
├── 2091465262179_1.jpg                (Additional image)
├── 2091465262179_2.jpg                (Additional image)
├── SKU-001.png                        (Image for product with SKU-001)
├── SKU-001_detail.jpg                  (Additional image)
├── 56_main.jpg                         (Main image for product ID 56)
├── 56_side.jpg                         (Additional image)
├── compact-printer-air.jpg              (Image for product with matching slug)
└── compact-printer-air_2.jpg            (Additional image)
            </pre>
        </div>
    </div>

    <!-- Tab: CSV Mapping -->
    <div id="tab-csv" class="tab-content">
        <div class="card">
            <h2><i class="fas fa-table"></i> Upload Image Mapping CSV</h2>
            <p>Upload a CSV file that maps products to image URLs.</p>

            <div class="info-box">
                <h4>📋 CSV Format:</h4>
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>product_identifier</th>
                            <th>identifier_type</th>
                            <th>image_url</th>
                            <th>is_main</th>
                            <th>display_order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>2091465262179</td>
                            <td>ean</td>
                            <td>https://example.com/images/product1.jpg</td>
                            <td>1</td>
                            <td>1</td>
                        </tr>
                        <tr>
                            <td>SKU-001</td>
                            <td>sku</td>
                            <td>https://example.com/images/product2.jpg</td>
                            <td>1</td>
                            <td>1</td>
                        </tr>
                        <tr>
                            <td>56</td>
                            <td>id</td>
                            <td>https://example.com/images/product3.jpg</td>
                            <td>0</td>
                            <td>2</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin: 1rem 0;">
                <a href="download_sample_image_csv.php" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Download Sample CSV
                </a>
            </div>

            <form method="post" enctype="multipart/form-data">
                <div class="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Upload Mapping CSV</h3>
                    <input type="file" name="mapping_csv" accept=".csv" required>
                </div>
                
                <div class="options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="download_images" value="1" checked>
                        Download images from URLs automatically
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    <i class="fas fa-upload"></i> Process Mapping
                </button>
            </form>
        </div>
    </div>

    <!-- Tab: Manual Mapping -->
    <div id="tab-manual" class="tab-content">
        <div class="card">
            <h2><i class="fas fa-hand-pointer"></i> Manual Image Upload</h2>
            <p>Manually upload images for products missing images.</p>

            <?php if (empty($products_no_images)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> 🎉 All products have images!
                </div>
            <?php else: ?>
                <h3>Products Missing Images (<?= count($products_no_images) ?>)</h3>
                
                <div class="table-responsive">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>EAN</th>
                                <th>SKU</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products_no_images as $product): ?>
                            <tr>
                                <td><?= $product['id'] ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td><?= $product['ean'] ?: '-' ?></td>
                                <td><?= $product['sku'] ?: '-' ?></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm" onclick="showImageUpload(<?= $product['id'] ?>)">
                                        <i class="fas fa-upload"></i> Upload
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Image Queue -->
    <div id="tab-queue" class="tab-content">
        <div class="card">
            <h2><i class="fas fa-clock"></i> Pending Image Downloads</h2>
            
            <?php if (empty($pending_downloads)): ?>
                <p>No pending image downloads.</p>
            <?php else: ?>
                <div class="queue-actions">
                    <a href="?action=download_all" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download All (<?= count($pending_downloads) ?>)
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Identifier</th>
                                <th>Image URL</th>
                                <th>Main</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_downloads as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name'] ?? 'Unknown') ?></td>
                                <td><?= $item['product_identifier'] ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($item['image_url']) ?>" target="_blank" class="url-link">
                                        <?= substr($item['image_url'], 0, 50) ?>...
                                    </a>
                                </td>
                                <td><?= $item['is_main'] ? '✅' : '❌' ?></td>
                                <td>
                                    <a href="?action=download_single&id=<?= $item['id'] ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Upload Modal -->
    <div id="imageUploadModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Upload Product Images</h2>
            <form id="manualUploadForm" enctype="multipart/form-data" action="upload_product_images.php" method="post">
                <input type="hidden" name="product_id" id="modal_product_id">
                
                <div class="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Select Images</h3>
                    <input type="file" name="images[]" multiple accept="image/*" required>
                </div>
                
                <div class="options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="set_first_as_main" value="1" checked>
                        Set first image as main product image
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    <i class="fas fa-upload"></i> Upload Images
                </button>
            </form>
        </div>
    </div>
</div>

<style>
/* Main Admin Layout */
.admin-main {
    margin-left: 260px;
    margin-top: 70px;
    padding: 2rem;
    background: #f8fafc;
    min-height: calc(100vh - 70px);
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #6b7280;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #4f46e5;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: #6b7280;
    font-size: 0.875rem;
}

.stat-card.success .stat-value { color: #10b981; }
.stat-card.warning .stat-value { color: #f59e0b; }
.stat-card.info .stat-value { color: #3b82f6; }
.stat-card.pending .stat-value { color: #8b5cf6; }

/* Tab Container */
.tab-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 1.5rem;
    border: 1px solid #e5e7eb;
}

.tab-buttons {
    display: flex;
    flex-wrap: wrap;
    border-bottom: 1px solid #e5e7eb;
}

.tab-btn {
    padding: 1rem 2rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1rem;
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
    display: none;
    animation: fadeIn 0.3s ease;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
}

/* Info Box */
.info-box {
    background: #f0f9ff;
    border-left: 4px solid #4f46e5;
    padding: 1.5rem;
    border-radius: 8px;
    margin: 1.5rem 0;
}

.info-box h4 {
    color: #1f2937;
    margin-bottom: 0.75rem;
}

.info-box ul {
    margin-left: 1.5rem;
    line-height: 1.6;
}

.info-box .note {
    color: #6b7280;
    font-size: 0.875rem;
    margin-top: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid #dbeafe;
}

/* Upload Area */
.upload-area {
    border: 3px dashed #d1d5db;
    border-radius: 12px;
    padding: 3rem;
    text-align: center;
    background: #f9fafb;
    transition: all 0.3s;
    margin-bottom: 1.5rem;
}

.upload-area:hover {
    border-color: #4f46e5;
    background: #f3f4f6;
}

.upload-area i {
    font-size: 4rem;
    color: #4f46e5;
    margin-bottom: 1rem;
}

.upload-area h3 {
    font-size: 1.25rem;
    color: #374151;
    margin-bottom: 0.5rem;
}

.upload-area p {
    color: #6b7280;
    margin-bottom: 1rem;
}

.file-name {
    display: block;
    margin-top: 1rem;
    color: #4f46e5;
    font-weight: 500;
}

/* Options */
.options {
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
    cursor: pointer;
    color: #374151;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4f46e5;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-large {
    width: 100%;
    justify-content: center;
    padding: 1rem;
}

.btn-sm {
    padding: 0.4rem 1rem;
    font-size: 0.875rem;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
}

.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.preview-table th {
    background: #f3f4f6;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
}

.preview-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.preview-table tr:hover {
    background: #f9fafb;
}

/* Code Block */
.code-block {
    background: #1f2937;
    color: #e5e7eb;
    padding: 1.5rem;
    border-radius: 8px;
    font-family: monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    overflow-x: auto;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-left: 4px solid;
}

.alert-danger {
    background: #fee2e2;
    border-left-color: #ef4444;
    color: #991b1b;
}

.alert-success {
    background: #d1fae5;
    border-left-color: #10b981;
    color: #065f46;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    position: relative;
    max-height: 80vh;
    overflow-y: auto;
}

.close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
}

.close:hover {
    color: #374151;
}

/* Success Message */
.success-message {
    text-align: center;
    padding: 3rem;
    background: #d1fae5;
    border-radius: 8px;
    color: #065f46;
    font-size: 1.25rem;
}

.success-message i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

/* Queue Actions */
.queue-actions {
    margin-bottom: 1.5rem;
}

/* URL Link */
.url-link {
    color: #4f46e5;
    text-decoration: none;
    font-size: 0.875rem;
}

.url-link:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 1024px) {
    .admin-main {
        margin-left: 0;
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .tab-btn {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
    
    .upload-area {
        padding: 2rem;
    }
    
    .upload-area i {
        font-size: 3rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-buttons {
        flex-direction: column;
    }
    
    .tab-btn {
        width: 100%;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Tab switching
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Modal functions
function showImageUpload(productId) {
    document.getElementById('modal_product_id').value = productId;
    document.getElementById('imageUploadModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('imageUploadModal').style.display = 'none';
}

// File upload preview
document.getElementById('images_zip')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : '';
    document.getElementById('zip_file_name').textContent = fileName;
});

// Drag and drop
const uploadArea = document.getElementById('zipUploadArea');
if (uploadArea) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.style.borderColor = '#4f46e5';
        uploadArea.style.background = '#f3f4f6';
    }
    
    function unhighlight() {
        uploadArea.style.borderColor = '#d1d5db';
        uploadArea.style.background = '#f9fafb';
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0 && files[0].name.endsWith('.zip')) {
            document.getElementById('images_zip').files = files;
            document.getElementById('zip_file_name').textContent = files[0].name;
        } else {
            alert('Please drop a ZIP file.');
        }
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('imageUploadModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>
