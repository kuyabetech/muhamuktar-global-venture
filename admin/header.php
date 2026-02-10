<?php
// includes/admin-header.php
// Requires: BASE_URL, SITE_NAME, is_admin(), $_SESSION

// Check if user is admin
if (!function_exists('is_admin') || !is_admin()) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Get current page for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar') ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        :root {
            --admin-primary: #4f46e5;
            --admin-primary-dark: #4338ca;
            --admin-primary-light: #6366f1;
            --admin-secondary: #10b981;
            --admin-danger: #ef4444;
            --admin-warning: #f59e0b;
            --admin-info: #3b82f6;
            --admin-dark: #1f2937;
            --admin-light: #f9fafb;
            --admin-gray: #9ca3af;
            --admin-border: #e5e7eb;
            --admin-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --admin-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            
            --sidebar-width: 260px;
            --header-height: 70px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--admin-light);
            color: var(--admin-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Admin Header */
        .admin-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: white;
            box-shadow: var(--admin-shadow);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            transition: var(--transition);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--admin-dark);
            cursor: pointer;
            display: none;
            transition: var(--transition);
        }

        .sidebar-toggle:hover {
            color: var(--admin-primary);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--admin-dark);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .notification-btn {
            position: relative;
            background: none;
            border: none;
            font-size: 1.25rem;
            color: var(--admin-gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .notification-btn:hover {
            color: var(--admin-primary);
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: var(--admin-danger);
            color: white;
            font-size: 0.75rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: var(--transition);
        }

        .admin-profile:hover {
            background: var(--admin-light);
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-weight: 600;
            color: var(--admin-dark);
        }

        .profile-role {
            font-size: 0.875rem;
            color: var(--admin-gray);
        }

        .profile-dropdown {
            position: absolute;
            top: calc(var(--header-height) - 0.5rem);
            right: 2rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--admin-shadow-lg);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: var(--transition);
            z-index: 1000;
        }

        .profile-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            text-decoration: none;
            color: var(--admin-dark);
            transition: var(--transition);
            border-bottom: 1px solid var(--admin-border);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: var(--admin-light);
            color: var(--admin-primary);
        }

        .dropdown-item i {
            width: 20px;
            color: var(--admin-gray);
        }

        .dropdown-item:hover i {
            color: var(--admin-primary);
        }

        /* Sidebar */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            border-right: 1px solid var(--admin-border);
            z-index: 200;
            transition: var(--transition);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--admin-border);
        }

        .admin-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--admin-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .admin-logo:hover {
            color: var(--admin-primary-dark);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--admin-gray);
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            text-decoration: none;
            color: var(--admin-dark);
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: var(--admin-light);
            color: var(--admin-primary);
            border-left-color: var(--admin-primary-light);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.1), transparent);
            color: var(--admin-primary);
            border-left-color: var(--admin-primary);
            font-weight: 600;
        }

        .nav-item i {
            width: 20px;
            color: var(--admin-gray);
        }

        .nav-item:hover i,
        .nav-item.active i {
            color: var(--admin-primary);
        }

        .badge {
            margin-left: auto;
            background: var(--admin-primary);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 999px;
            font-weight: 600;
        }

        /* Main Content */
        .admin-main {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 2rem;
            min-height: calc(100vh - var(--header-height));
            transition: var(--transition);
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-sidebar.active {
                transform: translateX(0);
            }

            .admin-header {
                left: 0;
            }

            .admin-main {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: block;
            }

            .overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 150;
                opacity: 0;
                visibility: hidden;
                transition: var(--transition);
            }

            .overlay.active {
                opacity: 1;
                visibility: visible;
            }
        }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--admin-shadow);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--admin-shadow-lg);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--admin-primary-light), var(--admin-primary));
            color: white;
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #34d399, var(--admin-secondary));
            color: white;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #fbbf24, var(--admin-warning));
            color: white;
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #f87171, var(--admin-danger));
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-dark);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--admin-gray);
            margin-top: 0.25rem;
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: var(--admin-secondary);
        }

        .stat-change.negative {
            color: var(--admin-danger);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: white;
            border: 1px solid var(--admin-border);
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--admin-dark);
            transition: var(--transition);
        }

        .action-btn:hover {
            background: var(--admin-primary);
            color: white;
            border-color: var(--admin-primary);
            transform: translateY(-2px);
            box-shadow: var(--admin-shadow-lg);
        }

        .action-btn:hover i {
            color: white;
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: linear-gradient(90deg, #d1fae5, #ecfdf5);
            border-left: 4px solid var(--admin-secondary);
            color: #065f46;
        }

        .alert-warning {
            background: linear-gradient(90deg, #fef3c7, #fffbeb);
            border-left: 4px solid var(--admin-warning);
            color: #92400e;
        }

        .alert-danger {
            background: linear-gradient(90deg, #fee2e2, #fef2f2);
            border-left: 4px solid var(--admin-danger);
            color: #991b1b;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--admin-gray);
        }

        .breadcrumb a {
            color: var(--admin-gray);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--admin-primary);
        }

        .breadcrumb i {
            font-size: 0.75rem;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--admin-gray);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--admin-primary);
        }
    </style>
</head>
<body>

<!-- Sidebar Overlay (Mobile) -->
<div class="overlay" id="sidebarOverlay"></div>

<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>admin/dashboard.php" class="admin-logo">
            <i class="fas fa-crown"></i>
            <?= htmlspecialchars(SITE_NAME ?? 'Admin') ?>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="section-title">Dashboard</div>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>Overview</span>
            </a>
            <a href="<?= BASE_URL ?>admin/analytics.php" class="nav-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Analytics</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="section-title">Store Management</div>
            <a href="<?= BASE_URL ?>admin/products.php" class="nav-item <?= $current_page === 'products.php' ? 'active' : '' ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
                <span class="badge" id="pending-products">0</span>
            </a>
            <a href="<?= BASE_URL ?>admin/categories.php" class="nav-item <?= $current_page === 'categories.php' ? 'active' : '' ?>">
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>
            <a href="<?= BASE_URL ?>admin/inventory.php" class="nav-item <?= $current_page === 'inventory.php' ? 'active' : '' ?>">
                <i class="fas fa-warehouse"></i>
                <span>Inventory</span>
            </a>
            <a href="<?= BASE_URL ?>admin/brands.php" class="nav-item <?= $current_page === 'brands.php' ? 'active' : '' ?>">
                <i class="fas fa-copyright"></i>
                <span>Brands</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="section-title">Sales</div>
            <a href="<?= BASE_URL ?>admin/orders.php" class="nav-item <?= $current_page === 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
                <span class="badge" id="pending-orders">0</span>
            </a>
            <a href="<?= BASE_URL ?>admin/transactions.php" class="nav-item <?= $current_page === 'transactions.php' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i>
                <span>Transactions</span>
            </a>
            <a href="<?= BASE_URL ?>admin/customers.php" class="nav-item <?= $current_page === 'customers.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
            <a href="<?= BASE_URL ?>admin/coupons.php" class="nav-item <?= $current_page === 'coupons.php' ? 'active' : '' ?>">
                <i class="fas fa-ticket-alt"></i>
                <span>Coupons</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="section-title">Content</div>
            <a href="<?= BASE_URL ?>admin/pages.php" class="nav-item <?= $current_page === 'pages.php' ? 'active' : '' ?>">
                <i class="fas fa-file"></i>
                <span>Pages</span>
            </a>
            <a href="<?= BASE_URL ?>admin/blog.php" class="nav-item <?= $current_page === 'blog.php' ? 'active' : '' ?>">
                <i class="fas fa-blog"></i>
                <span>Blog</span>
            </a>
            <a href="<?= BASE_URL ?>admin/media.php" class="nav-item <?= $current_page === 'media.php' ? 'active' : '' ?>">
                <i class="fas fa-photo-video"></i>
                <span>Media</span>
            </a>
            <a href="<?= BASE_URL ?>admin/testimonials.php" class="nav-item <?= $current_page === 'testimonials.php' ? 'active' : '' ?>">
                <i class="fas fa-comment-dots"></i>
                <span>Testimonials</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="section-title">Settings</div>
            <a href="<?= BASE_URL ?>admin/settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>General Settings</span>
            </a>
            <a href="<?= BASE_URL ?>admin/users.php" class="nav-item <?= $current_page === 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i>
                <span>Users & Permissions</span>
            </a>
            <a href="<?= BASE_URL ?>admin/shipping.php" class="nav-item <?= $current_page === 'shipping.php' ? 'active' : '' ?>">
                <i class="fas fa-shipping-fast"></i>
                <span>Shipping Zones</span>
            </a>
            <a href="<?= BASE_URL ?>admin/payment-methods.php" class="nav-item <?= $current_page === 'payment-methods.php' ? 'active' : '' ?>">
                <i class="fas fa-wallet"></i>
                <span>Payment Methods</span>
            </a>
        </div>

        <div class="nav-section">
            <div class="section-title">Tools</div>
            <a href="<?= BASE_URL ?>admin/backup.php" class="nav-item <?= $current_page === 'backup.php' ? 'active' : '' ?>">
                <i class="fas fa-database"></i>
                <span>Backup & Restore</span>
            </a>
            <a href="<?= BASE_URL ?>admin/logs.php" class="nav-item <?= $current_page === 'logs.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>System Logs</span>
            </a>
            <a href="<?= BASE_URL ?>admin/updates.php" class="nav-item <?= $current_page === 'updates.php' ? 'active' : '' ?>">
                <i class="fas fa-sync-alt"></i>
                <span>Updates</span>
            </a>
        </div>

        <div class="nav-section" style="margin-top: auto; padding-top: 1rem;">
            <a href="<?= BASE_URL ?>" class="nav-item" target="_blank">
                <i class="fas fa-external-link-alt"></i>
                <span>View Store</span>
            </a>
            <a href="<?= BASE_URL ?>logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>
</aside>

<!-- Admin Header -->
<header class="admin-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title" id="pageTitle">Dashboard</h1>
    </div>

    <div class="header-right">
        <button class="notification-btn" id="notificationBtn">
            <i class="far fa-bell"></i>
            <span class="notification-badge" id="notificationCount">3</span>
        </button>

        <div class="admin-profile" id="adminProfile">
            <div class="profile-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="profile-info">
                <div class="profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
                <div class="profile-role">Administrator</div>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>

        <div class="profile-dropdown" id="profileDropdown">
            <a href="<?= BASE_URL ?>admin/profile.php" class="dropdown-item">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php" class="dropdown-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="<?= BASE_URL ?>admin/activity.php" class="dropdown-item">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
            <div class="dropdown-item" style="border-top: 1px solid var(--admin-border); margin-top: 0.5rem; padding-top: 1rem;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </div>
        </div>
    </div>
</header>

<!-- Main Content Area -->
<main class="admin-main" id="adminMain">

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const adminMain = document.getElementById('adminMain');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    // Profile dropdown toggle
    const profileBtn = document.getElementById('adminProfile');
    const profileDropdown = document.getElementById('profileDropdown');

    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                profileDropdown.classList.remove('active');
            }
        });
    }

    // Notification bell
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            // In a real app, this would show notifications panel
            alert('Notifications panel would open here. This is a demo.');
        });
    }

    // Update page title dynamically
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        // You can set this dynamically based on current page
        const titles = {
            'dashboard.php': 'Dashboard Overview',
            'products.php': 'Manage Products',
            'orders.php': 'Order Management',
            'customers.php': 'Customer Management',
            'categories.php': 'Category Management',
            // Add more page titles as needed
        };
        
        const currentPage = '<?= $current_page ?>';
        if (titles[currentPage]) {
            pageTitle.textContent = titles[currentPage];
        }
    }

    // Fetch and update dashboard stats
    async function updateDashboardStats() {
        try {
            // Fetch pending orders count
            const response = await fetch('<?= BASE_URL ?>admin/api/stats.php?type=pending_orders');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('pending-orders').textContent = data.count;
            }
        } catch (error) {
            console.error('Error fetching stats:', error);
        }
    }

    // Update stats every 30 seconds
    updateDashboardStats();
    setInterval(updateDashboardStats, 30000);

    // Add active class to current page in navigation
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        if (item.href && currentPath.includes(item.getAttribute('href').split('/').pop())) {
            item.classList.add('active');
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl + B to toggle sidebar
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            toggleSidebar();
        }
        
        // Ctrl + N for notifications
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            notificationBtn.click();
        }
        
        // Esc to close dropdowns
        if (e.key === 'Escape') {
            profileDropdown.classList.remove('active');
        }
    });

    // Auto-hide sidebar on mobile when clicking a link
    if (window.innerWidth < 1024) {
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (sidebar.classList.contains('active')) {
                    toggleSidebar();
                }
            });
        });
    }

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Auto-close sidebar on resize to desktop if open
            if (window.innerWidth >= 1024 && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }, 250);
    });

    // Add breadcrumb functionality
    function updateBreadcrumb() {
        const pageTitleElement = document.getElementById('pageTitle');
        const pageTitleText = pageTitleElement ? pageTitleElement.textContent : 'Dashboard';
        
        // Remove existing breadcrumb
        const existingBreadcrumb = document.querySelector('.breadcrumb');
        if (existingBreadcrumb) existingBreadcrumb.remove();
        
        // Create new breadcrumb
        const breadcrumb = document.createElement('div');
        breadcrumb.className = 'breadcrumb';
        breadcrumb.innerHTML = `
            <a href="<?= BASE_URL ?>admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>${pageTitleText}</span>
        `;
        
        // Insert after page title
        if (adminMain && adminMain.firstChild) {
            adminMain.insertBefore(breadcrumb, adminMain.firstChild);
        }
    }

    // Call on page load
    updateBreadcrumb();
});

// Helper function to show toast notifications
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="toast-close"><i class="fas fa-times"></i></button>
    `;
    
    // Add styles
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        box-shadow: var(--admin-shadow-lg);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        border-left: 4px solid ${type === 'success' ? 'var(--admin-secondary)' : type === 'error' ? 'var(--admin-danger)' : 'var(--admin-warning)'};
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
    
    // Close button
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    });
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>