// api/carrier-status.php - Carrier API Status Check

require_once '../includes/config.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$carrier = $data['carrier'] ?? 'generic';

// Simulate API status check with response time
function simulateApiStatus($carrier) {
    $carriers = [
        'ups' => [
            'name' => 'UPS',
            'status' => 'operational',
            'response_time' => rand(80, 200), // ms
            'last_updated' => date('Y-m-d H:i:s', time() - rand(0, 300))
        ],
        'fedex' => [
            'name' => 'FedEx',
            'status' => 'operational',
            'response_time' => rand(90, 250),
            'last_updated' => date('Y-m-d H:i:s', time() - rand(0, 600))
        ],
        'usps' => [
            'name' => 'USPS',
            'status' => 'degraded',
            'response_time' => rand(300, 800),
            'last_updated' => date('Y-m-d H:i:s', time() - rand(0, 900))
        ],
        'dhl' => [
            'name' => 'DHL',
            'status' => 'operational',
            'response_time' => rand(100, 300),
            'last_updated' => date('Y-m-d H:i:s', time() - rand(0, 150))
        ]
    ];

    // If specific carrier requested
    if ($carrier !== 'generic' && isset($carriers[$carrier])) {
        return $carriers[$carrier];
    }

    // Return all carriers for generic request
    return [
        'overall_status' => 'partial_outage',
        'timestamp' => date('Y-m-d H:i:s'),
        'carriers' => $carriers
    ];
}

// Validate carrier name if provided
if ($carrier !== 'generic' && !in_array($carrier, ['ups', 'fedex', 'usps', 'dhl'])) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Invalid carrier specified',
        'valid_carriers' => ['ups', 'fedex', 'usps', 'dhl']
    ]);
    exit;
}

// Simulate occasional API errors (10% chance)
if (rand(1, 100) <= 10) {
    http_response_code(503);
    echo json_encode([
        'error' => true,
        'message' => 'Carrier API status service temporarily unavailable',
        'retry_after' => 30
    ]);
    exit;
}

// Get API status
$status = simulateApiStatus($carrier);

// Add additional metadata
$status['checked_at'] = date('Y-m-d H:i:s');
$status['request_id'] = uniqid('carrier_status_', true);

// Log the request (optional)
if (defined('LOG_API_REQUESTS') && LOG_API_REQUESTS) {
    error_log("Carrier status checked: " . json_encode([
        'carrier' => $carrier,
        'timestamp' => $status['checked_at'],
        'request_id' => $status['request_id']
    ]));
}

// Return the status
http_response_code(200);
echo json_encode($status);