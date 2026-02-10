<?php
// pages/products.php - All Products Listing

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
    $per_page = 20;
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
    $catStmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
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
/* Products Page Specific Styles */
.products-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: var(--space-xl);
    padding: var(--space-2xl) 0;
}

@media (max-width: 992px) {
    .products-container {
        grid-template-columns: 1fr;
        gap: var(--space-lg);
    }
}

/* Filter Sidebar */
.filter-sidebar {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    box-shadow: var(--shadow-md);
    height: fit-content;
    position: sticky;
    top: calc(100px + var(--space-lg));
    align-self: start;
}

.filter-sidebar h3 {
    font-size: 1.35rem;
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-lg);
    color: var(--text);
    padding-bottom: var(--space-sm);
    border-bottom: 2px solid var(--border);
}

.filter-group {
    margin-bottom: var(--space-xl);
}

.filter-group h4 {
    font-size: 1rem;
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-md);
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.category-list {
    list-style: none;
}

.category-item {
    margin-bottom: var(--space-sm);
}

.category-link {
    display: block;
    padding: var(--space-sm) var(--space-md);
    text-decoration: none;
    color: var(--text);
    border-radius: var(--radius);
    transition: all var(--transition);
    font-weight: var(--fw-medium);
    border-left: 3px solid transparent;
}

.category-link:hover {
    background: rgba(59, 130, 246, 0.08);
    color: var(--primary);
    padding-left: calc(var(--space-md) + 6px);
    border-left-color: var(--primary-light);
}

.category-link.active {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.05));
    color: var(--primary);
    font-weight: var(--fw-bold);
    border-left-color: var(--primary);
}

/* Price Range */
.price-range-form {
    margin-bottom: var(--space-lg);
}

.price-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-sm);
    margin-bottom: var(--space-md);
}

.price-input {
    width: 100%;
    padding: var(--space-md);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: var(--fs-base);
    background: var(--white);
    color: var(--text);
    transition: all var(--transition);
}

.price-input:focus {
    outline: none;
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.price-btn {
    width: 100%;
    padding: var(--space-md);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: var(--radius);
    font-weight: var(--fw-bold);
    cursor: pointer;
    transition: all var(--transition);
}

.price-btn:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
}

/* Sort Select */
.sort-select {
    width: 100%;
    padding: var(--space-md);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-size: var(--fs-base);
    background: var(--white);
    color: var(--text);
    cursor: pointer;
    transition: all var(--transition);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right var(--space-md) center;
    background-size: 16px;
    padding-right: calc(var(--space-md) * 2 + 16px);
}

.sort-select:focus {
    outline: none;
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Active Filters */
.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-sm);
    margin-bottom: var(--space-xl);
    padding: var(--space-md);
    background: var(--white);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    background: var(--primary);
    color: white;
    padding: var(--space-xs) var(--space-sm);
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: var(--fw-medium);
}

.filter-tag-remove {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0;
    font-size: 1.1rem;
    line-height: 1;
    margin-left: var(--space-xs);
    opacity: 0.8;
    transition: opacity var(--transition);
}

.filter-tag-remove:hover {
    opacity: 1;
}

/* Products Grid */
.products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xl);
    flex-wrap: wrap;
    gap: var(--space-md);
}

.products-title {
    font-size: 2.1rem;
    font-weight: var(--fw-extrabold);
    margin: 0;
    color: var(--text);
}

.products-title small {
    font-size: 1rem;
    color: var(--text-lighter);
    font-weight: var(--fw-medium);
    margin-left: var(--space-sm);
}

.products-count {
    color: var(--text-lighter);
    font-size: 0.95rem;
    font-weight: var(--fw-medium);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: var(--space-lg);
    margin-bottom: var(--space-2xl);
}

/* Product Card */
.product-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    box-shadow: var(--shadow-sm);
    transition: all var(--transition);
    display: block;
    position: relative;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

.product-image {
    height: 220px;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-image .placeholder-icon {
    font-size: 4rem;
    color: var(--text-lighter);
    transition: transform var(--transition);
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-card:hover .product-image .placeholder-icon {
    transform: scale(1.1);
}

.product-discount {
    position: absolute;
    top: var(--space-sm);
    left: var(--space-sm);
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius);
    font-size: 0.8rem;
    font-weight: var(--fw-bold);
    z-index: 2;
    box-shadow: var(--shadow-sm);
}

.product-badges {
    position: absolute;
    top: var(--space-sm);
    right: var(--space-sm);
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.product-badge {
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius);
    font-size: 0.7rem;
    font-weight: var(--fw-bold);
    backdrop-filter: blur(4px);
    text-align: center;
}

.product-info {
    padding: var(--space-lg);
}

.product-name {
    font-size: 1.05rem;
    font-weight: var(--fw-medium);
    margin-bottom: var(--space-md);
    color: var(--text);
    line-height: 1.4;
    height: 2.8em;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.product-price {
    margin: var(--space-md) 0;
}

.product-price-current {
    font-size: 1.35rem;
    font-weight: var(--fw-bold);
    color: var(--danger);
    display: block;
}

.product-price-old {
    font-size: 0.95rem;
    color: var(--text-lighter);
    text-decoration: line-through;
    margin-right: var(--space-sm);
}

.product-save {
    display: inline-block;
    background: var(--danger);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: var(--fw-bold);
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 2px;
    color: #fbbf24;
    font-size: 0.9rem;
    margin-bottom: var(--space-sm);
}

.product-rating span {
    color: var(--text-lighter);
    margin-left: var(--space-xs);
    font-weight: var(--fw-medium);
}

.product-shipping {
    font-size: 0.85rem;
    color: var(--success);
    font-weight: var(--fw-medium);
    margin-top: var(--space-md);
    display: flex;
    align-items: center;
    gap: var(--space-xs);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-xl);
    flex-wrap: wrap;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    height: 44px;
    padding: 0 var(--space-md);
    background: var(--white);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: var(--fw-medium);
    transition: all var(--transition);
}

.pagination-link:hover {
    background: rgba(59, 130, 246, 0.08);
    color: var(--primary);
    border-color: var(--primary-light);
    transform: translateY(-2px);
}

.pagination-link.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-color: var(--primary);
    font-weight: var(--fw-bold);
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: var(--space-2xl) 0;
    color: var(--text-lighter);
    font-size: 1.2rem;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 5rem;
    color: var(--border);
    margin-bottom: var(--space-lg);
    opacity: 0.5;
}

.empty-state p {
    margin-bottom: var(--space-lg);
    font-weight: var(--fw-medium);
}

.empty-state-btn {
    display: inline-block;
    padding: var(--space-md) var(--space-xl);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    text-decoration: none;
    border-radius: var(--radius);
    font-weight: var(--fw-bold);
    transition: all var(--transition);
}

.empty-state-btn:hover {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
}

/* Responsive Design */
@media (max-width: 992px) {
    .products-container {
        grid-template-columns: 1fr;
        gap: var(--space-lg);
    }
    
    .filter-sidebar {
        position: static;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: var(--space-md);
    }
}

@media (max-width: 768px) {
    .products-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-md);
    }
    
    .products-title {
        font-size: 1.75rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    }
    
    .product-image {
        height: 180px;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .price-inputs {
        grid-template-columns: 1fr;
    }
    
    .product-image {
        height: 150px;
    }
}

/* Quick View Button */
.quick-view-btn {
    position: absolute;
    bottom: var(--space-sm);
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border: none;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius);
    font-size: 0.8rem;
    font-weight: var(--fw-medium);
    cursor: pointer;
    opacity: 0;
    transition: all var(--transition);
    backdrop-filter: blur(4px);
}

.product-card:hover .quick-view-btn {
    opacity: 1;
    transform: translateX(-50%) translateY(-5px);
}

.quick-view-btn:hover {
    background: rgba(0, 0, 0, 0.9);
    transform: translateX(-50%) translateY(-5px) scale(1.05);
}

/* Loading Animation */
.loading {
    opacity: 0.7;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% { opacity: 0.7; }
    50% { opacity: 0.5; }
    100% { opacity: 0.7; }
}
</style>

<main class="container products-container">

  <!-- Sidebar Filters -->
<!--  <aside class="filter-sidebar">
    <h3>Filters</h3> -->

    <!-- Search -->
  <!--  <form method="get" class="filter-group">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search products..." class="price-input" autocomplete="off">
      <?php if ($category_id > 0): ?>
        <input type="hidden" name="category" value="<?= $category_id ?>">
      <?php endif; ?>
      <?php if ($sort !== 'newest'): ?>
        <input type="hidden" name="sort" value="<?= $sort ?>">
      <?php endif; ?>
      <?php if ($page > 1): ?>
        <input type="hidden" name="page" value="1">
      <?php endif; ?>
    </form>

  </aside>
-->
  <!-- Main Products Grid -->
  <section>
    <div class="products-header">
      <h1 class="products-title">
        <?php if ($search): ?>
          Results for "<?= htmlspecialchars($search) ?>"
        <?php elseif ($category_id > 0): ?>
          <?php 
            $category_name = 'Products';
            foreach ($categories as $cat) {
              if ($cat['id'] == $category_id) {
                $category_name = $cat['name'];
                break;
              }
            }
            echo htmlspecialchars($category_name);
          ?>
        <?php else: ?>
          All Products
        <?php endif; ?>
        <small>(<?= $total ?> items)</small>
      </h1>

      <div class="products-count">
        <?php if (count($products) > 0): ?>
          Showing <?= min(($page - 1) * $per_page + 1, $total) ?>-<?= min($page * $per_page, $total) ?> of <?= $total ?>
        <?php else: ?>
          No products found
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <p>No products found matching your criteria.</p>
        <p style="font-size: 1rem; color: var(--text-light); margin-bottom: var(--space-xl);">
          Try adjusting your filters or search term.
        </p>
        <a href="?page=1" class="empty-state-btn">
          <i class="fas fa-redo"></i> Clear All Filters
        </a>
      </div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $p): ?>
          <?php
            $final_price = $p['discount_price'] ?? $p['price'];
            $old_price = $p['discount_price'] ? $p['price'] : null;
            $discount_percent = $old_price ? round((($old_price - $final_price) / $old_price) * 100) : 0;
            $save_amount = $old_price ? $old_price - $final_price : 0;
            
            // Determine image source
            if (!empty($p['main_image'])) {
                $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                $image_alt = htmlspecialchars($p['name']);
            } else {
                $image_src = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="' . ($discount_percent > 0 ? '#f3f4f6' : '#e5e7eb') . '"/><text x="100" y="100" font-family="Arial" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">No Image</text></svg>');
                $image_alt = 'No image available';
            }
          ?>
          <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($p['slug'] ?? '') ?>" class="product-card">
            <div class="product-image">
              <img src="<?= $image_src ?>" 
                   alt="<?= $image_alt ?>" 
                   loading="lazy"
                   onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNmM2Y0ZjYiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOWNhM2FmIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+';">
              
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
              <h3 class="product-name">
                <?= htmlspecialchars($p['name'] ?? 'Unnamed Product') ?>
              </h3>
              
              <?php if (!empty($p['brand'])): ?>
                <div style="font-size: 0.9rem; color: var(--text-light); margin-bottom: var(--space-sm);">
                  <?= htmlspecialchars($p['brand']) ?>
                </div>
              <?php endif; ?>

              <div class="product-price">
                <span class="product-price-current">₦<?= number_format($final_price) ?></span>
                <?php if ($old_price): ?>
                  <span class="product-price-old">₦<?= number_format($old_price) ?></span>
                  <?php if ($save_amount > 0): ?>
                    <span class="product-save">Save ₦<?= number_format($save_amount) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <div class="product-rating">
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star"></i>
                <i class="fas fa-star-half-alt"></i>
                <span>4.7</span>
              </div>

              <div class="product-shipping">
                <i class="fas fa-shipping-fast"></i> Free Shipping
              </div>
              
              <?php if (($p['stock'] ?? 0) == 0): ?>
                <div style="font-size: 0.85rem; color: var(--danger); margin-top: var(--space-sm); font-weight: var(--fw-medium);">
                  <i class="fas fa-times-circle"></i> Out of Stock
                </div>
              <?php elseif (($p['stock'] ?? 0) < 10): ?>
                <div style="font-size: 0.85rem; color: var(--warning); margin-top: var(--space-sm); font-weight: var(--fw-medium);">
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
          <!-- Previous Button -->
          <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-link">
              <i class="fas fa-chevron-left"></i>
            </a>
          <?php else: ?>
            <span class="pagination-link disabled">
              <i class="fas fa-chevron-left"></i>
            </span>
          <?php endif; ?>

          <!-- Page Numbers -->
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

          <!-- Next Button -->
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

  </section>

</main>

<script>
// Products Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit search when typing (with debounce)
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
                minPriceInput.style.borderColor = 'var(--danger)';
                maxPriceInput.style.borderColor = 'var(--danger)';
                
                setTimeout(() => {
                    minPriceInput.style.borderColor = '';
                    maxPriceInput.style.borderColor = '';
                }, 2000);
            }
        });
    }
    
    // Image lazy loading
    const productImages = document.querySelectorAll('.product-image img');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        delete img.dataset.src;
                    }
                    imageObserver.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.1
        });
        
        productImages.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for browsers without IntersectionObserver
        productImages.forEach(img => {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                delete img.dataset.src;
            }
        });
    }
    
    // Quick view function
    window.quickView = function(productId) {
        // In a real implementation, you would fetch product details via AJAX
        // For now, we'll just navigate to the product page
        window.location.href = '<?= BASE_URL ?>pages/product.php?id=' + productId;
    };
    
    // Add to cart from product listing
    document.addEventListener('click', function(e) {
        if (e.target.closest('.add-to-cart-btn')) {
            e.preventDefault();
            const productCard = e.target.closest('.product-card');
            const productId = productCard.dataset.productId;
            
            if (productId) {
                addToCart(productId, 1);
            }
        }
    });
    
    // Update page title with search results
    <?php if ($search): ?>
        document.title = "Results for '<?= addslashes($search) ?>' | <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?>";
    <?php endif; ?>
});

// Add to cart function
function addToCart(productId, quantity) {
    console.log(`Adding ${quantity} of product ${productId} to cart`);
    
    // Show loading state
    const originalText = event.target.innerHTML;
    event.target.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    event.target.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Show success message
        showNotification('Product added to cart successfully!', 'success');
        
        // Update cart count
        if (window.updateCartCount) {
            const currentCount = parseInt(document.querySelector('#cart-count')?.textContent) || 0;
            window.updateCartCount(currentCount + quantity);
        }
        
        // Restore button
        event.target.innerHTML = originalText;
        event.target.disabled = false;
    }, 800);
}

// Notification function
function showNotification(message, type = 'success') {
    // Remove any existing notifications
    document.querySelectorAll('.notification').forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'success' ? 'var(--success)' : 
                     type === 'error' ? 'var(--danger)' : 
                     type === 'info' ? 'var(--primary)' : 'var(--text)'};
        color: white;
        padding: var(--space-md) var(--space-lg);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        z-index: 9999;
        animation: slideIn 0.3s ease;
        max-width: 350px;
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 
                          type === 'error' ? 'exclamation-circle' : 
                          'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS for animations if not already added
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// Product image error handling
document.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG' && e.target.closest('.product-image')) {
        e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjIwMCIgaGVpZ2h0PSIyMDAiIGZpbGw9IiNmM2Y0ZjYiLz48dGV4dCB4PSIxMDAiIHk9IjEwMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOWNhM2FmIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+';
        e.target.alt = 'Image not available';
    }
}, true);

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
        }
    }
    
    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput && searchInput.value) {
            searchInput.value = '';
            searchInput.form.submit();
        }
    }
});

// Sort dropdown change handler
const sortSelect = document.querySelector('.sort-select');
if (sortSelect) {
    sortSelect.addEventListener('change', function() {
        window.location.href = this.value ? '?sort=' + this.value : '?';
    });
}

// Price range form submission
const priceForms = document.querySelectorAll('.price-range-form');
priceForms.forEach(form => {
    form.addEventListener('submit', function(e) {
        const minPrice = this.querySelector('[name="min_price"]').value;
        const maxPrice = this.querySelector('[name="max_price"]').value;
        
        if (!minPrice && !maxPrice) {
            e.preventDefault();
            return false;
        }
        
        return true;
    });
});

// Infinite scroll (optional)
let isLoading = false;
let currentPage = <?= $page ?>;
let totalPages = <?= $total_pages ?>;

function checkScroll() {
    if (isLoading || currentPage >= totalPages) return;
    
    const scrollPosition = window.innerHeight + window.pageYOffset;
    const pageHeight = document.documentElement.scrollHeight;
    
    if (scrollPosition >= pageHeight - 500) {
        loadMoreProducts();
    }
}

function loadMoreProducts() {
    if (isLoading) return;
    
    isLoading = true;
    currentPage++;
    
    // Show loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading';
    loadingIndicator.style.cssText = `
        text-align: center;
        padding: var(--space-xl);
        grid-column: 1 / -1;
    `;
    loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading more products...';
    
    const productsGrid = document.querySelector('.products-grid');
    if (productsGrid) {
        productsGrid.appendChild(loadingIndicator);
    }
    
    // Fetch next page via AJAX
    const nextPageUrl = `?page=${currentPage}&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>`;
    
    fetch(nextPageUrl)
        .then(response => response.text())
        .then(html => {
            // Parse the HTML and extract products
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newProducts = doc.querySelector('.products-grid')?.innerHTML || '';
            
            // Remove loading indicator
            loadingIndicator.remove();
            
            // Append new products
            if (productsGrid && newProducts) {
                productsGrid.innerHTML += newProducts;
                isLoading = false;
            }
        })
        .catch(error => {
            console.error('Error loading more products:', error);
            loadingIndicator.remove();
            isLoading = false;
        });
}

// Enable infinite scroll if user scrolls to bottom
window.addEventListener('scroll', checkScroll);
</script>

<?php require_once '../includes/footer.php'; ?>