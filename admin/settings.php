<?php
// admin/settings.php - Site Settings

$page_title = "Settings";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Admin only
require_admin();

// Default settings (fallback)
$defaults = [
    'site_name'              => 'Muhamuktar Global Venture',
    'site_slogan'            => 'Quality Products • Fast Delivery',
    'contact_email'          => 'support@muhamuktar.com',
    'contact_phone'          => '+234 123 456 7890',
    'contact_address'        => '123 Main Street, Lagos, Nigeria',
    'free_shipping_threshold'=> '50000',
    'shipping_fee'           => '1500',
    'currency'               => '₦ NGN',
    'paystack_test_secret'   => '',
    'paystack_live_secret'   => '',
    'paystack_test_public'   => '',
    'paystack_live_public'   => '',
    'paystack_mode'          => 'test', // test or live
    'maintenance_mode'       => '0', // 0 = off, 1 = on
    'enable_registration'    => '1',
    'enable_reviews'         => '1',
    'default_country'        => 'NG',
    'tax_rate'               => '7.5',
    'store_logo'             => '',
    'favicon'                => '',
    'meta_description'       => '',
    'meta_keywords'          => '',
    'social_facebook'        => '',
    'social_twitter'         => '',
    'social_instagram'       => '',
    'social_whatsapp'        => '',
    'google_analytics'       => '',
    'recaptcha_site_key'     => '',
    'recaptcha_secret_key'   => '',
    'enable_recaptcha'       => '0',
];

// Load current settings from DB
$settings = [];
try {
    // Check if settings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(100) PRIMARY KEY,
            value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $stmt = $pdo->query("SELECT `key`, value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    error_log("Settings table error: " . $e->getMessage());
}

// Merge with defaults
foreach ($defaults as $k => $v) {
    if (!isset($settings[$k])) {
        $settings[$k] = $v;
    }
}

// Handle form submit
$message = '';
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST;
    $errors = [];

    foreach ($defaults as $key => $default) {
        $value = $posted[$key] ?? $default;

        // Sanitize based on field type
        if (in_array($key, ['paystack_test_secret', 'paystack_live_secret', 'paystack_test_public', 'paystack_live_public'])) {
            $value = trim($value);
        } elseif (in_array($key, ['maintenance_mode', 'enable_registration', 'enable_reviews', 'enable_recaptcha'])) {
            $value = isset($posted[$key]) ? '1' : '0';
        } elseif ($key === 'tax_rate') {
            $value = is_numeric($value) ? floatval($value) : 0.0;
            if ($value < 0 || $value > 100) {
                $errors[] = "Tax rate must be between 0 and 100";
            }
        } elseif ($key === 'free_shipping_threshold' || $key === 'shipping_fee') {
            $value = is_numeric($value) ? floatval($value) : 0.0;
            if ($value < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $key)) . " cannot be negative";
            }
        } elseif (in_array($key, ['contact_email', 'contact_phone'])) {
            $value = htmlspecialchars(trim($value));
            if ($key === 'contact_email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email address format";
            }
        } else {
            $value = htmlspecialchars(trim($value));
        }

        // Update or insert
        try {
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        } catch (Exception $e) {
            $errors[] = "Failed to save setting '$key': " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        $success_msg = "Settings saved successfully!";
        // Reload settings
        header("Location: settings.php?success=" . urlencode($success_msg));
        exit;
    } else {
        $error_msg = implode("<br>", $errors);
        header("Location: settings.php?error=" . urlencode($error_msg));
        exit;
    }
}
require_once 'header.php'; // Use admin header instead of regular header
?>

<div class="admin-main">
    
    <!-- Page Header -->
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--admin-dark);">
            <i class="fas fa-cog"></i> Site Settings
        </h1>
        <p style="color: var(--admin-gray);">Configure your store settings, payment, shipping, and more</p>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Settings Form with Tabs -->
    <div class="card">
        <!-- Tabs Navigation -->
        <div style="border-bottom: 1px solid var(--admin-border); margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem; overflow-x: auto;">
                <button type="button" class="tab-btn active" data-tab="general">General</button>
                <button type="button" class="tab-btn" data-tab="store">Store</button>
                <button type="button" class="tab-btn" data-tab="shipping">Shipping & Tax</button>
                <button type="button" class="tab-btn" data-tab="payment">Payment</button>
                <button type="button" class="tab-btn" data-tab="contact">Contact</button>
                <button type="button" class="tab-btn" data-tab="seo">SEO & Social</button>
                <button type="button" class="tab-btn" data-tab="security">Security</button>
                <button type="button" class="tab-btn" data-tab="maintenance">Maintenance</button>
            </div>
        </div>

        <form method="post" id="settingsForm">
            <!-- General Tab -->
            <div class="tab-content active" id="generalTab">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-store"></i> General Settings
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Site Name *</label>
                        <input type="text" name="site_name" 
                               value="<?= htmlspecialchars($settings['site_name']) ?>" 
                               required class="form-control" 
                               placeholder="Your store name">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Site Slogan / Tagline</label>
                        <input type="text" name="site_slogan" 
                               value="<?= htmlspecialchars($settings['site_slogan']) ?>" 
                               class="form-control" 
                               placeholder="Quality Products • Fast Delivery">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Currency</label>
                    <select name="currency" class="form-control">
                        <option value="₦ NGN" <?= $settings['currency'] === '₦ NGN' ? 'selected' : '' ?>>₦ NGN (Nigerian Naira)</option>
                        <option value="$ USD" <?= $settings['currency'] === '$ USD' ? 'selected' : '' ?>>$ USD (US Dollar)</option>
                        <option value="€ EUR" <?= $settings['currency'] === '€ EUR' ? 'selected' : '' ?>>€ EUR (Euro)</option>
                        <option value="£ GBP" <?= $settings['currency'] === '£ GBP' ? 'selected' : '' ?>>£ GBP (British Pound)</option>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="enable_registration" value="1" 
                                   <?= $settings['enable_registration'] === '1' ? 'checked' : '' ?>>
                            Allow New User Registration
                        </label>
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            When disabled, only admins can create accounts
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="enable_reviews" value="1" 
                                   <?= $settings['enable_reviews'] === '1' ? 'checked' : '' ?>>
                            Enable Product Reviews
                        </label>
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            Allow customers to leave reviews on products
                        </small>
                    </div>
                </div>
            </div>

            <!-- Store Tab -->
            <div class="tab-content" id="storeTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-shopping-bag"></i> Store Settings
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Store Logo URL</label>
                        <input type="text" name="store_logo" 
                               value="<?= htmlspecialchars($settings['store_logo']) ?>" 
                               class="form-control" 
                               placeholder="https://example.com/logo.png">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            URL to your store logo (displayed in header)
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Favicon URL</label>
                        <input type="text" name="favicon" 
                               value="<?= htmlspecialchars($settings['favicon']) ?>" 
                               class="form-control" 
                               placeholder="https://example.com/favicon.ico">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            URL to your site favicon (16x16 or 32x32 icon)
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Default Country</label>
                    <select name="default_country" class="form-control">
                        <option value="NG" <?= $settings['default_country'] === 'NG' ? 'selected' : '' ?>>Nigeria</option>
                        <option value="GH" <?= $settings['default_country'] === 'GH' ? 'selected' : '' ?>>Ghana</option>
                        <option value="KE" <?= $settings['default_country'] === 'KE' ? 'selected' : '' ?>>Kenya</option>
                        <option value="US" <?= $settings['default_country'] === 'US' ? 'selected' : '' ?>>United States</option>
                        <option value="UK" <?= $settings['default_country'] === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                    </select>
                </div>
            </div>

            <!-- Shipping & Tax Tab -->
            <div class="tab-content" id="shippingTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-shipping-fast"></i> Shipping & Tax Settings
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Free Shipping Threshold (₦)</label>
                        <input type="number" name="free_shipping_threshold" 
                               value="<?= htmlspecialchars($settings['free_shipping_threshold']) ?>" 
                               min="0" step="100" 
                               class="form-control">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            Orders above this amount get free shipping
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Standard Shipping Fee (₦)</label>
                        <input type="number" name="shipping_fee" 
                               value="<?= htmlspecialchars($settings['shipping_fee']) ?>" 
                               min="0" step="100" 
                               class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Tax Rate (%)</label>
                    <input type="number" name="tax_rate" 
                           value="<?= htmlspecialchars($settings['tax_rate']) ?>" 
                           min="0" max="100" step="0.1" 
                           class="form-control">
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        Set to 0 to disable tax calculations
                    </small>
                </div>
            </div>

            <!-- Payment Tab -->
            <div class="tab-content" id="paymentTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-credit-card"></i> Payment Gateway (Paystack)
                </h2>
                
                <div class="form-group">
                    <label class="form-label">Mode</label>
                    <select name="paystack_mode" class="form-control">
                        <option value="test" <?= $settings['paystack_mode'] === 'test' ? 'selected' : '' ?>>Test Mode</option>
                        <option value="live" <?= $settings['paystack_mode'] === 'live' ? 'selected' : '' ?>>Live Mode</option>
                    </select>
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        Use test mode for development, live mode for production
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Test Secret Key</label>
                        <input type="password" name="paystack_test_secret" 
                               value="<?= htmlspecialchars($settings['paystack_test_secret']) ?>" 
                               class="form-control" 
                               placeholder="sk_test_xxxxxxxx">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            From Paystack Test Dashboard
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Test Public Key</label>
                        <input type="text" name="paystack_test_public" 
                               value="<?= htmlspecialchars($settings['paystack_test_public']) ?>" 
                               class="form-control" 
                               placeholder="pk_test_xxxxxxxx">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Live Secret Key</label>
                        <input type="password" name="paystack_live_secret" 
                               value="<?= htmlspecialchars($settings['paystack_live_secret']) ?>" 
                               class="form-control" 
                               placeholder="sk_live_xxxxxxxx">
                        <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                            From Paystack Live Dashboard
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Live Public Key</label>
                        <input type="text" name="paystack_live_public" 
                               value="<?= htmlspecialchars($settings['paystack_live_public']) ?>" 
                               class="form-control" 
                               placeholder="pk_live_xxxxxxxx">
                    </div>
                </div>
            </div>

            <!-- Contact Tab -->
            <div class="tab-content" id="contactTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-address-book"></i> Contact Information
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Support Email *</label>
                        <input type="email" name="contact_email" 
                               value="<?= htmlspecialchars($settings['contact_email']) ?>" 
                               required class="form-control" 
                               placeholder="support@example.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Support Phone</label>
                        <input type="tel" name="contact_phone" 
                               value="<?= htmlspecialchars($settings['contact_phone']) ?>" 
                               class="form-control" 
                               placeholder="+234 123 456 7890">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Store Address</label>
                    <textarea name="contact_address" rows="3" class="form-control" 
                              placeholder="123 Main Street, Lagos, Nigeria"><?= htmlspecialchars($settings['contact_address']) ?></textarea>
                </div>
            </div>

            <!-- SEO & Social Tab -->
            <div class="tab-content" id="seoTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-chart-line"></i> SEO & Social Media
                </h2>
                
                <div class="form-group">
                    <label class="form-label">Meta Description</label>
                    <textarea name="meta_description" rows="3" class="form-control" 
                              placeholder="Brief description for search engines"><?= htmlspecialchars($settings['meta_description']) ?></textarea>
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        Recommended: 150-160 characters
                    </small>
                    <div id="metaDescCount" style="margin-top: 0.25rem; font-size: 0.875rem;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Meta Keywords</label>
                    <input type="text" name="meta_keywords" 
                           value="<?= htmlspecialchars($settings['meta_keywords']) ?>" 
                           class="form-control" 
                           placeholder="online shopping, nigeria, fashion, electronics">
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        Comma-separated keywords
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Facebook Page</label>
                        <input type="url" name="social_facebook" 
                               value="<?= htmlspecialchars($settings['social_facebook']) ?>" 
                               class="form-control" 
                               placeholder="https://facebook.com/yourpage">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Instagram</label>
                        <input type="url" name="social_instagram" 
                               value="<?= htmlspecialchars($settings['social_instagram']) ?>" 
                               class="form-control" 
                               placeholder="https://instagram.com/yourpage">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Twitter</label>
                        <input type="url" name="social_twitter" 
                               value="<?= htmlspecialchars($settings['social_twitter']) ?>" 
                               class="form-control" 
                               placeholder="https://twitter.com/yourpage">
                    </div>

                    <div class="form-group">
                        <label class="form-label">WhatsApp Business</label>
                        <input type="text" name="social_whatsapp" 
                               value="<?= htmlspecialchars($settings['social_whatsapp']) ?>" 
                               class="form-control" 
                               placeholder="+2341234567890">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Google Analytics ID</label>
                    <input type="text" name="google_analytics" 
                           value="<?= htmlspecialchars($settings['google_analytics']) ?>" 
                           class="form-control" 
                           placeholder="UA-XXXXXXXXX-X or G-XXXXXXXXXX">
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content" id="securityTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-shield-alt"></i> Security Settings
                </h2>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="enable_recaptcha" value="1" 
                               <?= $settings['enable_recaptcha'] === '1' ? 'checked' : '' ?>>
                        Enable reCAPTCHA on forms
                    </label>
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        Protects against spam on registration and contact forms
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">reCAPTCHA Site Key</label>
                        <input type="text" name="recaptcha_site_key" 
                               value="<?= htmlspecialchars($settings['recaptcha_site_key']) ?>" 
                               class="form-control" 
                               placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI">
                    </div>

                    <div class="form-group">
                        <label class="form-label">reCAPTCHA Secret Key</label>
                        <input type="password" name="recaptcha_secret_key" 
                               value="<?= htmlspecialchars($settings['recaptcha_secret_key']) ?>" 
                               class="form-control" 
                               placeholder="6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe">
                    </div>
                </div>
            </div>

            <!-- Maintenance Tab -->
            <div class="tab-content" id="maintenanceTab" style="display: none;">
                <h2 style="margin-bottom: 1.5rem; color: var(--admin-dark);">
                    <i class="fas fa-tools"></i> Maintenance Mode
                </h2>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" name="maintenance_mode" value="1" 
                               <?= $settings['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                        Enable Maintenance Mode
                    </label>
                    <small style="color: var(--admin-gray); display: block; margin-top: 0.25rem;">
                        When enabled, only admins can access the site. Visitors see a maintenance page.
                    </small>
                </div>

                <div class="alert alert-warning" style="margin-top: 1.5rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> Enabling maintenance mode will make your store inaccessible to customers.
                    Only administrators will be able to access the site.
                </div>
            </div>

            <!-- Form Actions -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--admin-border);">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                    <i class="fas fa-undo"></i> Reset to Defaults
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Tabs */
.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: var(--admin-gray);
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    font-size: 0.95rem;
}

.tab-btn:hover {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-border);
}

.tab-btn.active {
    color: var(--admin-primary);
    border-bottom-color: var(--admin-primary);
}

.tab-content {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--admin-dark);
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.9rem 1rem;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    font-size: 1rem;
    color: var(--admin-dark);
    background: white;
    transition: all 0.2s;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.form-control:focus {
    outline: none;
    border-color: var(--admin-primary);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-control::placeholder {
    color: var(--admin-gray);
}

/* Checkboxes */
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--admin-primary);
}

/* Select Styling */
select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1.5em;
    padding-right: 2.5rem;
}

/* Textarea */
textarea.form-control {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light));
    color: white;
    border: 1px solid var(--admin-primary);
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--admin-primary-dark), var(--admin-primary));
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: white;
    color: var(--admin-dark);
    border: 1px solid var(--admin-border);
}

.btn-secondary:hover {
    background: var(--admin-light);
    border-color: var(--admin-gray);
}

/* Alerts */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    border-left: 4px solid;
    font-size: 0.95rem;
}

.alert-success {
    background: linear-gradient(90deg, #d1fae5, #ecfdf5);
    border-left-color: var(--admin-secondary);
    color: #065f46;
}

.alert-danger {
    background: linear-gradient(90deg, #fee2e2, #fef2f2);
    border-left-color: var(--admin-danger);
    color: #991b1b;
}

.alert-warning {
    background: linear-gradient(90deg, #fef3c7, #fffbeb);
    border-left-color: var(--admin-warning);
    color: #92400e;
}

.alert i {
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Focus states */
.form-control:focus,
.btn:focus,
.tab-btn:focus {
    outline: 2px solid var(--admin-primary);
    outline-offset: 2px;
}

/* Responsive */
@media (max-width: 768px) {
    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab') + 'Tab';
            
            // Remove active class from all tabs
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Show corresponding content
            const activeTab = document.getElementById(tabId);
            if (activeTab) {
                activeTab.style.display = 'block';
                activeTab.classList.add('active');
            }
        });
    });

    // Character counter for meta description
    const metaDesc = document.querySelector('[name="meta_description"]');
    const descCount = document.getElementById('metaDescCount');
    
    if (metaDesc && descCount) {
        function updateDescCount() {
            const length = metaDesc.value.length;
            descCount.textContent = `${length} characters`;
            descCount.style.color = length > 160 ? 'var(--admin-danger)' : 'var(--admin-success)';
        }
        
        metaDesc.addEventListener('input', updateDescCount);
        updateDescCount(); // Initial call
    }

    // Show/hide reCAPTCHA fields
    const recaptchaToggle = document.querySelector('[name="enable_recaptcha"]');
    const recaptchaFields = document.querySelectorAll('[name^="recaptcha_"]');
    
    if (recaptchaToggle) {
        function toggleRecaptchaFields() {
            const isEnabled = recaptchaToggle.checked;
            recaptchaFields.forEach(field => {
                field.closest('.form-group').style.display = isEnabled ? 'block' : 'none';
            });
        }
        
        recaptchaToggle.addEventListener('change', toggleRecaptchaFields);
        toggleRecaptchaFields(); // Initial call
    }

    // Show/hide payment keys based on mode
    const paystackMode = document.querySelector('[name="paystack_mode"]');
    const testKeys = document.querySelectorAll('[name*="test"]');
    const liveKeys = document.querySelectorAll('[name*="live"]');
    
    if (paystackMode) {
        function togglePaystackKeys() {
            const mode = paystackMode.value;
            
            testKeys.forEach(field => {
                field.closest('.form-group').style.opacity = mode === 'test' ? '1' : '0.5';
                field.disabled = mode !== 'test';
            });
            
            liveKeys.forEach(field => {
                field.closest('.form-group').style.opacity = mode === 'live' ? '1' : '0.5';
                field.disabled = mode !== 'live';
            });
        }
        
        paystackMode.addEventListener('change', togglePaystackKeys);
        togglePaystackKeys(); // Initial call
    }

    // Form validation
    const settingsForm = document.getElementById('settingsForm');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            const email = this.querySelector('[name="contact_email"]');
            const freeShipping = this.querySelector('[name="free_shipping_threshold"]');
            const shippingFee = this.querySelector('[name="shipping_fee"]');
            const taxRate = this.querySelector('[name="tax_rate"]');
            
            // Validate email
            if (email && email.value && !isValidEmail(email.value)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                email.focus();
                return false;
            }
            
            // Validate free shipping threshold
            if (freeShipping && parseFloat(freeShipping.value) < 0) {
                e.preventDefault();
                alert('Free shipping threshold cannot be negative');
                freeShipping.focus();
                return false;
            }
            
            // Validate shipping fee
            if (shippingFee && parseFloat(shippingFee.value) < 0) {
                e.preventDefault();
                alert('Shipping fee cannot be negative');
                shippingFee.focus();
                return false;
            }
            
            // Add this continuation to the existing JavaScript section

                // Validate tax rate
                if (taxRate) {
                    const rate = parseFloat(taxRate.value);
                    if (isNaN(rate) || rate < 0 || rate > 100) {
                        e.preventDefault();
                        alert('Tax rate must be a number between 0 and 100');
                        taxRate.focus();
                        return false;
                    }
                }
                
                // Validate WhatsApp number if provided
                const whatsapp = this.querySelector('[name="social_whatsapp"]');
                if (whatsapp && whatsapp.value.trim()) {
                    if (!isValidWhatsApp(whatsapp.value)) {
                        e.preventDefault();
                        alert('Please enter a valid WhatsApp number (e.g., +2341234567890)');
                        whatsapp.focus();
                        return false;
                    }
                }
                
                // Validate Paystack keys if mode is selected
                if (paystackMode) {
                    const mode = paystackMode.value;
                    if (mode === 'test') {
                        const testSecret = this.querySelector('[name="paystack_test_secret"]');
                        const testPublic = this.querySelector('[name="paystack_test_public"]');
                        
                        if ((testSecret && testSecret.value.trim() && !testSecret.value.startsWith('sk_test_')) || 
                            (testPublic && testPublic.value.trim() && !testPublic.value.startsWith('pk_test_'))) {
                            e.preventDefault();
                            alert('Test keys should start with "sk_test_" or "pk_test_"');
                            testSecret.focus();
                            return false;
                        }
                    } else if (mode === 'live') {
                        const liveSecret = this.querySelector('[name="paystack_live_secret"]');
                        const livePublic = this.querySelector('[name="paystack_live_public"]');
                        
                        if ((liveSecret && liveSecret.value.trim() && !liveSecret.value.startsWith('sk_live_')) || 
                            (livePublic && livePublic.value.trim() && !livePublic.value.startsWith('pk_live_'))) {
                            e.preventDefault();
                            alert('Live keys should start with "sk_live_" or "pk_live_"');
                            liveSecret.focus();
                            return false;
                        }
                    }
                }
                
                // Show loading indicator
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitBtn.disabled = true;
                }
                
                return true;
            });
        }
        
        // Helper functions
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
        
        function isValidWhatsApp(number) {
            const re = /^\+[1-9]\d{1,14}$/;
            return re.test(number.trim());
        }
        
        // Reset to defaults function
        window.resetToDefaults = function() {
            if (confirm('Are you sure you want to reset all settings to defaults? This action cannot be undone.')) {
                // Create a hidden form with default values
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                // Add all default values as hidden inputs
                const defaults = <?= json_encode($defaults) ?>;
                for (const [key, value] of Object.entries(defaults)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        };
        
        // Show password toggle for secret keys
        const secretInputs = document.querySelectorAll('input[type="password"]');
        secretInputs.forEach(input => {
            const wrapper = input.parentElement;
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.style.cssText = `
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--admin-gray);
                cursor: pointer;
                font-size: 1rem;
            `;
            toggleBtn.addEventListener('click', function() {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
            
            wrapper.style.position = 'relative';
            input.style.paddingRight = '3rem';
            wrapper.appendChild(toggleBtn);
        });
        
        // Auto-save indicator
        let autoSaveTimer;
        const formInputs = settingsForm.querySelectorAll('input, select, textarea');
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Clear previous timer
                clearTimeout(autoSaveTimer);
                
                // Show auto-save message
                const autoSaveMsg = document.getElementById('autoSaveMsg') || (() => {
                    const msg = document.createElement('div');
                    msg.id = 'autoSaveMsg';
                    msg.style.cssText = `
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        background: var(--admin-primary);
                        color: white;
                        padding: 0.75rem 1.5rem;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        display: none;
                        align-items: center;
                        gap: 0.5rem;
                        z-index: 1000;
                        font-size: 0.9rem;
                    `;
                    msg.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changes detected';
                    document.body.appendChild(msg);
                    return msg;
                })();
                
                autoSaveMsg.style.display = 'flex';
                
                // Set timer to hide message
                autoSaveTimer = setTimeout(() => {
                    autoSaveMsg.style.display = 'none';
                }, 3000);
            });
        });
        
        // Warn before leaving unsaved changes
        let hasUnsavedChanges = false;
        formInputs.forEach(input => {
            input.addEventListener('input', () => {
                hasUnsavedChanges = true;
            });
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        settingsForm.addEventListener('submit', () => {
            hasUnsavedChanges = false;
        });
        
        // Tab switching with URL hash
        function activateTabFromHash() {
            const hash = window.location.hash.substring(1);
            if (hash) {
                const tabBtn = document.querySelector(`.tab-btn[data-tab="${hash}"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
        }
        
        window.addEventListener('hashchange', activateTabFromHash);
        activateTabFromHash();
        
        // Set hash when clicking tabs
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                window.location.hash = tabName;
            });
        });
        
        // Export/Import settings functionality
        const actionBar = document.createElement('div');
        actionBar.style.cssText = `
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: flex-end;
        `;
        
        const exportBtn = document.createElement('button');
        exportBtn.type = 'button';
        exportBtn.className = 'btn btn-secondary';
        exportBtn.innerHTML = '<i class="fas fa-download"></i> Export Settings';
        exportBtn.onclick = function() {
            const settingsData = {};
            formInputs.forEach(input => {
                if (input.name) {
                    settingsData[input.name] = input.type === 'checkbox' ? input.checked : input.value;
                }
            });
            
            const dataStr = JSON.stringify(settingsData, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'settings-export-<?= date('Y-m-d') ?>.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        };
        
        const importBtn = document.createElement('button');
        importBtn.type = 'button';
        importBtn.className = 'btn btn-secondary';
        importBtn.innerHTML = '<i class="fas fa-upload"></i> Import Settings';
        importBtn.onclick = function() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.style.display = 'none';
            
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = JSON.parse(e.target.result);
                        
                        // Populate form fields
                        for (const [key, value] of Object.entries(data)) {
                            const input = settingsForm.querySelector(`[name="${key}"]`);
                            if (input) {
                                if (input.type === 'checkbox') {
                                    input.checked = Boolean(value);
                                } else {
                                    input.value = value;
                                }
                            }
                        }
                        
                        alert('Settings imported successfully! Click "Save All Settings" to apply.');
                    } catch (err) {
                        alert('Error importing settings: Invalid JSON file');
                    }
                };
                
                reader.readAsText(file);
            };
            
            document.body.appendChild(input);
            input.click();
            document.body.removeChild(input);
        };
        
        actionBar.appendChild(exportBtn);
        actionBar.appendChild(importBtn);
        settingsForm.parentNode.insertBefore(actionBar, settingsForm.nextSibling);
        
        // Settings search functionality
        const searchContainer = document.createElement('div');
        searchContainer.style.cssText = `
            position: relative;
            margin-bottom: 1.5rem;
        `;
        
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.placeholder = 'Search settings...';
        searchInput.style.cssText = `
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 1px solid var(--admin-border);
            border-radius: 8px;
            font-size: 1rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 1rem center;
            background-size: 1.25rem;
        `;
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Search in labels
            const labels = document.querySelectorAll('.form-label');
            let foundInTab = null;
            
            labels.forEach(label => {
                const labelText = label.textContent.toLowerCase();
                const formGroup = label.closest('.form-group');
                
                if (labelText.includes(searchTerm) || 
                    (formGroup.querySelector('input') && formGroup.querySelector('input').name.toLowerCase().includes(searchTerm))) {
                    formGroup.style.backgroundColor = 'rgba(79, 70, 229, 0.1)';
                    formGroup.style.borderLeft = '3px solid var(--admin-primary)';
                    formGroup.style.paddingLeft = '0.75rem';
                    formGroup.style.marginLeft = '-0.75rem';
                    
                    // Find which tab this belongs to
                    const tabContent = formGroup.closest('.tab-content');
                    if (tabContent) {
                        const tabId = tabContent.id.replace('Tab', '');
                        foundInTab = tabId;
                    }
                } else {
                    formGroup.style.backgroundColor = '';
                    formGroup.style.borderLeft = '';
                    formGroup.style.paddingLeft = '';
                    formGroup.style.marginLeft = '';
                }
            });
            
            // Switch to tab where search term was found
            if (foundInTab && searchTerm) {
                const tabBtn = document.querySelector(`.tab-btn[data-tab="${foundInTab}"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
        });
        
        searchContainer.appendChild(searchInput);
        settingsForm.parentNode.insertBefore(searchContainer, settingsForm);
        
        // Initialize tooltips
        const tooltips = document.querySelectorAll('[title]');
        tooltips.forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.textContent = this.title;
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--admin-dark);
                    color: white;
                    padding: 0.5rem 0.75rem;
                    border-radius: 4px;
                    font-size: 0.875rem;
                    z-index: 10000;
                    max-width: 300px;
                    white-space: pre-wrap;
                `;
                
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
                tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
                
                this._tooltip = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    delete this._tooltip;
                }
            });
        });
        
        console.log('Settings page initialized successfully');
    });
</script>
</body>
</html>