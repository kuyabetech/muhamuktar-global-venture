<?php
// pages/invoice.php - Generate and Download Invoice PDF

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Must be logged in
require_login();

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header("Location: " . BASE_URL . "pages/orders.php");
    exit;
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name, u.email, u.phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: " . BASE_URL . "pages/orders.php");
    exit;
}

// Fetch order items
$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.sku, p.description, oi.price_at_time AS price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Fetch site settings
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$site_name    = $settings['site_name'] ?? 'Muhammadu Global Venture';
$site_email   = $settings['contact_email'] ?? 'info@muhammaduglobal.com';
$site_phone   = $settings['contact_phone'] ?? '+234 123 456 7890';
$site_address = $settings['contact_address'] ?? '123 Business Street, Lagos, Nigeria';
$site_logo    = $settings['store_logo'] ?? BASE_URL . 'assets/images/logo.png';

// Generate invoice number
$invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

// Include TCPDF library
require_once '../vendor/autoload.php'; // Composer autoload

// Create new PDF document
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator($site_name);
$pdf->SetAuthor($site_name);
$pdf->SetTitle('Invoice ' . $invoice_number);
$pdf->SetSubject('Invoice');
$pdf->SetKeywords('Invoice, Order, Receipt');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

// Colors
$header_color = array(79, 70, 229); // #4f46e5
$light_color = array(240, 249, 255); // #f0f9ff
$dark_color = array(17, 24, 39); // #111827
$gray_color = array(107, 114, 128); // #6b7280

// Function to draw colored box
function drawColoredBox($pdf, $x, $y, $width, $height, $color, $text, $font_size = 10, $text_color = array(255, 255, 255)) {
    $pdf->SetFillColor($color[0], $color[1], $color[2]);
    $pdf->SetDrawColor($color[0], $color[1], $color[2]);
    $pdf->Rect($x, $y, $width, $height, 'F');
    
    $pdf->SetTextColor($text_color[0], $text_color[1], $text_color[2]);
    $pdf->SetFont('helvetica', 'B', $font_size);
    $pdf->SetXY($x, $y);
    $pdf->Cell($width, $height, $text, 0, 0, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
}

// Company Header
$pdf->SetFont('helvetica', 'B', 24);
$pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
$pdf->Cell(0, 10, $site_name, 0, 1, 'L');
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor($gray_color[0], $gray_color[1], $gray_color[2]);
$pdf->Cell(0, 5, $site_address, 0, 1, 'L');
$pdf->Cell(0, 5, 'Email: ' . $site_email, 0, 1, 'L');
$pdf->Cell(0, 5, 'Phone: ' . $site_phone, 0, 1, 'L');

// Invoice Title
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 32);
$pdf->SetTextColor($dark_color[0], $dark_color[1], $dark_color[2]);
$pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');
$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor($gray_color[0], $gray_color[1], $gray_color[2]);
$pdf->Cell(0, 6, $invoice_number, 0, 1, 'R');
$pdf->Cell(0, 6, 'Date: ' . date('F d, Y'), 0, 1, 'R');
$pdf->Cell(0, 6, 'Due Date: ' . date('F d, Y', strtotime('+30 days')), 0, 1, 'R');

// Line separator
$pdf->Ln(5);
$pdf->SetDrawColor($header_color[0], $header_color[1], $header_color[2]);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(10);

// Customer Information
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
$pdf->Cell(90, 8, 'Bill To:', 0, 0, 'L');
$pdf->Cell(90, 8, 'Ship To:', 0, 1, 'L');

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Bill To
$pdf->MultiCell(90, 6, 
    $order['full_name'] . "\n" . 
    ($order['email'] ?? '') . "\n" . 
    ($order['phone'] ?? ''), 
    0, 'L', false, 0
);

// Ship To
$shipping_address = '';
if (!empty($order['address_line1'] ?? '')) $shipping_address .= $order['address_line1'] . "\n";
if (!empty($order['address_line2'] ?? '')) $shipping_address .= $order['address_line2'] . "\n";
if (!empty($order['city'] ?? '') || !empty($order['state'] ?? '')) {
    $shipping_address .= ($order['city'] ?? '') . ', ' . ($order['state'] ?? '') . ' ' . ($order['postal_code'] ?? '') . "\n";
}
if (!empty($order['country'] ?? '')) $shipping_address .= $order['country'];

$pdf->MultiCell(90, 6, $shipping_address, 0, 'L', false, 1);

$pdf->Ln(10);

// Order Information Table
$pdf->SetFillColor($light_color[0], $light_color[1], $light_color[2]);
$pdf->SetDrawColor(200, 200, 200);

// Table header
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor($dark_color[0], $dark_color[1], $dark_color[2]);
$pdf->Cell(45, 8, 'Order Number', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Order Date', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Payment Method', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'Payment Status', 1, 1, 'C', true);

// Table data
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(45, 8, $order['order_number'] ?? $order['id'], 1, 0, 'C');
$pdf->Cell(45, 8, date('F d, Y', strtotime($order['created_at'])), 1, 0, 'C');
$pdf->Cell(45, 8, $order['payment_method'] ?? 'Not specified', 1, 0, 'C');

// Payment status color
$payment_status = ucfirst($order['payment_status'] ?? 'pending');
if ($payment_status === 'Paid') {
    $pdf->SetTextColor(16, 185, 129);
} else {
    $pdf->SetTextColor(245, 158, 11);
}
$pdf->Cell(45, 8, $payment_status, 1, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(15);

// Order Items Table
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor($header_color[0], $header_color[1], $header_color[2]);
$pdf->Cell(0, 8, 'Order Items', 0, 1, 'L');

// Table header
$pdf->SetFillColor($header_color[0], $header_color[1], $header_color[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(10, 8, '#', 1, 0, 'C', true);
$pdf->Cell(80, 8, 'Description', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Unit Price', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'Amount', 1, 1, 'C', true);

// Table data
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);
$subtotal = 0;

foreach ($items as $index => $item) {
    $item_price = $item['price'] ?? 0;
    $quantity = $item['quantity'] ?? 1;
    $amount = $item_price * $quantity;
    $subtotal += $amount;

    $pdf->SetFillColor($index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255, $index % 2 == 0 ? 250 : 255);
    $pdf->Cell(10, 8, $index + 1, 1, 0, 'C', true);

    $desc = $item['name'] ?? 'Product';
    if (!empty($item['sku'])) $desc .= "\nSKU: " . $item['sku'];
    $pdf->MultiCell(80, 8, $desc, 1, 'L', true, 0);

    $pdf->Cell(25, 8, '₦' . number_format($item_price, 2), 1, 0, 'R', true);
    $pdf->Cell(20, 8, $quantity, 1, 0, 'C', true);
    $pdf->Cell(25, 8, '₦' . number_format($amount, 2), 1, 1, 'R', true);
}

$pdf->Ln(10);

// Summary Section - Shifted left
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor($dark_color[0], $dark_color[1], $dark_color[2]);

$summary_width = 60;
$summary_x = 15; // left side

$pdf->SetXY($summary_x, $pdf->GetY());
$pdf->Cell($summary_width, 8, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(40, 8, '₦' . number_format($order['subtotal'] ?? $subtotal, 2), 0, 1, 'R');

$pdf->SetX($summary_x);
$pdf->Cell($summary_width, 8, 'Shipping:', 0, 0, 'R');
$pdf->Cell(40, 8, '₦' . number_format($order['shipping_fee'] ?? 0, 2), 0, 1, 'R');

$pdf->SetX($summary_x);
$pdf->Cell($summary_width, 8, 'Tax:', 0, 0, 'R');
$pdf->Cell(40, 8, '₦' . number_format($order['tax'] ?? 0, 2), 0, 1, 'R');

$pdf->SetX($summary_x);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell($summary_width, 10, 'TOTAL:', 0, 0, 'R');
$pdf->Cell(40, 10, '₦' . number_format($order['total_amount'] ?? 0, 2), 0, 1, 'R');

// Watermark for unpaid
if (($order['payment_status'] ?? 'pending') !== 'paid') {
    $pdf->SetFont('helvetica', 'B', 80);
    $pdf->SetTextColor(239, 68, 68);
    $pdf->StartTransform();
    $pdf->Rotate(45, 105, 150);
    $pdf->Text(105, 150, 'UNPAID');
    $pdf->StopTransform();
}

// Output PDF
$pdf->Output('invoice-' . $invoice_number . '.pdf', 'D');
exit;