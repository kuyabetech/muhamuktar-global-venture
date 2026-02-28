<?php
// pages/support.php - Support Center Page

$page_title = "Support Center";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get support information from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'support_hours'");
    $stmt->execute();
    $support_hours = $stmt->fetchColumn() ?: 'Monday - Friday: 9am - 8pm<br>Saturday: 10am - 6pm<br>Sunday: 12pm - 4pm';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'support_chat_enabled'");
    $stmt->execute();
    $support_chat_enabled = $stmt->fetchColumn() ?: '1';

} catch (Exception $e) {
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $support_hours = 'Monday - Friday: 9am - 8pm<br>Saturday: 10am - 6pm<br>Sunday: 12pm - 4pm';
    $support_chat_enabled = '1';
}

// Handle support ticket submission
$ticket_submitted = false;
$ticket_error = '';
$ticket_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $order_number = trim($_POST['order_number'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';

    $errors = [];

    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($category)) {
        $errors[] = "Please select a category";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($message)) {
        $errors[] = "Message is required";
    }

    if (empty($errors)) {
        // In production, this would save to database and send email
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS support_tickets (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    ticket_number VARCHAR(50) UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    order_number VARCHAR(100),
                    category VARCHAR(100),
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
                    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            // Generate ticket number
            $ticket_number = 'TKT-' . strtoupper(uniqid());
            
            $stmt = $pdo->prepare("
                INSERT INTO support_tickets (ticket_number, name, email, order_number, category, subject, message, priority, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ticket_number,
                $name,
                $email,
                $order_number ?: null,
                $category,
                $subject,
                $message,
                $priority,
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            
            $ticket_success = "Your support ticket (#$ticket_number) has been created successfully. We'll respond within 24 hours.";
            
        } catch (Exception $e) {
            $ticket_success = "Your support request has been submitted successfully. We'll respond within 24 hours.";
        }
        
        $ticket_submitted = true;
    } else {
        $ticket_error = implode("<br>", $errors);
    }
}

// Get FAQ categories for quick links
$faq_categories = [
    ['name' => 'Orders & Shipping', 'icon' => 'fa-truck', 'link' => 'orders'],
    ['name' => 'Returns & Refunds', 'icon' => 'fa-undo-alt', 'link' => 'returns'],
    ['name' => 'Payments', 'icon' => 'fa-credit-card', 'link' => 'payments'],
    ['name' => 'Account Issues', 'icon' => 'fa-user', 'link' => 'account'],
    ['name' => 'Technical Support', 'icon' => 'fa-laptop', 'link' => 'technical'],
    ['name' => 'Product Questions', 'icon' => 'fa-question-circle', 'link' => 'products']
];
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Support Center</h1>
            <p class="header-description">We're here to help you 24/7</p>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Support Center</span>
            </div>
        </div>
    </section>

    <!-- Quick Help Section -->
    <section class="quick-help">
        <div class="container">
            <div class="help-grid">
                <div class="help-card">
                    <i class="fas fa-question-circle"></i>
                    <h3>FAQ</h3>
                    <p>Find answers to common questions</p>
                    <a href="<?= BASE_URL ?>pages/faq.php" class="help-link">Browse FAQ →</a>
                </div>
                <div class="help-card">
                    <i class="fas fa-ticket-alt"></i>
                    <h3>Submit Ticket</h3>
                    <p>Create a support ticket</p>
                    <a href="#ticket-form" class="help-link">Create Ticket →</a>
                </div>
                <div class="help-card">
                    <i class="fas fa-comment"></i>
                    <h3>Live Chat</h3>
                    <p>Chat with our support team</p>
                    <button class="help-link chat-btn" onclick="startLiveChat()">Start Chat →</button>
                </div>
                <div class="help-card">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Call Us</h3>
                    <p>Speak with a representative</p>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="help-link"><?= htmlspecialchars($contact_phone) ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Options -->
    <section class="support-options">
        <div class="container">
            <h2 class="section-title">How Can We Help You Today?</h2>
            <div class="options-grid">
                <div class="option-category">
                    <h3><i class="fas fa-shopping-cart"></i> Orders</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>pages/track-order.php">Track My Order</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#shipping">Shipping Information</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#delivery">Delivery Issues</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#cancellation">Cancel Order</a></li>
                    </ul>
                </div>
                <div class="option-category">
                    <h3><i class="fas fa-undo-alt"></i> Returns</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>pages/returns.php">Return Policy</a></li>
                        <li><a href="<?= BASE_URL ?>pages/returns.php#start">Start a Return</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#refund">Refund Status</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#exchange">Exchange Item</a></li>
                    </ul>
                </div>
                <div class="option-category">
                    <h3><i class="fas fa-credit-card"></i> Payments</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#payment">Payment Methods</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#billing">Billing Issues</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#invoice">Get Invoice</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#promo">Promo Codes</a></li>
                    </ul>
                </div>
                <div class="option-category">
                    <h3><i class="fas fa-user"></i> Account</h3>
                    <ul>
                        <li><a href="<?= BASE_URL ?>pages/login.php">Login Issues</a></li>
                        <li><a href="<?= BASE_URL ?>pages/forgot-password.php">Reset Password</a></li>
                        <li><a href="<?= BASE_URL ?>pages/profile.php">Update Profile</a></li>
                        <li><a href="<?= BASE_URL ?>pages/faq.php#privacy">Privacy Settings</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="contact-info">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h3>Email Support</h3>
                    <p>For non-urgent inquiries</p>
                    <a href="mailto:<?= htmlspecialchars($contact_email) ?>" class="contact-detail">
                        <?= htmlspecialchars($contact_email) ?>
                    </a>
                    <small>Response within 24 hours</small>
                </div>
                <div class="contact-card">
                    <i class="fas fa-phone-alt"></i>
                    <h3>Phone Support</h3>
                    <p>Speak with a representative</p>
                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" class="contact-detail">
                        <?= htmlspecialchars($contact_phone) ?>
                    </a>
                    <div class="hours"><?= $support_hours ?></div>
                </div>
                <div class="contact-card">
                    <i class="fas fa-comment"></i>
                    <h3>Live Chat</h3>
                    <p>Instant messaging support</p>
                    <?php if ($support_chat_enabled == '1'): ?>
                        <button class="chat-button" onclick="startLiveChat()">
                            <i class="fas fa-comment-dots"></i> Start Chat
                        </button>
                        <small>Average response: 2 minutes</small>
                    <?php else: ?>
                        <p class="unavailable">Chat currently offline</p>
                        <small>Try during business hours</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Support Ticket Form -->
    <section class="ticket-section" id="ticket-form">
        <div class="container">
            <h2 class="section-title">Create a Support Ticket</h2>
            
            <?php if ($ticket_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($ticket_success) ?>
                </div>
            <?php endif; ?>

            <?php if ($ticket_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $ticket_error ?>
                </div>
            <?php endif; ?>

            <?php if (!$ticket_submitted): ?>
                <div class="ticket-form-wrapper">
                    <form method="post" class="ticket-form" id="ticketForm">
                        <input type="hidden" name="submit_ticket" value="1">
                        
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
                                <label for="order_number">Order Number (Optional)</label>
                                <input type="text" id="order_number" name="order_number" 
                                       value="<?= htmlspecialchars($_POST['order_number'] ?? '') ?>"
                                       placeholder="e.g., ORD-12345">
                            </div>

                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required>
                                    <option value="">Select a category</option>
                                    <option value="order">Order Issue</option>
                                    <option value="payment">Payment Problem</option>
                                    <option value="shipping">Shipping & Delivery</option>
                                    <option value="returns">Returns & Refunds</option>
                                    <option value="account">Account Issue</option>
                                    <option value="technical">Technical Problem</option>
                                    <option value="product">Product Question</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <input type="text" id="subject" name="subject" 
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                       placeholder="Brief description of your issue" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" 
                                      placeholder="Please provide details about your issue..." 
                                      required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="message-hint">
                                <i class="fas fa-info-circle"></i>
                                Include any relevant information like order numbers, product names, or screenshots.
                            </div>
                        </div>

                        <div class="form-group checkbox">
                            <label>
                                <input type="checkbox" required>
                                I agree to the <a href="<?= BASE_URL ?>pages/privacy-policy.php">Privacy Policy</a> and terms of support
                            </label>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBtn">
                            <span class="btn-text">Submit Ticket</span>
                            <span class="btn-loader" style="display: none;">
                                <i class="fas fa-spinner fa-spin"></i> Submitting...
                            </span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Response Times -->
    <section class="response-times">
        <div class="container">
            <h2 class="section-title">Expected Response Times</h2>
            <div class="times-grid">
                <div class="time-card">
                    <i class="fas fa-bolt"></i>
                    <h3>Urgent Issues</h3>
                    <p>Phone: Immediate</p>
                    <p>Chat: &lt; 5 minutes</p>
                </div>
                <div class="time-card">
                    <i class="fas fa-clock"></i>
                    <h3>Normal Priority</h3>
                    <p>Email: 24 hours</p>
                    <p>Ticket: 12-24 hours</p>
                </div>
                <div class="time-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Business Hours</h3>
                    <div class="hours"><?= $support_hours ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Knowledge Base -->
    <section class="knowledge-base">
        <div class="container">
            <h2 class="section-title">Knowledge Base</h2>
            <div class="knowledge-grid">
                <a href="<?= BASE_URL ?>pages/shipping.php" class="knowledge-card">
                    <i class="fas fa-truck"></i>
                    <h3>Shipping Guide</h3>
                    <p>Learn about shipping options and tracking</p>
                    <span>Read More →</span>
                </a>
                <a href="<?= BASE_URL ?>pages/returns.php" class="knowledge-card">
                    <i class="fas fa-undo-alt"></i>
                    <h3>Returns Process</h3>
                    <p>How to return items and get refunds</p>
                    <span>Read More →</span>
                </a>
                <a href="<?= BASE_URL ?>pages/size-guide.php" class="knowledge-card">
                    <i class="fas fa-ruler"></i>
                    <h3>Size Guide</h3>
                    <p>Find your perfect fit</p>
                    <span>Read More →</span>
                </a>
                <a href="<?= BASE_URL ?>pages/faq.php" class="knowledge-card">
                    <i class="fas fa-question-circle"></i>
                    <h3>FAQ</h3>
                    <p>Frequently asked questions</p>
                    <span>Read More →</span>
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

/* Quick Help */
.quick-help {
    padding: 3rem 0;
    background: white;
}

.help-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.help-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
    transition: all 0.3s;
}

.help-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.help-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.help-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.help-card p {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.help-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: color 0.3s;
}

.help-link:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

.chat-btn {
    background: none;
    border: none;
    font-size: 1rem;
    cursor: pointer;
}

/* Support Options */
.support-options {
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

.options-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.option-category {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.option-category h3 {
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.option-category h3 i {
    color: var(--primary);
}

.option-category ul {
    list-style: none;
}

.option-category li {
    margin-bottom: 0.75rem;
}

.option-category a {
    color: var(--text-light);
    text-decoration: none;
    transition: color 0.3s;
}

.option-category a:hover {
    color: var(--primary);
}

/* Contact Information */
.contact-info {
    padding: 4rem 0;
    background: white;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.contact-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
}

.contact-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.contact-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.contact-card p {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.contact-detail {
    display: block;
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.contact-detail:hover {
    text-decoration: underline;
}

.contact-card .hours {
    color: var(--text-light);
    font-size: 0.9rem;
    margin-top: 0.5rem;
}

.chat-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.chat-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.unavailable {
    color: var(--danger);
    font-weight: 600;
}

/* Ticket Form */
.ticket-section {
    padding: 4rem 0;
    background: var(--bg);
}

.ticket-form-wrapper {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

/* Alerts */
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

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--text);
}

.form-group input,
.form-group select,
.form-group textarea {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.message-hint {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

.message-hint i {
    color: var(--primary);
    margin-right: 0.25rem;
}

.form-group.checkbox label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: normal;
}

.form-group.checkbox a {
    color: var(--primary);
    text-decoration: none;
}

.btn-submit {
    width: 100%;
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

/* Response Times */
.response-times {
    padding: 4rem 0;
    background: white;
}

.times-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    max-width: 900px;
    margin: 0 auto;
}

.time-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
}

.time-card i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.time-card h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.time-card p {
    color: var(--text-light);
    margin-bottom: 0.25rem;
}

.time-card .hours {
    color: var(--text-light);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Knowledge Base */
.knowledge-base {
    padding: 4rem 0;
    background: var(--bg);
}

.knowledge-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.knowledge-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.3s;
    text-align: center;
}

.knowledge-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.knowledge-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.knowledge-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.knowledge-card p {
    color: var(--text-light);
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.knowledge-card span {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .help-grid,
    .options-grid,
    .knowledge-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .contact-grid,
    .times-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .help-grid,
    .options-grid,
    .contact-grid,
    .times-grid,
    .knowledge-grid {
        grid-template-columns: 1fr;
    }
    
    .ticket-form-wrapper {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .help-card,
    .option-category,
    .contact-card,
    .time-card,
    .knowledge-card {
        padding: 1.5rem;
    }
}
</style>

<script>
document.getElementById('ticketForm')?.addEventListener('submit', function(e) {
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const category = document.getElementById('category').value;
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    
    if (!name || !email || !category || !subject || !message) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return false;
    }
    
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    submitBtn.disabled = true;
});

function startLiveChat() {
    <?php if ($support_chat_enabled == '1'): ?>
        // In production, this would open a chat widget
        alert('Live chat would open here. This is a demo version.');
    <?php else: ?>
        alert('Live chat is currently offline. Please try again during business hours or email us at <?= $contact_email ?>');
    <?php endif; ?>
}

// Character counter for message
document.getElementById('message')?.addEventListener('input', function() {
    const remaining = 2000 - this.value.length;
    const hint = document.querySelector('.message-hint');
    if (remaining < 100) {
        hint.innerHTML = `<i class="fas fa-info-circle"></i> ${remaining} characters remaining`;
        hint.style.color = remaining < 20 ? '#ef4444' : '#f59e0b';
    } else {
        hint.innerHTML = '<i class="fas fa-info-circle"></i> Include any relevant information like order numbers, product names, or screenshots.';
        hint.style.color = 'var(--text-light)';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>