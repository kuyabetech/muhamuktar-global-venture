<?php
// pages/category.php - Single Category Page

$page_title = "Category";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

$category_id = (int)($_GET['id'] ?? 0);
$slug = $_GET['slug'] ?? '';

if ($category_id <= 0 && empty($slug)) {
    header("Location: " . BASE_URL . "pages/categories.php");
    exit;
}

// Get category details
if (!empty($slug)) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND status = 'active'");
    $stmt->execute([$category_id]);
}
$category = $stmt->fetch();

if (!$category) {
    header("Location: " . BASE_URL . "pages/categories.php");
    exit;
}

$page_title = $category['name'] . " - " . SITE_NAME;

// Get subcategories
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count
    FROM categories c
    WHERE c.parent_id = ? AND c.status = 'active'
    ORDER BY c.display_order ASC, c.name ASC
");
$stmt->execute([$category['id']]);
$subcategories = $stmt->fetchAll();

// Get filters
$sort = $_GET['sort'] ?? 'newest';
$min_price = (float)($_GET['min_price'] ?? 0);
$max_price = (float)($_GET['max_price'] ?? 0);
$brand_filter = $_GET['brand'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Build product query
$where = ["p.status = 'active'", "p.category_id = ?"];
$params = [$category['id']];

if ($min_price > 0) {
    $where[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price > 0) {
    $where[] = "p.price <= ?";
    $params[] = $max_price;
}

if (!empty($brand_filter)) {
    $where[] = "p.brand = ?";
    $params[] = $brand_filter;
}

// Get total products
$count_sql = "SELECT COUNT(*) FROM products p WHERE " . implode(" AND ", $where);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetchColumn();
$total_pages = ceil($total_products / $limit);

// Build order by
$order_by = "p.created_at DESC"; // default newest
switch ($sort) {
    case 'price_asc':
        $order_by = "p.price ASC";
        break;
    case 'price_desc':
        $order_by = "p.price DESC";
        break;
    case 'name_asc':
        $order_by = "p.name ASC";
        break;
    case 'name_desc':
        $order_by = "p.name DESC";
        break;
    case 'popular':
        $order_by = "(SELECT COUNT(*) FROM order_items WHERE product_id = p.id) DESC";
        break;
    case 'rating':
        $order_by = "(SELECT AVG(rating) FROM reviews WHERE product_id = p.id) DESC";
        break;
}

// Get products
$sql = "
    SELECT p.*, 
           (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id AND status = 'approved') as review_count,
           (SELECT AVG(rating) FROM reviews WHERE product_id = p.id AND status = 'approved') as avg_rating
    FROM products p
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $order_by
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get brands in this category for filter
$stmt = $pdo->prepare("
    SELECT DISTINCT brand, COUNT(*) as product_count
    FROM products
    WHERE category_id = ? AND brand IS NOT NULL AND brand != '' AND status = 'active'
    GROUP BY brand
    ORDER BY brand
");
$stmt->execute([$category['id']]);
$brands = $stmt->fetchAll();

// Get price range
$stmt = $pdo->prepare("
    SELECT MIN(price) as min_price, MAX(price) as max_price
    FROM products
    WHERE category_id = ? AND status = 'active'
");
$stmt->execute([$category['id']]);
$price_range = $stmt->fetch();
$min_available = (float)$price_range['min_price'];
$max_available = (float)$price_range['max_price'];
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title"><?= htmlspecialchars($category['name']) ?></h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/categories.php">Categories</a>
                <span class="separator">/</span>
                <span class="current"><?= htmlspecialchars($category['name']) ?></span>
            </div>
            <?php if (!empty($category['description'])): ?>
                <p class="category-description"><?= htmlspecialchars($category['description']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Subcategories if any -->
    <?php if (!empty($subcategories)): ?>
        <section class="subcategories-section">
            <div class="container">
                <h2 class="section-title">Shop by Subcategory</h2>
                <div class="subcategories-grid">
                    <?php foreach ($subcategories as $subcat): ?>
                        <a href="<?= BASE_URL ?>pages/category.php?id=<?= $subcat['id'] ?>" class="subcategory-card">
                            <div class="subcategory-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                            <h3><?= htmlspecialchars($subcat['name']) ?></h3>
                            <span><?= $subcat['product_count'] ?> products</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Products Section -->
    <section class="products-section section">
        <div class="container">
            <div class="shop-layout">
                <!-- Sidebar Filters -->
                <aside class="shop-sidebar">
                    <div class="filter-widget">
                        <h3 class="filter-title">Filter by Price</h3>
                        <div class="price-filter">
                            <div class="price-inputs">
                                <div class="price-field">
                                    <label>Min</label>
                                    <input type="number" id="min-price" value="<?= $min_price ?: $min_available ?>" 
                                           min="<?= $min_available ?>" max="<?= $max_available ?>" step="100">
                                </div>
                                <div class="price-field">
                                    <label>Max</label>
                                    <input type="number" id="max-price" value="<?= $max_price ?: $max_available ?>" 
                                           min="<?= $min_available ?>" max="<?= $max_available ?>" step="100">
                                </div>
                            </div>
                            <button onclick="applyPriceFilter()" class="btn-filter">Apply</button>
                        </div>
                    </div>

                    <?php if (!empty($brands)): ?>
                        <div class="filter-widget">
                            <h3 class="filter-title">Filter by Brand</h3>
                            <div class="brand-filter">
                                <?php foreach ($brands as $brand): ?>
                                    <label class="brand-checkbox">
                                        <input type="checkbox" name="brand" value="<?= htmlspecialchars($brand['brand']) ?>" 
                                               <?= $brand_filter === $brand['brand'] ? 'checked' : '' ?>
                                               onchange="applyBrandFilter(this)">
                                        <span><?= htmlspecialchars($brand['brand']) ?></span>
                                        <span class="count">(<?= $brand['product_count'] ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </aside>

                <!-- Products Grid -->
                <div class="shop-content">
                    <!-- Shop Header -->
                    <div class="shop-header">
                        <div class="results-count">
                            Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_products) ?> of <?= $total_products ?> products
                        </div>
                        <div class="sort-options">
                            <label for="sort">Sort by:</label>
                            <select id="sort" onchange="changeSort(this.value)">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top Rated</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($products)): ?>
                        <div class="empty-products">
                            <i class="fas fa-box-open"></i>
                            <h3>No Products Found</h3>
                            <p>Try adjusting your filters or check back later</p>
                            <a href="<?= BASE_URL ?>pages/category.php?id=<?= $category['id'] ?>" class="btn-clear">Clear Filters</a>
                        </div>
                    <?php else: ?>
                        <div class="products-grid">
                            <?php foreach ($products as $product): ?>
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
                                            <span class="discount-badge">-<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%</span>
                                        <?php endif; ?>
                                        <button class="wishlist-btn" onclick="toggleWishlist(<?= $product['id'] ?>)">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    </div>
                                    <div class="product-info">
                                        <a href="<?= BASE_URL ?>pages/product.php?id=<?= $product['id'] ?>" class="product-title">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </a>
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
                                    <a href="?id=<?= $category['id'] ?>&page=1&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&brand=<?= urlencode($brand_filter) ?>" 
                                       class="page-link">&laquo;</a>
                                    <a href="?id=<?= $category['id'] ?>&page=<?= $page - 1 ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&brand=<?= urlencode($brand_filter) ?>" 
                                       class="page-link">&lsaquo;</a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?id=<?= $category['id'] ?>&page=<?= $i ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&brand=<?= urlencode($brand_filter) ?>" 
                                       class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?id=<?= $category['id'] ?>&page=<?= $page + 1 ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&brand=<?= urlencode($brand_filter) ?>" 
                                       class="page-link">&rsaquo;</a>
                                    <a href="?id=<?= $category['id'] ?>&page=<?= $total_pages ?>&sort=<?= $sort ?>&min_price=<?= $min_price ?>&max_price=<?= $max_price ?>&brand=<?= urlencode($brand_filter) ?>" 
                                       class="page-link">&raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
    padding: 3rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.category-description {
    max-width: 800px;
    margin: 1.5rem auto 0;
    opacity: 0.9;
}

/* Subcategories */
.subcategories-section {
    padding: 3rem 0;
    background: white;
}

.subcategories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1.5rem;
}

.subcategory-card {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
}

.subcategory-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    background: white;
}

.subcategory-icon {
    width: 60px;
    height: 60px;
    background: var(--primary-light);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto 1rem;
}

.subcategory-card h3 {
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.subcategory-card span {
    font-size: 0.85rem;
    color: var(--text-light);
}

/* Shop Layout */
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

.brand-filter {
    max-height: 250px;
    overflow-y: auto;
}

.brand-checkbox {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    cursor: pointer;
}

.brand-checkbox input {
    margin-right: 0.5rem;
}

.brand-checkbox .count {
    margin-left: auto;
    color: var(--text-light);
    font-size: 0.85rem;
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
    background: var(--danger);
    color: white;
}

.product-info {
    padding: 1.5rem;
}

.product-title {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text);
    text-decoration: none;
    margin-bottom: 0.5rem;
}

.product-title:hover {
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
    transition: background 0.3s;
}

.add-to-cart-btn:hover {
    background: var(--primary-dark);
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

.btn-clear {
    display: inline-block;
    padding: 0.75rem 2rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.3s;
}

.btn-clear:hover {
    background: var(--primary-dark);
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
        margin-bottom: 2rem;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .shop-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination {
        flex-wrap: wrap;
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

function applyBrandFilter(checkbox) {
    const url = new URL(window.location.href);
    if (checkbox.checked) {
        url.searchParams.set('brand', checkbox.value);
    } else {
        url.searchParams.delete('brand');
    }
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
    // Check if user is logged in
    <?php if (!is_logged_in()): ?>
        window.location.href = '<?= BASE_URL ?>pages/login.php?redirect=' + encodeURIComponent(window.location.href);
        return;
    <?php endif; ?>
    
    // Toggle wishlist via AJAX
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
            
            // Update wishlist count
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
    
    // Add to cart via AJAX
    fetch('<?= BASE_URL ?>api/add-to-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
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
        } else {
            alert(data.message || 'Error adding to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding to cart');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>