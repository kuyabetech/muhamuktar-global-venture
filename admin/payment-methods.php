<?php
// admin/payment-methods.php - Payment Methods Management

$page_title = "Payment Methods";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_methods (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) UNIQUE NOT NULL,
            type ENUM('card','bank','wallet','cash','crypto','other') DEFAULT 'card',
            description TEXT,
            instructions TEXT,
            logo VARCHAR(255),
            is_default TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            test_mode TINYINT(1) DEFAULT 1,
            test_public_key TEXT,
            test_secret_key TEXT,
            live_public_key TEXT,
            live_secret_key TEXT,
            webhook_url VARCHAR(500),
            callback_url VARCHAR(500),
            supported_currencies VARCHAR(255) DEFAULT 'NGN',
            min_amount DECIMAL(10,2),
            max_amount DECIMAL(10,2),
            processing_fee DECIMAL(5,2),
            fee_type ENUM('fixed','percentage') DEFAULT 'percentage',
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_active (is_active),
            INDEX idx_default (is_default),
            INDEX idx_sort (sort_order)
        )
    ");

    // Insert default payment methods if table is empty
    $count = $pdo->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
    if ($count == 0) {
        $default_methods = [
            [
                'name' => 'Paystack',
                'code' => 'paystack',
                'type' => 'card',
                'description' => 'Pay with cards, bank transfers, or USSD via Paystack',
                'instructions' => 'You will be redirected to Paystack to complete your payment securely.',
                'is_active' => 1,
                'test_mode' => 1,
                'supported_currencies' => 'NGN,GHS,ZAR,USD',
                'sort_order' => 1
            ],
            [
                'name' => 'Cash on Delivery',
                'code' => 'cod',
                'type' => 'cash',
                'description' => 'Pay with cash when your order is delivered',
                'instructions' => 'Please have the exact amount ready upon delivery.',
                'is_active' => 1,
                'test_mode' => 0,
                'supported_currencies' => 'NGN',
                'sort_order' => 2
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'bank_transfer',
                'type' => 'bank',
                'description' => 'Make a direct bank transfer to our account',
                'instructions' => 'Transfer the total amount to our bank account and upload payment proof.',
                'is_active' => 1,
                'test_mode' => 0,
                'supported_currencies' => 'NGN',
                'sort_order' => 3
            ],
            [
                'name' => 'PayPal',
                'code' => 'paypal',
                'type' => 'card',
                'description' => 'Pay with your PayPal account or credit/debit card',
                'instructions' => 'You will be redirected to PayPal to complete your payment.',
                'is_active' => 0,
                'test_mode' => 1,
                'supported_currencies' => 'USD,EUR,GBP',
                'sort_order' => 4
            ]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO payment_methods (name, code, type, description, instructions, is_active, test_mode, supported_currencies, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($default_methods as $method) {
            $stmt->execute([
                $method['name'],
                $method['code'],
                $method['type'],
                $method['description'],
                $method['instructions'],
                $method['is_active'],
                $method['test_mode'],
                $method['supported_currencies'],
                $method['sort_order']
            ]);
        }
    }
} catch (Exception $e) {
    error_log("Payment methods table error: " . $e->getMessage());
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

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $type = $_POST['type'] ?? 'card';
        $description = trim($_POST['description'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $test_mode = isset($_POST['test_mode']) ? 1 : 0;
        $test_public_key = trim($_POST['test_public_key'] ?? '');
        $test_secret_key = trim($_POST['test_secret_key'] ?? '');
        $live_public_key = trim($_POST['live_public_key'] ?? '');
        $live_secret_key = trim($_POST['live_secret_key'] ?? '');
        $webhook_url = trim($_POST['webhook_url'] ?? '');
        $callback_url = trim($_POST['callback_url'] ?? '');
        $supported_currencies = trim($_POST['supported_currencies'] ?? 'NGN');
        $min_amount = !empty($_POST['min_amount']) ? (float)$_POST['min_amount'] : null;
        $max_amount = !empty($_POST['max_amount']) ? (float)$_POST['max_amount'] : null;
        $processing_fee = !empty($_POST['processing_fee']) ? (float)$_POST['processing_fee'] : 0;
        $fee_type = $_POST['fee_type'] ?? 'percentage';
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        $errors = [];
        if (empty($name)) {
            $errors[] = "Payment method name is required";
        }
        if (empty($code)) {
            $errors[] = "Payment method code is required";
        }

        if (empty($errors)) {
            try {
                if ($_POST['action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_methods (
                            name, code, type, description, instructions, is_active, test_mode,
                            test_public_key, test_secret_key, live_public_key, live_secret_key,
                            webhook_url, callback_url, supported_currencies, min_amount, max_amount,
                            processing_fee, fee_type, sort_order
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name, $code, $type, $description, $instructions, $is_active, $test_mode,
                        $test_public_key, $test_secret_key, $live_public_key, $live_secret_key,
                        $webhook_url, $callback_url, $supported_currencies, $min_amount, $max_amount,
                        $processing_fee, $fee_type, $sort_order
                    ]);
                    
                    // Handle logo upload
                    if (!empty($_FILES['logo']['name'])) {
                        $method_id = $pdo->lastInsertId();
                        uploadPaymentLogo($method_id, $_FILES['logo']);
                    }
                    
                    $success_msg = "Payment method added successfully";
                    
                } elseif ($_POST['action'] === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE payment_methods SET 
                            name = ?, code = ?, type = ?, description = ?, instructions = ?,
                            is_active = ?, test_mode = ?, test_public_key = ?, test_secret_key = ?,
                            live_public_key = ?, live_secret_key = ?, webhook_url = ?, callback_url = ?,
                            supported_currencies = ?, min_amount = ?, max_amount = ?, processing_fee = ?,
                            fee_type = ?, sort_order = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $code, $type, $description, $instructions, $is_active, $test_mode,
                        $test_public_key, $test_secret_key, $live_public_key, $live_secret_key,
                        $webhook_url, $callback_url, $supported_currencies, $min_amount, $max_amount,
                        $processing_fee, $fee_type, $sort_order, $id
                    ]);
                    
                    // Handle logo upload
                    if (!empty($_FILES['logo']['name'])) {
                        // Delete old logo
                        $stmt = $pdo->prepare("SELECT logo FROM payment_methods WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_logo = $stmt->fetchColumn();
                        if ($old_logo && file_exists('../uploads/payments/' . $old_logo)) {
                            unlink('../uploads/payments/' . $old_logo);
                        }
                        
                        uploadPaymentLogo($id, $_FILES['logo']);
                    }
                    
                    $success_msg = "Payment method updated successfully";
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
        header("Location: payment-methods.php?success=" . urlencode($success_msg));
    } else {
        header("Location: payment-methods.php?error=" . urlencode($error_msg));
    }
    exit;
}

// Handle toggle active
if ($action === 'toggle_active' && $id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE payment_methods SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "Payment method status toggled";
    } catch (Exception $e) {
        $error_msg = "Error toggling status: " . $e->getMessage();
    }
    header("Location: payment-methods.php?success=" . urlencode($success_msg));
    exit;
}

// Handle set default
if ($action === 'set_default' && $id > 0) {
    try {
        $pdo->beginTransaction();
        
        // Remove default from all
        $pdo->exec("UPDATE payment_methods SET is_default = 0");
        
        // Set new default
        $stmt = $pdo->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $success_msg = "Default payment method updated";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error setting default: " . $e->getMessage();
    }
    header("Location: payment-methods.php?success=" . urlencode($success_msg));
    exit;
}

// Handle delete
if ($action === 'delete' && $id > 0) {
    try {
        // Delete logo
        $stmt = $pdo->prepare("SELECT logo FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        $logo = $stmt->fetchColumn();
        if ($logo && file_exists('../uploads/payments/' . $logo)) {
            unlink('../uploads/payments/' . $logo);
        }
        
        $stmt = $pdo->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = "Payment method deleted successfully";
    } catch (Exception $e) {
        $error_msg = "Error deleting payment method: " . $e->getMessage();
    }
    header("Location: payment-methods.php?success=" . urlencode($success_msg));
    exit;
}

// Upload logo helper
function uploadPaymentLogo($method_id, $file) {
    $upload_dir = '../uploads/payments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $method_id . '_' . uniqid() . '.' . $ext;
    $dest = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE payment_methods SET logo = ? WHERE id = ?");
        $stmt->execute([$filename, $method_id]);
        return true;
    }
    return false;
}

// Fetch all payment methods
$methods = $pdo->query("
    SELECT * FROM payment_methods 
    ORDER BY sort_order ASC, name ASC
")->fetchAll();

// Get method for editing
$edit_method = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->execute([$id]);
    $edit_method = $stmt->fetch();
}

// Available payment types
$payment_types = [
    'card' => 'Credit/Debit Card',
    'bank' => 'Bank Transfer',
    'wallet' => 'Digital Wallet',
    'cash' => 'Cash',
    'crypto' => 'Cryptocurrency',
    'other' => 'Other'
];

// Available currencies (common ones)
$currencies = ['NGN', 'GHS', 'KES', 'ZAR', 'USD', 'EUR', 'GBP', 'CAD', 'AUD'];
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-credit-card"></i> Payment Methods
            </h1>
            <p style="color: var(--admin-gray);">Configure payment gateways and methods</p>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Payment Method
        </a>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add' || $edit_method): ?>
        <!-- Add/Edit Payment Method Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">
                <i class="fas fa-<?= $edit_method ? 'edit' : 'plus' ?>"></i>
                <?= $edit_method ? 'Edit Payment Method' : 'Add Payment Method' ?>
            </h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="<?= $edit_method ? 'edit' : 'add' ?>">
                <?php if ($edit_method): ?>
                    <input type="hidden" name="id" value="<?= $edit_method['id'] ?>">
                <?php endif; ?>

                <!-- Tabs -->
                <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 1.5rem;">
                    <div style="display: flex; gap: 1rem; overflow-x: auto;">
                        <button type="button" class="tab-btn active" data-tab="general">General</button>
                        <button type="button" class="tab-btn" data-tab="api">API Settings</button>
                        <button type="button" class="tab-btn" data-tab="restrictions">Restrictions</button>
                    </div>
                </div>

                <!-- General Tab -->
                <div class="tab-content active" id="generalTab">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Method Name *</label>
                            <input type="text" name="name" 
                                   value="<?= htmlspecialchars($edit_method['name'] ?? '') ?>" 
                                   required class="form-control" placeholder="e.g., Paystack, PayPal">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Method Code *</label>
                            <input type="text" name="code" 
                                   value="<?= htmlspecialchars($edit_method['code'] ?? '') ?>" 
                                   required class="form-control" placeholder="paystack, paypal">
                            <small>Unique identifier (lowercase, no spaces)</small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-control">
                                <?php foreach ($payment_types as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($edit_method['type'] ?? 'card') === $value ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" 
                                   value="<?= htmlspecialchars($edit_method['sort_order'] ?? 0) ?>" 
                                   class="form-control" min="0">
                            <small>Lower numbers appear first</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" 
                                  placeholder="Brief description for customers"><?= htmlspecialchars($edit_method['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Instructions</label>
                        <textarea name="instructions" rows="4" class="form-control" 
                                  placeholder="Detailed instructions for customers"><?= htmlspecialchars($edit_method['instructions'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Logo</label>
                        <?php if ($edit_method && !empty($edit_method['logo'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <img src="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($edit_method['logo']) ?>" 
                                     alt="" style="max-height: 50px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo" accept="image/*" class="form-control">
                        <small>Recommended size: 100x50px</small>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?= ($edit_method['is_active'] ?? 1) ? 'checked' : '' ?>>
                                Active
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="test_mode" value="1" 
                                       <?= ($edit_method['test_mode'] ?? 1) ? 'checked' : '' ?>>
                                Test Mode
                            </label>
                            <small>Use test keys instead of live keys</small>
                        </div>
                    </div>
                </div>

                <!-- API Settings Tab -->
                <div class="tab-content" id="apiTab" style="display: none;">
                    <div class="form-group">
                        <h3 style="margin-bottom: 1rem;">Test Credentials</h3>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Test Public Key</label>
                            <input type="text" name="test_public_key" 
                                   value="<?= htmlspecialchars($edit_method['test_public_key'] ?? '') ?>" 
                                   class="form-control" placeholder="pk_test_...">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Test Secret Key</label>
                            <input type="password" name="test_secret_key" 
                                   value="<?= htmlspecialchars($edit_method['test_secret_key'] ?? '') ?>" 
                                   class="form-control" placeholder="sk_test_...">
                        </div>
                    </div>

                    <div class="form-group">
                        <h3 style="margin: 2rem 0 1rem;">Live Credentials</h3>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Live Public Key</label>
                            <input type="text" name="live_public_key" 
                                   value="<?= htmlspecialchars($edit_method['live_public_key'] ?? '') ?>" 
                                   class="form-control" placeholder="pk_live_...">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Live Secret Key</label>
                            <input type="password" name="live_secret_key" 
                                   value="<?= htmlspecialchars($edit_method['live_secret_key'] ?? '') ?>" 
                                   class="form-control" placeholder="sk_live_...">
                        </div>
                    </div>

                    <div class="form-group">
                        <h3 style="margin: 2rem 0 1rem;">Webhooks & Callbacks</h3>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Webhook URL</label>
                            <input type="url" name="webhook_url" 
                                   value="<?= htmlspecialchars($edit_method['webhook_url'] ?? '') ?>" 
                                   class="form-control" placeholder="https://yourdomain.com/webhook/paystack">
                            <small>URL for payment gateway to send notifications</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Callback URL</label>
                            <input type="url" name="callback_url" 
                                   value="<?= htmlspecialchars($edit_method['callback_url'] ?? '') ?>" 
                                   class="form-control" placeholder="https://yourdomain.com/payment/callback">
                            <small>URL to redirect after payment</small>
                        </div>
                    </div>
                </div>

                <!-- Restrictions Tab -->
                <div class="tab-content" id="restrictionsTab" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Supported Currencies</label>
                        <select name="supported_currencies" class="form-control" multiple size="5">
                            <?php foreach ($currencies as $currency): ?>
                                <option value="<?= $currency ?>" 
                                    <?= $edit_method && strpos($edit_method['supported_currencies'] ?? '', $currency) !== false ? 'selected' : '' ?>>
                                    <?= $currency ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl to select multiple. Leave blank for all currencies.</small>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Minimum Amount (₦)</label>
                            <input type="number" name="min_amount" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_method['min_amount'] ?? '') ?>" 
                                   class="form-control" placeholder="Optional">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Maximum Amount (₦)</label>
                            <input type="number" name="max_amount" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_method['max_amount'] ?? '') ?>" 
                                   class="form-control" placeholder="Optional">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Processing Fee</label>
                            <input type="number" name="processing_fee" step="0.01" min="0" 
                                   value="<?= htmlspecialchars($edit_method['processing_fee'] ?? 0) ?>" 
                                   class="form-control" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fee Type</label>
                            <select name="fee_type" class="form-control">
                                <option value="percentage" <?= ($edit_method['fee_type'] ?? 'percentage') === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                                <option value="fixed" <?= ($edit_method['fee_type'] ?? 'percentage') === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= $edit_method ? 'Update Method' : 'Add Method' ?>
                    </button>
                    <?php if ($edit_method): ?>
                        <a href="payment-methods.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Payment Methods List -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Method</th>
                            <th>Type</th>
                            <th>Currencies</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Default</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($methods)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-credit-card" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                    No payment methods configured
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($methods as $method): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($method['logo'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/payments/<?= htmlspecialchars($method['logo']) ?>" 
                                                 alt="" style="max-height: 40px; max-width: 80px;">
                                        <?php else: ?>
                                            <span style="color: var(--admin-gray);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($method['name']) ?></strong>
                                        <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                            <?= htmlspecialchars($method['code']) ?>
                                        </div>
                                    </td>
                                    <td><?= $payment_types[$method['type']] ?? ucfirst($method['type']) ?></td>
                                    <td><?= htmlspecialchars($method['supported_currencies']) ?></td>
                                    <td>
                                        <?php if ($method['processing_fee'] > 0): ?>
                                            <?= $method['fee_type'] === 'percentage' ? 
                                                $method['processing_fee'] . '%' : 
                                                '₦' . number_format($method['processing_fee'], 2) ?>
                                        <?php else: ?>
                                            Free
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $method['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $method['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                        <?php if ($method['test_mode']): ?>
                                            <span class="test-badge">Test</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($method['is_default']): ?>
                                            <i class="fas fa-check-circle" style="color: var(--admin-success);"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;"><?= $method['sort_order'] ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=toggle_active&id=<?= $method['id'] ?>" 
                                               class="btn btn-<?= $method['is_active'] ? 'warning' : 'success' ?> btn-sm" 
                                               title="<?= $method['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <i class="fas fa-<?= $method['is_active'] ? 'pause' : 'play' ?>"></i>
                                            </a>
                                            
                                            <?php if (!$method['is_default']): ?>
                                                <a href="?action=set_default&id=<?= $method['id'] ?>" 
                                                   class="btn btn-secondary btn-sm" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?action=edit&id=<?= $method['id'] ?>" 
                                               class="btn btn-secondary btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if (!$method['is_default']): ?>
                                                <a href="?action=delete&id=<?= $method['id'] ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Delete payment method <?= addslashes($method['name']) ?>?')"
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

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #fee2e2;
    color: #991b1b;
}

.test-badge {
    background: #fef3c7;
    color: #92400e;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
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

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706, #b45309);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
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
});
</script>

