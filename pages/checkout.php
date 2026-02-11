<?php
// pages/checkout.php
$page_title = "Checkout";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require login
require_login();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();
$cart_items = [];
$subtotal = 0;
$shipping_fee = 0;
$total = 0;
$payment_error = null;

// Fetch cart items from database (carts table)
try {
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                c.id as cart_id,
                c.quantity,
                c.price as item_price,
                p.id as product_id,
                p.name,
                p.slug,
                pi.filename as image,
                p.stock,
                p.status,
                p.price as original_price
            FROM carts c
            LEFT JOIN products p ON c.product_id = p.id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
            WHERE c.user_id = ? 
              AND (c.deleted_at IS NULL OR c.deleted_at = '')
              AND (p.status = 'active' OR p.status IS NULL)
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                c.id as cart_id,
                c.quantity,
                c.price as item_price,
                p.id as product_id,
                p.name,
                p.slug,
                pi.filename as image,
                p.stock,
                p.status,
                p.price as original_price
            FROM carts c
            LEFT JOIN products p ON c.product_id = p.id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
            WHERE c.session_id = ? 
              AND (c.deleted_at IS NULL OR c.deleted_at = '')
              AND (p.status = 'active' OR p.status IS NULL)
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$session_id]);
    }

    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($cart_items as $item) {
        if ($item['name']) { // Only if product exists
            $line_total = $item['quantity'] * $item['item_price'];
            $subtotal += $line_total;
        }
    }
    
    // Calculate shipping (free over ₦50,000)
    $shipping_fee = $subtotal >= 50000 ? 0 : 1500;
    $total = $subtotal + $shipping_fee;
    $total_kobo = $total * 100; // Convert to kobo for Paystack
    
} catch (Exception $e) {
    error_log("Checkout error: " . $e->getMessage());
    $payment_error = "Unable to load cart items: " . $e->getMessage();
}

// Check if cart is empty
if (empty($cart_items)) {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => "Your cart is empty. Add items first."
    ];
    header("Location: " . BASE_URL . "pages/cart.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $order_notes = trim($_POST['order_notes'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($shipping_address)) $errors[] = "Shipping address is required";
    if (empty($city)) $errors[] = "City is required";
    if (empty($state)) $errors[] = "State is required";
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Generate order reference
            $reference = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            
            // 1. Save order to orders table
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id,
                    order_number,
                    customer_name,
                    customer_email,
                    customer_phone,
                    shipping_address,
                    shipping_city,
                    shipping_state,
                    shipping_postal_code,
                    order_notes,
                    subtotal,
                    shipping_fee,
                    total_amount,
                    payment_status,
                    order_status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'processing', NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $reference,
                $full_name,
                $email,
                $phone,
                $shipping_address,
                $city,
                $state,
                $postal_code,
                $order_notes,
                $subtotal,
                $shipping_fee,
                $total
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // 2. Save order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    quantity,
                    unit_price,
                    total_price,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            foreach ($cart_items as $item) {
                if ($item['name']) {
                    $item_total = $item['quantity'] * $item['item_price'];
                    $stmt->execute([
                        $order_id,
                        $item['product_id'],
                        $item['name'],
                        $item['quantity'],
                        $item['item_price'],
                        $item_total
                    ]);
                    
                    // Update product stock (optional)
                    $update_stmt = $pdo->prepare("
                        UPDATE products 
                        SET stock = stock - ?, 
                            updated_at = NOW() 
                        WHERE id = ? AND stock >= ?
                    ");
                    $update_stmt->execute([
                        $item['quantity'],
                        $item['product_id'],
                        $item['quantity']
                    ]);
                }
            }
            
            // 3. Clear cart items (soft delete)
            if ($user_id) {
                $clear_stmt = $pdo->prepare("
                    UPDATE carts 
                    SET deleted_at = NOW() 
                    WHERE user_id = ? AND deleted_at IS NULL
                ");
                $clear_stmt->execute([$user_id]);
            } else {
                $clear_stmt = $pdo->prepare("
                    UPDATE carts 
                    SET deleted_at = NOW() 
                    WHERE session_id = ? AND deleted_at IS NULL
                ");
                $clear_stmt->execute([$session_id]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Save order data to session for Paystack
            $_SESSION['pending_order'] = [
                'order_id' => $order_id,
                'reference' => $reference,
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'shipping_address' => $shipping_address,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'order_notes' => $order_notes,
                'subtotal' => $subtotal,
                'shipping_fee' => $shipping_fee,
                'total' => $total,
                'total_kobo' => $total_kobo,
                'user_id' => $user_id,
                'session_id' => $session_id,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Initialize Paystack payment
            $payload = [
                'email' => $email,
                'amount' => $total_kobo, // in kobo
                'reference' => $reference,
                'callback_url' => BASE_URL . 'pages/verify-payment.php',
                'metadata' => [
                    'order_id' => $order_id,
                    'full_name' => $full_name,
                    'phone' => $phone,
                    'shipping_address' => $shipping_address,
                    'city' => $city,
                    'state' => $state,
                    'user_id' => $user_id,
                    'order_notes' => $order_notes
                ]
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
                    "Content-Type: application/json",
                    "Cache-Control: no-cache",
                ],
            ]);
            
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            
            if ($err) {
                $payment_error = "Payment initialization failed: " . $err;
                // Log error but don't rollback - order is saved
                error_log("Paystack error: " . $err);
            } else {
                $resp = json_decode($response, true);
                if ($resp['status'] === true && !empty($resp['data']['authorization_url'])) {
                    // Redirect to Paystack
                    header("Location: " . $resp['data']['authorization_url']);
                    exit;
                } else {
                    $payment_error = $resp['message'] ?? "Failed to initialize payment.";
                    error_log("Paystack response error: " . print_r($resp, true));
                }
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $payment_error = "Order processing failed: " . $e->getMessage();
            error_log("Order processing error: " . $e->getMessage());
        }
    } else {
        $payment_error = implode("<br>", $errors);
    }
}

// Get user info if available
$user_info = [];
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone, address, city, state, postal_code FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("User info error: " . $e->getMessage());
    }
}
require_once '../includes/header.php';

?>

<style>
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --secondary: #6b7280;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --text: #1f2937;
    --text-light: #6b7280;
    --border: #e5e7eb;
    --bg-light: #f9fafb;
}

.checkout-container {
    padding: 2rem 0 4rem;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100px);
    min-height: 70vh;
}

.checkout-header {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: var(--text);
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.checkout-subtitle {
    color: var(--text-light);
    margin-bottom: 2.5rem;
    font-size: 1.1rem;
}

.checkout-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 3rem;
    align-items: start;
}

.checkout-form-section {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    border: 1px solid var(--border);
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text);
}

.form-label .required {
    color: var(--danger);
}

.form-control {
    width: 100%;
    padding: 0.9rem 1rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 1rem;
    color: var(--text);
    transition: all 0.2s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.form-control.error {
    border-color: var(--danger);
}

.error-message {
    color: var(--danger);
    font-size: 0.9rem;
    margin-top: 0.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.order-summary-section {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    border: 1px solid var(--border);
    position: sticky;
    top: 120px;
}

.order-items {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 1.5rem;
    padding-right: 0.5rem;
}

.order-item {
    display: flex;
    align-items: center;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 12px;
    margin-bottom: 0.75rem;
    transition: transform 0.2s;
}

.order-item:hover {
    transform: translateX(4px);
}

.order-item-image {
    width: 70px;
    height: 70px;
    border-radius: 10px;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.order-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.order-item-image:hover img {
    transform: scale(1.05);
}

.order-item-details {
    flex: 1;
}

.order-item-name {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.25rem;
    font-size: 0.95rem;
    line-height: 1.3;
}

.order-item-qty {
    font-size: 0.85rem;
    color: var(--text-light);
    margin-bottom: 0.25rem;
}

.order-item-price {
    font-size: 0.85rem;
    color: var(--text);
    font-weight: 500;
}

.order-item-total {
    font-weight: 700;
    color: var(--danger);
    font-size: 1rem;
    margin-left: 1rem;
}

.summary-totals {
    margin: 1.5rem 0;
    padding: 1.5rem 0;
    border-top: 2px solid var(--border);
    border-bottom: 2px solid var(--border);
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    font-size: 1.05rem;
}

.summary-line.total {
    font-size: 1.4rem;
    font-weight: 800;
    margin-top: 1rem;
    color: var(--text);
}

.total-amount {
    font-size: 2rem;
    font-weight: 900;
    color: var(--danger);
    background: linear-gradient(135deg, var(--danger), #ec4899);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.checkout-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    width: 100%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    text-align: center;
    padding: 1.25rem;
    border-radius: 12px;
    font-size: 1.2rem;
    font-weight: 700;
    text-decoration: none;
    margin: 1.75rem 0 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    box-shadow: 0 8px 25px rgba(59,130,246,0.25);
}

.checkout-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(59,130,246,0.35);
}

.checkout-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.payment-methods {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin: 1rem 0;
    padding: 1rem 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.payment-icon {
    font-size: 1.8rem;
    color: var(--text-light);
    opacity: 0.7;
    transition: all 0.2s;
}

.payment-icon:hover {
    opacity: 1;
}

.security-notice {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: 10px;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--text-light);
}

.security-icon {
    color: var(--success);
    font-size: 1.2rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.alert-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.back-to-cart {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    padding: 0.75rem 1.5rem;
    border: 2px solid var(--primary);
    border-radius: 8px;
    transition: all 0.2s;
    margin-bottom: 1.5rem;
}

.back-to-cart:hover {
    background: var(--primary);
    color: white;
}

.shipping-progress {
    margin: 1rem 0;
    background: var(--bg-light);
    border-radius: 10px;
    overflow: hidden;
    height: 8px;
}

.shipping-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    border-radius: 10px;
    transition: width 0.3s ease;
}

.shipping-message {
    text-align: center;
    font-size: 0.9rem;
    color: var(--text-light);
    margin-top: 0.5rem;
}

@media (max-width: 1200px) {
    .checkout-grid {
        gap: 2rem;
    }
}

@media (max-width: 992px) {
    .checkout-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .order-summary-section {
        position: static;
        order: -1;
    }
    
    .checkout-header {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .checkout-container {
        padding: 1.5rem 0 3rem;
    }
    
    .checkout-form-section,
    .order-summary-section {
        padding: 1.5rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .order-items {
        max-height: 300px;
    }
    
    .order-item-image {
        width: 60px;
        height: 60px;
    }
}

@media (max-width: 480px) {
    .checkout-header {
        font-size: 1.75rem;
    }
    
    .section-title {
        font-size: 1.3rem;
    }
}
</style>

<main class="checkout-container container" style="margin:10px;">
    <a href="<?= BASE_URL ?>pages/cart.php" class="back-to-cart">
        <i class="fas fa-arrow-left"></i> Back to Cart
    </a>
    
    <h1 class="checkout-header">Complete Your Order</h1>
    <p class="checkout-subtitle">Fill in your details and complete payment securely</p>

    <?php if ($payment_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle alert-icon"></i>
            <div>
                <strong>Error:</strong>
                <div style="margin-top: 0.25rem;"><?= $payment_error ?></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="checkout-grid">
        <!-- Checkout Form -->
        <section class="checkout-form-section">
            <h2 class="section-title">Shipping Information</h2>
            
            <form method="post" id="checkoutForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Full Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="full_name" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user_info['full_name'] ?? '') ?>" 
                               required
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user_info['email'] ?? $_SESSION['user_email'] ?? '') ?>" 
                               required
                               placeholder="your@email.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               name="phone" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user_info['phone'] ?? '') ?>" 
                               required
                               placeholder="08012345678">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            State <span class="required">*</span>
                        </label>
                        <select name="state" class="form-control" required>
                            <option value="">Select State</option>
                            <option value="Lagos" <?= ($user_info['state'] ?? '') == 'Lagos' ? 'selected' : '' ?>>Lagos</option>
                            <option value="Abuja" <?= ($user_info['state'] ?? '') == 'Abuja' ? 'selected' : '' ?>>Abuja (FCT)</option>
                            <option value="Rivers" <?= ($user_info['state'] ?? '') == 'Rivers' ? 'selected' : '' ?>>Rivers</option>
                            <option value="Oyo" <?= ($user_info['state'] ?? '') == 'Oyo' ? 'selected' : '' ?>>Oyo</option>
                            <option value="Kano" <?= ($user_info['state'] ?? '') == 'Kano' ? 'selected' : '' ?>>Kano</option>
                            <option value="Kaduna" <?= ($user_info['state'] ?? '') == 'Kaduna' ? 'selected' : '' ?>>Kaduna</option>
                            <option value="Delta" <?= ($user_info['state'] ?? '') == 'Delta' ? 'selected' : '' ?>>Delta</option>
                            <option value="Ogun" <?= ($user_info['state'] ?? '') == 'Ogun' ? 'selected' : '' ?>>Ogun</option>
                            <option value="Ondo" <?= ($user_info['state'] ?? '') == 'Ondo' ? 'selected' : '' ?>>Ondo</option>
                            <option value="Enugu" <?= ($user_info['state'] ?? '') == 'Enugu' ? 'selected' : '' ?>>Enugu</option>
                            <option value="Anambra" <?= ($user_info['state'] ?? '') == 'Anambra' ? 'selected' : '' ?>>Anambra</option>
                            <option value="Akwa Ibom" <?= ($user_info['state'] ?? '') == 'Akwa Ibom' ? 'selected' : '' ?>>Akwa Ibom</option>
                            <option value="Cross River" <?= ($user_info['state'] ?? '') == 'Cross River' ? 'selected' : '' ?>>Cross River</option>
                            <option value="Edo" <?= ($user_info['state'] ?? '') == 'Edo' ? 'selected' : '' ?>>Edo</option>
                            <option value="Imo" <?= ($user_info['state'] ?? '') == 'Imo' ? 'selected' : '' ?>>Imo</option>
                            <!-- Add more states as needed -->
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Shipping Address <span class="required">*</span>
                    </label>
                    <textarea name="shipping_address" 
                              class="form-control" 
                              rows="3" 
                              required
                              placeholder="Street address, apartment, floor, etc."><?= htmlspecialchars($user_info['address'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            City <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="city" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user_info['city'] ?? '') ?>" 
                               required
                               placeholder="City">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Postal Code
                        </label>
                        <input type="text" 
                               name="postal_code" 
                               class="form-control" 
                               value="<?= htmlspecialchars($user_info['postal_code'] ?? '') ?>" 
                               placeholder="Postal code">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Order Notes (Optional)
                    </label>
                    <textarea name="order_notes" 
                              class="form-control" 
                              rows="3"
                              placeholder="Special instructions, delivery preferences, etc."></textarea>
                </div>
                
                <button type="submit" class="checkout-button" id="payButton">
                    <i class="fas fa-lock"></i> Pay ₦<?= number_format($total) ?> with Paystack
                </button>
            </form>
        </section>

        <!-- Order Summary -->
        <aside class="order-summary-section">
            <h2 class="section-title">Order Summary</h2>
            
            <div class="order-items">
                <?php foreach ($cart_items as $item): 
                    if (!$item['name']) continue;
                    
                    $item_total = $item['quantity'] * $item['item_price'];
                    $image_path = !empty($item['image']) ? '../uploads/products/' . htmlspecialchars($item['image']) : '';
                    $has_discount = $item['item_price'] < $item['original_price'];
                ?>
                    <div class="order-item">
                        <div class="order-item-image">
                            <?php if (!empty($item['image']) && file_exists(str_replace('../', '', $image_path))): ?>
                                <img src="<?= $image_path ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-box text-muted fa-2x\'></i>';">
                            <?php else: ?>
                                <i class="fas fa-box text-muted fa-2x"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-item-details">
                            <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="order-item-qty">
                                <span>Qty: <?= $item['quantity'] ?></span>
                                <?php if ($has_discount): ?>
                                    <span style="color:var(--success); margin-left: 0.5rem;">
                                        <i class="fas fa-tag"></i> Discounted
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="order-item-price">
                                ₦<?= number_format($item['item_price']) ?> each
                            </div>
                        </div>
                        
                        <div class="order-item-total">₦<?= number_format($item_total) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="summary-totals">
                <div class="summary-line">
                    <span>Subtotal</span>
                    <span>₦<?= number_format($subtotal) ?></span>
                </div>
                
                <div class="summary-line">
                    <span>Shipping</span>
                    <span>
                        <?php if ($shipping_fee == 0): ?>
                            <span style="color:var(--success); font-weight:600;">FREE</span>
                        <?php else: ?>
                            ₦<?= number_format($shipping_fee) ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if ($shipping_fee > 0 && $subtotal < 50000): ?>
                    <div style="margin: 1rem 0;">
                        <div class="shipping-progress">
                            <div class="shipping-progress-fill" style="width: <?= min(100, ($subtotal / 50000) * 100) ?>%"></div>
                        </div>
                        <div class="shipping-message">
                            Add <strong>₦<?= number_format(50000 - $subtotal) ?></strong> more for FREE shipping!
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="summary-line total">
                    <span>Total</span>
                    <span class="total-amount">₦<?= number_format($total) ?></span>
                </div>
            </div>
            
            <div class="payment-methods">
                <i class="fab fa-cc-visa payment-icon" title="Visa"></i>
                <i class="fab fa-cc-mastercard payment-icon" title="Mastercard"></i>
                <i class="fab fa-cc-amex payment-icon" title="American Express"></i>
                <i class="fab fa-cc-paypal payment-icon" title="PayPal"></i>
                <i class="fas fa-university payment-icon" title="Bank Transfer"></i>
            </div>
            
            <div class="security-notice">
                <i class="fas fa-shield-alt security-icon"></i>
                <span>Secure checkout • SSL encrypted • 30-day return policy</span>
            </div>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('checkoutForm');
    const payButton = document.getElementById('payButton');
    let isProcessing = false;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (isProcessing) return;
        isProcessing = true;
        
        // Disable button and show loading
        payButton.disabled = true;
        payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
        
        // Validate form
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            field.classList.remove('error');
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            }
        });
        
        // Email validation
        const emailField = form.querySelector('[name="email"]');
        if (emailField.value && !isValidEmail(emailField.value)) {
            emailField.classList.add('error');
            isValid = false;
        }
        
        if (!isValid) {
            showError('Please fill in all required fields correctly.');
            resetButton();
            return;
        }
        
        // Submit form
        form.submit();
    });
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function showError(message) {
        // Remove existing error alerts
        const existingAlert = document.querySelector('.alert-error');
        if (existingAlert) existingAlert.remove();
        
        // Create new error alert
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.innerHTML = `
            <i class="fas fa-exclamation-triangle alert-icon"></i>
            <div>
                <strong>Error:</strong>
                <div style="margin-top: 0.25rem;">${message}</div>
            </div>
        `;
        
        form.parentNode.insertBefore(alertDiv, form);
        
        // Scroll to error
        alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    function resetButton() {
        isProcessing = false;
        payButton.disabled = false;
        payButton.innerHTML = '<i class="fas fa-lock"></i> Pay ₦<?= number_format($total) ?> with Paystack';
    }
    
    // Real-time validation
    form.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('error');
            } else {
                this.classList.remove('error');
            }
            
            // Special email validation
            if (this.type === 'email' && this.value) {
                if (!isValidEmail(this.value)) {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            }
        });
        
        field.addEventListener('input', function() {
            this.classList.remove('error');
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>