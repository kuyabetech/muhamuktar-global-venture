<?php
// pages/shipping.php - Shipping Information Page

$page_title = "Shipping Information";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get shipping settings from database
try {
    // Free shipping threshold
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'free_shipping_threshold'");
    $stmt->execute();
    $free_shipping_threshold = $stmt->fetchColumn() ?: 50000;

    // Standard shipping fee
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'shipping_fee'");
    $stmt->execute();
    $shipping_fee = $stmt->fetchColumn() ?: 1500;

    // Contact email
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    // Contact phone
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

} catch (Exception $e) {
    $free_shipping_threshold = 50000;
    $shipping_fee = 1500;
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
}

// Get shipping zones
$shipping_zones = [];
try {
    $stmt = $pdo->query("
        SELECT sz.*, 
               (SELECT COUNT(*) FROM shipping_methods WHERE zone_id = sz.id AND status = 'active') as method_count
        FROM shipping_zones sz
        WHERE sz.status = 'active'
        ORDER BY sz.priority ASC, sz.name ASC
    ");
    $shipping_zones = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist
}

// Get shipping methods
$shipping_methods = [];
if (!empty($shipping_zones)) {
    try {
        $stmt = $pdo->query("
            SELECT sm.*, sz.name as zone_name
            FROM shipping_methods sm
            JOIN shipping_zones sz ON sm.zone_id = sz.id
            WHERE sm.status = 'active'
            ORDER BY sz.priority ASC, sm.is_default DESC, sm.cost ASC
        ");
        $shipping_methods = $stmt->fetchAll();
    } catch (Exception $e) {
        // Table might not exist
    }
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Shipping Information</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Shipping Info</span>
            </div>
        </div>
    </section>

    <!-- Shipping Overview -->
    <section class="overview-section">
        <div class="container">
            <div class="overview-grid">
                <div class="overview-card">
                    <i class="fas fa-truck"></i>
                    <h3>Fast Delivery</h3>
                    <p>Orders are processed within 24 hours and delivered within 2-5 business days</p>
                </div>
                <div class="overview-card">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Packaging</h3>
                    <p>All items are carefully packed to ensure they arrive in perfect condition</p>
                </div>
                <div class="overview-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Track Your Order</h3>
                    <p>Get real-time updates on your delivery with our tracking system</p>
                </div>
                <div class="overview-card">
                    <i class="fas fa-undo-alt"></i>
                    <h3>Easy Returns</h3>
                    <p>Not satisfied? Return within 14 days for a full refund</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Shipping Rates -->
    <section class="rates-section">
        <div class="container">
            <h2 class="section-title">Shipping Rates & Delivery Times</h2>
            
            <?php if (!empty($shipping_methods)): ?>
                <div class="rates-table-wrapper">
                    <table class="rates-table">
                        <thead>
                            <tr>
                                <th>Shipping Method</th>
                                <th>Delivery Time</th>
                                <th>Cost</th>
                                <th>Conditions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shipping_methods as $method): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($method['name']) ?></strong>
                                        <?php if ($method['is_default']): ?>
                                            <span class="default-badge">Default</span>
                                        <?php endif; ?>
                                        <?php if (!empty($method['description'])): ?>
                                            <div class="method-description">
                                                <?= htmlspecialchars($method['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($method['estimated_days_min'] && $method['estimated_days_max']): ?>
                                            <?= $method['estimated_days_min'] ?>-<?= $method['estimated_days_max'] ?> business days
                                        <?php elseif ($method['estimated_days_min']): ?>
                                            <?= $method['estimated_days_min'] ?>+ business days
                                        <?php else: ?>
                                            Varies by location
                                        <?php endif; ?>
                                    </td>
                                    <td class="cost-cell">
                                        <?php
                                        if ($method['type'] === 'free') {
                                            echo '<span class="free-badge">FREE</span>';
                                        } elseif ($method['type'] === 'percentage') {
                                            echo $method['cost'] . '% of order total';
                                        } else {
                                            echo '₦' . number_format($method['cost'], 2);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $conditions = [];
                                        if ($method['min_order']) $conditions[] = "Min order: ₦" . number_format($method['min_order']);
                                        if ($method['max_order']) $conditions[] = "Max order: ₦" . number_format($method['max_order']);
                                        if ($method['free_shipping_threshold']) {
                                            $conditions[] = "Free over ₦" . number_format($method['free_shipping_threshold']);
                                        }
                                        echo !empty($conditions) ? implode('<br>', $conditions) : 'No restrictions';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="default-rates">
                    <div class="rate-card">
                        <h3>Standard Shipping</h3>
                        <div class="rate-price">₦<?= number_format($shipping_fee, 2) ?></div>
                        <p>Delivery in 3-5 business days</p>
                    </div>
                    <div class="rate-card highlight">
                        <h3>Free Shipping</h3>
                        <div class="rate-price free">FREE</div>
                        <p>On orders over ₦<?= number_format($free_shipping_threshold, 2) ?></p>
                        <span class="rate-badge">Best Value</span>
                    </div>
                    <div class="rate-card">
                        <h3>Express Shipping</h3>
                        <div class="rate-price">₦<?= number_format($shipping_fee * 2, 2) ?></div>
                        <p>Delivery in 1-2 business days</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Shipping Zones -->
    <?php if (!empty($shipping_zones)): ?>
        <section class="zones-section">
            <div class="container">
                <h2 class="section-title">Shipping Zones</h2>
                <div class="zones-grid">
                    <?php foreach ($shipping_zones as $zone): ?>
                        <div class="zone-card">
                            <h3><?= htmlspecialchars($zone['name']) ?></h3>
                            <?php if (!empty($zone['description'])): ?>
                                <p class="zone-description"><?= htmlspecialchars($zone['description']) ?></p>
                            <?php endif; ?>
                            <div class="zone-coverage">
                                <strong>Coverage:</strong>
                                <?php
                                $coverage = [];
                                if (!empty($zone['countries'])) {
                                    $countries = explode(',', $zone['countries']);
                                    foreach ($countries as $country) {
                                        $coverage[] = $country;
                                    }
                                } else {
                                    $coverage[] = 'All Countries';
                                }
                                echo htmlspecialchars(implode(', ', $coverage));
                                ?>
                            </div>
                            <?php if (!empty($zone['states'])): ?>
                                <div class="zone-states">
                                    <strong>States:</strong> <?= htmlspecialchars($zone['states']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="zone-methods">
                                <strong><?= $zone['method_count'] ?> shipping methods available</strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Shipping FAQ -->
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>How long does shipping take?</h3>
                    <p>Standard shipping typically takes 3-5 business days within Nigeria. Express shipping takes 1-2 business days. International shipping times vary by location.</p>
                </div>
                <div class="faq-item">
                    <h3>Do you ship internationally?</h3>
                    <p>Yes, we ship to select countries. Shipping costs and delivery times will be calculated at checkout based on your location.</p>
                </div>
                <div class="faq-item">
                    <h3>How can I track my order?</h3>
                    <p>Once your order ships, you'll receive a tracking number via email. You can also track your order in your account dashboard.</p>
                </div>
                <div class="faq-item">
                    <h3>What if my package is delayed?</h3>
                    <p>If your package is significantly delayed, please contact our support team and we'll investigate the issue.</p>
                </div>
                <div class="faq-item">
                    <h3>Do you offer free shipping?</h3>
                    <p>Yes, we offer free shipping on orders over ₦<?= number_format($free_shipping_threshold, 2) ?> within Nigeria.</p>
                </div>
                <div class="faq-item">
                    <h3>Can I change my shipping address?</h3>
                    <p>You can change your shipping address before your order has been shipped. Contact us immediately for assistance.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Shipping Process -->
    <section class="process-section">
        <div class="container">
            <h2 class="section-title">How Shipping Works</h2>
            <div class="process-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Place Your Order</h3>
                    <p>Complete your purchase and receive order confirmation</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Order Processing</h3>
                    <p>We prepare and pack your items (1-2 business days)</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Shipping</h3>
                    <p>Your order is handed to our delivery partner</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Tracking</h3>
                    <p>Receive tracking number and follow your package</p>
                </div>
                <div class="step">
                    <div class="step-number">5</div>
                    <h3>Delivery</h3>
                    <p>Package arrives at your doorstep</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Support -->
    <section class="support-section">
        <div class="container">
            <div class="support-card">
                <h2>Need Help with Shipping?</h2>
                <p>Our customer support team is here to assist you with any shipping questions</p>
                <div class="support-options">
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="support-option">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($contact_email) ?></span>
                    </a>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="support-option">
                        <i class="fas fa-phone-alt"></i>
                        <span><?= htmlspecialchars($contact_phone) ?></span>
                    </a>
                    <a href="<?= BASE_URL ?>pages/contact.php" class="support-option">
                        <i class="fas fa-comment"></i>
                        <span>Contact Form</span>
                    </a>
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

/* Overview Section */
.overview-section {
    padding: 4rem 0;
    background: white;
}

.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.overview-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: all 0.3s;
}

.overview-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.overview-card i {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.overview-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.overview-card p {
    color: var(--text-light);
    line-height: 1.6;
}

/* Rates Section */
.rates-section {
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

/* Default Rates */
.default-rates {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
}

.rate-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-align: center;
    position: relative;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.rate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.rate-card.highlight {
    border: 2px solid var(--primary);
    transform: scale(1.05);
}

.rate-card.highlight:hover {
    transform: scale(1.05) translateY(-5px);
}

.rate-badge {
    position: absolute;
    top: -12px;
    right: 20px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 0.25rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.rate-card h3 {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.rate-price {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
}

.rate-price.free {
    color: #10b981;
}

.rate-card p {
    color: var(--text-light);
}

/* Rates Table */
.rates-table-wrapper {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.rates-table {
    width: 100%;
    border-collapse: collapse;
}

.rates-table th {
    padding: 1.5rem;
    text-align: left;
    background: var(--primary);
    color: white;
    font-weight: 600;
}

.rates-table td {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
}

.rates-table tr:last-child td {
    border-bottom: none;
}

.rates-table tr:hover {
    background: var(--bg);
}

.default-badge {
    display: inline-block;
    background: #d1fae5;
    color: #065f46;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

.method-description {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

.cost-cell {
    font-weight: 600;
    color: var(--primary);
}

.free-badge {
    display: inline-block;
    background: #d1fae5;
    color: #065f46;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
}

/* Zones Section */
.zones-section {
    padding: 4rem 0;
    background: white;
}

.zones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.zone-card {
    background: var(--bg);
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.zone-card h3 {
    font-size: 1.3rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.zone-description {
    color: var(--text-light);
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.zone-coverage,
.zone-states,
.zone-methods {
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.zone-coverage strong,
.zone-states strong,
.zone-methods strong {
    color: var(--text);
}

/* FAQ Section */
.faq-section {
    padding: 4rem 0;
    background: var(--bg);
}

.faq-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 2rem;
}

.faq-item {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.faq-item h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.faq-item h3:before {
    content: 'Q:';
    color: var(--primary);
    font-weight: 700;
}

.faq-item p {
    color: var(--text-light);
    line-height: 1.6;
    padding-left: 1.5rem;
    position: relative;
}

.faq-item p:before {
    content: 'A:';
    position: absolute;
    left: 0;
    color: var(--success);
    font-weight: 700;
}

/* Process Section */
.process-section {
    padding: 4rem 0;
    background: white;
}

.process-steps {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1.5rem;
    position: relative;
}

.process-steps:before {
    content: '';
    position: absolute;
    top: 40px;
    left: 10%;
    right: 10%;
    height: 2px;
    background: linear-gradient(90deg, var(--primary), var(--primary-light));
    z-index: 1;
}

.step {
    text-align: center;
    position: relative;
    z-index: 2;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.step-number {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 auto 1rem;
}

.step h3 {
    font-size: 1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.step p {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Support Section */
.support-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.support-card {
    max-width: 800px;
    margin: 0 auto;
    text-align: center;
    color: white;
}

.support-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.support-card p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.support-options {
    display: flex;
    justify-content: center;
    gap: 2rem;
    flex-wrap: wrap;
}

.support-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
    border-radius: 12px;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.support-option:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

.support-option i {
    font-size: 1.2rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .overview-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .process-steps {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .process-steps:before {
        display: none;
    }
}

@media (max-width: 992px) {
    .default-rates {
        grid-template-columns: 1fr;
        max-width: 400px;
    }
    
    .rate-card.highlight {
        transform: none;
    }
    
    .rate-card.highlight:hover {
        transform: translateY(-5px);
    }
    
    .faq-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .rates-table-wrapper {
        overflow-x: auto;
    }
    
    .rates-table {
        min-width: 800px;
    }
    
    .process-steps {
        grid-template-columns: 1fr;
    }
    
    .support-options {
        flex-direction: column;
        align-items: stretch;
    }
    
    .support-option {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .zone-card {
        padding: 1.5rem;
    }
    
    .faq-item {
        padding: 1.5rem;
    }
}
</style>

<script>
// FAQ Accordion functionality (optional)
document.querySelectorAll('.faq-item h3').forEach(header => {
    header.addEventListener('click', function() {
        const content = this.nextElementSibling;
        content.style.display = content.style.display === 'none' ? 'block' : 'none';
    });
});

// Initialize any interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Highlight the best value shipping option
    const freeShippingCard = document.querySelector('.rate-card .free-badge')?.closest('.rate-card');
    if (freeShippingCard) {
        freeShippingCard.classList.add('highlight');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>