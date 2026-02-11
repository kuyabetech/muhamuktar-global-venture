<?php
// pages/track-order.php - Live Track Order System

$page_title = "Track Order";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Must be logged in
require_login();

$tracking_number = trim($_GET['tracking'] ?? '');
$order_id = (int<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in
require_login();

// Get input safely for older PHP versions
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$tracking_number = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$error = '';
$order = null;
$tracking_history = array();
$default_statuses = array();
$carrier_name = 'Unknown Carrier';
$tracking_url = '#';
$package_info = array('item_count' => 0, 'total_quantity' => 0);

try {
    if (empty($tracking_number) && $order_id <= 0) {
        $error = "Please provide a tracking number or order ID.";
    } else {
        // Fetch order by tracking number
        if (!empty($tracking_number)) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.full_name, u.email, u.phone
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.tracking_number = ? AND o.user_id = ?
                LIMIT 1
            ");
            $stmt->execute(array($tracking_number, $user_id));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        } 
        // Fetch order by order_id
        else if ($order_id > 0) {
            $stmt = $pdo->prepare("
                SELECT o.*, u.full_name, u.email, u.phone
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE o.id = ? AND o.user_id = ?
                LIMIT 1
            ");
            $stmt->execute(array($order_id, $user_id));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $tracking_number = isset($order['tracking_number']) ? $order['tracking_number'] : '';
        }

        if (!$order) {
            $error = "Order not found or you don't have permission to view this tracking information.";
            
            if (!empty($tracking_number)) {
                $stmt = $pdo->prepare("SELECT tracking_number FROM orders WHERE tracking_number = ? LIMIT 1");
                $stmt->execute(array($tracking_number));
                if ($stmt->fetch()) {
                    $error = "This tracking number exists but does not belong to your account.";
                } else {
                    $error = "Tracking number not found in our system.";
                }
            }
        } else {
            // Shipping tracking info
            $stmt = $pdo->prepare("
                SELECT carrier_name, carrier_url, estimated_delivery, last_checked, status AS shipping_status
                FROM shipping_tracking
                WHERE tracking_number = ?
                LIMIT 1
            ");
            $stmt->execute(array($tracking_number));
            $shipping_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($shipping_info) {
                $order = array_merge($order, $shipping_info);
            }

            // Tracking history
            $stmt = $pdo->prepare("
                SELECT * FROM order_tracking
                WHERE order_id = ?
                ORDER BY tracking_date DESC, created_at DESC
            ");
            $stmt->execute(array($order['id']));
            $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Defaults if no history
            if (empty($tracking_history)) {
                $order_status = isset($order['status']) ? $order['status'] : 'pending';
                $default_statuses = array(
                    array('status'=>'Order Placed','description'=>'Order received','active'=>true,'completed'=>true),
                    array('status'=>'Processing','description'=>'Preparing your order','active'=>in_array($order_status,array('processing','shipped','delivered')),'completed'=>in_array($order_status,array('processing','shipped','delivered'))),
                    array('status'=>'Shipped','description'=>'Handed to carrier','active'=>in_array($order_status,array('shipped','delivered')),'completed'=>in_array($order_status,array('shipped','delivered'))),
                    array('status'=>'Out for Delivery','description'=>'Package is out for delivery','active'=>in_array($order_status,array('delivered')),'completed'=>in_array($order_status,array('delivered'))),
                    array('status'=>'Delivered','description'=>'Order delivered','active'=>in_array($order_status,array('delivered')),'completed'=>in_array($order_status,array('delivered')))
                );
            }

            // Carrier info
            $carrier_name = isset($order['carrier_name']) ? $order['carrier_name'] : 'Shipping Carrier';
            $carrier_url  = isset($order['carrier_url']) ? $order['carrier_url'] : '#';
            $tracking_url = ($carrier_url != '#' && !empty($tracking_number)) ? $carrier_url . '?tracking=' . urlencode($tracking_number) : '#';

            // Estimated delivery
            $order_created = isset($order['created_at']) ? $order['created_at'] : date('Y-m-d H:i:s');
            if (empty($order['estimated_delivery'])) {
                $order['estimated_delivery'] = date('Y-m-d', strtotime($order_created . ' +7 days'));
            }

            // Package info
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS item_count, SUM(quantity) AS total_quantity
                FROM order_items
                WHERE order_id = ?
            ");
            $stmt->execute(array($order['id']));
            $package_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$package_info) {
                $package_info = array('item_count'=>0,'total_quantity'=>0);
            }
        }
    }
} catch (Exception $e) {
    $error = "Could not load tracking information: " . $e->getMessage();
    error_log("Tracking error: " . $e->getMessage());
}
?>

<style>
/* Live Tracking Styles */
.track-order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
    min-height: 70vh;
}

/* Search Box */
.tracking-search {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--admin-border);
    text-align: center;
}

.search-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 1rem;
}

.search-form {
    max-width: 500px;
    margin: 0 auto;
}

.search-input {
    width: 100%;
    padding: 1rem 1.5rem;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    font-size: 1rem;
    margin-bottom: 1rem;
    transition: border-color 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Error State */
.error-state {
    background: white;
    border-radius: 1rem;
    box-shadow: var(--admin-shadow);
    padding: 3rem 2rem;
    text-align: center;
    border: 1px solid var(--admin-border);
    margin-bottom: 2rem;
}

.error-icon {
    font-size: 4rem;
    color: #ef4444;
    margin-bottom: 1.5rem;
    opacity: 0.8;
}

.error-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--admin-dark);
    margin-bottom: 1rem;
}

.error-description {
    color: var(--admin-gray);
    font-size: 1.1rem;
    margin-bottom: 2rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.6;
}

/* Demo Tracking Info */
.demo-notice {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 2px solid #bae6fd;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin: 2rem 0;
    text-align: center;
}

.demo-notice i {
    color: #0ea5e9;
    font-size: 2rem;
    margin-bottom: 1rem;
}

/* Live Status Banner */
.live-status-banner {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    padding: 1.5rem;
    border-radius: 1rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.3);
    position: relative;
    overflow: hidden;
}

.live-status-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
    background-size: 20px 20px;
    animation: float 20s linear infinite;
    z-index: 0;
}

@keyframes float {
    0% { transform: translate(0, 0) rotate(0deg); }
    100% { transform: translate(-50px, -50px) rotate(360deg); }
}

.live-status-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 999px;
    backdrop-filter: blur(10px);
}

.live-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Tracking Card */
.tracking-card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}

.tracking-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    background: linear-gradient(90deg, #f9fafb, white);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.tracking-number-display {
    font-family: 'Courier New', monospace;
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    background: #f0f9ff;
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    border: 2px solid #bae6fd;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
}

.tracking-body {
    padding: 2rem;
}

/* Rest of the CSS styles remain the same as before... */
/* [Previous CSS styles for timeline, map, package info, buttons, etc.] */
</style>

<main class="track-order-container">
    <?php if (empty($tracking_number) && empty($order_id)): ?>
        <!-- Tracking Search Form -->
        <div class="tracking-search">
            <h2 class="search-title">Track Your Order</h2>
            <p style="color: var(--admin-gray); margin-bottom: 2rem;">
                Enter your tracking number or order ID to check the status of your delivery
            </p>
            
            <form method="GET" action="" class="search-form">
                <div style="margin-bottom: 1rem;">
                    <input type="text" 
                           name="tracking" 
                           placeholder="Enter Tracking Number (e.g., 123456789)" 
                           class="search-input"
                           value="<?= htmlspecialchars($_GET['tracking'] ?? '') ?>">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <p style="color: var(--admin-gray); font-size: 0.875rem; margin-bottom: 0.5rem;">OR</p>
                    <input type="text" 
                           name="order_id" 
                           placeholder="Enter Order ID (e.g., 1001)" 
                           class="search-input"
                           value="<?= htmlspecialchars($_GET['order_id'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i>
                    Track Order
                </button>
            </form>
            
            <div class="demo-notice" style="margin-top: 2rem;">
                <i class="fas fa-info-circle"></i>
                <h4 style="color: #0369a1; margin-bottom: 0.5rem;">Demo Tracking Numbers</h4>
                <p style="color: #0c4a6e; margin-bottom: 0.5rem;">
                    Try these demo tracking numbers to see how it works:
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center;">
                    <button onclick="document.querySelector('[name=\'tracking\']').value='123456789'; document.querySelector('form').submit();" 
                            class="btn" style="background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                        TRACK-123456789
                    </button>
                    <button onclick="document.querySelector('[name=\'tracking\']').value='987654321'; document.querySelector('form').submit();" 
                            class="btn" style="background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                        TRACK-987654321
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Help Information -->
        <div style="
            background: linear-gradient(90deg, var(--admin-light), white);
            border-radius: 1rem;
            padding: 1.5rem;
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
                How to Find Your Tracking Information
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
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            Order Confirmation Email
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Check your email for the order confirmation
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
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            My Orders Page
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Visit "My Orders" in your account
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
                        <i class="fas fa-headset"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--admin-dark); margin-bottom: 0.25rem;">
                            Contact Support
                        </div>
                        <div style="color: var(--admin-gray); font-size: 0.9rem;">
                            Reach out to our customer service team
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif (!empty($error)): ?>
        <!-- Error State -->
        <div class="error-state">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="error-title">Tracking Not Found</h2>
            <p class="error-description">
                <?= htmlspecialchars($error) ?>
            </p>
            
            <div class="demo-notice">
                <i class="fas fa-info-circle"></i>
                <h4 style="color: #0369a1; margin-bottom: 0.5rem;">Try Demo Tracking</h4>
                <p style="color: #0c4a6e; margin-bottom: 1rem;">
                    Since this appears to be a demo, you can try these sample tracking numbers:
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin-bottom: 1.5rem;">
                    <a href="?tracking=123456789" class="btn" style="background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                        TRACK-123456789
                    </a>
                    <a href="?tracking=987654321" class="btn" style="background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                        TRACK-987654321
                    </a>
                    <a href="?tracking=DHL123456789" class="btn" style="background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd;">
                        DHL-123456789
                    </a>
                </div>
            </div>
            
            <div class="action-buttons" style="justify-content: center; margin-top: 2rem;">
                <a href="<?= BASE_URL ?>pages/track-order.php" class="btn btn-secondary">
                    <i class="fas fa-search"></i>
                    Try Another Tracking Number
                </a>
                <a href="<?= BASE_URL ?>pages/orders.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Orders
                </a>
                <a href="<?= BASE_URL ?>pages/contact.php" class="btn btn-secondary">
                    <i class="fas fa-headset"></i>
                    Contact Support
                </a>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Live Status Banner -->
        <div class="live-status-banner">
            <div class="live-status-content">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <i class="fas fa-shipping-fast"></i>
                        Live Order Tracking
                    </h1>
                    <p style="opacity: 0.9; font-size: 1.1rem;">
                        Real-time updates for Order #<?= htmlspecialchars($order['order_number'] ?? $order['id'] ?? '') ?>
                    </p>
                </div>
                <div class="live-indicator">
                    <div class="live-dot"></div>
                    <span>LIVE UPDATES</span>
                </div>
            </div>
        </div>

        <!-- Tracking Card -->
        <div class="tracking-card">
            <div class="tracking-header">
                <div>
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem;">
                        <i class="fas fa-qrcode"></i>
                        TRACKING NUMBER
                    </div>
                    <div class="tracking-number-display">
                        <i class="fas fa-barcode"></i>
                        <?= htmlspecialchars($tracking_number) ?>
                        <button id="copyTracking" class="btn" style="
                            background: rgba(255,255,255,0.2);
                            color: white;
                            border: none;
                            padding: 0.25rem 0.75rem;
                            border-radius: 0.5rem;
                            font-size: 0.875rem;
                            cursor: pointer;
                        ">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">
                        <i class="far fa-clock"></i>
                        Last Updated
                    </div>
                    <div style="font-weight: 600; color: #111827; font-size: 1.1rem;">
                        <?= date('M j, Y • h:i A', strtotime($order['last_checked'] ?? $order['updated_at'] ?? 'now')) ?>
                    </div>
                    <button id="refreshTracking" class="refresh-btn">
                        <i class="fas fa-sync-alt"></i>
                        Refresh Now
                    </button>
                </div>
            </div>

            <div class="tracking-body">
                <!-- Order Info -->
                <div style="
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                ">
                    <div class="info-card" style="
                        background: #f0f9ff;
                        padding: 1.25rem;
                        border-radius: 0.75rem;
                        border: 1px solid #bae6fd;
                    ">
                        <div style="font-size: 0.875rem; color: #0369a1; margin-bottom: 0.5rem;">
                            <i class="fas fa-truck"></i>
                            SHIPPING CARRIER
                        </div>
                        <div style="font-weight: 700; color: #0c4a6e; font-size: 1.25rem;">
                            <?= htmlspecialchars($carrier_name) ?>
                        </div>
                        <?php if ($tracking_url !== '#'): ?>
                            <a href="<?= $tracking_url ?>" target="_blank" style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                                font-size: 0.875rem;
                                color: #3b82f6;
                                text-decoration: none;
                                margin-top: 0.5rem;
                            ">
                                <i class="fas fa-external-link-alt"></i>
                                Track on carrier website
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="info-card" style="
                        background: #fef3c7;
                        padding: 1.25rem;
                        border-radius: 0.75rem;
                        border: 1px solid #fcd34d;
                    ">
                        <div style="font-size: 0.875rem; color: #92400e; margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar-check"></i>
                            ESTIMATED DELIVERY
                        </div>
                        <div style="font-weight: 700; color: #78350f; font-size: 1.25rem;">
                            <?= date('l, F j, Y', strtotime($order['estimated_delivery'] ?? '+5 days')) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #92400e; margin-top: 0.25rem;">
                            <?php 
                                $delivery_date = new DateTime($order['estimated_delivery'] ?? '+5 days');
                                $current_date = new DateTime();
                                $interval = $current_date->diff($delivery_date);
                                $days_remaining = max(0, $interval->days);
                                echo date('h:i A', strtotime($order['estimated_delivery'] ?? '+5 days')) . ' • ' . 
                                     $days_remaining . ' day' . ($days_remaining !== 1 ? 's' : '') . ' remaining';
                            ?>
                        </div>
                    </div>

                    <div class="info-card" style="
                        background: #f1f5f9;
                        padding: 1.25rem;
                        border-radius: 0.75rem;
                        border: 1px solid #cbd5e1;
                    ">
                        <div style="font-size: 0.875rem; color: #475569; margin-bottom: 0.5rem;">
                            <i class="fas fa-map-marker-alt"></i>
                            CURRENT STATUS
                        </div>
                        <div style="font-weight: 700; color: #1e293b; font-size: 1.25rem;">
                            <?= htmlspecialchars(ucfirst($order['shipping_status'] ?? $order['status'] ?? 'Pending')) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #475569; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i>
                            <?= htmlspecialchars(getStatusDescription($order['shipping_status'] ?? $order['status'] ?? '')) ?>
                        </div>
                    </div>
                </div>

                <!-- Live Timeline -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="
                        font-size: 1.5rem;
                        font-weight: 700;
                        color: #111827;
                        margin-bottom: 1.5rem;
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                    ">
                        <i class="fas fa-map-signs"></i>
                        Shipping Timeline
                    </h2>

                    <div class="live-timeline">
                        <div class="timeline-progress"></div>
                        
                        <?php if (!empty($tracking_history)): ?>
                            <?php foreach ($tracking_history as $index => $event): ?>
                                <?php 
                                    $is_active = $index === 0;
                                    $is_completed = !$is_active;
                                ?>
                                <div class="timeline-item <?= $is_active ? 'active' : ($is_completed ? 'completed' : '') ?>">
                                    <div class="timeline-icon">
                                        <?php if ($is_active): ?>
                                            <i class="fas fa-circle-notch fa-spin"></i>
                                        <?php else: ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-time">
                                            <i class="far fa-clock"></i>
                                            <?= date('M j, Y • h:i A', strtotime($event['tracking_date'] ?? $event['created_at'])) ?>
                                        </div>
                                        <div class="timeline-status">
                                            <?= htmlspecialchars($event['status']) ?>
                                        </div>
                                        <?php if (!empty($event['location'])): ?>
                                            <div class="timeline-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($event['location']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($event['description'])): ?>
                                            <div class="timeline-description">
                                                <?= htmlspecialchars($event['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($default_statuses)): ?>
                            <!-- Default timeline -->
                            <?php foreach ($default_statuses as $index => $stage): ?>
                                <div class="timeline-item <?= $stage['active'] ? 'active' : ($stage['completed'] ? 'completed' : '') ?>">
                                    <div class="timeline-icon">
                                        <?php if ($stage['active']): ?>
                                            <i class="fas fa-circle-notch fa-spin"></i>
                                        <?php elseif ($stage['completed']): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <i class="far fa-clock"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-status">
                                            <?= htmlspecialchars($stage['status']) ?>
                                        </div>
                                        <div class="timeline-description">
                                            <?= htmlspecialchars($stage['description']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                                <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No tracking history available yet.</p>
                                <p style="font-size: 0.875rem;">Tracking information will appear once your order is shipped.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <?php if (!empty($order['id'])): ?>
                        <a href="<?= BASE_URL ?>pages/order-detail.php?id=<?= $order['id'] ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                            View Order Details
                        </a>

                        <a href="<?= BASE_URL ?>pages/invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-success">
                            <i class="fas fa-file-invoice"></i>
                            Download Invoice
                        </a>
                    <?php endif; ?>

                    <button onclick="shareTracking()" class="btn btn-secondary">
                        <i class="fas fa-share-alt"></i>
                        Share Tracking
                    </button>

                    <a href="<?= BASE_URL ?>pages/contact.php?subject=Tracking%20Issue%20-%20<?= urlencode($tracking_number) ?>" 
                       class="btn btn-secondary">
                        <i class="fas fa-headset"></i>
                        Report Issue
                    </a>

                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i>
                        Print Tracking
                    </button>
                    
                    <a href="<?= BASE_URL ?>pages/track-order.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Track Another
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Demo Notice -->
        <div class="demo-notice">
            <i class="fas fa-info-circle"></i>
            <h4 style="color: #0369a1; margin-bottom: 0.5rem;">Demo Mode Active</h4>
            <p style="color: #0c4a6e; margin-bottom: 0.5rem;">
                This is a demonstration of our live tracking system. In production, this would show real carrier data.
            </p>
            <p style="color: #0c4a6e; font-size: 0.875rem;">
                Try different tracking numbers: 
                <a href="?tracking=123456789" style="color: #3b82f6; text-decoration: none;">TRACK-123456789</a> • 
                <a href="?tracking=987654321" style="color: #3b82f6; text-decoration: none;">TRACK-987654321</a> • 
                <a href="?tracking=DHL123456789" style="color: #3b82f6; text-decoration: none;">DHL-123456789</a>
            </p>
        </div>
    <?php endif; ?>
</main>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Global variables
let refreshInterval;
let lastUpdate = '<?= $order['last_checked'] ?? $order['updated_at'] ?? date('Y-m-d H:i:s') ?>';
let isRefreshing = false;

// Initialize live tracking
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($order): ?>
        // Start auto-refresh only if we have an order
        startAutoRefresh();
        
        // Setup event listeners
        setupEventListeners();
        
        // Check for initial updates
        checkForUpdates();
        
        // Setup visibility change handler
        document.addEventListener('visibilitychange', handleVisibilityChange);
    <?php endif; ?>
});

// Setup event listeners
function setupEventListeners() {
    // Refresh button
    const refreshBtn = document.getElementById('refreshTracking');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            if (!isRefreshing) {
                refreshTracking();
            }
        });
    }
    
    // Copy tracking number
    const copyBtn = document.getElementById('copyTracking');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            copyToClipboard('<?= htmlspecialchars($tracking_number) ?>');
        });
    }
});

// Start auto-refresh
function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        if (!document.hidden && !isRefreshing) {
            refreshTracking(true); // Silent refresh
        }
    }, 30000); // Refresh every 30 seconds
}

// Stop auto-refresh
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
}

// Handle visibility change
function handleVisibilityChange() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
        refreshTracking(true); // Silent refresh when tab becomes active
    }
}

// Refresh tracking data (demo version)
function refreshTracking(silent = false) {
    if (isRefreshing) return;
    
    isRefreshing = true;
    const refreshBtn = document.getElementById('refreshTracking');
    if (refreshBtn) {
        refreshBtn.classList.add('refreshing');
    }
    
    if (!silent) {
        showToast('Checking for updates...', 'info');
    }
    
    // Simulate API call with timeout
    setTimeout(() => {
        // In a real app, this would be an API call
        // For demo, we'll simulate random updates
        const randomUpdates = Math.random() > 0.7; // 30% chance of update
        
        if (randomUpdates) {
            // Simulate new tracking event
            const events = [
                { status: 'Arrived at Facility', location: 'Regional Distribution Center', description: 'Package has arrived at regional facility' },
                { status: 'In Transit', location: 'Main Sorting Hub', description: 'Package is moving through the network' },
                { status: 'Processed', location: 'Local Facility', description: 'Package has been processed for delivery' }
            ];
            
            const randomEvent = events[Math.floor(Math.random() * events.length)];
            lastUpdate = new Date().toISOString();
            
            // Update the timeline with new event
            const timeline = document.querySelector('.live-timeline');
            if (timeline) {
                const newEvent = document.createElement('div');
                newEvent.className = 'timeline-item active';
                newEvent.innerHTML = `
                    <div class="timeline-icon">
                        <i class="fas fa-circle-notch fa-spin"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-time">
                            <i class="far fa-clock"></i>
                            ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} • ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                        </div>
                        <div class="timeline-status">
                            ${randomEvent.status}
                        </div>
                        <div class="timeline-location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${randomEvent.location}
                        </div>
                        <div class="timeline-description">
                            ${randomEvent.description}
                        </div>
                    </div>
                `;
                
                // Insert at beginning of timeline
                timeline.insertBefore(newEvent, timeline.children[1]);
                
                // Update last updated time
                const lastUpdatedElement = document.querySelector('.tracking-header').querySelector('div:nth-child(2) div:nth-child(2)');
                if (lastUpdatedElement) {
                    lastUpdatedElement.textContent = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' • ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                }
            }
            
            if (!silent) {
                showToast('Tracking updated! New status: ' + randomEvent.status, 'success');
            }
        } else if (!silent) {
            showToast('No new updates available', 'info');
        }
        
        isRefreshing = false;
        if (refreshBtn) {
            refreshBtn.classList.remove('refreshing');
        }
    }, 1500); // Simulate network delay
}

// Check for updates on page load
function checkForUpdates() {
    const now = new Date();
    const lastChecked = new Date('<?= $order['last_checked'] ?? $order['updated_at'] ?? date('Y-m-d H:i:s') ?>');
    const hoursSinceUpdate = (now - lastChecked) / (1000 * 60 * 60);
    
    if (hoursSinceUpdate > 1) { // If last update was more than 1 hour ago
        refreshTracking(true);
    }
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Tracking number copied to clipboard!', 'success');
    }).catch(err => {
        showToast('Failed to copy', 'warning');
    });
}

// Share tracking
function shareTracking() {
    const shareData = {
        title: 'Track My Order #<?= htmlspecialchars($order['order_number'] ?? $order['id'] ?? '') ?>',
        text: `Track my order with tracking number: <?= htmlspecialchars($tracking_number) ?>`,
        url: window.location.href
    };
    
    if (navigator.share) {
        navigator.share(shareData).catch(console.error);
    } else {
        copyToClipboard(shareData.text + '\n\n' + shareData.url);
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${getToastIcon(type)}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

function getToastIcon(type) {
    switch(type) {
        case 'success': return 'check-circle';
        case 'warning': return 'exclamation-triangle';
        case 'error': return 'times-circle';
        default: return 'info-circle';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
// Helper functions for tracking
function detectCarrier($tracking_number) {
    if (empty($tracking_number)) return 'Generic';
    
    $patterns = [
        'DHL' => '/^DHL|^\d{10}$/i',
        'FedEx' => '/^FEDEX|^\d{12,20}$/i',
        'UPS' => '/^UPS|^1Z[A-Z0-9]{16}$/i',
        'USPS' => '/^USPS|^\d{20,26}$/i',
        'J&T' => '/^JT/i',
        'GIG' => '/^GIG/i',
        'Red Star' => '/^RS/i',
        'NIPOST' => '/^NP/i'
    ];
    
    foreach ($patterns as $carrier => $pattern) {
        if (preg_match($pattern, $tracking_number)) {
            return $carrier;
        }
    }
    
    return 'Generic';
}

function getCarrierInfo($carrier) {
    $carriers = [
        'DHL' => [
            'name' => 'DHL Express',
            'url' => 'https://www.dhl.com',
            'track_url' => 'https://www.dhl.com/en/express/tracking.html?AWB=',
            'phone' => '+1-800-225-5345'
        ],
        'FedEx' => [
            'name' => 'FedEx',
            'url' => 'https://www.fedex.com',
            'track_url' => 'https://www.fedex.com/fedextrack/?trknbr=',
            'phone' => '+1-800-463-3339'
        ],
        'UPS' => [
            'name' => 'UPS',
            'url' => 'https://www.ups.com',
            'track_url' => 'https://www.ups.com/track?tracknum=',
            'phone' => '+1-800-742-5877'
        ],
        'USPS' => [
            'name' => 'USPS',
            'url' => 'https://www.usps.com',
            'track_url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
            'phone' => '+1-800-275-8777'
        ],
        'J&T' => [
            'name' => 'J&T Express',
            'url' => 'https://www.jtexpress.my',
            'track_url' => 'https://www.jtexpress.my/track/',
            'phone' => '+60 3-9212 9212'
        ],
        'GIG' => [
            'name' => 'GIG Logistics',
            'url' => 'https://gigl.com.ng',
            'track_url' => 'https://gigl.com.ng/track/',
            'phone' => '+234 700 344 0000'
        ],
        'Red Star' => [
            'name' => 'Red Star Express',
            'url' => 'https://redstarplc.com',
            'track_url' => 'https://redstarplc.com/tracking/',
            'phone' => '+234 803 720 0000'
        ],
        'NIPOST' => [
            'name' => 'NIPOST',
            'url' => 'https://nipost.gov.ng',
            'track_url' => 'https://nipost.gov.ng/track-trace/',
            'phone' => '+234 803 720 0001'
        ],
        'Generic' => [
            'name' => 'Shipping Carrier',
            'url' => '#',
            'track_url' => '#',
            'phone' => 'Contact Support'
        ]
    ];
    
    return $carriers[$carrier] ?? $carriers['Generic'];
}

function calculateEstimatedDelivery($order_date, $carrier) {
    if (empty($order_date)) {
        $order_date = date('Y-m-d H:i:s');
    }
    
    $base_date = new DateTime($order_date);
    $delivery_days = [
        'DHL' => 3,
        'FedEx' => 2,
        'UPS' => 2,
        'USPS' => 5,
        'J&T' => 4,
        'GIG' => 3,
        'Red Star' => 4,
        'NIPOST' => 7,
        'Generic' => 5
    ];
    
    $days = $delivery_days[$carrier] ?? 5;
    $base_date->modify("+{$days} weekdays");
    
    // Add buffer for processing
    $base_date->modify('+1 day');
    
    return $base_date->format('Y-m-d H:i:s');
}

function getStatusDescription($status) {
    $descriptions = [
        'pending' => 'Order received, waiting for processing',
        'processing' => 'Order is being prepared for shipment',
        'shipped' => 'Package has been handed to carrier',
        'in_transit' => 'Package is moving through carrier network',
        'out_for_delivery' => 'Package is with delivery driver',
        'delivered' => 'Package has been delivered',
        'completed' => 'Order completed successfully',
        'cancelled' => 'Order was cancelled',
        'returned' => 'Package was returned to sender'
    ];
    
    return $descriptions[$status] ?? 'Tracking information unavailable';
}
?>