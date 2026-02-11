<?php
// api/tracking-update.php - Live Tracking API Endpoint

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
$tracking_number = $data['tracking_number'] ?? '';
$order_id = $data['order_id'] ?? 0;
$last_update = $data['last_update'] ?? '';

if (empty($tracking_number) && $order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    // Verify user has access to this order (if logged in)
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE (tracking_number = ? OR id = ?) AND user_id = ?");
        $stmt->execute([$tracking_number, $order_id, $user_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
    }
    
    // Get current tracking info
    if ($order_id > 0) {
        $stmt = $pdo->prepare("SELECT tracking_number FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        $tracking_number = $order['tracking_number'] ?? $tracking_number;
    }
    
    // Check if we should fetch from carrier API
    $should_fetch = shouldFetchFromCarrier($tracking_number, $last_update);
    
    $new_events = 0;
    $updated = false;
    
    if ($should_fetch) {
        // Fetch updates from carrier API
        $carrier_updates = fetchCarrierUpdates($tracking_number);
        
        if ($carrier_updates && !empty($carrier_updates['events'])) {
            // Update database with new events
            foreach ($carrier_updates['events'] as $event) {
                // Check if event already exists
                $stmt = $pdo->prepare("
                    SELECT id FROM order_tracking 
                    WHERE order_id = ? AND status = ? AND tracking_date = ? 
                    LIMIT 1
                ");
                $stmt->execute([$order_id, $event['status'], $event['date']]);
                
                if (!$stmt->fetch()) {
                    // Insert new tracking event
                    $stmt = $pdo->prepare("
                        INSERT INTO order_tracking (order_id, status, location, description, tracking_date)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $order_id,
                        $event['status'],
                        $event['location'] ?? '',
                        $event['description'] ?? '',
                        $event['date']
                    ]);
                    $new_events++;
                }
            }
            
            if ($new_events > 0) {
                $updated = true;
                
                // Update shipping_tracking table
                $last_event = $carrier_updates['events'][0];
                $stmt = $pdo->prepare("
                    INSERT INTO shipping_tracking 
                    (tracking_number, carrier_name, status, estimated_delivery, last_checked)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    estimated_delivery = VALUES(estimated_delivery),
                    last_checked = VALUES(last_checked)
                ");
                $stmt->execute([
                    $tracking_number,
                    $carrier_updates['carrier'] ?? 'Unknown',
                    $last_event['status'],
                    $carrier_updates['estimated_delivery'] ?? NULL
                ]);
                
                // Update order status if needed
                $order_status = mapTrackingToOrderStatus($last_event['status']);
                if ($order_status) {
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET status = ?, updated_at = NOW() 
                        WHERE tracking_number = ?
                    ");
                    $stmt->execute([$order_status, $tracking_number]);
                }
            }
        }
    }
    
    // Get updated tracking history
    $stmt = $pdo->prepare("
        SELECT * FROM order_tracking 
        WHERE order_id = ? 
        ORDER BY tracking_date DESC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$order_id]);
    $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get latest shipping info
    $stmt = $pdo->prepare("
        SELECT estimated_delivery, last_checked 
        FROM shipping_tracking 
        WHERE tracking_number = ?
    ");
    $stmt->execute([$tracking_number]);
    $shipping_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'new_events' => $new_events,
        'tracking_history' => $tracking_history,
        'estimated_delivery' => $shipping_info['estimated_delivery'] ?? NULL,
        'last_update' => $shipping_info['last_checked'] ?? date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Tracking update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

function shouldFetchFromCarrier($tracking_number, $last_update) {
    // Don't fetch too frequently
    if (!empty($last_update)) {
        $last_update_time = strtotime($last_update);
        $current_time = time();
        $minutes_since_update = ($current_time - $last_update_time) / 60;
        
        // Fetch every 30 minutes max
        if ($minutes_since_update < 30) {
            return false;
        }
    }
    
    // Check carrier type
    $carrier = detectCarrier($tracking_number);
    
    // Only fetch for supported carriers
    $supported_carriers = ['DHL', 'FedEx', 'UPS', 'USPS'];
    return in_array($carrier, $supported_carriers);
}

function fetchCarrierUpdates($tracking_number) {
    $carrier = detectCarrier($tracking_number);
    
    // Mock implementation - in production, integrate with actual carrier APIs
    // For now, simulate random updates
    
    $events = [];
    $statuses = [
        ['status' => 'In Transit', 'location' => 'Regional Distribution Center'],
        ['status' => 'Arrived at Facility', 'location' => 'Local Sorting Center'],
        ['status' => 'Processed', 'location' => 'Destination Facility'],
        ['status' => 'Out for Delivery', 'location' => 'Local Delivery Station'],
        ['status' => 'Delivered', 'location' => 'Customer Address']
    ];
    
    // Random chance of having new updates (30%)
    if (rand(1, 100) <= 30) {
        $random_status = $statuses[array_rand($statuses)];
        $events[] = [
            'status' => $random_status['status'],
            'location' => $random_status['location'],
            'description' => getStatusDescription($random_status['status']),
            'date' => date('Y-m-d H:i:s')
        ];
    }
    
    return [
        'carrier' => $carrier,
        'events' => $events,
        'estimated_delivery' => date('Y-m-d H:i:s', strtotime('+2 days'))
    ];
}

function mapTrackingToOrderStatus($tracking_status) {
    $mapping = [
        'Delivered' => 'delivered',
        'Out for Delivery' => 'shipped',
        'In Transit' => 'shipped',
        'Shipped' => 'shipped',
        'Processing' => 'processing'
    ];
    
    return $mapping[$tracking_status] ?? null;
}
?>