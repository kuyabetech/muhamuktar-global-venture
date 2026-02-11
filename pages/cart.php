<?php
// pages/cart.php
$page_title = "Shopping Cart";
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';


// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();
$cart_items = [];
$subtotal = 0;
$cart_count = 0;
$shipping_fee = 0;
$total = 0;

try {
    if ($user_id) {
        // Logged-in users
        $stmt = $pdo->prepare("
            SELECT 
                c.id AS cart_id,
                c.quantity,
                c.price AS item_price,
                c.created_at,
                p.id AS product_id,
                p.name,
                p.slug,
                pi.filename,
                p.stock,
                p.status,
                p.price AS current_price
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
        // Guests
        $stmt = $pdo->prepare("
            SELECT 
                c.id AS cart_id,
                c.quantity,
                c.price AS item_price,
                c.created_at,
                p.id AS product_id,
                p.name,
                p.slug,
                pi.filename,
                p.stock,
                p.status,
                p.price AS current_price
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
            $cart_count += $item['quantity'];
        }
    }
    
    // Calculate shipping (free over ₦50,000)
    $shipping_fee = $subtotal >= 50000 ? 0 : 1500;
    $total = $subtotal + $shipping_fee;

} catch (Exception $e) {
    error_log("Cart error: " . $e->getMessage());
    $error = "Unable to load cart items: " . $e->getMessage();
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

.cart-container {
    padding: 2rem 0 4rem;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100px);
    min-height: 70vh;
}

.cart-header {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: var(--text);
    background: linear-gradient(135deg, var(--primary), #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.cart-subtitle {
    color: var(--text-light);
    margin-bottom: 2.5rem;
    font-size: 1.1rem;
}

.empty-state {
    text-align: center;
    padding: 5rem 1.5rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    max-width: 600px;
    margin: 2rem auto;
    border: 1px solid var(--border);
}

.empty-icon {
    font-size: 6rem;
    color: #e5e7eb;
    margin-bottom: 1.5rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.empty-title {
    font-size: 1.8rem;
    color: var(--text);
    margin-bottom: 1rem;
    font-weight: 700;
}

.empty-message {
    color: var(--text-light);
    margin-bottom: 2.5rem;
    font-size: 1.1rem;
    line-height: 1.6;
}

.shop-now-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 1rem 2.5rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 20px rgba(59,130,246,0.25);
}

.shop-now-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 30px rgba(59,130,246,0.35);
}

.cart-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2.5rem;
    align-items: start;
}

.cart-items-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.cart-item-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
    position: relative;
}

.cart-item-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    border-color: var(--primary);
}

.cart-item-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cart-item-card:hover::before {
    opacity: 1;
}

.cart-item-inner {
    display: flex;
    padding: 1.5rem;
    gap: 1.5rem;
}

.item-thumbnail {
    width: 160px;
    height: 160px;
    flex-shrink: 0;
    background: var(--bg-light);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.item-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.item-thumbnail:hover img {
    transform: scale(1.05);
}

.item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.item-header {
    margin-bottom: 1rem;
}

.item-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.item-title a {
    color: var(--text);
    text-decoration: none;
    transition: color 0.2s;
}

.item-title a:hover {
    color: var(--primary);
}

.item-sku {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-bottom: 0.5rem;
}

.item-pricing {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
    margin: 1rem 0;
}

.item-price-current {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--danger);
}

.item-price-original {
    font-size: 1.1rem;
    color: var(--text-light);
    text-decoration: line-through;
}

.item-total {
    font-size: 1.1rem;
    color: var(--text);
    font-weight: 600;
    background: var(--bg-light);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    display: inline-block;
}

.stock-status {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-top: 0.5rem;
}

.stock-in-stock {
    background: #d1fae5;
    color: #065f46;
}

.stock-low {
    background: #fef3c7;
    color: #92400e;
}

.stock-out {
    background: #fee2e2;
    color: #991b1b;
}

.item-actions {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

.quantity-control {
    display: flex;
    align-items: center;
    border: 2px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    background: white;
    transition: border-color 0.2s;
}

.quantity-control:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.quantity-btn {
    width: 48px;
    height: 48px;
    background: var(--bg-light);
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text);
}

.quantity-btn:hover:not(:disabled) {
    background: var(--primary);
    color: white;
}

.quantity-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.quantity-field {
    width: 70px;
    text-align: center;
    border: none;
    font-size: 1.2rem;
    font-weight: 700;
    padding: 0.5rem;
    color: var(--text);
    background: white;
}

.quantity-field:focus {
    outline: none;
}

.remove-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-weight: 600;
    background: none;
    border: 2px solid var(--danger);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.95rem;
}

.remove-btn:hover {
    background: var(--danger);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239,68,68,0.25);
}

.summary-panel {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.08);
    border: 1px solid var(--border);
    position: sticky;
    top: 120px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
}

.summary-heading {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text);
    margin: 0;
}

.summary-count {
    font-size: 1rem;
    color: var(--text-light);
    background: var(--bg-light);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding: 0.75rem 0;
    font-size: 1.05rem;
}

.summary-line:not(:last-child) {
    border-bottom: 1px dashed var(--border);
}

.summary-line strong {
    font-weight: 700;
    color: var(--text);
}

.summary-line span {
    color: var(--text);
    font-weight: 500;
}

.free-shipping {
    color: var(--success);
    font-weight: 600;
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

.grand-total {
    border-top: 3px solid var(--border);
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.grand-total-label {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text);
}

.grand-total-amount {
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
    font-size: 2rem;
    color: var(--text-light);
    opacity: 0.7;
    transition: all 0.2s;
}

.payment-icon:hover {
    opacity: 1;
    transform: translateY(-2px);
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

.cart-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 2.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.continue-shopping {
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
}

.continue-shopping:hover {
    background: var(--primary);
    color: white;
}

.clear-cart {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--danger);
    font-weight: 600;
    background: none;
    border: 2px solid var(--danger);
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.clear-cart:hover {
    background: var(--danger);
    color: white;
}

@media (max-width: 1200px) {
    .cart-grid {
        gap: 2rem;
    }
}

@media (max-width: 992px) {
    .cart-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .summary-panel {
        position: static;
        order: -1;
    }
    
    .cart-header {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .cart-container {
        padding: 1.5rem 0 3rem;
    }
    
    .cart-item-inner {
        flex-direction: column;
        padding: 1.25rem;
    }
    
    .item-thumbnail {
        width: 100%;
        height: 200px;
    }
    
    .item-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .quantity-control {
        justify-content: center;
    }
    
    .remove-btn {
        text-align: center;
        justify-content: center;
    }
    
    .cart-actions {
        flex-direction: column;
        gap: 1rem;
    }
    
    .continue-shopping,
    .clear-cart {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .cart-header {
        font-size: 1.75rem;
    }
    
    .empty-state {
        padding: 3rem 1rem;
    }
    
    .empty-icon {
        font-size: 4rem;
    }
    
    .shop-now-btn {
        width: 100%;
        padding: 1rem;
    }
}
</style>

<main class="cart-container container" style="margin: 10px;">

    <h1 class="cart-header">Your Shopping Cart</h1>
    <p class="cart-subtitle">Review your items and proceed to checkout</p>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; border: 1px solid #fca5a5;">
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <div class="empty-state">
            <i class="fas fa-shopping-cart empty-icon"></i>
            <h2 class="empty-title">Your cart is feeling empty</h2>
            <p class="empty-message">Looks like you haven't added any items to your cart yet.<br>Explore our collection and find something you love!</p>
            <a href="<?= BASE_URL ?>pages/products.php" class="shop-now-btn">
                <i class="fas fa-shopping-bag"></i> Browse Products
            </a>
        </div>

    <?php else: ?>

        <div class="cart-grid">

            <!-- Items -->
            <section class="cart-items-list">
                <?php foreach ($cart_items as $item): 
                    if (!$item['name']) continue;
                    
                    $max_allowed = min($item['stock'], 50);
                    $low_stock = $item['quantity'] > $item['stock'];
                    $item_total = $item['quantity'] * $item['item_price'];
                    $has_image = !empty($item['filename']);
                    $image_path = $has_image ? '../uploads/products/' . htmlspecialchars($item['filename']) : '';
                    
                    // Determine stock status
                    if ($item['stock'] == 0) {
                        $stock_class = 'stock-out';
                        $stock_text = 'Out of stock';
                        $stock_icon = 'fa-times-circle';
                    } elseif ($item['stock'] < 10) {
                        $stock_class = 'stock-low';
                        $stock_text = 'Low stock: ' . $item['stock'] . ' left';
                        $stock_icon = 'fa-exclamation-triangle';
                    } else {
                        $stock_class = 'stock-in-stock';
                        $stock_text = 'In stock';
                        $stock_icon = 'fa-check-circle';
                    }
                ?>
                    <div class="cart-item-card">
                        <div class="cart-item-inner">
                            <div class="item-thumbnail">
                                <?php if ($has_image): ?>
                                    <img src="<?= $image_path ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\'fas fa-box-open fa-3x text-muted\'></i>';">
                                <?php else: ?>
                                    <i class="fas fa-box-open fa-3x text-muted"></i>
                                <?php endif; ?>
                            </div>

                            <div class="item-details">
                                <div class="item-header">
                                    <h3 class="item-title">
                                        <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($item['slug']) ?>">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </a>
                                    </h3>
                                    <div class="item-sku">SKU: <?= $item['product_id'] ?></div>
                                    
                                    <div class="item-pricing">
                                        <span class="item-price-current">₦<?= number_format($item['item_price']) ?></span>
                                        <?php if ($item['item_price'] < $item['current_price']): ?>
                                            <span class="item-price-original">₦<?= number_format($item['current_price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="stock-status <?= $stock_class ?>">
                                        <i class="fas <?= $stock_icon ?>"></i>
                                        <?= $stock_text ?>
                                    </div>
                                </div>

                                <div class="item-actions">
                                    <div class="quantity-control">
                                        <button type="button" 
                                                class="quantity-btn decrease-btn" 
                                                data-cart-id="<?= $item['cart_id'] ?>"
                                                <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        
                                        <input type="number" 
                                               class="quantity-field" 
                                               value="<?= $item['quantity'] ?>" 
                                               min="1" 
                                               max="<?= $max_allowed ?>"
                                               data-cart-id="<?= $item['cart_id'] ?>"
                                               data-unit-price="<?= $item['item_price'] ?>"
                                               readonly>
                                       
                                        <button type="button" 
                                                class="quantity-btn increase-btn" 
                                                data-cart-id="<?= $item['cart_id'] ?>"
                                                <?= $item['quantity'] >= $max_allowed ? 'disabled' : '' ?>>
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>

                                    <button type="button" 
                                            class="remove-btn remove-item-btn" 
                                            data-cart-id="<?= $item['cart_id'] ?>">
                                        <i class="fas fa-trash"></i> Remove Item
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Cart Actions -->
                <div class="cart-actions">
                    <a href="<?= BASE_URL ?>pages/products.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                    <button type="button" class="clear-cart" id="clearCartBtn">
                        <i class="fas fa-trash-alt"></i> Clear Cart
                    </button>
                </div>
            </section>

            <!-- Order Summary -->
            <aside class="summary-panel">
                <div class="summary-header">
                    <h2 class="summary-heading">Order Summary</h2>
                    <span class="summary-count"><?= $cart_count ?> items</span>
                </div>

                <div class="summary-details">
                    <div class="summary-line">
                        <span>Subtotal</span>
                        <strong>₦<?= number_format($subtotal) ?></strong>
                    </div>
                    
                    <div class="summary-line">
                        <span>Shipping</span>
                        <span class="free-shipping">
                            <?php if ($shipping_fee == 0): ?>
                                FREE
                            <?php else: ?>
                                ₦<?= number_format($shipping_fee) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($shipping_fee > 0): ?>
                        <div class="shipping-progress">
                            <div class="shipping-progress-fill" style="width: <?= min(100, ($subtotal / 50000) * 100) ?>%"></div>
                        </div>
                        <div class="shipping-message">
                            <?php if ($subtotal < 50000): ?>
                                Add <strong>₦<?= number_format(50000 - $subtotal) ?></strong> more for FREE shipping!
                            <?php else: ?>
                                You've earned FREE shipping!
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grand-total">
                    <div class="grand-total-label">Total</div>
                    <div class="grand-total-amount">₦<?= number_format($total) ?></div>
                </div>

                <button class="checkout-button" onclick="window.location.href='<?= BASE_URL ?>pages/checkout.php'">
                    <i class="fas fa-lock"></i> Proceed to Checkout
                </button>
                
                <div class="payment-methods">
                    <i class="fab fa-cc-visa payment-icon"></i>
                    <i class="fab fa-cc-mastercard payment-icon"></i>
                    <i class="fab fa-cc-amex payment-icon"></i>
                    <i class="fab fa-cc-paypal payment-icon"></i>
                    <i class="fas fa-university payment-icon"></i>
                </div>
                
                <div class="security-notice">
                    <i class="fas fa-shield-alt security-icon"></i>
                    <span>Secure checkout • 30-day return policy • SSL encrypted</span>
                </div>
            </aside>
        </div>

    <?php endif; ?>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update quantity function
    async function updateCartQuantity(cartId, quantity) {
        try {
            const response = await fetch('../actions/update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `cart_id=${cartId}&quantity=${quantity}`
            });
            
            if (response.ok) {
                location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update quantity. Please try again.');
        }
    }
    
    // Remove item function
    async function removeCartItem(cartId) {
        if (!confirm('Are you sure you want to remove this item from your cart?')) {
            return;
        }
        
        try {
            const response = await fetch('../actions/remove_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `cart_id=${cartId}`
            });
            
            if (response.ok) {
                location.reload();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to remove item. Please try again.');
        }
    }
    
    // Quantity buttons
    document.querySelectorAll('.decrease-btn').forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            const input = this.closest('.quantity-control').querySelector('.quantity-field');
            const newQuantity = parseInt(input.value) - 1;
            
            if (newQuantity >= parseInt(input.min)) {
                updateCartQuantity(cartId, newQuantity);
            }
        });
    });
    
    document.querySelectorAll('.increase-btn').forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            const input = this.closest('.quantity-control').querySelector('.quantity-field');
            const newQuantity = parseInt(input.value) + 1;
            
            if (newQuantity <= parseInt(input.max)) {
                updateCartQuantity(cartId, newQuantity);
            }
        });
    });
    
    // Remove buttons
    document.querySelectorAll('.remove-item-btn').forEach(button => {
        button.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            removeCartItem(cartId);
        });
    });
    
    // Clear cart button
    document.getElementById('clearCartBtn')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear your entire cart?')) {
            // You would implement clear cart functionality here
            alert('Clear cart functionality would be implemented here');
        }
    });
    
    // Quantity input direct edit
    document.querySelectorAll('.quantity-field').forEach(input => {
        input.addEventListener('dblclick', function() {
            this.removeAttribute('readonly');
            this.focus();
            this.select();
        });
        
        input.addEventListener('blur', function() {
            this.setAttribute('readonly', true);
            const cartId = this.dataset.cartId;
            const newQuantity = parseInt(this.value);
            const min = parseInt(this.min);
            const max = parseInt(this.max);
            
            if (!isNaN(newQuantity) && newQuantity >= min && newQuantity <= max) {
                if (newQuantity !== parseInt(this.defaultValue)) {
                    updateCartQuantity(cartId, newQuantity);
                }
            } else {
                this.value = this.defaultValue;
            }
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.blur();
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>