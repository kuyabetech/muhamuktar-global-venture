<?php
// pages/privacy-policy.php - Privacy Policy Page

$page_title = "Privacy Policy";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get privacy policy content from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'privacy_policy_content'");
    $stmt->execute();
    $privacy_content = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'privacy@muhamuktar.com';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_name'");
    $stmt->execute();
    $company_name = $stmt->fetchColumn() ?: 'Muhamuktar Global Venture';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_address'");
    $stmt->execute();
    $company_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';
    
} catch (Exception $e) {
    $privacy_content = '';
    $contact_email = 'privacy@muhamuktar.com';
    $company_name = 'Muhamuktar Global Venture';
    $company_address = '123 Main Street, Lagos, Nigeria';
}

$last_updated = '2024-01-01';
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Privacy Policy</h1>
            <p class="header-description">Last Updated: <?= date('F j, Y', strtotime($last_updated)) ?></p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Privacy Policy</span>
            </div>
        </div>
    </section>

    <!-- Policy Navigation -->
    <section class="policy-nav">
        <div class="container">
            <div class="nav-links">
                <a href="#introduction">Introduction</a>
                <a href="#information">Information We Collect</a>
                <a href="#use">How We Use Information</a>
                <a href="#sharing">Information Sharing</a>
                <a href="#cookies">Cookies</a>
                <a href="#rights">Your Rights</a>
                <a href="#security">Security</a>
                <a href="#changes">Policy Changes</a>
                <a href="#contact">Contact Us</a>
            </div>
        </div>
    </section>

    <!-- Policy Content -->
    <section class="policy-content">
        <div class="container">
            <div class="policy-wrapper">
                <?php if (!empty($privacy_content)): ?>
                    <?= nl2br(htmlspecialchars($privacy_content)) ?>
                <?php else: ?>
                
                <!-- Introduction -->
                <div class="policy-section" id="introduction">
                    <h2>1. Introduction</h2>
                    <p>Welcome to <?= htmlspecialchars($company_name) ?>. We respect your privacy and are committed to protecting your personal data. This privacy policy explains how we collect, use, disclose, and safeguard your information when you visit our website or make a purchase.</p>
                    <p>Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access the site.</p>
                </div>

                <!-- Information We Collect -->
                <div class="policy-section" id="information">
                    <h2>2. Information We Collect</h2>
                    
                    <h3>Personal Information</h3>
                    <p>We may collect personal information that you voluntarily provide to us when you:</p>
                    <ul>
                        <li>Register for an account</li>
                        <li>Make a purchase</li>
                        <li>Sign up for our newsletter</li>
                        <li>Contact customer support</li>
                        <li>Participate in promotions or surveys</li>
                    </ul>
                    
                    <p>This information may include:</p>
                    <ul>
                        <li>Name and contact information (email, phone number, shipping address)</li>
                        <li>Payment information (credit card details, billing address)</li>
                        <li>Account credentials (username, password)</li>
                        <li>Profile information (preferences, order history)</li>
                    </ul>

                    <h3>Automatically Collected Information</h3>
                    <p>When you visit our website, we automatically collect certain information about your device, including:</p>
                    <ul>
                        <li>IP address</li>
                        <li>Browser type and version</li>
                        <li>Operating system</li>
                        <li>Pages visited and time spent</li>
                        <li>Referring website addresses</li>
                    </ul>
                </div>

                <!-- How We Use Information -->
                <div class="policy-section" id="use">
                    <h2>3. How We Use Your Information</h2>
                    <p>We use the information we collect to:</p>
                    <ul>
                        <li>Process and fulfill your orders</li>
                        <li>Manage your account</li>
                        <li>Send order confirmations and updates</li>
                        <li>Respond to your comments and questions</li>
                        <li>Send marketing communications (with your consent)</li>
                        <li>Improve our website and services</li>
                        <li>Prevent fraudulent transactions</li>
                        <li>Comply with legal obligations</li>
                    </ul>
                </div>

                <!-- Information Sharing -->
                <div class="policy-section" id="sharing">
                    <h2>4. Information Sharing</h2>
                    <p>We may share your information with:</p>
                    
                    <h3>Service Providers</h3>
                    <p>We share information with third-party service providers who perform services on our behalf, such as:</p>
                    <ul>
                        <li>Payment processors (Paystack, Flutterwave)</li>
                        <li>Shipping carriers</li>
                        <li>Marketing platforms</li>
                        <li>Analytics providers</li>
                    </ul>

                    <h3>Legal Requirements</h3>
                    <p>We may disclose your information if required to do so by law or in response to valid requests by public authorities.</p>

                    <h3>Business Transfers</h3>
                    <p>If we are involved in a merger, acquisition, or sale of assets, your information may be transferred as part of that transaction.</p>

                    <p><strong>We do not sell your personal information to third parties.</strong></p>
                </div>

                <!-- Cookies -->
                <div class="policy-section" id="cookies">
                    <h2>5. Cookies and Tracking Technologies</h2>
                    <p>We use cookies and similar tracking technologies to track activity on our website and hold certain information. Cookies are files with small amount of data which may include an anonymous unique identifier.</p>
                    
                    <h3>Types of Cookies We Use:</h3>
                    <ul>
                        <li><strong>Essential Cookies:</strong> Required for the website to function properly</li>
                        <li><strong>Preference Cookies:</strong> Remember your preferences and settings</li>
                        <li><strong>Analytics Cookies:</strong> Help us understand how visitors interact with our website</li>
                        <li><strong>Marketing Cookies:</strong> Used to deliver relevant advertisements</li>
                    </ul>

                    <p>You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our website.</p>
                    
                    <p><a href="<?= BASE_URL ?>pages/cookie-policy.php" class="policy-link">Learn more about our Cookie Policy →</a></p>
                </div>

                <!-- Your Rights -->
                <div class="policy-section" id="rights">
                    <h2>6. Your Privacy Rights</h2>
                    
                    <h3>Access and Control</h3>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Access the personal information we hold about you</li>
                        <li>Request correction of inaccurate information</li>
                        <li>Request deletion of your information</li>
                        <li>Object to processing of your information</li>
                        <li>Request restriction of processing</li>
                        <li>Request data portability</li>
                        <li>Withdraw consent at any time</li>
                    </ul>

                    <h3>Marketing Communications</h3>
                    <p>You can opt out of receiving marketing communications from us by:</p>
                    <ul>
                        <li>Clicking the "unsubscribe" link in any marketing email</li>
                        <li>Updating your account preferences</li>
                        <li>Contacting us directly</li>
                    </ul>

                    <h3>Account Deletion</h3>
                    <p>You can request deletion of your account by contacting our support team. Note that we may retain certain information as required by law or for legitimate business purposes.</p>
                </div>

                <!-- Data Security -->
                <div class="policy-section" id="security">
                    <h2>7. Data Security</h2>
                    <p>We implement appropriate technical and organizational security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
                    
                    <p>These measures include:</p>
                    <ul>
                        <li>SSL/TLS encryption for data transmission</li>
                        <li>Secure payment processing through PCI-compliant providers</li>
                        <li>Regular security assessments</li>
                        <li>Access controls and authentication</li>
                    </ul>

                    <p>However, no method of transmission over the Internet or electronic storage is 100% secure. While we strive to use commercially acceptable means to protect your personal information, we cannot guarantee its absolute security.</p>
                </div>

                <!-- Children's Privacy -->
                <div class="policy-section">
                    <h2>8. Children's Privacy</h2>
                    <p>Our website is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you become aware that a child has provided us with personal information, please contact us.</p>
                </div>

                <!-- International Transfers -->
                <div class="policy-section">
                    <h2>9. International Data Transfers</h2>
                    <p>Your information may be transferred to and maintained on computers located outside of your state, province, country, or other governmental jurisdiction where the data protection laws may differ from those in your jurisdiction.</p>
                    <p>By using our website, you consent to the transfer of your information to Nigeria and other countries where we operate.</p>
                </div>

                <!-- Changes to Policy -->
                <div class="policy-section" id="changes">
                    <h2>10. Changes to This Privacy Policy</h2>
                    <p>We may update our privacy policy from time to time. We will notify you of any changes by posting the new privacy policy on this page and updating the "Last Updated" date.</p>
                    <p>You are advised to review this privacy policy periodically for any changes. Changes to this privacy policy are effective when they are posted on this page.</p>
                </div>

                <!-- Contact Us -->
                <div class="policy-section" id="contact">
                    <h2>11. Contact Us</h2>
                    <p>If you have any questions about this privacy policy, please contact us:</p>
                    
                    <div class="contact-details">
                        <p><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($contact_email) ?>"><?= htmlspecialchars($contact_email) ?></a></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($company_address) ?></p>
                        <p><strong>Phone:</strong> <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone ?? '+2341234567890') ?>"><?= htmlspecialchars($contact_phone ?? '+234 123 456 7890') ?></a></p>
                    </div>

                    <p>We aim to respond to all privacy-related inquiries within 5 business days.</p>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Consent Section -->
    <section class="consent-section">
        <div class="container">
            <div class="consent-card">
                <h2>Privacy Consent</h2>
                <p>By using our website, you consent to our privacy policy and agree to its terms.</p>
                <div class="consent-actions">
                    <a href="<?= BASE_URL ?>pages/terms-of-service.php" class="btn-secondary">Terms of Service</a>
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

/* Policy Navigation */
.policy-nav {
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
    gap: 1rem;
    justify-content: center;
}

.nav-links a {
    color: var(--text-light);
    text-decoration: none;
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    transition: all 0.3s;
}

.nav-links a:hover {
    background: var(--bg);
    color: var(--primary);
}

/* Policy Content */
.policy-content {
    padding: 4rem 0;
    background: white;
}

.policy-wrapper {
    max-width: 900px;
    margin: 0 auto;
    line-height: 1.8;
}

.policy-section {
    margin-bottom: 3rem;
    scroll-margin-top: 120px;
}

.policy-section h2 {
    font-size: 1.8rem;
    margin-bottom: 1.5rem;
    color: var(--text);
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
}

.policy-section h3 {
    font-size: 1.3rem;
    margin: 1.5rem 0 1rem;
    color: var(--text);
}

.policy-section p {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.policy-section ul {
    margin: 1rem 0 1rem 2rem;
    color: var(--text-light);
}

.policy-section li {
    margin-bottom: 0.5rem;
}

.policy-section strong {
    color: var(--text);
}

.policy-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.policy-link:hover {
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

/* Consent Section */
.consent-section {
    padding: 4rem 0;
    background: var(--bg);
}

.consent-card {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.consent-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.consent-card p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.consent-actions {
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
@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .nav-links {
        flex-wrap: wrap;
        justify-content: flex-start;
    }
    
    .policy-wrapper {
        padding: 0 1rem;
    }
    
    .policy-section h2 {
        font-size: 1.5rem;
    }
    
    .policy-section h3 {
        font-size: 1.2rem;
    }
    
    .consent-card {
        padding: 2rem;
    }
    
    .consent-actions {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .policy-section ul {
        margin-left: 1rem;
    }
    
    .contact-details {
        padding: 1rem;
    }
}
</style>

<script>
// Highlight active navigation link while scrolling
window.addEventListener('scroll', function() {
    const sections = document.querySelectorAll('.policy-section');
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
</script>

<style media="print">
    .page-header,
    .policy-nav,
    .consent-section,
    .footer {
        display: none;
    }
    
    .policy-content {
        padding: 2rem 0;
    }
    
    .policy-wrapper {
        max-width: 100%;
    }
</style>

<?php require_once '../includes/footer.php'; ?>