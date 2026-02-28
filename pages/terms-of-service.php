<?php
// pages/terms-of-service.php - Terms of Service Page

$page_title = "Terms of Service";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get terms content from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'terms_content'");
    $stmt->execute();
    $terms_content = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'legal@muhamuktar.com';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_name'");
    $stmt->execute();
    $company_name = $stmt->fetchColumn() ?: 'Muhamuktar Global Venture';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_address'");
    $stmt->execute();
    $company_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'registration_number'");
    $stmt->execute();
    $registration_number = $stmt->fetchColumn() ?: 'RC1234567';
    
} catch (Exception $e) {
    $terms_content = '';
    $contact_email = 'legal@muhamuktar.com';
    $company_name = 'Muhamuktar Global Venture';
    $company_address = '123 Main Street, Lagos, Nigeria';
    $registration_number = 'RC1234567';
}

$last_updated = '2024-01-01';
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Terms of Service</h1>
            <p class="header-description">Last Updated: <?= date('F j, Y', strtotime($last_updated)) ?></p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Terms of Service</span>
            </div>
        </div>
    </section>

    <!-- Quick Summary -->
    <section class="summary-section">
        <div class="container">
            <div class="summary-card">
                <i class="fas fa-gavel"></i>
                <div class="summary-content">
                    <h2>Welcome to <?= htmlspecialchars($company_name) ?></h2>
                    <p>By using our services, you agree to these terms. Please read them carefully.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Terms Navigation -->
    <section class="terms-nav">
        <div class="container">
            <div class="nav-links">
                <a href="#acceptance">Acceptance</a>
                <a href="#eligibility">Eligibility</a>
                <a href="#account">Account Terms</a>
                <a href="#orders">Orders</a>
                <a href="#pricing">Pricing</a>
                <a href="#shipping">Shipping</a>
                <a href="#returns">Returns</a>
                <a href="#conduct">User Conduct</a>
                <a href="#intellectual">Intellectual Property</a>
                <a href="#limitation">Limitation of Liability</a>
                <a href="#termination">Termination</a>
                <a href="#governing">Governing Law</a>
            </div>
        </div>
    </section>

    <!-- Terms Content -->
    <section class="terms-content">
        <div class="container">
            <div class="terms-wrapper">
                <?php if (!empty($terms_content)): ?>
                    <?= nl2br(htmlspecialchars($terms_content)) ?>
                <?php else: ?>

                <!-- Acceptance of Terms -->
                <div class="terms-section" id="acceptance">
                    <h2>1. Acceptance of Terms</h2>
                    <p>Welcome to <?= htmlspecialchars($company_name) ?> ("Company," "we," "us," or "our"). By accessing or using our website, mobile application, or any services provided by us (collectively, the "Services"), you agree to be bound by these Terms of Service ("Terms").</p>
                    <p>If you do not agree to these Terms, you may not access or use our Services. These Terms apply to all visitors, users, and others who access or use our Services.</p>
                </div>

                <!-- Eligibility -->
                <div class="terms-section" id="eligibility">
                    <h2>2. Eligibility</h2>
                    <p>By using our Services, you represent and warrant that:</p>
                    <ul>
                        <li>You are at least 18 years of age or the age of majority in your jurisdiction</li>
                        <li>You have the full power and authority to enter into these Terms</li>
                        <li>You are not located in a country that is subject to trade sanctions</li>
                        <li>You will provide accurate and complete information when creating an account</li>
                        <li>You will keep your account information updated</li>
                    </ul>
                </div>

                <!-- Account Terms -->
                <div class="terms-section" id="account">
                    <h2>3. Account Terms</h2>
                    
                    <h3>Account Creation</h3>
                    <p>To access certain features of our Services, you may need to create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                    
                    <h3>Account Security</h3>
                    <p>You agree to:</p>
                    <ul>
                        <li>Notify us immediately of any unauthorized use of your account</li>
                        <li>Use a strong password and not share it with others</li>
                        <li>Ensure you log out of your account at the end of each session</li>
                    </ul>
                    
                    <h3>Account Information</h3>
                    <p>You must provide accurate, current, and complete information when creating an account. We reserve the right to suspend or terminate accounts with false or misleading information.</p>
                </div>

                <!-- Orders and Purchases -->
                <div class="terms-section" id="orders">
                    <h2>4. Orders and Purchases</h2>
                    
                    <h3>Order Acceptance</h3>
                    <p>When you place an order, you are making an offer to purchase products. We reserve the right to accept or decline your order for any reason, including:</p>
                    <ul>
                        <li>Product unavailability</li>
                        <li>Errors in pricing or product information</li>
                        <li>Payment authorization failure</li>
                        <li>Suspected fraud or unauthorized transaction</li>
                    </ul>
                    
                    <h3>Order Confirmation</h3>
                    <p>We will send you an order confirmation email after receiving your order. This confirmation does not constitute acceptance of your order. A contract between us is formed when we ship your product(s).</p>
                    
                    <h3>Order Modifications</h3>
                    <p>You may modify or cancel your order within 1 hour of placement by contacting customer support. After that, orders may not be modified or cancelled.</p>
                    
                    <h3>Product Availability</h3>
                    <p>All products are subject to availability. We reserve the right to limit quantities and discontinue products at any time.</p>
                </div>

                <!-- Pricing and Payment -->
                <div class="terms-section" id="pricing">
                    <h2>5. Pricing and Payment</h2>
                    
                    <h3>Prices</h3>
                    <p>All prices are in Nigerian Naira (₦) unless otherwise stated. Prices do not include shipping fees, taxes, or duties, which will be added at checkout.</p>
                    
                    <h3>Payment Methods</h3>
                    <p>We accept various payment methods including credit/debit cards, bank transfers, and digital wallets. By providing payment information, you represent that you are authorized to use the payment method.</p>
                    
                    <h3>Payment Authorization</h3>
                    <p>Your payment method may be authorized for the full amount at checkout. Funds will only be captured when your order is confirmed.</p>
                    
                    <h3>Price Errors</h3>
                    <p>In the event of a pricing error, we reserve the right to cancel orders placed at the incorrect price, even after order confirmation.</p>
                </div>

                <!-- Shipping and Delivery -->
                <div class="terms-section" id="shipping">
                    <h2>6. Shipping and Delivery</h2>
                    
                    <h3>Shipping Policy</h3>
                    <p>We ship to addresses within Nigeria and select international locations. Shipping times and costs vary based on location and shipping method selected.</p>
                    
                    <h3>Risk of Loss</h3>
                    <p>Risk of loss and title for products purchased pass to you upon delivery to the carrier.</p>
                    
                    <h3>Delivery Issues</h3>
                    <p>If you experience delivery issues, please contact our support team. We are not responsible for delays caused by carriers or customs.</p>
                </div>

                <!-- Returns and Refunds -->
                <div class="terms-section" id="returns">
                    <h2>7. Returns and Refunds</h2>
                    
                    <h3>Return Policy</h3>
                    <p>We accept returns within 14 days of delivery for most items in original condition. Some items may not be eligible for return (e.g., perishable goods, intimate items).</p>
                    
                    <h3>Refund Process</h3>
                    <p>Refunds are processed within 3-5 business days after receiving and inspecting returned items. Refunds are issued to the original payment method.</p>
                    
                    <h3>Return Shipping</h3>
                    <p>Return shipping costs are the customer's responsibility unless the return is due to our error.</p>
                    
                    <p>For detailed information, please see our <a href="<?= BASE_URL ?>pages/returns.php">Returns Policy</a>.</p>
                </div>

                <!-- User Conduct -->
                <div class="terms-section" id="conduct">
                    <h2>8. User Conduct</h2>
                    
                    <p>You agree not to:</p>
                    <ul>
                        <li>Violate any applicable laws or regulations</li>
                        <li>Infringe upon intellectual property rights</li>
                        <li>Post false, inaccurate, or misleading information</li>
                        <li>Attempt to gain unauthorized access to our systems</li>
                        <li>Interfere with the proper functioning of our Services</li>
                        <li>Engage in any fraudulent activity</li>
                        <li>Harass, abuse, or harm others</li>
                        <li>Impersonate any person or entity</li>
                        <li>Use our Services for any illegal purpose</li>
                    </ul>
                </div>

                <!-- Intellectual Property -->
                <div class="terms-section" id="intellectual">
                    <h2>9. Intellectual Property Rights</h2>
                    
                    <h3>Our Content</h3>
                    <p>All content on our website, including text, graphics, logos, images, software, and the compilation thereof, is our property or the property of our licensors and is protected by copyright, trademark, and other intellectual property laws.</p>
                    
                    <h3>Limited License</h3>
                    <p>We grant you a limited, non-exclusive, non-transferable license to access and use our Services for personal, non-commercial purposes.</p>
                    
                    <h3>User Content</h3>
                    <p>By posting reviews, comments, or other content, you grant us a non-exclusive, royalty-free, perpetual, irrevocable license to use, reproduce, modify, and distribute such content.</p>
                </div>

                <!-- Limitation of Liability -->
                <div class="terms-section" id="limitation">
                    <h2>10. Limitation of Liability</h2>
                    
                    <p>To the maximum extent permitted by law, <?= htmlspecialchars($company_name) ?> shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from:</p>
                    <ul>
                        <li>Your use or inability to use our Services</li>
                        <li>Any conduct or content of any third party</li>
                        <li>Unauthorized access to or alteration of your transmissions or data</li>
                        <li>Statements or conduct of any third party</li>
                    </ul>
                    
                    <p>Our total liability to you shall not exceed the amount you paid for products purchased through our Services.</p>
                </div>

                <!-- Indemnification -->
                <div class="terms-section">
                    <h2>11. Indemnification</h2>
                    <p>You agree to indemnify, defend, and hold harmless <?= htmlspecialchars($company_name) ?> and our officers, directors, employees, and agents from and against any claims, liabilities, damages, losses, and expenses arising out of or in any way connected with:</p>
                    <ul>
                        <li>Your access to or use of our Services</li>
                        <li>Your violation of these Terms</li>
                        <li>Your violation of any third-party rights</li>
                    </ul>
                </div>

                <!-- Termination -->
                <div class="terms-section" id="termination">
                    <h2>12. Termination</h2>
                    
                    <h3>By You</h3>
                    <p>You may terminate your account at any time by contacting customer support or through your account settings.</p>
                    
                    <h3>By Us</h3>
                    <p>We may suspend or terminate your account or access to our Services at any time, without notice, for conduct that we believe violates these Terms or is harmful to other users or our business interests.</p>
                    
                    <h3>Effect of Termination</h3>
                    <p>Upon termination, your right to use our Services will immediately cease. Provisions of these Terms that by their nature should survive termination shall survive, including ownership provisions, warranty disclaimers, and limitations of liability.</p>
                </div>

                <!-- Governing Law -->
                <div class="terms-section" id="governing">
                    <h2>13. Governing Law</h2>
                    <p>These Terms shall be governed by and construed in accordance with the laws of the Federal Republic of Nigeria, without regard to its conflict of law provisions.</p>
                    
                    <p>Any dispute arising out of or relating to these Terms or our Services shall be resolved exclusively in the courts of Lagos State, Nigeria.</p>
                </div>

                <!-- Dispute Resolution -->
                <div class="terms-section">
                    <h2>14. Dispute Resolution</h2>
                    
                    <h3>Informal Resolution</h3>
                    <p>Before filing a claim, you agree to attempt to resolve any dispute informally by contacting us. We will attempt to resolve the dispute internally.</p>
                    
                    <h3>Arbitration</h3>
                    <p>If we cannot resolve the dispute informally, you agree to submit the dispute to binding arbitration in accordance with the Arbitration and Conciliation Act of Nigeria.</p>
                </div>

                <!-- Modifications to Terms -->
                <div class="terms-section">
                    <h2>15. Modifications to Terms</h2>
                    <p>We reserve the right to modify these Terms at any time. We will provide notice of material changes by posting the updated Terms on our website and updating the "Last Updated" date.</p>
                    <p>Your continued use of our Services after any such changes constitutes your acceptance of the new Terms.</p>
                </div>

                <!-- Severability -->
                <div class="terms-section">
                    <h2>16. Severability</h2>
                    <p>If any provision of these Terms is held to be invalid or unenforceable, such provision shall be struck and the remaining provisions shall be enforced to the fullest extent under law.</p>
                </div>

                <!-- Entire Agreement -->
                <div class="terms-section">
                    <h2>17. Entire Agreement</h2>
                    <p>These Terms, together with our Privacy Policy and any other agreements incorporated by reference, constitute the entire agreement between you and <?= htmlspecialchars($company_name) ?> regarding your use of our Services.</p>
                </div>

                <!-- Contact Information -->
                <div class="terms-section" id="contact">
                    <h2>18. Contact Information</h2>
                    <p>If you have any questions about these Terms, please contact us:</p>
                    
                    <div class="contact-details">
                        <p><strong><?= htmlspecialchars($company_name) ?></strong></p>
                        <p><strong>Registration Number:</strong> <?= htmlspecialchars($registration_number) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($company_address) ?></p>
                        <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a></p>
                        <p><strong>Phone:</strong> <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone ?? '+2341234567890') ?>"><?= htmlspecialchars($contact_phone ?? '+234 123 456 7890') ?></a></p>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Acceptance Section -->
    <section class="acceptance-section">
        <div class="container">
            <div class="acceptance-card">
                <i class="fas fa-check-circle"></i>
                <h2>Acceptance of Terms</h2>
                <p>By using our services, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.</p>
                <div class="acceptance-actions">
                    <a href="<?= BASE_URL ?>pages/privacy-policy.php" class="btn-secondary">Privacy Policy</a>
                    <a href="<?= BASE_URL ?>pages/cookie-policy.php" class="btn-secondary">Cookie Policy</a>
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

/* Summary Section */
.summary-section {
    padding: 3rem 0;
    background: white;
}

.summary-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 2rem;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 2rem;
    box-shadow: 0 10px 30px rgba(59, 130, 246, 0.2);
}

.summary-card i {
    font-size: 3rem;
}

.summary-content h2 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.summary-content p {
    opacity: 0.9;
    margin: 0;
}

/* Terms Navigation */
.terms-nav {
    background: white;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 70px;
    z-index: 100;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.nav-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
}

.nav-links a {
    color: var(--text-light);
    text-decoration: none;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s;
}

.nav-links a:hover {
    background: var(--bg);
    color: var(--primary);
}

/* Terms Content */
.terms-content {
    padding: 4rem 0;
    background: white;
}

.terms-wrapper {
    max-width: 900px;
    margin: 0 auto;
    line-height: 1.8;
}

.terms-section {
    margin-bottom: 3rem;
    scroll-margin-top: 120px;
}

.terms-section h2 {
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    color: var(--text);
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
}

.terms-section h3 {
    font-size: 1.3rem;
    margin: 1.5rem 0 1rem;
    color: var(--text);
}

.terms-section p {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.terms-section ul {
    margin: 1rem 0 1rem 2rem;
    color: var(--text-light);
}

.terms-section li {
    margin-bottom: 0.5rem;
}

.terms-section strong {
    color: var(--text);
}

.terms-section a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.terms-section a:hover {
    text-decoration: underline;
}

/* Contact Details */
.contact-details {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
    margin: 1.5rem 0;
}

.contact-details p {
    margin-bottom: 0.5rem;
}

.contact-details a {
    color: var(--primary);
    text-decoration: none;
}

.contact-details a:hover {
    text-decoration: underline;
}

/* Acceptance Section */
.acceptance-section {
    padding: 4rem 0;
    background: var(--bg);
}

.acceptance-card {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.acceptance-card i {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.acceptance-card h2 {
    font-size: 1.8rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.acceptance-card p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.acceptance-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.btn-secondary {
    display: inline-block;
    padding: 0.75rem 2rem;
    background: var(--bg);
    color: var(--text);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Responsive */
@media (max-width: 992px) {
    .nav-links {
        flex-wrap: wrap;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .summary-card {
        flex-direction: column;
        text-align: center;
        padding: 2rem;
    }
    
    .terms-wrapper {
        padding: 0 1rem;
    }
    
    .terms-section h2 {
        font-size: 1.5rem;
    }
    
    .terms-section h3 {
        font-size: 1.2rem;
    }
    
    .acceptance-card {
        padding: 2rem;
    }
    
    .acceptance-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .terms-section ul {
        margin-left: 1rem;
    }
    
    .contact-details {
        padding: 1rem;
    }
}

/* Print Styles */
@media print {
    .page-header,
    .summary-section,
    .terms-nav,
    .acceptance-section,
    footer {
        display: none;
    }
    
    .terms-content {
        padding: 1rem 0;
    }
    
    .terms-wrapper {
        max-width: 100%;
    }
}
</style>

<script>
// Highlight active navigation link while scrolling
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('.terms-section');
    const navLinks = document.querySelectorAll('.nav-links a');
    
    let current = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 150;
        if (pageYOffset >= sectionTop) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.style.background = 'var(--bg)';
            link.style.color = 'var(--primary)';
        } else {
            link.style.background = '';
            link.style.color = '';
        }
    });
});

// Smooth scroll for navigation links
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetSection = document.querySelector(targetId);
        
        if (targetSection) {
            targetSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Print functionality
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        window.print();
    }
});

// Last modified date
document.addEventListener('DOMContentLoaded', function() {
    const lastModified = document.lastModified;
    console.log('Page last modified:', lastModified);
});
</script>

<?php require_once '../includes/footer.php'; ?>