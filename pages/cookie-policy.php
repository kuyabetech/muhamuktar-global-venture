<?php
// pages/cookie-policy.php - Cookie Policy Page

$page_title = "Cookie Policy";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get cookie policy content from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'cookie_policy_content'");
    $stmt->execute();
    $cookie_content = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'privacy@muhamuktar.com';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'company_name'");
    $stmt->execute();
    $company_name = $stmt->fetchColumn() ?: 'Muhamuktar Global Venture';
    
} catch (Exception $e) {
    $cookie_content = '';
    $contact_email = 'privacy@muhamuktar.com';
    $company_name = 'Muhamuktar Global Venture';
}

$last_updated = '2024-01-01';

// Handle cookie preferences
$preferences_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $essential = isset($_POST['essential']) ? 1 : 1; // Always enabled
    $analytics = isset($_POST['analytics']) ? 1 : 0;
    $marketing = isset($_POST['marketing']) ? 1 : 0;
    $functional = isset($_POST['functional']) ? 1 : 0;
    
    setcookie('cookie_consent_essential', '1', time() + (365 * 24 * 60 * 60), '/');
    setcookie('cookie_consent_analytics', $analytics, time() + (365 * 24 * 60 * 60), '/');
    setcookie('cookie_consent_marketing', $marketing, time() + (365 * 24 * 60 * 60), '/');
    setcookie('cookie_consent_functional', $functional, time() + (365 * 24 * 60 * 60), '/');
    setcookie('cookie_consent_saved', '1', time() + (365 * 24 * 60 * 60), '/');
    
    $preferences_saved = true;
}

// Get current preferences
$analytics_consent = $_COOKIE['cookie_consent_analytics'] ?? 0;
$marketing_consent = $_COOKIE['cookie_consent_marketing'] ?? 0;
$functional_consent = $_COOKIE['cookie_consent_functional'] ?? 0;
$consent_saved = $_COOKIE['cookie_consent_saved'] ?? 0;
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Cookie Policy</h1>
            <p class="header-description">Last Updated: <?= date('F j, Y', strtotime($last_updated)) ?></p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/privacy-policy.php">Privacy Policy</a>
                <span class="separator">/</span>
                <span class="current">Cookie Policy</span>
            </div>
        </div>
    </section>

    <!-- Quick Summary -->
    <section class="summary-section">
        <div class="container">
            <div class="summary-card">
                <i class="fas fa-cookie-bite"></i>
                <div class="summary-content">
                    <h2>Our Use of Cookies</h2>
                    <p><?= htmlspecialchars($company_name) ?> uses cookies to enhance your browsing experience and provide personalized services.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Consent Status -->
    <?php if ($consent_saved): ?>
        <section class="status-section">
            <div class="container">
                <div class="status-card">
                    <i class="fas fa-check-circle"></i>
                    <div class="status-content">
                        <h3>Your Cookie Preferences Are Saved</h3>
                        <p>You can update your preferences at any time using the form below.</p>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Cookie Policy Content -->
    <section class="policy-content">
        <div class="container">
            <div class="policy-wrapper">
                <?php if (!empty($cookie_content)): ?>
                    <?= nl2br(htmlspecialchars($cookie_content)) ?>
                <?php else: ?>

                <!-- Introduction -->
                <div class="policy-section" id="introduction">
                    <h2>1. Introduction</h2>
                    <p>This Cookie Policy explains how <?= htmlspecialchars($company_name) ?> ("we," "us," or "our") uses cookies and similar technologies on our website. By using our website, you consent to the use of cookies in accordance with this policy.</p>
                </div>

                <!-- What are Cookies -->
                <div class="policy-section" id="what-are-cookies">
                    <h2>2. What Are Cookies?</h2>
                    <p>Cookies are small text files that are stored on your device (computer, tablet, or mobile) when you visit a website. They are widely used to make websites work more efficiently and provide information to the website owners.</p>
                    
                    <p>Cookies can be:</p>
                    <ul>
                        <li><strong>Session Cookies:</strong> Temporary cookies that expire when you close your browser</li>
                        <li><strong>Persistent Cookies:</strong> Remain on your device for a set period or until you delete them</li>
                        <li><strong>First-party Cookies:</strong> Set by the website you are visiting</li>
                        <li><strong>Third-party Cookies:</strong> Set by a domain other than the one you are visiting</li>
                    </ul>
                </div>

                <!-- Types of Cookies We Use -->
                <div class="policy-section" id="types">
                    <h2>3. Types of Cookies We Use</h2>
                    
                    <div class="cookie-types">
                        <div class="cookie-type">
                            <h3>Essential Cookies</h3>
                            <p>These cookies are necessary for the website to function properly. They enable core functionality such as security, network management, and account access. You cannot opt out of these cookies.</p>
                            <ul>
                                <li><strong>Session ID:</strong> Maintains your login state</li>
                                <li><strong>Security Tokens:</strong> Protects against CSRF attacks</li>
                                <li><strong>Cart Items:</strong> Remembers items in your shopping cart</li>
                            </ul>
                        </div>
                        
                        <div class="cookie-type">
                            <h3>Functional Cookies</h3>
                            <p>These cookies enhance your experience by remembering your preferences and choices.</p>
                            <ul>
                                <li><strong>Language Preference:</strong> Remembers your language selection</li>
                                <li><strong>Currency:</strong> Remembers your preferred currency</li>
                                <li><strong>Theme:</strong> Remembers your light/dark mode preference</li>
                            </ul>
                        </div>
                        
                        <div class="cookie-type">
                            <h3>Analytics Cookies</h3>
                            <p>These cookies help us understand how visitors interact with our website by collecting anonymous information.</p>
                            <ul>
                                <li><strong>Google Analytics:</strong> Tracks page views and user behavior</li>
                                <li><strong>Performance Metrics:</strong> Measures site speed and performance</li>
                            </ul>
                        </div>
                        
                        <div class="cookie-type">
                            <h3>Marketing Cookies</h3>
                            <p>These cookies track your browsing habits to deliver targeted advertisements.</p>
                            <ul>
                                <li><strong>Facebook Pixel:</strong> Tracks conversions for Facebook ads</li>
                                <li><strong>Google Ads:</strong> Enables remarketing campaigns</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Third-Party Cookies -->
                <div class="policy-section" id="third-party">
                    <h2>4. Third-Party Cookies</h2>
                    <p>We use services from third parties that may set cookies on your device:</p>
                    
                    <table class="cookie-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Purpose</th>
                                <th>Privacy Policy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Google Analytics</td>
                                <td>Website analytics</td>
                                <td><a href="https://policies.google.com/privacy" target="_blank">View Policy</a></td>
                            </tr>
                            <tr>
                                <td>Paystack</td>
                                <td>Payment processing</td>
                                <td><a href="https://paystack.com/privacy" target="_blank">View Policy</a></td>
                            </tr>
                            <tr>
                                <td>Facebook</td>
                                <td>Marketing & analytics</td>
                                <td><a href="https://www.facebook.com/privacy/policy" target="_blank">View Policy</a></td>
                            </tr>
                            <tr>
                                <td>Cloudflare</td>
                                <td>Security & performance</td>
                                <td><a href="https://www.cloudflare.com/privacypolicy/" target="_blank">View Policy</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Cookie Duration -->
                <div class="policy-section" id="duration">
                    <h2>5. How Long Do Cookies Last?</h2>
                    
                    <table class="duration-table">
                        <thead>
                            <tr>
                                <th>Cookie Type</th>
                                <th>Typical Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Session Cookies</td>
                                <td>Until browser is closed</td>
                            </tr>
                            <tr>
                                <td>Functional Cookies</td>
                                <td>1 year</td>
                            </tr>
                            <tr>
                                <td>Analytics Cookies</td>
                                <td>2 years</td>
                            </tr>
                            <tr>
                                <td>Marketing Cookies</td>
                                <td>90 days</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Cookie Preferences -->
    <section class="preferences-section" id="preferences">
        <div class="container">
            <h2 class="section-title">Manage Cookie Preferences</h2>
            
            <?php if ($preferences_saved): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Your cookie preferences have been saved successfully.
                </div>
            <?php endif; ?>

            <div class="preferences-card">
                <form method="post" class="preferences-form" id="cookieForm">
                    <div class="cookie-option essential">
                        <div class="cookie-option-header">
                            <div>
                                <h3>Essential Cookies</h3>
                                <p>Required for the website to function properly. Cannot be disabled.</p>
                            </div>
                            <div class="cookie-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="essential" value="1" checked disabled>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Always Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="cookie-option">
                        <div class="cookie-option-header">
                            <div>
                                <h3>Functional Cookies</h3>
                                <p>Enable enhanced functionality and personalization.</p>
                            </div>
                            <div class="cookie-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="functional" value="1" 
                                           <?= $functional_consent ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="cookie-option">
                        <div class="cookie-option-header">
                            <div>
                                <h3>Analytics Cookies</h3>
                                <p>Help us understand how visitors interact with our website.</p>
                            </div>
                            <div class="cookie-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="analytics" value="1" 
                                           <?= $analytics_consent ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="cookie-option">
                        <div class="cookie-option-header">
                            <div>
                                <h3>Marketing Cookies</h3>
                                <p>Used to deliver relevant advertisements and track campaign performance.</p>
                            </div>
                            <div class="cookie-toggle">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="marketing" value="1" 
                                           <?= $marketing_consent ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="preferences-actions">
                        <button type="submit" name="save_preferences" class="btn-primary">
                            Save Preferences
                        </button>
                        <button type="button" class="btn-secondary" onclick="acceptAll()">
                            Accept All
                        </button>
                        <button type="button" class="btn-text" onclick="rejectAll()">
                            Reject All Non-Essential
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- How to Control Cookies -->
    <section class="control-section">
        <div class="container">
            <h2 class="section-title">How to Control Cookies in Your Browser</h2>
            
            <div class="control-grid">
                <div class="browser-card">
                    <i class="fab fa-chrome"></i>
                    <h3>Chrome</h3>
                    <p>Settings → Privacy and Security → Cookies and other site data</p>
                    <a href="https://support.google.com/chrome/answer/95647" target="_blank" class="learn-more">Learn More →</a>
                </div>
                
                <div class="browser-card">
                    <i class="fab fa-firefox"></i>
                    <h3>Firefox</h3>
                    <p>Options → Privacy & Security → Cookies and Site Data</p>
                    <a href="https://support.mozilla.org/en-US/kb/cookies-information-websites-store-on-your-computer" target="_blank" class="learn-more">Learn More →</a>
                </div>
                
                <div class="browser-card">
                    <i class="fab fa-safari"></i>
                    <h3>Safari</h3>
                    <p>Preferences → Privacy → Cookies and website data</p>
                    <a href="https://support.apple.com/en-us/HT201265" target="_blank" class="learn-more">Learn More →</a>
                </div>
                
                <div class="browser-card">
                    <i class="fab fa-edge"></i>
                    <h3>Edge</h3>
                    <p>Settings → Cookies and site permissions → Cookies and site data</p>
                    <a href="https://support.microsoft.com/en-us/microsoft-edge/delete-cookies-in-microsoft-edge-63947406-40ac-c3b8-57b9-2a946a29ae09" target="_blank" class="learn-more">Learn More →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-card">
                <h2>Questions About Cookies?</h2>
                <p>If you have any questions about our use of cookies, please contact us.</p>
                <div class="contact-links">
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="contact-link">
                        <i class="fas fa-envelope"></i>
                        <?= htmlspecialchars($contact_email) ?>
                    </a>
                    <a href="<?= BASE_URL ?>pages/contact.php" class="contact-link">
                        <i class="fas fa-comment"></i>
                        Contact Form
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

/* Status Section */
.status-section {
    padding: 1rem 0;
    background: #f0fdf4;
}

.status-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border-left: 4px solid #10b981;
}

.status-card i {
    font-size: 1.5rem;
    color: #10b981;
}

.status-card h3 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
    color: #065f46;
}

.status-card p {
    color: #047857;
    margin: 0;
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

/* Cookie Types */
.cookie-types {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin: 2rem 0;
}

.cookie-type {
    background: var(--bg);
    padding: 1.5rem;
    border-radius: 12px;
}

.cookie-type h3 {
    margin-top: 0;
    color: var(--primary);
}

/* Tables */
.cookie-table,
.duration-table {
    width: 100%;
    border-collapse: collapse;
    margin: 2rem 0;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.cookie-table th,
.duration-table th {
    background: var(--primary);
    color: white;
    padding: 1rem;
    text-align: left;
}

.cookie-table td,
.duration-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
}

.cookie-table tr:last-child td,
.duration-table tr:last-child td {
    border-bottom: none;
}

.cookie-table a {
    color: var(--primary);
    text-decoration: none;
}

.cookie-table a:hover {
    text-decoration: underline;
}

/* Preferences Section */
.preferences-section {
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

.preferences-card {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.cookie-option {
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--border);
}

.cookie-option:last-child {
    border-bottom: none;
}

.cookie-option.essential {
    background: #f0f9ff;
    margin: -1.5rem -1.5rem 0;
    padding: 1.5rem;
    border-radius: 20px 20px 0 0;
}

.cookie-option-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.cookie-option-header h3 {
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
    color: var(--text);
}

.cookie-option-header p {
    color: var(--text-light);
    font-size: 0.9rem;
    margin: 0;
}

/* Toggle Switch */
.cookie-toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: var(--border);
    transition: .3s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary);
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

input:disabled + .toggle-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

.toggle-label {
    font-size: 0.9rem;
    color: var(--text-light);
}

/* Preferences Actions */
.preferences-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.btn-primary {
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: white;
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

.btn-text {
    padding: 0.75rem 1.5rem;
    background: none;
    color: var(--text-light);
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-text:hover {
    color: var(--primary);
}

/* Alert */
.alert {
    max-width: 800px;
    margin: 0 auto 2rem;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

/* Control Section */
.control-section {
    padding: 4rem 0;
    background: white;
}

.control-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.browser-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 12px;
    transition: transform 0.3s;
}

.browser-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.browser-card i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: var(--primary);
}

.browser-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.browser-card p {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.learn-more {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.learn-more:hover {
    text-decoration: underline;
}

/* Contact Section */
.contact-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}

.contact-card {
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
    color: white;
}

.contact-card h2 {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.contact-card p {
    font-size: 1.1rem;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.contact-links {
    display: flex;
    gap: 2rem;
    justify-content: center;
}

.contact-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: white;
    text-decoration: none;
    padding: 0.75rem 1.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    transition: all 0.3s;
}

.contact-link:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 992px) {
    .cookie-types {
        grid-template-columns: 1fr;
    }
    
    .control-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .summary-card {
        flex-direction: column;
        text-align: center;
    }
    
    .cookie-option-header {
        flex-direction: column;
    }
    
    .preferences-actions {
        flex-direction: column;
    }
    
    .contact-links {
        flex-direction: column;
    }
    
    .control-grid {
        grid-template-columns: 1fr;
    }
    
    .cookie-table,
    .duration-table {
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    .policy-wrapper {
        padding: 0 1rem;
    }
    
    .preferences-card {
        padding: 1.5rem;
    }
}
</style>

<script>
function acceptAll() {
    document.querySelector('input[name="functional"]').checked = true;
    document.querySelector('input[name="analytics"]').checked = true;
    document.querySelector('input[name="marketing"]').checked = true;
}

function rejectAll() {
    document.querySelector('input[name="functional"]').checked = false;
    document.querySelector('input[name="analytics"]').checked = false;
    document.querySelector('input[name="marketing"]').checked = false;
}

// Show cookie preferences banner if not set
document.addEventListener('DOMContentLoaded', function() {
    const consentSaved = <?= $consent_saved ? 'true' : 'false' ?>;
    
    if (!consentSaved) {
        // Show cookie banner (you can implement this)
        console.log('Show cookie consent banner');
    }
});

// Track cookie consent changes
document.getElementById('cookieForm')?.addEventListener('submit', function() {
    console.log('Cookie preferences saved:', {
        functional: document.querySelector('input[name="functional"]').checked,
        analytics: document.querySelector('input[name="analytics"]').checked,
        marketing: document.querySelector('input[name="marketing"]').checked
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>