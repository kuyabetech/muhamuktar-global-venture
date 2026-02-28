<?php
// admin/profile.php - Admin Profile Management

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

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

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
            // Update profile information
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $country = trim($_POST['country'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            
            $errors = [];
            
            if (empty($full_name)) {
                $errors[] = "Full name is required";
            }
            
            if (empty($email)) {
                $errors[] = "Email is required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format";
            }
            
            // Check if email exists for another user
            if ($email !== $user['email']) {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $user_id]);
                if ($check->fetch()) {
                    $errors[] = "Email already exists";
                }
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            full_name = ?, email = ?, phone = ?, address = ?, 
                            city = ?, state = ?, country = ?, postal_code = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $address, $city, $state, $country, $postal_code, $user_id]);
                    
                    // Update session
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_email'] = $email;
                    
                    $success_msg = "Profile updated successfully";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                } catch (Exception $e) {
                    $error_msg = "Error updating profile: " . $e->getMessage();
                }
            } else {
                $error_msg = implode("<br>", $errors);
            }
            
        } elseif ($action === 'change_password') {
            // Change password
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            $errors = [];
            
            if (empty($current_password)) {
                $errors[] = "Current password is required";
            } elseif (!password_verify($current_password, $user['password'])) {
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
            
        } elseif ($action === 'update_preferences') {
            // Update preferences
            $theme = $_POST['theme'] ?? 'light';
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $items_per_page = (int)($_POST['items_per_page'] ?? 25);
            
            // Save to user meta table (create if not exists)
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS user_meta (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        meta_key VARCHAR(255) NOT NULL,
                        meta_value TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_user_meta (user_id, meta_key),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                
                // Save preferences
                $preferences = [
                    'theme' => $theme,
                    'notifications' => $notifications,
                    'email_notifications' => $email_notifications,
                    'items_per_page' => $items_per_page
                ];
                
                foreach ($preferences as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE meta_value = ?
                    ");
                    $stmt->execute([$user_id, $key, $value, $value]);
                }
                
                $success_msg = "Preferences updated successfully";
                
            } catch (Exception $e) {
                $error_msg = "Error updating preferences: " . $e->getMessage();
            }
        }
    }
}

// Get user preferences
$preferences = [
    'theme' => 'light',
    'notifications' => 1,
    'email_notifications' => 1,
    'items_per_page' => 25
];

try {
    $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ?");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch()) {
        $preferences[$row['meta_key']] = $row['meta_value'];
    }
} catch (Exception $e) {
    // Table might not exist yet
}

// Get recent activity
$recent_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activity = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get login history
$login_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_activity_log 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $login_history = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
            <i class="fas fa-user-circle"></i> My Profile
        </h1>
        <p style="color: var(--admin-gray);">Manage your account settings and preferences</p>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Profile Overview Card -->
    <div class="card" style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
            <!-- Profile Avatar -->
            <div style="position: relative;">
                <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 600;">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div style="position: absolute; bottom: 0; right: 0;">
                    <span class="role-badge role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                </div>
            </div>
            
            <!-- Profile Info -->
            <div style="flex: 1;">
                <h2 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($user['full_name']) ?></h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
                    <div>
                        <div style="font-size: 0.875rem; color: var(--admin-gray);">Email</div>
                        <div><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--admin-gray);">Phone</div>
                        <div><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--admin-gray);">Member Since</div>
                        <div><?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--admin-gray);">Last Login</div>
                        <div><?= $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never' ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 2rem;">
        <div style="display: flex; gap: 1rem; overflow-x: auto;">
            <button type="button" class="tab-btn active" data-tab="profile">Profile Information</button>
            <button type="button" class="tab-btn" data-tab="password">Change Password</button>
            <button type="button" class="tab-btn" data-tab="preferences">Preferences</button>
            <button type="button" class="tab-btn" data-tab="activity">Activity Log</button>
            <button type="button" class="tab-btn" data-tab="sessions">Login History</button>
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
                               value="<?= htmlspecialchars($user['full_name']) ?>" 
                               required class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" 
                               value="<?= htmlspecialchars($user['email']) ?>" 
                               required class="form-control">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               class="form-control" placeholder="+234 123 456 7890">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" 
                               value="<?= htmlspecialchars($user['address'] ?? '') ?>" 
                               class="form-control" placeholder="Street address">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" name="city" 
                               value="<?= htmlspecialchars($user['city'] ?? '') ?>" 
                               class="form-control" placeholder="City">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">State</label>
                        <input type="text" name="state" 
                               value="<?= htmlspecialchars($user['state'] ?? '') ?>" 
                               class="form-control" placeholder="State">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" 
                               value="<?= htmlspecialchars($user['country'] ?? '') ?>" 
                               class="form-control" placeholder="Country">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" 
                               value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" 
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
                    <small>Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" required class="form-control">
                </div>
                
                <div class="password-strength" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 0.25rem; margin-bottom: 0.5rem;">
                        <div class="strength-bar" id="strength1"></div>
                        <div class="strength-bar" id="strength2"></div>
                        <div class="strength-bar" id="strength3"></div>
                        <div class="strength-bar" id="strength4"></div>
                    </div>
                    <div id="strengthText" style="font-size: 0.875rem;">Enter a password</div>
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
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notifications" value="1" 
                               <?= $preferences['notifications'] ? 'checked' : '' ?>>
                        Enable Desktop Notifications
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
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

    <!-- Activity Log Tab -->
    <div class="tab-content" id="activityTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Recent Activity</h2>
            
            <?php if (empty($recent_activity)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-history" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem;"></i>
                    <p>No recent activity found</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activity as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Login History Tab -->
    <div class="tab-content" id="sessionsTab" style="display: none;">
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Login History</h2>
            
            <?php if (empty($login_history)): ?>
                <div style="text-align: center; padding: 3rem;">
                    <i class="fas fa-history" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem;"></i>
                    <p>No login history found</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>IP Address</th>
                                <th>Device/Browser</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($login_history as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                                    <td>
                                        <?php 
                                        $ua = $log['user_agent'] ?? '';
                                        if (strpos($ua, 'Chrome') !== false) echo 'Chrome';
                                        elseif (strpos($ua, 'Firefox') !== false) echo 'Firefox';
                                        elseif (strpos($ua, 'Safari') !== false) echo 'Safari';
                                        elseif (strpos($ua, 'Edge') !== false) echo 'Edge';
                                        else echo 'Unknown';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-success">Success</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button class="btn btn-warning" onclick="logoutAllSessions()">
                        <i class="fas fa-sign-out-alt"></i> Logout All Devices
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.role-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
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

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--admin-gray);
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
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

/* Password Strength Bars */
.strength-bar {
    height: 4px;
    flex: 1;
    background: #e5e7eb;
    border-radius: 2px;
    transition: background 0.3s;
}

.strength-bar.weak {
    background: #ef4444;
}

.strength-bar.medium {
    background: #f59e0b;
}

.strength-bar.strong {
    background: #10b981;
}

.strength-bar.very-strong {
    background: #059669;
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.status-success {
    background: #d1fae5;
    color: #065f46;
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
            
            // Update URL hash
            window.location.hash = this.getAttribute('data-tab');
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

    // Password strength checker
    const passwordInput = document.querySelector('input[name="new_password"]');
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPasswordStrength);
    }
});

function checkPasswordStrength() {
    const password = document.querySelector('input[name="new_password"]').value;
    const strengthBars = document.querySelectorAll('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    
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

function logoutAllSessions() {
    if (confirm('This will log you out from all other devices. Continue?')) {
        // Make API call to logout all sessions
        fetch('api/logout-all.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $csrf_token ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Logged out from all other devices');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error logging out from other devices');
        });
    }
}
</script>

