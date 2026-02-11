<?php
// pages/product.php?slug=example-product-slug

$page_title = "Product Detail";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$slug = trim($_GET['slug'] ?? '');

if ($slug === '') {
    header("Location: " . BASE_URL . "pages/products.php");
    exit;
}

try {
    // Fetch product with main image
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name,
               pi.filename AS main_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_images pi 
            ON p.id = pi.product_id AND pi.is_main = 1
        WHERE p.slug = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        $related = [];
    } else {

        // Build product image URL
        $productImage = !empty($product['main_image'])
            ? BASE_URL . "uploads/products/" . $product['main_image']
            : BASE_URL . "assets/images/no-image.png";

        // Fetch related products (same category) with images
        $relatedStmt = $pdo->prepare("
            SELECT p.id, p.name, p.slug, p.price, p.discount_price,
                   pi.filename AS main_image
            FROM products p
            LEFT JOIN product_images pi 
                ON p.id = pi.product_id AND pi.is_main = 1
            WHERE p.category_id = ?
              AND p.id != ?
              AND p.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT 6
        ");
        $relatedStmt->execute([
            $product['category_id'],
            $product['id']
        ]);
        $related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    error_log("Product page error: " . $e->getMessage());
    $product = null;
    $related = [];
}

require_once '../includes/header.php';
?>
<style>
/* Product Page Styles */
.product-not-found {
    text-align: center;
    padding: 4rem 0;
    color: var(--text-light);
}

.product-not-found h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.product-not-found a {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: var(--radius);
    font-weight: var(--fw-bold);
    transition: all var(--transition);
}

.product-not-found a:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* Breadcrumb */
.breadcrumb {
    margin-bottom: 2rem;
    font-size: 0.95rem;
    color: var(--text-light);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.breadcrumb a {
    color: var(--primary);
    text-decoration: none;
    transition: color var(--transition);
}

.breadcrumb a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.breadcrumb span {
    color: var(--text);
    font-weight: var(--fw-medium);
}

/* Product Grid */
.product-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 4rem;
}

@media (max-width: 992px) {
    .product-detail-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}

/* Product Images */
.main-image-container {
    background: var(--white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1rem;
    height: 460px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--border);
    transition: all var(--transition);
    position: relative;
}

.main-image-container:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-md);
}

.main-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.main-image-container:hover .main-image {
    transform: scale(1.02);
}

.image-zoom {
    position: absolute;
    bottom: 1rem;
    right: 1rem;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: var(--radius);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity var(--transition);
}

.main-image-container:hover .image-zoom {
    opacity: 1;
}

/* Thumbnails */
.thumbnails-container {
    display: flex;
    gap: 0.8rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
}

.thumbnails-container::-webkit-scrollbar {
    height: 6px;
}

.thumbnails-container::-webkit-scrollbar-track {
    background: var(--bg);
    border-radius: 3px;
}

.thumbnails-container::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.thumbnails-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-light);
}

.thumbnail {
    width: 80px;
    height: 80px;
    background: var(--white);
    border-radius: var(--radius);
    cursor: pointer;
    border: 2px solid transparent;
    flex-shrink: 0;
    overflow: hidden;
    transition: all var(--transition);
    position: relative;
}

.thumbnail:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

.thumbnail.active {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Product Info */
.product-info h1 {
    font-size: 1.95rem;
    margin-bottom: 0.8rem;
    line-height: 1.3;
    color: var(--text);
    font-weight: var(--fw-extrabold);
}

.category-info {
    margin-bottom: 1.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.category-label {
    color: var(--text-light);
}

.category-link {
    color: var(--primary);
    font-weight: var(--fw-bold);
    text-decoration: none;
    transition: color var(--transition);
}

.category-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Price */
.price-container {
    margin: 1.5rem 0;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.current-price {
    font-size: 2.4rem;
    font-weight: var(--fw-extrabold);
    color: var(--danger);
}

.old-price {
    font-size: 1.3rem;
    color: var(--text-lighter);
    text-decoration: line-through;
}

.discount-badge {
    background: linear-gradient(135deg, var(--danger), #ef4444);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    font-weight: var(--fw-bold);
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
}

/* Rating */
.rating-container {
    margin: 1.2rem 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.star {
    color: #fbbf24;
}

.rating-text {
    margin-left: 0.6rem;
    color: var(--text);
}

/* Stock & Shipping */
.stock-shipping-container {
    margin: 1.5rem 0;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(209, 250, 229, 0.3), rgba(236, 253, 245, 0.3));
    border: 2px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--radius);
}

.stock-status {
    color: var(--success);
    font-weight: var(--fw-bold);
    margin-bottom: 0.5rem;
}

.shipping-info {
    color: var(--text);
    font-size: 0.95rem;
}

/* Quantity & Cart */
.quantity-cart-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 2rem 0;
    flex-wrap: wrap;
}

.quantity-selector {
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    background: var(--white);
    box-shadow: var(--shadow-sm);
}

.qty-btn {
    width: 48px;
    height: 48px;
    background: var(--bg);
    border: none;
    font-size: 1.3rem;
    cursor: pointer;
    color: var(--text);
    transition: all var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.qty-btn:hover {
    background: var(--primary-light);
    color: white;
}

.qty-input {
    width: 70px;
    text-align: center;
    border: none;
    font-size: 1.1rem;
    font-weight: var(--fw-bold);
    color: var(--text);
    background: var(--white);
    padding: 0.8rem 0;
}

.add-to-cart-btn {
    flex: 1;
    padding: 1.1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-size: 1.1rem;
    font-weight: var(--fw-bold);
    cursor: pointer;
    transition: all var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);
}

.add-to-cart-btn:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
}

/* Tabs */
.product-tabs {
    margin-top: 2.5rem;
}

.tabs-header {
    border-bottom: 2px solid var(--border);
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.tab-btn {
    padding: 0.8rem 0;
    font-size: 1.1rem;
    font-weight: var(--fw-bold);
    border: none;
    background: none;
    color: var(--text-light);
    cursor: pointer;
    transition: all var(--transition);
    position: relative;
}

.tab-btn:hover {
    color: var(--primary);
}

.tab-btn.active {
    color: var(--primary);
    border-bottom: 3px solid var(--primary);
}

.tab-content {
    line-height: 1.8;
    color: var(--text);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.feature-list {
    margin-top: 1rem;
    padding-left: 1.5rem;
}

.feature-list li {
    margin-bottom: 0.5rem;
    color: var(--text);
}

/* Related Products */
.related-products-section {
    margin-top: 4rem;
}

.section-title {
    font-size: 2rem;
    margin-bottom: 1.8rem;
    text-align: center;
    color: var(--text);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.5rem;
}

.product-card {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.22s;
    border: 2px solid transparent;
}

.product-card:hover {
    border-color: var(--primary-light);
    transform: translateY(-6px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
}

.product-image-container {
    height: 220px;
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.product-image-container img {
    transition: transform 0.3s ease;
}

.product-card:hover .product-image-container img {
    transform: scale(1.05);
}

.product-info-card {
    padding: 1rem;
}

.product-title {
    font-size: 1.05rem;
    margin-bottom: 0.5rem;
    height: 2.8em;
    overflow: hidden;
    color: var(--text);
    font-weight: var(--fw-bold);
    line-height: 1.4;
}

.product-price-card {
    font-size: 1.2rem;
    font-weight: var(--fw-extrabold);
    color: var(--danger);
}

/* Notifications */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    background: var(--success);
    color: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
    max-width: 400px;
}

.notification.error {
    background: var(--danger);
}

.notification.info {
    background: var(--primary);
}

.notification.warning {
    background: var(--warning);
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
}

/* Responsive */
@media (max-width: 768px) {
    .product-detail-grid {
        gap: 1.5rem;
    }
    
    .main-image-container {
        height: 350px;
    }
    
    .product-info h1 {
        font-size: 1.6rem;
    }
    
    .current-price {
        font-size: 2rem;
    }
    
    .quantity-cart-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .add-to-cart-btn {
        width: 100%;
    }
    
    .tabs-header {
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .tab-btn {
        flex: 1;
        min-width: 100px;
        text-align: center;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .main-image-container {
        height: 280px;
    }
    
    .thumbnail {
        width: 60px;
        height: 60px;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="container" style="padding: var(--space-xl) 0; margin:10px;">

  <?php if (!$product): ?>
    <div style="text-align:center; padding:4rem 0; color:var(--text-light);">
      <h2>Product not found</h2>
      <p>Slug requested: <code><?= htmlspecialchars($slug) ?></code></p>
      <a href="<?= BASE_URL ?>pages/products.php" style="
        display: inline-block;
        margin-top: 1rem;
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: white;
        text-decoration: none;
        border-radius: var(--radius);
        font-weight: var(--fw-bold);
        transition: all var(--transition);
      ">Back to shop</a>
    </div>
  <?php else: ?>

    <!-- DEBUG: Show product info -->
    <!-- <div style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px;">
      <strong>DEBUG:</strong> Product found: <?= htmlspecialchars($product['name']) ?> (ID: <?= $product['id'] ?>)
    </div> -->

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <a href="<?= BASE_URL ?>">Home</a> /
      <a href="<?= BASE_URL ?>pages/products.php">Products</a> /
      <span><?= htmlspecialchars($product['name']) ?></span>
    </nav>

    <div class="product-detail-grid">

      <!-- Left: Images -->
      <div>
        <div class="main-image-container">
          <?php 
          // Check for image in database
          $image_url = '';
          if (!empty($product['main_image'])) {
            $image_url = BASE_URL . 'uploads/products/' . $product['main_image'];
          } else {
            $image_url = BASE_URL . 'assets/images/no-image.png';
          }
          ?>
          <img src="<?= $image_url ?>" 
               alt="<?= htmlspecialchars($product['name']) ?>" 
               class="main-image"
               id="mainProductImage"
               onerror="this.src='https://via.placeholder.com/600x600?text=No+Image+Available'">
          <div class="image-zoom">
            <i class="fas fa-search-plus"></i> Hover to zoom
          </div>
        </div>

        <!-- Thumbnails -->
        <div class="thumbnails-container">
          <?php if (!empty($image_url)): ?>
            <div class="thumbnail active" 
                 data-image="<?= $image_url ?>">
              <img src="<?= $image_url ?>" 
                   alt="<?= htmlspecialchars($product['name']) ?>"
                   class="thumbnail-image"
                   onerror="this.src='https://via.placeholder.com/80?text=Img'">
            </div>
          <?php endif; ?>
         <?php 
          // Add this above the thumbnails foreach
if (!isset($product_images) || !is_array($product_images)) {
    $product_images = [];
}
foreach ($product_images as $index => $img): 
    $img_url = BASE_URL . 'uploads/products/' . $img;
?>
    <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
         data-image="<?= $img_url ?>">
        <img src="<?= $img_url ?>" 
             alt="<?= htmlspecialchars($product['name']) ?>">
    </div>
<?php endforeach; ?>
        </div>
      </div>

      <!-- Right: Info -->
      <div class="product-info">
        <h1><?= htmlspecialchars($product['name']) ?></h1>

        <div class="category-info">
          <span class="category-label">Category:</span>
          <?php if (!empty($product['category_name'])): ?>
            <a href="<?= BASE_URL ?>pages/products.php?category=<?= urlencode($product['category_name']) ?>" 
               class="category-link"><?= htmlspecialchars($product['category_name']) ?></a>
          <?php else: ?>
            <span class="category-link">General</span>
          <?php endif; ?>
          
          <?php if (!empty($product['brand'])): ?>
            <span style="margin-left: 1rem; color: var(--text-light);">
              <i class="fas fa-tag"></i> Brand: <?= htmlspecialchars($product['brand']) ?>
            </span>
          <?php endif; ?>
        </div>

        <!-- Price -->
        <?php
          $final_price = $product['discount_price'] ?? $product['price'];
          $old_price   = $product['discount_price'] ? $product['price'] : null;
          $savings = $old_price ? $old_price - $final_price : 0;
        ?>
        <div class="price-container">
          <span class="current-price">₦<?= number_format($final_price, 2) ?></span>
          <?php if ($old_price && $old_price > $final_price): ?>
            <del class="old-price">₦<?= number_format($old_price, 2) ?></del>
            <span class="discount-badge">
              Save ₦<?= number_format($savings, 2) ?> (<?= round((($old_price - $final_price) / $old_price) * 100) ?>% OFF)
            </span>
          <?php endif; ?>
        </div>

        <!-- SKU & Stock -->
        <div style="margin: 1rem 0; padding: 0.75rem; background: var(--bg); border-radius: var(--radius);">
          <div style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
            <?php if (!empty($product['sku'])): ?>
              <div>
                <span style="color: var(--text-light); font-size: 0.9rem;">SKU:</span>
                <strong style="color: var(--text);"><?= htmlspecialchars($product['sku']) ?></strong>
              </div>
            <?php endif; ?>
            
            <div>
              <span style="color: var(--text-light); font-size: 0.9rem;">Stock:</span>
              <strong style="color: <?= $product['stock'] > 5 ? 'var(--success)' : ($product['stock'] > 0 ? 'var(--warning)' : 'var(--danger)') ?>;">
                <?= $product['stock'] ?> units
              </strong>
            </div>
            
            <?php if (!empty($product['model'])): ?>
              <div>
                <span style="color: var(--text-light); font-size: 0.9rem;">Model:</span>
                <strong style="color: var(--text);"><?= htmlspecialchars($product['model']) ?></strong>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Rating -->
        <div class="rating-container">
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star star"></i>
          <i class="fas fa-star-half-alt star"></i>
          <span class="rating-text">4.8 (1,234 reviews)</span>
        </div>

        <!-- Stock & Shipping -->
        <?php
          $stock = $product['stock'] ?? 0;
          $stock_class = $stock > 10 ? 'success' : ($stock > 0 ? 'warning' : 'danger');
          $stock_text = $stock > 10 ? 'In Stock' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
        ?>
        <div class="stock-shipping-container">
          <div class="stock-status" style="color: var(--<?= $stock_class ?>);">
            <i class="fas fa-<?= $stock > 0 ? 'check-circle' : 'times-circle' ?>"></i>
            <?= $stock_text ?> (<?= $stock ?> items left)
          </div>
          <div class="shipping-info">
            <?php if ($final_price >= 50000): ?>
              <i class="fas fa-shipping-fast"></i> Free shipping
            <?php else: ?>
              <i class="fas fa-truck"></i> Standard shipping: ₦1,500
            <?php endif; ?>
            • Estimated delivery: 2–5 business days
          </div>
        </div>

        <!-- Short Description -->
        <?php if (!empty($product['short_description'])): ?>
          <div style="margin: 1.5rem 0; padding: 1rem; background: var(--white); border: 1px solid var(--border); border-radius: var(--radius);">
            <p style="color: var(--text); line-height: 1.6; margin: 0;">
              <?= nl2br(htmlspecialchars($product['short_description'])) ?>
            </p>
          </div>
        <?php endif; ?>

        <!-- Quantity & Cart -->
        <div class="quantity-cart-container">
          <div class="quantity-selector">
            <button class="qty-btn" id="decreaseQty" <?= $stock <= 0 ? 'disabled' : '' ?>>-</button>
            <input type="number" class="qty-input" id="quantityInput" 
                   value="1" min="1" max="<?= $stock ?>" 
                   <?= $stock <= 0 ? 'disabled' : '' ?> readonly>
            <button class="qty-btn" id="increaseQty" <?= $stock <= 0 ? 'disabled' : '' ?>>+</button>
          </div>

          <button class="add-to-cart-btn" id="addToCartBtn" 
                  <?= $stock <= 0 ? 'disabled style="background: var(--text-lighter); cursor: not-allowed;"' : '' ?>>
            <?php if ($stock <= 0): ?>
              <i class="fas fa-times"></i> Out of Stock
            <?php else: ?>
              <i class="fas fa-cart-plus"></i> Add to Cart
            <?php endif; ?>
          </button>
        </div>

        <!-- Additional action buttons -->
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
          <button style="
            padding: 0.75rem 1.5rem;
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-weight: var(--fw-bold);
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
          " onclick="addToWishlist(<?= $product['id'] ?>)">
            <i class="far fa-heart"></i> Wishlist
          </button>
          
          <button style="
            padding: 0.75rem 1.5rem;
            background: var(--white);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            font-weight: var(--fw-bold);
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
          " onclick="compareProduct(<?= $product['id'] ?>)">
            <i class="fas fa-exchange-alt"></i> Compare
          </button>
        </div>

        <!-- Tabs -->
        <div class="product-tabs">
          <div class="tabs-header">
            <button class="tab-btn active" data-tab="description">Description</button>
            <button class="tab-btn" data-tab="specifications">Specifications</button>
            <button class="tab-btn" data-tab="reviews">Reviews (1,234)</button>
          </div>

          <div class="tab-content active" id="descriptionTab">
            <p><?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?></p>
            <ul class="feature-list">
              <li>High-quality materials and construction</li>
              <li>Fast nationwide delivery</li>
              <li>Secure payment via Paystack</li>
              <li>30-day return policy</li>
              <li>1-year manufacturer warranty</li>
            </ul>
          </div>

          <div class="tab-content" id="specificationsTab" style="display: none;">
            <table style="width: 100%; border-collapse: collapse;">
              <?php
              $specs = [
                  'Brand' => $product['brand'] ?? 'Infinix',
                  'Model' => $product['model'] ?? 'Hot 40',
                  'SKU' => $product['sku'] ?? 'SKU002',
                  'UPC' => $product['upc'] ?? '13dfg',
                  'EAN' => $product['ean'] ?? 'Ggvv',
                  'Price' => '₦' . number_format($product['price'], 2),
                  'Discount Price' => $product['discount_price'] ? '₦' . number_format($product['discount_price'], 2) : 'None',
                  'Stock' => $product['stock'] . ' units',
                  'Weight' => $product['weight'] ?: 'Not specified',
                  'Dimensions' => $product['dimensions'] ?: 'Not specified',
                  'Taxable' => $product['taxable'] ? 'Yes' : 'No',
                  'Virtual Product' => $product['is_virtual'] ? 'Yes' : 'No',
                  'Downloadable' => $product['downloadable'] ? 'Yes' : 'No'
              ];
              foreach ($specs as $key => $value):
                if (!empty($value) && $value !== 'Not specified'):
              ?>
                  <tr>
                    <td style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); width: 40%;">
                      <strong><?= htmlspecialchars($key) ?></strong>
                    </td>
                    <td style="padding: 0.75rem 0; border-bottom: 1px solid var(--border); color: var(--text-light);">
                      <?= htmlspecialchars($value) ?>
                    </td>
                  </tr>
              <?php 
                endif;
              endforeach; 
              ?>
            </table>
          </div>

          <div class="tab-content" id="reviewsTab" style="display: none;">
            <div style="background: var(--bg); padding: 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem;">
              <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div style="font-size: 2.5rem; font-weight: var(--fw-extrabold); color: var(--text);">4.8</div>
                <div>
                  <div style="display: flex; gap: 0.25rem; margin-bottom: 0.25rem;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star" style="color: #fbbf24;"></i>
                    <?php endfor; ?>
                  </div>
                  <div style="color: var(--text-light); font-size: 0.9rem;">
                    Based on 1,234 reviews
                  </div>
                </div>
              </div>
            </div>

            <div style="text-align: center; padding: 2rem; background: var(--white); border-radius: var(--radius); border: 2px dashed var(--border);">
              <i class="fas fa-comments" style="font-size: 2rem; color: var(--primary-light); margin-bottom: 1rem; display: block;"></i>
              <p style="color: var(--text-light); margin-bottom: 1rem;">
                Customer reviews will appear here. Be the first to review this product!
              </p>
              <button onclick="showReviewForm()" style="
                padding: 0.75rem 1.5rem;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: var(--radius);
                font-weight: var(--fw-bold);
                cursor: pointer;
                transition: all var(--transition);
              ">
                <i class="fas fa-pen"></i> Write a Review
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
      <section class="related-products-section">
        <h2 class="section-title">You May Also Like</h2>

        <div class="products-grid">
          <?php foreach ($related as $r): 
            $r_final_price = $r['discount_price'] ?? $r['price'];
            $r_old_price = $r['discount_price'] ? $r['price'] : null;
            $r_discount = $r_old_price ? round((($r_old_price - $r_final_price) / $r_old_price) * 100) : 0;
          ?>
            <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($r['slug']) ?>" class="product-card">
              <?php if ($r_discount > 0): ?>
                <div style="position: absolute; top: 10px; left: 10px; background: var(--danger); color: white; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.8rem; font-weight: var(--fw-bold); z-index: 2;">
                  <?= $r_discount ?>% OFF
                </div>
              <?php endif; ?>
              
              <div class="product-image-container">
                <img src="<?= $r['main_image'] 
                    ? BASE_URL . 'uploads/products/' . $r['main_image']
                    : BASE_URL . 'assets/images/no-image.png' ?>"
                     alt="<?= htmlspecialchars($r['name']) ?>">
              </div>
              <div class="product-info-card">
                <h3 class="product-title"><?= htmlspecialchars($r['name']) ?></h3>
                <div class="product-price-card">
                  <?php if ($r_old_price): ?>
                    <del style="font-size: 0.9rem; color: var(--text-lighter); margin-right: 0.5rem;">
                      ₦<?= number_format($r_old_price) ?>
                    </del>
                  <?php endif; ?>
                  ₦<?= number_format($r_final_price) ?>
                </div>
                <div style="display: flex; align-items: center; gap: 0.25rem; margin-top: 0.5rem;">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star" style="color: #fbbf24; font-size: 0.8rem;"></i>
                  <?php endfor; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  <?php endif; ?>

</main>
<script>
// ====================
// GLOBAL FUNCTIONS (Available everywhere)
// ====================
// These functions need to be available globally for inline onclick handlers
function showReviewForm() {
    // [Keep your existing showReviewForm code exactly as is]
    // Create modal for review form
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        padding: 1rem;
    `;
    
    modal.innerHTML = `
        <div style="
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.5rem; color: var(--text);">Write a Review</h3>
                <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    color: var(--text-light);
                    cursor: pointer;
                    padding: 0.5rem;
                ">&times;</button>
            </div>
            
            <form id="reviewForm">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: var(--fw-bold); color: var(--text);">
                        Your Rating
                    </label>
                    <div class="star-rating" style="display: flex; flex-direction: row-reverse; justify-content: flex-end;">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" style="display: none;">
                            <label for="star<?= $i ?>" style="
                                font-size: 2rem;
                                color: var(--border);
                                cursor: pointer;
                                padding: 0 0.25rem;
                                transition: color var(--transition);
                            ">&#9733;</label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: var(--fw-bold); color: var(--text);">
                        Your Review
                    </label>
                    <textarea style="
                        width: 100%;
                        padding: 0.75rem;
                        border: 2px solid var(--border);
                        border-radius: var(--radius);
                        font-family: var(--font-base);
                        min-height: 150px;
                        resize: vertical;
                    " placeholder="Share your experience with this product..."></textarea>
                </div>
                
                <button type="submit" style="
                    width: 100%;
                    padding: 1rem;
                    background: var(--primary);
                    color: white;
                    border: none;
                    border-radius: var(--radius);
                    font-weight: var(--fw-bold);
                    cursor: pointer;
                    transition: all var(--transition);
                ">
                    <i class="fas fa-paper-plane"></i> Submit Review
                </button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add star rating interaction
    const stars = modal.querySelectorAll('.star-rating label');
    stars.forEach(star => {
        star.addEventListener('mouseover', function() {
            const rating = this.getAttribute('for').replace('star', '');
            highlightStars(rating);
        });
        
        star.addEventListener('click', function() {
            const rating = this.getAttribute('for').replace('star', '');
            highlightStars(rating);
        });
    });
    
    function highlightStars(rating) {
        stars.forEach(star => {
            const starValue = star.getAttribute('for').replace('star', '');
            star.style.color = starValue <= rating ? '#fbbf24' : 'var(--border)';
        });
    }
    
    // Handle form submission
    modal.querySelector('#reviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const rating = this.querySelector('input[name="rating"]:checked');
        const review = this.querySelector('textarea').value;
        
        if (!rating) {
            showNotification('error', 'Please select a rating');
            return;
        }
        
        if (!review.trim()) {
            showNotification('error', 'Please write your review');
            return;
        }
        
        // Simulate API call
        showNotification('success', 'Thank you for your review! It will be published after moderation.');
        modal.remove();
    });
}

// ====================
// MAIN PRODUCT PAGE CODE
// ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product page initialized');
    
    // ====================
    // 1. IMAGE GALLERY
    // ====================
    const mainImage = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            const imageUrl = this.getAttribute('data-image');
            const thumbnailImg = this.querySelector('.thumbnail-image');
            
            // Update main image
            if (mainImage && imageUrl) {
                mainImage.src = imageUrl;
                mainImage.alt = thumbnailImg.alt;
            }
            
            // Update active thumbnail
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Image zoom effect
    if (mainImage) {
        mainImage.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.transition = 'transform 0.3s ease';
        });
        
        mainImage.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    }
    
    // ====================
    // 2. QUANTITY SELECTOR
    // ====================
    const quantityInput = document.getElementById('quantityInput');
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');
    
    function updateQuantity(change) {
        if (!quantityInput) return;
        
        let value = parseInt(quantityInput.value) + change;
        const max = parseInt(quantityInput.max) || 999;
        const min = parseInt(quantityInput.min) || 1;
        
        if (value < min) value = min;
        if (value > max) value = max;
        
        quantityInput.value = value;
        
        // Update button text if quantity > 1
        updateCartButtonText(value);
    }
    
    function updateCartButtonText(quantity) {
        const button = document.getElementById('addToCartBtn');
        if (button && quantity > 1) {
            button.innerHTML = `<i class="fas fa-cart-plus"></i> Add ${quantity} to Cart`;
        } else if (button) {
            button.innerHTML = `<i class="fas fa-cart-plus"></i> Add to Cart`;
        }
    }
    
    if (decreaseBtn) {
        decreaseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            updateQuantity(-1);
        });
    }
    
    if (increaseBtn) {
        increaseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            updateQuantity(1);
        });
    }
    
    // Prevent quantity input manual changes
    if (quantityInput) {
        quantityInput.addEventListener('keydown', function(e) {
            e.preventDefault();
        });
        
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            const max = parseInt(this.max) || 999;
            const min = parseInt(this.min) || 1;
            
            if (isNaN(value) || value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
            updateCartButtonText(value);
        });
    }
    
    // ====================
    // 3. TAB SWITCHING
    // ====================
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab') + 'Tab';
            
            // Remove active class from all tabs
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => {
                content.classList.remove('active');
                content.style.display = 'none';
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.style.display = 'block';
                setTimeout(() => {
                    activeTab.classList.add('active');
                }, 10);
            }
        });
    });
    
// ====================
// 4. ADD TO CART FUNCTIONALITY (DEBUG VERSION)
// ====================
const addToCartBtn = document.getElementById('addToCartBtn');

if (addToCartBtn) {
    addToCartBtn.addEventListener('click', async function(e) {
        e.preventDefault();

        const quantity = parseInt(quantityInput.value) || 1;
        const productId = <?= $product['id'] ?? 0 ?>;
        const productName = "<?= addslashes($product['name'] ?? '') ?>";

        console.log("=== ADD TO CART DEBUG ===");
        console.log("Product ID:", productId);
        console.log("Quantity:", quantity);
        console.log("Product Name:", productName);

        if (!productId) {
            showNotification('error', 'Product information not found');
            return;
        }

        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        this.disabled = true;

        try {
            // Option 1: Simple URL-encoded data
            const data = new URLSearchParams();
            data.append('product_id', productId);
            data.append('quantity', quantity);
            
            console.log("Sending data:", data.toString());
            console.log("URL:", 'add_to_cart.php');
            
            // Make the request
            const response = await fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json'
                },
                body: data
            });
            
            console.log("Response status:", response.status);
            console.log("Response OK:", response.ok);
            
            // Get response text first
            const responseText = await response.text();
            console.log("Raw response:", responseText);
            
            // Try to parse as JSON
            let jsonData;
            try {
                jsonData = JSON.parse(responseText);
                console.log("Parsed JSON:", jsonData);
            } catch (parseError) {
                console.error("Failed to parse JSON:", parseError);
                console.error("Response was not JSON:", responseText);
                throw new Error("Server returned non-JSON response");
            }
            
            if (jsonData.success) {
                showNotification('success', jsonData.message || `Added ${quantity} item(s) to cart`);
                updateHeaderCartCount(quantity);

                this.innerHTML = '<i class="fas fa-check"></i> Added to Cart';
                this.style.background = 'linear-gradient(135deg, var(--success), #10b981)';

                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
            } else {
                showNotification('error', jsonData.message || 'Failed to add product to cart.');
                this.innerHTML = originalText;
                this.disabled = false;
            }
            
        } catch (err) {
            console.error("=== FETCH ERROR DETAILS ===");
            console.error("Error name:", err.name);
            console.error("Error message:", err.message);
            console.error("Error stack:", err.stack);
            
            // More specific error messages
            if (err.name === 'TypeError') {
                if (err.message.includes('Failed to fetch')) {
                    showNotification('error', 'Network error. Please check your connection and try again.');
                    console.log("Possible causes: CORS, wrong URL, server down");
                } else {
                    showNotification('error', 'Browser error. Please try a different browser.');
                }
            } else if (err.message.includes('non-JSON')) {
                showNotification('error', 'Server error. Please contact support.');
                console.log("Server is returning HTML/error page instead of JSON");
            } else {
                showNotification('error', err.message || 'An unexpected error occurred.');
            }
            
            this.innerHTML = originalText;
            this.disabled = false;
        }
    });
}
    // ====================
    // 5. NOTIFICATION SYSTEM
    // ====================
    function showNotification(type, message) {
        // Remove existing notifications
        document.querySelectorAll('.notification').forEach(n => n.remove());
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle',
            warning: 'fa-exclamation-triangle'
        };
        
        notification.innerHTML = `
            <i class="fas ${icons[type] || 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }
    
    // ====================
    // 6. UPDATE HEADER CART COUNT
    // ====================
    function updateHeaderCartCount(addedQuantity) {
        // Get current cart count from header
        const cartCountElements = document.querySelectorAll('#cart-count, #mobile-cart-count');
        let currentCount = 0;
        
        if (cartCountElements.length > 0) {
            currentCount = parseInt(cartCountElements[0].textContent) || 0;
        }
        
        // Calculate new count
        const newCount = currentCount + addedQuantity;
        
        // Update all cart count elements
        cartCountElements.forEach(element => {
            element.textContent = newCount;
            element.style.display = newCount > 0 ? 'flex' : 'none';
        });
        
        // Dispatch event for other parts of the app
        window.dispatchEvent(new CustomEvent('cartUpdate', {
            detail: { count: newCount, added: addedQuantity }
        }));
        
        // Store in localStorage for persistence
        localStorage.setItem('cartCount', newCount.toString());
    }
    
    // ====================
    // 7. RELATED PRODUCTS HOVER EFFECTS
    // ====================
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-6px)';
            this.style.boxShadow = '0 12px 24px rgba(0,0,0,0.12)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)';
        });
    });
    
    // ====================
    // 8. STOCK WARNING
    // ====================
    function checkStockWarning() {
        const stock = <?= $product['stock'] ?? 0 ?>;
        const quantity = parseInt(quantityInput.value) || 1;
        
        if (stock <= 10 && stock > 0) {
            // Low stock warning
            const stockContainer = document.querySelector('.stock-shipping-container');
            if (stockContainer && !document.getElementById('lowStockWarning')) {
                const warning = document.createElement('div');
                warning.id = 'lowStockWarning';
                warning.innerHTML = `
                    <div style="color: var(--warning); font-weight: var(--fw-bold); margin-top: 0.5rem;">
                        <i class="fas fa-exclamation-triangle"></i> Only ${stock} items left in stock!
                    </div>
                `;
                stockContainer.appendChild(warning);
            }
        }
        
        // Disable add to cart if out of stock
        if (stock === 0 && addToCartBtn) {
            addToCartBtn.disabled = true;
            addToCartBtn.innerHTML = '<i class="fas fa-times"></i> Out of Stock';
            addToCartBtn.style.background = 'var(--text-lighter)';
            addToCartBtn.style.cursor = 'not-allowed';
            
            // Update stock status
            const stockStatus = document.querySelector('.stock-status');
            if (stockStatus) {
                stockStatus.textContent = 'Out of Stock';
                stockStatus.style.color = 'var(--danger)';
            }
        }
    }
    
    // Check stock on load
    checkStockWarning();
    
    // ====================
    // 9. KEYBOARD SHORTCUTS
    // ====================
    document.addEventListener('keydown', function(e) {
        // '+' key to increase quantity
        if (e.key === '+' || e.key === '=') {
            e.preventDefault();
            updateQuantity(1);
        }
        
        // '-' key to decrease quantity
        if (e.key === '-' || e.key === '_') {
            e.preventDefault();
            updateQuantity(-1);
        }
        
        // 'A' key to add to cart
        if (e.key === 'a' || e.key === 'A') {
            if (addToCartBtn && !addToCartBtn.disabled) {
                e.preventDefault();
                addToCartBtn.click();
            }
        }
    });
    
    // ====================
    // 10. INITIALIZE & EXPORT FUNCTIONS
    // ====================
    console.log('Product page fully initialized');
    
    // Make these functions available globally
    window.showNotification = showNotification;
    window.updateQuantity = function(change) {
        updateQuantity(change);
    };
    window.updateHeaderCartCount = updateHeaderCartCount;
    window.checkStockWarning = checkStockWarning;
});

// ====================
// CSS ANIMATIONS (Add to your CSS file)
// ====================
/*
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 1000;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}

.notification.success {
    background: linear-gradient(135deg, var(--success), #10b981);
}

.notification.error {
    background: linear-gradient(135deg, var(--danger), #ef4444);
}

.notification.info {
    background: linear-gradient(135deg, var(--info), #3b82f6);
}

.notification.warning {
    background: linear-gradient(135deg, var(--warning), #f59e0b);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.btn.added {
    animation: pulse 0.5s ease;
    background: linear-gradient(135deg, var(--success), #10b981) !important;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(0.95); }
    100% { transform: scale(1); }
}
*/
// The rest of your existing JavaScript remains the same...
</script>

<?php require_once '../includes/footer.php'; ?>