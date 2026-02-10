<?php
// admin/sidebar.php
// Include this in all admin pages after require_login() and require_admin()
?>

<aside style="
  width: 260px;
  background: #111827;
  color: white;
  min-height: calc(100vh - 70px);
  position: fixed;
  top: 70px;
  left: 0;
  padding: 1.5rem 0;
  overflow-y: auto;
  transition: width 0.3s;
">
  <div style="padding: 0 1.5rem; margin-bottom: 2rem;">
    <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--primary-light);">Admin Panel</h2>
  </div>

  <nav>
    <ul style="list-style: none;">
      <li>
        <a href="<?= BASE_URL ?>admin/" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-tachometer-alt"></i>
          Dashboard
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/products.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-box-open"></i>
          Products
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/orders.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-shopping-bag"></i>
          Orders
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/categories.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-tags"></i>
          Categories
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/customers.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-users"></i>
          Customers
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/analytics.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-chart-line"></i>
          Analytics
        </a>
      </li>
      <li>
        <a href="<?= BASE_URL ?>admin/settings.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: white;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(255,255,255,0.1)'">
          <i class="fas fa-cog"></i>
          Settings
        </a>
      </li>
      <li style="margin-top: 2rem; border-top: 1px solid #374151; padding-top: 1rem;">
        <a href="<?= BASE_URL ?>logout.php" style="
          display: flex;
          align-items: center;
          gap: 1rem;
          padding: 1rem 1.5rem;
          color: #dc2626;
          text-decoration: none;
          font-weight: 500;
          transition: 0.2s;
        " onmouseover="this.style.background = 'rgba(220,38,38,0.15)'">
          <i class="fas fa-sign-out-alt"></i>
          Logout
        </a>
      </li>
    </ul>
  </nav>
</aside>

<style>
  @media (max-width: 992px) {
    aside {
      width: 0;
      overflow: hidden;
      transition: width 0.3s;
    }
    aside.active {
      width: 260px;
    }
  }
</style>