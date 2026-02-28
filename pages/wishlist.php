<?php
// pages/wishlist.php - Wishlist Page

$page_title = "My Wishlist";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Redirect if not logged in
if (!is_logged_in()) {
    header("Location: " . BASE_URL . "pages/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle remove from wishlist
if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        // Update wishlist count in session
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['wishlist_count'] = $stmt->fetchColumn();
        
        header("Location: wishlist.php?removed=1");
        exit;
    } catch (Exception $e) {
        $error = "Error removing item from wishlist";
    }
}

// Handle clear wishlist
if (isset($_GET['clear'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['wishlist_count'] = 0;
        
        header("Location: wishlist.php?cleared=1");
        exit;
    } catch (Exception $e) {
        $error = "Error clearing wishlist";
    }
}

// Handle add all to cart
if (isset($_POST['add_all_to_cart'])) {
    try {
        // Get all wishlist items
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $items = $stmt->fetchAll();
        
        $pdo->beginTransaction();
        
        foreach ($items as $item) {
            // Check if already in cart
            $check = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $check->execute([$user_id, $item['product_id']]);
            $cart_item = $check->fetch();
            
            if ($cart_item) {
                // Update quantity
                $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $item['product_id']]);
            } else {
                // Add to cart
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                $stmt->execute([$user_id, $item['product_id']]);
            }
        }
        
        $pdo->commit();
        
        // Update cart count
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $_SESSION['cart_count'] = $stmt->fetchColumn();
        
        header("Location: cart.php?added=all");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding items to cart";
    }
}

// Get wishlist items
$wishlist_items = [];
$total_value = 0;

try {
    $stmt = $pdo->prepare("
        SELECT w.*, 
               p.id as product_id,
               p.name, 
               p.price, 
               p.discount_price,
               p.slug,
               p.stock,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
               (SELECT COUNT(*) FROM cart WHERE user_id = ? AND product_id = p.id) as in_cart
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $wishlist_items = $stmt->fetchAll();
    
    // Calculate total value
    foreach ($wishlist_items as $item) {
        $price = $item['discount_price'] ?: $item['price'];
        $total_value += $price;
    }
    
} catch (Exception $e) {
    $error = "Error loading wishlist";
}

// Get recently viewed products (optional)
$recently_viewed = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p
        WHERE p.id IN (
            SELECT product_id FROM recently_viewed 
            WHERE user_id = ? 
            ORDER BY viewed_at DESC 
            LIMIT 4
        )
    ");
    $stmt->execute([$user_id]);
    $recently_viewed = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

$message = $_GET['removed'] ? 'Item removed from wishlist' : ($_GET['cleared'] ? 'Wishlist cleared' : '');
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">My Wishlist</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/profile.php">Profile</a>
                <span class="separator">/</span>
                <span class="current">Wishlist</span>
            </div>
        </div>
    </section>

    <!-- Wishlist Section -->
    <section class="wishlist-section">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($wishlist_items)): ?>
                <!-- Empty Wishlist -->
                <div class="empty-wishlist">
                    <div class="empty-state">
                        <i class="fas fa-heart-broken"></i>
                        <h2>Your Wishlist is Empty</h2>
                        <p>Save items you love to your wishlist and they'll appear here</p>
                        <div class="empty-actions">
                            <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">
                                <i class="fas fa-shopping-bag"></i>
                                Continue Shopping
                            </a>
                            <a href="<?= BASE_URL ?>pages/categories.php" class="btn-secondary">
                                <i class="fas fa-list"></i>
                                Browse Categories
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($recently_viewed)): ?>
                        <div class="recently-viewed">
                            <h3>Recently Viewed</h3>
                            <div class="products-grid">
                                <?php foreach ($recently_viewed as $product): ?>
                                    <div class="product-card">
                                        <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>" class="product-image">
                                            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image'] ?? 'no-image.jpg') ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        </a>
                                        <div class="product-info">
                                            <h4 class="product-title">
                                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                                    <?= htmlspecialchars($product['name']) ?>
                                                </a>
                                            </h4>
                                            <div class="product-price">
                                                <?php if ($product['discount_price']): ?>
                                                    <span class="current-price">₦<?= number_format($product['discount_price']) ?></span>
                                                    <span class="old-price">₦<?= number_format($product['price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current-price">₦<?= number_format($product['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <button class="quick-add-btn" onclick="quickAddToCart(<?= $product['id'] ?>)">
                                                <i class="fas fa-cart-plus"></i>
                                                Quick Add
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Wishlist Header -->
                <div class="wishlist-header">
                    <div class="wishlist-summary">
                        <span class="item-count"><?= count($wishlist_items) ?> items</span>
                        <span class="total-value">Total Value: <strong>₦<?= number_format($total_value, 2) ?></strong></span>
                    </div>
                    <div class="wishlist-actions">
                        <form method="post" style="display: inline;">
                            <button type="submit" name="add_all_to_cart" class="btn-primary" 
                                    onclick="return confirm('Add all items from wishlist to cart?')">
                                <i class="fas fa-cart-plus"></i>
                                Add All to Cart
                            </button>
                        </form>
                        <a href="?clear=1" class="btn-secondary" onclick="return confirm('Clear your entire wishlist?')">
                            <i class="fas fa-trash"></i>
                            Clear Wishlist
                        </a>
                    </div>
                </div>

                <!-- Wishlist Grid -->
                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                        <div class="wishlist-card">
                            <div class="product-image">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $item['product_id'] ?>">
                                    <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($item['image'] ?? 'no-image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['name']) ?>">
                                </a>
                                <?php if ($item['discount_price']): ?>
                                    <span class="discount-badge">
                                        -<?= round((($item['price'] - $item['discount_price']) / $item['price']) * 100) ?>%
                                    </span>
                                <?php endif; ?>
                                <button class="remove-btn" onclick="removeFromWishlist(<?= $item['product_id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-title">
                                    <a href="<?= BASE_URL ?>pages/product.php?id=<?= $item['product_id'] ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="product-price">
                                    <?php if ($item['discount_price']): ?>
                                        <span class="current-price">₦<?= number_format($item['discount_price']) ?></span>
                                        <span class="old-price">₦<?= number_format($item['price']) ?></span>
                                    <?php else: ?>
                                        <span class="current-price">₦<?= number_format($item['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="stock-status <?= $item['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                    <i class="fas fa-<?= $item['stock'] > 0 ? 'check-circle' : 'times-circle' ?>"></i>
                                    <?= $item['stock'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                </div>
                                
                                <div class="product-actions">
                                    <?php if ($item['in_cart'] > 0): ?>
                                        <a href="<?= BASE_URL ?>pages/cart.php" class="btn-secondary btn-block">
                                            <i class="fas fa-check"></i>
                                            Already in Cart
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-primary btn-block" onclick="addToCart(<?= $item['product_id'] ?>)">
                                            <i class="fas fa-cart-plus"></i>
                                            Add to Cart
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn-icon" onclick="shareProduct(<?= $item['product_id'] ?>)" title="Share">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Share Wishlist -->
                <div class="share-wishlist">
                    <h3>Share Your Wishlist</h3>
                    <p>Share your wishlist with friends and family</p>
                    <div class="share-buttons">
                        <button class="share-btn facebook" onclick="shareOnFacebook()">
                            <i class="fab fa-facebook-f"></i>
                            Facebook
                        </button>
                        <button class="share-btn twitter" onclick="shareOnTwitter()">
                            <i class="fab fa-twitter"></i>
                            Twitter
                        </button>
                        <button class="share-btn whatsapp" onclick="shareOnWhatsApp()">
                            <i class="fab fa-whatsapp"></i>
                            WhatsApp
                        </button>
                        <button class="share-btn email" onclick="shareByEmail()">
                            <i class="fas fa-envelope"></i>
                            Email
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Recommendations Section -->
    <?php if (!empty($wishlist_items)): ?>
        <section class="recommendations-section">
            <div class="container">
                <h2 class="section-title">You Might Also Like</h2>
                <?php
                // Get product recommendations based on wishlist categories
                $category_ids = [];
                foreach ($wishlist_items as $item) {
                    $stmt = $pdo->prepare("SELECT category_id FROM products WHERE id = ?");
                    $stmt->execute([$item['product_id']]);
                    $cat_id = $stmt->fetchColumn();
                    if ($cat_id) {
                        $category_ids[] = $cat_id;
                    }
                }
                
                $recommendations = [];
                if (!empty($category_ids)) {
                    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
                    $stmt = $pdo->prepare("
                        SELECT p.*, 
                               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
                        FROM products p
                        WHERE p.category_id IN ($placeholders)
                        AND p.id NOT IN (SELECT product_id FROM wishlist WHERE user_id = ?)
                        AND p.status = 'active'
                        ORDER BY RAND()
                        LIMIT 4
                    ");
                    
                    $params = $category_ids;
                    $params[] = $user_id;
                    $stmt->execute($params);
                    $recommendations = $stmt->fetchAll();
                }
                ?>
                
                <?php if (!empty($recommendations)): ?>
                    <div class="products-grid">
                        <?php foreach ($recommendations as $product): ?>
                            <div class="product-card">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>" class="product-image">
                                    <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image'] ?? 'no-image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php if ($product['discount_price']): ?>
                                        <span class="discount-badge">-<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%</span>
                                    <?php endif; ?>
                                </a>
                                <div class="product-info">
                                    <h4 class="product-title">
                                        <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </a>
                                    </h4>
                                    <div class="product-price">
                                        <?php if ($product['discount_price']): ?>
                                            <span class="current-price">₦<?= number_format($product['discount_price']) ?></span>
                                            <span class="old-price">₦<?= number_format($product['price']) ?></span>
                                        <?php else: ?>
                                            <span class="current-price">₦<?= number_format($product['price']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="add-to-wishlist-btn" onclick="addToWishlist(<?= $product['id'] ?>)">
                                        <i class="far fa-heart"></i>
                                        Add to Wishlist
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 3rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

/* Wishlist Section */
.wishlist-section {
    padding: 4rem 0;
    background: var(--bg);
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Empty Wishlist */
.empty-wishlist {
    text-align: center;
}

.empty-state {
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    margin-bottom: 3rem;
}

.empty-state i {
    font-size: 5rem;
    color: var(--text-lighter);
    margin-bottom: 1.5rem;
}

.empty-state h2 {
    font-size: 2rem;
    color: var(--text);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-light);
    font-size: 1.1rem;
    margin-bottom: 2rem;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: white;
    color: var(--text);
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    border: 2px solid var(--border);
    transition: all 0.3s;
    cursor: pointer;
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Recently Viewed */
.recently-viewed {
    margin-top: 3rem;
}

.recently-viewed h3 {
    font-size: 1.5rem;
    margin-bottom: 2rem;
    color: var(--text);
}

/* Wishlist Header */
.wishlist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.wishlist-summary {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.item-count {
    font-size: 1.1rem;
    color: var(--text-light);
}

.total-value {
    font-size: 1.2rem;
    color: var(--text);
}

.total-value strong {
    color: var(--primary);
    font-size: 1.3rem;
}

.wishlist-actions {
    display: flex;
    gap: 1rem;
}

/* Wishlist Grid */
.wishlist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.wishlist-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
    position: relative;
}

.wishlist-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.product-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.wishlist-card:hover .product-image img {
    transform: scale(1.05);
}

.discount-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: var(--danger);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 2;
}

.remove-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 35px;
    height: 35px;
    background: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s;
    z-index: 2;
}

.remove-btn:hover {
    background: var(--danger);
    color: white;
    transform: scale(1.1);
}

.product-info {
    padding: 1.5rem;
}

.product-title {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.product-title a {
    color: var(--text);
    text-decoration: none;
}

.product-title a:hover {
    color: var(--primary);
}

.product-price {
    margin-bottom: 0.75rem;
}

.current-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.old-price {
    font-size: 0.9rem;
    color: var(--text-light);
    text-decoration: line-through;
    margin-left: 0.5rem;
}

.stock-status {
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.stock-status i {
    margin-right: 0.25rem;
}

.stock-status.in-stock {
    color: #10b981;
}

.stock-status.out-of-stock {
    color: var(--danger);
}

.product-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-block {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
    text-decoration: none;
}

.btn-icon {
    width: 45px;
    height: 45px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

/* Share Wishlist */
.share-wishlist {
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.share-wishlist h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.share-wishlist p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.share-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.share-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    color: white;
}

.share-btn.facebook {
    background: #1877f2;
}

.share-btn.twitter {
    background: #1da1f2;
}

.share-btn.whatsapp {
    background: #25d366;
}

.share-btn.email {
    background: #ea4335;
}

.share-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.1);
}

/* Recommendations Section */
.recommendations-section {
    padding: 4rem 0;
    background: white;
}

.section-title {
    font-size: 2rem;
    text-align: center;
    margin-bottom: 3rem;
    position: relative;
    padding-bottom: 1rem;
}

.section-title:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: var(--primary);
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.product-card .product-image {
    height: 200px;
}

.product-card .product-info {
    padding: 1rem;
}

.product-card .product-title {
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
}

.add-to-wishlist-btn {
    width: 100%;
    padding: 0.5rem;
    background: none;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-light);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.add-to-wishlist-btn:hover {
    background: #fee2e2;
    border-color: var(--danger);
    color: var(--danger);
}

.quick-add-btn {
    width: 100%;
    padding: 0.5rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.quick-add-btn:hover {
    background: var(--primary-dark);
}

/* Responsive */
@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .wishlist-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .wishlist-summary {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .wishlist-section {
        padding: 2rem 0;
    }
    
    .wishlist-grid {
        grid-template-columns: 1fr;
    }
    
    .empty-actions {
        flex-direction: column;
    }
    
    .share-buttons {
        flex-direction: column;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .wishlist-card .product-image {
        height: 200px;
    }
}
</style>

<script>
function removeFromWishlist(productId) {
    if (confirm('Remove this item from your wishlist?')) {
        window.location.href = '?remove=' + productId;
    }
}

function addToCart(productId) {
    fetch('<?= BASE_URL ?>api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({product_id: productId, quantity: 1})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert('Product added to cart!');
            
            // Update cart count in header
            if (data.cart_count !== undefined) {
                window.dispatchEvent(new CustomEvent('cartUpdate', {
                    detail: {count: data.cart_count}
                }));
            }
            
            // Reload page to update button state
            location.reload();
        } else {
            alert(data.message || 'Error adding to cart');
        }
    });
}

function quickAddToCart(productId) {
    addToCart(productId);
}

function addToWishlist(productId) {
    <?php if (!is_logged_in()): ?>
        window.location.href = '<?= BASE_URL ?>pages/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    <?php endif; ?>
    
    fetch('<?= BASE_URL ?>api/toggle-wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({product_id: productId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Added to wishlist!');
            if (data.wishlist_count !== undefined) {
                window.dispatchEvent(new CustomEvent('wishlistUpdate', {
                    detail: {count: data.wishlist_count}
                }));
            }
        }
    });
}

function shareOnFacebook() {
    const url = encodeURIComponent(window.location.href);
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank');
}

function shareOnTwitter() {
    const text = encodeURIComponent('Check out my wishlist!');
    const url = encodeURIComponent(window.location.href);
    window.open('https://twitter.com/intent/tweet?text=' + text + '&url=' + url, '_blank');
}

function shareOnWhatsApp() {
    const text = encodeURIComponent('Check out my wishlist: ' + window.location.href);
    window.open('https://wa.me/?text=' + text, '_blank');
}

function shareByEmail() {
    const subject = encodeURIComponent('My Wishlist');
    const body = encodeURIComponent('Check out my wishlist: ' + window.location.href);
    window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
}

function shareProduct(productId) {
    const url = '<?= BASE_URL ?>pages/product.php?id=' + productId;
    const text = encodeURIComponent('Check out this product: ' + url);
    
    if (navigator.share) {
        navigator.share({
            title: 'Share Product',
            url: url
        });
    } else {
        prompt('Copy this link to share:', url);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>