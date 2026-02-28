<?php
// pages/sitemap.php - Sitemap Page

$page_title = "Sitemap";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get all categories
try {
    $stmt = $pdo->query("
        SELECT id, name, slug, 
               (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count
        FROM categories c
        WHERE status = 'active'
        ORDER BY name ASC
    ");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

// Get all brands
try {
    $stmt = $pdo->query("
        SELECT DISTINCT brand, COUNT(*) as product_count
        FROM products
        WHERE brand IS NOT NULL AND brand != '' AND status = 'active'
        GROUP BY brand
        ORDER BY brand ASC
    ");
    $brands = $stmt->fetchAll();
} catch (Exception $e) {
    $brands = [];
}

// Get blog posts
try {
    $stmt = $pdo->query("
        SELECT id, title, slug, created_at
        FROM blog_posts
        WHERE status = 'published'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $blog_posts = $stmt->fetchAll();
} catch (Exception $e) {
    $blog_posts = [];
}

// Get pages
$static_pages = [
    ['url' => '', 'title' => 'Home', 'priority' => '1.0'],
    ['url' => 'pages/products.php', 'title' => 'All Products', 'priority' => '0.9'],
    ['url' => 'pages/categories.php', 'title' => 'Categories', 'priority' => '0.8'],
    ['url' => 'pages/new-arrivals.php', 'title' => 'New Arrivals', 'priority' => '0.8'],
    ['url' => 'pages/best-sellers.php', 'title' => 'Best Sellers', 'priority' => '0.8'],
    ['url' => 'pages/deals.php', 'title' => 'Hot Deals', 'priority' => '0.8'],
    ['url' => 'pages/about.php', 'title' => 'About Us', 'priority' => '0.6'],
    ['url' => 'pages/contact.php', 'title' => 'Contact Us', 'priority' => '0.6'],
    ['url' => 'pages/faq.php', 'title' => 'FAQ', 'priority' => '0.6'],
    ['url' => 'pages/shipping.php', 'title' => 'Shipping Information', 'priority' => '0.6'],
    ['url' => 'pages/returns.php', 'title' => 'Returns & Refunds', 'priority' => '0.6'],
    ['url' => 'pages/size-guide.php', 'title' => 'Size Guide', 'priority' => '0.6'],
    ['url' => 'pages/privacy-policy.php', 'title' => 'Privacy Policy', 'priority' => '0.5'],
    ['url' => 'pages/terms-of-service.php', 'title' => 'Terms of Service', 'priority' => '0.5'],
    ['url' => 'pages/cookie-policy.php', 'title' => 'Cookie Policy', 'priority' => '0.5'],
    ['url' => 'pages/sitemap.php', 'title' => 'Sitemap', 'priority' => '0.5'],
];

// Get account pages
$account_pages = [
    ['url' => 'pages/login.php', 'title' => 'Login', 'priority' => '0.4'],
    ['url' => 'pages/register.php', 'title' => 'Register', 'priority' => '0.4'],
    ['url' => 'pages/forgot-password.php', 'title' => 'Forgot Password', 'priority' => '0.3'],
    ['url' => 'pages/profile.php', 'title' => 'My Profile', 'priority' => '0.5'],
    ['url' => 'pages/orders.php', 'title' => 'My Orders', 'priority' => '0.5'],
    ['url' => 'pages/wishlist.php', 'title' => 'Wishlist', 'priority' => '0.5'],
    ['url' => 'pages/cart.php', 'title' => 'Shopping Cart', 'priority' => '0.7'],
    ['url' => 'pages/checkout.php', 'title' => 'Checkout', 'priority' => '0.7'],
];

// Get total counts
$total_products = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $total_products = $stmt->fetchColumn();
} catch (Exception $e) {}

$total_categories = count($categories);
$total_brands = count($brands);
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Sitemap</h1>
            <p class="header-description">Find your way around our website</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Sitemap</span>
            </div>
        </div>
    </section>

    <!-- Site Stats -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-box"></i>
                    <div class="stat-number"><?= number_format($total_products) ?></div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-tags"></i>
                    <div class="stat-number"><?= number_format($total_categories) ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-copyright"></i>
                    <div class="stat-number"><?= number_format($total_brands) ?></div>
                    <div class="stat-label">Brands</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-file-alt"></i>
                    <div class="stat-number"><?= number_format(count($static_pages) + count($account_pages)) ?></div>
                    <div class="stat-label">Pages</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sitemap Grid -->
    <section class="sitemap-section">
        <div class="container">
            <div class="sitemap-grid">
                <!-- Main Pages -->
                <div class="sitemap-category">
                    <h2><i class="fas fa-home"></i> Main Pages</h2>
                    <ul class="sitemap-list">
                        <?php foreach ($static_pages as $page): ?>
                            <li>
                                <a href="<?= BASE_URL . $page['url'] ?>">
                                    <?= htmlspecialchars($page['title']) ?>
                                    <?php if ($page['priority'] >= 0.8): ?>
                                        <span class="priority-high">Popular</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Categories -->
                <?php if (!empty($categories)): ?>
                    <div class="sitemap-category">
                        <h2><i class="fas fa-tags"></i> Categories</h2>
                        <ul class="sitemap-list">
                            <?php foreach ($categories as $category): ?>
                                <li>
                                    <a href="<?= BASE_URL ?>pages/category.php?id=<?= $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                        <span class="count">(<?= number_format($category['product_count']) ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Brands -->
                <?php if (!empty($brands)): ?>
                    <div class="sitemap-category">
                        <h2><i class="fas fa-copyright"></i> Brands</h2>
                        <ul class="sitemap-list">
                            <?php foreach ($brands as $brand): ?>
                                <li>
                                    <a href="<?= BASE_URL ?>pages/products.php?brand=<?= urlencode($brand['brand']) ?>">
                                        <?= htmlspecialchars($brand['brand']) ?>
                                        <span class="count">(<?= number_format($brand['product_count']) ?>)</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Account Pages -->
                <div class="sitemap-category">
                    <h2><i class="fas fa-user"></i> Account</h2>
                    <ul class="sitemap-list">
                        <?php foreach ($account_pages as $page): ?>
                            <li>
                                <a href="<?= BASE_URL . $page['url'] ?>">
                                    <?= htmlspecialchars($page['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Blog Posts -->
                <?php if (!empty($blog_posts)): ?>
                    <div class="sitemap-category">
                        <h2><i class="fas fa-blog"></i> Recent Blog Posts</h2>
                        <ul class="sitemap-list">
                            <?php foreach ($blog_posts as $post): ?>
                                <li>
                                    <a href="<?= BASE_URL ?>blog/<?= htmlspecialchars($post['slug']) ?>">
                                        <?= htmlspecialchars($post['title']) ?>
                                        <span class="date"><?= date('M d, Y', strtotime($post['created_at'])) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Support Pages -->
                <div class="sitemap-category">
                    <h2><i class="fas fa-headset"></i> Support</h2>
                    <ul class="sitemap-list">
                        <li><a href="<?= BASE_URL ?>pages/support.php">Support Center</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php">Frequently Asked Questions</a></li>
                        <li><a href="<?= BASE_URL ?>pages/contact.php">Contact Us</a></li>
                        <li><a href="<?= BASE_URL ?>pages/shipping.php">Shipping Information</a></li>
                        <li><a href="<?= BASE_URL ?>pages/returns.php">Returns & Refunds</a></li>
                        <li><a href="<?= BASE_URL ?>pages/size-guide.php">Size Guide</a></li>
                    </ul>
                </div>

                <!-- Legal Pages -->
                <div class="sitemap-category">
                    <h2><i class="fas fa-gavel"></i> Legal</h2>
                    <ul class="sitemap-list">
                        <li><a href="<?= BASE_URL ?>pages/privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="<?= BASE_URL ?>pages/terms-of-service.php">Terms of Service</a></li>
                        <li><a href="<?= BASE_URL ?>pages/cookie-policy.php">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- XML Sitemap Notice -->
    <section class="xml-section">
        <div class="container">
            <div class="xml-card">
                <i class="fas fa-code"></i>
                <div class="xml-content">
                    <h3>XML Sitemap</h3>
                    <p>For search engines and developers, we also provide an XML sitemap.</p>
                    <a href="<?= BASE_URL ?>sitemap.xml" class="xml-link" target="_blank">
                        View XML Sitemap <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Search Box -->
    <section class="search-section">
        <div class="container">
            <div class="search-card">
                <h2>Can't find what you're looking for?</h2>
                <p>Try searching our website</p>
                <form action="<?= BASE_URL ?>pages/search.php" method="get" class="search-form">
                    <input type="text" name="q" placeholder="Search products, categories, pages..." required>
                    <button type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
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

/* Stats Section */
.stats-section {
    padding: 3rem 0;
    background: white;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.stat-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 0.25rem;
}

.stat-label {
    color: var(--text-light);
}

/* Sitemap Section */
.sitemap-section {
    padding: 4rem 0;
    background: var(--bg);
}

.sitemap-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.sitemap-category {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.sitemap-category:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.sitemap-category h2 {
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sitemap-category h2 i {
    color: var(--primary);
}

.sitemap-list {
    list-style: none;
}

.sitemap-list li {
    margin-bottom: 0.75rem;
    line-height: 1.5;
}

.sitemap-list a {
    color: var(--text-light);
    text-decoration: none;
    transition: color 0.3s;
    display: inline-block;
    width: 100%;
}

.sitemap-list a:hover {
    color: var(--primary);
    transform: translateX(5px);
}

.sitemap-list .count,
.sitemap-list .date {
    color: var(--text-lighter);
    font-size: 0.85rem;
    margin-left: 0.5rem;
}

.priority-high {
    display: inline-block;
    background: #fee2e2;
    color: #991b1b;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-left: 0.5rem;
    font-weight: 600;
}

/* XML Section */
.xml-section {
    padding: 3rem 0;
    background: white;
}

.xml-card {
    max-width: 600px;
    margin: 0 auto;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 2rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 2rem;
    box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
}

.xml-card i {
    font-size: 3rem;
}

.xml-content h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
}

.xml-content p {
    opacity: 0.9;
    margin-bottom: 1rem;
}

.xml-link {
    display: inline-block;
    color: white;
    text-decoration: none;
    font-weight: 600;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    transition: all 0.3s;
}

.xml-link:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.xml-link i {
    font-size: 0.9rem;
    margin-left: 0.5rem;
}

/* Search Section */
.search-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.search-card {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    color: white;
}

.search-card h2 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.search-card p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.search-form {
    display: flex;
    gap: 1rem;
    background: white;
    padding: 0.5rem;
    border-radius: 50px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.search-form input {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    background: transparent;
}

.search-form input:focus {
    outline: none;
}

.search-form button {
    padding: 1rem 2rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.search-form button:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .xml-card {
        flex-direction: column;
        text-align: center;
    }
    
    .search-form {
        flex-direction: column;
        background: transparent;
        gap: 1rem;
    }
    
    .search-form input,
    .search-form button {
        border-radius: 50px;
    }
    
    .search-form button {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .sitemap-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Add smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Track sitemap link clicks
document.querySelectorAll('.sitemap-list a').forEach(link => {
    link.addEventListener('click', function(e) {
        console.log('Sitemap link clicked:', this.href);
        // You could add analytics tracking here
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>