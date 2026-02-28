<?php
// pages/search.php - Search Results Page

$page_title = "Search Results";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get search query
$query = trim($_GET['q'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$sort = $_GET['sort'] ?? 'relevance';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Track search
if (!empty($query)) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS search_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                query VARCHAR(255) NOT NULL,
                results_count INT DEFAULT 0,
                user_id INT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_query (query),
                INDEX idx_created (created_at)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO search_log (query, user_id, ip_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $query, 
            $_SESSION['user_id'] ?? null, 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Log error but continue
    }
}

// Build search query
$where = ["p.status = 'active'"];
$params = [];

if (!empty($query)) {
    // Simple search - can be enhanced with FULLTEXT index
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)";
    $search_term = "%$query%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if ($category_id > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
}

// Get total results
$count_sql = "SELECT COUNT(*) FROM products p WHERE " . implode(" AND ", $where);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

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
        // Relevance - prioritize name matches
        $order_by = "CASE 
            WHEN p.name LIKE ? THEN 1
            WHEN p.brand LIKE ? THEN 2
            ELSE 3
        END, p.created_at DESC";
        // Add search term twice for the CASE statement
        if (!empty($query)) {
            $search_term_like = "%$query%";
            array_unshift($params, $search_term_like, $search_term_like);
        }
        break;
}

// Get results
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
$results = $stmt->fetchAll();

// Get categories for filter
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id, c.name
        HAVING product_count > 0
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {}

// Get popular searches
$popular_searches = [];
try {
    $stmt = $pdo->query("
        SELECT query, COUNT(*) as search_count
        FROM search_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY query
        ORDER BY search_count DESC
        LIMIT 10
    ");
    $popular_searches = $stmt->fetchAll();
} catch (Exception $e) {}

// Get suggested searches if no results
$suggestions = [];
if ($total_results == 0 && !empty($query)) {
    try {
        // Find similar terms
        $words = explode(' ', $query);
        $like_conditions = [];
        $suggest_params = [];
        
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $like_conditions[] = "name LIKE ?";
                $suggest_params[] = "%$word%";
            }
        }
        
        if (!empty($like_conditions)) {
            $suggest_sql = "
                SELECT DISTINCT name, 
                       (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
                FROM products p
                WHERE " . implode(" OR ", $like_conditions) . "
                AND status = 'active'
                LIMIT 5
            ";
            $stmt = $pdo->prepare($suggest_sql);
            $stmt->execute($suggest_params);
            $suggestions = $stmt->fetchAll();
        }
    } catch (Exception $e) {}
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Search Results</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Search</span>
            </div>
        </div>
    </section>

    <!-- Search Form -->
    <section class="search-section">
        <div class="container">
            <form action="<?= BASE_URL ?>pages/search.php" method="get" class="search-form-large">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           name="q" 
                           value="<?= htmlspecialchars($query) ?>" 
                           placeholder="Search products, brands, categories..." 
                           autocomplete="off"
                           class="search-input">
                    <button type="submit" class="search-button">Search</button>
                </div>
                
                <?php if (!empty($categories)): ?>
                    <div class="category-filter">
                        <select name="category" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <?php if (empty($query)): ?>
        <!-- Empty Search State -->
        <section class="empty-section">
            <div class="container">
                <div class="empty-card">
                    <i class="fas fa-search"></i>
                    <h2>Enter a search term</h2>
                    <p>Search for products, brands, or categories</p>
                </div>
                
                <?php if (!empty($popular_searches)): ?>
                    <div class="popular-searches">
                        <h3>Popular Searches</h3>
                        <div class="search-tags">
                            <?php foreach ($popular_searches as $search): ?>
                                <a href="?q=<?= urlencode($search['query']) ?>" class="search-tag">
                                    <?= htmlspecialchars($search['query']) ?>
                                    <span class="count">(<?= $search['search_count'] ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <?php elseif ($total_results == 0): ?>
        <!-- No Results -->
        <section class="no-results-section">
            <div class="container">
                <div class="no-results-card">
                    <i class="fas fa-frown"></i>
                    <h2>No results found for "<?= htmlspecialchars($query) ?>"</h2>
                    <p>Try different keywords or check your spelling</p>
                    
                    <?php if (!empty($suggestions)): ?>
                        <div class="suggestions">
                            <h3>You might be interested in:</h3>
                            <div class="suggestion-grid">
                                <?php foreach ($suggestions as $product): ?>
                                    <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>" class="suggestion-item">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($product['name']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="search-tips">
                        <h3>Search Tips:</h3>
                        <ul>
                            <li>Check your spelling</li>
                            <li>Use fewer keywords</li>
                            <li>Try a different category</li>
                            <li>Browse our <a href="<?= BASE_URL ?>pages/categories.php">categories</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

    <?php else: ?>
        <!-- Results Header -->
        <section class="results-header">
            <div class="container">
                <div class="results-info">
                    <h2>
                        Found <?= number_format($total_results) ?> result<?= $total_results != 1 ? 's' : '' ?> for 
                        "<?= htmlspecialchars($query) ?>"
                    </h2>
                    
                    <div class="results-actions">
                        <div class="sort-options">
                            <label for="sort">Sort by:</label>
                            <select id="sort" onchange="changeSort(this.value)">
                                <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevance</option>
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Best Selling</option>
                            </select>
                        </div>
                        
                        <div class="view-options">
                            <button class="view-btn active" onclick="setView('grid')">
                                <i class="fas fa-th"></i>
                            </button>
                            <button class="view-btn" onclick="setView('list')">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Results Grid -->
        <section class="results-section">
            <div class="container">
                <div class="results-grid" id="resultsGrid">
                    <?php foreach ($results as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>">
                                    <?php if (!empty($product['image'])): ?>
                                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image']) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php else: ?>
                                        <img src="<?= BASE_URL ?>assets/images/no-image.jpg" alt="No image">
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
                                <?php if ($product['brand']): ?>
                                    <div class="product-brand"><?= htmlspecialchars($product['brand']) ?></div>
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
                                
                                <div class="product-meta">
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
                                    
                                    <?php if ($product['sales_count'] > 0): ?>
                                        <div class="sales-count">
                                            <?= number_format($product['sales_count']) ?> sold
                                        </div>
                                    <?php endif; ?>
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
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&page=1" 
                               class="page-link first">&laquo;</a>
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>" 
                               class="page-link prev">&lsaquo;</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&page=<?= $i ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>" 
                               class="page-link next">&rsaquo;</a>
                            <a href="?q=<?= urlencode($query) ?>&category=<?= $category_id ?>&sort=<?= $sort ?>&page=<?= $total_pages ?>" 
                               class="page-link last">&raquo;</a>
                        <?php endif; ?>
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

/* Search Form */
.search-section {
    padding: 2rem 0;
    background: white;
    border-bottom: 1px solid var(--border);
}

.search-form-large {
    max-width: 800px;
    margin: 0 auto;
}

.search-wrapper {
    display: flex;
    position: relative;
    margin-bottom: 1rem;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
    z-index: 1;
}

.search-input {
    flex: 1;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border);
    border-right: none;
    border-radius: 50px 0 0 50px;
    font-size: 1.1rem;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-button {
    padding: 1rem 2rem;
    background: var(--primary);
    color: white;
    border: 2px solid var(--primary);
    border-radius: 0 50px 50px 0;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.search-button:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
}

.category-filter {
    text-align: center;
}

.category-filter select {
    padding: 0.5rem 2rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95rem;
    cursor: pointer;
}

/* Empty State */
.empty-section {
    padding: 4rem 0;
    background: var(--bg);
}

.empty-card {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    max-width: 600px;
    margin: 0 auto 3rem;
}

.empty-card i {
    font-size: 4rem;
    color: var(--text-lighter);
    margin-bottom: 1.5rem;
}

.empty-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.empty-card p {
    color: var(--text-light);
}

/* Popular Searches */
.popular-searches {
    text-align: center;
}

.popular-searches h3 {
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    color: var(--text);
}

.search-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
}

.search-tag {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    background: white;
    color: var(--text);
    text-decoration: none;
    border-radius: 50px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.search-tag:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.search-tag .count {
    color: var(--text-light);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.search-tag:hover .count {
    color: rgba(255,255,255,0.8);
}

/* No Results */
.no-results-section {
    padding: 4rem 0;
    background: var(--bg);
}

.no-results-card {
    max-width: 700px;
    margin: 0 auto;
    text-align: center;
    background: white;
    padding: 4rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.no-results-card i {
    font-size: 4rem;
    color: var(--text-lighter);
    margin-bottom: 1.5rem;
}

.no-results-card h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.no-results-card p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

/* Suggestions */
.suggestions {
    margin: 3rem 0;
    text-align: left;
}

.suggestions h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.suggestion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.suggestion-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: var(--bg);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
}

.suggestion-item:hover {
    background: var(--primary);
    color: white;
}

.suggestion-item img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
}

.suggestion-item span {
    font-size: 0.9rem;
}

/* Search Tips */
.search-tips {
    margin-top: 2rem;
    padding: 1.5rem;
    background: var(--bg);
    border-radius: 12px;
    text-align: left;
}

.search-tips h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.search-tips ul {
    list-style: none;
}

.search-tips li {
    color: var(--text-light);
    margin-bottom: 0.5rem;
    padding-left: 1.5rem;
    position: relative;
}

.search-tips li:before {
    content: '•';
    color: var(--primary);
    position: absolute;
    left: 0.5rem;
}

.search-tips a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.search-tips a:hover {
    text-decoration: underline;
}

/* Results Header */
.results-header {
    padding: 2rem 0;
    background: white;
    border-bottom: 1px solid var(--border);
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.results-info h2 {
    font-size: 1.3rem;
    color: var(--text);
}

.results-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
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
    font-size: 0.95rem;
    cursor: pointer;
}

.view-options {
    display: flex;
    gap: 0.5rem;
}

.view-btn {
    width: 40px;
    height: 40px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-light);
    cursor: pointer;
    transition: all 0.3s;
}

.view-btn:hover,
.view-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Results Grid */
.results-section {
    padding: 4rem 0;
    background: var(--bg);
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
    margin-bottom: 3rem;
}

/* Product Card */
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
    left: 1rem;
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
    z-index: 2;
}

.wishlist-btn:hover {
    background: #fee2e2;
    color: var(--danger);
}

.product-info {
    padding: 1.5rem;
}

.product-brand {
    color: var(--primary);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
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

.product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.product-rating {
    color: #fbbf24;
    font-size: 0.9rem;
}

.review-count {
    color: var(--text-light);
    font-size: 0.85rem;
    margin-left: 0.25rem;
}

.sales-count {
    color: var(--text-light);
    font-size: 0.85rem;
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

/* List View */
.results-grid.list-view {
    grid-template-columns: 1fr;
}

.list-view .product-card {
    display: flex;
    height: 200px;
}

.list-view .product-image {
    width: 200px;
    height: 200px;
    flex-shrink: 0;
}

.list-view .product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.list-view .product-title {
    height: auto;
    -webkit-line-clamp: 1;
}

.list-view .add-to-cart-btn {
    width: auto;
    margin-top: auto;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 3rem;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 0.5rem;
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
    padding: 0 1rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .results-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .results-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .results-info {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .search-wrapper {
        flex-direction: column;
        gap: 1rem;
    }
    
    .search-input {
        border-radius: 50px;
        border-right: 2px solid var(--border);
    }
    
    .search-button {
        border-radius: 50px;
    }
    
    .results-grid {
        grid-template-columns: 1fr;
    }
    
    .list-view .product-card {
        flex-direction: column;
        height: auto;
    }
    
    .list-view .product-image {
        width: 100%;
        height: 250px;
    }
    
    .no-results-card {
        padding: 2rem;
    }
    
    .no-results-card h2 {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .results-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .sort-options {
        width: 100%;
    }
    
    .sort-options select {
        flex: 1;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
let currentView = localStorage.getItem('searchView') || 'grid';

function setView(view) {
    currentView = view;
    localStorage.setItem('searchView', view);
    
    const grid = document.getElementById('resultsGrid');
    const buttons = document.querySelectorAll('.view-btn');
    
    if (view === 'list') {
        grid.classList.add('list-view');
        buttons[0].classList.remove('active');
        buttons[1].classList.add('active');
    } else {
        grid.classList.remove('list-view');
        buttons[0].classList.add('active');
        buttons[1].classList.remove('active');
    }
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
            // Show success message
            alert('Product added to cart!');
            
            // Update cart count
            if (data.cart_count !== undefined) {
                window.dispatchEvent(new CustomEvent('cartUpdate', {
                    detail: {count: data.cart_count}
                }));
            }
            
            // Optional: change button text temporarily
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Added!';
            btn.style.background = '#10b981';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 2000);
        } else {
            alert(data.message || 'Error adding to cart');
        }
    });
}

// Auto-suggest search (optional enhancement)
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    let debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = this.value.trim();
            if (query.length > 2) {
                // You could implement live search suggestions here
                console.log('Search for:', query);
            }
        }, 500);
    });
}

// Initialize view on page load
document.addEventListener('DOMContentLoaded', function() {
    setView(currentView);
});
</script>

<?php require_once '../includes/footer.php'; ?>