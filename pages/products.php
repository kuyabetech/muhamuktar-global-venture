<?php
// pages/products.php - All Products Listing (AliExpress Style)

$page_title = "All Products";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get filter parameters with validation
$category_id = isset($_GET['category']) ? max(0, (int)$_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? max(0.0, (float)$_GET['min_price']) : 0.0;
$max_price = isset($_GET['max_price']) ? max(0.0, (float)$_GET['max_price']) : 0.0;
$sort = $_GET['sort'] ?? 'newest'; // newest, price-low, price-high, popular
$search = trim($_GET['q'] ?? '');

// Validate sort options
$allowed_sorts = ['newest', 'price-low', 'price-high', 'popular'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'newest';
}

try {
    // Build main data query with product images
    $sql = "SELECT p.*, c.name AS category_name, 
                   pi.filename AS main_image
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_main = 1
            WHERE p.status = 'active'";
    
    $params = [];
    $conditions = [];

    if ($category_id > 0) {
        $conditions[] = "p.category_id = ?";
        $params[] = $category_id;
    }

    if ($min_price > 0) {
        $conditions[] = "COALESCE(p.discount_price, p.price) >= ?";
        $params[] = $min_price;
    }

    if ($max_price > 0) {
        $conditions[] = "COALESCE(p.discount_price, p.price) <= ?";
        $params[] = $max_price;
    }

    if ($search !== '') {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
        $search_term = "%" . $search . "%";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
    }

    // Add conditions to SQL
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    // Sorting
    switch ($sort) {
        case 'price-low':
            $sql .= " ORDER BY COALESCE(p.discount_price, p.price) ASC";
            break;
        case 'price-high':
            $sql .= " ORDER BY COALESCE(p.discount_price, p.price) DESC";
            break;
        case 'popular':
            $sql .= " ORDER BY p.views DESC, p.created_at DESC";
            break;
        default: // newest
            $sql .= " ORDER BY p.created_at DESC";
    }

    // Pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 24; // AliExpress shows 24-48 products per page
    $offset = ($page - 1) * $per_page;

    // COUNT query
    $countSql = "SELECT COUNT(*) FROM products p WHERE p.status = 'active'";
    $countParams = [];

    if ($category_id > 0) {
        $countSql .= " AND p.category_id = ?";
        $countParams[] = $category_id;
    }

    if ($min_price > 0) {
        $countSql .= " AND COALESCE(p.discount_price, p.price) >= ?";
        $countParams[] = $min_price;
    }

    if ($max_price > 0) {
        $countSql .= " AND COALESCE(p.discount_price, p.price) <= ?";
        $countParams[] = $max_price;
    }

    if ($search !== '') {
        $countSql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ? OR p.brand LIKE ?)";
        $search_term = "%" . $search . "%";
        array_push($countParams, $search_term, $search_term, $search_term, $search_term);
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();

    $total_pages = max(1, ceil($total / $per_page));

    // Add LIMIT / OFFSET to main query
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;

    // Execute main query
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $products = [];
        error_log("Product query failed: " . $stmt->errorInfo()[2]);
    }

    // Fetch categories for filter dropdown
    $catStmt = $pdo->query("SELECT id, name, (SELECT COUNT(*) FROM products WHERE category_id = categories.id AND status = 'active') as product_count FROM categories ORDER BY name");
    if ($catStmt) {
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $categories = [];
    }

} catch (PDOException $e) {
    error_log("PDO Exception in products.php: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total = 0;
    $total_pages = 1;
} catch (Exception $e) {
    error_log("General Exception in products.php: " . $e->getMessage());
    $products = [];
    $categories = [];
    $total = 0;
    $total_pages = 1;
}
?>

<style>
/* ===== ALIEXPRESS STYLE PRODUCT LISTING ===== */
/* Only product cards and grid layout - your colors preserved */

/* Products Page Layout */
.products-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 1.5rem;
    padding: 1.5rem 0;
}

@media (max-width: 992px) {
    .products-layout {
        grid-template-columns: 1fr;
    }
}

/* Filter Sidebar - AliExpress Style */
.filter-sidebar {
    background: var(--white);
    border-radius: 8px;
    padding: 1.2rem;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    height: fit-content;
    position: sticky;
    top: 100px;
    align-self: start;
    border: 1px solid var(--border);
}

.filter-sidebar h3 {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 1.2rem;
    color: var(--text);
    padding-bottom: 0.8rem;
    border-bottom: 1px solid var(--border);
}

.filter-group {
    margin-bottom: 1.5rem;
}

.filter-group h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.8rem;
    color: var(--text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Category List */
.category-list {
    list-style: none;
    max-height: 300px;
    overflow-y: auto;
}

.category-item {
    margin-bottom: 0.3rem;
}

.category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.8rem;
    text-decoration: none;
    color: var(--text);
    border-radius: 4px;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.category-link:hover {
    background: rgba(59, 130, 246, 0.08);
    color: var(--primary);
}

.category-link.active {
    background: rgba(59, 130, 246, 0.12);
    color: var(--primary);
    font-weight: 500;
}

.category-count {
    color: var(--text-lighter);
    font-size: 0.8rem;
}

/* Price Range */
.price-inputs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
}

.price-input {
    width: 100%;
    padding: 0.6rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    font-size: 0.9rem;
    background: var(--white);
    color: var(--text);
}

.price-input:focus {
    outline: none;
    border-color: var(--primary);
}

.price-btn {
    width: 100%;
    padding: 0.6rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.price-btn:hover {
    background: var(--primary-dark);
}

/* Sort Bar - AliExpress Style */
.sort-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 0.8rem 1rem;
    background: var(--white);
    border-radius: 8px;
    border: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}

.sort-options {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.sort-label {
    font-size: 0.9rem;
    color: var(--text-light);
}

.sort-links {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.sort-link {
    padding: 0.4rem 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    text-decoration: none;
    color: var(--text);
    font-size: 0.9rem;
    transition: all 0.2s;
}

.sort-link:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.sort-link.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.products-found {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Products Grid - AliExpress Style */
.products-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1200px) {
    .products-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 992px) {
    .products-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
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

/* Product Image */
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
    font-weight: 600;
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
    font-size: 0.6rem;
    font-weight: 600;
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
    font-weight: 600;
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
    font-weight: 600;
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

/* Stock Status */
.stock-status {
    font-size: 0.65rem;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 500;
    display: inline-block;
    margin-top: 4px;
}

.stock-low {
    background: rgba(220, 38, 38, 0.1);
    color: var(--danger);
}

.stock-out {
    background: rgba(107, 114, 128, 0.1);
    color: var(--text-lighter);
}

/* Pagination - AliExpress Style */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.3rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 0.5rem;
    background: var(--white);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.pagination-link:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pagination-link.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 0;
    color: var(--text-lighter);
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    color: var(--border);
    margin-bottom: 1rem;
}

.empty-state p {
    margin-bottom: 1.5rem;
    font-size: 1.1rem;
}

.empty-state-btn {
    display: inline-block;
    padding: 0.8rem 2rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.2s;
}

.empty-state-btn:hover {
    background: var(--primary-dark);
}

/* Active Filters */
.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 0.8rem;
    background: var(--white);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--primary);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
}

.filter-tag-remove {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    opacity: 0.8;
}

.filter-tag-remove:hover {
    opacity: 1;
}

/* Quick View Button */
.quick-view-btn {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s;
    white-space: nowrap;
    z-index: 3;
}

.product-card:hover .quick-view-btn {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.quick-view-btn:hover {
    background: rgba(0, 0, 0, 0.9);
}

/* Responsive */
@media (max-width: 768px) {
    .sort-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .sort-options {
        width: 100%;
    }
    
    .sort-links {
        width: 100%;
    }
    
    .sort-link {
        flex: 1;
        text-align: center;
    }
}
</style>

<div class="container products-layout">

    <!-- Filter Sidebar -->

    <!-- Main Products Area -->
    <main>


        <!-- Sort Bar -->
        <div class="sort-bar">
            <div class="sort-options">
                <span class="sort-label">Sort by:</span>
                <div class="sort-links">
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])) ?>" 
                       class="sort-link <?= $sort == 'newest' ? 'active' : '' ?>">Newest</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'popular', 'page' => 1])) ?>" 
                       class="sort-link <?= $sort == 'popular' ? 'active' : '' ?>">Best Sellers</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price-low', 'page' => 1])) ?>" 
                       class="sort-link <?= $sort == 'price-low' ? 'active' : '' ?>">Price: Low to High</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['sort' => 'price-high', 'page' => 1])) ?>" 
                       class="sort-link <?= $sort == 'price-high' ? 'active' : '' ?>">Price: High to Low</a>
                </div>
            </div>
            <div class="products-found">
                <?= $total ?> products found
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No products found matching your criteria.</p>
                <a href="?" class="empty-state-btn">Clear All Filters</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $p): 
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    $discount_percent = $old_price ? round((($old_price - $final_price) / $old_price) * 100) : 0;
                    
                    // Image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                    } else {
                        $image_src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
                    }
                    
                    // Random rating for demo
                    $rating = 4 + (rand(0, 10) / 10);
                    $rating_count = rand(10, 5000);
                ?>
                <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? $p['id']) ?>" class="product-card">
                    <div class="product-image">
                        <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        
                        <?php if ($discount_percent > 0): ?>
                            <span class="product-discount">-<?= $discount_percent ?>%</span>
                        <?php endif; ?>
                        
                        <div class="product-badges">
                            <?php if (($p['featured'] ?? 0) == 1): ?>
                                <span class="product-badge" style="background: var(--warning);">FEATURED</span>
                            <?php endif; ?>
                            <?php if (($p['stock'] ?? 0) < 10 && ($p['stock'] ?? 0) > 0): ?>
                                <span class="product-badge" style="background: var(--danger);">LOW STOCK</span>
                            <?php endif; ?>
                        </div>
                        
                        <button class="quick-view-btn" onclick="event.preventDefault(); quickView(<?= $p['id'] ?>)">
                            <i class="fas fa-eye"></i> Quick View
                        </button>
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
                                <span class="price-save">-<?= $discount_percent ?>%</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-rating">
                            <?php
                            $full_stars = floor($rating);
                            $half_star = ($rating - $full_stars) >= 0.5;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $full_stars):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i == $full_stars + 1 && $half_star): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                            <?php endfor; ?>
                            <span>(<?= number_format($rating_count) ?>)</span>
                        </div>
                        
                        <div class="product-shipping">
                            <i class="fas fa-shipping-fast"></i> Free Shipping
                        </div>
                        
                        <?php if (($p['stock'] ?? 0) == 0): ?>
                            <div class="stock-status stock-out">
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </div>
                        <?php elseif (($p['stock'] ?? 0) < 10): ?>
                            <div class="stock-status stock-low">
                                <i class="fas fa-exclamation-triangle"></i> Only <?= $p['stock'] ?> left
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-link disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pagination-link">1</a>';
                        if ($start_page > 2) echo '<span class="pagination-link disabled">...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="pagination-link <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="pagination-link disabled">...</span>
                        <?php endif; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="pagination-link">
                            <?= $total_pages ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-link disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<script>
// Products Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit search with debounce
    const searchInput = document.querySelector('input[name="q"]');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length > 0 || "<?= $search ?>" !== '') {
                    this.form.submit();
                }
            }, 500);
        });
    }
    
    // Price range validation
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');
    
    if (minPriceInput && maxPriceInput) {
        const priceForm = minPriceInput.closest('form');
        priceForm.addEventListener('submit', function(e) {
            const min = parseFloat(minPriceInput.value) || 0;
            const max = parseFloat(maxPriceInput.value) || 0;
            
            if (max > 0 && min > max) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price.');
                minPriceInput.focus();
            }
        });
    }
    
    // Quick view function
    window.quickView = function(productId) {
        window.location.href = '<?= BASE_URL ?>pages/product.php?id=' + productId;
    };
});

// Image error handling
document.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG' && e.target.closest('.product-image')) {
        e.target.src = 'https://via.placeholder.com/200/f8f8f8/999?text=No+Image';
    }
}, true);
</script>

<?php require_once '../includes/footer.php'; ?>