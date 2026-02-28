<?php
// pages/best-sellers.php - Best Sellers Page

$page_title = "Best Sellers";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get filters
$time_period = $_GET['period'] ?? 'month'; // week, month, year, all
$category_id = (int)($_GET['category'] ?? 0);
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Define time period for best sellers
switch ($time_period) {
    case 'week':
        $period_sql = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $period_title = "This Week";
        break;
    case 'month':
        $period_sql = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $period_title = "This Month";
        break;
    case 'year':
        $period_sql = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        $period_title = "This Year";
        break;
    default:
        $period_sql = "";
        $period_title = "All Time";
        break;
}

// Build category filter
$category_sql = "";
$params = [];
if ($category_id > 0) {
    $category_sql = "AND p.category_id = ?";
    $params[] = $category_id;
}

// Get total count
$count_sql = "
    SELECT COUNT(DISTINCT p.id)
    FROM products p
    WHERE p.status = 'active'
    AND EXISTS (
        SELECT 1 FROM order_items oi 
        JOIN orders o ON oi.order_id = o.id 
        WHERE oi.product_id = p.id 
        AND o.status NOT IN ('cancelled') 
        $period_sql
    )
    $category_sql
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Get best selling products
$sql = "
    SELECT p.*, 
           c.name as category_name,
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled') $period_sql
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    $category_sql
    GROUP BY p.id
    HAVING total_sold > 0
    ORDER BY total_sold DESC, total_revenue DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get top categories for best sellers
$categories_sql = "
    SELECT c.id, c.name, COUNT(DISTINCT p.id) as product_count, COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM categories c
    JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled') $period_sql
    WHERE p.status = 'active'
    GROUP BY c.id, c.name
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT 10
";
$top_categories = $pdo->query($categories_sql)->fetchAll();

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COALESCE(SUM(oi.quantity), 0) as total_units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled') $period_sql
    WHERE p.status = 'active'
";
$stats = $pdo->query($stats_sql)->fetch();

// Get top product (most sold)
$top_product_sql = "
    SELECT p.*, 
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status NOT IN ('cancelled') $period_sql
    WHERE p.status = 'active'
    GROUP BY p.id
    HAVING total_sold > 0
    ORDER BY total_sold DESC
    LIMIT 1
";
$top_product = $pdo->query($top_product_sql)->fetch();
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Best Sellers</h1>
            <p class="header-description">Discover the most popular products our customers love</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Best Sellers</span>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-chart-line"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= number_format($stats['total_products']) ?></span>
                        <span class="stat-label">Products Sold</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-boxes"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= number_format($stats['total_units_sold']) ?></span>
                        <span class="stat-label">Units Sold</span>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="stat-info">
                        <span class="stat-value">₦<?= number_format($stats['total_revenue']) ?></span>
                        <span class="stat-label">Total Revenue</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Top Product Spotlight -->
    <?php if ($top_product): ?>
        <section class="spotlight-section">
            <div class="container">
                <div class="spotlight-card">
                    <div class="spotlight-badge">#1 Best Seller</div>
                    <div class="spotlight-content">
                        <div class="spotlight-image">
                            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($top_product['image'] ?? 'no-image.jpg') ?>" 
                                 alt="<?= htmlspecialchars($top_product['name']) ?>">
                        </div>
                        <div class="spotlight-info">
                            <span class="spotlight-category">Top Rated</span>
                            <h2><?= htmlspecialchars($top_product['name']) ?></h2>
                            <div class="spotlight-stats">
                                <span><i class="fas fa-shopping-cart"></i> <?= number_format($top_product['total_sold']) ?> sold</span>
                                <span><i class="fas fa-star"></i> 4.8/5 (120 reviews)</span>
                            </div>
                            <div class="spotlight-price">
                                <?php if ($top_product['discount_price']): ?>
                                    <span class="current-price">₦<?= number_format($top_product['discount_price']) ?></span>
                                    <span class="old-price">₦<?= number_format($top_product['price']) ?></span>
                                <?php else: ?>
                                    <span class="current-price">₦<?= number_format($top_product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="spotlight-description">
                                <?= htmlspecialchars(substr($top_product['description'] ?? '', 0, 200)) ?>...
                            </p>
                            <div class="spotlight-actions">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $top_product['id'] ?>" class="btn-primary">
                                    View Product
                                </a>
                                <button class="btn-secondary" onclick="addToCart(<?= $top_product['id'] ?>)">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Filter Bar -->
    <section class="filter-bar">
        <div class="container">
            <div class="filter-options">
                <div class="period-filter">
                    <span>Time Period:</span>
                    <a href="?period=week" class="filter-link <?= $time_period === 'week' ? 'active' : '' ?>">This Week</a>
                    <a href="?period=month" class="filter-link <?= $time_period === 'month' ? 'active' : '' ?>">This Month</a>
                    <a href="?period=year" class="filter-link <?= $time_period === 'year' ? 'active' : '' ?>">This Year</a>
                    <a href="?period=all" class="filter-link <?= $time_period === 'all' ? 'active' : '' ?>">All Time</a>
                </div>
                
                <?php if (!empty($top_categories)): ?>
                    <div class="category-filter">
                        <span>Category:</span>
                        <a href="?period=<?= $time_period ?>" class="filter-link <?= $category_id == 0 ? 'active' : '' ?>">All</a>
                        <?php foreach ($top_categories as $cat): ?>
                            <a href="?period=<?= $time_period ?>&category=<?= $cat['id'] ?>" 
                               class="filter-link <?= $category_id == $cat['id'] ? 'active' : '' ?>">
                                <?= htmlspecialchars($cat['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Best Sellers Grid -->
    <section class="best-sellers-section">
        <div class="container">
            <h2 class="section-title"><?= $period_title ?> Best Sellers</h2>
            
            <?php if (empty($products)): ?>
                <div class="empty-products">
                    <i class="fas fa-chart-line"></i>
                    <h3>No Best Sellers Found</h3>
                    <p>Check back later for popular products</p>
                    <a href="<?= BASE_URL ?>pages/products.php" class="btn-primary">Browse All Products</a>
                </div>
            <?php else: ?>
                <!-- Products Grid -->
                <div class="products-grid">
                    <?php 
                    $rank = $offset + 1;
                    foreach ($products as $product): 
                    ?>
                        <div class="product-card">
                            <div class="rank-badge">#<?= $rank++ ?></div>
                            <div class="product-image">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                    <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image'] ?? 'no-image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>">
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
                                <?php if ($product['category_name']): ?>
                                    <div class="product-category">
                                        <a href="?category=<?= $product['category_id'] ?>&period=<?= $time_period ?>">
                                            <?= htmlspecialchars($product['category_name']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <h3 class="product-title">
                                    <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </a>
                                </h3>
                                
                                <div class="product-stats">
                                    <span class="sold-count">
                                        <i class="fas fa-shopping-cart"></i> <?= number_format($product['total_sold']) ?> sold
                                    </span>
                                </div>
                                
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
                            <a href="?page=1&period=<?= $time_period ?>&category=<?= $category_id ?>" 
                               class="page-link first">&laquo;</a>
                            <a href="?page=<?= $page - 1 ?>&period=<?= $time_period ?>&category=<?= $category_id ?>" 
                               class="page-link prev">&lsaquo;</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&period=<?= $time_period ?>&category=<?= $category_id ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&period=<?= $time_period ?>&category=<?= $category_id ?>" 
                               class="page-link next">&rsaquo;</a>
                            <a href="?page=<?= $total_pages ?>&period=<?= $time_period ?>&category=<?= $category_id ?>" 
                               class="page-link last">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Category Performance -->
    <?php if (!empty($top_categories)): ?>
        <section class="category-section">
            <div class="container">
                <h2 class="section-title">Top Performing Categories</h2>
                <div class="category-grid">
                    <?php foreach ($top_categories as $category): ?>
                        <a href="?category=<?= $category['id'] ?>&period=<?= $time_period ?>" class="category-card">
                            <div class="category-info">
                                <h3><?= htmlspecialchars($category['name']) ?></h3>
                                <p><?= number_format($category['product_count']) ?> products</p>
                            </div>
                            <div class="category-stats">
                                <span class="sold-count"><?= number_format($category['total_sold']) ?> sold</span>
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Why Shop Best Sellers -->
    <section class="benefits-section">
        <div class="container">
            <h2 class="section-title">Why Shop Best Sellers?</h2>
            <div class="benefits-grid">
                <div class="benefit-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Proven Quality</h3>
                    <p>Our best sellers are loved by thousands of customers - quality you can trust</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-star"></i>
                    <h3>Top Rated</h3>
                    <p>These products consistently receive high ratings from satisfied customers</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-truck"></i>
                    <h3>Fast Moving</h3>
                    <p>Popular items ship quickly - get them before they're out of stock</p>
                </div>
                <div class="benefit-card">
                    <i class="fas fa-gem"></i>
                    <h3>Best Value</h3>
                    <p>Great prices on products that deliver real value for money</p>
                </div>
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

/* Stats Section */
.stats-section {
    padding: 3rem 0;
    background: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.stat-card {
    background: var(--bg);
    padding: 2rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stat-card i {
    font-size: 3rem;
    color: var(--primary);
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
}

.stat-label {
    color: var(--text-light);
    font-size: 0.95rem;
}

/* Spotlight Section */
.spotlight-section {
    padding: 2rem 0 4rem;
    background: white;
}

.spotlight-card {
    background: linear-gradient(135deg, #f8f9fc, white);
    border-radius: 24px;
    padding: 3rem;
    position: relative;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    border: 1px solid var(--border);
}

.spotlight-badge {
    position: absolute;
    top: -15px;
    left: 30px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 0.75rem 2rem;
    border-radius: 50px;
    font-weight: 700;
    font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.spotlight-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
}

.spotlight-image {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.spotlight-image img {
    width: 100%;
    height: auto;
    display: block;
}

.spotlight-category {
    display: inline-block;
    background: #fee2e2;
    color: #991b1b;
    padding: 0.25rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.spotlight-info h2 {
    font-size: 2.2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.spotlight-stats {
    display: flex;
    gap: 2rem;
    margin-bottom: 1.5rem;
}

.spotlight-stats span {
    color: var(--text-light);
}

.spotlight-stats i {
    color: var(--primary);
    margin-right: 0.5rem;
}

.spotlight-price {
    margin-bottom: 1.5rem;
}

.spotlight-price .current-price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
}

.spotlight-price .old-price {
    font-size: 1.2rem;
    color: var(--text-light);
    text-decoration: line-through;
    margin-left: 1rem;
}

.spotlight-description {
    color: var(--text-light);
    line-height: 1.8;
    margin-bottom: 2rem;
}

.spotlight-actions {
    display: flex;
    gap: 1rem;
}

/* Filter Bar */
.filter-bar {
    background: white;
    padding: 1rem 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.filter-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.period-filter,
.category-filter {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.period-filter span,
.category-filter span {
    color: var(--text-light);
    font-weight: 600;
}

.filter-link {
    padding: 0.5rem 1rem;
    color: var(--text);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
}

.filter-link:hover {
    background: var(--bg);
    color: var(--primary);
}

.filter-link.active {
    background: var(--primary);
    color: white;
}

/* Best Sellers Section */
.best-sellers-section {
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

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.product-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
    position: relative;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.rank-badge {
    position: absolute;
    top: 1rem;
    left: 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 700;
    font-size: 1rem;
    z-index: 2;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
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

.discount-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--danger);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
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

.product-stats {
    margin-bottom: 0.75rem;
}

.sold-count {
    color: var(--text-light);
    font-size: 0.9rem;
}

.sold-count i {
    color: var(--primary);
    margin-right: 0.25rem;
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

/* Category Section */
.category-section {
    padding: 4rem 0;
    background: white;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.category-card {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.category-card:hover {
    transform: translateX(5px);
    background: white;
    border-color: var(--primary);
}

.category-info h3 {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.category-info p {
    color: var(--text-light);
    font-size: 0.9rem;
}

.category-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.sold-count {
    color: var(--primary);
    font-weight: 600;
}

.category-stats i {
    color: var(--text-light);
}

/* Benefits Section */
.benefits-section {
    padding: 4rem 0;
    background: var(--bg);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.benefit-card {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: transform 0.3s;
}

.benefit-card:hover {
    transform: translateY(-5px);
}

.benefit-card i {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.benefit-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.benefit-card p {
    color: var(--text-light);
    line-height: 1.6;
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

.btn-primary {
    display: inline-block;
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    text-decoration: none;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    padding: 1rem 2rem;
    background: white;
    color: var(--text);
    border: 2px solid var(--border);
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
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

/* Responsive */
@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .benefits-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .spotlight-content {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-options {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .category-grid {
        grid-template-columns: 1fr;
    }
    
    .benefits-grid {
        grid-template-columns: 1fr;
    }
    
    .spotlight-actions {
        flex-direction: column;
    }
    
    .period-filter,
    .category-filter {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .product-image {
        height: 200px;
    }
}
</style>

<script>
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