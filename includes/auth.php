<?php
// includes/auth.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function register_user($email, $password, $full_name = '', $phone = '') {
    global $pdo;

    if (empty($email) || empty($password)) {
        return ['error' => 'Email and password required'];
    }

    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['error' => 'Email already registered'];
    }

    // Use Argon2ID if available, otherwise fallback safely
    $algo = defined('PASSWORD_ARGON2ID')
        ? PASSWORD_ARGON2ID
        : PASSWORD_DEFAULT;

    $hash = password_hash($password, $algo);

    if ($hash === false) {
        return ['error' => 'Password hashing failed'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, full_name, phone, role)
        VALUES (?, ?, ?, ?, 'customer')
    ");
    $stmt->execute([$email, $hash, $full_name, $phone]);

    return [
        'success' => true,
        'user_id' => $pdo->lastInsertId()
    ];
}

function login_user($email, $password) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {

        // Optional: auto-rehash if algorithm changes
        if (password_needs_rehash(
            $user['password_hash'],
            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
        )) {
            $newHash = password_hash(
                $password,
                defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
            );
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$newHash, $user['id']]);
        }

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['role']       = $user['role'];

        return ['success' => true];
    }

    return ['error' => 'Invalid email or password'];
}

function logout_user() {
    $_SESSION = [];
    session_destroy();
    redirect(BASE_URL . 'index.php');
}