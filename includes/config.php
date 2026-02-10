<?php
// includes/config.php
define('SITE_NAME', 'Muhamuktar Global V.');
define('BASE_URL', 'http://localhost:8081/'); // change to your domain later

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'muhamuktar');
define('DB_USER', 'root');           // ← change
define('DB_PASS', '');               // ← change

// Paystack (TEST keys first!)
define('PAYSTACK_PUBLIC_KEY', 'pk_test_xxxxxxxxxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY',  'sk_test_xxxxxxxxxxxxxxxxxxxx');

// Session start + error reporting (dev only)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);        // remove in production