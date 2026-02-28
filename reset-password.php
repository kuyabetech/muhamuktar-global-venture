<?php
// pages/reset-password.php - Password Reset

$page_title = "Reset Password";
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
$token = $_GET['token'] ?? '';

// Validate token
if (empty($token)) {
    header("Location: " . BASE_URL . "pages/forgot-password.php");
    exit;
}

// Check if token is valid
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.full_name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $error = "Invalid or expired reset token. Please request a new one.";
    }
} catch (Exception $e) {
    $error = "An error occurred. Please try again.";
    error_log("Reset password error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number";
    } elseif (!preg_match('/[!@#$%^&*]/', $password)) {
        $error = "Password must contain at least one special character (!@#$%^&*)";
    } else {
        try {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$reset['id']]);
            
            $success = "Your password has been reset successfully! You can now login with your new password.";
            
            // Log the password change
            error_log("Password reset successful for user: " . $reset['email']);
            
        } catch (Exception $e) {
            $error = "An error occurred. Please try again.";
            error_log("Password update error: " . $e->getMessage());
        }
    }
}
?>

<main class="main-content">
    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1 class="page-title">Reset Password</h1>
            <div class="breadcrumb">
                <a href="<?= BASE_URL ?>">Home</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/login.php">Login</a>
                <span class="separator">/</span>
                <a href="<?= BASE_URL ?>pages/forgot-password.php">Forgot Password</a>
                <span class="separator">/</span>
                <span class="current">Reset Password</span>
            </div>
        </div>
    </section>

    <!-- Reset Password Form -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <i class="fas fa-key auth-icon"></i>
                        <h2>Create New Password</h2>
                        <?php if ($reset && empty($error) && empty($success)): ?>
                            <p>Hello <strong><?= htmlspecialchars($reset['full_name']) ?></strong>, please enter your new password below.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        
                        <?php if (strpos($error, 'Invalid or expired') !== false): ?>
                            <div class="auth-footer">
                                <p><a href="<?= BASE_URL ?>pages/forgot-password.php">Request New Reset Link</a></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                        
                        <div class="auth-footer">
                            <p><a href="<?= BASE_URL ?>pages/login.php" class="btn-submit" style="display: inline-block; text-decoration: none; margin-top: 1rem;">Go to Login</a></p>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($error) && empty($success) && $reset): ?>
                        <form method="post" class="auth-form" id="resetForm">
                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i>
                                    New Password
                                </label>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Enter new password"
                                       required 
                                       minlength="8">
                                <div class="password-strength">
                                    <div class="strength-bar" id="strength1"></div>
                                    <div class="strength-bar" id="strength2"></div>
                                    <div class="strength-bar" id="strength3"></div>
                                    <div class="strength-bar" id="strength4"></div>
                                </div>
                                <div id="strengthText" class="strength-text">Enter a password</div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">
                                    <i class="fas fa-lock"></i>
                                    Confirm New Password
                                </label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Confirm new password"
                                       required>
                                <div id="matchMessage" class="match-message"></div>
                            </div>

                            <div class="password-requirements">
                                <h4>Password Requirements:</h4>
                                <ul>
                                    <li id="req-length">✓ At least 8 characters</li>
                                    <li id="req-uppercase">✓ At least one uppercase letter</li>
                                    <li id="req-lowercase">✓ At least one lowercase letter</li>
                                    <li id="req-number">✓ At least one number</li>
                                    <li id="req-special">✓ At least one special character (!@#$%^&*)</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                                <span class="btn-text">Reset Password</span>
                                <span class="btn-loader" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> Resetting...
                                </span>
                            </button>
                        </form>

                        <div class="auth-footer">
                            <p>Remember your password? <a href="<?= BASE_URL ?>pages/login.php">Sign In</a></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="auth-info">
                    <div class="info-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Strong Password</h3>
                        <p>Use a mix of uppercase, lowercase, numbers, and special characters for better security.</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-clock"></i>
                        <h3>Limited Time</h3>
                        <p>This reset link expires in 1 hour for your security.</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-laptop"></i>
                        <h3>Secure Connection</h3>
                        <p>All password resets are encrypted and processed securely.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Tips -->
    <section class="tips-section">
        <div class="container">
            <h2 class="section-title">Password Creation Tips</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Make It Unique</h3>
                    <p>Don't reuse passwords from other websites. Each account should have its own password.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-random"></i>
                    <h3>Use Passphrases</h3>
                    <p>Consider using a random combination of words, e.g., "Purple-Dog-Coffee-42!"</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-history"></i>
                    <h3>Avoid Common Words</h3>
                    <p>Stay away from dictionary words, names, or obvious combinations like "password123".</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Use a Manager</h3>
                    <p>Consider using a password manager to generate and store strong passwords.</p>
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

/* Password Strength */
.password-strength {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.strength-bar {
    height: 4px;
    flex: 1;
    background: #e5e7eb;
    border-radius: 2px;
    transition: background 0.3s;
}

.strength-bar.weak {
    background: #ef4444;
}

.strength-bar.medium {
    background: #f59e0b;
}

.strength-bar.strong {
    background: #10b981;
}

.strength-bar.very-strong {
    background: #059669;
}

.strength-text {
    font-size: 0.85rem;
    margin-top: 0.25rem;
    color: var(--text-light);
}

.match-message {
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Password Requirements */
.password-requirements {
    background: var(--bg);
    padding: 1.25rem;
    border-radius: 12px;
    margin: 1.5rem 0;
}

.password-requirements h4 {
    font-size: 0.95rem;
    margin-bottom: 0.75rem;
    color: var(--text);
}

.password-requirements ul {
    list-style: none;
}

.password-requirements li {
    font-size: 0.9rem;
    color: var(--text-light);
    margin-bottom: 0.4rem;
    padding-left: 1.5rem;
    position: relative;
}

.password-requirements li.met {
    color: #10b981;
}

.password-requirements li:before {
    content: '○';
    position: absolute;
    left: 0;
    color: var(--text-light);
}

.password-requirements li.met:before {
    content: '✓';
    color: #10b981;
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

.btn-submit:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-submit:active:not(:disabled) {
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
document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    
    if (password && confirm && submitBtn) {
        // Password strength checker
        function checkPasswordStrength() {
            const val = password.value;
            const strengthBars = document.querySelectorAll('.strength-bar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (val.length >= 8) strength++;
            if (val.match(/[a-z]/)) strength++;
            if (val.match(/[A-Z]/)) strength++;
            if (val.match(/[0-9]/)) strength++;
            if (val.match(/[!@#$%^&*]/)) strength++;
            
            // Reset bars
            strengthBars.forEach(bar => {
                bar.className = 'strength-bar';
            });
            
            // Set bars based on strength
            if (strength <= 2) {
                for (let i = 0; i < strength; i++) {
                    strengthBars[i].classList.add('weak');
                }
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#ef4444';
            } else if (strength <= 3) {
                for (let i = 0; i < strength; i++) {
                    strengthBars[i].classList.add('medium');
                }
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#f59e0b';
            } else if (strength <= 4) {
                for (let i = 0; i < strength; i++) {
                    strengthBars[i].classList.add('strong');
                }
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#10b981';
            } else {
                for (let i = 0; i < strength; i++) {
                    strengthBars[i].classList.add('very-strong');
                }
                strengthText.textContent = 'Very strong password';
                strengthText.style.color = '#059669';
            }
            
            // Update requirement checkmarks
            document.getElementById('req-length').className = val.length >= 8 ? 'met' : '';
            document.getElementById('req-uppercase').className = /[A-Z]/.test(val) ? 'met' : '';
            document.getElementById('req-lowercase').className = /[a-z]/.test(val) ? 'met' : '';
            document.getElementById('req-number').className = /[0-9]/.test(val) ? 'met' : '';
            document.getElementById('req-special').className = /[!@#$%^&*]/.test(val) ? 'met' : '';
            
            validateForm();
        }
        
        // Check password match
        function checkPasswordMatch() {
            const matchMsg = document.getElementById('matchMessage');
            
            if (confirm.value) {
                if (password.value === confirm.value) {
                    matchMsg.innerHTML = '✓ Passwords match';
                    matchMsg.style.color = '#10b981';
                } else {
                    matchMsg.innerHTML = '✗ Passwords do not match';
                    matchMsg.style.color = '#ef4444';
                }
            } else {
                matchMsg.innerHTML = '';
            }
            
            validateForm();
        }
        
        // Validate entire form
        function validateForm() {
            const val = password.value;
            const requirements = [
                val.length >= 8,
                /[a-z]/.test(val),
                /[A-Z]/.test(val),
                /[0-9]/.test(val),
                /[!@#$%^&*]/.test(val),
                password.value === confirm.value && confirm.value !== ''
            ];
            
            submitBtn.disabled = !requirements.every(Boolean);
        }
        
        password.addEventListener('input', function() {
            checkPasswordStrength();
            checkPasswordMatch();
        });
        
        confirm.addEventListener('input', checkPasswordMatch);
    }
});

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');
    
    // Show loading state
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-flex';
    submitBtn.disabled = true;
});
</script>

<?php require_once '../includes/footer.php'; ?>