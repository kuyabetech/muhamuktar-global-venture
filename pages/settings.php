<?php
// pages/settings.php - Account Settings Page

$page_title = "Account Settings";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "pages/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "logout.php");
    exit;
}

// Get user preferences
$preferences = [
    'email_notifications' => true,
    'sms_notifications' => false,
    'newsletter' => true,
    'order_updates' => true,
    'promotional_emails' => false,
    'two_factor_auth' => false,
    'language' => 'en',
    'currency' => 'NGN',
    'timezone' => 'Africa/Lagos'
];

try {
    $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM user_meta WHERE user_id = ?");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch()) {
        $preferences[$row['meta_key']] = $row['meta_value'];
    }
} catch (Exception $e) {
    // Meta table might not exist
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
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

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    full_name = ?, phone = ?, address = ?, 
                    city = ?, state = ?, country = ?, postal_code = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $phone, $address, $city, $state, $country, $postal_code, $user_id]);

            $_SESSION['user_name'] = $full_name;
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
}

// Handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_email'])) {
    $new_email = trim($_POST['new_email'] ?? '');
    $confirm_email = trim($_POST['confirm_email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = [];

    if (empty($new_email)) {
        $errors[] = "New email is required";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if ($new_email !== $confirm_email) {
        $errors[] = "Emails do not match";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!password_verify($password, $user['password'])) {
        $errors[] = "Incorrect password";
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Email already exists";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$new_email, $user_id]);

            $_SESSION['user_email'] = $new_email;
            $success_msg = "Email updated successfully";

        } catch (Exception $e) {
            $error_msg = "Error updating email: " . $e->getMessage();
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
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
}

// Handle preferences update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $pref_data = [
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
        'newsletter' => isset($_POST['newsletter']) ? 1 : 0,
        'order_updates' => isset($_POST['order_updates']) ? 1 : 0,
        'promotional_emails' => isset($_POST['promotional_emails']) ? 1 : 0,
        'language' => $_POST['language'] ?? 'en',
        'currency' => $_POST['currency'] ?? 'NGN',
        'timezone' => $_POST['timezone'] ?? 'Africa/Lagos'
    ];

    try {
        // Create user_meta table if not exists
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

        foreach ($pref_data as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE meta_value = ?
            ");
            $stmt->execute([$user_id, $key, $value, $value]);
        }

        $success_msg = "Preferences updated successfully";
        $preferences = array_merge($preferences, $pref_data);

    } catch (Exception $e) {
        $error_msg = "Error updating preferences: " . $e->getMessage();
    }
}

// Handle notification settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    $notify_settings = [
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
        'order_updates' => isset($_POST['order_updates']) ? 1 : 0,
        'promotional_emails' => isset($_POST['promotional_emails']) ? 1 : 0,
        'newsletter' => isset($_POST['newsletter']) ? 1 : 0
    ];

    try {
        foreach ($notify_settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO user_meta (user_id, meta_key, meta_value) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE meta_value = ?
            ");
            $stmt->execute([$user_id, $key, $value, $value]);
        }

        $success_msg = "Notification settings updated successfully";
        $preferences = array_merge($preferences, $notify_settings);

    } catch (Exception $e) {
        $error_msg = "Error updating notifications: " . $e->getMessage();
    }
}

// Handle account deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_password = $_POST['confirm_password'] ?? '';
    $confirm_text = $_POST['confirm_text'] ?? '';

    $errors = [];

    if (!password_verify($confirm_password, $user['password'])) {
        $errors[] = "Incorrect password";
    }

    if ($confirm_text !== 'DELETE') {
        $errors[] = "Please type DELETE to confirm";
    }

    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Delete user data (or anonymize)
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);

            $stmt = $pdo->prepare("DELETE FROM user_meta WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Anonymize orders instead of deleting
            $stmt = $pdo->prepare("
                UPDATE orders SET 
                    user_id = NULL,
                    customer_name = 'Deleted User',
                    customer_email = 'deleted@example.com',
                    customer_phone = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);

            // Delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();

            // Logout
            session_destroy();
            header("Location: " . BASE_URL . "?account_deleted=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Error deleting account: " . $e->getMessage();
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Get recent login history
$login_history = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_activity_log 
        WHERE user_id = ? AND action LIKE '%login%'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $login_history = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get connected devices/sessions
$active_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND expires_at > NOW()
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Language options
$languages = [
    'en' => 'English',
    'fr' => 'French',
    'es' => 'Spanish',
    'ar' => 'Arabic',
    'ha' => 'Hausa',
    'yo' => 'Yoruba',
    'ig' => 'Igbo'
];

// Currency options
$currencies = [
    'NGN' => 'Nigerian Naira (₦)',
    'USD' => 'US Dollar ($)',
    'EUR' => 'Euro (€)',
    'GBP' => 'British Pound (£)'
];

// Timezone options
$timezones = [
    'Africa/Lagos' => 'Lagos, Nigeria',
    'Africa/Accra' => 'Accra, Ghana',
    'Africa/Nairobi' => 'Nairobi, Kenya',
    'Africa/Johannesburg' => 'Johannesburg, SA',
    'Europe/London' => 'London, UK',
    'America/New_York' => 'New York, USA'
];
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Account Settings</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/profile.php">Profile</a>
                <span class="separator">/</span>
                <span class="current">Settings</span>
            </div>
        </div>
    </section>

    <!-- Settings Section -->
    <section class="settings-section">
        <div class="container">
            <!-- Messages -->
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <div class="settings-nav">
                <a href="#profile" class="nav-link active" data-tab="profile">Profile Information</a>
                <a href="#email" class="nav-link" data-tab="email">Email Settings</a>
                <a href="#password" class="nav-link" data-tab="password">Password</a>
                <a href="#notifications" class="nav-link" data-tab="notifications">Notifications</a>
                <a href="#preferences" class="nav-link" data-tab="preferences">Preferences</a>
                <a href="#privacy" class="nav-link" data-tab="privacy">Privacy & Security</a>
                <a href="#sessions" class="nav-link" data-tab="sessions">Active Sessions</a>
                <a href="#danger" class="nav-link danger" data-tab="danger">Danger Zone</a>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Profile Information Tab -->
                <div class="settings-tab active" id="profileTab">
                    <h2>Profile Information</h2>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="+234 123 456 7890">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" 
                                   value="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                   placeholder="Street address">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" 
                                       value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                                       placeholder="Lagos">
                            </div>

                            <div class="form-group">
                                <label for="state">State</label>
                                <input type="text" id="state" name="state" 
                                       value="<?= htmlspecialchars($user['state'] ?? '') ?>"
                                       placeholder="Lagos State">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" 
                                       value="<?= htmlspecialchars($user['country'] ?? 'Nigeria') ?>"
                                       placeholder="Nigeria">
                            </div>

                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" 
                                       value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>"
                                       placeholder="100001">
                            </div>
                        </div>

                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </div>

                <!-- Email Settings Tab -->
                <div class="settings-tab" id="emailTab">
                    <h2>Email Settings</h2>
                    
                    <div class="current-email">
                        <label>Current Email</label>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>

                    <form method="post" class="settings-form" onsubmit="return validateEmailForm()">
                        <input type="hidden" name="change_email" value="1">

                        <div class="form-group">
                            <label for="new_email">New Email Address</label>
                            <input type="email" id="new_email" name="new_email" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_email">Confirm New Email</label>
                            <input type="email" id="confirm_email" name="confirm_email" required>
                        </div>

                        <div class="form-group">
                            <label for="email_password">Your Password</label>
                            <input type="password" id="email_password" name="password" required>
                            <small>Enter your password to confirm changes</small>
                        </div>

                        <button type="submit" class="btn-primary">Change Email</button>
                    </form>
                </div>

                <!-- Password Tab -->
                <div class="settings-tab" id="passwordTab">
                    <h2>Change Password</h2>
                    
                    <form method="post" class="settings-form" onsubmit="return validatePasswordForm()">
                        <input type="hidden" name="change_password" value="1">

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                            <div class="password-strength">
                                <div class="strength-bar" id="strength1"></div>
                                <div class="strength-bar" id="strength2"></div>
                                <div class="strength-bar" id="strength3"></div>
                                <div class="strength-bar" id="strength4"></div>
                            </div>
                            <small>At least 8 characters with uppercase, lowercase, and numbers</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <div id="password_match" class="match-message"></div>
                        </div>

                        <button type="submit" class="btn-primary">Update Password</button>
                    </form>
                </div>

                <!-- Notifications Tab -->
                <div class="settings-tab" id="notificationsTab">
                    <h2>Notification Settings</h2>
                    
                    <form method="post" class="settings-form">
                        <input type="hidden" name="update_notifications" value="1">

                        <div class="notification-group">
                            <h3>Email Notifications</h3>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="email_notifications" value="1" 
                                       <?= $preferences['email_notifications'] ? 'checked' : '' ?>>
                                Receive email notifications
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="order_updates" value="1" 
                                       <?= $preferences['order_updates'] ? 'checked' : '' ?>>
                                Order status updates
                            </label>

                            <label class="checkbox-label">
                                <input type="checkbox" name="promotional_emails" value="1" 
                                       <?= $preferences['promotional_emails'] ? 'checked' : '' ?>>
                                Promotional emails and offers
                            </label>
                        </div>

                        <div class="notification-group">
                            <h3>SMS Notifications</h3>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="sms_notifications" value="1" 
                                       <?= $preferences['sms_notifications'] ? 'checked' : '' ?>>
                                Receive SMS notifications
                            </label>
                        </div>

                        <div class="notification-group">
                            <h3>Newsletter</h3>
                            
                            <label class="checkbox-label">
                                <input type="checkbox" name="newsletter" value="1" 
                                       <?= $preferences['newsletter'] ? 'checked' : '' ?>>
                                Subscribe to newsletter
                            </label>
                        </div>

                        <button type="submit" class="btn-primary">Save Preferences</button>
                    </form>
                </div>

                <!-- Preferences Tab -->
                <div class="settings-tab" id="preferencesTab">
                    <h2>User Preferences</h2>
                    
                    <form method="post" class="settings-form">
                        <input type="hidden" name="update_preferences" value="1">

                        <div class="form-group">
                            <label for="language">Language</label>
                            <select id="language" name="language">
                                <?php foreach ($languages as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= ($preferences['language'] ?? 'en') == $code ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency">
                                <?php foreach ($currencies as $code => $name): ?>
                                    <option value="<?= $code ?>" <?= ($preferences['currency'] ?? 'NGN') == $code ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz => $name): ?>
                                    <option value="<?= $tz ?>" <?= ($preferences['timezone'] ?? 'Africa/Lagos') == $tz ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary">Save Preferences</button>
                    </form>
                </div>

                <!-- Privacy & Security Tab -->
                <div class="settings-tab" id="privacyTab">
                    <h2>Privacy & Security</h2>
                    
                    <div class="security-card">
                        <h3>Two-Factor Authentication</h3>
                        <p>Add an extra layer of security to your account by enabling two-factor authentication.</p>
                        
                        <?php if ($preferences['two_factor_auth'] ?? false): ?>
                            <button class="btn-secondary" onclick="disable2FA()">Disable 2FA</button>
                        <?php else: ?>
                            <button class="btn-primary" onclick="enable2FA()">Enable 2FA</button>
                        <?php endif; ?>
                    </div>

                    <div class="security-card">
                        <h3>Login History</h3>
                        <div class="login-history">
                            <?php if (empty($login_history)): ?>
                                <p>No recent login history</p>
                            <?php else: ?>
                                <?php foreach ($login_history as $login): ?>
                                    <div class="login-item">
                                        <div>
                                            <i class="fas fa-<?= $login['status'] ?? 'check-circle' ?>" 
                                               style="color: <?= ($login['status'] ?? 'success') == 'success' ? '#10b981' : '#ef4444' ?>;"></i>
                                            <span><?= date('M d, Y H:i', strtotime($login['created_at'])) ?></span>
                                        </div>
                                        <div>
                                            <span>IP: <?= htmlspecialchars($login['ip_address'] ?? 'Unknown') ?></span>
                                            <span class="device"><?= htmlspecialchars($login['user_agent'] ?? 'Unknown') ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="security-card">
                        <h3>Data Export</h3>
                        <p>Download a copy of your personal data.</p>
                        <a href="<?= BASE_URL ?>api/export-data.php" class="btn-secondary">Request Data Export</a>
                    </div>
                </div>

                <!-- Active Sessions Tab -->
                <div class="settings-tab" id="sessionsTab">
                    <h2>Active Sessions</h2>
                    
                    <div class="current-session">
                        <h3>Current Session</h3>
                        <div class="session-item current">
                            <div>
                                <i class="fas fa-laptop"></i>
                                <strong>This Device</strong>
                            </div>
                            <div>
                                <span>IP: <?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown') ?></span>
                                <span>Started: <?= date('M d, Y H:i', $_SESSION['login_time'] ?? time()) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($active_sessions)): ?>
                        <div class="other-sessions">
                            <h3>Other Active Sessions</h3>
                            <?php foreach ($active_sessions as $session): ?>
                                <?php if ($session['session_id'] !== session_id()): ?>
                                    <div class="session-item">
                                        <div>
                                            <i class="fas fa-<?= strpos($session['user_agent'], 'Mobile') ? 'mobile-alt' : 'laptop' ?>"></i>
                                            <span><?= htmlspecialchars($session['device_name'] ?? 'Unknown Device') ?></span>
                                        </div>
                                        <div>
                                            <span>IP: <?= htmlspecialchars($session['ip_address'] ?? 'Unknown') ?></span>
                                            <span>Last active: <?= date('M d, Y H:i', strtotime($session['last_activity'])) ?></span>
                                            <button class="btn-text" onclick="terminateSession('<?= $session['session_id'] ?>')">
                                                <i class="fas fa-times"></i> Terminate
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <button class="btn-secondary" onclick="terminateAllSessions()">
                                <i class="fas fa-sign-out-alt"></i>
                                Log Out All Other Devices
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Danger Zone Tab -->
                <div class="settings-tab" id="dangerTab">
                    <h2>Danger Zone</h2>
                    
                    <div class="danger-card">
                        <h3>Delete Account</h3>
                        <p>Once you delete your account, there is no going back. Please be certain.</p>
                        
                        <form method="post" class="danger-form" onsubmit="return confirmDelete()">
                            <input type="hidden" name="delete_account" value="1">

                            <div class="form-group">
                                <label for="delete_password">Enter your password to confirm</label>
                                <input type="password" id="delete_password" name="confirm_password" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm_text">Type <strong>DELETE</strong> to confirm</label>
                                <input type="text" id="confirm_text" name="confirm_text" required 
                                       placeholder="DELETE">
                            </div>

                            <button type="submit" class="btn-danger">Permanently Delete My Account</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 3rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

/* Settings Section */
.settings-section {
    padding: 4rem 0;
    background: var(--bg);
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Settings Navigation */
.settings-nav {
    background: white;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 2rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.nav-link {
    padding: 0.75rem 1.5rem;
    color: var(--text-light);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    font-weight: 500;
}

.nav-link:hover {
    background: var(--bg);
    color: var(--primary);
}

.nav-link.active {
    background: var(--primary);
    color: white;
}

.nav-link.danger {
    color: var(--danger);
}

.nav-link.danger:hover {
    background: #fee2e2;
    color: var(--danger);
}

.nav-link.danger.active {
    background: var(--danger);
    color: white;
}

/* Settings Content */
.settings-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.settings-tab {
    display: none;
}

.settings-tab.active {
    display: block;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.settings-tab h2 {
    font-size: 1.8rem;
    margin-bottom: 2rem;
    color: var(--text);
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
}

/* Settings Form */
.settings-form {
    max-width: 600px;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group small {
    display: block;
    color: var(--text-light);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Current Email */
.current-email {
    background: var(--bg);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.current-email label {
    font-weight: 600;
    color: var(--text-light);
    display: block;
    margin-bottom: 0.25rem;
}

.current-email p {
    font-size: 1.1rem;
    color: var(--text);
}

/* Password Strength */
.password-strength {
    display: flex;
    gap: 0.25rem;
    margin: 0.5rem 0;
}

.strength-bar {
    height: 4px;
    flex: 1;
    background: var(--border);
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

.match-message {
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Checkbox Labels */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin-right: 0.5rem;
}

/* Notification Groups */
.notification-group {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--bg);
    border-radius: 12px;
}

.notification-group h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    color: var(--text);
}

/* Security Cards */
.security-card {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.security-card h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.security-card p {
    color: var(--text-light);
    margin-bottom: 1rem;
}

/* Login History */
.login-history {
    max-height: 300px;
    overflow-y: auto;
}

.login-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid var(--border);
    font-size: 0.9rem;
}

.login-item:last-child {
    border-bottom: none;
}

.login-item i {
    margin-right: 0.5rem;
}

.login-item .device {
    color: var(--text-light);
    margin-left: 1rem;
}

/* Session Items */
.session-item {
    background: var(--bg);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.session-item.current {
    border: 2px solid var(--primary);
}

.session-item i {
    margin-right: 0.5rem;
    color: var(--primary);
}

.session-item button {
    margin-left: 1rem;
}

/* Danger Zone */
.danger-card {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 2rem;
}

.danger-card h3 {
    color: #991b1b;
    margin-bottom: 0.5rem;
}

.danger-card p {
    color: #b91c1c;
    margin-bottom: 1.5rem;
}

.danger-form {
    max-width: 400px;
}

.danger-form label {
    color: #991b1b;
}

.btn-danger {
    padding: 0.75rem 1.5rem;
    background: #dc2626;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
}

/* Buttons */
.btn-primary {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: white;
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-text {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-size: 0.9rem;
    padding: 0.25rem 0.5rem;
}

.btn-text:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .settings-nav {
        flex-direction: column;
    }
    
    .nav-link {
        text-align: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .session-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .session-item button {
        margin-left: 0;
    }
    
    .login-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
}

@media (max-width: 480px) {
    .settings-content {
        padding: 1.5rem;
    }
    
    .settings-tab h2 {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Tab switching
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Update active nav link
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
        
        // Show corresponding tab
        const tabId = this.dataset.tab + 'Tab';
        document.querySelectorAll('.settings-tab').forEach(tab => tab.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        
        // Update URL hash
        window.location.hash = this.dataset.tab;
    });
});

// Check URL hash for tab
const hash = window.location.hash.substring(1);
if (hash) {
    const tabLink = document.querySelector(`[data-tab="${hash}"]`);
    if (tabLink) {
        tabLink.click();
    }
}

// Password strength checker
const newPassword = document.getElementById('new_password');
if (newPassword) {
    newPassword.addEventListener('input', function() {
        const password = this.value;
        const strengthBars = document.querySelectorAll('.strength-bar');
        
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        
        strengthBars.forEach((bar, index) => {
            bar.className = 'strength-bar';
            if (index < strength) {
                if (strength <= 2) bar.classList.add('weak');
                else if (strength <= 3) bar.classList.add('medium');
                else if (strength <= 4) bar.classList.add('strong');
                else bar.classList.add('very-strong');
            }
        });
    });
}

// Password match checker
const confirmPassword = document.getElementById('confirm_password');
if (confirmPassword) {
    confirmPassword.addEventListener('input', function() {
        const matchMsg = document.getElementById('password_match');
        if (this.value === newPassword.value) {
            matchMsg.innerHTML = '✓ Passwords match';
            matchMsg.style.color = '#10b981';
        } else {
            matchMsg.innerHTML = '✗ Passwords do not match';
            matchMsg.style.color = '#ef4444';
        }
    });
}

// Form validations
function validateEmailForm() {
    const newEmail = document.getElementById('new_email').value;
    const confirmEmail = document.getElementById('confirm_email').value;
    
    if (newEmail !== confirmEmail) {
        alert('Emails do not match');
        return false;
    }
    
    return true;
}

function validatePasswordForm() {
    const current = document.getElementById('current_password').value;
    const newPass = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (!current || !newPass || !confirm) {
        alert('Please fill in all fields');
        return false;
    }
    
    if (newPass !== confirm) {
        alert('New passwords do not match');
        return false;
    }
    
    if (newPass.length < 8) {
        alert('Password must be at least 8 characters long');
        return false;
    }
    
    return true;
}

function confirmDelete() {
    const confirmText = document.getElementById('confirm_text').value;
    
    if (confirmText !== 'DELETE') {
        alert('Please type DELETE to confirm');
        return false;
    }
    
    return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone.');
}

function enable2FA() {
    alert('Two-factor authentication setup would be initiated. This is a demo feature.');
}

function disable2FA() {
    if (confirm('Disable two-factor authentication? Your account will be less secure.')) {
        alert('2FA disabled (demo)');
    }
}

function terminateSession(sessionId) {
    if (confirm('Terminate this session?')) {
        alert('Session terminated (demo)');
    }
}

function terminateAllSessions() {
    if (confirm('Log out all other devices? You will remain logged in on this device.')) {
        alert('All other sessions terminated (demo)');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>