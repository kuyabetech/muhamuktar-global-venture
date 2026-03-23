<?php
// pages/forgot-password.php - Password Recovery with PHPMailer

$page_title = "Forgot Password";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Load PHPMailer (if using Composer)
require_once 'vendor/autoload.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: " . BASE_URL . "pages/profile.php");
    exit;
}

$error = '';
$success = '';
$email = '';

// Get email settings from database
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_host'");
    $stmt->execute();
    $smtp_host = $stmt->fetchColumn() ?: 'smtp.gmail.com';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_port'");
    $stmt->execute();
    $smtp_port = $stmt->fetchColumn() ?: 587;
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_user'");
    $stmt->execute();
    $smtp_user = $stmt->fetchColumn() ?: '';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_pass'");
    $stmt->execute();
    $smtp_pass = $stmt->fetchColumn() ?: '';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_encryption'");
    $stmt->execute();
    $smtp_encryption = $stmt->fetchColumn() ?: 'tls';
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'site_name'");
    $stmt->execute();
    $site_name = $stmt->fetchColumn() ?: SITE_NAME;
    
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $from_email = $stmt->fetchColumn() ?: 'noreply@muhamuktar.com';
    
} catch (Exception $e) {
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    $smtp_user = '';
    $smtp_pass = '';
    $smtp_encryption = 'tls';
    $site_name = SITE_NAME ?? 'Muhamuktar Global Venture';
    $from_email = 'noreply@muhamuktar.com';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()
                ");
                $stmt->execute([$user['id'], $token, $expires, $token, $expires]);
                
                // Send reset email using PHPMailer
                $reset_link = BASE_URL . "pages/reset-password.php?token=" . $token;
                
                // Create PHPMailer instance
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = $smtp_host;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp_user;
                    $mail->Password   = $smtp_pass;
                    $mail->SMTPSecure = $smtp_encryption;
                    $mail->Port       = $smtp_port;
                    
                    // Recipients
                    $mail->setFrom($from_email, $site_name);
                    $mail->addAddress($user['email'], $user['full_name']);
                    $mail->addReplyTo($from_email, $site_name);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = "Password Reset Request - " . $site_name;
                    
                    // HTML email body
                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Password Reset</title>
                        <style>
                            body {
                                font-family: Arial, sans-serif;
                                line-height: 1.6;
                                color: #333;
                                margin: 0;
                                padding: 0;
                            }
                            .container {
                                max-width: 600px;
                                margin: 0 auto;
                                padding: 20px;
                            }
                            .header {
                                background: linear-gradient(135deg, #1e40af, #3b82f6);
                                color: white;
                                padding: 30px;
                                text-align: center;
                                border-radius: 10px 10px 0 0;
                            }
                            .content {
                                background: #f8fafc;
                                padding: 30px;
                                border-radius: 0 0 10px 10px;
                            }
                            .button {
                                display: inline-block;
                                padding: 12px 24px;
                                background: linear-gradient(135deg, #1e40af, #3b82f6);
                                color: white;
                                text-decoration: none;
                                border-radius: 5px;
                                margin: 20px 0;
                            }
                            .footer {
                                margin-top: 30px;
                                padding-top: 20px;
                                border-top: 1px solid #e2e8f0;
                                font-size: 12px;
                                color: #64748b;
                                text-align: center;
                            }
                            .warning {
                                background: #fef3c7;
                                border-left: 4px solid #f59e0b;
                                padding: 15px;
                                margin: 20px 0;
                                border-radius: 5px;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1 style='margin:0;'>Password Reset Request</h1>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                                
                                <p>We received a request to reset the password for your account at <strong>" . htmlspecialchars($site_name) . "</strong>. If you didn't make this request, you can safely ignore this email.</p>
                                
                                <div style='text-align: center;'>
                                    <a href='" . $reset_link . "' class='button'>Reset Your Password</a>
                                </div>
                                
                                <p>Or copy and paste this link into your browser:</p>
                                <p style='background: #f1f5f9; padding: 10px; border-radius: 5px; word-break: break-all;'>
                                    <small>" . $reset_link . "</small>
                                </p>
                                
                                <div class='warning'>
                                    <strong>⚠️ Important:</strong> This link will expire in 1 hour for security reasons.
                                </div>
                                
                                <p>If you continue to have issues, please contact our support team.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " " . htmlspecialchars($site_name) . ". All rights reserved.</p>
                                <p>This is an automated message, please do not reply to this email.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Plain text version for non-HTML email clients
                    $mail->AltBody = "Hello " . $user['full_name'] . ",\n\n" .
                                    "We received a request to reset the password for your account at " . $site_name . ".\n\n" .
                                    "To reset your password, click the link below:\n" .
                                    $reset_link . "\n\n" .
                                    "This link will expire in 1 hour for security reasons.\n\n" .
                                    "If you didn't request this, please ignore this email.\n\n" .
                                    "Thank you,\n" . $site_name . " Team";
                    
                    $mail->send();
                    $success = "Password reset instructions have been sent to your email address. Please check your inbox (and spam folder).";
                    
                    // Log successful email
                    error_log("Password reset email sent to: $email");
                    
                } catch (PHPMailer\PHPMailer\Exception $e) {
                    // PHPMailer error
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                    
                    // Fallback to error message
                    $error = "Unable to send email at this time. Please try again later or contact support.";
                    
                    // For development only - show reset link
                    if (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
                        $reset_link_demo = $reset_link;
                        $success = "Email sending failed, but here's your reset link (development only):<br>" . $reset_link;
                    }
                }
                
            } else {
                // Don't reveal if email exists or not for security
                $success = "If your email is registered, you will receive reset instructions.";
            }
            
        } catch (Exception $e) {
            $error = "An error occurred. Please try again later.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// Create password_resets table if not exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Forgot Password</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/login.php">Login</a>
                <span class="separator">/</span>
                <span class="current">Forgot Password</span>
            </div>
        </div>
    </section>

    <!-- Forgot Password Form -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-lock auth-icon"></i>
                        <h2>Reset Your Password</h2>
                        <p>Enter your email address and we'll send you instructions to reset your password.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= $success ?>
                        </div>
                        
                        <!-- Show reset link in development mode only -->
                        <?php if (isset($reset_link_demo) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])): ?>
                            <div class="demo-notice">
                                <p><strong>Development Mode:</strong> Reset link (for testing only):</p>
                                <a href="<?= $reset_link_demo ?>" class="demo-link">
                                    <?= $reset_link_demo ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="auth-footer">
                            <p><a href="<?= BASE_URL ?>pages/login.php">Return to Login</a></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                        <form method="post" class="auth-form" id="forgotForm">
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($email) ?>" 
                                       placeholder="Enter your email address"
                                       required 
                                       autofocus>
                            </div>

                            <button type="submit" class="btn-submit" id="submitBtn">
                                <span class="btn-text">Send Reset Instructions</span>
                                <span class="btn-loader" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> Sending...
                                </span>
                            </button>
                        </form>

                        <div class="auth-footer">
                            <p>Remember your password? <a href="<?= BASE_URL ?>pages/login.php">Sign In</a></p>
                            <p>Don't have an account? <a href="<?= BASE_URL ?>pages/register.php">Create one</a></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="auth-info">
                    <div class="info-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Secure Process</h3>
                        <p>Your password reset link is encrypted and expires in 1 hour for security.</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-envelope-open-text"></i>
                        <h3>Check Your Email</h3>
                        <p>We'll send instructions to your email. Don't forget to check your spam folder.</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-question-circle"></i>
                        <h3>Need Help?</h3>
                        <p>Contact our support team if you're having trouble resetting your password.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Tips -->
    <section class="tips-section">
        <div class="container">
            <h2 class="section-title">Password Security Tips</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <i class="fas fa-key"></i>
                    <h3>Strong Passwords</h3>
                    <p>Use a mix of letters, numbers, and symbols. Avoid common words or personal information.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-sync-alt"></i>
                    <h3>Regular Updates</h3>
                    <p>Change your password regularly and never reuse passwords across different sites.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-lock"></i>
                    <h3>Two-Factor Auth</h3>
                    <p>Enable two-factor authentication for an extra layer of security.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-eye-slash"></i>
                    <h3>Keep It Private</h3>
                    <p>Never share your password with anyone. We'll never ask for your password.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Add Email Settings Form (for admin use) -->
<?php if (function_exists('is_admin') && is_admin()): ?>
<section class="email-settings-section">
    <div class="container">
        <div class="settings-card">
            <h3>Email Settings (Admin Only)</h3>
            <p>Configure your SMTP settings for email delivery.</p>
            
            <form method="post" action="<?= BASE_URL ?>admin/settings.php" class="settings-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?= htmlspecialchars($smtp_host) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_port">SMTP Port</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?= $smtp_port ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_user">SMTP Username</label>
                        <input type="text" id="smtp_user" name="smtp_user" value="<?= htmlspecialchars($smtp_user) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_pass">SMTP Password</label>
                        <input type="password" id="smtp_pass" name="smtp_pass" value="<?= htmlspecialchars($smtp_pass) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_encryption">Encryption</label>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?= $smtp_encryption === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $smtp_encryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="" <?= $smtp_encryption === '' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="from_email">From Email</label>
                        <input type="email" id="from_email" name="contact_email" value="<?= htmlspecialchars($from_email) ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Save Email Settings</button>
            </form>
            
            <div class="test-email">
                <h4>Test Email Configuration</h4>
                <form method="post" action="<?= BASE_URL ?>admin/test-email.php" class="test-form">
                    <div class="form-group">
                        <label for="test_email">Send test email to:</label>
                        <input type="email" id="test_email" name="test_email" value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>" required>
                    </div>
                    <button type="submit" class="btn-secondary">Send Test Email</button>
                </form>
            </div>
        </div>
    </div>
</section>

<style>
.email-settings-section {
    padding: 2rem 0;
    background: #f8f9fc;
    border-top: 1px solid #e5e7eb;
}

.settings-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    max-width: 800px;
    margin: 0 auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.settings-card h3 {
    margin-bottom: 0.5rem;
    color: var(--text);
}

.settings-card p {
    color: var(--text-light);
    margin-bottom: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.settings-form .form-group {
    margin-bottom: 0;
}

.test-email {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.test-email h4 {
    margin-bottom: 1rem;
    color: var(--text);
}

.test-form {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
}

.test-form .form-group {
    flex: 1;
    margin-bottom: 0;
}

.test-form input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 1rem;
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    background: white;
    color: var(--text);
    border: 2px solid var(--border);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
}

.btn-secondary:hover {
    background: var(--bg);
    border-color: var(--primary);
    color: var(--primary);
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .test-form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
<?php endif; ?>

<style>
/* Existing styles remain the same */
.page-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 3rem 0;
    text-align: center;
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

/* Auth Section */
.auth-section {
    padding: 4rem 0;
    background: var(--bg);
}

.auth-container {
    max-width: 1100px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: start;
}

/* Auth Card */
.auth-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 1.5rem;
}

.auth-header h2 {
    font-size: 1.8rem;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.auth-header p {
    color: var(--text-light);
    font-size: 0.95rem;
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert i {
    font-size: 1.1rem;
}

/* Demo Notice */
.demo-notice {
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.demo-notice p {
    color: #92400e;
    margin-bottom: 0.5rem;
}

.demo-link {
    display: block;
    padding: 0.75rem;
    background: white;
    border: 1px solid #fbbf24;
    border-radius: 6px;
    color: #92400e;
    text-decoration: none;
    font-family: monospace;
    word-break: break-all;
    font-size: 0.9rem;
}

.demo-link:hover {
    background: #fef9e7;
}

/* Form */
.auth-form {
    margin-top: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.form-group label i {
    color: var(--primary);
}

.form-group input {
    width: 100%;
    padding: 1rem 1.25rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
}

.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
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

/* Auth Footer */
.auth-footer {
    text-align: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.auth-footer p {
    color: var(--text-light);
    margin-bottom: 0.5rem;
}

.auth-footer a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.auth-footer a:hover {
    text-decoration: underline;
}

/* Auth Info */
.auth-info {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    text-align: center;
}

.info-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.info-card h3 {
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.info-card p {
    color: var(--text-light);
    line-height: 1.6;
}

/* Tips Section */
.tips-section {
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

.tips-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 2rem;
}

.tip-card {
    text-align: center;
    padding: 2rem 1.5rem;
    background: var(--bg);
    border-radius: 16px;
    transition: all 0.3s;
}

.tip-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.tip-card i {
    font-size: 2.5rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.tip-card h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text);
}

.tip-card p {
    color: var(--text-light);
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 992px) {
    .auth-container {
        grid-template-columns: 1fr;
        max-width: 500px;
    }
    
    .auth-info {
        order: -1;
    }
    
    .tips-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .auth-card {
        padding: 1.5rem;
    }
    
    .auth-header h2 {
        font-size: 1.5rem;
    }
    
    .tips-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .auth-card {
        padding: 1.25rem;
    }
}
</style>

<script>
document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    
    // Simple email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        return;
    }
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    submitBtn.disabled = true;
});
</script>

<?php require_once 'includes/footer.php'; ?>