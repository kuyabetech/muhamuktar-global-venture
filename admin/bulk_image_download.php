<?php
// admin/bulk_image_download.php - Download images from URLs

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_admin();

set_time_limit(0); // No time limit for large downloads

$action = $_GET['action'] ?? '';

if ($action === 'download_single' && isset($_GET['id'])) {
    downloadSingleImage((int)$_GET['id'], $pdo);
} elseif ($action === 'download_all') {
    downloadAllImages($pdo);
}

function downloadSingleImage($queue_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM product_image_queue WHERE id = ?");
    $stmt->execute([$queue_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        downloadAndSaveImage($item, $pdo);
    }
    
    header("Location: import_product_images.php");
}

function downloadAllImages($pdo) {
    $stmt = $pdo->query("SELECT * FROM product_image_queue WHERE status = 'pending'");
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        downloadAndSaveImage($item, $pdo);
    }
    
    $_SESSION['success'] = "Downloaded " . count($items) . " images";
    header("Location: import_product_images.php");
}

function downloadAndSaveImage($item, $pdo) {
    $upload_dir = '../uploads/products/';
    $thumbs_dir = $upload_dir . 'thumbs/';
    
    // Get image content
    $ch = curl_init($item['image_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $image_data) {
        // Determine file extension
        $ext = pathinfo(parse_url($item['image_url'], PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = 'jpg';
        }
        
        // Generate filename
        $filename = $item['product_id'] . '_' . uniqid() . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        // Save image
        if (file_put_contents($dest, $image_data)) {
            // Create thumbnail
            createThumbnail($dest, $thumbs_dir . $filename);
            
            // Save to product_images
            $img_stmt = $pdo->prepare("
                INSERT INTO product_images (product_id, filename, is_main, display_order) 
                VALUES (?, ?, ?, ?)
            ");
            $img_stmt->execute([
                $item['product_id'], 
                $filename, 
                $item['is_main'],
                $item['display_order']
            ]);
            
            // Update queue status
            $pdo->prepare("UPDATE product_image_queue SET status = 'downloaded', local_path = ? WHERE id = ?")
                ->execute([$filename, $item['id']]);
            
            // If main image, unset others
            if ($item['is_main']) {
                $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ? AND filename != ?")
                    ->execute([$item['product_id'], $filename]);
            }
            
            return true;
        }
    }
    
    // Update status to failed
    $pdo->prepare("UPDATE product_image_queue SET status = 'failed', error_message = ? WHERE id = ?")
        ->execute(["HTTP $http_code", $item['id']]);
    
    return false;
}

function createThumbnail($source, $destination, $width = 300, $height = 300) {
    list($src_width, $src_height, $type) = getimagesize($source);
    
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
        default:
            return false;
    }
    
    $src_ratio = $src_width / $src_height;
    $dst_ratio = $width / $height;
    
    if ($src_ratio > $dst_ratio) {
        $new_width = $width;
        $new_height = $width / $src_ratio;
    } else {
        $new_height = $height;
        $new_width = $height * $src_ratio;
    }
    
    $dst_img = imagecreatetruecolor($width, $height);
    
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($dst_img, false);
        imagesavealpha($dst_img, true);
        $transparent = imagecolorallocatealpha($dst_img, 255, 255, 255, 127);
        imagefilledrectangle($dst_img, 0, 0, $width, $height, $transparent);
    }
    
    $dst_x = ($width - $new_width) / 2;
    $dst_y = ($height - $new_height) / 2;
    
    imagecopyresampled($dst_img, $src_img, $dst_x, $dst_y, 0, 0, 
                       $new_width, $new_height, $src_width, $src_height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst_img, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst_img, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($dst_img, $destination);
            break;
    }
    
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    return true;
}