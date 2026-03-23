<?php
// pages/brands.php - Brands Listing Page

$page_title = "Shop by Brand";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get all brands with product counts
try {
    $stmt = $pdo->query("
        SELECT DISTINCT brand, 
               COUNT(*) as product_count,
               MIN(price) as min_price,
               MAX(price) as max_price,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as sample_image
        FROM products p
        WHERE brand IS NOT NULL 
          AND brand != '' 
          AND status = 'active'
        GROUP BY brand
        ORDER BY brand ASC
    ");
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brands = [];
}

// Get featured brands (brands with most products)
try {
    $stmt = $pdo->query("
        SELECT brand, 
               COUNT(*) as product_count,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as sample_image
        FROM products p
        WHERE brand IS NOT NULL 
          AND brand != '' 
          AND status = 'active'
        GROUP BY brand
        ORDER BY product_count DESC
        LIMIT 8
    ");
    $featured_brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featured_brands = [];
}

// Get brand categories (for filtering)
$brand_categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name, 
               COUNT(DISTINCT p.brand) as brand_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.brand IS NOT NULL AND p.brand != '' AND p.status = 'active'
        WHERE c.status = 'active'
        GROUP BY c.id, c.name
        HAVING brand_count > 0
        ORDER BY c.name
    ");
    $brand_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $brand_categories = [];
}

// Get filter parameters
$selected_letter = $_GET['letter'] ?? '';
$selected_category = (int)($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Filter brands based on parameters
$filtered_brands = $brands;
if (!empty($selected_letter) && ctype_alpha($selected_letter)) {
    $filtered_brands = array_filter($brands, function($brand) use ($selected_letter) {
        return strtoupper(substr($brand['brand'], 0, 1)) === strtoupper($selected_letter);
    });
}

if ($selected_category > 0) {
    // Get brands from specific category
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT brand
            FROM products
            WHERE category_id = ? 
              AND brand IS NOT NULL 
              AND brand != '' 
              AND status = 'active'
        ");
        $stmt->execute([$selected_category]);
        $category_brands = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $filtered_brands = array_filter($filtered_brands, function($brand) use ($category_brands) {
            return in_array($brand['brand'], $category_brands);
        });
    } catch (Exception $e) {
        // Ignore errors
    }
}

if (!empty($search)) {
    $filtered_brands = array_filter($brands, function($brand) use ($search) {
        return stripos($brand['brand'], $search) !== false;
    });
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 24;
$offset = ($page - 1) * $limit;
$total_brands = count($filtered_brands);
$total_pages = ceil($total_brands / $limit);
$paginated_brands = array_slice($filtered_brands, $offset, $limit);
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Shop by Brand</h1>
            <p class="header-description">Discover products from top brands and manufacturers</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Brands</span>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <form action="<?= BASE_URL ?>pages/brands.php" method="get" class="search-form">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search for brands..." 
                           class="search-input">
                    <button type="submit" class="search-btn">Search Brands</button>
                </div>
                
                <?php if (!empty($brand_categories)): ?>
                    <div class="category-filter">
                        <label for="category">Filter by Category:</label>
                        <select name="category" id="category" onchange="this.form.submit()">
                            <option value="0">All Categories</option>
                            <?php foreach ($brand_categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $selected_category == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?> (<?= $cat['brand_count'] ?> brands)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <!-- Featured Brands Section -->
    <?php if (!empty($featured_brands) && empty($search) && $selected_category == 0 && empty($selected_letter)): ?>
    <section class="featured-section">
        <div class="container">
            <h2 class="section-title">Featured Brands</h2>
            <div class="featured-grid">
                <?php foreach ($featured_brands as $brand): ?>
                    <a href="<?= BASE_URL ?>pages/products.php?brand=<?= urlencode($brand['brand']) ?>" class="featured-card">
                        <?php if (!empty($brand['sample_image'])): ?>
                            <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($brand['sample_image']) ?>" 
                                 alt="<?= htmlspecialchars($brand['brand']) ?>"
                                 class="brand-image">
                        <?php else: ?>
                            <div class="brand-icon">
                                <i class="fas fa-copyright"></i>
                            </div>
                        <?php endif; ?>
                        <h3 class="brand-name"><?= htmlspecialchars($brand['brand']) ?></h3>
                        <p class="brand-count"><?= number_format($brand['product_count']) ?> products</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Alphabet Filter -->
    <section class="alphabet-filter">
        <div class="container">
            <div class="alphabet-grid">
                <a href="?letter=all<?= $selected_category ? '&category=' . $selected_category : '' ?>" 
                   class="alpha-link <?= empty($selected_letter) ? 'active' : '' ?>">All</a>
                <?php foreach (range('A', 'Z') as $letter): ?>
                    <a href="?letter=<?= $letter ?><?= $selected_category ? '&category=' . $selected_category : '' ?>" 
                       class="alpha-link <?= $selected_letter === $letter ? 'active' : '' ?>">
                        <?= $letter ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Results Summary -->
    <section class="results-section">
        <div class="container">
            <div class="results-header">
                <h2 class="results-title">
                    <?php if (!empty($search)): ?>
                        Search results for "<?= htmlspecialchars($search) ?>"
                    <?php elseif (!empty($selected_letter)): ?>
                        Brands starting with "<?= $selected_letter ?>"
                    <?php elseif ($selected_category > 0): ?>
                        Brands in selected category
                    <?php else: ?>
                        All Brands
                    <?php endif; ?>
                </h2>
                <p class="results-count"><?= number_format($total_brands) ?> brands found</p>
            </div>

            <!-- Brands Grid -->
            <?php if (empty($paginated_brands)): ?>
                <div class="empty-state">
                    <i class="fas fa-copyright"></i>
                    <h3>No brands found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                    <a href="<?= BASE_URL ?>pages/brands.php" class="btn-primary">Clear Filters</a>
                </div>
            <?php else: ?>
                <div class="brands-grid">
                    <?php foreach ($paginated_brands as $brand): ?>
                        <a href="<?= BASE_URL ?>pages/products.php?brand=<?= urlencode($brand['brand']) ?>" class="brand-card">
                            <?php if (!empty($brand['sample_image'])): ?>
                                <img src="<?= BASE_URL ?>uploads/products/thumbs/<?= htmlspecialchars($brand['sample_image']) ?>" 
                                     alt="<?= htmlspecialchars($brand['brand']) ?>"
                                     class="brand-image">
                            <?php else: ?>
                                <div class="brand-icon">
                                    <i class="fas fa-copyright"></i>
                                </div>
                            <?php endif; ?>
                            
                            <h3 class="brand-name"><?= htmlspecialchars($brand['brand']) ?></h3>
                            
                            <div class="brand-stats">
                                <span class="product-count">
                                    <i class="fas fa-box"></i>
                                    <?= number_format($brand['product_count']) ?> products
                                </span>
                                <span class="price-range">
                                    <i class="fas fa-tag"></i>
                                    ₦<?= number_format($brand['min_price']) ?> - ₦<?= number_format($brand['max_price']) ?>
                                </span>
                            </div>
                            
                            <span class="shop-now">
                                Shop Now <i class="fas fa-arrow-right"></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1&letter=<?= $selected_letter ?>&category=<?= $selected_category ?>&search=<?= urlencode($search) ?>" 
                               class="page-link first">&laquo;</a>
                            <a href="?page=<?= $page - 1 ?>&letter=<?= $selected_letter ?>&category=<?= $selected_category ?>&search=<?= urlencode($search) ?>" 
                               class="page-link prev">&lsaquo;</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&letter=<?= $selected_letter ?>&category=<?= $selected_category ?>&search=<?= urlencode($search) ?>" 
                               class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&letter=<?= $selected_letter ?>&category=<?= $selected_category ?>&search=<?= urlencode($search) ?>" 
                               class="page-link next">&rsaquo;</a>
                            <a href="?page=<?= $total_pages ?>&letter=<?= $selected_letter ?>&category=<?= $selected_category ?>&search=<?= urlencode($search) ?>" 
                               class="page-link last">&raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Popular Brands Cloud -->
    <?php if (!empty($brands) && empty($search) && $selected_category == 0 && empty($selected_letter)): ?>
    <section class="cloud-section">
        <div class="container">
            <h2 class="section-title">Popular Brands</h2>
            <div class="brand-cloud">
                <?php 
                // Calculate font sizes based on product count
                $max_count = max(array_column($brands, 'product_count'));
                $min_count = min(array_column($brands, 'product_count'));
                
                foreach (array_slice($brands, 0, 30) as $brand): 
                    $size = 0.8 + (($brand['product_count'] - $min_count) / ($max_count - $min_count)) * 1.2;
                ?>
                    <a href="<?= BASE_URL ?>pages/products.php?brand=<?= urlencode($brand['brand']) ?>" 
                       class="cloud-item"
                       style="font-size: <?= $size ?>rem;">
                        <?= htmlspecialchars($brand['brand']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Why Shop by Brand -->
    <section class="info-section">
        <div class="container">
            <h2 class="section-title">Why Shop by Brand?</h2>
            <div class="info-grid">
                <div class="info-card">
                    <i class="fas fa-check-circle"></i>
                    <h3>Quality Assurance</h3>
                    <p>Brands represent established quality standards and reliable products you can trust.</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-search"></i>
                    <h3>Easy Discovery</h3>
                    <p>Find all products from your favorite brands in one convenient place.</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-tag"></i>
                    <h3>Best Deals</h3>
                    <p>Compare prices and find the best deals across different brands.</p>
                </div>
                <div class="info-card">
                    <i class="fas fa-star"></i>
                    <h3>Authentic Products</h3>
                    <p>All our products are 100% authentic and sourced directly from brands.</p>
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

/* Search Section */
.search-section {
    padding: 3rem 0;
    background: white;
    border-bottom: 1px solid var(--border);
}

.search-form {
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
    border-radius: 50px;
    font-size: 1rem;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.search-btn {
    padding: 1rem 2rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-left: 1rem;
    white-space: nowrap;
}

.search-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.category-filter {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.category-filter select {
    padding: 0.75rem 2rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.95rem;
    cursor: pointer;
    min-width: 250px;
}

/* Featured Section */
.featured-section {
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

.featured-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.featured-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.featured-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.brand-image {
    width: 80px;
    height: 80px;
    object-fit: contain;
    margin: 0 auto 1rem;
}

.brand-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1rem;
}

.brand-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.brand-count {
    color: var(--text-light);
    font-size: 0.9rem;
}

/* Alphabet Filter */
.alphabet-filter {
    padding: 2rem 0;
    background: white;
}

.alphabet-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.alpha-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--border);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text);
    font-weight: 600;
    transition: all 0.3s;
}

.alpha-link:hover {
    background: var(--bg);
    color: var(--primary);
    border-color: var(--primary);
}

.alpha-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Results Section */
.results-section {
    padding: 3rem 0;
    background: var(--bg);
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.results-title {
    font-size: 1.5rem;
    color: var(--text);
}

.results-count {
    color: var(--text-light);
    font-size: 1.1rem;
}

/* Brands Grid */
.brands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.brand-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.brand-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.brand-card .brand-image {
    width: 100px;
    height: 100px;
    margin-bottom: 1.5rem;
}

.brand-card .brand-icon {
    width: 100px;
    height: 100px;
    margin-bottom: 1.5rem;
}

.brand-stats {
    margin: 1rem 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.brand-stats span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-light);
    font-size: 0.9rem;
}

.brand-stats i {
    color: var(--primary);
    width: 20px;
}

.shop-now {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--primary);
    font-weight: 600;
    margin-top: 1rem;
    transition: gap 0.3s;
}

.brand-card:hover .shop-now {
    gap: 0.8rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 16px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-lighter);
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.btn-primary {
    display: inline-block;
    padding: 1rem 2rem;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.page-link {
    display: inline-flex;
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

/* Brand Cloud */
.cloud-section {
    padding: 4rem 0;
    background: white;
}

.brand-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 2rem;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
}

.cloud-item {
    color: var(--text);
    text-decoration: none;
    transition: all 0.3s;
    display: inline-block;
}

.cloud-item:hover {
    color: var(--primary);
    transform: scale(1.1);
}

/* Info Section */
.info-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.info-card {
    text-align: center;
    padding: 2rem;
}

.info-card i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.info-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.info-card p {
    opacity: 0.9;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 1200px) {
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .search-wrapper {
        flex-direction: column;
        gap: 1rem;
    }
    
    .search-btn {
        margin-left: 0;
    }
    
    .category-filter {
        flex-direction: column;
        align-items: stretch;
    }
    
    .category-filter select {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .featured-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .brands-grid {
        grid-template-columns: 1fr;
    }
    
    .results-header {
        flex-direction: column;
        text-align: center;
    }
    
    .alphabet-grid {
        padding: 0 1rem;
    }
    
    .alpha-link {
        width: 35px;
        height: 35px;
        font-size: 0.9rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .featured-grid {
        grid-template-columns: 1fr;
    }
    
    .brand-cloud {
        gap: 0.8rem;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Alphabet filter smooth scroll
    document.querySelectorAll('.alpha-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href') !== '#') {
                // Allow normal navigation for actual links
                return;
            }
            e.preventDefault();
            // Smooth scroll to brands section
            document.querySelector('.results-section').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });

    // Brand card hover effects
    document.querySelectorAll('.brand-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });

    // Search form validation
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('.search-input');
            if (searchInput.value.trim() === '') {
                e.preventDefault();
                searchInput.focus();
                searchInput.style.borderColor = 'var(--danger)';
                setTimeout(() => {
                    searchInput.style.borderColor = '';
                }, 2000);
            }
        });
    }

    // Lazy load brand images
    const brandImages = document.querySelectorAll('.brand-image');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    imageObserver.unobserve(img);
                }
            });
        });

        brandImages.forEach(img => imageObserver.observe(img));
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>