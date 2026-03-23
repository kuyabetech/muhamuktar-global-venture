<?php
// index.php - Homepage (AliExpress-style product display)

$page_title = "Home";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Fetch featured categories
try {
    $catStmt = $pdo->query("
        SELECT id, name, slug 
        FROM categories 
        WHERE status = 'active' 
        ORDER BY display_order, name 
        LIMIT 12
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Fetch flash deal products (with discounts)
try {
    $flashStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image,
               ROUND(((p.price - p.discount_price) / p.price) * 100) as discount_percent
        FROM products p
        WHERE p.status = 'active' AND p.discount_price IS NOT NULL
        ORDER BY RAND()
        LIMIT 6
    ");
    $flash_products = $flashStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $flash_products = [];
}

// Fetch featured products
try {
    $featuredStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active' AND p.featured = 1
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $featured_products = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featured_products = [];
}

// Fetch new arrivals
try {
    $newStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $new_products = $newStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $new_products = [];
}

// Fetch best sellers
try {
    $bestStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active'
        ORDER BY p.views DESC, p.created_at DESC
        LIMIT 12
    ");
    $best_products = $bestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $best_products = [];
}

// Fetch on-sale products
try {
    $saleStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image,
               ROUND(((p.price - p.discount_price) / p.price) * 100) as discount_percent
        FROM products p
        WHERE p.status = 'active' AND p.discount_price IS NOT NULL
        ORDER BY RAND()
        LIMIT 12
    ");
    $sale_products = $saleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sale_products = [];
}
?>

<style>
/* ===== ALIEXPRESS STYLE PRODUCT DISPLAY ===== */
/* Only product cards and grid layout - your colors preserved */

/* Products Grid - AliExpress Style */
.products-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

@media (max-width: 992px) {
    .products-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
}

/* Product Card - AliExpress Style */
.product-card {
    background: var(--white);
    border-radius: 8px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    display: block;
    position: relative;
    height: 100%;
    border: 1px solid var(--border);
}

.product-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
    border-color: var(--primary);
}

/* Product Image - Square Aspect Ratio */
.product-image {
    position: relative;
    background: #f8f8f8;
    padding-top: 100%; /* 1:1 Square */
    overflow: hidden;
}

.product-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.2s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.03);
}

/* Discount Badge */
.product-discount {
    position: absolute;
    top: 8px;
    left: 8px;
    background: var(--danger);
    color: white;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: var(--fw-bold);
    z-index: 2;
    line-height: 1;
}

/* Product Badges (right side) */
.product-badges {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 2;
}

.product-badge {
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 0.65rem;
    font-weight: var(--fw-bold);
    text-align: center;
    line-height: 1.2;
    backdrop-filter: blur(2px);
}

/* Product Info */
.product-info {
    padding: 10px 8px 12px;
}

/* Product Title */
.product-title {
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 6px;
    color: var(--text);
    line-height: 1.4;
    height: 2.4em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

/* Product Brand */
.product-brand {
    font-size: 0.7rem;
    color: var(--text-light);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.product-brand i {
    font-size: 0.6rem;
    color: var(--success);
}

/* Price Section */
.product-price {
    margin: 6px 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}

.price-current {
    font-size: 1.1rem;
    font-weight: var(--fw-bold);
    color: var(--danger);
    line-height: 1.2;
}

.price-old {
    font-size: 0.7rem;
    color: var(--text-lighter);
    text-decoration: line-through;
}

.price-save {
    display: inline-block;
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.6rem;
    font-weight: var(--fw-bold);
    margin-left: auto;
}

/* Rating Section */
.product-rating {
    display: flex;
    align-items: center;
    gap: 2px;
    color: #fbbf24;
    font-size: 0.7rem;
    margin: 4px 0;
}

.product-rating span {
    color: var(--text-lighter);
    margin-left: 4px;
    font-size: 0.65rem;
}

/* Shipping Info */
.product-shipping {
    font-size: 0.65rem;
    color: var(--success);
    font-weight: 500;
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.product-shipping i {
    font-size: 0.6rem;
}

/* Sold Count */
.product-sold {
    font-size: 0.65rem;
    color: var(--text-lighter);
    margin-left: auto;
}

/* Progress Bar - For Flash Sales */
.progress-bar {
    background: var(--border);
    height: 3px;
    border-radius: 2px;
    margin-top: 6px;
    overflow: hidden;
}

.progress-fill {
    background: var(--danger);
    height: 100%;
    border-radius: 2px;
}

/* Section Headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-header h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text);
    position: relative;
    padding-left: 12px;
}

.section-header h2::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 20px;
    background: var(--primary);
    border-radius: 2px;
}

.view-all-link {
    color: var(--primary);
    font-weight: 500;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.view-all-link:hover {
    color: var(--primary-dark);
    gap: 6px;
}

/* Flash Sale Timer */
.flash-timer {
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(220, 38, 38, 0.1);
    padding: 5px 12px;
    border-radius: 20px;
    margin-left: 15px;
}

.timer-block {
    background: var(--danger);
    color: white;
    padding: 3px 6px;
    border-radius: 4px;
    font-weight: bold;
    min-width: 35px;
    text-align: center;
    font-size: 0.9rem;
}

.timer-sep {
    color: var(--danger);
    font-weight: bold;
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 768px) {
    .categories-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 480px) {
    .categories-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.category-card {
    background: var(--white);
    border-radius: 8px;
    padding: 15px 10px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all 0.2s ease;
    border: 1px solid var(--border);
}

.category-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border-color: var(--primary);
}

.category-icon {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 8px;
}

.category-name {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 4px;
}

.category-count {
    font-size: 0.7rem;
    color: var(--text-light);
}

/* Hero Banner */
.hero-banner {
    background: linear-gradient(135deg, rgba(30, 64, 175, 0.9), rgba(59, 130, 246, 0.8));
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    border-radius: 0 0 20px 20px;
}

.hero-content {
    text-align: center;
    max-width: 700px;
    margin: 0 auto;
}

.hero-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
}

.hero-subtitle {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    opacity: 0.95;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.hero-btn {
    padding: 0.8rem 2rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.hero-btn.primary {
    background: var(--danger);
    color: white;
}

.hero-btn.secondary {
    background: rgba(255,255,255,0.15);
    color: white;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255,255,255,0.3);
}

/* Sale Banner */
.sale-banner {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
    padding: 2.5rem;
    border-radius: 12px;
    text-align: center;
    margin: 2rem 0;
}

.sale-title {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
}

.sale-subtitle {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
    opacity: 0.95;
}

.sale-btn {
    background: white;
    color: var(--danger);
    padding: 0.8rem 2.5rem;
    border-radius: 30px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.sale-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255,255,255,0.3);
}

/* Newsletter */
.newsletter-section {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 3rem 0;
    text-align: center;
    margin-top: 2rem;
    border-radius: 20px 20px 0 0;
}

.newsletter-content {
    max-width: 500px;
    margin: 0 auto;
}

.newsletter-title {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.newsletter-form {
    display: flex;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.newsletter-input {
    flex: 1;
    padding: 1rem;
    border: none;
    border-radius: 30px;
    font-size: 0.95rem;
}

.newsletter-btn {
    background: var(--danger);
    color: white;
    border: none;
    padding: 1rem 2rem;
    border-radius: 30px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.newsletter-btn:hover {
    background: #b91c1c;
    transform: translateY(-2px);
}

@media (max-width: 480px) {
    .newsletter-form {
        flex-direction: column;
    }
    
    .hero-title {
        font-size: 1.8rem;
    }
}
</style>

<div class="homepage">

<!-- Hero Banner -->
<section class="hero-banner">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Discover Amazing Products at Unbeatable Prices</h1>
            <p class="hero-subtitle">Shop thousands of quality items from trusted sellers • Free shipping on orders over ₦50,000 • Secure payments</p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>pages/products.php" class="hero-btn primary">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
                <a href="#featured" class="hero-btn secondary">
                    <i class="fas fa-star"></i> View Featured
                </a>
            </div>
        </div>
    </div>
</section>



<!-- Flash Sale Section -->
<?php if (!empty($flash_products)): ?>
<section style="margin-bottom: 2rem;">
    <div class="container">
        <div class="section-header">
            <h2>
                ⚡ Flash Sale
                <span class="flash-timer">
                    <span class="timer-block" id="hours">24</span>
                    <span class="timer-sep">:</span>
                    <span class="timer-block" id="minutes">59</span>
                    <span class="timer-sep">:</span>
                    <span class="timer-block" id="seconds">59</span>
                </span>
            </h2>
            <a href="<?= BASE_URL ?>pages/products.php?sale=flash" class="view-all-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="products-grid">
            <?php foreach ($flash_products as $p): 
                $final_price = $p['discount_price'] ?? $p['price'];
                $old_price = $p['discount_price'] ? $p['price'] : null;
                $discount = $p['discount_percent'] ?? ($old_price ? round((($old_price - $final_price) / $old_price) * 100) : 0);
                $sold_percent = rand(30, 95); // Demo - replace with actual sold percentage
                
                // Image source
                if (!empty($p['main_image'])) {
                    $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                } else {
                    $image_src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
                }
            ?>
            <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="product-card">
                <div class="product-image">
                    <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <?php if ($discount > 0): ?>
                        <span class="product-discount">-<?= $discount ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <?php if (!empty($p['brand'])): ?>
                    <div class="product-brand">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($p['brand']) ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                    
                    <div class="product-price">
                        <span class="price-current">₦<?= number_format($final_price) ?></span>
                        <?php if ($old_price): ?>
                            <span class="price-old">₦<?= number_format($old_price) ?></span>
                            <span class="price-save">-<?= $discount ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                        <span>(<?= rand(100, 5000) ?>)</span>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $sold_percent ?>%"></div>
                    </div>
                    
                    <div class="product-shipping">
                        <i class="fas fa-shipping-fast"></i> Free Shipping
                        <span class="product-sold"><?= $sold_percent ?>% sold</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products -->
<section style="margin-bottom: 2rem;">
    <div class="container">
        <div class="section-header">
            <h2>✨ Featured Products</h2>
            <a href="<?= BASE_URL ?>pages/products.php?featured=1" class="view-all-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="products-grid">
            <?php if (!empty($featured_products)): ?>
                <?php foreach ($featured_products as $p): 
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    
                    // Image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                    } else {
                        $image_src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
                    }
                ?>
                <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="product-card">
                    <div class="product-image">
                        <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    </div>
                    <div class="product-info">
                        <?php if (!empty($p['brand'])): ?>
                        <div class="product-brand">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($p['brand']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                        
                        <div class="product-price">
                            <span class="price-current">₦<?= number_format($final_price) ?></span>
                            <?php if ($old_price): ?>
                                <span class="price-old">₦<?= number_format($old_price) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <span>(<?= rand(100, 5000) ?>)</span>
                        </div>
                        
                        <div class="product-shipping">
                            <i class="fas fa-shipping-fast"></i> Free Shipping
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Sale Banner -->
<section class="sale-banner">
    <div class="container">
        <div class="sale-content">
            <h2 class="sale-title">🔥 SUPER DEAL ENDS SOON!</h2>
            <p class="sale-subtitle">Up to 70% OFF on selected items • Limited time offer</p>
            <a href="<?= BASE_URL ?>pages/products.php?discount_price=1" class="sale-btn">
                <i class="fas fa-bolt"></i> Shop Sale Now
            </a>
        </div>
    </div>
</section>

<!-- New Arrivals -->
<section style="margin-bottom: 2rem;">
    <div class="container">
        <div class="section-header">
            <h2>🆕 New Arrivals</h2>
            <a href="<?= BASE_URL ?>pages/products.php?sort=newest" class="view-all-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="products-grid">
            <?php if (!empty($new_products)): ?>
                <?php foreach (array_slice($new_products, 0, 12) as $p): 
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    
                    // Image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                    } else {
                        $image_src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
                    }
                ?>
                <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="product-card">
                    <div class="product-image">
                        <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        <div class="product-badges">
                            <span class="product-badge" style="background: var(--success);">NEW</span>
                        </div>
                    </div>
                    <div class="product-info">
                        <?php if (!empty($p['brand'])): ?>
                        <div class="product-brand">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($p['brand']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                        
                        <div class="product-price">
                            <span class="price-current">₦<?= number_format($final_price) ?></span>
                            <?php if ($old_price): ?>
                                <span class="price-old">₦<?= number_format($old_price) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span>(<?= rand(10, 500) ?>)</span>
                        </div>
                        
                        <div class="product-shipping">
                            <i class="fas fa-shipping-fast"></i> Free Shipping
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Best Sellers -->
<section style="margin-bottom: 2rem; background: #f8fafc; padding: 2rem 0;">
    <div class="container">
        <div class="section-header">
            <h2>🏆 Best Sellers</h2>
            <a href="<?= BASE_URL ?>pages/products.php?sort=popular" class="view-all-link">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="products-grid">
            <?php if (!empty($best_products)): ?>
                <?php foreach ($best_products as $p): 
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    
                    // Image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                    } else {
                        $image_src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
                    }
                ?>
                <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="product-card">
                    <div class="product-image">
                        <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        <div class="product-badges">
                            <span class="product-badge" style="background: var(--warning);">HOT</span>
                        </div>
                    </div>
                    <div class="product-info">
                        <?php if (!empty($p['brand'])): ?>
                        <div class="product-brand">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($p['brand']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                        
                        <div class="product-price">
                            <span class="price-current">₦<?= number_format($final_price) ?></span>
                            <?php if ($old_price): ?>
                                <span class="price-old">₦<?= number_format($old_price) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <span>(<?= rand(1000, 10000) ?>)</span>
                        </div>
                        
                        <div class="product-shipping">
                            <i class="fas fa-shipping-fast"></i> Free Shipping
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-content">
            <h2 class="newsletter-title">Stay Updated</h2>
            <p class="newsletter-description">Subscribe to get 10% off your first order and exclusive deals</p>
            <form class="newsletter-form" id="newsletterForm">
                <input type="email" class="newsletter-input" placeholder="Enter your email" required>
                <button type="submit" class="newsletter-btn">
                    <i class="fas fa-paper-plane"></i> Subscribe
                </button>
            </form>
        </div>
    </div>
</section>

</div>

<script>
// Flash Sale Timer
function startFlashTimer() {
    const hoursEl = document.getElementById('hours');
    const minutesEl = document.getElementById('minutes');
    const secondsEl = document.getElementById('seconds');
    
    if (!hoursEl || !minutesEl || !secondsEl) return;
    
    let hours = 23;
    let minutes = 59;
    let seconds = 59;
    
    setInterval(() => {
        if (seconds > 0) {
            seconds--;
        } else {
            if (minutes > 0) {
                minutes--;
                seconds = 59;
            } else {
                if (hours > 0) {
                    hours--;
                    minutes = 59;
                    seconds = 59;
                }
            }
        }
        
        hoursEl.textContent = String(hours).padStart(2, '0');
        minutesEl.textContent = String(minutes).padStart(2, '0');
        secondsEl.textContent = String(seconds).padStart(2, '0');
    }, 1000);
}

// Newsletter form
document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Thank you for subscribing!');
    this.reset();
});

document.addEventListener('DOMContentLoaded', startFlashTimer);
</script>

<?php require_once 'includes/footer.php'; ?>