<?php
// verify-payment.php (in root or pages/)
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    die("Invalid reference.");
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . urlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ],
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['status'] === true && $result['data']['status'] === 'success') {
    // Payment verified!
    $amount_paid = $result['data']['amount'] / 100;

    // Retrieve pending order from session (or better: from DB)
    if (isset($_SESSION['pending_order']) && $_SESSION['pending_order']['reference'] === $reference) {
        $order = $_SESSION['pending_order'];

        // TODO: Save real order to database here
        // Insert into orders table, order_items, etc.
        // Clear cart: unset($_SESSION['cart']);
        // unset($_SESSION['pending_order']);

        echo "<h1>Payment Successful! ₦" . number_format($amount_paid) . "</h1>";
        echo "<p>Order reference: " . htmlspecialchars($reference) . "</p>";
        echo "<p>Thank you! We'll process your order soon.</p>";
        echo '<a href="' . BASE_URL . 'pages/orders.php">View Orders</a>';
    } else {
        echo "<h1>Order not found</h1>";
    }
} else {
    echo "<h1>Payment Failed</h1>";
    echo "<p>" . ($result['message'] ?? 'Unknown error') . "</p>";
    echo '<a href="' . BASE_URL . 'pages/cart.php">Back to Cart</a>';
}
?>