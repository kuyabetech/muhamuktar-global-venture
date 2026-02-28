<?php
// admin/users.php - User Management

$page_title = "User Management";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize database table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            address TEXT,
            city VARCHAR(100),
            state VARCHAR(100),
            country VARCHAR(100),
            postal_code VARCHAR(20),
            role ENUM('admin','manager','customer','vendor') DEFAULT 'customer',
            status ENUM('active','inactive','banned') DEFAULT 'active',
            email_verified TINYINT(1) DEFAULT 0,
            last_login DATETIME,
            last_ip VARCHAR(45),
            profile_image VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role),
            INDEX idx_status (status),
            INDEX idx_email (email)
        )
    ");

    // Create user activity log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        )
    ");
} catch (Exception $e) {
    error_log("Users table error: " . $e->getMessage());
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
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle user creation/editing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $role = $_POST['role'] ?? 'customer';
        $status = $_POST['status'] ?? 'active';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errors = [];

        // Validate required fields
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        }
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Check if email exists
        $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ?" . ($id ? " AND id != ?" : ""));
        if ($id) {
            $check_email->execute([$email, $id]);
        } else {
            $check_email->execute([$email]);
        }
        if ($check_email->fetch()) {
            $errors[] = "Email already exists";
        }

        // Password validation for new users or password change
        if ($_POST['action'] === 'add' || !empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long";
            }
            if ($password !== $confirm_password) {
                $errors[] = "Passwords do not match";
            }
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, password, phone, address, city, state, country, postal_code, role, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$full_name, $email, $hashed_password, $phone, $address, $city, $state, $country, $postal_code, $role, $status]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Handle profile image upload
                    if (!empty($_FILES['profile_image']['name'])) {
                        uploadProfileImage($user_id, $_FILES['profile_image']);
                    }
                    
                    // Log activity
                    logUserActivity($user_id, "User account created by admin", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    
                    $success_msg = "User created successfully";
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    // Build update query dynamically
                    $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?, role = ?, status = ?";
                    $params = [$full_name, $email, $phone, $address, $city, $state, $country, $postal_code, $role, $status];

                    // Update password if provided
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql .= ", password = ?";
                        $params[] = $hashed_password;
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $id;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    // Handle profile image upload
                    if (!empty($_FILES['profile_image']['name'])) {
                        // Delete old image
                        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_image = $stmt->fetchColumn();
                        if ($old_image && file_exists('../uploads/profiles/' . $old_image)) {
                            unlink('../uploads/profiles/' . $old_image);
                        }
                        
                        uploadProfileImage($id, $_FILES['profile_image']);
                    }
                    
                    // Log activity
                    logUserActivity($id, "User account updated by admin", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    
                    $success_msg = "User updated successfully";
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
        header("Location: users.php?success=" . urlencode($success_msg));
    } else {
        header("Location: users.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Handle delete user
if ($action === 'delete' && $id > 0) {
    // Prevent deleting yourself
    if ($id == $_SESSION['user_id']) {
        $error_msg = "You cannot delete your own account";
    } else {
        try {
            // Delete profile image
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetchColumn();
            if ($image && file_exists('../uploads/profiles/' . $image)) {
                unlink('../uploads/profiles/' . $image);
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success_msg = "User deleted successfully";
        } catch (Exception $e) {
            $error_msg = "Error deleting user: " . $e->getMessage();
        }
    }
    header("Location: users.php?success=" . urlencode($success_msg));
    exit;
}

// Handle bulk actions
if ($action === 'bulk' && isset($_POST['selected'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $selected = $_POST['selected'];
        $bulk_action = $_POST['bulk_action'] ?? '';
        
        if (!empty($selected) && !empty($bulk_action)) {
            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            
            try {
                switch ($bulk_action) {
                    case 'activate':
                        $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id IN ($placeholders)");
                        $stmt->execute($selected);
                        $success_msg = "Selected users activated";
                        break;
                        
                    case 'deactivate':
                        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id IN ($placeholders)");
                        $stmt->execute($selected);
                        $success_msg = "Selected users deactivated";
                        break;
                        
                    case 'make_admin':
                        $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id IN ($placeholders)");
                        $stmt->execute($selected);
                        $success_msg = "Selected users made admin";
                        break;
                        
                    case 'make_customer':
                        $stmt = $pdo->prepare("UPDATE users SET role = 'customer' WHERE id IN ($placeholders)");
                        $stmt->execute($selected);
                        $success_msg = "Selected users made customer";
                        break;
                        
                    case 'delete':
                        // Delete profile images first
                        foreach ($selected as $user_id) {
                            if ($user_id != $_SESSION['user_id']) {
                                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $image = $stmt->fetchColumn();
                                if ($image && file_exists('../uploads/profiles/' . $image)) {
                                    unlink('../uploads/profiles/' . $image);
                                }
                            }
                        }
                        
                        // Remove current user from selection
                        $selected = array_filter($selected, function($id) {
                            return $id != $_SESSION['user_id'];
                        });
                        
                        if (!empty($selected)) {
                            $placeholders = implode(',', array_fill(0, count($selected), '?'));
                            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                            $stmt->execute($selected);
                            $success_msg = "Selected users deleted";
                        }
                        break;
                }
            } catch (Exception $e) {
                $error_msg = "Error performing bulk action: " . $e->getMessage();
            }
        }
    }
    header("Location: users.php?success=" . urlencode($success_msg));
    exit;
}

// Upload profile image helper
function uploadProfileImage($user_id, $file) {
    $upload_dir = '../uploads/profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $user_id . '_' . uniqid() . '.' . $ext;
    $dest = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $stmt->execute([$filename, $user_id]);
        return true;
    }
    return false;
}

// Log user activity
function logUserActivity($user_id, $action, $ip, $user_agent) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $ip, $user_agent]);
}

// Build search query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

if (!empty($role_filter)) {
    $where[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM users $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Fetch users
$sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) AS order_count,
           (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status != 'cancelled') AS total_spent
    FROM users u
    $where_sql
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn(),
    'banned' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn(),
    'admins' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'managers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'manager'")->fetchColumn(),
    'customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
    'vendors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor'")->fetchColumn(),
    'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'new_week' => $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
];

// Get user for editing
$edit_user = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch();
    
    if ($edit_user) {
        // Get user activity log
        $stmt = $pdo->prepare("
            SELECT * FROM user_activity_log 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$id]);
        $user_activity = $stmt->fetchAll();
    }
}

// Get roles for dropdown
$roles = ['admin', 'manager', 'customer', 'vendor'];
$statuses = ['active', 'inactive', 'banned'];
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-users"></i> User Management
            </h1>
            <p style="color: var(--admin-gray);">Manage user accounts, roles, and permissions</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
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
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-icon primary"><i class="fas fa-users"></i></div>
            </div>
            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                <?= number_format($stats['new_today']) ?> new today
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['active']) ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
            </div>
            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                <?= number_format($stats['inactive']) ?> inactive
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['customers']) ?></div>
                    <div class="stat-label">Customers</div>
                </div>
                <div class="stat-icon warning"><i class="fas fa-user"></i></div>
            </div>
            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                <?= number_format($stats['admins']) ?> admins
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div>
                    <div class="stat-value"><?= number_format($stats['new_week']) ?></div>
                    <div class="stat-label">New (7 days)</div>
                </div>
                <div class="stat-icon info"><i class="fas fa-user-plus"></i></div>
            </div>
        </div>
    </div>

    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit User Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_user ? 'user-edit' : 'user-plus' ?>"></i>
                <?= $edit_user ? 'Edit User' : 'Create New User' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_user ? 'edit' : 'add' ?>">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" 
                               value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>" 
                               required class="form-control" placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" 
                               value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" 
                               required class="form-control" placeholder="john@example.com">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" 
                               value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>" 
                               class="form-control" placeholder="+234 123 456 7890">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Profile Image</label>
                        <?php if ($edit_user && !empty($edit_user['profile_image'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?= BASE_URL ?>uploads/profiles/<?= htmlspecialchars($edit_user['profile_image']) ?>" 
                                     alt="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="profile_image" accept="image/*" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" rows="2" class="form-control" 
                              placeholder="Street address"><?= htmlspecialchars($edit_user['address'] ?? '') ?></textarea>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" 
                               value="<?= htmlspecialchars($edit_user['city'] ?? '') ?>" 
                               class="form-control" placeholder="Lagos">
                    </div>

                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" 
                               value="<?= htmlspecialchars($edit_user['state'] ?? '') ?>" 
                               class="form-control" placeholder="Lagos State">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" 
                               value="<?= htmlspecialchars($edit_user['country'] ?? 'Nigeria') ?>" 
                               class="form-control" placeholder="Nigeria">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" 
                               value="<?= htmlspecialchars($edit_user['postal_code'] ?? '') ?>" 
                               class="form-control" placeholder="100001">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role ?>" <?= ($edit_user['role'] ?? 'customer') === $role ? 'selected' : '' ?>>
                                    <?= ucfirst($role) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status ?>" <?= ($edit_user['status'] ?? 'active') === $status ? 'selected' : '' ?>>
                                    <?= ucfirst($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <?= $edit_user ? 'New Password (leave blank to keep current)' : 'Password *' ?>
                        </label>
                        <input type="password" name="password" 
                               <?= !$edit_user ? 'required' : '' ?> 
                               class="form-control" placeholder="••••••••">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" 
                               <?= !$edit_user ? 'required' : '' ?> 
                               class="form-control" placeholder="••••••••">
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_user ? 'Update User' : 'Create User' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($edit_user && !empty($user_activity)): ?>
                <!-- User Activity Log -->
                <div style="margin-top: 3rem;">
                    <h3 style="margin-bottom: 1.5rem;">Recent Activity</h3>
                    <div style="background: var(--admin-light); padding: 1rem; border-radius: 8px;">
                        <?php foreach ($user_activity as $log): ?>
                            <div style="padding: 0.75rem; border-bottom: 1px solid var(--admin-border);">
                                <div style="display: flex; justify-content: space-between;">
                                    <div>
                                        <span class="activity-action"><?= htmlspecialchars($log['action']) ?></span>
                                        <?php if (!empty($log['details'])): ?>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                                <?= htmlspecialchars($log['details']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                        <?= date('M d, Y H:i', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--admin-gray); margin-top: 0.25rem;">
                                    IP: <?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Filters and Bulk Actions -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="get" id="filterForm">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem;">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by name, email, or phone..." class="form-control">
                    
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role ?>" <?= $role_filter === $role ? 'selected' : '' ?>>
                                <?= ucfirst($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>" <?= $status_filter === $status ? 'selected' : '' ?>>
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="users.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Bulk Actions -->
            <form method="post" action="?action=bulk" id="bulkForm" onsubmit="return confirmBulkAction()">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <select name="bulk_action" class="form-control" style="width: 200px;">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="make_admin">Make Admin</option>
                        <option value="make_customer">Make Customer</option>
                        <option value="delete">Delete</option>
                    </select>
                    
                    <button type="submit" class="btn btn-secondary" id="applyBulkAction" disabled>
                        Apply
                    </button>
                    
                    <div style="margin-left: auto; color: var(--admin-gray);">
                        <span id="selectedCount">0</span> users selected
                    </div>
                </div>

                <!-- Users Table -->
                <div style="overflow-x: auto; margin-top: 2rem;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 30px;"><input type="checkbox" id="selectAll"></th>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Joined</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 3rem;">
                                        <i class="fas fa-users" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                        No users found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected[]" value="<?= $user['id'] ?>" 
                                                   class="user-checkbox" <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <?php if (!empty($user['profile_image'])): ?>
                                                    <img src="<?= BASE_URL ?>uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" 
                                                         alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--admin-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                                                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 600;"><?= htmlspecialchars($user['full_name']) ?></div>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="current-user-badge">You</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($user['email']) ?></div>
                                            <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                                <?= htmlspecialchars($user['phone'] ?? 'No phone') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?= $user['role'] ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $user['status'] ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;"><?= number_format($user['order_count']) ?></td>
                                        <td style="text-align: right;">₦<?= number_format($user['total_spent'] ?? 0, 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        <td><?= $user['last_login'] ? date('M d, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?action=edit&id=<?= $user['id'] ?>" class="btn btn-secondary btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="?action=delete&id=<?= $user['id'] ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Delete user <?= addslashes($user['full_name']) ?>?')"
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
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
                        <a href="?page=1&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>" 
                           class="page-link"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>" 
                           class="page-link"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>" 
                           class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>" 
                           class="page-link"><i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&role=<?= $role_filter ?>&status=<?= $status_filter ?>" 
                           class="page-link"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #f3f4f6;
    color: #374151;
}

.status-banned {
    background: #fee2e2;
    color: #991b1b;
}

.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.role-admin {
    background: #dbeafe;
    color: #1e40af;
}

.role-manager {
    background: #e0e7ff;
    color: #3730a3;
}

.role-customer {
    background: #f3f4f6;
    color: #374151;
}

.role-vendor {
    background: #fef3c7;
    color: #92400e;
}

.current-user-badge {
    background: var(--admin-primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    margin-left: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.activity-action {
    font-weight: 600;
    color: var(--admin-primary);
}

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

/* Checkbox styles */
.user-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All functionality
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
    const selectedCount = document.getElementById('selectedCount');
    const applyBulkAction = document.getElementById('applyBulkAction');
    
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.user-checkbox:checked:not(:disabled)').length;
        selectedCount.textContent = checked;
        applyBulkAction.disabled = checked === 0;
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
    
    // Password match validation
    const password = document.querySelector('[name="password"]');
    const confirmPassword = document.querySelector('[name="confirm_password"]');
    
    if (password && confirmPassword) {
        function validatePassword() {
            if (password.value || confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        }
        
        password.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
    }
});

function confirmBulkAction() {
    const action = document.querySelector('[name="bulk_action"]').value;
    const count = document.querySelectorAll('.user-checkbox:checked').length;
    
    if (count === 0) {
        alert('No users selected');
        return false;
    }
    
    if (action === 'delete') {
        return confirm(`Delete ${count} user(s)? This action cannot be undone.`);
    }
    
    return confirm(`Apply "${action}" to ${count} user(s)?`);
}
</script>

