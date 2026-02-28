<?php
// pages/new-arrivals.php - New Arrivals Page

$page_title = "New Arrivals";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get filters
$sort = $_GET['sort'] ?? 'newest';
$category_id = (int)($_GET['category'] ?? 0);
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Days to consider as "new" (e.g., last 30 days)
$new_days = 30;

// Build query
$where = ["p.status = 'active'", "p.created_at >= DATE_SUB(NOW(), INTERVAL $new_days DAY)"];
$params = [];

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
    case 'popular':
        $order_by = "(SELECT COUNT(*) FROM order_items WHERE product_id = p.id) DESC";
        break;
    case 'discount':
        $order_by = "p.discount_price IS NOT NULL DESC, p.discount_price ASC";
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
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND reviews.status = 'approved') as review_count,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND reviews.status = 'approved') as avg_rating
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order_by
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.created_at >= DATE_SUB(NOW(), INTERVAL $new_days DAY) AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    HAVING product_count > 0
    ORDER BY c.name
")->fetchAll();

// Get price range for new arrivals
$price_range = $pdo->query("
    SELECT MIN(price) as min_price, MAX(price) as max_price
    FROM products
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $new_days DAY) AND status = 'active'
")->fetch();
$min_available = (float)$price_range['min_price'];
$max_available = (float)$price_range['max_price'];

// Get featured new arrivals
$featured = $pdo->query("
    SELECT p.*, 
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL $new_days DAY) 
    AND p.status = 'active'
    AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 4
")->fetchAll();
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">New Arrivals</h1>
            <p class="header-description">Discover the latest products added to our store in the last <?= $new_days ?> days</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">New Arrivals</span>
            </div>
        </div>
    </section>

    <!-- Featured New Arrivals -->
    <?php if (!empty($featured)): ?>
        <section class="featured-section">
            <div class="container">
                <h2 class="section-title">Featured New Arrivals</h2>
                <div class="featured-grid">
                    <?php foreach ($featured as $product): ?>
                        <div class="featured-card">
                            <div class="product-image">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                    <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image'] ?? 'no-image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>">
                                </a>
                                <span class="new-badge">New</span>
                                <?php if ($product['discount_price']): ?>
                                    <span class="discount-badge">
                                        -<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%
                                    </span>
                                <?php endif; ?>
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
                                <button class="quick-view-btn" onclick="quickView(<?= $product['id'] ?>)">
                                    Quick View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Main Content -->
    <section class="new-arrivals-section">
        <div class="container">
            <div class="shop-layout">
                <!-- Sidebar Filters -->
                <aside class="shop-sidebar">
                    <!-- Categories Filter -->
                    <div class="filter-widget">
                        <h3 class="filter-title">Categories</h3>
                        <div class="category-filter">
                            <a href="?<?= http_build_query(array_merge($_GET, ['category' => 0, 'page' => 1])) ?>" 
                               class="category-link <?= $category_id == 0 ? 'active' : '' ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $cat): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat['id'], 'page' => 1])) ?>" 
                                   class="category-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                    <span class="count">(<?= $cat['product_count'] ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Filter -->
                    <div class="filter-widget">
                        <h3 class="filter-title">Price Range</h3>
                        <div class="price-filter">
                            <div class="price-inputs">
                                <div class="price-field">
                                    <label>Min (₦)</label>
                                    <input type="number" id="min-price" value="<?= $min_price ?: $min_available ?>" 
                                           min="<?= floor($min_available) ?>" max="<?= ceil($max_available) ?>" step="1000">
                                </div>
                                <div class="price-field">
                                    <label>Max (₦)</label>
                                    <input type="number" id="max-price" value="<?= $max_price ?: $max_available ?>" 
                                           min="<?= floor($min_available) ?>" max="<?= ceil($max_available) ?>" step="1000">
                                </div>
                            </div>
                            <button onclick="applyPriceFilter()" class="btn-filter">Apply</button>
                        </div>
                    </div>

                    <!-- Sort Options (Mobile) -->
                    <div class="filter-widget mobile-only">
                        <h3 class="filter-title">Sort By</h3>
                        <select onchange="changeSort(this.value)" class="mobile-sort">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="discount" <?= $sort === 'discount' ? 'selected' : '' ?>>Biggest Discount</option>
                        </select>
                    </div>
                </aside>

                <!-- Products Grid -->
                <div class="shop-content">
                    <!-- Shop Header -->
                    <div class="shop-header">
                        <div class="results-info">
                            <span class="results-count">
                                Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_products) ?> 
                                of <?= $total_products ?> new products
                            </span>
                            <span class="new-badge-info">
                                <i class="fas fa-clock"></i> Last <?= $new_days ?> days
                            </span>
                        </div>
                        <div class="sort-options desktop-only">
                            <label for="sort">Sort by:</label>
                            <select id="sort" onchange="changeSort(this.value)">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="discount" <?= $sort === 'discount' ? 'selected' : '' ?>>Biggest Discount</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <?php if (empty($products)): ?>
                        <div class="empty-products">
                            <i class="fas fa-box-open"></i>
                            <h3>No New Arrivals Found</h3>
                            <p>Check back soon for new products</p>
                            <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">Browse All Products</a>
                        </div>
                    <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
                                <div class="product-card">
                                    <div class="product-image">
                                        <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image'] ?? 'no-image.jpg') ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        </a>
                                        <span class="new-badge">New</span>
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
                                        <?php if ($product['category_name']): ?>
                                            <div class="product-category">
                                                <a href="?category=<?= $product['category_id'] ?>">
                                                    <?= htmlspecialchars($product['category_name']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
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
                                            <i class="fas fa-cart-plus"></i>
                                            Add to Cart
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=1&sort=<?= $sort ?>&category=<?= $category_id ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
                                       class="page-link first">&laquo;</a>
                                    <a href="?page=<?= $page - 1 ?>&sort=<?= $sort ?>&category=<?= $category_id ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
                                       class="page-link prev">&lsaquo;</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?= $i ?>&sort=<?= $sort ?>&category=<?= $category_id ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
                                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&sort=<?= $sort ?>&category=<?= $category_id ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
                                       class="page-link next">&rsaquo;</a>
                                    <a href="?page=<?= $total_pages ?>&sort=<?= $sort ?>&category=<?= $category_id ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>" 
                                       class="page-link last">&raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter-section">
        <div class="container">
            <div class="newsletter-content">
                <h2>Never Miss a New Arrival!</h2>
                <p>Subscribe to get notified about new products and exclusive offers</p>
                <form class="newsletter-form" id="newsletterForm">
                    <input type="email" placeholder="Your email address" required>
                    <button type="submit">Subscribe</button>
                </form>
            </div>
        </div>
    </section>
</main>

<style>
/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 4rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.header-description {
    font-size: 1.1rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

/* Featured Section */
.featured-section {
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

.featured-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.featured-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s;
}

.featured-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

/* New Arrivals Section */
.new-arrivals-section {
    padding: 4rem 0;
    background: var(--bg);
}

.shop-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
}

/* Sidebar */
.shop-sidebar {
    position: sticky;
    top: 100px;
    height: fit-content;
}

.filter-widget {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-title {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border);
}

/* Category Filter */
.category-filter {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    color: var(--text);
    text-decoration: none;
    transition: color 0.3s;
}

.category-link:hover {
    color: var(--primary);
}

.category-link.active {
    color: var(--primary);
    font-weight: 600;
}

.category-link .count {
    color: var(--text-light);
    font-size: 0.9rem;
}

/* Price Filter */
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
    transition: all 0.3s;
}

.btn-filter:hover {
    background: var(--primary-dark);
}

/* Shop Header */
.shop-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.results-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.results-count {
    color: var(--text-light);
}

.new-badge-info {
    background: #fee2e2;
    color: #991b1b;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.new-badge-info i {
    margin-right: 0.25rem;
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
    height: 250px;
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

.new-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
}

.discount-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--danger);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
}

.wishlist-btn {
    position: absolute;
    bottom: 1rem;
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
    z-index: 2;
}

.wishlist-btn:hover {
    background: #fee2e2;
    color: var(--danger);
    transform: scale(1.1);
}

.product-info {
    padding: 1.5rem;
}

.product-category {
    margin-bottom: 0.5rem;
}

.product-category a {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.product-category a:hover {
    text-decoration: underline;
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

.product-rating {
    margin-bottom: 1rem;
    color: #fbbf24;
}

.product-rating .fa-star,
.product-rating .fa-star-half-alt {
    margin-right: 2px;
}

.review-count {
    color: var(--text-light);
    font-size: 0.85rem;
    margin-left: 0.5rem;
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
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.add-to-cart-btn:hover {
    background: var(--primary-dark);
}

.quick-view-btn {
    width: 100%;
    padding: 0.75rem;
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.quick-view-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Empty State */
.empty-products {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 12px;
}

.empty-products i {
    font-size: 4rem;
    color: var(--text-lighter);
    margin-bottom: 1rem;
}

.empty-products h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.empty-products p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 3rem;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
}

.page-link:hover,
.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.first,
.page-link.last,
.page-link.prev,
.page-link.next {
    width: auto;
    padding: 0 1rem;
}

/* Newsletter Section */
.newsletter-section {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 4rem 0;
    text-align: center;
}

.newsletter-content h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.newsletter-content p {
    margin-bottom: 2rem;
    opacity: 0.9;
}

.newsletter-form {
    display: flex;
    max-width: 500px;
    margin: 0 auto;
    gap: 1rem;
}

.newsletter-form input {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
}

.newsletter-form button {
    padding: 1rem 2rem;
    background: white;
    color: var(--primary);
    border: none;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.newsletter-form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .featured-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .shop-layout {
        grid-template-columns: 1fr;
    }
    
    .shop-sidebar {
        position: static;
        margin-bottom: 2rem;
    }
    
    .desktop-only {
        display: none;
    }
    
    .mobile-only {
        display: block;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .new-arrivals-section {
        padding: 2rem 0;
    }
    
    .products-grid,
    .featured-grid {
        grid-template-columns: 1fr;
    }
    
    .shop-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .newsletter-form {
        flex-direction: column;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .product-image {
        height: 200px;
    }
}

.mobile-only {
    display: none;
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

function quickView(productId) {
    // Implement quick view modal
    alert('Quick view for product ' + productId);
}

document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    alert('Thank you for subscribing! You will be notified about new arrivals.');
    this.reset();
});
</script>

<?php require_once '../includes/footer.php'; ?>