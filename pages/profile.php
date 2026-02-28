<?php
// pages/profile.php - User Profile Page

$page_title = "My Profile";
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
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: " . BASE_URL . "logout.php");
    exit;
}

// Get user stats
try {
    // Order count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $order_count = $stmt->fetchColumn();

    // Total spent
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE user_id = ? AND status IN ('delivered', 'completed')");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetchColumn();

    // Wishlist count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = $stmt->fetchColumn();

    // Review count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $review_count = $stmt->fetchColumn();

    // Member since
    $member_since = date('F Y', strtotime($user['created_at']));

    // Last login
    $last_login = $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'First login';

} catch (Exception $e) {
    $order_count = 0;
    $total_spent = 0;
    $wishlist_count = 0;
    $review_count = 0;
    $member_since = date('F Y', strtotime($user['created_at']));
    $last_login = 'First login';
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
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

// Get recent orders
$recent_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();
} catch (Exception $e) {
    // Orders table might not exist
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">My Profile</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Profile</span>
            </div>
        </div>
    </section>

    <!-- Profile Section -->
    <section class="profile-section">
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
                    <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Stats -->
            <div class="profile-stats">
                <div class="stat-card">
                    <i class="fas fa-shopping-bag"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= number_format($order_count) ?></span>
                        <span class="stat-label">Total Orders</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <span class="stat-value">₦<?= number_format($total_spent) ?></span>
                        <span class="stat-label">Total Spent</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-heart"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= number_format($wishlist_count) ?></span>
                        <span class="stat-label">Wishlist</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= number_format($review_count) ?></span>
                        <span class="stat-label">Reviews</span>
                    </div>
                </div>
            </div>

            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="overview">Overview</button>
                <button class="tab-btn" data-tab="orders">Orders</button>
                <button class="tab-btn" data-tab="wishlist">Wishlist</button>
                <button class="tab-btn" data-tab="settings">Settings</button>
                <button class="tab-btn" data-tab="security">Security</button>
            </div>

            <!-- Overview Tab -->
            <div class="tab-content active" id="overviewTab">
                <div class="overview-grid">
                    <!-- Profile Card -->
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                            </div>
                            <div class="profile-title">
                                <h2><?= htmlspecialchars($user['full_name']) ?></h2>
                                <p><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                        </div>
                        <div class="profile-details">
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <span class="detail-label">Address</span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($user['address'] ?? 'Not provided') ?>
                                        <?php if ($user['city']): ?>, <?= htmlspecialchars($user['city']) ?><?php endif; ?>
                                        <?php if ($user['state']): ?>, <?= htmlspecialchars($user['state']) ?><?php endif; ?>
                                        <?php if ($user['country']): ?>, <?= htmlspecialchars($user['country']) ?><?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div>
                                    <span class="detail-label">Member Since</span>
                                    <span class="detail-value"><?= $member_since ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <span class="detail-label">Last Login</span>
                                    <span class="detail-value"><?= $last_login ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="recent-orders-card">
                        <div class="card-header">
                            <h3>Recent Orders</h3>
                            <a href="#orders" class="view-all" onclick="document.querySelector('[data-tab=\"orders\"]').click()">View All</a>
                        </div>
                        
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>No orders yet</p>
                                <a href="<?= BASE_URL ?>pages/products.php" class="btn-shop">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="orders-list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-info">
                                            <span class="order-number">#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></span>
                                            <span class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                                            <span class="order-status status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                                        </div>
                                        <div class="order-total">
                                            <span class="total-label">Total:</span>
                                            <span class="total-value">₦<?= number_format($order['total_amount'], 2) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Wishlist Preview -->
                    <div class="wishlist-preview-card">
                        <div class="card-header">
                            <h3>Wishlist</h3>
                            <a href="#wishlist" class="view-all" onclick="document.querySelector('[data-tab=\"wishlist\"]').click()">View All</a>
                        </div>
                        
                        <?php if ($wishlist_count == 0): ?>
                            <div class="empty-state">
                                <i class="fas fa-heart"></i>
                                <p>Your wishlist is empty</p>
                                <a href="<?= BASE_URL ?>pages/products.php" class="btn-shop">Explore Products</a>
                            </div>
                        <?php else: ?>
                            <div class="wishlist-preview">
                                <p>You have <strong><?= $wishlist_count ?></strong> items in your wishlist</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Account Summary -->
                    <div class="summary-card">
                        <h3>Account Summary</h3>
                        <div class="summary-stats">
                            <div class="summary-item">
                                <span class="summary-label">Email Verified</span>
                                <span class="summary-value <?= $user['email_verified'] ? 'verified' : 'unverified' ?>">
                                    <?= $user['email_verified'] ? 'Yes' : 'No' ?>
                                </span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Phone Verified</span>
                                <span class="summary-value unverified">
                                    <?= !empty($user['phone']) ? 'Yes' : 'No' ?>
                                </span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">2FA Enabled</span>
                                <span class="summary-value unverified">No</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Tab -->
            <div class="tab-content" id="ordersTab" style="display: none;">
                <div class="orders-section">
                    <h2>Order History</h2>
                    
                    <?php
                    // Get all orders
                    $all_orders = [];
                    try {
                        $stmt = $pdo->prepare("
                            SELECT o.*, 
                                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                            FROM orders o
                            WHERE o.user_id = ?
                            ORDER BY o.created_at DESC
                        ");
                        $stmt->execute([$user_id]);
                        $all_orders = $stmt->fetchAll();
                    } catch (Exception $e) {}
                    ?>

                    <?php if (empty($all_orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No Orders Yet</h3>
                            <p>Start shopping to see your orders here</p>
                            <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">Browse Products</a>
                        </div>
                    <?php else: ?>
                        <div class="orders-table-wrapper">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_orders as $order): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></td>
                                            <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                            <td><?= $order['item_count'] ?></td>
                                            <td>₦<?= number_format($order['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="order-status status-<?= $order['status'] ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>pages/order-detail.php?id=<?= $order['id'] ?>" 
                                                   class="btn-view">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Wishlist Tab -->
            <div class="tab-content" id="wishlistTab" style="display: none;">
                <div class="wishlist-section">
                    <h2>My Wishlist</h2>
                    
                    <?php
                    // Get wishlist items
                    $wishlist_items = [];
                    try {
                        $stmt = $pdo->prepare("
                            SELECT w.*, p.name, p.price, p.discount_price, p.slug,
                                   (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
                            FROM wishlist w
                            JOIN products p ON w.product_id = p.id
                            WHERE w.user_id = ?
                            ORDER BY w.created_at DESC
                        ");
                        $stmt->execute([$user_id]);
                        $wishlist_items = $stmt->fetchAll();
                    } catch (Exception $e) {}
                    ?>

                    <?php if (empty($wishlist_items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>Your Wishlist is Empty</h3>
                            <p>Save items you love to your wishlist</p>
                            <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">Browse Products</a>
                        </div>
                    <?php else: ?>
                        <div class="wishlist-grid">
                            <?php foreach ($wishlist_items as $item): ?>
                                <div class="wishlist-item">
                                    <div class="item-image">
                                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($item['image'] ?? 'no-image.jpg') ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>">
                                        <button class="remove-btn" onclick="removeFromWishlist(<?= $item['product_id'] ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="item-info">
                                        <h3 class="item-title">
                                            <a href="<?= BASE_URL ?>pages/product.php?id=<?= $item['product_id'] ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        </h3>
                                        <div class="item-price">
                                            <?php if ($item['discount_price']): ?>
                                                <span class="current-price">₦<?= number_format($item['discount_price']) ?></span>
                                                <span class="old-price">₦<?= number_format($item['price']) ?></span>
                                            <?php else: ?>
                                                <span class="current-price">₦<?= number_format($item['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="add-to-cart-btn" onclick="addToCart(<?= $item['product_id'] ?>)">
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Settings Tab -->
            <div class="tab-content" id="settingsTab" style="display: none;">
                <div class="settings-section">
                    <h2>Profile Settings</h2>
                    
                    <form method="post" class="settings-form">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="+234 123 456 7890">
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="2" 
                                      placeholder="Street address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
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

                        <button type="submit" class="btn-save">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content" id="securityTab" style="display: none;">
                <div class="security-section">
                    <h2>Change Password</h2>
                    
                    <form method="post" class="security-form" onsubmit="return validatePasswordForm()">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="8">
                            <div class="password-requirements">
                                <small>Password must be at least 8 characters long</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <div id="password_match_message" class="match-message"></div>
                        </div>

                        <button type="submit" class="btn-save" id="changePasswordBtn">Change Password</button>
                    </form>

                    <div class="security-tips">
                        <h3>Security Tips</h3>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> Use a strong, unique password</li>
                            <li><i class="fas fa-check-circle"></i> Never share your password with anyone</li>
                            <li><i class="fas fa-check-circle"></i> Change your password regularly</li>
                            <li><i class="fas fa-check-circle"></i> Enable two-factor authentication when available</li>
                        </ul>
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

/* Profile Section */
.profile-section {
    padding: 4rem 0;
    background: var(--bg);
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
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

/* Profile Stats */
.profile-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stat-card i {
    font-size: 2.5rem;
    color: var(--primary);
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Profile Tabs */
.profile-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid var(--border);
    padding-bottom: 0.5rem;
    overflow-x: auto;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-light);
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    position: relative;
}

.tab-btn:hover {
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
}

.tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -0.7rem;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--primary);
    border-radius: 3px 3px 0 0;
}

/* Overview Tab */
.overview-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.profile-card {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
}

.profile-title h2 {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
    color: var(--text);
}

.profile-title p {
    color: var(--text-light);
}

.profile-details {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.detail-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.detail-item i {
    width: 24px;
    color: var(--primary);
    font-size: 1.2rem;
    margin-top: 0.2rem;
}

.detail-item .detail-label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-light);
    margin-bottom: 0.25rem;
}

.detail-item .detail-value {
    color: var(--text);
    font-weight: 500;
}

/* Cards */
.recent-orders-card,
.wishlist-preview-card,
.summary-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.card-header h3 {
    font-size: 1.2rem;
    color: var(--text);
}

.view-all {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
}

.view-all:hover {
    text-decoration: underline;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--text-lighter);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-light);
    margin-bottom: 1.5rem;
}

.btn-shop,
.btn-primary {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-shop:hover,
.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--bg);
    border-radius: 8px;
}

.order-info {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.order-number {
    font-weight: 600;
    color: var(--text);
}

.order-date {
    color: var(--text-light);
    font-size: 0.9rem;
}

.order-status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-processing {
    background: #dbeafe;
    color: #1e40af;
}

.status-shipped {
    background: #e0e7ff;
    color: #3730a3;
}

.status-delivered {
    background: #d1fae5;
    color: #065f46;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.order-total {
    text-align: right;
}

.total-label {
    font-size: 0.85rem;
    color: var(--text-light);
    display: block;
}

.total-value {
    font-weight: 700;
    color: var(--text);
}

/* Orders Table */
.orders-table-wrapper {
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th {
    padding: 1rem;
    text-align: left;
    background: var(--bg);
    font-weight: 600;
    color: var(--text);
    border-bottom: 2px solid var(--border);
}

.orders-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.btn-view {
    padding: 0.4rem 1rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.btn-view:hover {
    background: var(--primary-dark);
}

/* Wishlist Grid */
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.wishlist-item {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.3s;
}

.wishlist-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.item-image {
    position: relative;
    height: 200px;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.remove-btn {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 30px;
    height: 30px;
    background: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.remove-btn:hover {
    background: var(--danger);
    color: white;
}

.item-info {
    padding: 1.5rem;
}

.item-title {
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.item-title a {
    color: var(--text);
    text-decoration: none;
}

.item-title a:hover {
    color: var(--primary);
}

.item-price {
    margin-bottom: 1rem;
}

.current-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.old-price {
    font-size: 0.9rem;
    color: var(--text-light);
    text-decoration: line-through;
    margin-left: 0.5rem;
}

.add-to-cart-btn {
    width: 100%;
    padding: 0.75rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.add-to-cart-btn:hover {
    background: var(--primary-dark);
}

/* Settings Form */
.settings-form,
.security-form {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    max-width: 600px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
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
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.btn-save {
    padding: 0.75rem 2rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-save:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Security Tips */
.security-tips {
    margin-top: 2rem;
    padding: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.security-tips h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.security-tips ul {
    list-style: none;
}

.security-tips li {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    color: var(--text-light);
}

.security-tips li i {
    color: #10b981;
}

/* Password Match Message */
.match-message {
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .profile-stats {
        grid-template-columns: 1fr;
    }
    
    .profile-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .wishlist-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .profile-section {
        padding: 2rem 0;
    }
    
    .profile-card {
        padding: 1.5rem;
    }
    
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .order-item {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .order-info {
        flex-direction: column;
    }
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
    
    // Password match validation
    const newPass = document.getElementById('new_password');
    const confirmPass = document.getElementById('confirm_password');
    const matchMsg = document.getElementById('password_match_message');
    const changeBtn = document.getElementById('changePasswordBtn');
    
    if (newPass && confirmPass && matchMsg) {
        function checkMatch() {
            if (confirmPass.value) {
                if (newPass.value === confirmPass.value) {
                    matchMsg.innerHTML = '✓ Passwords match';
                    matchMsg.style.color = '#10b981';
                } else {
                    matchMsg.innerHTML = '✗ Passwords do not match';
                    matchMsg.style.color = '#ef4444';
                }
            } else {
                matchMsg.innerHTML = '';
            }
        }
        
        newPass.addEventListener('input', checkMatch);
        confirmPass.addEventListener('input', checkMatch);
    }
});

function validatePasswordForm() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (newPass.length < 8) {
        alert('Password must be at least 8 characters long!');
        return false;
    }
    
    return true;
}

function removeFromWishlist(productId) {
    if (confirm('Remove this item from your wishlist?')) {
        fetch('<?= BASE_URL ?>api/remove-from-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({product_id: productId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function addToCart(productId) {
    fetch('<?= BASE_URL ?>api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({product_id: productId, quantity: 1})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Product added to cart!');
            if (data.cart_count !== undefined) {
                window.dispatchEvent(new CustomEvent('cartUpdate', {
                    detail: {count: data.cart_count}
                }));
            }
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>