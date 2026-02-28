<?php
// pages/categories.php - Categories Listing Page

$page_title = "Shop by Category";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get all categories with product counts
$stmt = $pdo->query("
    SELECT c.*, 
           COUNT(p.id) AS product_count,
           MAX(pi.filename) AS sample_image
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_main = 1
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC, c.name ASC
");
$categories = $stmt->fetchAll();

// Get featured categories
$stmt = $pdo->query("
    SELECT c.*, 
           COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active' AND c.featured = 1
    GROUP BY c.id
    ORDER BY c.display_order ASC
    LIMIT 6
");
$featured_categories = $stmt->fetchAll();
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Shop by Category</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Categories</span>
            </div>
        </div>
    </section>

    <!-- Featured Categories Section -->
    <?php if (!empty($featured_categories)): ?>
    <section class="featured-categories section">
        <div class="container">
            <h2 class="section-title">Featured Categories</h2>
            <div class="featured-grid">
                <?php foreach ($featured_categories as $category): ?>
                    <a href="<?= BASE_URL ?>pages/category.php?id=<?= $category['id'] ?>" class="featured-card">
                        <?php if (!empty($category['image'])): ?>
                            <img src="<?= BASE_URL ?>uploads/categories/<?= htmlspecialchars($category['image']) ?>" 
                                 alt="<?= htmlspecialchars($category['name']) ?>" class="category-image">
                        <?php else: ?>
                            <div class="category-icon">
                                <i class="fas fa-folder"></i>
                            </div>
                        <?php endif; ?>
                        <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                        <span class="product-count"><?= number_format($category['product_count']) ?> Products</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- All Categories Grid -->
    <section class="all-categories section">
        <div class="container">
            <h2 class="section-title">All Categories</h2>
            
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Categories Found</h3>
                    <p>Check back later for new categories</p>
                </div>
            <?php else: ?>
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <a href="<?= BASE_URL ?>pages/category.php?id=<?= $category['id'] ?>" class="category-card">
                            <?php if (!empty($category['image'])): ?>
                                <img src="<?= BASE_URL ?>uploads/categories/<?= htmlspecialchars($category['image']) ?>" 
                                     alt="<?= htmlspecialchars($category['name']) ?>" class="category-image">
                            <?php else: ?>
                                <div class="category-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                            <?php endif; ?>
                            <div class="category-info">
                                <h3 class="category-name"><?= htmlspecialchars($category['name']) ?></h3>
                                <p class="category-description">
                                    <?= htmlspecialchars(substr($category['description'] ?? '', 0, 100)) ?>
                                    <?= strlen($category['description'] ?? '') > 100 ? '...' : '' ?>
                                </p>
                                <span class="product-count">
                                    <?= number_format($category['product_count']) ?> products available
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Shop by Lifestyle Section -->
    <section class="lifestyle-section section bg-light">
        <div class="container">
            <h2 class="section-title">Shop by Lifestyle</h2>
            <div class="lifestyle-grid">
                <a href="<?= BASE_URL ?>pages/products.php?tag=men" class="lifestyle-card men">
                    <div class="lifestyle-content">
                        <h3>Men's Collection</h3>
                        <p>Discover fashion for men</p>
                        <span class="btn-link">Shop Now →</span>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>pages/products.php?tag=women" class="lifestyle-card women">
                    <div class="lifestyle-content">
                        <h3>Women's Collection</h3>
                        <p>Trendy styles for women</p>
                        <span class="btn-link">Shop Now →</span>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>pages/products.php?tag=kids" class="lifestyle-card kids">
                    <div class="lifestyle-content">
                        <h3>Kids' Collection</h3>
                        <p>Cute and comfortable</p>
                        <span class="btn-link">Shop Now →</span>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>pages/products.php?tag=home" class="lifestyle-card home">
                    <div class="lifestyle-content">
                        <h3>Home & Living</h3>
                        <p>Make your home beautiful</p>
                        <span class="btn-link">Shop Now →</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter-section">
        <div class="container">
            <div class="newsletter-content">
                <h2>Never Miss a Deal!</h2>
                <p>Subscribe to get updates on new arrivals and special offers</p>
                <form class="newsletter-form" id="categoryNewsletterForm">
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

.breadcrumb {
    font-size: 1rem;
}

.breadcrumb a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
}

.breadcrumb a:hover {
    color: white;
    text-decoration: underline;
}

.breadcrumb .separator {
    margin: 0 0.5rem;
    color: rgba(255, 255, 255, 0.5);
}

.breadcrumb .current {
    color: white;
}

/* Sections */
.section {
    padding: 5rem 0;
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

.bg-light {
    background: #f8f9fc;
}

/* Featured Categories */
.featured-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
}

.featured-card {
    background: white;
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    text-decoration: none;
    color: var(--text);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.featured-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.featured-card .category-icon {
    width: 80px;
    height: 80px;
    background: var(--primary-light);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1rem;
}

.featured-card .category-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 1rem;
}

.featured-card .category-name {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.featured-card .product-count {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Categories Grid */
.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.category-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.category-card .category-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.category-card .category-icon {
    height: 200px;
    background: linear-gradient(135deg, var(--primary-light), var(--primary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
}

.category-info {
    padding: 1.5rem;
}

.category-name {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.category-description {
    color: var(--text-light);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.product-count {
    color: var(--primary);
    font-weight: 600;
}

/* Lifestyle Grid */
.lifestyle-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.lifestyle-card {
    position: relative;
    height: 300px;
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    color: white;
}

.lifestyle-card.men {
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1617137968427-85924d800a71?w=600');
    background-size: cover;
    background-position: center;
}

.lifestyle-card.women {
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600');
    background-size: cover;
    background-position: center;
}

.lifestyle-card.kids {
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1514090458221-65bb69cf3436?w=600');
    background-size: cover;
    background-position: center;
}

.lifestyle-card.home {
    background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://images.unsplash.com/photo-1484101403633-562f891dc89a?w=600');
    background-size: cover;
    background-position: center;
}

.lifestyle-content {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 2rem;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
}

.lifestyle-content h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.lifestyle-content p {
    margin-bottom: 1rem;
    opacity: 0.9;
}

.lifestyle-content .btn-link {
    color: white;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: transform 0.3s;
}

.lifestyle-card:hover .btn-link {
    transform: translateX(10px);
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
    background: var(--primary-dark);
    color: white;
    transform: translateY(-2px);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem;
    color: var(--text-light);
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: var(--text-lighter);
}

.empty-state h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        padding: 3rem 0;
    }
    
    .page-title {
        font-size: 2rem;
    }
    
    .section {
        padding: 3rem 0;
    }
    
    .section-title {
        font-size: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .featured-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .lifestyle-grid {
        grid-template-columns: 1fr;
    }
    
    .newsletter-form {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .featured-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.getElementById('categoryNewsletterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const email = this.querySelector('input').value;
    
    // Simulate subscription (replace with actual AJAX)
    alert('Thank you for subscribing! Check your email for confirmation.');
    this.reset();
});
</script>

<?php require_once '../includes/footer.php'; ?>