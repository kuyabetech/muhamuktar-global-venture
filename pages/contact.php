<?php
// pages/contact.php - Contact Page

$page_title = "Contact Us";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get contact information from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_address'");
    $stmt->execute();
    $contact_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';

    // Get business hours
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'business_hours'");
    $stmt->execute();
    $business_hours = $stmt->fetchColumn() ?: 'Monday - Friday: 9am - 6pm<br>Saturday: 10am - 4pm<br>Sunday: Closed';

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

    // Get map coordinates (if any)
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'map_latitude'");
    $stmt->execute();
    $map_lat = $stmt->fetchColumn() ?: '6.5244';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'map_longitude'");
    $stmt->execute();
    $map_lng = $stmt->fetchColumn() ?: '3.3792';

} catch (Exception $e) {
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $contact_address = '123 Main Street, Lagos, Nigeria';
    $business_hours = 'Monday - Friday: 9am - 6pm<br>Saturday: 10am - 4pm<br>Sunday: Closed';
    $social_facebook = '';
    $social_twitter = '';
    $social_instagram = '';
    $social_whatsapp = '';
    $map_lat = '6.5244';
    $map_lng = '3.3792';
}

// Handle contact form submission
$form_submitted = false;
$form_error = '';
$form_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $errors = [];

    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($message)) {
        $errors[] = "Message is required";
    }

    if (empty($errors)) {
        // In production, this would send an email and save to database
        // For now, just show success message
        
        // Save to database if table exists
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(50),
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    status ENUM('new','read','replied') DEFAULT 'new',
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, phone, subject, message, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, 
                $email, 
                $phone, 
                $subject, 
                $message, 
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
        } catch (Exception $e) {
            // Table might not exist, ignore
            error_log("Contact form save error: " . $e->getMessage());
        }
        
        // Send email notification (in production)
        $to = $contact_email;
        $email_subject = "New Contact Form Message: $subject";
        $email_message = "Name: $name\nEmail: $email\nPhone: $phone\n\nMessage:\n$message";
        $headers = "From: $email";
        
        // mail($to, $email_subject, $email_message, $headers); // Uncomment in production
        
        $form_success = "Thank you for contacting us! We'll get back to you within 24 hours.";
        $form_submitted = true;
        
        // Log the contact
        error_log("Contact form submission from: $name ($email)");
    } else {
        $form_error = implode("<br>", $errors);
    }
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Contact Us</h1>
            <p class="header-description">We're here to help! Reach out to us anytime.</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Contact</span>
            </div>
        </div>
    </section>

    <!-- Contact Info Cards -->
    <section class="info-section">
        <div class="container">
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Visit Us</h3>
                    <address>
                        <?= nl2br(htmlspecialchars($contact_address)) ?>
                    </address>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-phone-alt"></i>
                    </div>
                    <h3>Call Us</h3>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="contact-link">
                        <?= htmlspecialchars($contact_phone) ?>
                    </a>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Email Us</h3>
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="contact-link">
                        <?= htmlspecialchars($contact_email) ?>
                    </a>
                </div>
                
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Business Hours</h3>
                    <div class="hours"><?= $business_hours ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3964.2114326496303!2d<?= $map_lng ?>!3d<?= $map_lat ?>!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x103b8b2ae68280c1%3A0xdc9e87a367c3d9cb!2sLagos%2C%20Nigeria!5e0!3m2!1sen!2s!4v1620000000000!5m2!1sen!2s" 
                    width="100%" 
                    height="450" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section class="form-section">
        <div class="container">
            <div class="form-wrapper">
                <h2 class="form-title">Send Us a Message</h2>
                
                <?php if ($form_success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($form_success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($form_error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $form_error ?>
                    </div>
                <?php endif; ?>

                <?php if (!$form_submitted): ?>
                    <form method="post" class="contact-form" id="contactForm">
                        <input type="hidden" name="submit_contact" value="1">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Your Name *</label>
                                <input type="text" id="name" name="name" 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                       placeholder="John Doe" required>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                       placeholder="john@example.com" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                       placeholder="+234 123 456 7890">
                            </div>

                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" 
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                       placeholder="How can we help?" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" 
                                      placeholder="Tell us more about your inquiry..." 
                                      required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group checkbox">
                            <label>
                                <input type="checkbox" required>
                                I agree to the <a href="<?= BASE_URL ?>pages/privacy-policy.php">Privacy Policy</a> and consent to being contacted
                            </label>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBtn">
                            <span class="btn-text">Send Message</span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Sending...
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Social Connect Section -->
    <section class="social-section">
        <div class="container">
            <h2 class="section-title">Connect With Us</h2>
            <div class="social-grid">
                <?php if (!empty($social_facebook)): ?>
                    <a href="<?= htmlspecialchars($social_facebook) ?>" target="_blank" class="social-card facebook">
                        <i class="fab fa-facebook-f"></i>
                        <span>Facebook</span>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($social_twitter)): ?>
                    <a href="<?= htmlspecialchars($social_twitter) ?>" target="_blank" class="social-card twitter">
                        <i class="fab fa-twitter"></i>
                        <span>Twitter</span>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($social_instagram)): ?>
                    <a href="<?= htmlspecialchars($social_instagram) ?>" target="_blank" class="social-card instagram">
                        <i class="fab fa-instagram"></i>
                        <span>Instagram</span>
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($social_whatsapp)): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $social_whatsapp) ?>" target="_blank" class="social-card whatsapp">
                        <i class="fab fa-whatsapp"></i>
                        <span>WhatsApp</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Quick Links -->
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title">Quick Answers</h2>
            <div class="faq-preview">
                <div class="faq-item">
                    <h3>How long does shipping take?</h3>
                    <p>Standard shipping takes 3-5 business days within Nigeria.</p>
                    <a href="<?= BASE_URL ?>pages/faq.php" class="read-more">Read More FAQs →</a>
                </div>
                <div class="faq-item">
                    <h3>What is your return policy?</h3>
                    <p>We offer 14-day returns on most items in original condition.</p>
                    <a href="<?= BASE_URL ?>pages/returns.php" class="read-more">View Return Policy →</a>
                </div>
                <div class="faq-item">
                    <h3>Do you ship internationally?</h3>
                    <p>Yes, we ship to select countries worldwide.</p>
                    <a href="<?= BASE_URL ?>pages/shipping.php" class="read-more">Shipping Information →</a>
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

/* Info Section */
.info-section {
    padding: 4rem 0;
    background: white;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.info-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: all 0.3s;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.info-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 1.5rem;
}

.info-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.info-card address,
.info-card .hours {
    color: var(--text-light);
    font-style: normal;
    line-height: 1.6;
}

.contact-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.contact-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Map Section */
.map-section {
    padding: 0;
    background: var(--bg);
}

.map-container {
    width: 100%;
    height: 450px;
    overflow: hidden;
}

.map-container iframe {
    width: 100%;
    height: 100%;
}

/* Form Section */
.form-section {
    padding: 4rem 0;
    background: var(--bg);
}

.form-wrapper {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.form-title {
    font-size: 2rem;
    margin-bottom: 2rem;
    text-align: center;
    color: var(--text);
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Contact Form */
.contact-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--text);
}

.form-group input,
.form-group textarea,
.form-group select {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 150px;
}

.form-group.checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: normal;
}

.form-group.checkbox input {
    width: auto;
}

.form-group.checkbox a {
    color: var(--primary);
    text-decoration: none;
}

.form-group.checkbox a:hover {
    text-decoration: underline;
}

.btn-submit {
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-loader {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

/* Social Section */
.social-section {
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

.social-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    max-width: 800px;
    margin: 0 auto;
}

.social-card {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    color: white;
    transition: all 0.3s;
}

.social-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.social-card.facebook {
    background: #1877f2;
}

.social-card.twitter {
    background: #1da1f2;
}

.social-card.instagram {
    background: linear-gradient(45deg, #f09433, #d62976, #962fbf);
}

.social-card.whatsapp {
    background: #25d366;
}

.social-card i {
    font-size: 2rem;
}

.social-card span {
    font-size: 1.1rem;
    font-weight: 600;
}

/* FAQ Section */
.faq-section {
    padding: 4rem 0;
    background: var(--bg);
}

.faq-preview {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
}

.faq-item {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.faq-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.faq-item h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.faq-item p {
    color: var(--text-light);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.read-more {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
}

.read-more:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 1200px) {
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .faq-preview {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-wrapper {
        padding: 2rem;
    }
    
    .form-title {
        font-size: 1.5rem;
    }
    
    .social-grid {
        grid-template-columns: 1fr;
    }
    
    .faq-preview {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .info-card {
        padding: 1.5rem;
    }
    
    .form-wrapper {
        padding: 1.5rem;
    }
    
    .btn-submit {
        padding: 0.875rem;
    }
}
</style>

<script>
document.getElementById('contactForm')?.addEventListener('submit', function(e) {
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    
    // Basic validation
    if (!name || !email || !subject || !message) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return false;
    }
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    submitBtn.disabled = true;
});

// Phone number formatting
document.getElementById('phone')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0) {
        if (value.length <= 3) {
            value = value;
        } else if (value.length <= 6) {
            value = value.slice(0, 3) + ' ' + value.slice(3);
        } else {
            value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 10);
        }
        e.target.value = value;
    }
});

// Character counter for message
document.getElementById('message')?.addEventListener('input', function() {
    const remaining = 1000 - this.value.length;
    const counter = document.getElementById('charCounter');
    if (!counter) {
        const counterEl = document.createElement('div');
        counterEl.id = 'charCounter';
        counterEl.style.cssText = 'text-align: right; font-size: 0.85rem; color: var(--text-light); margin-top: 0.25rem;';
        this.parentNode.appendChild(counterEl);
    }
    const counterEl = document.getElementById('charCounter');
    if (counterEl) {
        counterEl.textContent = remaining + ' characters remaining';
        if (remaining < 100) {
            counterEl.style.color = '#ef4444';
        } else {
            counterEl.style.color = 'var(--text-light)';
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>