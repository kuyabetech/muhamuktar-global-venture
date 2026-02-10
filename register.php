<?php
$page_title = "Create Account";
require 'includes/config.php';
require 'includes/auth.php';

$errors = [];
$success = false;
$form = $_POST ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email           = trim($form['email'] ?? '');
    $full_name       = trim($form['full_name'] ?? '');
    $phone           = trim($form['phone'] ?? '');
    $password        = $form['password'] ?? '';
    $password_confirm = $form['password_confirm'] ?? '';
    $terms           = isset($form['terms']);

    if (empty($email))               $errors[] = "Email is required";
    if (empty($password))            $errors[] = "Password is required";
    if ($password !== $password_confirm) $errors[] = "Passwords do not match";
    if (strlen($password) < 8)       $errors[] = "Password must be at least 8 characters";
    if (!$terms)                     $errors[] = "You must accept the Terms of Service";

    if (empty($errors)) {
        $result = register_user($email, $password, $full_name, $phone);
        if (isset($result['error'])) {
            $errors[] = $result['error'];
        } else {
            login_user($email, $password);
            $success = true;
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
      --success: #059669;
      --text: #1f2937;
      --text-light: #4b5563;
      --text-lighter: #6b7280;
      --bg: #f8f9fc;
      --white: #ffffff;
      --border: #e5e7eb;
      --shadow: 0 10px 25px rgba(0,0,0,0.08);

      --fs-base: 1rem;
      --fs-lg: 1.25rem;
      --space-md: 1rem;
      --space-lg: 1.5rem;
      --space-xl: 2rem;
      --space-2xl: 3rem;
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
      max-width: 460px;
      margin: 4rem auto;
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
      color: var(--text);
      margin-bottom: 2rem;
      font-size: 1.8rem;
    }

    .error-box {
      background: #fee2e2;
      color: var(--danger);
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
    }

    .success-box {
      text-align: center;
      color: var(--success);
      font-size: 1.2rem;
      padding: 2rem 1rem;
      background: rgba(5,150,105,0.08);
      border-radius: 12px;
      margin: 2rem 0;
    }

    .form-group {
      position: relative;
      margin-bottom: 1.6rem;
    }

    .form-group input {
      width: 100%;
      padding: 1.35rem 1rem 0.75rem 1rem;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: var(--fs-base);
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
      right: 1rem;
      top: 50%;
      transform: translateY(-35%);
      cursor: pointer;
      color: var(--text-lighter);
    }

    .password-meter {
      height: 4px;
      margin-top: 0.4rem;
      border-radius: 2px;
      background: #e5e7eb;
      transition: width 0.4s ease;
    }

    .meter-weak   { width: 33%; background: var(--danger); }
    .meter-medium { width: 66%; background: var(--warning); }
    .meter-strong { width: 100%; background: var(--success); }

    .terms {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      margin: 1.5rem 0;
      font-size: 0.95rem;
      color: var(--text-light);
    }

    .terms a {
      color: var(--primary);
      text-decoration: none;
    }

    .terms a:hover { text-decoration: underline; }

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

    .login-link {
      text-align: center;
      margin-top: 1.8rem;
      color: var(--text-light);
    }

    .login-link a {
      color: var(--primary);
      font-weight: 600;
      text-decoration: none;
    }
  </style>
</head>
<body>

<div class="auth-wrapper">
  <div class="logo"><i class="fas fa-shopping-bag"></i> <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?></div>
  <h2>Create Your Account</h2>

  <?php if ($errors): ?>
    <div class="error-box"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="success-box">
      <i class="fas fa-check-circle fa-3x" style="margin-bottom:1rem;"></i><br>
      Account created successfully!<br>
      Redirecting in <span id="count">3</span> seconds...
    </div>
    <script>
      let c = 3;
      const el = document.getElementById('count');
      const t = setInterval(() => {
        c--; el.textContent = c;
        if (c <= 0) {
          clearInterval(t);
          window.location = "<?= BASE_URL ?>index.php";
        }
      }, 1000);
    </script>
  <?php else: ?>
    <form method="post" id="register-form">
      <div class="form-group">
        <input type="email" name="email" id="email" placeholder=" " required autocomplete="email" value="<?= htmlspecialchars($form['email']??'') ?>">
        <label for="email">Email address</label>
      </div>

      <div class="form-group">
        <input type="text" name="full_name" id="full_name" placeholder=" " autocomplete="name" value="<?= htmlspecialchars($form['full_name']??'') ?>">
        <label for="full_name">Full name</label>
      </div>

      <div class="form-group">
        <input type="tel" name="phone" id="phone" placeholder=" " autocomplete="tel" value="<?= htmlspecialchars($form['phone']??'') ?>">
        <label for="phone">Phone number (optional)</label>
      </div>

      <div class="form-group input-wrapper">
        <input type="password" name="password" id="password" placeholder=" " required minlength="8" autocomplete="new-password">
        <label for="password">Password</label>
        <i class="fas fa-eye toggle-password" id="togglePass1"></i>
        <div class="password-meter" id="meter"></div>
      </div>

      <div class="form-group input-wrapper">
        <input type="password" name="password_confirm" id="password_confirm" placeholder=" " required autocomplete="new-password">
        <label for="password_confirm">Confirm password</label>
        <i class="fas fa-eye toggle-password" id="togglePass2"></i>
      </div>

      <div class="terms">
        <input type="checkbox" name="terms" id="terms" required>
        <label for="terms">
          I agree to the <a href="#">Terms of Service</a> & <a href="#">Privacy Policy</a>
        </label>
      </div>

      <button type="submit">Create Account</button>
    </form>

    <div class="login-link">
      Already have an account? <a href="<?= BASE_URL ?>login.php">Sign in</a>
    </div>
  <?php endif; ?>
</div>

<script>
// Password visibility toggle
document.querySelectorAll('.toggle-password').forEach(el => {
  el.addEventListener('click', function() {
    const input = this.previousElementSibling.previousElementSibling;
    const type = input.type === 'password' ? 'text' : 'password';
    input.type = type;
    this.classList.toggle('fa-eye');
    this.classList.toggle('fa-eye-slash');
  });
});

// Password strength
const pw = document.getElementById('password');
const meter = document.getElementById('meter');

if (pw && meter) {
  pw.addEventListener('input', () => {
    const v = pw.value;
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;

    meter.className = 'password-meter';
    if (s <= 1) meter.classList.add('meter-weak');
    else if (s <= 2) meter.classList.add('meter-medium');
    else meter.classList.add('meter-strong');
  });
}
</script>

</body>
</html>