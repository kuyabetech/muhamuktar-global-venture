<?php
// includes/header.php
// Requires: is_logged_in(), is_admin(), BASE_URL, SITE_NAME, $_SESSION['user_name'] / 'user_email'
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title ?? SITE_NAME ?? 'Muhamuktar Global Venture') ?> | Premium Marketplace</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    :root {
      --primary: #1e40af;
      --primary-dark: #1e3a8a;
      --primary-light: #3b82f6;
      --danger: #dc2626;
      --success: #059669;
      --warning: #d97706;
      --text: #1f2937;
      --text-light: #4b5563;
      --text-lighter: #6b7280;
      --bg: #f8f9fc;
      --white: #ffffff;
      --border: #e5e7eb;
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
      --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);

      --font-base: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      --fs-base: 1rem;
      --fs-lg: 1.25rem;
      --fw-medium: 500;
      --fw-bold: 600;
      --fw-extrabold: 800;

      --space-xs: 0.5rem;
      --space-sm: 0.75rem;
      --space-md: 1rem;
      --space-lg: 1.5rem;
      --space-xl: 2rem;
      --space-2xl: 3rem;

      --transition: 0.25s ease;
      --radius: 8px;
      --radius-lg: 12px;
    }

    [data-theme="dark"] {
      --bg: #0f172a;
      --text: #e2e8f0;
      --text-light: #94a3b8;
      --text-lighter: #64748b;
      --white: #1e293b;
      --border: #334155;
      --shadow-md: 0 4px 12px rgba(0,0,0,0.25);
    }

    * { margin:0; padding:0; box-sizing:border-box; }
    body { 
      font-family:var(--font-base); 
      background:var(--bg); 
      color:var(--text); 
      line-height:1.6;
      transition: background-color var(--transition), color var(--transition);
    }

    .container { max-width:1320px; margin:0 auto; padding:0 var(--space-lg); }

    /* Top Announcement */
    .top-announcement {
      background: linear-gradient(90deg, var(--primary), var(--primary-light));
      color:white;
      text-align:center;
      padding:var(--space-sm) 0;
      font-size:0.92rem;
      font-weight:var(--fw-medium);
      position: relative;
      overflow: hidden;
    }

    .top-announcement::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
      100% { left: 100%; }
    }

    /* Header */
    header {
      background:var(--white);
      box-shadow:var(--shadow-md);
      position:sticky;
      top:0;
      z-index:1000;
      border-bottom:1px solid var(--border);
      transition: all var(--transition);
    }

    .header-inner {
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:var(--space-lg) 0;
      position: relative;
    }

    /* Logo */
    .logo {
      font-size:1.7rem;
      font-weight:var(--fw-extrabold);
      color:var(--primary);
      text-decoration:none;
      display:flex;
      align-items:center;
      gap:var(--space-sm);
      flex-shrink:0;
      transition: color var(--transition);
    }

    .logo:hover { 
      color:var(--primary-dark); 
      transform: translateY(-1px);
    }

    /* Search Form */
    .search-form {
      flex:1;
      max-width:480px;
      margin:0 var(--space-xl);
      position:relative;
    }

    .search-form input {
      width:100%;
      padding:var(--space-md) var(--space-lg) var(--space-md) var(--space-xl);
      border:2px solid var(--border);
      border-radius:50px;
      font-size:var(--fs-base);
      background:var(--white);
      transition: all var(--transition);
    }

    .search-form input:focus {
      outline:none;
      border-color:var(--primary-light);
      box-shadow:0 0 0 3px rgba(59,130,246,0.15);
      background:var(--white);
    }

    .search-icon {
      position:absolute;
      left:var(--space-lg);
      top:50%;
      transform:translateY(-50%);
      color:var(--text-lighter);
      pointer-events: none;
    }

    /* Header Actions */
    .header-actions {
      display:flex;
      align-items:center;
      gap:var(--space-lg);
    }

    .action-btn {
      font-size:1.35rem;
      color:var(--text-light);
      background:none;
      border:none;
      cursor:pointer;
      position:relative;
      transition: var(--transition);
      padding: var(--space-xs);
      border-radius: var(--radius);
    }

    .action-btn:hover { 
      color:var(--primary); 
      transform:translateY(-2px);
      background: rgba(59, 130, 246, 0.1);
    }

    .badge {
      position:absolute;
      top:0;
      right:0;
      background:var(--danger);
      color:white;
      font-size:0.65rem;
      min-width:18px;
      height:18px;
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:var(--fw-bold);
      border: 2px solid var(--white);
    }

    /* User Dropdown */
    .user-dropdown-wrapper {
      position:relative;
    }

    .user-dropdown-toggle {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: var(--fw-bold);
      cursor: pointer;
      transition: all var(--transition);
      font-size: 1.1rem;
      border: 3px solid rgba(255,255,255,0.9);
      box-shadow: var(--shadow-sm);
    }

    .user-dropdown-toggle:hover {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
      transform: scale(1.05);
    }

    .dropdown {
      position: absolute;
      right: 0;
      top: calc(100% + var(--space-sm));
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      min-width: 260px;
      z-index: 1100;
      display: none;
      padding: var(--space-sm) 0;
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity var(--transition), transform var(--transition);
    }

    .dropdown.active {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }

    .dropdown-header {
      padding: var(--space-md) var(--space-lg);
      border-bottom: 1px solid var(--border);
    }

    .dropdown-header .user-name {
      font-weight: var(--fw-bold);
      color: var(--text);
      margin-bottom: 2px;
    }

    .dropdown-header .user-email {
      font-size: 0.9rem;
      color: var(--text-light);
    }

    .dropdown-link {
      display: flex;
      align-items: center;
      gap: var(--space-md);
      padding: var(--space-md) var(--space-lg);
      text-decoration: none;
      color: var(--text);
      transition: all var(--transition);
    }

    .dropdown-link:hover {
      background: rgba(59, 130, 246, 0.08);
      color: var(--primary);
      padding-left: calc(var(--space-lg) + 4px);
    }

    .dropdown-link i {
      width: 20px;
      text-align: center;
      color: var(--text-light);
    }

    .dropdown-link:hover i {
      color: var(--primary);
    }

    .dropdown-divider {
      height: 1px;
      background: var(--border);
      margin: var(--space-sm) 0;
    }

    .dropdown-section-title {
      padding: var(--space-sm) var(--space-lg) var(--space-xs);
      font-size: 0.85rem;
      color: var(--text-lighter);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Currency & Theme */
    .currency-selector,
    .theme-toggle-wrapper {
      display:flex;
      align-items:center;
    }

    .currency-btn {
      display: flex;
      align-items: center;
      gap: var(--space-xs);
      background: none;
      border: 1px solid var(--border);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius);
      cursor: pointer;
      color: var(--text);
      transition: all var(--transition);
      font-weight: var(--fw-medium);
    }

    .currency-btn:hover {
      border-color: var(--primary-light);
      background: rgba(59, 130, 246, 0.05);
    }

    /* Mobile Menu Toggle */
    .menu-toggle {
      font-size:1.9rem;
      color:var(--text);
      background:none;
      border:none;
      cursor:pointer;
      padding: var(--space-xs);
      border-radius: var(--radius);
      transition: all var(--transition);
      display: none;
    }

    .menu-toggle:hover {
      background: rgba(59, 130, 246, 0.1);
      color: var(--primary);
    }

    /* Mobile Navigation */
    .mobile-nav {
      position: fixed;
      top: 0;
      left: -100%;
      width: 85%;
      max-width: 320px;
      height: 100vh;
      background: var(--white);
      box-shadow: var(--shadow-lg);
      z-index: 1200;
      transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }

    .mobile-nav.active {
      left: 0;
    }

    .overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1199;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s;
    }

    .overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .mobile-nav-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-xl) var(--space-lg);
      border-bottom: 1px solid var(--border);
    }

    .mobile-nav-logo {
      font-size: 1.4rem;
      font-weight: var(--fw-bold);
      color: var(--primary);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .mobile-close-btn {
      font-size: 1.5rem;
      background: none;
      border: none;
      color: var(--text);
      cursor: pointer;
      padding: var(--space-xs);
      border-radius: var(--radius);
      transition: all var(--transition);
    }

    .mobile-close-btn:hover {
      background: rgba(59, 130, 246, 0.1);
      color: var(--primary);
    }

    .mobile-search {
      padding: var(--space-lg);
      border-bottom: 1px solid var(--border);
    }

    .mobile-search-input {
      width: 100%;
      padding: var(--space-md) var(--space-md) var(--space-md) var(--space-2xl);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: var(--fs-base);
      background: var(--bg);
    }

    .mobile-search-icon {
      position: absolute;
      left: calc(var(--space-lg) + var(--space-md));
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-lighter);
    }

    .mobile-nav-menu {
      flex: 1;
      padding: var(--space-lg);
      overflow-y: auto;
    }

    .mobile-nav-link {
      display: flex;
      align-items: center;
      gap: var(--space-md);
      padding: var(--space-md) 0;
      text-decoration: none;
      color: var(--text);
      font-weight: var(--fw-medium);
      border-bottom: 1px solid var(--border);
      transition: all var(--transition);
    }

    .mobile-nav-link:last-child {
      border-bottom: none;
    }

    .mobile-nav-link:hover {
      color: var(--primary);
      padding-left: var(--space-xs);
    }

    .mobile-nav-link i {
      width: 24px;
      color: var(--text-light);
    }

    .mobile-nav-footer {
      padding: var(--space-lg);
      border-top: 1px solid var(--border);
      background: rgba(59, 130, 246, 0.05);
    }

    .mobile-actions {
      display: flex;
      gap: var(--space-sm);
      margin-top: var(--space-md);
    }

    /* Responsive Design */
    @media (min-width: 993px) {
      .menu-toggle { display: none; }
      .mobile-nav { display: none; }
      .overlay { display: none; }
    }

    @media (max-width: 992px) {
      .search-form,
      .header-actions,
      .theme-toggle-wrapper,
      .currency-selector {
        display: none !important;
      }

      .menu-toggle {
        display: block;
      }

      .header-inner {
        padding: var(--space-md) 0;
      }

      .logo {
        font-size: 1.5rem;
      }

      .logo i {
        font-size: 1.6rem;
      }
    }

    @media (min-width: 768px) and (max-width: 992px) {
      .search-form {
        max-width: 350px;
        margin: 0 var(--space-md);
      }
    }

    /* Join Now Button */
    .join-btn {
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      padding: 0.7rem 1.5rem;
      border-radius: var(--radius);
      font-weight: var(--fw-bold);
      text-decoration: none;
      transition: all var(--transition);
      border: none;
      cursor: pointer;
      white-space: nowrap;
    }

    .join-btn:hover {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
    }

    /* Mobile Join Button */
    .mobile-join-btn {
      display: block;
      width: 100%;
      text-align: center;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      padding: var(--space-md);
      border-radius: var(--radius);
      font-weight: var(--fw-bold);
      text-decoration: none;
      margin-top: var(--space-md);
      transition: all var(--transition);
    }

    .mobile-join-btn:hover {
      background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--bg);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--text-light);
    }
  </style>
</head>
<body>

<!-- Top announcement bar -->
<div class="top-announcement">
  <div class="container">
    🚚 Free shipping on orders over ₦50,000 • <strong>WELCOME25</strong> for 10% off your first order!
  </div>
</div>

<header>
  <div class="container">
    <div class="header-inner">

      <!-- Logo -->
      <a href="<?= BASE_URL ?>" class="logo">
        <i class="fas fa-shopping-bag"></i>
        <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?>
      </a>

      <!-- Search Form (Desktop) -->
      <form class="search-form" action="<?= BASE_URL ?>pages/products.php" method="get">
        <i class="fas fa-search search-icon"></i>
        <input type="search" name="q" placeholder="Search products, brands, categories..." aria-label="Search" autocomplete="off">
      </form>

      <!-- Currency Selector (Desktop) -->
      <div class="currency-selector">
        <button class="currency-btn" title="Change currency">
          <i class="fas fa-money-bill-wave"></i>
          ₦ NGN <i class="fas fa-chevron-down" style="font-size:0.8rem;"></i>
        </button>
      </div>

      <!-- Theme Toggle (Desktop) -->
      <div class="theme-toggle-wrapper">
        <button id="theme-toggle" class="action-btn" aria-label="Toggle dark mode" title="Toggle theme">
          <i class="fas fa-moon"></i>
        </button>
      </div>

      <!-- Header Actions (Desktop) -->
      <div class="header-actions">
        <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
          <!-- Notifications -->
          <button class="action-btn" title="Notifications" id="notifications-btn">
            <i class="far fa-bell"></i>
            <span class="badge" id="notif-count">3</span>
          </button>

          <!-- Cart -->
          <a href="<?= BASE_URL ?>pages/cart.php" class="action-btn" title="Cart">
            <i class="fas fa-shopping-cart"></i>
            <span class="badge" id="cart-count"><?= $_SESSION['cart_count'] ?? 0 ?></span>
          </a>

          <!-- User Dropdown -->
          <div class="user-dropdown-wrapper">
            <div class="user-dropdown-toggle" id="user-toggle" title="My Account">
              <?= strtoupper(substr($_SESSION['user_name'] ?? ($_SESSION['user_email'] ?? 'U'), 0, 1)) ?>
            </div>

            <!-- Dropdown Content -->
            <div class="dropdown" id="user-dropdown">
              <div class="dropdown-header">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                <div class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
              </div>

              <a href="<?= BASE_URL ?>pages/profile.php" class="dropdown-link">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
              </a>

              <a href="<?= BASE_URL ?>pages/orders.php" class="dropdown-link">
                <i class="fas fa-shopping-bag"></i>
                <span>My Orders</span>
              </a>

              <a href="<?= BASE_URL ?>pages/wishlist.php" class="dropdown-link">
                <i class="fas fa-heart"></i>
                <span>Wishlist</span>
                <span style="margin-left: auto; background: var(--primary); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">12</span>
              </a>

              <a href="<?= BASE_URL ?>pages/settings.php" class="dropdown-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
              </a>

              <?php if (function_exists('is_admin') && is_admin()): ?>
                <div class="dropdown-divider"></div>
                <div class="dropdown-section-title">Admin Panel</div>
                
                <a href="<?= BASE_URL ?>admin/index.php" class="dropdown-link">
                  <i class="fas fa-chart-line"></i>
                  <span>Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>admin/products.php" class="dropdown-link">
                  <i class="fas fa-box"></i>
                  <span>Manage Products</span>
                </a>

                <a href="<?= BASE_URL ?>admin/orders.php" class="dropdown-link">
                  <i class="fas fa-clipboard-list"></i>
                  <span>View Orders</span>
                </a>

                <a href="<?= BASE_URL ?>admin/users.php" class="dropdown-link">
                  <i class="fas fa-users"></i>
                  <span>Manage Users</span>
                </a>
              <?php endif; ?>

              <div class="dropdown-divider"></div>
              <a href="<?= BASE_URL ?>logout.php" class="dropdown-link" style="color: var(--danger);">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>

        <?php else: ?>
          <!-- Guest User Actions -->
          <a href="<?= BASE_URL ?>login.php" class="action-btn" title="Sign In">
            <i class="fas fa-sign-in-alt"></i>
          </a>
          <a href="<?= BASE_URL ?>register.php" class="join-btn">Join Now</a>
        <?php endif; ?>
      </div>

      <!-- Mobile Menu Toggle -->
      <button class="menu-toggle" aria-label="Toggle menu" id="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
      </button>

    </div>
  </div>
</header>

<!-- Mobile Navigation -->
<div class="mobile-nav" id="mobile-nav">
  <div class="mobile-nav-header">
    <a href="<?= BASE_URL ?>" class="mobile-nav-logo">
      <i class="fas fa-shopping-bag"></i>
      <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?>
    </a>
    <button class="mobile-close-btn" id="mobile-close-btn">
      <i class="fas fa-times"></i>
    </button>
  </div>

  <!-- Mobile Search -->
  <div class="mobile-search">
    <form action="<?= BASE_URL ?>pages/products.php" method="get" style="position: relative;">
      <i class="fas fa-search mobile-search-icon"></i>
      <input type="search" name="q" placeholder="Search products..." class="mobile-search-input" autocomplete="off">
    </form>
  </div>

  <!-- Mobile Navigation Menu -->
  <div class="mobile-nav-menu">
    <a href="<?= BASE_URL ?>" class="mobile-nav-link">
      <i class="fas fa-home"></i>
      <span>Home</span>
    </a>

    <a href="<?= BASE_URL ?>pages/products.php" class="mobile-nav-link">
      <i class="fas fa-store"></i>
      <span>All Products</span>
    </a>

    <a href="<?= BASE_URL ?>pages/categories.php" class="mobile-nav-link">
      <i class="fas fa-list"></i>
      <span>Categories</span>
    </a>

    <a href="<?= BASE_URL ?>pages/deals.php" class="mobile-nav-link">
      <i class="fas fa-fire"></i>
      <span>Hot Deals</span>
      <span style="margin-left: auto; background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">SALE</span>
    </a>

    <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
      <div class="dropdown-divider" style="margin: var(--space-lg) 0;"></div>
      
      <div class="dropdown-section-title">My Account</div>
      
      <a href="<?= BASE_URL ?>pages/profile.php" class="mobile-nav-link">
        <i class="fas fa-user"></i>
        <span>Profile</span>
      </a>

      <a href="<?= BASE_URL ?>pages/orders.php" class="mobile-nav-link">
        <i class="fas fa-shopping-bag"></i>
        <span>Orders</span>
        <span id="mobile-orders-count" style="margin-left: auto; background: var(--primary); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">5</span>
      </a>

      <a href="<?= BASE_URL ?>pages/cart.php" class="mobile-nav-link">
        <i class="fas fa-shopping-cart"></i>
        <span>Cart</span>
        <span id="mobile-cart-count" style="margin-left: auto; background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?= $_SESSION['cart_count'] ?? 0 ?></span>
      </a>

      <a href="<?= BASE_URL ?>pages/wishlist.php" class="mobile-nav-link">
        <i class="fas fa-heart"></i>
        <span>Wishlist</span>
        <span style="margin-left: auto; background: var(--primary); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">12</span>
      </a>

      <?php if (function_exists('is_admin') && is_admin()): ?>
        <div class="dropdown-divider" style="margin: var(--space-lg) 0;"></div>
        <div class="dropdown-section-title">Admin</div>
        
        <a href="<?= BASE_URL ?>admin/index.php" class="mobile-nav-link">
          <i class="fas fa-chart-line"></i>
          <span>Dashboard</span>
        </a>

        <a href="<?= BASE_URL ?>admin/products.php" class="mobile-nav-link">
          <i class="fas fa-box"></i>
          <span>Products</span>
        </a>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <!-- Mobile Navigation Footer -->
  <div class="mobile-nav-footer">
    <?php if (function_exists('is_logged_in') && is_logged_in()): ?>
      <a href="<?= BASE_URL ?>logout.php" class="mobile-join-btn" style="background: var(--danger);">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>login.php" class="mobile-nav-link" style="border: none; padding: var(--space-md) 0; justify-content: center;">
        <i class="fas fa-sign-in-alt"></i>
        <span>Sign In</span>
      </a>
      <a href="<?= BASE_URL ?>register.php" class="mobile-join-btn">
        <i class="fas fa-user-plus"></i> Create Account
      </a>
    <?php endif; ?>
    
    <div class="mobile-actions">
      <button class="action-btn" style="flex: 1;" onclick="toggleTheme()">
        <i class="fas fa-moon"></i> Theme
      </button>
      <button class="currency-btn" style="flex: 1;">
        <i class="fas fa-money-bill-wave"></i> ₦ NGN
      </button>
    </div>
  </div>
</div>

<!-- Overlay -->
<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<script>
// Debug logging
console.log('Header script loading...');

// ====================
// DOM Ready Function
// ====================
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded, initializing header functionality...');
    
    // ====================
    // 1. THEME TOGGLE
    // ====================
    const themeToggle = document.getElementById('theme-toggle');
    console.log('Theme toggle element:', themeToggle);
    
    function toggleTheme() {
        console.log('toggleTheme called');
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const newTheme = isDark ? 'light' : 'dark';
        
        console.log('Current theme is dark?', isDark, 'Switching to:', newTheme);
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update desktop theme button icon
        const desktopIcon = document.querySelector('#theme-toggle i');
        if (desktopIcon) {
            console.log('Updating desktop theme icon');
            desktopIcon.classList.toggle('fa-moon', newTheme === 'light');
            desktopIcon.classList.toggle('fa-sun', newTheme === 'dark');
        }
        
        // Update mobile theme button icon
        const mobileIcon = document.querySelector('.mobile-actions .action-btn i');
        if (mobileIcon) {
            console.log('Updating mobile theme icon');
            mobileIcon.classList.toggle('fa-moon', newTheme === 'light');
            mobileIcon.classList.toggle('fa-sun', newTheme === 'dark');
        }
        
        console.log('Theme changed to:', newTheme);
    }
    
    // Initialize theme
    function initTheme() {
        console.log('Initializing theme...');
        const savedTheme = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        let initialTheme = 'light';
        if (savedTheme) {
            initialTheme = savedTheme;
            console.log('Using saved theme:', savedTheme);
        } else if (prefersDark) {
            initialTheme = 'dark';
            console.log('Using system preference: dark');
        }
        
        document.documentElement.setAttribute('data-theme', initialTheme);
        
        // Set correct icon based on initial theme
        const isDarkInitial = initialTheme === 'dark';
        const desktopIcon = document.querySelector('#theme-toggle i');
        const mobileIcon = document.querySelector('.mobile-actions .action-btn i');
        
        if (desktopIcon) {
            desktopIcon.classList.toggle('fa-moon', !isDarkInitial);
            desktopIcon.classList.toggle('fa-sun', isDarkInitial);
        }
        
        if (mobileIcon) {
            mobileIcon.classList.toggle('fa-moon', !isDarkInitial);
            mobileIcon.classList.toggle('fa-sun', isDarkInitial);
        }
        
        console.log('Theme initialized to:', initialTheme);
    }
    
    // Attach event listeners for theme toggle
    if (themeToggle) {
        console.log('Adding click listener to theme toggle');
        themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Also attach to mobile theme button
    const mobileThemeBtn = document.querySelector('.mobile-actions .action-btn');
    if (mobileThemeBtn) {
        console.log('Adding click listener to mobile theme button');
        mobileThemeBtn.addEventListener('click', toggleTheme);
    }
    
    // ====================
    // 2. USER DROPDOWN
    // ====================
    const userToggle = document.getElementById('user-toggle');
    const userDropdown = document.getElementById('user-dropdown');
    console.log('User dropdown elements:', { userToggle, userDropdown });
    
    if (userToggle && userDropdown) {
        console.log('Setting up user dropdown');
        
        userToggle.addEventListener('click', function(e) {
            console.log('User toggle clicked');
            e.preventDefault();
            e.stopPropagation();
            
            const isActive = userDropdown.classList.contains('active');
            console.log('Dropdown is active?', isActive);
            
            // Close all other dropdowns first
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                if (dropdown !== userDropdown) {
                    dropdown.classList.remove('active');
                }
            });
            
            // Toggle this dropdown
            userDropdown.classList.toggle('active');
            console.log('Dropdown toggled. New state:', userDropdown.classList.contains('active'));
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (userDropdown.classList.contains('active') && 
                !userDropdown.contains(e.target) && 
                !userToggle.contains(e.target)) {
                console.log('Click outside dropdown, closing');
                userDropdown.classList.remove('active');
            }
        });
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userDropdown.classList.contains('active')) {
                console.log('Escape pressed, closing dropdown');
                userDropdown.classList.remove('active');
            }
        });
    }
    
    // ====================
    // 3. MOBILE MENU
    // ====================
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileCloseBtn = document.getElementById('mobile-close-btn');
    const mobileNav = document.getElementById('mobile-nav');
    const overlay = document.getElementById('overlay');
    
    console.log('Mobile menu elements:', { 
        mobileMenuToggle, 
        mobileCloseBtn, 
        mobileNav, 
        overlay 
    });
    
    function openMobileMenu() {
        console.log('Opening mobile menu');
        if (mobileNav) mobileNav.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        console.log('Mobile menu opened');
    }
    
    function closeMobileMenu() {
        console.log('Closing mobile menu');
        if (mobileNav) mobileNav.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
        console.log('Mobile menu closed');
    }
    
    if (mobileMenuToggle) {
        console.log('Adding click listener to mobile menu toggle');
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            openMobileMenu();
        });
    }
    
    if (mobileCloseBtn) {
        console.log('Adding click listener to mobile close button');
        mobileCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            closeMobileMenu();
        });
    }
    
    if (overlay) {
        console.log('Adding click listener to overlay');
        overlay.addEventListener('click', function(e) {
            e.preventDefault();
            closeMobileMenu();
        });
    }
    
    // Close mobile menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileNav && mobileNav.classList.contains('active')) {
            console.log('Escape pressed, closing mobile menu');
            closeMobileMenu();
        }
    });
    
    // ====================
    // 4. CURRENCY SELECTOR
    // ====================
    const currencyBtns = document.querySelectorAll('.currency-btn');
    console.log('Currency buttons found:', currencyBtns.length);
    
    currencyBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Currency button clicked');
            // In a real app, you would show a currency selection modal here
            // For now, just show a simple alert
            alert('Currency Selection\n\nAvailable currencies:\n• ₦ NGN (Naira)\n• $ USD (US Dollar)\n• € EUR (Euro)\n• £ GBP (British Pound)\n\nIn a real application, this would open a modal for currency selection.');
        });
    });
    
    // ====================
    // 5. SEARCH FORM ENHANCEMENT
    // ====================
    const searchForms = document.querySelectorAll('form[action*="products.php"]');
    console.log('Search forms found:', searchForms.length);
    
    searchForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const input = form.querySelector('input[type="search"]');
            if (input && !input.value.trim()) {
                console.log('Empty search submitted, preventing form submission');
                e.preventDefault();
                input.focus();
                input.style.borderColor = 'var(--danger)';
                input.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
                
                setTimeout(() => {
                    input.style.borderColor = '';
                    input.style.boxShadow = '';
                }, 2000);
            } else {
                console.log('Search submitted with query:', input?.value);
            }
        });
    });
    
    // ====================
    // 6. CART & NOTIFICATION COUNTS
    // ====================
    function updateCartCount(count) {
        console.log('Updating cart count to:', count);
        
        const cartCountElements = document.querySelectorAll('#cart-count, #mobile-cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            // Show/hide badge based on count
            if (parseInt(count) > 0) {
                element.style.display = 'flex';
            } else {
                element.style.display = 'none';
            }
        });
        
        // Also update in localStorage for persistence
        localStorage.setItem('cartCount', count);
    }
    
    function updateNotificationCount(count) {
        console.log('Updating notification count to:', count);
        
        const notifElement = document.getElementById('notif-count');
        if (notifElement) {
            notifElement.textContent = count;
            if (parseInt(count) > 0) {
                notifElement.style.display = 'flex';
            } else {
                notifElement.style.display = 'none';
            }
        }
        
        localStorage.setItem('notifCount', count);
    }
    
    function updateOrdersCount(count) {
        console.log('Updating orders count to:', count);
        
        const ordersElement = document.getElementById('mobile-orders-count');
        if (ordersElement) {
            ordersElement.textContent = count;
        }
    }
    
    // Initialize counts from localStorage or session
    function initCounts() {
        console.log('Initializing counts...');
        
        // Get cart count from localStorage or session
        let cartCount = <?= json_encode($_SESSION['cart_count'] ?? 0) ?>;
        const savedCartCount = localStorage.getItem('cartCount');
        if (savedCartCount !== null) {
            cartCount = parseInt(savedCartCount);
        }
        updateCartCount(cartCount);
        
        // Get notification count
        let notifCount = localStorage.getItem('notifCount') || 3;
        updateNotificationCount(notifCount);
        
        // Get orders count (example)
        updateOrdersCount(5);
        
        console.log('Counts initialized:', { cartCount, notifCount });
    }
    
    // ====================
    // 7. EVENT LISTENERS FOR EXTERNAL UPDATES
    // ====================
    // Listen for custom events from other parts of the app
    window.addEventListener('cartUpdate', function(e) {
        console.log('Cart update event received:', e.detail);
        if (e.detail && e.detail.count !== undefined) {
            updateCartCount(e.detail.count);
        }
    });
    
    window.addEventListener('notificationUpdate', function(e) {
        console.log('Notification update event received:', e.detail);
        if (e.detail && e.detail.count !== undefined) {
            updateNotificationCount(e.detail.count);
        }
    });
    
    // ====================
    // 8. DEMO FUNCTIONALITY
    // ====================
    // Add demo buttons for testing (remove in production)
    function addDemoControls() {
        console.log('Adding demo controls');
        
        // Only add in development environment
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            const demoControls = document.createElement('div');
            demoControls.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--white);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: var(--space-sm);
                z-index: 9999;
                box-shadow: var(--shadow-lg);
                font-size: 12px;
                display: none; /* Hidden by default */
            `;
            
            demoControls.innerHTML = `
                <div style="margin-bottom: var(--space-xs); font-weight: bold;">Debug Controls</div>
                <button onclick="window.dispatchEvent(new CustomEvent('cartUpdate', {detail: {count: 5}}))" 
                        style="margin: 2px; padding: 4px 8px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Cart: 5
                </button>
                <button onclick="window.dispatchEvent(new CustomEvent('notificationUpdate', {detail: {count: 2}}))" 
                        style="margin: 2px; padding: 4px 8px; background: var(--warning); color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Notif: 2
                </button>
                <button onclick="toggleTheme()" 
                        style="margin: 2px; padding: 4px 8px; background: var(--success); color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Toggle Theme
                </button>
            `;
            
            document.body.appendChild(demoControls);
            
            // Show demo controls when holding Ctrl+Shift+D
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                    demoControls.style.display = demoControls.style.display === 'none' ? 'block' : 'none';
                }
            });
        }
    }
    
    // ====================
    // 9. INITIALIZATION
    // ====================
    console.log('Starting initialization...');
    
    // Initialize everything
    initTheme();
    initCounts();
    addDemoControls();
    
    // Make functions available globally for debugging
    window.toggleTheme = toggleTheme;
    window.updateCartCount = updateCartCount;
    window.updateNotificationCount = updateNotificationCount;
    
    console.log('Header initialization complete!');
    
    // ====================
    // 10. PERFORMANCE OPTIMIZATION
    // ====================
    // Debounce resize events
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // Close mobile menu on large screens
            if (window.innerWidth > 992 && mobileNav && mobileNav.classList.contains('active')) {
                closeMobileMenu();
            }
        }, 250);
    });
    
    // ====================
    // 11. ACCESSIBILITY
    // ====================
    // Add keyboard navigation for dropdowns
    document.addEventListener('keydown', function(e) {
        // Tab key navigation for dropdowns
        if (e.key === 'Tab' && userDropdown && userDropdown.classList.contains('active')) {
            const focusableElements = userDropdown.querySelectorAll('a, button, input, select, textarea');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];
            
            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    userToggle.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    userToggle.focus();
                }
            }
        }
    });
    
    // Focus trap for mobile menu
    if (mobileNav) {
        mobileNav.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && mobileNav.classList.contains('active')) {
                const focusableElements = mobileNav.querySelectorAll('a, button, input, select, textarea');
                const firstElement = focusableElements[0];
                const lastElement = focusableElements[focusableElements.length - 1];
                
                if (e.shiftKey) {
                    // Shift + Tab
                    if (document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    // Tab
                    if (document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
            
            // Close on Escape
            if (e.key === 'Escape') {
                closeMobileMenu();
                mobileMenuToggle.focus();
            }
        });
    }
});

// ====================
// ERROR HANDLING
// ====================
window.addEventListener('error', function(e) {
    console.error('JavaScript Error in header:', e.message, 'at', e.filename, 'line', e.lineno);
});

// Console log for debugging
console.log('Header script loaded successfully. Waiting for DOM...');

// Fallback initialization for browsers that might not fire DOMContentLoaded
if (document.readyState === 'loading') {
    console.log('Document still loading...');
} else {
    console.log('Document already loaded, scripts may run immediately.');
}
</script>

<main style="padding:var(--space-2xl) 0; min-height:70vh;">
<main style="padding:var(--space-2xl) 0; min-height:70vh;">