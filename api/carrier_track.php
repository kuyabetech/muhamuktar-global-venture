<?php
// api/carrier-track.php - Carrier Tracking API Endpoint

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$tracking_number = $data['tracking_number'] ?? '';
$carrier = $data['carrier'] ?? 'generic';
$force_refresh = $data['force_refresh'] ?? false;

if (empty($tracking_number)) {
    echo json_encode(['success' => false, 'message' => 'Tracking number required']);
    exit;
}

try {
    // Check if we recently fetched this tracking number
    if (!$force_refresh) {
        $stmt = $pdo->prepare("
            SELECT tracking_data, last_checked 
            FROM shipping_tracking 
            WHERE tracking_number = ? 
            AND last_checked > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$tracking_number]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $tracking_data = json_decode($result['tracking_data'], true);
            echo json_encode([
                'success' => true,
                'updated' => false,
                'message' => 'Using cached data',
                'last_update' => $result['last_checked'],
                'tracking_history' => $tracking_data['tracking_history'] ?? []
            ]);
            exit;
        }
    }
    
    // Fetch from carrier API
    $carrier_info = detectCarrier($tracking_number);
    $tracking_data = fetchCarrierTrackingData($tracking_number, $carrier);
    
    if ($tracking_data['success']) {
        // Update database
        updateShippingTracking(0, $tracking_number, $tracking_data, $carrier_info);
        
        echo json_encode([
            'success' => true,
            'updated' => true,
            'carrier' => $carrier_info['name'],
            'status' => $tracking_data['status'],
            'status_description' => getStatusDescription($tracking_data['status']),
            'estimated_delivery' => $tracking_data['estimated_delivery'] ?? null,
            'tracking_history' => $tracking_data['tracking_history'] ?? [],
            'new_events' => $tracking_data['new_events'] ?? 0,
            'last_update' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $tracking_data['message'] ?? 'Failed to fetch tracking data'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Carrier track error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
?>