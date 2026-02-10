<?php
$page_title = "Sign In";
require 'includes/config.php';
require 'includes/auth.php';

$errors = [];
$form = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($form['email'] ?? '');
    $password = $form['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required";
    } else {
        $result = login_user($email, $password);
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            // Redirect based on role
            $role = $_SESSION['role'] ?? 'customer';
            if ($role === 'admin') {
                header("Location: " . BASE_URL . "admin/index.php");
            } else {
                header("Location: " . BASE_URL . "index.php");
            }
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar Global Venture') ?></title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    :root {
      --primary: #1e40af;
      --primary-dark: #1e3a8a;
      --primary-light: #3b82f6;
      --danger: #dc2626;
      --text: #1f2937;
      --text-light: #4b5563;
      --text-lighter: #6b7280;
      --bg: #f8f9fc;
      --white: #ffffff;
      --border: #e5e7eb;
      --shadow: 0 10px 25px rgba(0,0,0,0.08);
    }

    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: system-ui, sans-serif;
      background: linear-gradient(135deg, var(--bg), #e0e7ff);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .auth-wrapper {
      max-width: 420px;
      margin: 5rem auto;
      background: var(--white);
      padding: 2.5rem 2rem;
      border-radius: 16px;
      box-shadow: var(--shadow);
    }

    .logo {
      text-align: center;
      font-size: 2.4rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 2rem;
    }

    h2 {
      text-align: center;
      margin-bottom: 2rem;
      color: var(--text);
    }

    .error-box {
      background: #fee2e2;
      color: var(--danger);
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
    }

    .form-group {
      position: relative;
      margin-bottom: 1.6rem;
    }

    .form-group input {
      width: 100%;
      padding: 1.35rem 3.5rem 0.75rem 1rem;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 1rem;
      background: #f9fafb;
      transition: 0.2s;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--primary-light);
      box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
      background: white;
    }

    .form-group label {
      position: absolute;
      top: 1.1rem;
      left: 1rem;
      color: var(--text-lighter);
      font-size: 0.95rem;
      pointer-events: none;
      transition: 0.25s ease;
    }

    .form-group input:not(:placeholder-shown) + label,
    .form-group input:focus + label {
      top: 0.45rem;
      left: 0.9rem;
      font-size: 0.78rem;
      color: var(--primary);
      background: white;
      padding: 0 4px;
    }

    .input-wrapper {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      right: 1.2rem;
      top: 50%;
      transform: translateY(-40%);
      cursor: pointer;
      color: var(--text-lighter);
    }

    button[type="submit"] {
      width: 100%;
      padding: 1.1rem;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 1.05rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.25s;
    }

    button[type="submit"]:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    .forgot {
      text-align: right;
      margin: 0.5rem 0 1.5rem;
      font-size: 0.92rem;
    }

    .forgot a {
      color: var(--primary);
      text-decoration: none;
    }

    .forgot a:hover { text-decoration: underline; }

    .register-link {
      text-align: center;
      margin-top: 1.8rem;
      color: var(--text-light);
    }

    .register-link a {
      color: var(--primary);
      font-weight: 600;
      text-decoration: none;
    }
  </style>
</head>
<body>

<div class="auth-wrapper">
  <div class="logo"><i class="fas fa-shopping-bag"></i> <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?></div>
  <h2>Sign In to Your Account</h2>

  <?php if ($errors): ?>
    <div class="error-box"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <input type="email" name="email" id="email" placeholder=" " required autocomplete="email" value="<?= htmlspecialchars($form['email']??'') ?>">
      <label for="email">Email address</label>
    </div>

    <div class="form-group input-wrapper">
      <input type="password" name="password" id="password" placeholder=" " required autocomplete="current-password">
      <label for="password">Password</label>
      <i class="fas fa-eye toggle-password" id="togglePass"></i>
    </div>

    <div class="forgot">
      <a href="#">Forgot password?</a>
    </div>

    <button type="submit">Sign In</button>
  </form>

  <div class="register-link">
    Don't have an account? <a href="<?= BASE_URL ?>register.php">Create one</a>
  </div>
</div>

<script>
// Password visibility
document.getElementById('togglePass')?.addEventListener('click', function() {
  const input = document.getElementById('password');
  const type = input.type === 'password' ? 'text' : 'password';
  input.type = type;
  this.classList.toggle('fa-eye');
  this.classList.toggle('fa-eye-slash');
});
</script>

</body>
</html>