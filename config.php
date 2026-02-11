<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'online_courses');

// Zoom API - Fixed syntax errors
define('ACCOUNT_ID', 'AcsqCHS4TAySIQamqpRJSA');
define('ZOOM_API_KEY', 'T0kn_ewSKKtC3vqTWtdgw');
define('ZOOM_API_SECRET', 'Nex3SdtBEp1Si2GDVvTVKUdq6lVyuW14');

// Paystack API
define('PAYSTACK_PUBLIC_KEY', 'pk_test_916e63eef1dcfb9bb3606252da956b1997be9e8d');
define('PAYSTACK_SECRET_KEY', 'sk_test_5a39204118831ccb0113d1730dfabfe17a47efa0');
define('PAYSTACK_CALLBACK_URL', 'http://localhost/student/verify_payment.php');

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kuyabe3232@gmail.com');
define('SMTP_PASSWORD', 'xfuftuhthzvuurtq');
define('SMTP_FROM_EMAIL', 'noreply@learng.ng');
define('SMTP_FROM_NAME', 'LearnNG Team');

// Debug mode - enable for development
define('DEBUG_MODE', true);

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
    if (DEBUG_MODE) {
        die("Database connection failed: " . htmlspecialchars($e->getMessage()));
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please try again later.");
    }
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser($pdo) {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("currentUser error: " . $e->getMessage());
        return null;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
}

function requireRole($pdo, $role) {
    $user = currentUser($pdo);
    if (!$user || $user['role'] !== $role) {
        if (!headers_sent()) {
            header("Location: ../unauthorized.php");
        }
        exit("Access denied.");
    }
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token']) {
            logSecurityEvent('CSRF token validation failed', $_SERVER['REMOTE_ADDR']);
            if (!headers_sent()) {
                header("Location: ../error.php?code=csrf");
            }
            exit("Invalid CSRF token. Please try again.");
        }
    }
}

// Rate Limiting - Simplified version

// Security logging
function logSecurityEvent($event, $ip) {
    $log = date('Y-m-d H:i:s') . " - IP: $ip - Event: $event" . PHP_EOL;
    @file_put_contents(__DIR__ . '/security.log', $log, FILE_APPEND);
}

// Caching System - Fixed directory creation
class CacheSystem {
    private $cacheDir;
    private $ttl;
    
    public function __construct($ttl = 300) {
        $this->cacheDir = __DIR__ . '/cache/';
        $this->ttl = $ttl;
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
                throw new RuntimeException('Failed to create cache directory: ' . $this->cacheDir);
            }
        }
    }
    
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        
        $data = @unserialize($content);
        if ($data === false) {
            @unlink($file);
            return null;
        }
        
        if (time() - $data['timestamp'] > $this->ttl) {
            @unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public function set($key, $value, $customTtl = null) {
        $file = $this->getCacheFile($key);
        $data = [
            'timestamp' => time(),
            'value' => $value
        ];
        
        $ttl = $customTtl ?: $this->ttl;
        return @file_put_contents($file, serialize($data)) !== false;
    }
    
    public function delete($key) {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }
    
    public function clear() {
        $files = @glob($this->cacheDir . '*');
        if ($files === false) {
            return false;
        }
        
        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!@unlink($file)) {
                    $success = false;
                }
            }
        }
        return $success;
    }
    
    private function getCacheFile($key) {
        $hashedKey = hash('sha256', $key);
        return $this->cacheDir . $hashedKey . '.cache';
    }
}

// Initialize cache with error handling
try {
    $cache = new CacheSystem(300);
} catch (Exception $e) {
    error_log("Cache initialization failed: " . $e->getMessage());
    // Create a dummy cache that always returns null
    $cache = new class {
        public function get($key) { return null; }
        public function set($key, $value, $customTtl = null) { return true; }
        public function delete($key) { return true; }
        public function clear() { return true; }
    };
}

// Error handling
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_types = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    
    error_log("PHP $error_type [$errno]: $errstr in $errfile on line $errline");
    
    if (DEBUG_MODE) {
        echo "<div style='background:#fee; border:1px solid #f00; padding:15px; margin:10px; border-radius:5px; font-family: Arial, sans-serif;'>
                <strong style='color:#c00;'>$error_type:</strong> $errstr<br>
                <small style='color:#666;'>File: $errfile (Line: $errline)</small>
              </div>";
    }
    
    return true;
}

function customExceptionHandler($exception) {
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=utf-8');
    }
    
    if (DEBUG_MODE) {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>System Error - LearnNG</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f8f9fa; }
                .error-container { max-width: 800px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc2626; margin-top: 0; }
                .error-details { background: #fef2f2; padding: 15px; border-radius: 5px; margin: 20px 0; }
                code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
                .trace { background: #f8fafc; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; }
                .btn { display: inline-block; padding: 10px 20px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .btn:hover { background: #3a56d4; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1>System Error</h1>
                <div class='error-details'>
                    <p><strong>Message:</strong> {$exception->getMessage()}</p>
                    <p><strong>File:</strong> <code>{$exception->getFile()}</code></p>
                    <p><strong>Line:</strong> <code>{$exception->getLine()}</code></p>
                </div>
                <h3>Stack Trace:</h3>
                <div class='trace'><pre>{$exception->getTraceAsString()}</pre></div>
                <a href='../index.php' class='btn'>Return to Home</a>
            </div>
        </body>
        </html>";
    } else {
        echo "<!DOCTYPE html>
        <html>
        <head>
            <title>Oops! Something went wrong - LearnNG</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f8f9fa; }
                .error-container { max-width: 600px; margin: 0 auto; }
                h1 { color: #dc2626; }
                p { color: #666; line-height: 1.6; }
                .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; 
                       background: #4361ee; color: white; text-decoration: none; 
                       border-radius: 6px; font-weight: 600; }
                .btn:hover { background: #3a56d4; }
                .logo { font-size: 24px; color: #4361ee; font-weight: bold; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <div class='logo'>LearnNG</div>
                <h1>Oops! Something went wrong</h1>
                <p>Our team has been notified. Please try again later.</p>
                <a href='../index.php' class='btn'>Return to Home</a>
            </div>
        </body>
        </html>";
    }
    
    exit;
}

// Set error handlers
set_error_handler('customErrorHandler', E_ALL);
set_exception_handler('customExceptionHandler');

// File validation
function validateFilePath($path, $allowedDir) {
    if (empty($path)) {
        return false;
    }
    
    $baseDir = realpath($allowedDir);
    if ($baseDir === false) {
        return false;
    }
    
    $fullPath = realpath($path);
    if ($fullPath === false) {
        return false;
    }
    
    // Check if path is within allowed directory
    if (strpos($fullPath, $baseDir) !== 0) {
        logSecurityEvent('Path traversal attempt: ' . $path, $_SERVER['REMOTE_ADDR']);
        return false;
    }
    
    return $fullPath;
}

// Helper functions
function time_ago($datetime, $full = false) {
    if (empty($datetime)) {
        return 'just now';
    }
    
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        
        $string = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];
        
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (empty($string)) {
            return 'just now';
        }
        
        if (!$full) {
            $string = array_slice($string, 0, 1);
        }
        
        return implode(', ', $string) . ' ago';
    } catch (Exception $e) {
        error_log("time_ago error: " . $e->getMessage());
        return 'recently';
    }
}

// Database query wrapper with error handling - FIXED VERSION
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Failed to prepare SQL statement: " . $sql);
        }
        
        // Check if params is an array
        if (!is_array($params)) {
            $params = [$params];
        }
        
        // Execute with parameters
        $result = $stmt->execute($params);
        
        if ($result === false) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Database query failed: " . ($errorInfo[2] ?? 'Unknown error'));
        }
        
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database PDO error: " . $e->getMessage() . " - SQL: " . $sql);
        throw new Exception("Database operation failed. Please try again.");
    } catch (Exception $e) {
        error_log("Database general error: " . $e->getMessage() . " - SQL: " . $sql);
        throw new Exception("Database operation failed. Please try again.");
    }
}

// Cached query wrapper - FIXED VERSION
function cachedQuery($pdo, $cache, $key, $sql, $params = [], $ttl = null) {
    try {
        // Try to get from cache first
        $cached = $cache->get($key);
        if ($cached !== null && $cached !== false) {
            return $cached;
        }
        
        // Execute query
        $stmt = executeQuery($pdo, $sql, $params);
        $result = $stmt->fetchAll();
        
        // Store in cache
        $cache->set($key, $result, $ttl);
        
        return $result;
    } catch (Exception $e) {
        // Log cache failure but continue
        error_log("Cache query failed for key $key: " . $e->getMessage());
        
        // Try direct query without cache
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $innerException) {
            error_log("Direct query also failed: " . $innerException->getMessage());
            return []; // Return empty array instead of throwing
        }
    }
}

// Output buffer to catch any stray output
ob_start();
?>