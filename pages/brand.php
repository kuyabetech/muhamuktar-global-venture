<?php
// pages/brand.php - Single Brand Page

$page_title = "Brand";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get brand from URL
$brand = trim($_GET['brand'] ?? '');
if (empty($brand)) {
    header("Location: " . BASE_URL . "pages/brands.php");
    exit;
}

$decoded_brand = urldecode($brand);

// Get brand information and stats
try {
    // Brand overview
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            MIN(price) as min_price,
            MAX(price) as max_price,
            AVG(price) as avg_price,
            COUNT(DISTINCT category_id) as categories_count,
            SUM(stock) as total_stock,
            (SELECT COUNT(*) FROM order_items oi 
             JOIN products p2 ON oi.product_id = p2.id 
             WHERE p2.brand = ?) as total_sold
        FROM products
        WHERE brand = ? AND status = 'active'
    ");
    $stmt->execute([$decoded_brand, $decoded_brand]);
    $brand_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get categories for this brand
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.slug,
               COUNT(p.id) as product_count
        FROM categories c
        JOIN products p ON c.id = p.category_id
        WHERE p.brand = ? AND p.status = 'active' AND c.status = 'active'
        GROUP BY c.id, c.name, c.slug
        ORDER BY c.name
    ");
    $stmt->execute([$decoded_brand]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $brand_info = [
        'total_products' => 0,
        'min_price' => 0,
        'max_price' => 0,
        'avg_price' => 0,
        'categories_count' => 0,
        'total_stock' => 0,
        'total_sold' => 0
    ];
    $categories = [];
}

// Get filters
$category_id = (int)($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'popular';
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Build product query
$where = ["p.status = 'active'", "p.brand = ?"];
$params = [$decoded_brand];

if ($category_id > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
}

if ($min_price > 0) {
    $where[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where[] = "p.price <= ?";
    $params[] = $max_price;
}

// Get total products
$count_sql = "SELECT COUNT(*) FROM products p WHERE " . implode(" AND ", $where);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Build order by
switch ($sort) {
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
    case 'newest':
        $order_by = "p.created_at DESC";
        break;
    case 'rating':
        $order_by = "(SELECT AVG(rating) FROM reviews WHERE product_id = p.id) DESC";
        break;
    case 'popular':
        $order_by = "(SELECT COUNT(*) FROM order_items WHERE product_id = p.id) DESC";
        break;
    default:
        $order_by = "p.created_at DESC";
        break;
}

// Get products
$sql = "
    SELECT p.*, 
           c.name as category_name,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating,
           (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as sales_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order_by
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get price range for this brand
$price_range_sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products p WHERE brand = ? AND status = 'active'";
$stmt = $pdo->prepare($price_range_sql);
$stmt->execute([$decoded_brand]);
$price_range = $stmt->fetch();
$min_available = (float)$price_range['min_price'];
$max_available = (float)$price_range['max_price'];

// Get top selling products
$top_products_sql = "
    SELECT p.id, p.name, p.price, p.discount_price,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
           COUNT(oi.id) as times_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.brand = ? AND p.status = 'active'
    GROUP BY p.id, p.name, p.price, p.discount_price
    ORDER BY times_sold DESC
    LIMIT 5
";
$stmt = $pdo->prepare($top_products_sql);
$stmt->execute([$decoded_brand]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer reviews for this brand
$reviews_sql = "
    SELECT r.*, u.full_name as reviewer_name, p.name as product_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE p.brand = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 5
";
$stmt = $pdo->prepare($reviews_sql);
$stmt->execute([$decoded_brand]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<main class="main-content">
    <!-- Brand Header -->
    <section class="brand-header">
        <div class="container">
            <div class="brand-banner">
                <div class="brand-info">
                    <h1 class="brand-title"><?= htmlspecialchars($decoded_brand) ?></h1>
                    
                    <div class="brand-stats">
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($brand_info['total_products']) ?></span>
                            <span class="stat-label">Products</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($brand_info['categories_count']) ?></span>
                            <span class="stat-label">Categories</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">₦<?= number_format($brand_info['min_price']) ?></span>
                            <span class="stat-label">Starting Price</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?= number_format($brand_info['total_sold']) ?></span>
                            <span class="stat-label">Items Sold</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Navigation -->
    <?php if (!empty($categories)): ?>
        <section class="category-nav">
            <div class="container">
                <div class="category-slider">
                    <a href="?brand=<?= urlencode($brand) ?>" 
                       class="category-link <?= $category_id == 0 ? 'active' : '' ?>">
                        All Products
                    </a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?brand=<?= urlencode($brand) ?>&category=<?= $cat['id'] ?>" 
                           class="category-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                            <span class="count">(<?= $cat['product_count'] ?>)</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Main Content -->
    <section class="products-section">
        <div class="container">
            <div class="shop-layout">
                <!-- Sidebar -->
                <aside class="shop-sidebar">
                    <!-- Price Filter -->
                    <div class="filter-widget">
                        <h3 class="filter-title">Price Range</h3>
                        <div class="price-filter">
                            <div class="price-inputs">
                                <div class="price-field">
                                    <label>Min (₦)</label>
                                    <input type="number" id="min-price" 
                                           value="<?= $min_price ?: $min_available ?>" 
                                           min="<?= floor($min_available) ?>" 
                                           max="<?= ceil($max_available) ?>" 
                                           step="1000">
                                </div>
                                <div class="price-field">
                                    <label>Max (₦)</label>
                                    <input type="number" id="max-price" 
                                           value="<?= $max_price ?: $max_available ?>" 
                                           min="<?= floor($min_available) ?>" 
                                           max="<?= ceil($max_available) ?>" 
                                           step="1000">
                                </div>
                            </div>
                            <button onclick="applyPriceFilter()" class="btn-filter">
                                Apply Filter
                            </button>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <?php if (!empty($top_products)): ?>
                        <div class="filter-widget">
                            <h3 class="filter-title">Top Selling</h3>
                            <div class="top-products">
                                <?php foreach ($top_products as $product): ?>
                                    <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>" 
                                       class="top-product-item">
                                        <div class="top-product-image">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($product['image']) ?>" 
                                                     alt="<?= htmlspecialchars($product['name']) ?>">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="fas fa-image"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="top-product-info">
                                            <h4><?= htmlspecialchars($product['name']) ?></h4>
                                            <div class="price">
                                                <?php if ($product['discount_price']): ?>
                                                    <span class="current">₦<?= number_format($product['discount_price']) ?></span>
                                                    <span class="old">₦<?= number_format($product['price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current">₦<?= number_format($product['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="sold-count"><?= $product['times_sold'] ?> sold</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>

                <!-- Products Grid -->
                <div class="shop-content">
                    <!-- Shop Header -->
                    <div class="shop-header">
                        <div class="results-info">
                            <span class="results-count">
                                Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_products) ?> 
                                of <?= $total_products ?> products
                            </span>
                        </div>
                        
                        <div class="sort-options">
                            <label for="sort">Sort by:</label>
                            <select id="sort" onchange="changeSort(this.value)">
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <?php if (empty($products)): ?>
                        <div class="empty-products">
                            <i class="fas fa-box-open"></i>
                            <h3>No products found</h3>
                            <p>Try adjusting your filters</p>
                            <a href="?brand=<?= urlencode($brand) ?>" class="btn-clear">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($product['image']) ?>" 
                                                     alt="<?= htmlspecialchars($product['name']) ?>">
                                            <?php else: ?>
                                                <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNmM2Y0ZjYiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOWNhM2FmIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+" 
                                                     alt="No image">
                                            <?php endif; ?>
                                        </a>
                                        
                                        <?php if ($product['discount_price']): ?>
                                            <span class="discount-badge">
                                                -<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%
                                            </span>
                                        <?php endif; ?>
                                        
                                        <button class="wishlist-btn" onclick="toggleWishlist(<?= $product['id'] ?>)">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                                <?= htmlspecialchars($product['name']) ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="product-price">
                                            <?php if ($product['discount_price']): ?>
                                                <span class="current-price">₦<?= number_format($product['discount_price']) ?></span>
                                                <span class="old-price">₦<?= number_format($product['price']) ?></span>
                                            <?php else: ?>
                                                <span class="current-price">₦<?= number_format($product['price']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-rating">
                                            <?php
                                            $rating = round($product['avg_rating'] ?? 0);
                                            for ($i = 1; $i <= 5; $i++):
                                                if ($i <= $rating):
                                                    echo '<i class="fas fa-star"></i>';
                                                elseif ($i - 0.5 <= $rating):
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                else:
                                                    echo '<i class="far fa-star"></i>';
                                                endif;
                                            endfor;
                                            ?>
                                            <span class="review-count">(<?= $product['review_count'] ?>)</span>
                                        </div>
                                        
                                        <button class="add-to-cart-btn" onclick="addToCart(<?= $product['id'] ?>)">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?brand=<?= urlencode($brand) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&page=1" 
                                       class="page-link first">&laquo;</a>
                                    <a href="?brand=<?= urlencode($brand) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&page=<?= $page - 1 ?>" 
                                       class="page-link prev">&lsaquo;</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?brand=<?= urlencode($brand) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&page=<?= $i ?>" 
                                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?brand=<?= urlencode($brand) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&page=<?= $page + 1 ?>" 
                                       class="page-link next">&rsaquo;</a>
                                    <a href="?brand=<?= urlencode($brand) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&page=<?= $total_pages ?>" 
                                       class="page-link last">&raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Reviews -->
    <?php if (!empty($reviews)): ?>
        <section class="reviews-section">
            <div class="container">
                <h2 class="section-title">Customer Reviews for <?= htmlspecialchars($decoded_brand) ?></h2>
                <div class="reviews-grid">
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <span class="reviewer-name"><?= htmlspecialchars($review['reviewer_name'] ?? 'Anonymous') ?></span>
                                    <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                                </div>
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="review-product">on <a href="<?= BASE_URL ?>pages/product.php?id=<?= $review['product_id'] ?>">
                                <?= htmlspecialchars($review['product_name']) ?>
                            </a></p>
                            <p class="review-content"><?= htmlspecialchars($review['content']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Similar Brands -->
    <section class="similar-section">
        <div class="container">
            <h2 class="section-title">Other Popular Brands</h2>
            <?php
            // Get similar brands
            $similar_sql = "
                SELECT DISTINCT brand, COUNT(*) as product_count
                FROM products
                WHERE brand != ? 
                  AND brand IS NOT NULL 
                  AND brand != '' 
                  AND status = 'active'
                  AND category_id IN (
                      SELECT DISTINCT category_id 
                      FROM products 
                      WHERE brand = ? AND status = 'active'
                  )
                GROUP BY brand
                ORDER BY product_count DESC
                LIMIT 6
            ";
            $stmt = $pdo->prepare($similar_sql);
            $stmt->execute([$decoded_brand, $decoded_brand]);
            $similar_brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <?php if (!empty($similar_brands)): ?>
                <div class="similar-grid">
                    <?php foreach ($similar_brands as $similar): ?>
                        <a href="<?= BASE_URL ?>pages/products.php?brand=<?= urlencode($similar['brand']) ?>" class="similar-card">
                            <h3><?= htmlspecialchars($similar['brand']) ?></h3>
                            <p><?= $similar['product_count'] ?> products</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<style>
/* Brand Header */
.brand-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 3rem 0;
}

.brand-banner {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
}

.brand-info {
    padding: 2rem;
}

.brand-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 2rem;
    text-align: center;
}

.brand-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    max-width: 800px;
    margin: 0 auto;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

/* Category Navigation */
.category-nav {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 1rem 0;
    position: sticky;
    top: 70px;
    z-index: 100;
}

.category-slider {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
}

.category-slider::-webkit-scrollbar {
    height: 3px;
}

.category-slider::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.category-link {
    padding: 0.5rem 1rem;
    color: var(--text);
    text-decoration: none;
    white-space: nowrap;
    border-radius: 20px;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.category-link:hover {
    color: var(--primary);
    background: var(--bg);
}

.category-link.active {
    background: var(--primary);
    color: white;
}

.category-link .count {
    font-size: 0.8rem;
    opacity: 0.8;
}

/* Shop Layout */
.shop-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
    margin-top: 2rem;
}

/* Sidebar */
.shop-sidebar {
    position: sticky;
    top: 140px;
    height: fit-content;
}

.filter-widget {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-title {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border);
}

.price-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.price-field label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-light);
    margin-bottom: 0.25rem;
}

.price-field input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 6px;
}

.btn-filter {
    width: 100%;
    padding: 0.75rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-filter:hover {
    background: var(--primary-dark);
}

/* Top Products */
.top-products {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.top-product-item {
    display: flex;
    gap: 0.75rem;
    text-decoration: none;
    color: var(--text);
    padding: 0.5rem;
    border-radius: 8px;
    transition: background 0.3s;
}

.top-product-item:hover {
    background: var(--bg);
}

.top-product-image {
    width: 60px;
    height: 60px;
    flex-shrink: 0;
    background: var(--bg);
    border-radius: 8px;
    overflow: hidden;
}

.top-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.no-image {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-light);
}

.top-product-info {
    flex: 1;
}

.top-product-info h4 {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.top-product-info .price {
    margin-bottom: 0.25rem;
}

.top-product-info .current {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.9rem;
}

.top-product-info .old {
    font-size: 0.8rem;
    color: var(--text-light);
    text-decoration: line-through;
    margin-left: 0.25rem;
}

.sold-count {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Shop Header */
.shop-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.results-count {
    color: var(--text-light);
}

.sort-options {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-options select {
    padding: 0.5rem 2rem 0.5rem 1rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.product-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
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
}

.wishlist-btn {
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
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.wishlist-btn:hover {
    background: #fee2e2;
    color: var(--danger);
}

.product-info {
    padding: 1.5rem;
}

.product-title {
    font-size: 1rem;
    margin-bottom: 0.5rem;
    line-height: 1.4;
    height: 2.8em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.product-title a {
    color: var(--text);
    text-decoration: none;
}

.product-title a:hover {
    color: var(--primary);
}

.product-price {
    margin-bottom: 0.5rem;
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

.product-rating {
    margin-bottom: 1rem;
    color: #fbbf24;
}

.review-count {
    color: var(--text-light);
    font-size: 0.85rem;
    margin-left: 0.25rem;
}

.add-to-cart-btn {
    width: 100%;
    padding: 0.75rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.add-to-cart-btn:hover {
    background: var(--primary-dark);
}

/* Reviews Section */
.reviews-section {
    padding: 4rem 0;
    background: var(--bg);
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

.reviews-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
}

.review-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.reviewer-name {
    font-weight: 600;
    color: var(--text);
}

.review-date {
    font-size: 0.85rem;
    color: var(--text-light);
}

.review-rating {
    margin-bottom: 0.5rem;
    color: #fbbf24;
}

.review-product {
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.review-product a {
    color: var(--primary);
    text-decoration: none;
}

.review-product a:hover {
    text-decoration: underline;
}

.review-content {
    color: var(--text-light);
    line-height: 1.6;
}

/* Similar Section */
.similar-section {
    padding: 4rem 0;
    background: white;
}

.similar-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.similar-card {
    background: var(--bg);
    padding: 2rem;
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    text-align: center;
    transition: all 0.3s;
}

.similar-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    background: var(--primary);
    color: white;
}

.similar-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.similar-card p {
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Responsive */
@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .shop-layout {
        grid-template-columns: 1fr;
    }
    
    .shop-sidebar {
        position: static;
    }
    
    .reviews-grid {
        grid-template-columns: 1fr;
    }
    
    .similar-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .brand-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .brand-title {
        font-size: 2rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .similar-grid {
        grid-template-columns: 1fr;
    }
    
    .shop-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .category-slider {
        padding-bottom: 1rem;
    }
}
</style>

<script>
function applyPriceFilter() {
    const min = document.getElementById('min-price').value;
    const max = document.getElementById('max-price').value;
    const url = new URL(window.location.href);
    url.searchParams.set('min_price', min);
    url.searchParams.set('max_price', max);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function changeSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function toggleWishlist(productId) {
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
            const btn = event.currentTarget;
            if (data.added) {
                btn.innerHTML = '<i class="fas fa-heart" style="color: var(--danger);"></i>';
            } else {
                btn.innerHTML = '<i class="far fa-heart"></i>';
            }
            
            if (data.wishlist_count !== undefined) {
                window.dispatchEvent(new CustomEvent('wishlistUpdate', {
                    detail: {count: data.wishlist_count}
                }));
            }
        }
    });
}

function addToCart(productId) {
    <?php if (!is_logged_in()): ?>
        window.location.href = '<?= BASE_URL ?>pages/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    <?php endif; ?>
    
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
            alert('Product added to cart!');
            if (data.cart_count !== undefined) {
                window.dispatchEvent(new CustomEvent('cartUpdate', {
                    detail: {count: data.cart_count}
                }));
            }
        } else {
            alert(data.message || 'Error adding to cart');
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>