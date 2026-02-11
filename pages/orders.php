<?php
// pages/orders.php - Customer Order History

$page_title = "My Orders";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Must be logged in
require_login();

$user_id = $_SESSION['user_id'] ?? 0;

try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_amount, o.status, o.order_status, 
               o.payment_status, o.created_at, o.tracking_number,
               COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $orders = [];
    $error = "Could not load orders. Please try again later.";
}
?>

<style>
/* Customer Orders Page Styles */
.orders-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
    min-height: 70vh;
}

.page-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 0.5rem;
}

.page-subtitle {
    color: var(--admin-gray);
    font-size: 1.1rem;
}

/* Error Alert */
.error-alert {
    background: linear-gradient(90deg, #fee2e2, #fef2f2);
    border-left: 4px solid var(--admin-danger);
    color: #991b1b;
    padding: 1.2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 4rem 2rem;
    text-align: center;
}

.empty-icon {
    font-size: 4rem;
    color: var(--admin-border);
    margin-bottom: 1.5rem;
    opacity: 0.6;
}

.empty-title {
    font-size: 1.8rem;
    color: var(--admin-dark);
    margin-bottom: 1rem;
    font-weight: 600;
}

.empty-description {
    color: var(--admin-gray);
    font-size: 1.1rem;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.shop-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
    border-radius: 0.75rem;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: var(--transition);
}

.shop-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--admin-shadow-lg);
}

/* Orders List */
.orders-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Order Card */
.order-card {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--admin-border);
}

.order-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--admin-shadow-lg);
}

/* Order Header */
.order-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--admin-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    background: linear-gradient(90deg, var(--admin-light), white);
}

.order-info {
    flex: 1;
    min-width: 300px;
}

.order-number {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 0.25rem;
    display: block;
    text-decoration: none;
}

.order-number:hover {
    color: var(--admin-primary);
}

.order-date {
    color: var(--admin-gray);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.order-date i {
    font-size: 0.8rem;
}

/* Order Status */
.order-status-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.order-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    min-width: 120px;
    justify-content: center;
}

.payment-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
    background: var(--admin-gray);
}

/* Order Body */
.order-body {
    padding: 1.5rem;
}

.order-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--admin-border);
}

.order-items {
    font-size: 0.95rem;
    color: var(--admin-dark);
    font-weight: 500;
}

.order-items strong {
    color: var(--admin-primary);
}

.order-total {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-primary);
}

/* Tracking Info */
.tracking-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: linear-gradient(90deg, #f0f9ff, #e0f2fe);
    border: 1px solid #bae6fd;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.tracking-info i {
    color: #0ea5e9;
    font-size: 1.25rem;
}

.tracking-details {
    flex: 1;
}

.tracking-label {
    font-size: 0.875rem;
    color: #0369a1;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.tracking-number {
    font-family: 'Courier New', monospace;
    color: #0c4a6e;
    font-weight: 600;
    font-size: 1rem;
}

/* Order Actions */
.order-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.view-btn, .track-btn, .invoice-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
}

.view-btn {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-dark));
    color: white;
}

.view-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.track-btn {
    background: var(--admin-light);
    color: var(--admin-dark);
    border: 1px solid var(--admin-border);
}

.track-btn:hover {
    background: var(--admin-primary);
    color: white;
    border-color: var(--admin-primary);
    transform: translateY(-2px);
}

.invoice-btn {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
}

.invoice-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.cancel-btn {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.cancel-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.review-btn {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.review-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .orders-container {
        padding: 1.5rem 1rem;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .order-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .order-status-container {
        align-items: flex-start;
    }
    
    .order-summary {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .view-btn, .track-btn, .invoice-btn {
        width: 100%;
        justify-content: center;
    }
    
    .cancel-btn, .review-btn {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .empty-state {
        padding: 3rem 1rem;
    }
    
    .empty-icon {
        font-size: 3rem;
    }
    
    .empty-title {
        font-size: 1.5rem;
    }
    
    .order-card {
        border-radius: 0.75rem;
    }
}
</style>

<main class="orders-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">My Orders</h1>
        <p class="page-subtitle">View and manage all your recent orders</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="error-alert">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h2 class="empty-title">No Orders Yet</h2>
            <p class="empty-description">
                You haven't placed any orders yet. Start shopping to discover amazing products!
            </p>
            <a href="<?= BASE_URL ?>pages/products.php" class="shop-btn">
                <i class="fas fa-store"></i>
                Start Shopping
            </a>
        </div>

    <?php else: ?>
        <!-- Orders List -->
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <?php
                    // Determine which status to show
                    $display_status = !empty($order['status']) ? $order['status'] : $order['order_status'];
                    $status_text = ucfirst(str_replace('_', ' ', $display_status));
                    $status_color = match($display_status) {
                        'pending'    => '#f59e0b',
                        'paid'       => '#10b981',
                        'processing' => '#3b82f6',
                        'shipped'    => '#8b5cf6',
                        'delivered'  => '#047857',
                        'completed'  => '#047857',
                        'cancelled'  => '#ef4444',
                        default      => '#6b7280'
                    };
                    
                    $payment_color = match($order['payment_status'] ?? 'pending') {
                        'pending'    => '#f59e0b',
                        'paid'       => '#10b981',
                        'failed'     => '#ef4444',
                        'refunded'   => '#8b5cf6',
                        default      => '#6b7280'
                    };
                ?>

                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-info">
                            <a href="<?= BASE_URL ?>pages/order-detail.php?id=<?= $order['id'] ?>" class="order-number">
                                Order #<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?>
                            </a>
                            <div class="order-date">
                                <i class="far fa-calendar"></i>
                                Placed on <?= date('F j, Y \a\t h:i A', strtotime($order['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="order-status-container">
                            <span class="order-status-badge" style="background: <?= $status_color ?>">
                                <i class="fas fa-circle" style="font-size: 0.6rem;"></i>
                                <?= $status_text ?>
                            </span>
                            <span class="payment-status-badge" style="background: <?= $payment_color ?>">
                                <i class="fas fa-credit-card" style="font-size: 0.7rem;"></i>
                                <?= ucfirst($order['payment_status'] ?? 'pending') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Order Body -->
                    <div class="order-body">
                        <!-- Order Summary -->
                        <div class="order-summary">
                            <div class="order-items">
                                <i class="fas fa-box"></i>
                                <strong><?= $order['item_count'] ?></strong> item<?= $order['item_count'] > 1 ? 's' : '' ?>
                            </div>
                            <div class="order-total">₦<?= number_format($order['total_amount']) ?></div>
                        </div>

                        <!-- Tracking Information (if available) -->
                        <?php if (!empty($order['tracking_number'])): ?>
                            <div class="tracking-info">
                                <i class="fas fa-shipping-fast"></i>
                                <div class="tracking-details">
                                    <div class="tracking-label">Tracking Number</div>
                                    <div class="tracking-number"><?= htmlspecialchars($order['tracking_number']) ?></div>
                                </div>
                                <a href="<?= BASE_URL ?>pages/track-order.php?tracking=<?= urlencode($order['tracking_number']) ?>&order_id=<?= $order['id'] ?>" class="track-btn">
                                    <i class="fas fa-map-marked-alt"></i>
                                    Track Order
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="tracking-info" style="background: linear-gradient(90deg, #fef3c7, #fef9c3); border-color: #fcd34d;">
                                <i class="fas fa-info-circle" style="color: #d97706;"></i>
                                <div class="tracking-details">
                                    <div class="tracking-label" style="color: #92400e;">Tracking Pending</div>
                                    <div style="color: #92400e; font-size: 0.875rem;">
                                        Tracking number will be assigned once your order is shipped
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Order Actions -->
                        <div class="order-actions">
                            <div class="action-buttons">
                                <a href="<?= BASE_URL ?>pages/order-detail.php?id=<?= $order['id'] ?>" class="view-btn">
                                    <i class="fas fa-eye"></i>
                                    View Order Details
                                </a>
                                
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <a href="<?= BASE_URL ?>pages/track-order.php?tracking=<?= urlencode($order['tracking_number']) ?>&order_id=<?= $order['id'] ?>" class="track-btn">
                                        <i class="fas fa-shipping-fast"></i>
                                        Track Order
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?= BASE_URL ?>pages/invoice.php?id=<?= $order['id'] ?>" target="_blank" class="invoice-btn">
                                    <i class="fas fa-file-invoice"></i>
                                    Download Invoice
                                </a>
                            </div>
                            
                            <?php if ($display_status === 'pending' || $display_status === 'processing'): ?>
                                <button class="cancel-btn" 
                                        onclick="if(confirm('Are you sure you want to cancel this order?')) window.location='<?= BASE_URL ?>pages/cancel-order.php?id=<?= $order['id'] ?>'">
                                    <i class="fas fa-times-circle"></i>
                                    Cancel Order
                                </button>
                            <?php elseif ($display_status === 'delivered' || $display_status === 'completed'): ?>
                                <button class="review-btn" 
                                        onclick="window.location='<?= BASE_URL ?>pages/write-review.php?order_id=<?= $order['id'] ?>'">
                                    <i class="fas fa-star"></i>
                                    Write Review
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- End Order Body -->
                </div>
                <!-- End Order Card -->
            <?php endforeach; ?>
        </div>
        <!-- End Orders List -->

        <!-- Order Statistics -->
        <div class="order-stats" style="
            background: white;
            border-radius: 1rem;
            box-shadow: var(--admin-shadow);
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px solid var(--admin-border);
        ">
            <h3 style="
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--admin-dark);
                margin-bottom: 1rem;
            ">
                <i class="fas fa-chart-pie"></i>
                Order Statistics
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                <?php
                $total_orders = count($orders);
                $pending_orders = array_filter($orders, fn($o) => 
                    ($o['status'] === 'pending' || $o['order_status'] === 'pending') && 
                    ($o['payment_status'] !== 'paid')
                );
                $active_orders = array_filter($orders, fn($o) => 
                    in_array($o['status'] ?? $o['order_status'], ['processing', 'shipped', 'paid']) ||
                    ($o['payment_status'] === 'paid' && !in_array($o['status'] ?? $o['order_status'], ['cancelled', 'completed', 'delivered']))
                );
                $completed_orders = array_filter($orders, fn($o) => 
                    in_array($o['status'] ?? $o['order_status'], ['completed', 'delivered'])
                );
                ?>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--admin-primary);">
                        <?= $total_orders ?>
                    </div>
                    <div style="color: var(--admin-gray); font-size: 0.9rem;">Total Orders</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;">
                        <?= count($pending_orders) ?>
                    </div>
                    <div style="color: var(--admin-gray); font-size: 0.9rem;">Pending</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;">
                        <?= count($active_orders) ?>
                    </div>
                    <div style="color: var(--admin-gray); font-size: 0.9rem;">Active</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #10b981;">
                        <?= count($completed_orders) ?>
                    </div>
                    <div style="color: var(--admin-gray); font-size: 0.9rem;">Completed</div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="help-section" style="
            background: linear-gradient(90deg, var(--admin-light), white);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-top: 2rem;
            border: 1px solid var(--admin-border);
        ">
            <h3 style="
                font-size: 1.25rem;
                font-weight: 600;
                color: var(--admin-dark);
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            ">
                <i class="fas fa-question-circle"></i>
                Need Help?
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <div style="
                        background: var(--admin-primary);
                        color: white;
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            Contact Support
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Call us at <strong>+234 123 456 7890</strong>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <div style="
                        background: var(--admin-secondary);
                        color: white;
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            Email Support
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Email us at <strong>support@example.com</strong>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <div style="
                        background: var(--admin-warning);
                        color: white;
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            Live Chat
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Chat with us 24/7
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</main>

<script>
// Add animation to order cards on load
document.addEventListener('DOMContentLoaded', function() {
    const orderCards = document.querySelectorAll('.order-card');
    orderCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Add print invoice functionality
function printInvoice(orderId) {
    window.open('<?= BASE_URL ?>pages/invoice.php?id=' + orderId, '_blank');
}

// Add share order functionality
function shareOrder(orderNumber) {
    if (navigator.share) {
        navigator.share({
            title: 'My Order #' + orderNumber,
            text: 'Check out my order details',
            url: window.location.href
        });
    } else {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('Order link copied to clipboard!');
        });
    }
}

// Enhanced tracking with real-time updates
function checkTrackingUpdates(orderId) {
    fetch('<?= BASE_URL ?>api/tracking-update.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.has_update) {
                // Show notification
                showNotification('Tracking update available for order #' + orderId);
            }
        })
        .catch(error => console.error('Error checking tracking:', error));
}

function showNotification(message) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Order Tracking Update', {
            body: message,
            icon: '<?= BASE_URL ?>assets/images/logo.png'
        });
    }
}

// Request notification permission on page load
document.addEventListener('DOMContentLoaded', function() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>