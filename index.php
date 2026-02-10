<?php
// index.php - Homepage (AliExpress-like layout)

$page_title = "Home";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Fetch featured categories
try {
    $catStmt = $pdo->query("
        SELECT id, name, slug 
        FROM categories 
        WHERE status = 'active' 
        ORDER BY display_order, name 
        LIMIT 8
    ");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Fetch featured products
try {
    $featuredStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active' AND p.featured = 1
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $featured_products = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featured_products = [];
}

// Fetch new arrivals
try {
    $newStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $new_products = $newStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $new_products = [];
}

// Fetch best sellers (based on views)
try {
    $bestStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active'
        ORDER BY p.views DESC, p.created_at DESC
        LIMIT 8
    ");
    $best_products = $bestStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $best_products = [];
}

// Fetch on-sale products
try {
    $saleStmt = $pdo->query("
        SELECT p.id, p.name, p.slug, p.price, p.discount_price, p.stock, p.brand,
               (SELECT filename FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image
        FROM products p
        WHERE p.status = 'active' AND p.discount_price IS NOT NULL
        ORDER BY RAND()
        LIMIT 8
    ");
    $sale_products = $saleStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sale_products = [];
}
?>

<style>
/* Homepage Specific Styles */
.homepage {
    overflow: hidden;
}

/* Hero Banner */
.hero-banner {
    background: linear-gradient(135deg, rgba(30, 64, 175, 0.9), rgba(59, 130, 246, 0.8));
    color: white;
    padding: var(--space-3xl) 0;
    position: relative;
    overflow: hidden;
    margin-bottom: var(--space-2xl);
}

.hero-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('https://images.unsplash.com/photo-1556740714-a8395b3a74dd?w=1600&auto=format&fit=crop') center/cover;
    z-index: -1;
    animation: zoomIn 20s infinite alternate;
}

@keyframes zoomIn {
    from { transform: scale(1); }
    to { transform: scale(1.1); }
}

.hero-content {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    position: relative;
    z-index: 2;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: var(--fw-extrabold);
    margin-bottom: var(--space-lg);
    line-height: 1.2;
    text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
    animation: fadeInUp 0.8s ease;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
}

.hero-subtitle {
    font-size: 1.4rem;
    margin-bottom: var(--space-xl);
    opacity: 0.9;
    animation: fadeInUp 1s ease;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.hero-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 1.2s ease;
}

.hero-btn {
    padding: var(--space-lg) var(--space-2xl);
    font-size: 1.2rem;
    font-weight: var(--fw-bold);
    border-radius: var(--radius-lg);
    text-decoration: none;
    transition: all var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    box-shadow: var(--shadow-lg);
}

.hero-btn.primary {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
}

.hero-btn.primary:hover {
    background: linear-gradient(135deg, #dc2626, var(--danger));
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
}

.hero-btn.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.hero-btn.secondary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-3px);
    border-color: rgba(255, 255, 255, 0.4);
}

/* Features Section */
.features-section {
    padding: var(--space-3xl) 0;
    background: var(--white);
}

.section-title {
    text-align: center;
    font-size: 2.5rem;
    font-weight: var(--fw-extrabold);
    margin-bottom: var(--space-xl);
    color: var(--text);
}

.section-subtitle {
    text-align: center;
    color: var(--text-light);
    font-size: 1.1rem;
    max-width: 700px;
    margin: 0 auto var(--space-2xl);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-xl);
}

.feature-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    text-align: center;
    transition: all var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    border-radius: 4px 4px 0 0;
}

.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-light);
}

.feature-icon {
    font-size: 3rem;
    margin-bottom: var(--space-lg);
    color: var(--primary);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.feature-card:nth-child(2) .feature-icon {
    color: var(--success);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
}

.feature-card:nth-child(3) .feature-icon {
    color: #fbbf24;
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.1), rgba(251, 191, 36, 0.05));
}

.feature-card:nth-child(4) .feature-icon {
    color: var(--primary-light);
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05));
}

.feature-title {
    font-size: 1.35rem;
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-sm);
    color: var(--text);
}

.feature-description {
    color: var(--text-light);
    line-height: 1.6;
}

/* Categories Section */
.categories-section {
    padding: var(--space-3xl) 0;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: var(--space-lg);
}

.category-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.category-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: var(--primary-light);
}

.category-icon {
    font-size: 2.5rem;
    margin-bottom: var(--space-md);
    color: var(--primary);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.category-name {
    font-size: 1.1rem;
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-xs);
}

.category-count {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Products Section */
.products-section {
    padding: var(--space-3xl) 0;
    background: var(--white);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xl);
    flex-wrap: wrap;
    gap: var(--space-md);
}

.section-header h2 {
    margin: 0;
}

.view-all-link {
    color: var(--primary);
    font-weight: var(--fw-bold);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    transition: all var(--transition);
}

.view-all-link:hover {
    color: var(--primary-dark);
    gap: var(--space-sm);
}

/* Updated Product Card Styles - Professional & Compact */
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
    border: 1px solid var(--border);
}

.product-image {
    height: 180px; /* Reduced from 220px */
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

.product-info {
    padding: var(--space-md); /* Reduced from var(--space-lg) */
}

.product-name {
    font-size: 0.95rem; /* Reduced from 1.05rem */
    font-weight: var(--fw-medium);
    margin-bottom: var(--space-sm); /* Reduced from var(--space-md) */
    color: var(--text);
    line-height: 1.4;
    height: 2.6em; /* Reduced from 2.8em */
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.product-price {
    margin: var(--space-sm) 0; /* Reduced from var(--space-md) */
}

.product-price-current {
    font-size: 1.2rem; /* Reduced from 1.35rem */
    font-weight: var(--fw-bold);
    color: var(--danger);
    display: block;
}

.product-price-old {
    font-size: 0.85rem; /* Reduced from 0.95rem */
    color: var(--text-lighter);
    text-decoration: line-through;
    margin-right: var(--space-xs); /* Reduced from var(--space-sm) */
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 1px; /* Reduced from 2px */
    color: #fbbf24;
    font-size: 0.85rem; /* Reduced from 0.9rem */
    margin-bottom: var(--space-xs); /* Reduced from var(--space-sm) */
}

.product-rating span {
    color: var(--text-lighter);
    margin-left: var(--space-xs);
    font-weight: var(--fw-medium);
    font-size: 0.85rem;
}

.product-shipping {
    font-size: 0.8rem; /* Reduced from 0.85rem */
    color: var(--success);
    font-weight: var(--fw-medium);
    margin-top: var(--space-sm); /* Reduced from var(--space-md) */
    display: flex;
    align-items: center;
    gap: 3px; /* Reduced from var(--space-xs) */
}

/* For Homepage Products */
.homepage .product-image {
    height: 160px; /* Even more compact for homepage */
}

.homepage .product-info {
    padding: 0.75rem; /* More compact padding */
}

.homepage .product-name {
    font-size: 0.9rem;
    height: 2.4em;
}

.homepage .product-price-current {
    font-size: 1.1rem;
}

/* Compact Badge Styles */
.product-discount {
    position: absolute;
    top: 8px; /* Reduced from var(--space-sm) */
    left: 8px; /* Reduced from var(--space-sm) */
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
    padding: 4px 8px; /* Reduced padding */
    border-radius: 4px; /* Smaller radius */
    font-size: 0.7rem; /* Smaller font */
    font-weight: var(--fw-bold);
    z-index: 2;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Lighter shadow */
}

.product-badges {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.product-badge {
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 3px 6px; /* More compact */
    border-radius: 3px; /* Smaller */
    font-size: 0.65rem; /* Smaller */
    font-weight: var(--fw-bold);
    backdrop-filter: blur(4px);
    text-align: center;
    line-height: 1;
}

/* Save amount badge */
.product-save {
    display: inline-block;
    background: var(--danger);
    color: white;
    padding: 1px 6px; /* More compact */
    border-radius: 3px;
    font-size: 0.7rem; /* Smaller */
    font-weight: var(--fw-bold);
    margin-left: 4px;
    vertical-align: middle;
}

/* Stock indicator */
.stock-indicator {
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: var(--fw-medium);
    display: inline-block;
    margin-top: 4px;
}

/* Grid adjustments for more compact layout */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); /* Smaller min-width */
    gap: 1rem; /* Reduced from var(--space-lg) */
    margin-bottom: var(--space-xl);
}

@media (max-width: 768px) {
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 0.75rem;
    }
    
    .product-image {
        height: 150px;
    }
    
    .product-name {
        font-size: 0.85rem;
        height: 2.4em;
    }
    
    .product-price-current {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .product-image {
        height: 140px;
    }
    
    .product-info {
        padding: 0.5rem;
    }
    
    .product-name {
        font-size: 0.8rem;
        height: 2.2em;
    }
    
    .product-price-current {
        font-size: 1rem;
    }
    
    .product-discount {
        font-size: 0.6rem;
        padding: 2px 6px;
    }
}

/* Quick view button - more compact */
.quick-view-btn {
    position: absolute;
    bottom: 8px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: var(--fw-medium);
    cursor: pointer;
    opacity: 0;
    transition: all var(--transition);
    backdrop-filter: blur(4px);
    white-space: nowrap;
}

.product-card:hover .quick-view-btn {
    opacity: 1;
    transform: translateX(-50%) translateY(-3px);
}

/* Product hover effect - subtle */
.product-card:hover {
    transform: translateY(-4px); /* Reduced from -8px */
    box-shadow: var(--shadow-md); /* Reduced from var(--shadow-lg) */
    border-color: var(--primary-light);
}

/* Category cards - more compact */
.category-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: var(--space-md); /* Reduced from var(--space-lg) */
    text-align: center;
    text-decoration: none;
    color: var(--text);
    transition: all var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.category-icon {
    font-size: 2rem; /* Reduced from 2.5rem */
    margin-bottom: var(--space-sm);
    color: var(--primary);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
    width: 60px; /* Reduced from 70px */
    height: 60px; /* Reduced from 70px */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.category-name {
    font-size: 1rem; /* Reduced from 1.1rem */
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-xs);
}

.category-count {
    font-size: 0.85rem; /* Reduced from 0.9rem */
    color: var(--text-light);
}

/* Feature cards - more compact */
.feature-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: var(--space-lg); /* Reduced from var(--space-xl) */
    text-align: center;
    transition: all var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.feature-icon {
    font-size: 2.5rem; /* Reduced from 3rem */
    margin-bottom: var(--space-md); /* Reduced from var(--space-lg) */
    color: var(--primary);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(59, 130, 246, 0.05));
    width: 70px; /* Reduced from 80px */
    height: 70px; /* Reduced from 80px */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: auto;
    margin-right: auto;
}

.feature-title {
    font-size: 1.2rem; /* Reduced from 1.35rem */
    font-weight: var(--fw-bold);
    margin-bottom: var(--space-xs); /* Reduced from var(--space-sm) */
    color: var(--text);
}

.feature-description {
    color: var(--text-light);
    line-height: 1.6;
    font-size: 0.95rem; /* Added smaller font size */
}
/* Sale Banner */
.sale-banner {
    background: linear-gradient(135deg, var(--danger), #f87171);
    color: white;
    padding: var(--space-2xl);
    border-radius: var(--radius-lg);
    text-align: center;
    margin: var(--space-3xl) 0;
    position: relative;
    overflow: hidden;
}

.sale-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 10px,
        rgba(255, 255, 255, 0.05) 10px,
        rgba(255, 255, 255, 0.05) 20px
    );
    animation: slide 20s linear infinite;
}

@keyframes slide {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.sale-content {
    position: relative;
    z-index: 2;
}

.sale-title {
    font-size: 2.5rem;
    font-weight: var(--fw-extrabold);
    margin-bottom: var(--space-md);
}

.sale-subtitle {
    font-size: 1.2rem;
    margin-bottom: var(--space-lg);
    opacity: 0.9;
}

.sale-timer {
    font-size: 2rem;
    font-weight: var(--fw-bold);
    font-family: monospace;
    background: rgba(0, 0, 0, 0.2);
    padding: var(--space-md) var(--space-lg);
    border-radius: var(--radius);
    display: inline-block;
    margin-bottom: var(--space-lg);
}

.sale-btn {
    background: white;
    color: var(--danger);
    padding: var(--space-md) var(--space-2xl);
    border-radius: var(--radius);
    font-weight: var(--fw-bold);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    transition: all var(--transition);
}

.sale-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 255, 255, 0.2);
}

/* Newsletter */
.newsletter-section {
    padding: var(--space-3xl) 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    text-align: center;
}

.newsletter-content {
    max-width: 600px;
    margin: 0 auto;
}

.newsletter-title {
    font-size: 2.5rem;
    font-weight: var(--fw-extrabold);
    margin-bottom: var(--space-md);
}

.newsletter-description {
    font-size: 1.1rem;
    margin-bottom: var(--space-xl);
    opacity: 0.9;
}

.newsletter-form {
    display: flex;
    gap: var(--space-sm);
    max-width: 500px;
    margin: 0 auto;
}

.newsletter-input {
    flex: 1;
    padding: var(--space-lg);
    border: 2px solid transparent;
    border-radius: var(--radius);
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.newsletter-input:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.15);
}

.newsletter-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.newsletter-btn {
    padding: var(--space-lg) var(--space-xl);
    background: white;
    color: var(--primary);
    border: none;
    border-radius: var(--radius);
    font-weight: var(--fw-bold);
    cursor: pointer;
    transition: all var(--transition);
    white-space: nowrap;
}

.newsletter-btn:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 255, 255, 0.2);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in {
    animation: fadeInUp 0.6s ease;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
    }
    
    .hero-actions {
        flex-direction: column;
    }
    
    .hero-btn {
        width: 100%;
        justify-content: center;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .newsletter-form {
        flex-direction: column;
    }
    
    .sale-title {
        font-size: 2rem;
    }
    
    .sale-timer {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 1.75rem;
    }
    
    .products-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .feature-card {
        padding: var(--space-lg);
    }
}
</style>

<div class="homepage">

<!-- Hero Banner -->
<section class="hero-banner">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Discover Amazing Products at Unbeatable Prices</h1>
            <p class="hero-subtitle">Shop thousands of quality items from trusted sellers • Free shipping on orders over ₦50,000 • Secure payments</p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>pages/products.php" class="hero-btn primary">
                    <i class="fas fa-shopping-bag"></i> Start Shopping
                </a>
                <a href="#featured" class="hero-btn secondary">
                    <i class="fas fa-star"></i> View Featured Products
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="categories-section">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <p class="section-subtitle">Browse our wide selection of product categories</p>
        <div class="categories-grid">
            <?php if (empty($categories)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-2xl); color: var(--text-light);">
                    <i class="fas fa-folder-open" style="font-size: 4rem; margin-bottom: var(--space-md);"></i>
                    <h3>No categories available</h3>
                    <p>Categories will be displayed here</p>
                </div>
            <?php else: ?>
                <?php 
                $category_icons = [
                    'fas fa-mobile-alt', 'fas fa-laptop', 'fas fa-tshirt', 'fas fa-shoe-prints',
                    'fas fa-home', 'fas fa-utensils', 'fas fa-dumbbell', 'fas fa-book'
                ];
                $i = 0;
                ?>
                <?php foreach ($categories as $cat): ?>
                    <a href="<?= BASE_URL ?>pages/products.php?category=<?= $cat['id'] ?>" class="category-card">
                        <div class="category-icon">
                            <i class="<?= $category_icons[$i % count($category_icons)] ?>"></i>
                        </div>
                        <h3 class="category-name"><?= htmlspecialchars($cat['name']) ?></h3>
                        <div class="category-count">Browse Products →</div>
                    </a>
                    <?php $i++; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="products-section" id="featured">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Featured Products</h2>
            <a href="<?= BASE_URL ?>pages/products.php?featured=1" class="view-all-link">
                View All Featured <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="products-grid">
            <?php if (empty($featured_products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-2xl); color: var(--text-light);">
                    <i class="fas fa-star" style="font-size: 4rem; margin-bottom: var(--space-md); color: var(--border);"></i>
                    <h3>No featured products</h3>
                    <p>Featured products will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($featured_products as $p): ?>
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
                        $image_src = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f3f4f6"/><text x="100" y="100" font-family="Arial" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">No Image</text></svg>');
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
                                <span class="product-badge" style="background: var(--warning);">FEATURED</span>
                            </div>
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
                                <span>4.8</span>
                            </div>

                            <div class="product-shipping">
                                <i class="fas fa-shipping-fast"></i> Free Shipping
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Sale Banner -->
<section class="sale-banner">
    <div class="container">
        <div class="sale-content">
            <h2 class="sale-title">SUPER DEAL ENDS SOON!</h2>
            <p class="sale-subtitle">Up to 70% OFF on selected items • Limited time offer</p>
            <div class="sale-timer" id="saleTimer">24:59:59</div>
            <a href="<?= BASE_URL ?>pages/products.php?discount_price=1" class="sale-btn">
                <i class="fas fa-fire"></i> Shop Sale Now
            </a>
        </div>
    </div>
</section>

<!-- New Arrivals -->
<section class="products-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">New Arrivals</h2>
            <a href="<?= BASE_URL ?>pages/products.php?sort=newest" class="view-all-link">
                View All New <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="products-grid">
            <?php if (empty($new_products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-2xl); color: var(--text-light);">
                    <i class="fas fa-box-open" style="font-size: 4rem; margin-bottom: var(--space-md); color: var(--border);"></i>
                    <h3>No new arrivals</h3>
                    <p>New products will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach (array_slice($new_products, 0, 8) as $p): ?>
                    <?php
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    $discount_percent = $old_price ? round((($old_price - $final_price) / $old_price) * 100) : 0;
                    
                    // Determine image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                        $image_alt = htmlspecialchars($p['name']);
                    } else {
                        $image_src = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f3f4f6"/><text x="100" y="100" font-family="Arial" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">No Image</text></svg>');
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
                                <span class="product-badge" style="background: var(--success);">NEW</span>
                            </div>
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
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <h2 class="section-title">Why Choose Muhamuktar Global Venture?</h2>
        <p class="section-subtitle">We provide the best shopping experience for our customers</p>
        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-truck-fast"></i>
                </div>
                <h3 class="feature-title">Fast & Free Delivery</h3>
                <p class="feature-description">Nationwide shipping with free delivery on orders over ₦50,000. Express delivery available in major cities.</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-title">Buyer Protection</h3>
                <p class="feature-description">30-day money-back guarantee. Secure payments via Paystack with fraud protection.</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="feature-title">Top Rated Sellers</h3>
                <p class="feature-description">All sellers verified with 4.8+ average ratings. Quality products from trusted suppliers.</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <h3 class="feature-title">24/7 Support</h3>
                <p class="feature-description">Chat, WhatsApp, and email support available round the clock. Quick response time.</p>
            </div>
        </div>
    </div>
</section>

<!-- Best Sellers -->
<section class="products-section" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Best Sellers</h2>
            <a href="<?= BASE_URL ?>pages/products.php?sort=popular" class="view-all-link">
                View All Best Sellers <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="products-grid">
            <?php if (empty($best_products)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--space-2xl); color: var(--text-light);">
                    <i class="fas fa-chart-line" style="font-size: 4rem; margin-bottom: var(--space-md); color: var(--border);"></i>
                    <h3>No best sellers</h3>
                    <p>Popular products will appear here</p>
                </div>
            <?php else: ?>
                <?php foreach ($best_products as $p): ?>
                    <?php
                    $final_price = $p['discount_price'] ?? $p['price'];
                    $old_price = $p['discount_price'] ? $p['price'] : null;
                    $discount_percent = $old_price ? round((($old_price - $final_price) / $old_price) * 100) : 0;
                    
                    // Determine image source
                    if (!empty($p['main_image'])) {
                        $image_src = BASE_URL . 'uploads/products/thumbs/' . htmlspecialchars($p['main_image']);
                        $image_alt = htmlspecialchars($p['name']);
                    } else {
                        $image_src = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f3f4f6"/><text x="100" y="100" font-family="Arial" font-size="14" fill="#9ca3af" text-anchor="middle" dy=".3em">No Image</text></svg>');
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
                                <span class="product-badge" style="background: var(--primary);">BEST SELLER</span>
                            </div>
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
                                <?php endif; ?>
                            </div>

                            <div class="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <span>4.9</span>
                            </div>

                            <div class="product-shipping">
                                <i class="fas fa-shipping-fast"></i> Free Shipping
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Newsletter -->
<section class="newsletter-section">
    <div class="container">
        <div class="newsletter-content">
            <h2 class="newsletter-title">Stay Updated</h2>
            <p class="newsletter-description">Subscribe to our newsletter and get 10% off your first order. Receive exclusive deals and new product updates.</p>
            <form class="newsletter-form" id="newsletterForm">
                <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                <button type="submit" class="newsletter-btn">
                    <i class="fas fa-paper-plane"></i> Subscribe
                </button>
            </form>
            <p style="margin-top: var(--space-md); font-size: 0.9rem; opacity: 0.8;">
                By subscribing, you agree to our Privacy Policy and consent to receive updates.
            </p>
        </div>
    </div>
</section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sale timer
    function updateSaleTimer() {
        const timerElement = document.getElementById('saleTimer');
        if (!timerElement) return;
        
        // Set sale end time (24 hours from now)
        const endTime = new Date();
        endTime.setHours(endTime.getHours() + 24);
        
        function update() {
            const now = new Date();
            const timeLeft = endTime - now;
            
            if (timeLeft <= 0) {
                timerElement.textContent = '00:00:00';
                return;
            }
            
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            timerElement.textContent = 
                String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');
        }
        
        update();
        setInterval(update, 1000);
    }
    
    updateSaleTimer();
    
    // Newsletter form
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('.newsletter-input').value;
            
            // Show loading state
            const btn = this.querySelector('.newsletter-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
            btn.disabled = true;
            
            // Simulate API call
            setTimeout(() => {
                showNotification('Successfully subscribed to newsletter!', 'success');
                this.reset();
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 1000);
        });
    }
    
    // Image lazy loading
    const images = document.querySelectorAll('.product-image img');
    
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
        
        images.forEach(img => imageObserver.observe(img));
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add to cart functionality
    document.addEventListener('click', function(e) {
        const addToCartBtn = e.target.closest('.add-to-cart-btn');
        if (addToCartBtn) {
            e.preventDefault();
            const productCard = addToCartBtn.closest('.product-card');
            const productId = productCard.dataset.productId;
            
            if (productId) {
                addToCart(productId, 1);
            }
        }
    });
    
    // Quick view functionality
    const quickViewBtns = document.querySelectorAll('.quick-view-btn');
    quickViewBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const productCard = this.closest('.product-card');
            const productId = productCard.dataset.productId;
            
            if (productId) {
                quickViewProduct(productId);
            }
        });
    });
    
    // Product hover animations
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.zIndex = '10';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.zIndex = '';
        });
    });
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

// Quick view function
function quickViewProduct(productId) {
    // In a real implementation, you would show a modal with product details
    console.log(`Quick view for product ${productId}`);
    
    // For now, navigate to product page
    window.location.href = '<?= BASE_URL ?>pages/product.php?id=' + productId;
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

// Parallax effect for hero banner
window.addEventListener('scroll', function() {
    const heroBanner = document.querySelector('.hero-banner');
    if (heroBanner) {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        heroBanner.style.backgroundPosition = `center ${rate}px`;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>