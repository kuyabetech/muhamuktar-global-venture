<?php
// pages/cart.php

$page_title = "Shopping Cart";
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// For MVP: use session-based cart
// Format: $_SESSION['cart'] = [ product_id => quantity, ... ]
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, price, discount_price, stock
            FROM products
            WHERE id IN ($placeholders) AND status = 'active'
        ");
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($_SESSION['cart'] as $id => $qty) {
            if (isset($products[$id])) {
                $p = $products[$id];
                $price = $p['discount_price'] ?? $p['price'];
                $sub = $price * $qty;
                $total += $sub;

                $cart_items[] = [
                    'id'      => $id,
                    'name'    => $p['name'],
                    'slug'    => $p['slug'],
                    'price'   => $price,
                    'qty'     => $qty,
                    'sub'     => $sub,
                    'stock'   => $p['stock']
                ];
            }
        }
    } catch (Exception $e) {
        $cart_items = [];
    }
}

// Handle quantity update / remove (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $qty    = (int)($_POST['qty'] ?? 1);

    if ($id > 0 && isset($_SESSION['cart'][$id])) {
        if ($action === 'update') {
            if ($qty > 0) {
                $_SESSION['cart'][$id] = min($qty, 20); // max qty limit example
            } else {
                unset($_SESSION['cart'][$id]);
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$id]);
        }
    }

    // Refresh page to show updated cart
    header("Location: " . BASE_URL . "pages/cart.php");
    exit;
}
?>

<main class="container" style="padding: 2rem 0; min-height: 60vh;">

  <h1 style="font-size: 2.2rem; margin-bottom: 2rem;">Your Shopping Cart</h1>

  <?php if (empty($cart_items)): ?>

    <div style="
      text-align: center;
      padding: 5rem 1rem;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    ">
      <i class="fas fa-shopping-cart" style="font-size: 6rem; color: #d1d5db; margin-bottom: 1.5rem;"></i>
      <h2 style="font-size: 1.6rem; margin-bottom: 1rem; color: #4b5563;">Your cart is empty</h2>
      <p style="margin-bottom: 2rem; color: #6b7280;">Looks like you haven't added anything yet.</p>
      <a href="<?= BASE_URL ?>pages/products.php" style="
        display: inline-block;
        background: var(--primary);
        color: white;
        padding: 1rem 2rem;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.25s;
      " onmouseover="this.style.background=var(--primary-dark)">Start Shopping</a>
    </div>

  <?php else: ?>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2.5rem;">

      <!-- Cart Items -->
      <div>
        <?php foreach ($cart_items as $item): ?>
          <div style="
            display: flex;
            background: white;
            border-radius: 12px;
            margin-bottom: 1.2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
          ">
            <!-- Image placeholder -->
            <div style="width: 140px; background: #f3f4f6; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-box-open" style="font-size: 4rem; color: #d1d5db;"></i>
            </div>

            <div style="flex: 1; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between;">
              <div>
                <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem;">
                  <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($item['slug']) ?>" style="color: inherit; text-decoration: none;">
                    <?= htmlspecialchars($item['name']) ?>
                  </a>
                </h3>

                <div style="margin-bottom: 0.8rem;">
                  <span style="font-size: 1.3rem; font-weight: 700; color: #ef4444;">
                    ₦<?= number_format($item['price']) ?>
                  </span>
                  <span style="margin-left: 0.8rem; color: #6b7280;">
                    × <?= $item['qty'] ?> = ₦<?= number_format($item['sub']) ?>
                  </span>
                </div>
              </div>

              <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Quantity -->
                <form method="post" style="display: flex; align-items: center; gap: 0.5rem;">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= $item['id'] ?>">
                  <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" style="
                    width: 36px; height: 36px; border: 1px solid var(--border);
                    background: #f3f4f6; border-radius: 6px; font-size: 1.2rem;
                  " <?= $item['qty'] <= 1 ? 'disabled' : '' ?>>-</button>

                  <span style="width: 50px; text-align: center; font-weight: 600;"><?= $item['qty'] ?></span>

                  <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" style="
                    width: 36px; height: 36px; border: 1px solid var(--border);
                    background: #f3f4f6; border-radius: 6px; font-size: 1.2rem;
                  " <?= $item['qty'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                </form>

                <!-- Remove -->
                <form method="post">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="id" value="<?= $item['id'] ?>">
                  <button type="submit" style="
                    background: none;
                    border: none;
                    color: var(--danger);
                    font-size: 1rem;
                    cursor: pointer;
                  ">Remove</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Cart Summary -->
      <aside style="
        background: white;
        border-radius: 12px;
        padding: 1.8rem;
        height: fit-content;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        position: sticky;
        top: 1.5rem;
      ">
        <h2 style="font-size: 1.5rem; margin-bottom: 1.5rem;">Order Summary</h2>

        <div style="margin-bottom: 1.2rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
            <span>Subtotal (<?= count($cart_items) ?> items)</span>
            <strong>₦<?= number_format($total) ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem;">
            <span>Shipping</span>
            <span style="color: var(--success);">Free</span>
          </div>
          <div style="border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem; display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 700;">
            <span>Total</span>
            <span style="color: #ef4444;">₦<?= number_format($total) ?></span>
          </div>
        </div>

        <a href="<?= BASE_URL ?>pages/checkout.php" style="
          display: block;
          background: var(--primary);
          color: white;
          text-align: center;
          padding: 1.1rem;
          border-radius: 10px;
          font-size: 1.1rem;
          font-weight: 600;
          text-decoration: none;
          margin-top: 1.5rem;
          transition: 0.25s;
        " onmouseover="this.style.background=var(--primary-dark)">
          Proceed to Checkout
        </a>

        <p style="text-align:center; margin-top: 1.2rem; font-size: 0.9rem; color: #6b7280;">
          Secure checkout powered by Paystack
        </p>
      </aside>

    </div>

  <?php endif; ?>

</main>

<?php require_once '../includes/footer.php'; ?>