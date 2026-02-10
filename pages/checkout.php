<?php
// pages/checkout.php

$page_title = "Checkout";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Require login
require_login();

// Fetch cart items (same logic as cart.php)
$cart_items = [];
$total_kobo = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, slug, price, discount_price
        FROM products
        WHERE id IN ($placeholders) AND status = 'active'
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($_SESSION['cart'] as $id => $qty) {
        if (isset($products[$id])) {
            $p = $products[$id];
            $price = ($p['discount_price'] ?? $p['price']) * 100; // to kobo
            $sub = $price * $qty;
            $total_kobo += $sub;

            $cart_items[] = [
                'id'    => $id,
                'name'  => $p['name'],
                'qty'   => $qty,
                'price' => $price / 100, // back to naira for display
                'sub'   => $sub / 100,
            ];
        }
    }
}

if (empty($cart_items)) {
    $_SESSION['flash'] = "Your cart is empty. Add items first.";
    header("Location: " . BASE_URL . "pages/cart.php");
    exit;
}

// Handle form submission → initialize Paystack transaction
$payment_error = null;
$order_reference = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $order_notes      = trim($_POST['order_notes'] ?? '');

    if (empty($shipping_address)) {
        $payment_error = "Please enter your shipping address.";
    } else {
        // Generate unique reference
        $reference = 'MGV-' . time() . '-' . bin2hex(random_bytes(4));

        // Save minimal order info to session (or DB) for verification later
        $_SESSION['pending_order'] = [
            'reference'       => $reference,
            'total_kobo'      => $total_kobo,
            'items'           => $cart_items,
            'shipping_address'=> $shipping_address,
            'notes'           => $order_notes,
            'user_id'         => $_SESSION['user_id'] ?? null,
        ];

        // Initialize Paystack transaction (server-side)
        $payload = [
            'email'       => $_SESSION['user_email'] ?? 'guest@example.com',
            'amount'      => $total_kobo, // in kobo
            'reference'   => $reference,
            'callback_url'=> BASE_URL . 'verify-payment.php',
            'metadata'    => [
                'user_id'         => $_SESSION['user_id'] ?? 0,
                'order_notes'     => $order_notes,
                'shipping_address'=> $shipping_address,
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://api.paystack.co/transaction/initialize",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
                "Content-Type: application/json",
                "Cache-Control: no-cache",
            ],
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $payment_error = "cURL error: " . $err;
        } else {
            $resp = json_decode($response, true);
            if ($resp['status'] === true && !empty($resp['data']['authorization_url'])) {
                // Redirect to Paystack hosted checkout
                header("Location: " . $resp['data']['authorization_url']);
                exit;
            } else {
                $payment_error = $resp['message'] ?? "Failed to initialize payment.";
            }
        }
    }
}
?>

<main class="container" style="padding: 2rem 0;">

  <h1 style="font-size: 2.2rem; margin-bottom: 2rem;">Checkout</h1>

  <?php if ($payment_error): ?>
    <div style="background:#fee2e2; color:#dc2626; padding:1rem; border-radius:10px; margin-bottom:1.5rem;">
      <?= htmlspecialchars($payment_error) ?>
    </div>
  <?php endif; ?>

  <div style="display:grid; grid-template-columns:2fr 1fr; gap:2.5rem;">

    <!-- Order Summary -->
    <section>
      <h2 style="font-size:1.6rem; margin-bottom:1.2rem;">Order Summary</h2>

      <?php foreach ($cart_items as $item): ?>
        <div style="
          display:flex;
          align-items:center;
          background:white;
          border-radius:10px;
          padding:1rem;
          margin-bottom:1rem;
          box-shadow:0 1px 6px rgba(0,0,0,0.06);
        ">
          <div style="flex:1;">
            <strong><?= htmlspecialchars($item['name']) ?></strong><br>
            ₦<?= number_format($item['price']) ?> × <?= $item['qty'] ?>
          </div>
          <div style="font-weight:700; color:#ef4444;">
            ₦<?= number_format($item['sub']) ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="margin-top:1.5rem; padding:1.5rem; background:#f8f9fc; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.8rem;">
          <span>Subtotal</span>
          <strong>₦<?= number_format($total_kobo / 100) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.8rem;">
          <span>Shipping</span>
          <span style="color:var(--success);">Free</span>
        </div>
        <div style="border-top:1px solid #e5e7eb; padding-top:1rem; margin-top:1rem; display:flex; justify-content:space-between; font-size:1.3rem; font-weight:700;">
          <span>Total</span>
          <span style="color:#ef4444;">₦<?= number_format($total_kobo / 100) ?></span>
        </div>
      </div>
    </section>

    <!-- Checkout Form -->
    <aside style="background:white; padding:1.8rem; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,0.08); height:fit-content;">
      <h2 style="font-size:1.6rem; margin-bottom:1.5rem;">Shipping Details</h2>

      <form method="post">
        <div style="margin-bottom:1.5rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Full Shipping Address *</label>
          <textarea name="shipping_address" rows="4" required style="
            width:100%;
            padding:0.9rem;
            border:1px solid var(--border);
            border-radius:8px;
            font-size:1rem;
            resize:vertical;
          " placeholder="Street address, city, state, postal code..."></textarea>
        </div>

        <div style="margin-bottom:1.8rem;">
          <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Order Notes (optional)</label>
          <textarea name="order_notes" rows="3" style="
            width:100%;
            padding:0.9rem;
            border:1px solid var(--border);
            border-radius:8px;
            font-size:1rem;
          " placeholder="Delivery instructions, landmark, etc..."></textarea>
        </div>

        <button type="submit" style="
          width:100%;
          padding:1.2rem;
          background:var(--primary);
          color:white;
          border:none;
          border-radius:10px;
          font-size:1.15rem;
          font-weight:600;
          cursor:pointer;
          transition:0.25s;
        " onmouseover="this.style.background=var(--primary-dark)">
          Pay ₦<?= number_format($total_kobo / 100) ?> with Paystack
        </button>
      </form>

      <p style="text-align:center; margin-top:1.2rem; font-size:0.9rem; color:#6b7280;">
        Secure payment powered by Paystack
      </p>
    </aside>

  </div>

</main>

<?php require_once '../includes/footer.php'; ?>