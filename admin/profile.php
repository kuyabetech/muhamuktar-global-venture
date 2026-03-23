<?php
// admin/profile.php - Fixed Version
$page_title = "My Profile";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data - check which columns exist
try {
    // First, get the actual columns in users table
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define all possible columns we might want
    $possible_columns = [
        'id', 'username', 'email', 'password', 'full_name', 'first_name', 'last_name',
        'phone', 'phone_number', 'mobile', 'address', 'city', 'state', 'country', 
        'postal_code', 'zip', 'role', 'status', 'created_at', 'updated_at', 'last_login'
    ];
    
    // Find which columns actually exist
    $existing_columns = array_intersect($possible_columns, $columns);
    
    // Build select query with only existing columns
    $select_cols = implode(', ', $existing_columns);
    
    $stmt = $pdo->prepare("SELECT $select_cols FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    // Fallback to simple select
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

if (!$user) {
    header("Location: logout.php");
    exit;
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle form submission
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            // Get the actual columns again for update
            $stmt = $pdo->query("DESCRIBE users");
            $db_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build update array dynamically
            $updates = [];
            $params = [];
            
            // Check each possible field and add to update if column exists
            $field_mappings = [
                'full_name' => ['full_name', 'name'],
                'email' => ['email'],
                'phone' => ['phone', 'phone_number', 'mobile'],
                'address' => ['address'],
                'city' => ['city'],
                'state' => ['state'],
                'country' => ['country'],
                'postal_code' => ['postal_code', 'zip']
            ];
            
            foreach ($field_mappings as $field => $possible_names) {
                $value = trim($_POST[$field] ?? '');
                
                // Find which column name exists in database
                foreach ($possible_names as $col_name) {
                    if (in_array($col_name, $db_columns)) {
                        $updates[] = "$col_name = ?";
                        $params[] = $value;
                        break;
                    }
                }
            }
            
            if (!empty($updates)) {
                $params[] = $user_id;
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    // Update session name if full_name exists
                    if (isset($_POST['full_name'])) {
                        $_SESSION['user_name'] = $_POST['full_name'];
                    }
                    $_SESSION['user_email'] = $_POST['email'];
                    
                    $success_msg = "Profile updated successfully";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                } catch (Exception $e) {
                    $error_msg = "Error updating profile: " . $e->getMessage();
                }
            } else {
                $error_msg = "No valid fields to update";
            }
            
        } elseif ($action === 'change_password') {
            // Change password - this should always work if password column exists
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($current_password)) {
                $errors[] = "Current password is required";
            } elseif (!password_verify($current_password, $user['password'] ?? '')) {
                $errors[] = "Current password is incorrect";
            }
            
            if (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match";
            }
            
            if (empty($errors)) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    $success_msg = "Password changed successfully";
                    
                } catch (Exception $e) {
                    $error_msg = "Error changing password: " . $e->getMessage();
                }
            } else {
                $error_msg = implode("<br>", $errors);
            }
        }
    }
}

// Get user preferences (if user_meta table exists)
$preferences = [
    'theme' => 'light',
    'notifications' => 1,
    'email_notifications' => 1,
    'items_per_page' => 25
];

try {
    // Check if user_meta table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_meta'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ?");
        $stmt->execute([$user_id]);
        while ($row = $stmt->fetch()) {
            $preferences[$row['meta_key']] = $row['meta_value'];
        }
    }
} catch (Exception $e) {
    // Table doesn't exist, ignore
}

// Helper function to safely get user data
function getUserField($user, $field, $default = 'Not provided') {
    if (isset($user[$field]) && !empty($user[$field])) {
        return htmlspecialchars($user[$field]);
    }
    // Try alternative field names
    $alternatives = [
        'full_name' => ['full_name', 'name', 'username'],
        'phone' => ['phone', 'phone_number', 'mobile'],
        'address' => ['address', 'street_address'],
        'city' => ['city', 'town'],
        'state' => ['state', 'province'],
        'country' => ['country', 'nation'],
        'postal_code' => ['postal_code', 'zip', 'postcode']
    ];
    
    if (isset($alternatives[$field])) {
        foreach ($alternatives[$field] as $alt) {
            if (isset($user[$alt]) && !empty($user[$alt])) {
                return htmlspecialchars($user[$alt]);
            }
        }
    }
    
    return $default;
}

// Get display name
$display_name = getUserField($user, 'full_name', $user['email'] ?? 'User');

// Get last login safely
$last_login = 'Never';
if (isset($user['last_login']) && !empty($user['last_login'])) {
    $last_login = date('M d, Y H:i', strtotime($user['last_login']));
} elseif (isset($user['updated_at']) && !empty($user['updated_at'])) {
    $last_login = date('M d, Y H:i', strtotime($user['updated_at']));
}

require_once 'header.php';
?>

<style>
/* Base Admin Styles */
.admin-main {
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Responsive Typography */
h1 { font-size: clamp(1.8rem, 4vw, 2rem); }
h2 { font-size: clamp(1.3rem, 3vw, 1.5rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.25rem); }

/* Card Component */
.card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
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

/* Profile Avatar */
.profile-avatar {
    position: relative;
    width: clamp(100px, 20vw, 120px);
    height: clamp(100px, 20vw, 120px);
}

.avatar-initials {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5, #818cf8);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: clamp(2rem, 6vw, 3rem);
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
    transition: transform 0.3s;
}

.avatar-initials:hover {
    transform: scale(1.05);
}

.role-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    border: 2px solid white;
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

@keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Form Elements */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
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

.form-control:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}

.form-text {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
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

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(245, 158, 11, 0.3);
}

/* Password Strength */
.password-strength {
    margin: 1rem 0;
}

.strength-bars {
    display: flex;
    gap: 0.25rem;
    margin-bottom: 0.5rem;
}

.strength-bar {
    height: 4px;
    flex: 1;
    background: #e5e7eb;
    border-radius: 2px;
    transition: all 0.3s;
}

.strength-bar.weak { background: #ef4444; }
.strength-bar.medium { background: #f59e0b; }
.strength-bar.strong { background: #10b981; }
.strength-bar.very-strong { background: #059669; }

.strength-text {
    font-size: 0.875rem;
    transition: color 0.3s;
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
    min-width: 600px;
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
}

tr:hover {
    background: #f9fafb;
}

/* Status Badge */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.status-success {
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

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.info-item {
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 10px;
}

.info-label {
    font-size: 0.8rem;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
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
    
    .card {
        padding: 1.5rem;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
    }
    
    .profile-avatar {
        margin: 0 auto;
    }
    
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .card {
        padding: 1.25rem;
        border-radius: 12px;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .tabs-scroll {
        padding-bottom: 0.25rem;
    }
    
    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    table {
        font-size: 0.85rem;
    }
    
    th, td {
        padding: 0.75rem;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .card {
        padding: 1rem;
        border-radius: 10px;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    h2 {
        font-size: 1.25rem;
    }
    
    .form-label {
        font-size: 0.9rem;
    }
    
    .form-control {
        padding: 0.6rem 0.75rem;
    }
    
    .btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
    
    .role-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
    }
    
    .info-item {
        padding: 0.5rem;
    }
    
    .empty-state {
        padding: 2rem;
    }
    
    .empty-state i {
        font-size: 2rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .card {
        background: #1f2937;
        border-color: #374151;
    }
    
    .form-control {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    
    .form-control:focus {
        border-color: #818cf8;
    }
    
    .info-item {
        background: #374151;
    }
    
    .info-label {
        color: #9ca3af;
    }
    
    .info-value {
        color: #f3f4f6;
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
    .btn, .tab-btn {
        display: none;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #000;
    }
}
</style>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
        <h1 style="margin-bottom: 0.5rem;">
            <i class="fas fa-user-circle"></i> My Profile
        </h1>
        <p style="color: #6b7280;">Manage your account settings and preferences</p>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Profile Overview Card -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="profile-header" style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
            <!-- Profile Avatar -->
            <div class="profile-avatar">
                <div class="avatar-initials">
                    <?= strtoupper(substr($display_name, 0, 1)) ?>
                </div>
                <span class="role-badge role-<?= $user['role'] ?? 'customer' ?>">
                    <?= ucfirst($user['role'] ?? 'customer') ?>
                </span>
            </div>
            
            <!-- Profile Info -->
            <div style="flex: 1;">
                <h2 style="margin-bottom: 0.5rem;"><?= $display_name ?></h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phone</div>
                        <div class="info-value"><?= getUserField($user, 'phone') ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value">
                            <?= isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A' ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value"><?= $last_login ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-scroll">
            <button type="button" class="tab-btn active" data-tab="profile">Profile Information</button>
            <button type="button" class="tab-btn" data-tab="password">Change Password</button>
            <button type="button" class="tab-btn" data-tab="preferences">Preferences</button>
        </div>
    </div>

    <!-- Profile Information Tab -->
    <div class="tab-content active" id="profileTab">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Edit Profile Information</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" 
                               value="<?= getUserField($user, 'full_name', '') ?>" 
                               required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" 
                               value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                               required class="form-control">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" 
                               value="<?= getUserField($user, 'phone', '') ?>" 
                               class="form-control" placeholder="+234 123 456 7890">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" 
                               value="<?= getUserField($user, 'address', '') ?>" 
                               class="form-control" placeholder="Street address">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" 
                               value="<?= getUserField($user, 'city', '') ?>" 
                               class="form-control" placeholder="City">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" 
                               value="<?= getUserField($user, 'state', '') ?>" 
                               class="form-control" placeholder="State">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" 
                               value="<?= getUserField($user, 'country', '') ?>" 
                               class="form-control" placeholder="Country">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" 
                               value="<?= getUserField($user, 'postal_code', '') ?>" 
                               class="form-control" placeholder="Postal code">
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Tab -->
    <div class="tab-content" id="passwordTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Change Password</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" required class="form-control" minlength="8">
                    <div class="form-text">Minimum 8 characters</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" required class="form-control">
                </div>
                
                <div class="password-strength">
                    <div class="strength-bars">
                        <div class="strength-bar" id="strength1"></div>
                        <div class="strength-bar" id="strength2"></div>
                        <div class="strength-bar" id="strength3"></div>
                        <div class="strength-bar" id="strength4"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Enter a password</div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Preferences Tab -->
    <div class="tab-content" id="preferencesTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">User Preferences</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="update_preferences">
                
                <div class="form-group">
                    <label class="form-label">Theme</label>
                    <select name="theme" class="form-control">
                        <option value="light" <?= $preferences['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $preferences['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                        <option value="auto" <?= $preferences['theme'] === 'auto' ? 'selected' : '' ?>>Auto (System)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Items Per Page</label>
                    <select name="items_per_page" class="form-control">
                        <option value="10" <?= $preferences['items_per_page'] == 10 ? 'selected' : '' ?>>10</option>
                        <option value="25" <?= $preferences['items_per_page'] == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $preferences['items_per_page'] == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $preferences['items_per_page'] == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-check-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="notifications" value="1" 
                               <?= $preferences['notifications'] ? 'checked' : '' ?>>
                        Enable Desktop Notifications
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-check-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="email_notifications" value="1" 
                               <?= $preferences['email_notifications'] ? 'checked' : '' ?>>
                        Receive Email Notifications
                    </label>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Your existing JavaScript remains exactly the same
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
    
    // Check URL hash
    const hash = window.location.hash.substring(1);
    if (hash && ['profile', 'password', 'preferences'].includes(hash)) {
        showTab(hash);
    }

    // Password strength checker
    const passwordInput = document.querySelector('input[name="new_password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
    
    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

function checkPasswordStrength() {
    const password = document.querySelector('input[name="new_password"]')?.value || '';
    const strengthBars = document.querySelectorAll('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    
    if (!strengthBars.length || !strengthText) return;
    
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    // Reset bars
    strengthBars.forEach(bar => {
        bar.className = 'strength-bar';
    });
    
    // Set bars based on strength
    if (strength <= 2) {
        for (let i = 0; i < strength; i++) {
            strengthBars[i].classList.add('weak');
        }
        strengthText.textContent = 'Weak password';
        strengthText.style.color = '#ef4444';
    } else if (strength <= 3) {
        for (let i = 0; i < strength; i++) {
            strengthBars[i].classList.add('medium');
        }
        strengthText.textContent = 'Medium password';
        strengthText.style.color = '#f59e0b';
    } else if (strength <= 4) {
        for (let i = 0; i < strength; i++) {
            strengthBars[i].classList.add('strong');
        }
        strengthText.textContent = 'Strong password';
        strengthText.style.color = '#10b981';
    } else {
        for (let i = 0; i < strength; i++) {
            strengthBars[i].classList.add('very-strong');
        }
        strengthText.textContent = 'Very strong password';
        strengthText.style.color = '#059669';
    }
}
</script>

