<?php
// pages/faq.php - Frequently Asked Questions Page

$page_title = "Frequently Asked Questions";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get contact information
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    // Get FAQ categories if stored in database
    $faq_categories = [];
    $stmt = $pdo->query("SELECT * FROM faq_categories WHERE status = 'active' ORDER BY display_order ASC");
    $faq_categories = $stmt->fetchAll();

    // Get FAQs
    $faqs = [];
    if (!empty($faq_categories)) {
        $stmt = $pdo->query("
            SELECT f.*, c.name as category_name 
            FROM faqs f
            JOIN faq_categories c ON f.category_id = c.id
            WHERE f.status = 'active'
            ORDER BY c.display_order ASC, f.display_order ASC
        ");
        $faqs = $stmt->fetchAll();
    }

} catch (Exception $e) {
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $faq_categories = [];
    $faqs = [];
}

// Define default FAQs if database is not set up
if (empty($faqs)) {
    $faqs = [
        [
            'category_name' => 'Orders & Shipping',
            'question' => 'How long does shipping take?',
            'answer' => 'Standard shipping typically takes 3-5 business days within Nigeria. Express shipping takes 1-2 business days. International shipping times vary by location.'
        ],
        [
            'category_name' => 'Orders & Shipping',
            'question' => 'How can I track my order?',
            'answer' => 'Once your order ships, you\'ll receive a tracking number via email. You can also track your order in your account dashboard under "My Orders".'
        ],
        [
            'category_name' => 'Orders & Shipping',
            'question' => 'Do you ship internationally?',
            'answer' => 'Yes, we ship to select countries. Shipping costs and delivery times will be calculated at checkout based on your location.'
        ],
        [
            'category_name' => 'Orders & Shipping',
            'question' => 'Can I change my shipping address after placing an order?',
            'answer' => 'You can change your shipping address within 1 hour of placing your order by contacting our support team. After that, we cannot guarantee changes.'
        ],
        [
            'category_name' => 'Returns & Refunds',
            'question' => 'What is your return policy?',
            'answer' => 'We offer a 14-day return policy for most items. Items must be unused and in original packaging with tags attached.'
        ],
        [
            'category_name' => 'Returns & Refunds',
            'question' => 'How long do refunds take?',
            'answer' => 'Refunds are processed within 3-5 business days after we receive and inspect your return. The time for the refund to appear in your account depends on your payment method.'
        ],
        [
            'category_name' => 'Returns & Refunds',
            'question' => 'Can I exchange an item?',
            'answer' => 'Yes, you can request an exchange for a different size, color, or product of equal value. Contact our support team to initiate an exchange.'
        ],
        [
            'category_name' => 'Payment',
            'question' => 'What payment methods do you accept?',
            'answer' => 'We accept various payment methods including credit/debit cards, Paystack, bank transfers, and cash on delivery (where available).'
        ],
        [
            'category_name' => 'Payment',
            'question' => 'Is it safe to use my credit card on your site?',
            'answer' => 'Yes, we use industry-standard SSL encryption to protect your information. We never store your full credit card details on our servers.'
        ],
        [
            'category_name' => 'Payment',
            'question' => 'Do you offer payment plans?',
            'answer' => 'Currently, we do not offer payment plans. Full payment is required at checkout.'
        ],
        [
            'category_name' => 'Account',
            'question' => 'How do I create an account?',
            'answer' => 'Click on the "Join Now" button in the top right corner and fill in your details. You can also create an account during checkout.'
        ],
        [
            'category_name' => 'Account',
            'question' => 'I forgot my password. What should I do?',
            'answer' => 'Click on "Forgot Password" on the login page and enter your email address. We\'ll send you instructions to reset your password.'
        ],
        [
            'category_name' => 'Products',
            'question' => 'Are your products authentic?',
            'answer' => 'Yes, we guarantee that all products sold on our platform are 100% authentic. We source directly from manufacturers and authorized distributors.'
        ],
        [
            'category_name' => 'Products',
            'question' => 'Do you offer product warranties?',
            'answer' => 'Warranty information varies by product and brand. Please check the product description for specific warranty details.'
        ]
    ];
}

// Group FAQs by category
$grouped_faqs = [];
foreach ($faqs as $faq) {
    $category = $faq['category_name'];
    if (!isset($grouped_faqs[$category])) {
        $grouped_faqs[$category] = [];
    }
    $grouped_faqs[$category][] = $faq;
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Frequently Asked Questions</h1>
            <p class="header-description">Find answers to common questions about our products and services</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">FAQ</span>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="faqSearch" placeholder="Search for answers..." class="search-input">
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
    </section>

    <!-- Categories Navigation -->
    <section class="categories-nav">
        <div class="container">
            <div class="categories-grid">
                <?php foreach (array_keys($grouped_faqs) as $category): ?>
                    <a href="#<?= strtolower(str_replace(' ', '-', $category)) ?>" class="category-link">
                        <?php 
                        $icon = 'fa-question-circle';
                        if (strpos($category, 'Order') !== false) $icon = 'fa-shopping-cart';
                        elseif (strpos($category, 'Return') !== false) $icon = 'fa-undo-alt';
                        elseif (strpos($category, 'Payment') !== false) $icon = 'fa-credit-card';
                        elseif (strpos($category, 'Account') !== false) $icon = 'fa-user';
                        elseif (strpos($category, 'Product') !== false) $icon = 'fa-box';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                        <span><?= htmlspecialchars($category) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Sections -->
    <section class="faq-section">
        <div class="container">
            <?php foreach ($grouped_faqs as $category => $faqs): ?>
                <div class="faq-category" id="<?= strtolower(str_replace(' ', '-', $category)) ?>">
                    <h2 class="category-title">
                        <?php 
                        $icon = 'fa-question-circle';
                        if (strpos($category, 'Order') !== false) $icon = 'fa-shopping-cart';
                        elseif (strpos($category, 'Return') !== false) $icon = 'fa-undo-alt';
                        elseif (strpos($category, 'Payment') !== false) $icon = 'fa-credit-card';
                        elseif (strpos($category, 'Account') !== false) $icon = 'fa-user';
                        elseif (strpos($category, 'Product') !== false) $icon = 'fa-box';
                        ?>
                        <i class="fas <?= $icon ?>"></i>
                        <?= htmlspecialchars($category) ?>
                    </h2>
                    
                    <div class="faq-list">
                        <?php foreach ($faqs as $index => $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFAQ(this)">
                                    <h3><?= htmlspecialchars($faq['question']) ?></h3>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer">
                                    <p><?= nl2br(htmlspecialchars($faq['answer'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Still Have Questions -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-card">
                <h2>Still Have Questions?</h2>
                <p>Can't find the answer you're looking for? Please contact our support team.</p>
                
                <div class="contact-options">
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="contact-option">
                        <i class="fas fa-envelope"></i>
                        <div>
                            <span class="label">Email Us</span>
                            <span class="value"><?= htmlspecialchars($contact_email) ?></span>
                        </div>
                    </a>
                    
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="contact-option">
                        <i class="fas fa-phone-alt"></i>
                        <div>
                            <span class="label">Call Us</span>
                            <span class="value"><?= htmlspecialchars($contact_phone) ?></span>
                        </div>
                    </a>
                    
                    <a href="<?= BASE_URL ?>pages/contact.php" class="contact-option">
                        <i class="fas fa-comment"></i>
                        <div>
                            <span class="label">Live Chat</span>
                            <span class="value">Chat with support</span>
                        </div>
                    </a>
                </div>

                <div class="support-hours">
                    <i class="fas fa-clock"></i>
                    <span>Support available Monday - Friday, 9am - 6pm (WAT)</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Helpful Resources -->
    <section class="resources-section">
        <div class="container">
            <h2 class="section-title">Helpful Resources</h2>
            <div class="resources-grid">
                <a href="<?= BASE_URL ?>pages/shipping.php" class="resource-card">
                    <i class="fas fa-truck"></i>
                    <h3>Shipping Information</h3>
                    <p>Learn about our shipping options and delivery times</p>
                    <span class="learn-more">Learn More →</span>
                </a>
                
                <a href="<?= BASE_URL ?>pages/returns.php" class="resource-card">
                    <i class="fas fa-undo-alt"></i>
                    <h3>Returns & Refunds</h3>
                    <p>Understand our return policy and how to return items</p>
                    <span class="learn-more">Learn More →</span>
                </a>
                
                <a href="<?= BASE_URL ?>pages/size-guide.php" class="resource-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Size Guide</h3>
                    <p>Find the right size for clothing and accessories</p>
                    <span class="learn-more">Learn More →</span>
                </a>
                
                <a href="<?= BASE_URL ?>pages/contact.php" class="resource-card">
                    <i class="fas fa-headset"></i>
                    <h3>Contact Support</h3>
                    <p>Get in touch with our customer service team</p>
                    <span class="learn-more">Contact Us →</span>
                </a>
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
    padding: 2rem 0;
    background: white;
    border-bottom: 1px solid var(--border);
}

.search-wrapper {
    max-width: 600px;
    margin: 0 auto;
    position: relative;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
}

.search-input {
    width: 100%;
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

.search-results {
    max-width: 600px;
    margin: 1rem auto 0;
    display: none;
}

.search-results.active {
    display: block;
}

.search-result-item {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.3s;
}

.search-result-item:hover {
    background: var(--bg);
    border-color: var(--primary);
}

.search-result-item h4 {
    margin-bottom: 0.25rem;
    color: var(--text);
}

.search-result-item p {
    color: var(--text-light);
    font-size: 0.9rem;
}

/* Categories Navigation */
.categories-nav {
    padding: 2rem 0;
    background: var(--bg);
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.category-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.category-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    background: var(--primary);
    color: white;
}

.category-link i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--primary);
}

.category-link:hover i {
    color: white;
}

.category-link span {
    font-size: 0.9rem;
    font-weight: 600;
    text-align: center;
}

/* FAQ Section */
.faq-section {
    padding: 4rem 0;
    background: white;
}

.faq-category {
    margin-bottom: 3rem;
}

.category-title {
    font-size: 1.8rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-title i {
    color: var(--primary);
}

.faq-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.faq-item {
    background: var(--bg);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
}

.faq-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.faq-question {
    padding: 1.5rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.3s;
}

.faq-question:hover {
    background: rgba(59, 130, 246, 0.05);
}

.faq-question h3 {
    font-size: 1.1rem;
    color: var(--text);
    margin: 0;
    flex: 1;
    padding-right: 1rem;
}

.faq-question i {
    color: var(--primary);
    transition: transform 0.3s;
}

.faq-item.active .faq-question i {
    transform: rotate(180deg);
}

.faq-answer {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    background: white;
}

.faq-item.active .faq-answer {
    max-height: 500px;
    transition: max-height 0.5s ease-in;
}

.faq-answer p {
    padding: 1.5rem;
    color: var(--text-light);
    line-height: 1.8;
    margin: 0;
    border-top: 1px solid var(--border);
}

/* Contact Section */
.contact-section {
    padding: 4rem 0;
    background: var(--bg);
}

.contact-card {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    text-align: center;
}

.contact-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.contact-card p {
    color: var(--text-light);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.contact-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.contact-option {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
}

.contact-option:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    background: var(--primary);
    color: white;
}

.contact-option i {
    font-size: 2rem;
}

.contact-option .label {
    display: block;
    font-size: 0.85rem;
    opacity: 0.8;
    margin-bottom: 0.25rem;
}

.contact-option .value {
    font-weight: 600;
}

.support-hours {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--bg);
    border-radius: 50px;
    color: var(--text-light);
}

.support-hours i {
    color: var(--primary);
}

/* Resources Section */
.resources-section {
    padding: 4rem 0;
    background: white;
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

.resources-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.resource-card {
    background: var(--bg);
    padding: 2rem;
    border-radius: 16px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
    text-align: center;
}

.resource-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    background: var(--primary);
    color: white;
}

.resource-card i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--primary);
}

.resource-card:hover i {
    color: white;
}

.resource-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.resource-card p {
    color: var(--text-light);
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.resource-card:hover p {
    color: rgba(255,255,255,0.9);
}

.learn-more {
    font-weight: 600;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .resources-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .contact-options {
        grid-template-columns: 1fr;
    }
    
    .categories-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .resources-grid {
        grid-template-columns: 1fr;
    }
    
    .category-title {
        font-size: 1.5rem;
    }
    
    .contact-card {
        padding: 2rem;
    }
}

@media (max-width: 480px) {
    .categories-grid {
        grid-template-columns: 1fr;
    }
    
    .faq-question h3 {
        font-size: 1rem;
    }
    
    .support-hours {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// Toggle FAQ answer
function toggleFAQ(element) {
    const faqItem = element.closest('.faq-item');
    faqItem.classList.toggle('active');
}

// Search functionality
const searchInput = document.getElementById('faqSearch');
const searchResults = document.getElementById('searchResults');
const faqItems = document.querySelectorAll('.faq-item');

searchInput.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    
    if (searchTerm.length < 2) {
        searchResults.innerHTML = '';
        searchResults.classList.remove('active');
        return;
    }
    
    const results = [];
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question h3').textContent.toLowerCase();
        const answer = item.querySelector('.faq-answer p').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            results.push({
                question: item.querySelector('.faq-question h3').textContent,
                answer: item.querySelector('.faq-answer p').textContent,
                element: item
            });
        }
    });
    
    if (results.length > 0) {
        let html = '<h3 style="margin-bottom: 1rem;">Search Results:</h3>';
        results.forEach(result => {
            html += `
                <div class="search-result-item" onclick="jumpToFAQ(this)" data-question="${result.question}">
                    <h4>${result.question}</h4>
                    <p>${result.answer.substring(0, 100)}...</p>
                </div>
            `;
        });
        searchResults.innerHTML = html;
        searchResults.classList.add('active');
    } else {
        searchResults.innerHTML = '<p style="text-align: center; color: var(--text-light);">No results found</p>';
        searchResults.classList.add('active');
    }
});

function jumpToFAQ(element) {
    const question = element.dataset.question;
    
    faqItems.forEach(item => {
        const itemQuestion = item.querySelector('.faq-question h3').textContent;
        if (itemQuestion === question) {
            // Open the FAQ item
            item.classList.add('active');
            
            // Scroll to it
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Highlight temporarily
            item.style.backgroundColor = '#fef3c7';
            setTimeout(() => {
                item.style.backgroundColor = '';
            }, 2000);
        }
    });
    
    // Clear search
    searchInput.value = '';
    searchResults.innerHTML = '';
    searchResults.classList.remove('active');
}

// Open all FAQs from URL hash
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash) {
        const category = document.getElementById(hash);
        if (category) {
            category.scrollIntoView({ behavior: 'smooth' });
        }
    }
    
    // Open first FAQ in each category by default? (optional)
    // document.querySelectorAll('.faq-category:first-child .faq-item:first-child').forEach(item => {
    //     item.classList.add('active');
    // });
});
</script>

<?php require_once '../includes/footer.php'; ?>