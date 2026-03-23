<?php
// includes/footer.php
// Place this at the bottom of your pages, after </main>

// Fetch footer settings from database
try {
    // Get site name
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'site_name'");
    $stmt->execute();
    $site_name = $stmt->fetchColumn();
    if (!$site_name) {
        $site_name = SITE_NAME ?? 'Muhamuktar Global Venture';
    }

    // Get contact information
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_address'");
    $stmt->execute();
    $contact_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';

    // Get social media links
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_facebook'");
    $stmt->execute();
    $social_facebook = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_twitter'");
    $stmt->execute();
    $social_twitter = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_instagram'");
    $stmt->execute();
    $social_instagram = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_whatsapp'");
    $stmt->execute();
    $social_whatsapp = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_youtube'");
    $stmt->execute();
    $social_youtube = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_tiktok'");
    $stmt->execute();
    $social_tiktok = $stmt->fetchColumn();

    // Get footer description
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'footer_description'");
    $stmt->execute();
    $footer_description = $stmt->fetchColumn();
    if (!$footer_description) {
        $footer_description = "Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.";
    }

    // Get footer copyright text
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'copyright_text'");
    $stmt->execute();
    $copyright_text = $stmt->fetchColumn();

    // Get payment methods
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'payment_methods'");
    $stmt->execute();
    $payment_methods = $stmt->fetchColumn();

    // Get current year
    $current_year = date('Y');

} catch (Exception $e) {
    // Fallback values if database query fails
    $site_name = SITE_NAME ?? 'Muhamuktar Global Venture';
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $contact_address = '123 Main Street, Lagos, Nigeria';
    $social_facebook = '';
    $social_twitter = '';
    $social_instagram = '';
    $social_whatsapp = '';
    $social_youtube = '';
    $social_tiktok = '';
    $footer_description = "Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.";
    $copyright_text = '';
    $payment_methods = '';
    $current_year = date('Y');
}

// Parse payment methods
$payment_icons = [
    'visa' => ['icon' => 'fab fa-cc-visa', 'name' => 'Visa'],
    'mastercard' => ['icon' => 'fab fa-cc-mastercard', 'name' => 'Mastercard'],
    'amex' => ['icon' => 'fab fa-cc-amex', 'name' => 'American Express'],
    'paypal' => ['icon' => 'fab fa-cc-paypal', 'name' => 'PayPal'],
    'paystack' => ['icon' => 'fas fa-credit-card', 'name' => 'Paystack'],
    'flutterwave' => ['icon' => 'fas fa-credit-card', 'name' => 'Flutterwave'],
    'bank_transfer' => ['icon' => 'fas fa-university', 'name' => 'Bank Transfer'],
    'cash' => ['icon' => 'fas fa-money-bill-wave', 'name' => 'Cash on Delivery']
];

$enabled_payments = [];
if (!empty($payment_methods)) {
    $enabled_payments = explode(',', $payment_methods);
}

// Get current year for copyright
$current_year = date('Y');
?>

<footer class="site-footer">
    <div class="container">
        <!-- Footer Top -->
        <div class="footer-top">
            <!-- Column 1: Brand & About -->
            <div class="footer-col">
                <div class="footer-logo">
                    <i class="fas fa-shopping-bag"></i>
                    <span><?= htmlspecialchars($site_name) ?></span>
                </div>
                <p class="footer-description">
                    <?= htmlspecialchars($footer_description) ?>
                </p>
                
                <!-- Social Links -->
                <div class="social-links">
                    <?php if (!empty($social_facebook)): ?>
                        <a href="<?= htmlspecialchars($social_facebook) ?>" target="_blank" class="social-link facebook" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_twitter)): ?>
                        <a href="<?= htmlspecialchars($social_twitter) ?>" target="_blank" class="social-link twitter" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_instagram)): ?>
                        <a href="<?= htmlspecialchars($social_instagram) ?>" target="_blank" class="social-link instagram" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_whatsapp)): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $social_whatsapp) ?>" target="_blank" class="social-link whatsapp" aria-label="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_youtube)): ?>
                        <a href="<?= htmlspecialchars($social_youtube) ?>" target="_blank" class="social-link youtube" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_tiktok)): ?>
                        <a href="<?= htmlspecialchars($social_tiktok) ?>" target="_blank" class="social-link tiktok" aria-label="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="footer-col">
                <h3 class="footer-title">Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>pages/products.php"><i class="fas fa-chevron-right"></i> Shop All Products</a></li>
                    <li><a href="<?= BASE_URL ?>pages/categories.php"><i class="fas fa-chevron-right"></i> Categories</a></li>
                    <li><a href="<?= BASE_URL ?>pages/deals.php"><i class="fas fa-chevron-right"></i> Deals & Promotions</a></li>
                    <li><a href="<?= BASE_URL ?>pages/new-arrivals.php"><i class="fas fa-chevron-right"></i> New Arrivals</a></li>
                    <li><a href="<?= BASE_URL ?>pages/best-sellers.php"><i class="fas fa-chevron-right"></i> Best Sellers</a></li>
                    <li><a href="<?= BASE_URL ?>pages/brands.php"><i class="fas fa-chevron-right"></i> Brands</a></li>
                </ul>
            </div>

            <!-- Column 3: Customer Service -->
            <div class="footer-col">
                <h3 class="footer-title">Customer Service</h3>
                <ul class="footer-links">
                    <li><a href="<?= BASE_URL ?>pages/track-order.php"><i class="fas fa-chevron-right"></i> Track Your Order</a></li>
                    <li><a href="<?= BASE_URL ?>pages/shipping.php"><i class="fas fa-chevron-right"></i> Shipping Information</a></li>
                    <li><a href="<?= BASE_URL ?>pages/returns.php"><i class="fas fa-chevron-right"></i> Returns & Refunds</a></li>
                    <li><a href="<?= BASE_URL ?>pages/faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                    <li><a href="<?= BASE_URL ?>pages/size-guide.php"><i class="fas fa-chevron-right"></i> Size Guide</a></li>
                    <li><a href="<?= BASE_URL ?>pages/contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>

            <!-- Column 4: Contact & Newsletter -->
            <div class="footer-col">
                <h3 class="footer-title">Contact Us</h3>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($contact_address) ?></span>
                    </li>
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>">
                            <?= htmlspecialchars($contact_phone) ?>
                        </a>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <a href="mailto:<?= htmlspecialchars($contact_email) ?>">
                            <?= htmlspecialchars($contact_email) ?>
                        </a>
                    </li>
                </ul>

                <div class="newsletter">
                    <h4>Stay Updated</h4>
                    <p>Subscribe to get exclusive deals and updates.</p>
                    <form id="newsletterForm" class="newsletter-form">
                        <div class="input-group">
                            <input type="email" id="newsletterEmail" placeholder="Your email address" required>
                            <button type="submit" aria-label="Subscribe">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <div id="newsletterMessage" class="newsletter-message"></div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Footer Middle: Payment Methods -->
        <div class="footer-middle">
            <div class="payment-methods">
                <span class="payment-label">We Accept:</span>
                <div class="payment-icons">
                    <?php if (empty($enabled_payments)): ?>
                        <!-- Default payment methods -->
                        <span class="payment-icon" title="Visa"><i class="fab fa-cc-visa"></i></span>
                        <span class="payment-icon" title="Mastercard"><i class="fab fa-cc-mastercard"></i></span>
                        <span class="payment-icon" title="Paystack"><i class="fas fa-credit-card"></i> Paystack</span>
                        <span class="payment-icon" title="Flutterwave"><i class="fas fa-credit-card"></i> Flutterwave</span>
                        <span class="payment-icon" title="Bank Transfer"><i class="fas fa-university"></i> Bank Transfer</span>
                        <span class="payment-icon" title="Cash on Delivery"><i class="fas fa-money-bill-wave"></i> Cash on Delivery</span>
                    <?php else: ?>
                        <?php foreach ($enabled_payments as $payment): ?>
                            <?php if (isset($payment_icons[trim($payment)])): ?>
                                <span class="payment-icon" title="<?= $payment_icons[trim($payment)]['name'] ?>">
                                    <i class="<?= $payment_icons[trim($payment)]['icon'] ?>"></i>
                                    <?= $payment_icons[trim($payment)]['name'] ?>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trust Badges -->
            <div class="trust-badges">
                <span class="trust-badge">
                    <i class="fas fa-shield-alt"></i> Secure SSL
                </span>
                <span class="trust-badge">
                    <i class="fas fa-lock"></i> 256-bit Encryption
                </span>
                <span class="trust-badge">
                    <i class="fas fa-truck"></i> Free Shipping*
                </span>
                <span class="trust-badge">
                    <i class="fas fa-undo-alt"></i> 14-Day Returns
                </span>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="copyright">
                <p>&copy; <?= $current_year ?> <?= htmlspecialchars($site_name) ?>. 
                   <?= !empty($copyright_text) ? htmlspecialchars($copyright_text) : 'All rights reserved.' ?></p>
            </div>
            
            <div class="legal-links">
                <a href="<?= BASE_URL ?>pages/privacy-policy.php">Privacy Policy</a>
                <span class="separator">|</span>
                <a href="<?= BASE_URL ?>pages/terms-of-service.php">Terms of Service</a>
                <span class="separator">|</span>
                <a href="<?= BASE_URL ?>pages/cookie-policy.php">Cookie Policy</a>
                <span class="separator">|</span>
                <a href="<?= BASE_URL ?>pages/sitemap.php">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top Button -->
<button class="scroll-top-btn" id="scrollTopBtn" aria-label="Scroll to top">
    <i class="fas fa-arrow-up"></i>
</button>

<style>
/* Footer Styles */
.site-footer {
    background: #0a0e17;
    color: #94a3b8;
    padding: 4rem 0 2rem;
    margin-top: auto;
    font-size: 0.95rem;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.container {
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 1.5rem;
}

/* Footer Top */
.footer-top {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2.5rem;
    margin-bottom: 3rem;
}

.footer-col {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* Logo */
.footer-logo {
    font-size: 1.6rem;
    font-weight: 700;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.5rem;
}

.footer-logo i {
    color: #3b82f6;
}

.footer-description {
    line-height: 1.7;
    margin-bottom: 1rem;
}

/* Social Links */
.social-links {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.social-link:hover {
    transform: translateY(-3px);
}

.social-link.facebook:hover {
    background: #1877f2;
    box-shadow: 0 5px 15px rgba(24, 119, 242, 0.3);
}

.social-link.twitter:hover {
    background: #1da1f2;
    box-shadow: 0 5px 15px rgba(29, 161, 242, 0.3);
}

.social-link.instagram:hover {
    background: linear-gradient(45deg, #f09433, #d62976, #962fbf);
    box-shadow: 0 5px 15px rgba(225, 48, 108, 0.3);
}

.social-link.whatsapp:hover {
    background: #25d366;
    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
}

.social-link.youtube:hover {
    background: #ff0000;
    box-shadow: 0 5px 15px rgba(255, 0, 0, 0.3);
}

.social-link.tiktok:hover {
    background: #000000;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

/* Footer Titles */
.footer-title {
    color: white;
    font-size: 1.15rem;
    font-weight: 600;
    margin-bottom: 1rem;
    position: relative;
    padding-bottom: 0.5rem;
}

.footer-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 40px;
    height: 2px;
    background: #3b82f6;
}

/* Footer Links */
.footer-links {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.footer-links a {
    color: #94a3b8;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.footer-links a i {
    font-size: 0.8rem;
    color: #3b82f6;
    transition: transform 0.3s ease;
}

.footer-links a:hover {
    color: white;
    transform: translateX(5px);
}

.footer-links a:hover i {
    transform: translateX(3px);
}

/* Contact Info */
.contact-info {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.contact-info li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    line-height: 1.6;
}

.contact-info i {
    color: #3b82f6;
    font-size: 1.1rem;
    margin-top: 0.2rem;
}

.contact-info a {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.3s ease;
}

.contact-info a:hover {
    color: white;
}

/* Newsletter */
.newsletter h4 {
    color: white;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.newsletter p {
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.newsletter-form {
    width: 100%;
}

.input-group {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

.newsletter-form input {
    flex: 1;
    padding: 0.9rem 1.2rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.newsletter-form input:focus {
    outline: none;
    border-color: #3b82f6;
    background: rgba(255, 255, 255, 0.1);
}

.newsletter-form input::placeholder {
    color: #64748b;
}

.newsletter-form button {
    padding: 0.9rem 1.2rem;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.newsletter-form button:hover {
    background: #2563eb;
    transform: translateY(-2px);
}

.newsletter-message {
    margin-top: 0.5rem;
    font-size: 0.85rem;
}

/* Footer Middle */
.footer-middle {
    padding: 2rem 0;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

/* Payment Methods */
.payment-methods {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.payment-label {
    color: white;
    font-weight: 600;
}

.payment-icons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.payment-icon {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem 0.8rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    color: #94a3b8;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.payment-icon i {
    font-size: 1.1rem;
}

.payment-icon:hover {
    background: rgba(59, 130, 246, 0.2);
    color: white;
    transform: translateY(-2px);
}

/* Trust Badges */
.trust-badges {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.trust-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #94a3b8;
    font-size: 0.9rem;
}

.trust-badge i {
    color: #3b82f6;
}

/* Footer Bottom */
.footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.9rem;
}

.copyright p {
    color: #64748b;
}

.legal-links {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.legal-links a {
    color: #94a3b8;
    text-decoration: none;
    transition: color 0.3s ease;
}

.legal-links a:hover {
    color: white;
}

.legal-links .separator {
    color: #475569;
}

/* Scroll to Top Button */
.scroll-top-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #3b82f6;
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.scroll-top-btn.visible {
    opacity: 1;
    visibility: visible;
}

.scroll-top-btn:hover {
    background: #2563eb;
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

/* Dark Mode Adjustments */
[data-theme="dark"] .site-footer {
    background: #0a0e17;
}

[data-theme="dark"] .payment-icon {
    background: rgba(255, 255, 255, 0.03);
}

/* Responsive Design */
@media (max-width: 1200px) {
    .footer-top {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .footer-middle {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) {
    .footer-top {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .footer-bottom {
        flex-direction: column;
        text-align: center;
    }
    
    .legal-links {
        justify-content: center;
    }
    
    .scroll-top-btn {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
}

@media (max-width: 480px) {
    .payment-icons {
        justify-content: center;
    }
    
    .trust-badges {
        justify-content: center;
    }
    
    .input-group {
        flex-direction: column;
    }
    
    .newsletter-form button {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Newsletter subscription handler
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('newsletterEmail').value;
            const messageDiv = document.getElementById('newsletterMessage');
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                messageDiv.innerHTML = '<span style="color: #ef4444;">Please enter a valid email address</span>';
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            submitBtn.disabled = true;
            
            // Simulate API call (replace with actual AJAX in production)
            setTimeout(() => {
                messageDiv.innerHTML = '<span style="color: #10b981;">✓ Thank you for subscribing!</span>';
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
                document.getElementById('newsletterEmail').value = '';
                
                // Clear success message after 5 seconds
                setTimeout(() => {
                    messageDiv.innerHTML = '';
                }, 5000);
            }, 1500);
            
            // Uncomment for actual AJAX request:
            /*
            fetch('<?= BASE_URL ?>api/newsletter-subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = '<span style="color: #10b981;">✓ Thank you for subscribing!</span>';
                    document.getElementById('newsletterEmail').value = '';
                } else {
                    messageDiv.innerHTML = '<span style="color: #ef4444;">✗ ' + data.message + '</span>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<span style="color: #ef4444;">✗ An error occurred. Please try again.</span>';
            })
            .finally(() => {
                submitBtn.innerHTML = originalHtml;
                submitBtn.disabled = false;
            });
            */
        });
    }

    // Scroll to top button functionality
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 500) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Lazy load payment method images
    const paymentIcons = document.querySelectorAll('.payment-icon i');
    paymentIcons.forEach(icon => {
        // Icons are already loaded via Font Awesome, no need for lazy loading
    });

    // Track outbound links for analytics
    document.querySelectorAll('footer a[href^="http"]:not([href*="' + window.location.hostname + '"])').forEach(link => {
        link.addEventListener('click', function(e) {
            console.log('Outbound link clicked:', this.href);
            // You can add analytics tracking here
        });
    });

    // Add smooth hover effect for links
    document.querySelectorAll('.footer-links a, .legal-links a').forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s ease';
        });
    });

    // Check for dark mode preference
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
});
</script>