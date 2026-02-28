<?php
// pages/returns.php - Returns & Refunds Policy Page

$page_title = "Returns & Refunds";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get settings from database
try {
    // Return policy settings
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'return_policy_days'");
    $stmt->execute();
    $return_days = $stmt->fetchColumn() ?: 14;

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'return_policy_text'");
    $stmt->execute();
    $return_policy_text = $stmt->fetchColumn();

    // Contact information
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_address'");
    $stmt->execute();
    $return_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';

} catch (Exception $e) {
    $return_days = 14;
    $return_policy_text = '';
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $return_address = '123 Main Street, Lagos, Nigeria';
}

// Handle return request form
$form_submitted = false;
$form_error = '';
$form_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return_request'])) {
    $order_number = trim($_POST['order_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $product_id = (int)($_POST['product_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $comments = trim($_POST['comments'] ?? '');

    $errors = [];

    if (empty($order_number)) {
        $errors[] = "Order number is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($reason)) {
        $errors[] = "Please select a reason for return";
    }

    if (empty($errors)) {
        // In production, this would save to database and send email
        // For now, just show success message
        $form_success = "Your return request has been submitted successfully. We'll contact you within 24 hours.";
        
        // Log the return request
        error_log("Return request - Order: $order_number, Email: $email, Reason: $reason");
        
        $form_submitted = true;
    } else {
        $form_error = implode("<br>", $errors);
    }
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Returns & Refunds</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <span class="current">Returns & Refunds</span>
            </div>
        </div>
    </section>

    <!-- Quick Summary -->
    <section class="summary-section">
        <div class="container">
            <div class="summary-card">
                <i class="fas fa-undo-alt"></i>
                <div class="summary-content">
                    <h2><?= $return_days ?>-Day Return Policy</h2>
                    <p>We want you to be completely satisfied with your purchase. If you're not happy, you can return most items within <?= $return_days ?> days of delivery for a full refund.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Policy Details -->
    <section class="policy-section">
        <div class="container">
            <h2 class="section-title">Return Policy Details</h2>
            
            <?php if (!empty($return_policy_text)): ?>
                <div class="policy-content">
                    <?= nl2br(htmlspecialchars($return_policy_text)) ?>
                </div>
            <?php endif; ?>

            <div class="policy-grid">
                <!-- Eligible Items -->
                <div class="policy-card">
                    <div class="policy-icon">
                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    </div>
                    <h3>Eligible Items</h3>
                    <ul class="policy-list">
                        <li><i class="fas fa-check"></i> Unused items in original packaging</li>
                        <li><i class="fas fa-check"></i> Items with tags still attached</li>
                        <li><i class="fas fa-check"></i> Electronics within <?= $return_days ?> days</li>
                        <li><i class="fas fa-check"></i> Clothing and accessories</li>
                        <li><i class="fas fa-check"></i> Home and living products</li>
                    </ul>
                </div>

                <!-- Non-Eligible Items -->
                <div class="policy-card">
                    <div class="policy-icon">
                        <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                    </div>
                    <h3>Non-Eligible Items</h3>
                    <ul class="policy-list">
                        <li><i class="fas fa-times"></i> Used or worn items</li>
                        <li><i class="fas fa-times"></i> Items without original packaging</li>
                        <li><i class="fas fa-times"></i> Personalized or custom items</li>
                        <li><i class="fas fa-times"></i> Perishable goods</li>
                        <li><i class="fas fa-times"></i> Gift cards</li>
                    </ul>
                </div>

                <!-- Refund Options -->
                <div class="policy-card">
                    <div class="policy-icon">
                        <i class="fas fa-money-bill-wave" style="color: var(--primary);"></i>
                    </div>
                    <h3>Refund Options</h3>
                    <ul class="policy-list">
                        <li><i class="fas fa-check"></i> Original payment method (3-5 business days)</li>
                        <li><i class="fas fa-check"></i> Store credit (immediate)</li>
                        <li><i class="fas fa-check"></i> Exchange for different size/color</li>
                        <li><i class="fas fa-check"></i> Exchange for different product</li>
                    </ul>
                </div>

                <!-- Timeline -->
                <div class="policy-card">
                    <div class="policy-icon">
                        <i class="fas fa-clock" style="color: #f59e0b;"></i>
                    </div>
                    <h3>Processing Timeline</h3>
                    <ul class="policy-list">
                        <li><i class="fas fa-check"></i> Return approval: 1-2 business days</li>
                        <li><i class="fas fa-check"></i> Refund processing: 3-5 business days</li>
                        <li><i class="fas fa-check"></i> Exchange shipping: 2-3 business days</li>
                        <li><i class="fas fa-check"></i> Store credit: Immediate</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Return Process -->
    <section class="process-section">
        <div class="container">
            <h2 class="section-title">How to Return an Item</h2>
            <div class="process-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Initiate Return</h3>
                    <p>Submit a return request using the form below or contact our support team</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Get Approval</h3>
                    <p>We'll review your request and provide return instructions within 24 hours</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Package Item</h3>
                    <p>Securely pack the item in its original packaging with all tags attached</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Ship Back</h3>
                    <p>Send the package to our returns address using a trackable shipping method</p>
                </div>
                <div class="step">
                    <div class="step-number">5</div>
                    <h3>Receive Refund</h3>
                    <p>Once received and inspected, we'll process your refund within 3-5 days</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Return Request Form -->
    <section class="form-section">
        <div class="container">
            <h2 class="section-title">Request a Return</h2>
            
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
                <div class="form-wrapper">
                    <form method="post" class="return-form">
                        <input type="hidden" name="submit_return_request" value="1">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="order_number">Order Number *</label>
                                <input type="text" id="order_number" name="order_number" 
                                       placeholder="e.g., ORD-12345" required>
                                <small>Found in your order confirmation email</small>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" 
                                       placeholder="your@email.com" required>
                                <small>Email used when placing the order</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="product_id">Product (Optional)</label>
                            <select id="product_id" name="product_id">
                                <option value="0">Entire Order</option>
                                <?php
                                // You could fetch products from the order if needed
                                ?>
                                <option value="1">Product 1</option>
                                <option value="2">Product 2</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Return *</label>
                            <select id="reason" name="reason" required>
                                <option value="">Select a reason</option>
                                <option value="wrong_item">Wrong item received</option>
                                <option value="defective">Defective or damaged</option>
                                <option value="size_issue">Size or fit issue</option>
                                <option value="quality">Quality not as expected</option>
                                <option value="changed_mind">Changed my mind</option>
                                <option value="delayed">Delivery took too long</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comments">Additional Comments</label>
                            <textarea id="comments" name="comments" rows="4" 
                                      placeholder="Please provide any additional details about your return..."></textarea>
                        </div>

                        <div class="form-group checkbox">
                            <label>
                                <input type="checkbox" required>
                                I confirm that the item(s) are unused and in original condition
                            </label>
                        </div>

                        <button type="submit" class="btn-submit">Submit Return Request</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Shipping for Returns -->
    <section class="shipping-section">
        <div class="container">
            <div class="shipping-grid">
                <div class="shipping-card">
                    <i class="fas fa-truck"></i>
                    <h3>Return Shipping</h3>
                    <p>Customers are responsible for return shipping costs unless the return is due to our error (wrong item, defective product).</p>
                </div>
                <div class="shipping-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Returns Address</h3>
                    <address>
                        <?= nl2br(htmlspecialchars($return_address)) ?>
                    </address>
                </div>
                <div class="shipping-card">
                    <i class="fas fa-clock"></i>
                    <h3>Processing Time</h3>
                    <p>Please allow 3-5 business days for your return to be processed once received at our warehouse.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>How long do I have to return an item?</h3>
                    <p>You have <?= $return_days ?> days from the delivery date to initiate a return.</p>
                </div>
                <div class="faq-item">
                    <h3>Will I be refunded the original shipping cost?</h3>
                    <p>Original shipping costs are non-refundable unless the return is due to our error.</p>
                </div>
                <div class="faq-item">
                    <h3>How long does a refund take?</h3>
                    <p>Refunds are processed within 3-5 business days after we receive and inspect your return.</p>
                </div>
                <div class="faq-item">
                    <h3>Can I exchange an item instead of returning it?</h3>
                    <p>Yes, you can request an exchange for a different size, color, or product of equal value.</p>
                </div>
                <div class="faq-item">
                    <h3>What if my item arrived damaged?</h3>
                    <p>Please contact us immediately with photos of the damage. We'll arrange a replacement or refund.</p>
                </div>
                <div class="faq-item">
                    <h3>Do you offer free return shipping?</h3>
                    <p>Free return shipping is only provided for defective items or our errors. Otherwise, return shipping is at customer's expense.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Support -->
    <section class="support-section">
        <div class="container">
            <div class="support-card">
                <h2>Need Help with Your Return?</h2>
                <p>Our customer support team is here to assist you</p>
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

/* Summary Section */
.summary-section {
    padding: 4rem 0;
    background: white;
}

.summary-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    padding: 3rem;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 2rem;
    box-shadow: 0 20px 40px rgba(59, 130, 246, 0.2);
}

.summary-card i {
    font-size: 4rem;
}

.summary-content h2 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.summary-content p {
    font-size: 1.1rem;
    opacity: 0.9;
}

/* Policy Section */
.policy-section {
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

.policy-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 3rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    line-height: 1.8;
}

.policy-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.policy-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.policy-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.policy-icon {
    text-align: center;
    margin-bottom: 1rem;
}

.policy-icon i {
    font-size: 2.5rem;
}

.policy-card h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    text-align: center;
    color: var(--text);
}

.policy-list {
    list-style: none;
}

.policy-list li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    color: var(--text-light);
    font-size: 0.95rem;
}

.policy-list li i {
    width: 20px;
}

.policy-list li i.fa-check {
    color: #10b981;
}

.policy-list li i.fa-times {
    color: #ef4444;
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

/* Form Section */
.form-section {
    padding: 4rem 0;
    background: var(--bg);
}

.form-wrapper {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    padding: 3rem;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.return-form {
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
.form-group select,
.form-group textarea {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-group small {
    color: var(--text-light);
    font-size: 0.85rem;
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
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
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

/* Shipping Section */
.shipping-section {
    padding: 4rem 0;
    background: white;
}

.shipping-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.shipping-card {
    text-align: center;
    padding: 2rem;
    background: var(--bg);
    border-radius: 16px;
}

.shipping-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.shipping-card h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
    color: var(--text);
}

.shipping-card p,
.shipping-card address {
    color: var(--text-light);
    line-height: 1.6;
    font-style: normal;
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

/* Responsive */
@media (max-width: 1200px) {
    .policy-grid {
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
    .summary-card {
        flex-direction: column;
        text-align: center;
    }
    
    .shipping-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .policy-grid {
        grid-template-columns: 1fr;
    }
    
    .process-steps {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .faq-grid {
        grid-template-columns: 1fr;
    }
    
    .form-wrapper {
        padding: 1.5rem;
    }
    
    .support-options {
        flex-direction: column;
    }
    
    .support-option {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .summary-card {
        padding: 2rem;
    }
    
    .summary-content h2 {
        font-size: 1.5rem;
    }
    
    .policy-card,
    .faq-item,
    .shipping-card {
        padding: 1.5rem;
    }
}
</style>

<script>
// Form validation
document.querySelector('.return-form')?.addEventListener('submit', function(e) {
    const orderNumber = document.getElementById('order_number').value;
    const email = document.getElementById('email').value;
    const reason = document.getElementById('reason').value;
    
    if (!orderNumber || !email || !reason) {
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
});

// FAQ accordion (optional)
document.querySelectorAll('.faq-item h3').forEach(header => {
    header.addEventListener('click', function() {
        const content = this.nextElementSibling;
        content.style.display = content.style.display === 'none' ? 'block' : 'none';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>