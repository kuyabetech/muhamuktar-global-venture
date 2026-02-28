<?php
// admin/transactions.php - Transaction Management

$page_title = "Transactions";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Initialize database table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            transaction_id VARCHAR(100) UNIQUE NOT NULL,
            order_id INT NOT NULL,
            user_id INT,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            payment_gateway VARCHAR(50),
            status ENUM('pending','success','failed','refunded','partial_refund') DEFAULT 'pending',
            transaction_type ENUM('payment','refund','partial_refund') DEFAULT 'payment',
            reference VARCHAR(255),
            authorization_code VARCHAR(255),
            card_last4 VARCHAR(4),
            card_type VARCHAR(50),
            bank VARCHAR(100),
            currency VARCHAR(10) DEFAULT 'NGN',
            fee DECIMAL(10,2) DEFAULT 0,
            net_amount DECIMAL(10,2) DEFAULT 0,
            metadata TEXT,
            response_message TEXT,
            response_code VARCHAR(50),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_created (created_at),
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
} catch (Exception $e) {
    error_log("Transactions table error: " . $e->getMessage());
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

// Filters
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

// Handle refund
if ($action === 'refund' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $refund_amount = (float)($_POST['amount'] ?? 0);
        $refund_reason = trim($_POST['reason'] ?? '');
        $notify_customer = isset($_POST['notify_customer']);
        
        try {
            $pdo->beginTransaction();
            
            // Get transaction details
            $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $stmt->execute([$id]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            if ($transaction['status'] === 'refunded') {
                throw new Exception("Transaction already refunded");
            }
            
            if ($refund_amount > $transaction['amount']) {
                throw new Exception("Refund amount cannot exceed transaction amount");
            }
            
            // Calculate refund type
            $refund_type = ($refund_amount == $transaction['amount']) ? 'refund' : 'partial_refund';
            
            // Process refund (in production, this would call payment gateway API)
            // For demo, we'll simulate successful refund
            $refund_successful = true;
            $refund_reference = 'REF_' . uniqid() . '_' . time();
            
            if ($refund_successful) {
                // Update original transaction
                if ($refund_type === 'refund') {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'refunded' WHERE id = ?");
                    $stmt->execute([$id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'partial_refund' WHERE id = ?");
                    $stmt->execute([$id]);
                }
                
                // Create refund transaction record
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        transaction_id, order_id, user_id, amount, payment_method,
                        payment_gateway, status, transaction_type, reference,
                        metadata, response_message, ip_address, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    'REF_' . uniqid(),
                    $transaction['order_id'],
                    $transaction['user_id'],
                    -$refund_amount,
                    $transaction['payment_method'],
                    $transaction['payment_gateway'],
                    'success',
                    $refund_type,
                    $refund_reference,
                    json_encode(['original_transaction' => $transaction['transaction_id'], 'reason' => $refund_reason]),
                    "Refund processed successfully",
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);
                
                // Update order status if fully refunded
                if ($refund_type === 'refund') {
                    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'refunded' WHERE id = ?");
                    $stmt->execute([$transaction['order_id']]);
                }
                
                $pdo->commit();
                $success_msg = "Refund of " . formatMoney($refund_amount) . " processed successfully";
                
                // Log activity
                logActivity($_SESSION['user_id'], "Processed refund of {$refund_amount} for transaction #{$transaction['transaction_id']}");
            } else {
                throw new Exception("Refund failed at payment gateway");
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Refund failed: " . $e->getMessage();
        }
    }
    header("Location: transactions.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Handle manual transaction entry
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? '';
        $payment_gateway = $_POST['payment_gateway'] ?? 'manual';
        $reference = trim($_POST['reference'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $errors = [];
        
        if ($order_id <= 0) {
            $errors[] = "Valid order ID is required";
        }
        if ($amount <= 0) {
            $errors[] = "Amount must be greater than 0";
        }
        if (empty($payment_method)) {
            $errors[] = "Payment method is required";
        }
        
        if (empty($errors)) {
            try {
                // Check if order exists
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    throw new Exception("Order not found");
                }
                
                // Generate unique transaction ID
                $transaction_id = 'TXN_' . time() . '_' . uniqid();
                
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        transaction_id, order_id, user_id, amount, payment_method,
                        payment_gateway, status, transaction_type, reference,
                        metadata, ip_address, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $transaction_id,
                    $order_id,
                    $order['user_id'],
                    $amount,
                    $payment_method,
                    $payment_gateway,
                    'success',
                    'payment',
                    $reference,
                    json_encode(['notes' => $notes, 'manual_entry' => true]),
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);
                
                $success_msg = "Manual transaction added successfully";
                
                logActivity($_SESSION['user_id'], "Added manual transaction #{$transaction_id} for order #{$order_id}");
                
            } catch (Exception $e) {
                $error_msg = "Error adding transaction: " . $e->getMessage();
            }
        } else {
            $error_msg = implode("<br>", $errors);
        }
    }
    header("Location: transactions.php?success=" . urlencode($success_msg) . "&error=" . urlencode($error_msg));
    exit;
}

// Build search query
$where = [];
$params = [];

if (!empty($status_filter)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $where[] = "payment_method LIKE ?";
    $params[] = "%$method_filter%";
}

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where[] = "(transaction_id LIKE ? OR reference LIKE ? OR authorization_code LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term);
}

$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM transactions $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_transactions = $count_stmt->fetchColumn();
$total_pages = ceil($total_transactions / $limit);

// Fetch transactions
$sql = "
    SELECT t.*, 
           o.order_number,
           o.total_amount as order_total,
           u.full_name as customer_name,
           u.email as customer_email
    FROM transactions t
    LEFT JOIN orders o ON t.order_id = o.id
    LEFT JOIN users u ON t.user_id = u.id
    $where_sql
    ORDER BY t.created_at DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn(),
    'successful' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'success'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn(),
    'failed' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'failed'")->fetchColumn(),
    'refunded' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'refunded'")->fetchColumn(),
    'partial_refund' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'partial_refund'")->fetchColumn(),
    'total_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'success'")->fetchColumn(),
    'total_fees' => $pdo->query("SELECT COALESCE(SUM(fee), 0) FROM transactions WHERE status = 'success'")->fetchColumn(),
    'today_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'success'")->fetchColumn(),
    'week_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'success'")->fetchColumn(),
    'month_amount' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'success'")->fetchColumn(),
];

// Get payment methods breakdown
$methods = $pdo->query("
    SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
    FROM transactions 
    WHERE status = 'success'
    GROUP BY payment_method
    ORDER BY total DESC
")->fetchAll();

// Get daily chart data (last 7 days)
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT COALESCE(COUNT(*), 0) as count, COALESCE(SUM(amount), 0) as total
        FROM transactions 
        WHERE DATE(created_at) = ? AND status = 'success'
    ");
    $stmt->execute([$date]);
    $data = $stmt->fetch();
    $chart_data[] = [
        'date' => date('M d', strtotime($date)),
        'count' => (int)$data['count'],
        'total' => (float)$data['total']
    ];
}

// Get single transaction for details
$transaction_details = null;
if ($action === 'view' && $id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, o.order_number, u.full_name, u.email, u.phone
        FROM transactions t
        LEFT JOIN orders o ON t.order_id = o.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $transaction_details = $stmt->fetch();
}

// Format money helper
function formatMoney($amount) {
    return '₦' . number_format($amount, 2);
}

// Log activity helper
function logActivity($user_id, $action) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail
    }
}
require_once 'header.php';
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
                <i class="fas fa-credit-card"></i> Transactions
            </h1>
            <p style="color: var(--admin-gray);">View and manage payment transactions</p>
        </div>
        <div>
            <a href="?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Manual Entry
            </a>
            <a href="?export=1" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if ($action === 'view' && $transaction_details): ?>
        <!-- Transaction Details View -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h2>
                    <i class="fas fa-receipt"></i> 
                    Transaction #<?= htmlspecialchars($transaction_details['transaction_id']) ?>
                </h2>
                <a href="transactions.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- Left Column - Transaction Info -->
                <div>
                    <div style="background: var(--admin-light); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Transaction Information</h3>
                        
                        <div class="detail-row">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-<?= $transaction_details['status'] ?>">
                                    <?= ucfirst($transaction_details['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Amount</div>
                            <div class="detail-value">
                                <strong style="font-size: 1.5rem; color: var(--admin-primary);">
                                    <?= formatMoney($transaction_details['amount']) ?>
                                </strong>
                                <?php if ($transaction_details['fee'] > 0): ?>
                                    <div style="font-size: 0.875rem; color: var(--admin-gray);">
                                        Fee: <?= formatMoney($transaction_details['fee']) ?> | 
                                        Net: <?= formatMoney($transaction_details['net_amount'] ?: $transaction_details['amount'] - $transaction_details['fee']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Date & Time</div>
                            <div class="detail-value"><?= date('F j, Y H:i:s', strtotime($transaction_details['created_at'])) ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Payment Method</div>
                            <div class="detail-value">
                                <?= ucfirst($transaction_details['payment_method'] ?? 'N/A') ?>
                                <?php if ($transaction_details['payment_gateway']): ?>
                                    (via <?= ucfirst($transaction_details['payment_gateway']) ?>)
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($transaction_details['card_last4']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Card Details</div>
                                <div class="detail-value">
                                    <?= ucfirst($transaction_details['card_type']) ?> ending in <?= $transaction_details['card_last4'] ?>
                                    <?php if ($transaction_details['bank']): ?><br>Bank: <?= $transaction_details['bank'] ?><?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <div class="detail-label">Reference</div>
                            <div class="detail-value"><?= htmlspecialchars($transaction_details['reference'] ?? 'N/A') ?></div>
                        </div>
                        
                        <?php if ($transaction_details['authorization_code']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Authorization Code</div>
                                <div class="detail-value"><?= htmlspecialchars($transaction_details['authorization_code']) ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="detail-row">
                            <div class="detail-label">Currency</div>
                            <div class="detail-value"><?= $transaction_details['currency'] ?></div>
                        </div>
                        
                        <?php if ($transaction_details['response_message']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Response</div>
                                <div class="detail-value"><?= htmlspecialchars($transaction_details['response_message']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column - Related Info -->
                <div>
                    <!-- Order Info -->
                    <div style="background: var(--admin-light); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Related Order</h3>
                        <?php if ($transaction_details['order_id']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Order Number</div>
                                <div class="detail-value">
                                    <a href="orders.php?action=view&id=<?= $transaction_details['order_id'] ?>">
                                        #<?= htmlspecialchars($transaction_details['order_number']) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Order Total</div>
                                <div class="detail-value"><?= formatMoney($transaction_details['order_total'] ?? 0) ?></div>
                            </div>
                        <?php else: ?>
                            <p>No order associated</p>
                        <?php endif; ?>
                    </div>

                    <!-- Customer Info -->
                    <div style="background: var(--admin-light); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-bottom: 1rem;">Customer</h3>
                        <?php if ($transaction_details['user_id']): ?>
                            <div class="detail-row">
                                <div class="detail-label">Name</div>
                                <div class="detail-value">
                                    <a href="customers.php?action=view&id=<?= $transaction_details['user_id'] ?>">
                                        <?= htmlspecialchars($transaction_details['full_name']) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?= htmlspecialchars($transaction_details['email']) ?></div>
                            </div>
                            <?php if ($transaction_details['phone']): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Phone</div>
                                    <div class="detail-value"><?= htmlspecialchars($transaction_details['phone']) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>Guest customer</p>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <?php if ($transaction_details['status'] === 'success'): ?>
                        <div style="background: var(--admin-light); padding: 1.5rem; border-radius: 8px;">
                            <h3 style="margin-bottom: 1rem;">Actions</h3>
                            
                            <button class="btn btn-warning" onclick="showRefundModal(<?= $transaction_details['id'] ?>, <?= $transaction_details['amount'] ?>)">
                                <i class="fas fa-undo"></i> Process Refund
                            </button>
                            
                            <button class="btn btn-secondary" onclick="printReceipt()">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Metadata if available -->
            <?php if (!empty($transaction_details['metadata'])): 
                $metadata = json_decode($transaction_details['metadata'], true);
                if (!empty($metadata)):
            ?>
                <div style="margin-top: 2rem;">
                    <h3 style="margin-bottom: 1rem;">Additional Data</h3>
                    <pre style="background: var(--admin-light); padding: 1rem; border-radius: 8px; overflow-x: auto;">
<?= json_encode($metadata, JSON_PRETTY_PRINT) ?>
                    </pre>
                </div>
            <?php 
                endif;
            endif; ?>
        </div>

    <?php elseif ($action === 'add'): ?>
        <!-- Manual Transaction Form -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem;">Add Manual Transaction</h2>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Order ID *</label>
                        <input type="number" name="order_id" required class="form-control" placeholder="Enter order ID">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="form-control" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="">Select method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="paystack">Paystack</option>
                            <option value="flutterwave">Flutterwave</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Gateway</label>
                        <input type="text" name="payment_gateway" class="form-control" placeholder="e.g., Paystack, Manual">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="Transaction reference">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Additional notes">
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Transaction
                    </button>
                    <a href="transactions.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatMoney($stats['total_amount']) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-icon primary"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div style="font-size: 0.875rem; color: var(--admin-gray);">
                    Fees: <?= formatMoney($stats['total_fees']) ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatMoney($stats['today_amount']) ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-icon success"><i class="fas fa-calendar-day"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatMoney($stats['week_amount']) ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-icon warning"><i class="fas fa-calendar-week"></i></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?= formatMoney($stats['month_amount']) ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-icon info"><i class="fas fa-calendar-alt"></i></div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <!-- Transactions Chart -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Transaction Volume (Last 7 Days)</h3>
                <div style="height: 250px;" id="transactionChart"></div>
            </div>

            <!-- Payment Methods Breakdown -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Payment Methods</h3>
                <div style="height: 200px;" id="methodsChart"></div>
                <div style="margin-top: 1rem;">
                    <?php foreach ($methods as $method): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--admin-border);">
                            <span><?= ucfirst($method['payment_method'] ?: 'Unknown') ?></span>
                            <span>
                                <strong><?= formatMoney($method['total']) ?></strong>
                                <span style="color: var(--admin-gray); margin-left: 0.5rem;">(<?= $method['count'] ?>)</span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Status Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <a href="?status=success" class="stat-card-small" style="background: #d1fae5; color: #065f46;">
                <div>Successful</div>
                <div class="count"><?= number_format($stats['successful'] ?? 0) ?></div>
            </a>
            <a href="?status=pending" class="stat-card-small" style="background: #fef3c7; color: #92400e;">
                <div>Pending</div>
                <div class="count"><?= number_format($stats['pending'] ?? 0) ?></div>
            </a>
            <a href="?status=failed" class="stat-card-small" style="background: #fee2e2; color: #991b1b;">
                <div>Failed</div>
                <div class="count"><?= number_format($stats['failed'] ?? 0) ?></div>
            </a>
            <a href="?status=refunded" class="stat-card-small" style="background: #f3f4f6; color: #374151;">
                <div>Refunded</div>
                <div class="count"><?= number_format($stats['refunded'] ?? 0) ?></div>
            </a>
        </div>

        <!-- Filters -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="get" id="filterForm">
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 1rem;">
                    <div>
                        <label class="form-label">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Transaction ID, Reference..." class="form-control">
                    </div>

                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="success" <?= $status_filter === 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="refunded" <?= $status_filter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                            <option value="partial_refund" <?= $status_filter === 'partial_refund' ? 'selected' : '' ?>>Partial Refund</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-control">
                    </div>

                    <div>
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-control">
                    </div>

                    <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="transactions.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="card">
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Date/Time</th>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem;">
                                    <i class="fas fa-credit-card" style="font-size: 3rem; color: var(--admin-gray); margin-bottom: 1rem; display: block;"></i>
                                    No transactions found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td>
                                        <code><?= htmlspecialchars(substr($t['transaction_id'], 0, 20)) ?>...</code>
                                    </td>
                                    <td style="white-space: nowrap;"><?= date('M d, Y H:i', strtotime($t['created_at'])) ?></td>
                                    <td>
                                        <?php if ($t['order_id']): ?>
                                            <a href="orders.php?action=view&id=<?= $t['order_id'] ?>">
                                                #<?= htmlspecialchars($t['order_number'] ?? $t['order_id']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($t['user_id']): ?>
                                            <a href="customers.php?action=view&id=<?= $t['user_id'] ?>">
                                                <?= htmlspecialchars($t['customer_name'] ?? 'Unknown') ?>
                                            </a>
                                        <?php else: ?>
                                            Guest
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <strong><?= formatMoney($t['amount']) ?></strong>
                                    </td>
                                    <td>
                                        <?= ucfirst($t['payment_method'] ?? 'N/A') ?>
                                        <?php if ($t['card_last4']): ?>
                                            <br><small>•••• <?= $t['card_last4'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $t['status'] ?>">
                                            <?= ucfirst($t['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code><?= htmlspecialchars(substr($t['reference'] ?? '', 0, 10)) ?>...</code>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?action=view&id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($t['status'] === 'success'): ?>
                                                <button class="btn btn-warning btn-sm" title="Refund" 
                                                        onclick="showRefundModal(<?= $t['id'] ?>, <?= $t['amount'] ?>)">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="margin-top: 2rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="page-link"><i class="fas fa-angle-double-left"></i></a>
                        <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="page-link"><i class="fas fa-angle-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="page-link"><i class="fas fa-angle-right"></i></a>
                        <a href="?page=<?= $total_pages ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&search=<?= urlencode($search) ?>" 
                           class="page-link"><i class="fas fa-angle-double-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Refund Modal -->
    <div class="modal" id="refundModal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Process Refund</h2>
                <button class="modal-close" onclick="closeRefundModal()">&times;</button>
            </div>
            
            <form method="post" id="refundForm" action="transactions.php?action=refund">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id" id="refundTransactionId">
                
                <div class="form-group">
                    <label class="form-label">Refund Amount</label>
                    <input type="number" name="amount" id="refundAmount" step="0.01" min="0.01" 
                           class="form-control" required>
                    <small id="refundMaxAmount"></small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason for Refund</label>
                    <textarea name="reason" rows="3" class="form-control" 
                              placeholder="Explain why you're processing this refund..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="notify_customer" value="1" checked>
                        Notify customer about refund
                    </label>
                </div>
                
                <div class="alert alert-warning" style="margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> Refunds cannot be undone automatically. Make sure you have confirmed with the customer.
                </div>
                
                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-danger" onclick="return confirmRefund()">
                        <i class="fas fa-undo"></i> Process Refund
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeRefundModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
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

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #991b1b;
}

.status-refunded {
    background: #f3f4f6;
    color: #374151;
}

.status-partial_refund {
    background: #e0e7ff;
    color: #3730a3;
}

.stat-card-small {
    display: block;
    padding: 1rem;
    border-radius: 8px;
    text-decoration: none;
    transition: transform 0.2s;
}

.stat-card-small:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-card-small .count {
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 0.25rem;
}

.stat-icon.info {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.detail-row {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.detail-label {
    font-weight: 600;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
}

.detail-value {
    color: var(--admin-gray);
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

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--admin-gray);
}

.modal-close:hover {
    color: var(--admin-danger);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initTransactionChart();
    initMethodsChart();
});

function initTransactionChart() {
    const ctx = document.createElement('canvas');
    document.getElementById('transactionChart').appendChild(ctx);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($chart_data, 'date')) ?>,
            datasets: [{
                label: 'Transaction Count',
                data: <?= json_encode(array_column($chart_data, 'count')) ?>,
                backgroundColor: '#4f46e5',
                borderRadius: 4,
                yAxisID: 'y-count'
            }, {
                label: 'Amount (₦)',
                data: <?= json_encode(array_column($chart_data, 'total')) ?>,
                type: 'line',
                borderColor: '#10b981',
                backgroundColor: 'transparent',
                borderWidth: 3,
                pointBackgroundColor: '#10b981',
                yAxisID: 'y-amount'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                'y-count': {
                    beginAtZero: true,
                    position: 'left',
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                'y-amount': {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        display: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function initMethodsChart() {
    const ctx = document.createElement('canvas');
    document.getElementById('methodsChart').appendChild(ctx);
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($methods, 'payment_method')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($methods, 'count')) ?>,
                backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '60%'
        }
    });
}

function showRefundModal(transactionId, maxAmount) {
    document.getElementById('refundTransactionId').value = transactionId;
    document.getElementById('refundAmount').max = maxAmount;
    document.getElementById('refundAmount').value = maxAmount;
    document.getElementById('refundMaxAmount').textContent = 'Max: ₦' + maxAmount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('refundModal').style.display = 'flex';
}

function closeRefundModal() {
    document.getElementById('refundModal').style.display = 'none';
}

function confirmRefund() {
    const amount = document.getElementById('refundAmount').value;
    const max = document.getElementById('refundAmount').max;
    
    if (amount <= 0) {
        alert('Please enter a valid amount');
        return false;
    }
    
    if (amount > max) {
        alert('Refund amount cannot exceed ' + document.getElementById('refundMaxAmount').textContent);
        return false;
    }
    
    return confirm(`Process refund of ₦${parseFloat(amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}?\n\nThis action cannot be undone automatically.`);
}

function printReceipt() {
    window.print();
}

// Close modal on ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRefundModal();
    }
});
</script>

