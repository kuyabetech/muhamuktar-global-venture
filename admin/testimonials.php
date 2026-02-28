<?php
// admin/testimonials.php - Testimonials Management

$page_title = "Testimonials";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS testimonials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255),
            customer_photo VARCHAR(255),
            company VARCHAR(255),
            position VARCHAR(255),
            content TEXT NOT NULL,
            rating INT CHECK (rating BETWEEN 1 AND 5),
            product_id INT,
            order_id INT,
            status ENUM('approved', 'pending', 'rejected', 'featured') DEFAULT 'pending',
            featured TINYINT(1) DEFAULT 0,
            show_on_homepage TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            approved_at DATETIME,
            approved_by INT,
            INDEX idx_status (status),
            INDEX idx_featured (featured),
            INDEX idx_product (product_id),
            INDEX idx_homepage (show_on_homepage)
        )
    ");
} catch (Exception $e) {
    error_log("Testimonials table error: " . $e->getMessage());
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
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $rating = (int)($_POST['rating'] ?? 5);
        $product_id = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $status = $_POST['status'] ?? 'pending';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $show_on_homepage = isset($_POST['show_on_homepage']) ? 1 : 0;

        $errors = [];
        if (empty($customer_name)) {
            $errors[] = "Customer name is required";
        }
        if (empty($content)) {
            $errors[] = "Testimonial content is required";
        }
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Rating must be between 1 and 5";
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO testimonials (customer_name, customer_email, company, position, content, 
                                                rating, product_id, status, featured, show_on_homepage, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $customer_name, $customer_email, $company, $position, $content,
                        $rating, $product_id, $status, $featured, $show_on_homepage
                    ]);
                    
                    // Handle photo upload
                    if (!empty($_FILES['customer_photo']['name'])) {
                        $testimonial_id = $pdo->lastInsertId();
                        $upload_dir = '../uploads/testimonials/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        $ext = strtolower(pathinfo($_FILES['customer_photo']['name'], PATHINFO_EXTENSION));
                        $filename = $testimonial_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['customer_photo']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE testimonials SET customer_photo = ? WHERE id = ?");
                            $stmt->execute([$filename, $testimonial_id]);
                        }
                    }
                    
                    $success_msg = "Testimonial added successfully";
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE testimonials SET 
                            customer_name = ?, customer_email = ?, company = ?, position = ?, 
                            content = ?, rating = ?, product_id = ?, status = ?, 
                            featured = ?, show_on_homepage = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $customer_name, $customer_email, $company, $position, $content,
                        $rating, $product_id, $status, $featured, $show_on_homepage, $id
                    ]);
                    
                    // Handle photo upload
                    if (!empty($_FILES['customer_photo']['name'])) {
                        $upload_dir = '../uploads/testimonials/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        
                        // Delete old photo
                        $stmt = $pdo->prepare("SELECT customer_photo FROM testimonials WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_photo = $stmt->fetchColumn();
                        if ($old_photo && file_exists($upload_dir . $old_photo)) {
                            unlink($upload_dir . $old_photo);
                        }
                        
                        $ext = strtolower(pathinfo($_FILES['customer_photo']['name'], PATHINFO_EXTENSION));
                        $filename = $id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['customer_photo']['tmp_name'], $dest)) {
                            $stmt = $pdo->prepare("UPDATE testimonials SET customer_photo = ? WHERE id = ?");
                            $stmt->execute([$filename, $id]);
                        }
                    }
                    
                    $success_msg = "Testimonial updated successfully";
                    
                } elseif ($_POST['action'] === 'delete' && $id > 0) {
                    // Delete photo
                    $stmt = $pdo->prepare("SELECT customer_photo FROM testimonials WHERE id = ?");
                    $stmt->execute([$id]);
                    $photo = $stmt->fetchColumn();
                    if ($photo && file_exists('../uploads/testimonials/' . $photo)) {
                        unlink('../uploads/testimonials/' . $photo);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM testimonials WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_msg = "Testimonial deleted successfully";
                }
                
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }
    
    if (!empty($success_msg)) {
        header("Location: testimonials.php?success=" . urlencode($success_msg));
    } else {
        header("Location: testimonials.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Handle status update
if ($action === 'approve' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE testimonials SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
    $stmt->execute([$_SESSION['user_id'], $id]);
    header("Location: testimonials.php?success=Testimonial approved");
    exit;
}

if ($action === 'reject' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE testimonials SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: testimonials.php?success=Testimonial rejected");
    exit;
}

if ($action === 'feature' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE testimonials SET featured = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: testimonials.php?success=Testimonial featured");
    exit;
}

if ($action === 'unfeature' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE testimonials SET featured = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: testimonials.php?success=Testimonial unfeatured");
    exit;
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(customer_name LIKE ? OR customer_email LIKE ? OR company LIKE ? OR content LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch testimonials
$sql = "
    SELECT t.*, p.name AS product_name,
           u.full_name AS approver_name
    FROM testimonials t
    LEFT JOIN products p ON t.product_id = p.id
    LEFT JOIN users u ON t.approved_by = u.id
    $where_sql
    ORDER BY t.created_at DESC
";

$testimonials = $pdo->prepare($sql);
$testimonials->execute($params);
$testimonials = $testimonials->fetchAll();

// Get stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'approved'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'pending'")->fetchColumn(),
    'rejected' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE status = 'rejected'")->fetchColumn(),
    'featured' => $pdo->query("SELECT COUNT(*) FROM testimonials WHERE featured = 1")->fetchColumn(),
    'avg_rating' => $pdo->query("SELECT AVG(rating) FROM testimonials WHERE status = 'approved'")->fetchColumn() ?: 0,
];

// Get products for dropdown
$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();

// Get testimonial for editing
$edit_testimonial = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id = ?");
    $stmt->execute([$id]);
    $edit_testimonial = $stmt->fetch();
}
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-star"></i> Testimonials
            </h1>
            <p style="color: var(--admin-gray);">Manage customer reviews and testimonials</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Testimonial
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-comments"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['approved']) ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['pending']) ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['avg_rating'], 1) ?></div>
                    <div class="stat-label">Avg Rating</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-star"></i></div>
            </div>
        </div>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Testimonial Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_testimonial ? 'edit' : 'plus' ?>"></i>
                <?= $edit_testimonial ? 'Edit Testimonial' : 'Add New Testimonial' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_testimonial ? 'edit' : 'add' ?>">
                <?php if ($edit_testimonial): ?>
                    <input type="hidden" name="id" value="<?= $edit_testimonial['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" 
                               value="<?= htmlspecialchars($edit_testimonial['customer_name'] ?? '') ?>" 
                               required class="form-control" placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Customer Email</label>
                        <input type="email" name="customer_email" 
                               value="<?= htmlspecialchars($edit_testimonial['customer_email'] ?? '') ?>" 
                               class="form-control" placeholder="john@example.com">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" 
                               value="<?= htmlspecialchars($edit_testimonial['company'] ?? '') ?>" 
                               class="form-control" placeholder="Company Name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" 
                               value="<?= htmlspecialchars($edit_testimonial['position'] ?? '') ?>" 
                               class="form-control" placeholder="CEO, Manager, etc.">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Testimonial *</label>
                    <textarea name="content" rows="5" class="form-control" 
                              placeholder="What did the customer say?"><?= htmlspecialchars($edit_testimonial['content'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-control">
                            <option value="5" <?= ($edit_testimonial['rating'] ?? 5) == 5 ? 'selected' : '' ?>>5 Stars - Excellent</option>
                            <option value="4" <?= ($edit_testimonial['rating'] ?? 5) == 4 ? 'selected' : '' ?>>4 Stars - Very Good</option>
                            <option value="3" <?= ($edit_testimonial['rating'] ?? 5) == 3 ? 'selected' : '' ?>>3 Stars - Good</option>
                            <option value="2" <?= ($edit_testimonial['rating'] ?? 5) == 2 ? 'selected' : '' ?>>2 Stars - Fair</option>
                            <option value="1" <?= ($edit_testimonial['rating'] ?? 5) == 1 ? 'selected' : '' ?>>1 Star - Poor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Related Product</label>
                        <select name="product_id" class="form-control">
                            <option value="">No specific product</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= ($edit_testimonial['product_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="pending" <?= ($edit_testimonial['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= ($edit_testimonial['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= ($edit_testimonial['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Customer Photo</label>
                        <?php if ($edit_testimonial && !empty($edit_testimonial['customer_photo'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?= BASE_URL ?>uploads/testimonials/<?= htmlspecialchars($edit_testimonial['customer_photo']) ?>" 
                                     alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--admin-primary);">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="customer_photo" accept="image/*" class="form-control">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="featured" value="1" 
                                   <?= ($edit_testimonial['featured'] ?? 0) ? 'checked' : '' ?>>
                            Featured Testimonial
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="show_on_homepage" value="1" 
                                   <?= ($edit_testimonial['show_on_homepage'] ?? 0) ? 'checked' : '' ?>>
                            Show on Homepage
                        </label>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_testimonial ? 'Update Testimonial' : 'Add Testimonial' ?>
                    </button>
                    <?php if ($edit_testimonial): ?>
                        <a href="testimonials.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Filters -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="get">
                <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem;">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by name, email, company, or content..." class="form-control">
                    
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="testimonials.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Status Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="?status=approved" class="btn <?= $status_filter === 'approved' ? 'btn-primary' : 'btn-secondary' ?>">
                Approved (<?= $stats['approved'] ?>)
            </a>
            <a href="?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
                Pending (<?= $stats['pending'] ?>)
            </a>
            <a href="?status=rejected" class="btn <?= $status_filter === 'rejected' ? 'btn-primary' : 'btn-secondary' ?>">
                Rejected (<?= $stats['rejected'] ?>)
            </a>
            <a href="testimonials.php" class="btn btn-secondary">All Testimonials</a>
        </div>

        <!-- Testimonials List -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Testimonial</th>
                            <th>Rating</th>
                            <th>Product</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Options</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($testimonials)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-star" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                    No testimonials found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($testimonials as $t): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <?php if (!empty($t['customer_photo'])): ?>
                                                <img src="<?= BASE_URL ?>uploads/testimonials/<?= htmlspecialchars($t['customer_photo']) ?>" 
                                                     alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600;"><?= htmlspecialchars($t['customer_name']) ?></div>
                                                <?php if ($t['company']): ?>
                                                    <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                                        <?= htmlspecialchars($t['company']) ?>
                                                        <?= $t['position'] ? ' • ' . htmlspecialchars($t['position']) : '' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="max-width: 300px;">
                                            <div style="font-size: 0.875rem; color: var(--admin-gray); margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($t['customer_email'] ?? '') ?>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars(substr($t['content'], 0, 150)) ?>...
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="color: #fbbf24;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= $t['rating'] ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($t['product_name'] ?? '-') ?></td>
                                    <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $t['status'] ?>">
                                            <?= ucfirst($t['status']) ?>
                                        </span>
                                        <?php if ($t['featured']): ?>
                                            <span class="featured-badge">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['show_on_homepage']): ?>
                                            <i class="fas fa-home" title="Shows on homepage"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($t['status'] === 'pending'): ?>
                                                <a href="?action=approve&id=<?= $t['id'] ?>" 
                                                   class="btn btn-success btn-sm" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?action=reject&id=<?= $t['id'] ?>" 
                                                   class="btn btn-warning btn-sm" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$t['featured']): ?>
                                                <a href="?action=feature&id=<?= $t['id'] ?>" 
                                                   class="btn btn-info btn-sm" title="Feature">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?action=unfeature&id=<?= $t['id'] ?>" 
                                                   class="btn btn-secondary btn-sm" title="Unfeature">
                                                    <i class="fas fa-star-o"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?action=edit&id=<?= $t['id'] ?>" 
                                               class="btn btn-secondary btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <form method="post" style="display: inline;" 
                                                  onsubmit="return confirm('Delete testimonial from <?= addslashes($t['customer_name']) ?>?')">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
    margin-right: 0.25rem;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-featured {
    background: #dbeafe;
    color: #1e40af;
}

.featured-badge {
    background: #fbbf24;
    color: #92400e;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.25rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.fa-star, .fa-star-o {
    color: #fbbf24;
    margin-right: 2px;
}
</script>

