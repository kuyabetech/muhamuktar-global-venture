<?php
// api/paystack-webhook.php
require_once '../includes/config.php';

$body = @file_get_contents("php://input");
$event = json_decode($body);

if (!$event || !isset($event->event)) {
    http_response_code(400);
    exit;
}

// Verify signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
if ($signature !== hash_hmac('sha512', $body, PAYSTACK_SECRET_KEY)) {
    http_response_code(401);
    exit("Invalid signature");
}

// Log for debug
file_put_contents('paystack.log', date('Y-m-d H:i:s') . " - " . json_encode($event) . "\n", FILE_APPEND);

if ($event->event === 'charge.success') {
    $reference = $event->data->reference;
    // TODO: Update order status to 'paid' in DB using $reference
}

http_response_code(200);
echo "Webhook received";