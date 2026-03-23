<?php
// admin/test-email.php - Test Email Configuration

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../vendor/autoload.php';

// Admin only
if (!is_admin()) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email'] ?? '');
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['email_test_error'] = "Please enter a valid email address";
        header("Location: " . BASE_URL . "pages/forgot-password.php#email-settings");
        exit;
    }
    
    try {
        // Get email settings from database
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_host'");
        $stmt->execute();
        $smtp_host = $stmt->fetchColumn() ?: 'smtp.gmail.com';
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_port'");
        $stmt->execute();
        $smtp_port = $stmt->fetchColumn() ?: 587;
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_user'");
        $stmt->execute();
        $smtp_user = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_pass'");
        $stmt->execute();
        $smtp_pass = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'smtp_encryption'");
        $stmt->execute();
        $smtp_encryption = $stmt->fetchColumn() ?: 'tls';
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'site_name'");
        $stmt->execute();
        $site_name = $stmt->fetchColumn() ?: SITE_NAME;
        
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
        $stmt->execute();
        $from_email = $stmt->fetchColumn() ?: 'noreply@muhamuktar.com';
        
        // Send test email
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = $smtp_encryption;
        $mail->Port       = $smtp_port;
        
        $mail->setFrom($from_email, $site_name);
        $mail->addAddress($test_email);
        
        $mail->isHTML(true);
        $mail->Subject = "Test Email from " . $site_name;
        $mail->Body    = "
        <h1>Email Configuration Test</h1>
        <p>This is a test email to confirm that your email settings are configured correctly.</p>
        <p>If you're receiving this, your SMTP settings are working!</p>
        <p><strong>Details:</strong></p>
        <ul>
            <li>Time: " . date('Y-m-d H:i:s') . "</li>
            <li>Server: " . $_SERVER['SERVER_NAME'] . "</li>
        </ul>
        ";
        
        $mail->send();
        $_SESSION['email_test_success'] = "Test email sent successfully to $test_email";
        
    } catch (Exception $e) {
        $_SESSION['email_test_error'] = "Failed to send test email: " . $e->getMessage();
    }
    
    header("Location: " . BASE_URL . "pages/forgot-password.php#email-settings");
    exit;
} else {
    header("Location: " . BASE_URL . "pages/forgot-password.php");
    exit;
}