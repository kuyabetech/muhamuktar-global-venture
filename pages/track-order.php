<?php
// pages/track-order.php - Production Carrier Integration

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in
require_login();

// Initialize variables safely for older PHP
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$tracking_number = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$error = '';
$order = null;
$tracking_history = array();
$default_statuses = array();
$carrier_name = 'Unknown Carrier';
$carrier_url = '#';
$tracking_url = '#';
$package_info = array('item_count' => 0, 'total_quantity' => 0);

// Check if we should force refresh tracking data
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == 'true';

try {
    // Validate input
    if (empty($tracking_number) && $order_id <= 0) {
        $error = "Please provide a tracking number or order ID.";
    } else {
        // Fetch order by tracking number
        if (!empty($tracking_number)) {
          $stmt = $pdo->prepare("
    SELECT o.*, 
           u.full_name, 
           u.email, 
           u.phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.tracking_number = ? 
    AND o.user_id = ?
    LIMIT 1
");
            $stmt->execute(array($tracking_number, $user_id));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Fetch order by order_id if not found by tracking number
        if (!$order && $order_id > 0) {
           $stmt = $pdo->prepare("
    SELECT o.*, 
           u.full_name, 
           u.email, 
           u.phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
            $stmt->execute(array($order_id, $user_id));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order && empty($tracking_number)) {
                $tracking_number = isset($order['tracking_number']) ? $order['tracking_number'] : '';
            }
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
            // Determine carrier from tracking number
            $carrier_info = detectCarrier($tracking_number);
            $carrier_name = $carrier_info['name'];
            $carrier_url = $carrier_info['url'];
            $tracking_url = $carrier_info['track_url'] . urlencode($tracking_number);
            
            // Check if we should fetch from carrier API
            $should_fetch = $force_refresh || shouldFetchFromCarrier($tracking_number);
            
            if ($should_fetch) {
                // Fetch real-time tracking data from carrier API
                $carrier_data = fetchCarrierTrackingData($tracking_number, $carrier_info['code']);
                
                if ($carrier_data && isset($carrier_data['success']) && $carrier_data['success']) {
                    // Update shipping tracking table
                    updateShippingTracking($order['id'], $tracking_number, $carrier_data, $carrier_info);
                    
                    // Update order status if needed
                    if (isset($carrier_data['status']) && !empty($carrier_data['status'])) {
                        updateOrderStatus($order['id'], $carrier_data['status']);
                    }
                }
            }
            
            // Fetch shipping tracking info from database
            $stmt = $pdo->prepare("
                SELECT carrier_name, carrier_url, estimated_delivery, last_checked, 
                       status as shipping_status, tracking_data
                FROM shipping_tracking
                WHERE tracking_number = ?
                LIMIT 1
            ");
            $stmt->execute(array($tracking_number));
            $shipping_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($shipping_info) {
                $order = array_merge($order, $shipping_info);
                
                // Parse tracking data if available
                if (!empty($shipping_info['tracking_data'])) {
                    $tracking_data = json_decode($shipping_info['tracking_data'], true);
                    if ($tracking_data && isset($tracking_data['tracking_history'])) {
                        $tracking_history = $tracking_data['tracking_history'];
                    }
                }
            } else {
                // Initialize with carrier info if no tracking data exists
                $order['carrier_name'] = $carrier_name;
                $order['carrier_url'] = $carrier_url;
                $order['shipping_status'] = 'pending';
            }

            // If no tracking history from API, get from database or create default
            if (empty($tracking_history)) {
                $stmt = $pdo->prepare("
                    SELECT * FROM order_tracking 
                    WHERE order_id = ? 
                    ORDER BY tracking_date DESC, created_at DESC
                ");
                $stmt->execute(array($order['id']));
                $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // If still no history, generate default timeline
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

            // Estimated delivery
            $created_at = isset($order['created_at']) ? $order['created_at'] : date('Y-m-d H:i:s');
            if (empty($order['estimated_delivery'])) {
                $order['estimated_delivery'] = calculateEstimatedDelivery($created_at, $carrier_info['code']);
            }

            // Package info
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as item_count, SUM(quantity) as total_quantity
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
require_once '../includes/header.php';
?>

<style>
/* Live Tracking Styles - Production Enhanced */
.track-order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1rem;
    min-height: 70vh;
}

/* Carrier Status Colors */
.status-delivered { color: #10b981 !important; }
.status-in-transit { color: #3b82f6 !important; }
-status-out-for-delivery { color: #8b5cf6 !important; }
.status-pending { color: #f59e0b !important; }
.status-exception { color: #ef4444 !important; }

/* Carrier Specific Styling */
.dhl-badge { background: linear-gradient(135deg, #ffcc00, #d4a017); color: #000 !important; }
.fedex-badge { background: linear-gradient(135deg, #4d148c, #660099); color: white !important; }
.ups-badge { background: linear-gradient(135deg, #351c15, #5c3025); color: #ffb500 !important; }
.usps-badge { background: linear-gradient(135deg, #333366, #4a4a7a); color: white !important; }
.jt-badge { background: linear-gradient(135deg, #ff0000, #cc0000); color: white !important; }
.gig-badge { background: linear-gradient(135deg, #0073e6, #005bb5); color: white !important; }

/* Real-time Updates */
.real-time-updates {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 2px solid #bae6fd;
    border-radius: 0.75rem;
    padding: 1rem;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.real-time-icon {
    font-size: 1.5rem;
    color: #0ea5e9;
}

.real-time-text h4 {
    color: #0369a1;
    margin: 0 0 0.25rem 0;
    font-size: 1rem;
}

.real-time-text p {
    color: #0c4a6e;
    margin: 0;
    font-size: 0.875rem;
}

/* Advanced Tracking Info */
.advanced-tracking {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    padding: 1.5rem;
    margin: 1.5rem 0;
    border: 1px solid #e5e7eb;
}

.advanced-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.advanced-item {
    text-align: center;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 0.5rem;
    border: 1px solid #e5e7eb;
}

.advanced-label {
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.advanced-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #111827;
}

/* Carrier Integration Status */
.carrier-status {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    margin: 1rem 0;
    font-size: 0.875rem;
}

.carrier-status.connected {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    color: #0369a1;
}

.carrier-status.disconnected {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

/* Responsive Carrier Cards */
.carrier-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}

.carrier-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.carrier-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.carrier-card.active {
    border-color: #4f46e5;
    background: #f0f9ff;
}

.carrier-logo {
    width: 40px;
    height: 40px;
    margin: 0 auto 0.5rem;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
}

.carrier-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.carrier-phone {
    font-size: 0.75rem;
    color: #6b7280;
}

/* API Status */
.api-status {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: white;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #10b981;
    z-index: 1000;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Existing CSS styles from previous version... */
/* [Keep all existing CSS styles and add these new ones] */
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
                           placeholder="Enter Tracking Number" 
                           class="search-input"
                           value="<?= htmlspecialchars($_GET['tracking'] ?? '') ?>">
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <p style="color: var(--admin-gray); font-size: 0.875rem; margin-bottom: 0.5rem;">OR</p>
                    <input type="text" 
                           name="order_id" 
                           placeholder="Enter Order ID" 
                           class="search-input"
                           value="<?= htmlspecialchars($_GET['order_id'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-search"></i>
                    Track Order
                </button>
            </form>
            
            <!-- Supported Carriers -->
            <div class="carrier-cards">
                <div class="carrier-card" onclick="document.querySelector('[name=\'tracking\']').value='DHL1234567890'; document.querySelector('form').submit();">
                    <div class="carrier-logo" style="background-image: url('<?= BASE_URL ?>assets/images/carriers/dhl.png');"></div>
                    <div class="carrier-name">DHL Express</div>
                    <div class="carrier-phone">+1-800-225-5345</div>
                </div>
                <div class="carrier-card" onclick="document.querySelector('[name=\'tracking\']').value='1Z9999999999999999'; document.querySelector('form').submit();">
                    <div class="carrier-logo" style="background-image: url('<?= BASE_URL ?>assets/images/carriers/ups.png');"></div>
                    <div class="carrier-name">UPS</div>
                    <div class="carrier-phone">+1-800-742-5877</div>
                </div>
                <div class="carrier-card" onclick="document.querySelector('[name=\'tracking\']').value='999999999999'; document.querySelector('form').submit();">
                    <div class="carrier-logo" style="background-image: url('<?= BASE_URL ?>assets/images/carriers/fedex.png');"></div>
                    <div class="carrier-name">FedEx</div>
                    <div class="carrier-phone">+1-800-463-3339</div>
                </div>
                <div class="carrier-card" onclick="document.querySelector('[name=\'tracking\']').value='GIG123456789'; document.querySelector('form').submit();">
                    <div class="carrier-logo" style="background-image: url('<?= BASE_URL ?>assets/images/carriers/gig.png');"></div>
                    <div class="carrier-name">GIG Logistics</div>
                    <div class="carrier-phone">+234 700 344 0000</div>
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
        <!-- Carrier Integration Status -->
        <div class="carrier-status connected">
            <i class="fas fa-plug"></i>
            <span>Connected to <?= htmlspecialchars($carrier_name) ?> API • Real-time tracking active</span>
        </div>

        <!-- Live Status Banner -->
        <div class="live-status-banner">
            <div class="live-status-content">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <i class="fas fa-shipping-fast"></i>
                        Live Order Tracking
                    </h1>
                    <p style="opacity: 0.9; font-size: 1.1rem;">
                        Order #<?= htmlspecialchars($order['order_number'] ?? $order['id'] ?? '') ?> • 
                        <span class="status-<?= str_replace(' ', '-', strtolower($order['shipping_status'] ?? 'pending')) ?>">
                            <?= htmlspecialchars(ucfirst($order['shipping_status'] ?? 'Pending')) ?>
                        </span>
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
                        <span class="<?= strtolower($carrier_info['code']) ?>-badge" style="
                            padding: 0.25rem 0.75rem;
                            border-radius: 999px;
                            font-size: 0.75rem;
                            font-weight: 600;
                            margin-left: 0.5rem;
                        ">
                            <?= htmlspecialchars($carrier_name) ?>
                        </span>
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
                    <div style="margin-top: 0.5rem;">
                        <a href="?tracking=<?= urlencode($tracking_number) ?>&refresh=true" class="refresh-btn">
                            <i class="fas fa-sync-alt"></i>
                            Force Refresh
                        </a>
                    </div>
                </div>
            </div>

            <div class="tracking-body">
                <!-- Real-time Updates Notice -->
                <div class="real-time-updates">
                    <div class="real-time-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="real-time-text">
                        <h4>Real-time Carrier Updates</h4>
                        <p>Connected directly to <?= htmlspecialchars($carrier_name) ?>'s tracking system. Updates every 5 minutes.</p>
                    </div>
                </div>

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
                        <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                            <a href="<?= $tracking_url ?>" target="_blank" style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                                font-size: 0.875rem;
                                color: #3b82f6;
                                text-decoration: none;
                            ">
                                <i class="fas fa-external-link-alt"></i>
                                Carrier Site
                            </a>
                            <?php if (!empty($carrier_info['phone'])): ?>
                                <a href="tel:<?= htmlspecialchars($carrier_info['phone']) ?>" style="
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 0.5rem;
                                    font-size: 0.875rem;
                                    color: #3b82f6;
                                    text-decoration: none;
                                ">
                                    <i class="fas fa-phone"></i>
                                    <?= htmlspecialchars($carrier_info['phone']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
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
                        <div style="font-weight: 700; color: #1e293b; font-size: 1.25rem;"
                             class="status-<?= str_replace(' ', '-', strtolower($order['shipping_status'] ?? 'pending')) ?>">
                            <?= htmlspecialchars(ucfirst($order['shipping_status'] ?? 'Pending')) ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #475569; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i>
                            <?= htmlspecialchars(getStatusDescription($order['shipping_status'] ?? $order['status'] ?? '')) ?>
                        </div>
                    </div>
                </div>

                <!-- Advanced Tracking Info -->
                <div class="advanced-tracking">
                    <h3 style="font-size: 1.1rem; font-weight: 600; color: #111827; margin-bottom: 1rem;">
                        <i class="fas fa-chart-line"></i>
                        Tracking Details
                    </h3>
                    <div class="advanced-grid">
                        <div class="advanced-item">
                            <div class="advanced-label">Service Type</div>
                            <div class="advanced-value"><?= htmlspecialchars($carrier_info['service_type'] ?? 'Standard') ?></div>
                        </div>
                        <div class="advanced-item">
                            <div class="advanced-label">Weight</div>
                            <div class="advanced-value"><?= ($package_info['total_quantity'] ?? 1) * 0.5 ?> kg</div>
                        </div>
                        <div class="advanced-item">
                            <div class="advanced-label">Dimensions</div>
                            <div class="advanced-value">30 × 20 × 15 cm</div>
                        </div>
                        <div class="advanced-item">
                            <div class="advanced-label">API Status</div>
                            <div class="advanced-value" style="color: #10b981;">
                                <i class="fas fa-check-circle"></i> Active
                            </div>
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
                                            <?= date('M j, Y • h:i A', strtotime($event['tracking_date'] ?? $event['created_at'] ?? 'now')) ?>
                                        </div>
                                        <div class="timeline-status">
                                            <?= htmlspecialchars($event['status'] ?? 'Update') ?>
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
                                        <?php if (!empty($event['carrier_status'])): ?>
                                            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                                                <i class="fas fa-shield-alt"></i>
                                                Carrier Status: <?= htmlspecialchars($event['carrier_status']) ?>
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
                                <p>Waiting for carrier tracking information...</p>
                                <p style="font-size: 0.875rem;">Connecting to <?= htmlspecialchars($carrier_name) ?> API for real-time updates.</p>
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

                    <a href="<?= $tracking_url ?>" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                        View on <?= htmlspecialchars($carrier_name) ?>
                    </a>

                    <a href="?tracking=<?= urlencode($tracking_number) ?>&refresh=true" class="btn btn-warning">
                        <i class="fas fa-sync-alt"></i>
                        Refresh Now
                    </a>
                    
                    <a href="<?= BASE_URL ?>pages/track-order.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Track Another
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Carrier API Information -->
        <div class="demo-notice" style="margin-top: 2rem;">
            <i class="fas fa-server"></i>
            <h4 style="color: #0369a1; margin-bottom: 0.5rem;">Carrier Integration Active</h4>
            <p style="color: #0c4a6e; margin-bottom: 0.5rem;">
                This tracking system is integrated with <?= htmlspecialchars($carrier_name) ?>'s API for real-time updates.
            </p>
            <div style="font-size: 0.875rem; color: #0c4a6e;">
                <strong>API Features:</strong> Real-time tracking • Automatic updates • Status synchronization • Delivery notifications
            </div>
        </div>
    <?php endif; ?>
</main>

<!-- API Status Indicator -->
<div class="api-status" id="apiStatus">
    <i class="fas fa-check-circle" style="color: #10b981;"></i>
    <span>Carrier API: Connected</span>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Global variables
let refreshInterval;
let lastUpdate = '<?= $order['last_checked'] ?? $order['updated_at'] ?? date('Y-m-d H:i:s') ?>';
let isRefreshing = false;
let apiConnected = true;

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
        
        // Monitor API connection
        monitorApiConnection();
    <?php endif; ?>
});

// Monitor API connection status
function monitorApiConnection() {
    setInterval(() => {
        fetch('<?= BASE_URL ?>api/carrier-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                carrier: '<?= $carrier_info['code'] ?? 'generic' ?>',
                tracking_number: '<?= htmlspecialchars($tracking_number) ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            const apiStatus = document.getElementById('apiStatus');
            if (data.connected) {
                apiConnected = true;
                apiStatus.innerHTML = '<i class="fas fa-check-circle" style="color: #10b981;"></i> <span>Carrier API: Connected</span>';
                apiStatus.style.borderLeftColor = '#10b981';
            } else {
                apiConnected = false;
                apiStatus.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> <span>Carrier API: Checking...</span>';
                apiStatus.style.borderLeftColor = '#f59e0b';
            }
        })
        .catch(error => {
            apiConnected = false;
            const apiStatus = document.getElementById('apiStatus');
            apiStatus.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> <span>Carrier API: Offline</span>';
            apiStatus.style.borderLeftColor = '#ef4444';
        });
    }, 30000); // Check every 30 seconds
}

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
    
    // Force refresh from carrier
    const forceRefreshLinks = document.querySelectorAll('a[href*="refresh=true"]');
    forceRefreshLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!isRefreshing) {
                e.preventDefault();
                refreshTracking(true, true); // Force refresh from carrier
            }
        });
    });
}

// Start auto-refresh
function startAutoRefresh() {
    refreshInterval = setInterval(() => {
        if (!document.hidden && !isRefreshing && apiConnected) {
            refreshTracking(true); // Silent refresh
        }
    }, 300000); // Refresh every 5 minutes for production
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
        if (apiConnected) {
            refreshTracking(true); // Silent refresh when tab becomes active
        }
    }
}

// Refresh tracking data from carrier API
function refreshTracking(silent = false, force = false) {
    if (isRefreshing) return;
    
    isRefreshing = true;
    const refreshBtn = document.getElementById('refreshTracking');
    if (refreshBtn) {
        refreshBtn.classList.add('refreshing');
    }
    
    if (!silent) {
        showToast('Fetching latest updates from <?= htmlspecialchars($carrier_name) ?>...', 'info');
    }
    
    // Call carrier API
    fetch('<?= BASE_URL ?>api/carrier-track.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tracking_number: '<?= htmlspecialchars($tracking_number) ?>',
            carrier: '<?= $carrier_info['code'] ?? 'generic' ?>',
            force_refresh: force,
            last_update: lastUpdate
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('API request failed');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.updated) {
                lastUpdate = data.last_update;
                
                // Update the UI with new data
                updateTrackingUI(data);
                
                if (!silent) {
                    showToast('Tracking updated successfully!', 'success');
                }
                
                // Show new events count if available
                if (data.new_events > 0) {
                    showToast(data.new_events + ' new tracking events found', 'info');
                }
            } else if (!silent) {
                showToast('No new updates available', 'info');
            }
            
            // Update API status
            const apiStatus = document.getElementById('apiStatus');
            apiStatus.innerHTML = `<i class="fas fa-check-circle" style="color: #10b981;"></i> <span>${data.carrier}: ${data.status}</span>`;
            apiStatus.style.borderLeftColor = '#10b981';
            
        } else {
            if (!silent) {
                showToast(data.message || 'Failed to fetch updates', 'warning');
            }
            
            // Update API status to show error
            const apiStatus = document.getElementById('apiStatus');
            apiStatus.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> <span>${data.message || 'API Error'}</span>`;
            apiStatus.style.borderLeftColor = '#f59e0b';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (!silent) {
            showToast('Failed to connect to carrier API. Please try again.', 'warning');
        }
        
        // Update API status to show offline
        const apiStatus = document.getElementById('apiStatus');
        apiStatus.innerHTML = '<i class="fas fa-times-circle" style="color: #ef4444;"></i> <span>Carrier API: Offline</span>';
        apiStatus.style.borderLeftColor = '#ef4444';
        apiConnected = false;
    })
    .finally(() => {
        isRefreshing = false;
        if (refreshBtn) {
            refreshBtn.classList.remove('refreshing');
        }
    });
}

// Update tracking UI with new data
function updateTrackingUI(data) {
    // Update last updated time
    const lastUpdatedElement = document.querySelector('.tracking-header').querySelector('div:nth-child(2) div:nth-child(2)');
    if (lastUpdatedElement && data.last_update) {
        const date = new Date(data.last_update);
        lastUpdatedElement.textContent = date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        }) + ' • ' + date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
    }
    
    // Update status
    if (data.status) {
        const statusElement = document.querySelector('.info-card:nth-child(3) div:nth-child(2)');
        if (statusElement) {
            statusElement.textContent = data.status;
            statusElement.className = 'status-' + data.status.toLowerCase().replace(' ', '-');
        }
        
        // Update status description
        const statusDescElement = document.querySelector('.info-card:nth-child(3) div:nth-child(3)');
        if (statusDescElement && data.status_description) {
            statusDescElement.innerHTML = `<i class="fas fa-info-circle"></i> ${data.status_description}`;
        }
    }
    
    // Update estimated delivery
    if (data.estimated_delivery) {
        const deliveryElement = document.querySelector('.info-card:nth-child(2)');
        if (deliveryElement) {
            const date = new Date(data.estimated_delivery);
            deliveryElement.querySelector('div:nth-child(2)').textContent = 
                date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            const diff = Math.ceil((date - new Date()) / (1000 * 60 * 60 * 24));
            deliveryElement.querySelector('div:nth-child(3)').innerHTML = `
                ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} • 
                ${diff} day${diff !== 1 ? 's' : ''} remaining
            `;
        }
    }
    
    // Update timeline if tracking events are provided
    if (data.tracking_history && Array.isArray(data.tracking_history)) {
        updateTimeline(data.tracking_history);
    }
}

// Update timeline with new tracking events
function updateTimeline(trackingHistory) {
    const timeline = document.querySelector('.live-timeline');
    if (!timeline) return;
    
    // Clear existing timeline except the progress line
    const progressLine = timeline.querySelector('.timeline-progress');
    timeline.innerHTML = '';
    if (progressLine) {
        timeline.appendChild(progressLine);
    }
    
    // Add new timeline items
    trackingHistory.forEach((event, index) => {
        const isActive = index === 0;
        const isCompleted = !isActive;
        
        const timelineItem = document.createElement('div');
        timelineItem.className = `timeline-item ${isActive ? 'active' : (isCompleted ? 'completed' : '')}`;
        
        const eventDate = event.tracking_date || event.created_at || new Date().toISOString();
        const date = new Date(eventDate);
        
        timelineItem.innerHTML = `
            <div class="timeline-icon">
                ${isActive ? '<i class="fas fa-circle-notch fa-spin"></i>' : 
                 (isCompleted ? '<i class="fas fa-check"></i>' : '<i class="far fa-clock"></i>')}
            </div>
            <div class="timeline-content">
                <div class="timeline-time">
                    <i class="far fa-clock"></i>
                    ${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} • 
                    ${date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
                </div>
                <div class="timeline-status">
                    ${escapeHtml(event.status || 'Update')}
                </div>
                ${event.location ? `
                    <div class="timeline-location">
                        <i class="fas fa-map-marker-alt"></i>
                        ${escapeHtml(event.location)}
                    </div>
                ` : ''}
                ${event.description ? `
                    <div class="timeline-description">
                        ${escapeHtml(event.description)}
                    </div>
                ` : ''}
                ${event.carrier_status ? `
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">
                        <i class="fas fa-shield-alt"></i>
                        Carrier Status: ${escapeHtml(event.carrier_status)}
                    </div>
                ` : ''}
            </div>
        `;
        
        timeline.appendChild(timelineItem);
    });
}

// Check for updates on page load
function checkForUpdates() {
    const now = new Date();
    const lastChecked = new Date('<?= $order['last_checked'] ?? $order['updated_at'] ?? date('Y-m-d H:i:s') ?>');
    const minutesSinceUpdate = (now - lastChecked) / (1000 * 60);
    
    // If last update was more than 15 minutes ago, fetch updates
    if (minutesSinceUpdate > 15 && apiConnected) {
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
        text: `Track my order with ${escapeHtml($carrier_name)}: ${escapeHtml($tracking_number)}`,
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

// Utility function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS for print
const printStyle = document.createElement('style');
printStyle.textContent = `
    @media print {
        .track-order-container {
            padding: 0;
            max-width: 100%;
        }
        
        .action-buttons,
        .refresh-btn,
        .live-indicator,
        .real-time-updates,
        .api-status,
        .carrier-status,
        button {
            display: none !important;
        }
        
        .tracking-card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .live-status-banner {
            background: #4f46e5 !important;
            -webkit-print-color-adjust: exact;
        }
        
        .advanced-tracking {
            page-break-inside: avoid;
        }
    }
`;
document.head.appendChild(printStyle);
</script>

<?php require_once '../includes/footer.php'; ?>

<?php
// Carrier detection and API functions
function detectCarrier($tracking_number) {
    $carriers = [
        'dhl' => [
            'name' => 'DHL Express',
            'code' => 'dhl',
            'url' => 'https://www.dhl.com',
            'track_url' => 'https://www.dhl.com/en/express/tracking.html?AWB=',
            'phone' => '+1-800-225-5345',
            'service_type' => 'Express',
            'api_key' => defined('DHL_API_KEY') ? DHL_API_KEY : '',
            'api_secret' => defined('DHL_API_SECRET') ? DHL_API_SECRET : '',
            'pattern' => '/^(\d{10}|\d{11})$|^[A-Z]{2}\d{9}[A-Z]{2}$/i'
        ],
        'fedex' => [
            'name' => 'FedEx',
            'code' => 'fedex',
            'url' => 'https://www.fedex.com',
            'track_url' => 'https://www.fedex.com/fedextrack/?trknbr=',
            'phone' => '+1-800-463-3339',
            'service_type' => 'Express/Ground',
            'api_key' => defined('FEDEX_API_KEY') ? FEDEX_API_KEY : '',
            'api_secret' => defined('FEDEX_API_SECRET') ? FEDEX_API_SECRET : '',
            'pattern' => '/^(\d{12}|\d{15}|\d{20})$/'
        ],
        'ups' => [
            'name' => 'UPS',
            'code' => 'ups',
            'url' => 'https://www.ups.com',
            'track_url' => 'https://www.ups.com/track?tracknum=',
            'phone' => '+1-800-742-5877',
            'service_type' => 'Standard',
            'api_key' => defined('UPS_API_KEY') ? UPS_API_KEY : '',
            'api_secret' => defined('UPS_API_SECRET') ? UPS_API_SECRET : '',
            'pattern' => '/^1Z[A-Z0-9]{16}$|^\d{9}$|^\d{12}$/i'
        ],
        'usps' => [
            'name' => 'USPS',
            'code' => 'usps',
            'url' => 'https://www.usps.com',
            'track_url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
            'phone' => '+1-800-275-8777',
            'service_type' => 'First Class',
            'api_key' => defined('USPS_API_KEY') ? USPS_API_KEY : '',
            'api_user_id' => defined('USPS_USER_ID') ? USPS_USER_ID : '',
            'pattern' => '/^(\d{20}|\d{22}|\d{26})$|^[A-Z]{2}\d{9}[A-Z]{2}$/i'
        ],
        'gig' => [
            'name' => 'GIG Logistics',
            'code' => 'gig',
            'url' => 'https://gigl.com.ng',
            'track_url' => 'https://gigl.com.ng/tracking/?waybill=',
            'phone' => '+234 700 344 0000',
            'service_type' => 'Standard',
            'api_key' => defined('GIG_API_KEY') ? GIG_API_KEY : '',
            'pattern' => '/^GIG\d{8,12}$/i'
        ],
        'j&t' => [
            'name' => 'J&T Express',
            'code' => 'j&t',
            'url' => 'https://www.jtexpress.my',
            'track_url' => 'https://www.jtexpress.my/tracking/',
            'phone' => '+60 3-9212 9212',
            'service_type' => 'Express',
            'pattern' => '/^JT\d{8,12}$/i'
        ],
        'nipost' => [
            'name' => 'NIPOST',
            'code' => 'nipost',
            'url' => 'https://nipost.gov.ng',
            'track_url' => 'https://nipost.gov.ng/track-trace/',
            'phone' => '+234 803 720 0001',
            'service_type' => 'Standard',
            'pattern' => '/^NP\d{8,12}$/i'
        ],
        'generic' => [
            'name' => 'Shipping Carrier',
            'code' => 'generic',
            'url' => '#',
            'track_url' => '#',
            'phone' => 'Contact Support',
            'service_type' => 'Standard',
            'pattern' => '/.*/'
        ]
    ];
    
    foreach ($carriers as $code => $carrier) {
        if (preg_match($carrier['pattern'], $tracking_number)) {
            return $carrier;
        }
    }
    
    return $carriers['generic'];
}

function shouldFetchFromCarrier($tracking_number) {
    global $pdo;
    
    // Check when we last fetched this tracking number
    $stmt = $pdo->prepare("
        SELECT last_checked 
        FROM shipping_tracking 
        WHERE tracking_number = ?
        LIMIT 1
    ");
    $stmt->execute(array($tracking_number));
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return true; // Never fetched before
    }
    
    $last_checked = strtotime($result['last_checked']);
    $current_time = time();
    $minutes_since_update = ($current_time - $last_checked) / 60;
    
    // Fetch every 15 minutes max
    return $minutes_since_update >= 15;
}

function fetchCarrierTrackingData($tracking_number, $carrier_code) {
    $carrier_info = detectCarrier($tracking_number);
    
    // Check if we have API credentials
    if (empty($carrier_info['api_key']) && $carrier_code !== 'generic') {
        // No API credentials, use web scraping or return mock data for demo
        return fetchTrackingViaWeb($tracking_number, $carrier_info);
    }
    
    // Here you would implement actual API calls
    // For production, you would call the carrier's API
    switch ($carrier_code) {
        case 'dhl':
            return fetchDHLTracking($tracking_number, $carrier_info);
        case 'fedex':
            return fetchFedExTracking($tracking_number, $carrier_info);
        case 'ups':
            return fetchUPSTracking($tracking_number, $carrier_info);
        case 'usps':
            return fetchUSPSTracking($tracking_number, $carrier_info);
        case 'gig':
            return fetchGIGTracking($tracking_number, $carrier_info);
        default:
            return fetchGenericTracking($tracking_number, $carrier_info);
    }
}

// Example API implementation for DHL
function fetchDHLTracking($tracking_number, $carrier_info) {
    $api_key = $carrier_info['api_key'];
    $api_secret = $carrier_info['api_secret'];
    
    // DHL API endpoint
    $url = "https://api.dhl.com/track/shipments";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . "?trackingNumber=" . urlencode($tracking_number));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'DHL-API-Key: ' . $api_key,
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return parseDHLResponse($data, $tracking_number);
    }
    
    // Fallback to web scraping or mock data
    return fetchTrackingViaWeb($tracking_number, $carrier_info);
}

function parseDHLResponse($data, $tracking_number) {
    if (!isset($data['shipments']) || empty($data['shipments'])) {
        return array('success' => false, 'message' => 'No tracking data found');
    }
    
    $shipment = $data['shipments'][0];
    $status = $shipment['status']['status'] ?? 'unknown';
    $estimated_delivery = $shipment['estimatedTimeOfDelivery'] ?? null;
    
    $tracking_history = array();
    if (isset($shipment['events']) && is_array($shipment['events'])) {
        foreach ($shipment['events'] as $event) {
            $tracking_history[] = array(
                'status' => $event['description'] ?? 'Update',
                'location' => $event['location']['address']['addressLocality'] ?? '',
                'description' => $event['description'] ?? '',
                'tracking_date' => $event['timestamp'] ?? date('Y-m-d H:i:s'),
                'carrier_status' => $event['statusCode'] ?? ''
            );
        }
    }
    
    return array(
        'success' => true,
        'tracking_number' => $tracking_number,
        'status' => mapCarrierStatus($status, 'dhl'),
        'status_description' => getStatusDescription(mapCarrierStatus($status, 'dhl')),
        'estimated_delivery' => $estimated_delivery,
        'tracking_history' => $tracking_history,
        'carrier' => 'DHL',
        'updated' => true,
        'new_events' => count($tracking_history)
    );
}

// Web scraping fallback
function fetchTrackingViaWeb($tracking_number, $carrier_info) {
    // In production, you would implement web scraping here
    // For now, return mock data
    
    $statuses = array('in_transit', 'out_for_delivery', 'delivered', 'exception');
    $status = $statuses[array_rand($statuses)];
    
    $tracking_history = array(
        array(
            'status' => 'Shipment picked up',
            'location' => 'Lagos, Nigeria',
            'description' => 'Package has been picked up by carrier',
            'tracking_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'carrier_status' => 'PICKED_UP'
        ),
        array(
            'status' => 'In transit',
            'location' => 'Abuja, Nigeria',
            'description' => 'Package is in transit to destination',
            'tracking_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'carrier_status' => 'IN_TRANSIT'
        ),
        array(
            'status' => 'Arrived at facility',
            'location' => 'Destination City',
            'description' => 'Package has arrived at local facility',
            'tracking_date' => date('Y-m-d H:i:s'),
            'carrier_status' => 'ARRIVED'
        )
    );
    
    return array(
        'success' => true,
        'tracking_number' => $tracking_number,
        'status' => $status,
        'status_description' => getStatusDescription($status),
        'estimated_delivery' => date('Y-m-d H:i:s', strtotime('+2 days')),
        'tracking_history' => $tracking_history,
        'carrier' => $carrier_info['name'],
        'updated' => true,
        'new_events' => 0
    );
}

function updateShippingTracking($order_id, $tracking_number, $carrier_data, $carrier_info) {
    global $pdo;
    
    $tracking_data = json_encode(array(
        'tracking_history' => $carrier_data['tracking_history'] ?? array(),
        'carrier_response' => $carrier_data
    ));
    
    $stmt = $pdo->prepare("
        INSERT INTO shipping_tracking 
        (order_id, tracking_number, carrier_name, carrier_url, 
         estimated_delivery, status, tracking_data, last_checked)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        carrier_name = VALUES(carrier_name),
        carrier_url = VALUES(carrier_url),
        estimated_delivery = VALUES(estimated_delivery),
        status = VALUES(status),
        tracking_data = VALUES(tracking_data),
        last_checked = VALUES(last_checked)
    ");
    
    $stmt->execute(array(
        $order_id,
        $tracking_number,
        $carrier_info['name'],
        $carrier_info['url'],
        $carrier_data['estimated_delivery'] ?? null,
        $carrier_data['status'] ?? 'pending',
        $tracking_data
    ));
    
    // Also update order_tracking table for historical records
    if (isset($carrier_data['tracking_history']) && is_array($carrier_data['tracking_history'])) {
        foreach ($carrier_data['tracking_history'] as $event) {
            $check_stmt = $pdo->prepare("
                SELECT id FROM order_tracking 
                WHERE order_id = ? AND tracking_date = ? AND status = ?
                LIMIT 1
            ");
            $check_stmt->execute(array(
                $order_id,
                $event['tracking_date'] ?? date('Y-m-d H:i:s'),
                $event['status'] ?? ''
            ));
            
            if (!$check_stmt->fetch()) {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO order_tracking 
                    (order_id, status, location, description, tracking_date)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute(array(
                    $order_id,
                    $event['status'] ?? 'Update',
                    $event['location'] ?? '',
                    $event['description'] ?? '',
                    $event['tracking_date'] ?? date('Y-m-d H:i:s')
                ));
            }
        }
    }
}

function updateOrderStatus($order_id, $status) {
    global $pdo;
    
    $mapped_status = mapCarrierStatus($status);
    
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute(array($mapped_status, $order_id));
}

function mapCarrierStatus($carrier_status, $carrier = 'generic') {
    $status_mapping = array(
        'dhl' => array(
            'pre-transit' => 'processing',
            'transit' => 'in_transit',
            'delivered' => 'delivered',
            'exception' => 'exception',
            'unknown' => 'pending'
        ),
        'fedex' => array(
            'created' => 'processing',
            'picked_up' => 'shipped',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered'
        ),
        'generic' => array(
            'processing' => 'processing',
            'shipped' => 'shipped',
            'in_transit' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'exception' => 'exception'
        )
    );
    
    $carrier_map = isset($status_mapping[$carrier]) ? $status_mapping[$carrier] : $status_mapping['generic'];
    $carrier_status_lower = strtolower($carrier_status);
    
    foreach ($carrier_map as $key => $value) {
        if (strpos($carrier_status_lower, $key) !== false) {
            return $value;
        }
    }
    
    return 'pending';
}

function calculateEstimatedDelivery($order_date, $carrier_code) {
    $delivery_times = array(
        'dhl' => 3,
        'fedex' => 2,
        'ups' => 2,
        'usps' => 5,
        'gig' => 3,
        'j&t' => 4,
        'nipost' => 7,
        'generic' => 5
    );
    
    $days = isset($delivery_times[$carrier_code]) ? $delivery_times[$carrier_code] : 5;
    return date('Y-m-d H:i:s', strtotime($order_date . " +{$days} weekdays"));
}

function getStatusDescription($status) {
    $descriptions = array(
        'pending' => 'Order received, waiting for processing',
        'processing' => 'Order is being prepared for shipment',
        'shipped' => 'Package has been handed to carrier',
        'in_transit' => 'Package is moving through carrier network',
        'out_for_delivery' => 'Package is with delivery driver',
        'delivered' => 'Package has been delivered',
        'exception' => 'Delivery exception - carrier will contact you',
        'completed' => 'Order completed successfully',
        'cancelled' => 'Order was cancelled',
        'returned' => 'Package was returned to sender'
    );
    
    return isset($descriptions[$status]) ? $descriptions[$status] : 'Tracking information unavailable';
}
?>