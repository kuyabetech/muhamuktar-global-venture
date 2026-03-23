<?php
// includes/config.php - Main Configuration File

// Site constants (fallbacks if database not available)
define('SITE_NAME_DEFAULT', 'Muhamuktar Global Venture');
define('BASE_URL', 'http://localhost:8081/'); // change to your domain later

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'muhamuktar');
define('DB_USER', 'root');           // ← change
define('DB_PASS', '');               // ← change

// Database
//define('DB_HOST', 'localhost');
//define('DB_NAME', 'eceklebg_muhamuktar');
//define('DB_USER', 'eceklebg_muhamuktar');           // ← change
//define('DB_PASS', 'Abdulx32@/@!');               // ← change

// Paystack default keys (fallbacks)
define('PAYSTACK_PUBLIC_KEY_DEFAULT', 'pk_test_916e63eef1dcfb9bb3606252da956b1997be9e8d');
define('PAYSTACK_SECRET_KEY_DEFAULT', 'sk_test_5a39204118831ccb0113d1730dfabfe17a47efa0');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (development only)
error_reporting(E_ALL);
ini_set('display_errors', 1);        // remove in production

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // If database connection fails, we'll use default values
    error_log("Database connection failed: " . $e->getMessage());
    // Don't exit - we'll use defaults
}

// Function to get settings from database
function getSetting($key, $default = null) {
    global $pdo;
    
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        }
    } catch (Exception $e) {
        error_log("Error fetching setting '$key': " . $e->getMessage());
    }
    
    return $default;
}

// Load ALL settings from database
try {
    if (isset($pdo)) {
        // =============================================
        // SITE INFORMATION
        // =============================================
        $site_name = getSetting('site_name');
        define('SITE_NAME', $site_name ?: SITE_NAME_DEFAULT);
        
        $site_slogan = getSetting('site_slogan');
        define('SITE_SLOGAN', $site_slogan ?: 'Quality Products • Fast Delivery');
        
        $site_logo = getSetting('site_logo');
        define('SITE_LOGO', $site_logo ?: '');
        
        $favicon = getSetting('favicon');
        define('FAVICON', $favicon ?: '');
        
        // =============================================
        // PAYSTACK PAYMENT GATEWAY
        // =============================================
        $paystack_mode = getSetting('paystack_mode', 'test');
        define('PAYSTACK_MODE', $paystack_mode);
        
        if ($paystack_mode === 'live') {
            define('PAYSTACK_PUBLIC_KEY', getSetting('paystack_live_public', PAYSTACK_PUBLIC_KEY_DEFAULT));
            define('PAYSTACK_SECRET_KEY', getSetting('paystack_live_secret', PAYSTACK_SECRET_KEY_DEFAULT));
        } else {
            define('PAYSTACK_PUBLIC_KEY', getSetting('paystack_test_public', PAYSTACK_PUBLIC_KEY_DEFAULT));
            define('PAYSTACK_SECRET_KEY', getSetting('paystack_test_secret', PAYSTACK_SECRET_KEY_DEFAULT));
        }
        
        // =============================================
        // EMAIL SETTINGS (SMTP)
        // =============================================
        define('SMTP_HOST', getSetting('smtp_host', 'smtp.gmail.com'));
        define('SMTP_PORT', (int)getSetting('smtp_port', 587));
        define('SMTP_USER', getSetting('smtp_user', ''));
        define('SMTP_PASS', getSetting('smtp_pass', ''));
        define('SMTP_ENCRYPTION', getSetting('smtp_encryption', 'tls'));
        define('CONTACT_EMAIL', getSetting('contact_email', 'support@muhamuktar.com'));
        define('CONTACT_PHONE', getSetting('contact_phone', '+234 123 456 7890'));
        define('CONTACT_ADDRESS', getSetting('contact_address', '123 Main Street, Lagos, Nigeria'));
        
        // =============================================
        // CURRENCY SETTINGS
        // =============================================
        $currency_setting = getSetting('currency', '₦ NGN');
        $currency_parts = explode(' ', $currency_setting);
        define('CURRENCY_SYMBOL', $currency_parts[0] ?? '₦');
        define('CURRENCY_CODE', $currency_parts[1] ?? 'NGN');
        
        // =============================================
        // SHIPPING SETTINGS
        // =============================================
        define('FREE_SHIPPING_THRESHOLD', (float)getSetting('free_shipping_threshold', 50000));
        define('STANDARD_SHIPPING_FEE', (float)getSetting('shipping_fee', 1500));
        
        // =============================================
        // TAX SETTINGS
        // =============================================
        define('TAX_RATE', (float)getSetting('tax_rate', 7.5));
        define('TAX_INCLUDED', (bool)getSetting('tax_included', false));
        
        // =============================================
        // SITE FEATURES
        // =============================================
        define('MAINTENANCE_MODE', (bool)getSetting('maintenance_mode', false));
        define('ALLOW_REGISTRATION', (bool)getSetting('enable_registration', true));
        define('ALLOW_REVIEWS', (bool)getSetting('enable_reviews', true));
        
        // =============================================
        // SOCIAL MEDIA LINKS
        // =============================================
        define('SOCIAL_FACEBOOK', getSetting('social_facebook', ''));
        define('SOCIAL_TWITTER', getSetting('social_twitter', ''));
        define('SOCIAL_INSTAGRAM', getSetting('social_instagram', ''));
        define('SOCIAL_WHATSAPP', getSetting('social_whatsapp', ''));
        define('SOCIAL_YOUTUBE', getSetting('social_youtube', ''));
        define('SOCIAL_TIKTOK', getSetting('social_tiktok', ''));
        
        // =============================================
        // SEO SETTINGS
        // =============================================
        define('META_DESCRIPTION', getSetting('meta_description', 'Premium marketplace offering quality products with fast delivery across Nigeria.'));
        define('META_KEYWORDS', getSetting('meta_keywords', 'online shopping, nigeria, ecommerce, marketplace'));
        define('META_AUTHOR', getSetting('meta_author', 'Muhamuktar Global Venture'));
        
        // =============================================
        // ANNOUNCEMENT
        // =============================================
        define('ANNOUNCEMENT_TEXT', getSetting('announcement_text', '🚚 Free shipping on orders over ₦50,000 • WELCOME25 for 10% off your first order!'));
        define('ANNOUNCEMENT_ENABLED', (bool)getSetting('announcement_enabled', true));
        
        // =============================================
        // COMPANY INFORMATION
        // =============================================
        define('COMPANY_NAME', getSetting('company_name', SITE_NAME));
        define('COMPANY_ADDRESS', getSetting('contact_address', '123 Main Street, Lagos, Nigeria'));
        define('COMPANY_REGISTRATION', getSetting('registration_number', 'RC1234567'));
        define('COMPANY_VAT', getSetting('vat_number', ''));
        
        // =============================================
        // FOOTER SETTINGS
        // =============================================
        define('FOOTER_DESCRIPTION', getSetting('footer_description', 'Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.'));
        define('COPYRIGHT_TEXT', getSetting('copyright_text', 'All rights reserved.'));
        define('FOOTER_COLUMNS', (int)getSetting('footer_columns', 4));
        
        // =============================================
        // CACHE SETTINGS
        // =============================================
        define('CACHE_ENABLED', (bool)getSetting('cache_enabled', true));
        define('CACHE_LIFETIME', (int)getSetting('cache_lifetime', 3600));
        
        // =============================================
        // IMAGE SETTINGS
        // =============================================
        define('MAX_IMAGE_SIZE', (int)getSetting('max_image_size', 10 * 1024 * 1024)); // 10MB default
        define('ALLOWED_IMAGE_TYPES', getSetting('allowed_image_types', 'jpg,jpeg,png,gif,webp'));
        define('IMAGE_QUALITY', (int)getSetting('image_quality', 80));
        
        // =============================================
        // PRODUCT SETTINGS
        // =============================================
        define('PRODUCTS_PER_PAGE', (int)getSetting('products_per_page', 12));
        define('RELATED_PRODUCTS_COUNT', (int)getSetting('related_products_count', 4));
        define('RECENTLY_VIEWED_COUNT', (int)getSetting('recently_viewed_count', 6));
        
        // =============================================
        // ORDER SETTINGS
        // =============================================
        define('ORDER_PREFIX', getSetting('order_prefix', 'ORD-'));
        define('INVOICE_PREFIX', getSetting('invoice_prefix', 'INV-'));
        define('ORDER_EXPIRY_HOURS', (int)getSetting('order_expiry_hours', 24));
        define('RETURN_DAYS', (int)getSetting('return_policy_days', 14));
        
        // =============================================
        // SECURITY SETTINGS
        // =============================================
        define('SESSION_TIMEOUT', (int)getSetting('session_timeout', 3600)); // 1 hour
        define('MAX_LOGIN_ATTEMPTS', (int)getSetting('max_login_attempts', 5));
        define('PASSWORD_MIN_LENGTH', (int)getSetting('password_min_length', 8));
        define('TWO_FACTOR_AUTH', (bool)getSetting('two_factor_auth', false));
        
        // =============================================
        // ANALYTICS & TRACKING
        // =============================================
        define('GOOGLE_ANALYTICS_ID', getSetting('google_analytics', ''));
        define('FACEBOOK_PIXEL_ID', getSetting('facebook_pixel', ''));
        
        // =============================================
        // NEWSLETTER SETTINGS
        // =============================================
        define('NEWSLETTER_ENABLED', (bool)getSetting('newsletter_enabled', true));
        define('MAILCHIMP_API_KEY', getSetting('mailchimp_api_key', ''));
        define('MAILCHIMP_LIST_ID', getSetting('mailchimp_list_id', ''));
        
        // =============================================
        // REVIEW SETTINGS
        // =============================================
        define('REVIEW_MODERATION', (bool)getSetting('review_moderation', true));
        define('REVIEW_MIN_LENGTH', (int)getSetting('review_min_length', 10));
        
        // =============================================
        // API SETTINGS
        // =============================================
        define('API_RATE_LIMIT', (int)getSetting('api_rate_limit', 60)); // requests per minute
        define('API_KEY', getSetting('api_key', ''));
        
        // =============================================
        // WHATSAPP SETTINGS
        // =============================================
        define('WHATSAPP_NUMBER', getSetting('whatsapp_number', ''));
        define('WHATSAPP_ENABLED', (bool)getSetting('whatsapp_enabled', false));
        
        // =============================================
        // RECAPTCHA SETTINGS
        // =============================================
        define('RECAPTCHA_ENABLED', (bool)getSetting('recaptcha_enabled', false));
        define('RECAPTCHA_SITE_KEY', getSetting('recaptcha_site_key', ''));
        define('RECAPTCHA_SECRET_KEY', getSetting('recaptcha_secret_key', ''));
        
    } else {
        // Use defaults if database not available
        define('SITE_NAME', SITE_NAME_DEFAULT);
        define('SITE_SLOGAN', 'Quality Products • Fast Delivery');
        define('SITE_LOGO', '');
        define('FAVICON', '');
        
        define('PAYSTACK_MODE', 'test');
        define('PAYSTACK_PUBLIC_KEY', PAYSTACK_PUBLIC_KEY_DEFAULT);
        define('PAYSTACK_SECRET_KEY', PAYSTACK_SECRET_KEY_DEFAULT);
        
        define('SMTP_HOST', 'smtp.gmail.com');
        define('SMTP_PORT', 587);
        define('SMTP_USER', '');
        define('SMTP_PASS', '');
        define('SMTP_ENCRYPTION', 'tls');
        define('CONTACT_EMAIL', 'support@muhamuktar.com');
        define('CONTACT_PHONE', '+234 123 456 7890');
        define('CONTACT_ADDRESS', '123 Main Street, Lagos, Nigeria');
        
        define('CURRENCY_SYMBOL', '₦');
        define('CURRENCY_CODE', 'NGN');
        
        define('FREE_SHIPPING_THRESHOLD', 50000);
        define('STANDARD_SHIPPING_FEE', 1500);
        
        define('TAX_RATE', 7.5);
        define('TAX_INCLUDED', false);
        
        define('MAINTENANCE_MODE', false);
        define('ALLOW_REGISTRATION', true);
        define('ALLOW_REVIEWS', true);
        
        define('SOCIAL_FACEBOOK', '');
        define('SOCIAL_TWITTER', '');
        define('SOCIAL_INSTAGRAM', '');
        define('SOCIAL_WHATSAPP', '');
        define('SOCIAL_YOUTUBE', '');
        define('SOCIAL_TIKTOK', '');
        
        define('META_DESCRIPTION', 'Premium marketplace offering quality products with fast delivery across Nigeria.');
        define('META_KEYWORDS', 'online shopping, nigeria, ecommerce, marketplace');
        define('META_AUTHOR', 'Muhamuktar Global Venture');
        
        define('ANNOUNCEMENT_TEXT', '🚚 Free shipping on orders over ₦50,000 • WELCOME25 for 10% off your first order!');
        define('ANNOUNCEMENT_ENABLED', true);
        
        define('COMPANY_NAME', SITE_NAME_DEFAULT);
        define('COMPANY_ADDRESS', '123 Main Street, Lagos, Nigeria');
        define('COMPANY_REGISTRATION', 'RC1234567');
        define('COMPANY_VAT', '');
        
        define('FOOTER_DESCRIPTION', 'Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.');
        define('COPYRIGHT_TEXT', 'All rights reserved.');
        define('FOOTER_COLUMNS', 4);
        
        define('CACHE_ENABLED', true);
        define('CACHE_LIFETIME', 3600);
        
        define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);
        define('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp');
        define('IMAGE_QUALITY', 80);
        
        define('PRODUCTS_PER_PAGE', 12);
        define('RELATED_PRODUCTS_COUNT', 4);
        define('RECENTLY_VIEWED_COUNT', 6);
        
        define('ORDER_PREFIX', 'ORD-');
        define('INVOICE_PREFIX', 'INV-');
        define('ORDER_EXPIRY_HOURS', 24);
        define('RETURN_DAYS', 14);
        
        define('SESSION_TIMEOUT', 3600);
        define('MAX_LOGIN_ATTEMPTS', 5);
        define('PASSWORD_MIN_LENGTH', 8);
        define('TWO_FACTOR_AUTH', false);
        
        define('GOOGLE_ANALYTICS_ID', '');
        define('FACEBOOK_PIXEL_ID', '');
        
        define('NEWSLETTER_ENABLED', true);
        define('MAILCHIMP_API_KEY', '');
        define('MAILCHIMP_LIST_ID', '');
        
        define('REVIEW_MODERATION', true);
        define('REVIEW_MIN_LENGTH', 10);
        
        define('API_RATE_LIMIT', 60);
        define('API_KEY', '');
        
        define('WHATSAPP_NUMBER', '');
        define('WHATSAPP_ENABLED', false);
        
        define('RECAPTCHA_ENABLED', false);
        define('RECAPTCHA_SITE_KEY', '');
        define('RECAPTCHA_SECRET_KEY', '');
    }
    
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    
    // Fallback to defaults if any error occurs
    define('SITE_NAME', SITE_NAME_DEFAULT);
    define('SITE_SLOGAN', 'Quality Products • Fast Delivery');
    define('SITE_LOGO', '');
    define('FAVICON', '');
    
    define('PAYSTACK_MODE', 'test');
    define('PAYSTACK_PUBLIC_KEY', PAYSTACK_PUBLIC_KEY_DEFAULT);
    define('PAYSTACK_SECRET_KEY', PAYSTACK_SECRET_KEY_DEFAULT);
    
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', '');
    define('SMTP_PASS', '');
    define('SMTP_ENCRYPTION', 'tls');
    define('CONTACT_EMAIL', 'support@muhamuktar.com');
    define('CONTACT_PHONE', '+234 123 456 7890');
    define('CONTACT_ADDRESS', '123 Main Street, Lagos, Nigeria');
    
    define('CURRENCY_SYMBOL', '₦');
    define('CURRENCY_CODE', 'NGN');
    
    define('FREE_SHIPPING_THRESHOLD', 50000);
    define('STANDARD_SHIPPING_FEE', 1500);
    
    define('TAX_RATE', 7.5);
    define('TAX_INCLUDED', false);
    
    define('MAINTENANCE_MODE', false);
    define('ALLOW_REGISTRATION', true);
    define('ALLOW_REVIEWS', true);
    
    define('SOCIAL_FACEBOOK', '');
    define('SOCIAL_TWITTER', '');
    define('SOCIAL_INSTAGRAM', '');
    define('SOCIAL_WHATSAPP', '');
    define('SOCIAL_YOUTUBE', '');
    define('SOCIAL_TIKTOK', '');
    
    define('META_DESCRIPTION', 'Premium marketplace offering quality products with fast delivery across Nigeria.');
    define('META_KEYWORDS', 'online shopping, nigeria, ecommerce, marketplace');
    define('META_AUTHOR', 'Muhamuktar Global Venture');
    
    define('ANNOUNCEMENT_TEXT', '🚚 Free shipping on orders over ₦50,000 • WELCOME25 for 10% off your first order!');
    define('ANNOUNCEMENT_ENABLED', true);
    
    define('COMPANY_NAME', SITE_NAME_DEFAULT);
    define('COMPANY_ADDRESS', '123 Main Street, Lagos, Nigeria');
    define('COMPANY_REGISTRATION', 'RC1234567');
    define('COMPANY_VAT', '');
    
    define('FOOTER_DESCRIPTION', 'Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.');
    define('COPYRIGHT_TEXT', 'All rights reserved.');
    define('FOOTER_COLUMNS', 4);
    
    define('CACHE_ENABLED', true);
    define('CACHE_LIFETIME', 3600);
    
    define('MAX_IMAGE_SIZE', 10 * 1024 * 1024);
    define('ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,webp');
    define('IMAGE_QUALITY', 80);
    
    define('PRODUCTS_PER_PAGE', 12);
    define('RELATED_PRODUCTS_COUNT', 4);
    define('RECENTLY_VIEWED_COUNT', 6);
    
    define('ORDER_PREFIX', 'ORD-');
    define('INVOICE_PREFIX', 'INV-');
    define('ORDER_EXPIRY_HOURS', 24);
    define('RETURN_DAYS', 14);
    
    define('SESSION_TIMEOUT', 3600);
    define('MAX_LOGIN_ATTEMPTS', 5);
    define('PASSWORD_MIN_LENGTH', 8);
    define('TWO_FACTOR_AUTH', false);
    
    define('GOOGLE_ANALYTICS_ID', '');
    define('FACEBOOK_PIXEL_ID', '');
    
    define('NEWSLETTER_ENABLED', true);
    define('MAILCHIMP_API_KEY', '');
    define('MAILCHIMP_LIST_ID', '');
    
    define('REVIEW_MODERATION', true);
    define('REVIEW_MIN_LENGTH', 10);
    
    define('API_RATE_LIMIT', 60);
    define('API_KEY', '');
    
    define('WHATSAPP_NUMBER', '');
    define('WHATSAPP_ENABLED', false);
    
    define('RECAPTCHA_ENABLED', false);
    define('RECAPTCHA_SITE_KEY', '');
    define('RECAPTCHA_SECRET_KEY', '');
}

// Timezone setting
date_default_timezone_set('Africa/Lagos');

// Helper function to check if site is in maintenance mode
function isMaintenanceMode() {
    // Allow admins to bypass maintenance
    if (function_exists('is_admin') && is_admin()) {
        return false;
    }
    
    // Allow specific IPs (add your office IPs)
    $allowed_ips = ['127.0.0.1', '::1']; // Add more IPs as needed
    if (in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
        return false;
    }
    
    return defined('MAINTENANCE_MODE') && MAINTENANCE_MODE;
}

// Helper function to get currency symbol
function getCurrencySymbol() {
    return defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₦';
}

// Helper function to format price
function formatPrice($amount) {
    return getCurrencySymbol() . number_format($amount, 2);
}

// Helper function to get site name
function getSiteName() {
    return defined('SITE_NAME') ? SITE_NAME : 'Muhamuktar Global Venture';
}

// Helper function to get site slogan
function getSiteSlogan() {
    return defined('SITE_SLOGAN') ? SITE_SLOGAN : 'Quality Products • Fast Delivery';
}

// Helper function to get contact email
function getContactEmail() {
    return defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'support@muhamuktar.com';
}

// Helper function to get contact phone
function getContactPhone() {
    return defined('CONTACT_PHONE') ? CONTACT_PHONE : '+234 123 456 7890';
}

// Helper function to get contact address
function getContactAddress() {
    return defined('CONTACT_ADDRESS') ? CONTACT_ADDRESS : '123 Main Street, Lagos, Nigeria';
}

// Helper function to check if registration is allowed
function isRegistrationAllowed() {
    return defined('ALLOW_REGISTRATION') ? ALLOW_REGISTRATION : true;
}

// Helper function to get announcement
function getAnnouncement() {
    if (defined('ANNOUNCEMENT_ENABLED') && !ANNOUNCEMENT_ENABLED) {
        return '';
    }
    return defined('ANNOUNCEMENT_TEXT') ? ANNOUNCEMENT_TEXT : '';
}

// Helper function to get meta description
function getMetaDescription() {
    return defined('META_DESCRIPTION') ? META_DESCRIPTION : '';
}

// Helper function to get meta keywords
function getMetaKeywords() {
    return defined('META_KEYWORDS') ? META_KEYWORDS : '';
}

// Helper function to get Paystack public key
function getPaystackPublicKey() {
    return defined('PAYSTACK_PUBLIC_KEY') ? PAYSTACK_PUBLIC_KEY : '';
}

// Helper function to get Paystack secret key
function getPaystackSecretKey() {
    return defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : '';
}

// Helper function to check if Paystack is in test mode
function isPaystackTestMode() {
    return defined('PAYSTACK_MODE') && PAYSTACK_MODE === 'test';
}

// Helper function to get products per page
function getProductsPerPage() {
    return defined('PRODUCTS_PER_PAGE') ? PRODUCTS_PER_PAGE : 12;
}

// Helper function to get order prefix
function getOrderPrefix() {
    return defined('ORDER_PREFIX') ? ORDER_PREFIX : 'ORD-';
}

// Helper function to get return days
function getReturnDays() {
    return defined('RETURN_DAYS') ? RETURN_DAYS : 14;
}

// Helper function to check if cache is enabled
function isCacheEnabled() {
    return defined('CACHE_ENABLED') ? CACHE_ENABLED : true;
}

// Helper function to get cache lifetime
function getCacheLifetime() {
    return defined('CACHE_LIFETIME') ? CACHE_LIFETIME : 3600;
}

// Helper function to check if reviews are allowed
function areReviewsAllowed() {
    return defined('ALLOW_REVIEWS') ? ALLOW_REVIEWS : true;
}

// Helper function to check if review moderation is enabled
function isReviewModerated() {
    return defined('REVIEW_MODERATION') ? REVIEW_MODERATION : true;
}

// Helper function to get review minimum length
function getReviewMinLength() {
    return defined('REVIEW_MIN_LENGTH') ? REVIEW_MIN_LENGTH : 10;
}

// Helper function to get social media links
function getSocialLinks() {
    return [
        'facebook' => defined('SOCIAL_FACEBOOK') ? SOCIAL_FACEBOOK : '',
        'twitter' => defined('SOCIAL_TWITTER') ? SOCIAL_TWITTER : '',
        'instagram' => defined('SOCIAL_INSTAGRAM') ? SOCIAL_INSTAGRAM : '',
        'whatsapp' => defined('SOCIAL_WHATSAPP') ? SOCIAL_WHATSAPP : '',
        'youtube' => defined('SOCIAL_YOUTUBE') ? SOCIAL_YOUTUBE : '',
        'tiktok' => defined('SOCIAL_TIKTOK') ? SOCIAL_TIKTOK : '',
    ];
}

// Helper function to get footer description
function getFooterDescription() {
    return defined('FOOTER_DESCRIPTION') ? FOOTER_DESCRIPTION : '';
}

// Helper function to get copyright text
function getCopyrightText() {
    $text = defined('COPYRIGHT_TEXT') ? COPYRIGHT_TEXT : 'All rights reserved.';
    return '&copy; ' . date('Y') . ' ' . getSiteName() . '. ' . $text;
}

// Maintenance mode check
if (isMaintenanceMode()) {
    // Only include maintenance page if not already in maintenance page
    $current_page = basename($_SERVER['PHP_SELF']);
    if ($current_page !== 'maintenance.php') {
        require_once 'maintenance.php';
        exit;
    }
}