<?php
// pages/forgot-password.php - Password Recovery

$page_title = "Forgot Password";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: " . BASE_URL . "pages/profile.php");
    exit;
}

$error = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
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
                
                // Send reset email (simulated for demo)
                $reset_link = BASE_URL . "pages/reset-password.php?token=" . $token;
                
                // In production, send actual email
                // mail($email, "Password Reset Request", "Click here to reset: $reset_link");
                
                // For demo, we'll show the link (remove in production)
                $success = "Password reset instructions have been sent to your email.";
                
                // Log the attempt
                error_log("Password reset requested for: $email - Link: $reset_link");
                
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
                            <?= htmlspecialchars($success) ?>
                        </div>
                        
                        <?php if (strpos($success, 'If your email is registered') === false): ?>
                            <!-- Demo mode - show reset link (remove in production) -->
                            <div class="demo-notice">
                                <p><strong>Demo Mode:</strong> Reset link (would be emailed):</p>
                                <a href="<?= $reset_link ?? '#' ?>" class="demo-link">
                                    <?= $reset_link ?? '' ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

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

<style>
/* Page Header */
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

.form-group input.error {
    border-color: var(--danger);
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

.btn-submit:active {
    transform: translateY(0);
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

// Add password reset table if not exists
<?php
// This should be run once, but we'll check and create the table
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
</script>

<?php require_once 'includes/footer.php'; ?>