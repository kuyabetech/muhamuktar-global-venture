<?php
// admin/upload_product_images.php - Handle manual image uploads

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Create necessary directories
$upload_dir = '../uploads/products/';
$thumbs_dir = $upload_dir . 'thumbs/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($thumbs_dir)) {
    mkdir($thumbs_dir, 0755, true);
}

/**
 * Create thumbnail from source image
 */
function createThumbnail($source, $destination, $width = 300, $height = 300) {
    // Check if source file exists
    if (!file_exists($source)) {
        error_log("Source file not found: " . $source);
        return false;
    }
    
    // Get image info
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
        error_log("Failed to create image resource from: " . $source);
        return false;
    }
    
    // Calculate dimensions to maintain aspect ratio
    $src_ratio = $src_width / $src_height;
    $dst_ratio = $width / $height;
    
    if ($src_ratio > $dst_ratio) {
        $new_width = $width;
        $new_height = $width / $src_ratio;
    } else {
        $new_height = $height;
        $new_width = $height * $src_ratio;
    }
    
    // Create thumbnail canvas
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
    
    // Resize image
    imagecopyresampled($dst_img, $src_img, 
                       (int)$dst_x, (int)$dst_y, 0, 0, 
                       (int)$new_width, (int)$new_height, 
                       $src_width, $src_height);
    
    // Save thumbnail based on original type
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
    
    // Clean up
    imagedestroy($src_img);
    imagedestroy($dst_img);
    
    if (!$success) {
        error_log("Failed to save thumbnail to: " . $destination);
    }
    
    return $success;
}

/**
 * Validate image file
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

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $set_first_as_main = isset($_POST['set_first_as_main']);
    
    // Validate product ID
    if (!$product_id) {
        $_SESSION['error'] = "Invalid product ID";
        header("Location: import_product_images.php");
        exit;
    }
    
    // Verify product exists
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $_SESSION['error'] = "Product not found";
            header("Location: import_product_images.php");
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Database error occurred";
        header("Location: import_product_images.php");
        exit;
    }
    
    $uploaded = 0;
    $failed = 0;
    $errors = [];
    $first_uploaded_id = null;
    
    // Process each uploaded file
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
            $failed++;
            $errors[] = "File " . ($key + 1) . ": Upload error code " . $_FILES['images']['error'][$key];
            continue;
        }
        
        // Validate file
        $file_info = [
            'name' => $_FILES['images']['name'][$key],
            'type' => $_FILES['images']['type'][$key],
            'tmp_name' => $tmp_name,
            'error' => $_FILES['images']['error'][$key],
            'size' => $_FILES['images']['size'][$key]
        ];
        
        $validation = validateImageFile($file_info);
        if ($validation !== true) {
            $failed++;
            $errors[] = "File " . $_FILES['images']['name'][$key] . ": " . $validation;
            continue;
        }
        
        // Generate unique filename
        $ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
        $filename = $product_id . '_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($tmp_name, $dest)) {
            // Create thumbnail
            if (createThumbnail($dest, $thumbs_dir . $filename)) {
                try {
                    // Determine if this should be main image
                    $is_main = ($set_first_as_main && $uploaded === 0) ? 1 : 0;
                    
                    // Get next display order
                    $order_stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ?");
                    $order_stmt->execute([$product_id]);
                    $order = $order_stmt->fetchColumn() + 1;
                    
                    // Save to database
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, filename, is_main, display_order, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([$product_id, $filename, $is_main, $order]);
                    $image_id = $pdo->lastInsertId();
                    
                    if ($is_main) {
                        $first_uploaded_id = $image_id;
                    }
                    
                    $uploaded++;
                    
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "File " . $_FILES['images']['name'][$key] . ": Database error - " . $e->getMessage();
                    // Delete the uploaded file if database insert failed
                    if (file_exists($dest)) unlink($dest);
                    if (file_exists($thumbs_dir . $filename)) unlink($thumbs_dir . $filename);
                }
            } else {
                $failed++;
                $errors[] = "File " . $_FILES['images']['name'][$key] . ": Failed to create thumbnail";
                // Clean up
                if (file_exists($dest)) unlink($dest);
            }
        } else {
            $failed++;
            $errors[] = "File " . $_FILES['images']['name'][$key] . ": Failed to move uploaded file";
        }
    }
    
    // If first image is main, unset other main images
    if ($first_uploaded_id) {
        try {
            $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ? AND id != ?")
                ->execute([$product_id, $first_uploaded_id]);
        } catch (Exception $e) {
            error_log("Failed to update main image status: " . $e->getMessage());
        }
    }
    
    // Set session messages
    if ($uploaded > 0) {
        $_SESSION['success'] = "✅ Successfully uploaded $uploaded images for product: " . htmlspecialchars($product['name']);
    }
    
    if ($failed > 0) {
        $_SESSION['warning'] = "⚠️ $failed images failed to upload";
        if (!empty($errors)) {
            $_SESSION['upload_errors'] = $errors;
        }
    }
    
    // Redirect back
    header("Location: import_product_images.php");
    exit;
}

// If not POST request, redirect
header("Location: import_product_images.php");
exit;
?>