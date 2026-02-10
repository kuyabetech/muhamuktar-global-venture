<?php
// includes/footer.php
// Place this at the bottom of your pages, after </main>
?>

<footer style="
  background: #111827;
  color: #d1d5db;
  padding: 4rem 0 2rem;
  margin-top: auto;
  font-size: 0.95rem;
">
  <div class="container" style="
    max-width: 1320px;
    margin: 0 auto;
    padding: 0 1.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 2.5rem;
  ">

    <!-- Column 1: Brand & About -->
    <div>
      <div style="
        font-size: 1.6rem;
        font-weight: 700;
        color: white;
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
      ">
        <i class="fas fa-shopping-bag" style="color: var(--primary);"></i>
        <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar Global Venture') ?>
      </div>
      <p style="margin-bottom: 1.2rem; line-height: 1.7;">
        Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.
      </p>
      <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <a href="#" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
          <i class="fab fa-facebook-f"></i>
        </a>
        <a href="#" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
          <i class="fab fa-twitter"></i>
        </a>
        <a href="#" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="#" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
          <i class="fab fa-whatsapp"></i>
        </a>
      </div>
    </div>

    <!-- Column 2: Quick Links -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Quick Links</h3>
      <ul style="list-style: none; line-height: 2;">
        <li><a href="<?= BASE_URL ?>pages/products.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Shop All Products</a></li>
        <li><a href="<?= BASE_URL ?>pages/categories.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Categories</a></li>
        <li><a href="<?= BASE_URL ?>pages/deals.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Deals & Promotions</a></li>
        <li><a href="<?= BASE_URL ?>pages/track-order.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Track Your Order</a></li>
        <li><a href="<?= BASE_URL ?>pages/wishlist.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Wishlist</a></li>
      </ul>
    </div>

    <!-- Column 3: Support & Contact -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Support</h3>
      <ul style="list-style: none; line-height: 2;">
        <li><a href="<?= BASE_URL ?>pages/support.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Help Center</a></li>
        <li><a href="<?= BASE_URL ?>pages/returns.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Returns & Refunds</a></li>
        <li><a href="<?= BASE_URL ?>pages/shipping.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Shipping Info</a></li>
        <li><a href="<?= BASE_URL ?>pages/contact.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;">Contact Us</a></li>
        <li style="margin-top: 0.8rem;">
          <i class="fas fa-phone-alt" style="margin-right: 0.5rem;"></i> +234 123 456 7890
        </li>
        <li>
          <i class="fas fa-envelope" style="margin-right: 0.5rem;"></i> support@muhamuktar.com
        </li>
      </ul>
    </div>

    <!-- Column 4: Newsletter -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Stay Updated</h3>
      <p style="margin-bottom: 1rem;">Subscribe to get exclusive deals and updates.</p>

      <form style="display: flex; flex-direction: column; gap: 0.8rem;">
        <input type="email" placeholder="Your email address" style="
          padding: 0.9rem 1.2rem;
          border: none;
          border-radius: 8px;
          background: #1f2937;
          color: white;
          font-size: 0.95rem;
        " required>
        <button type="submit" style="
          padding: 0.9rem;
          background: var(--primary);
          color: white;
          border: none;
          border-radius: 8px;
          font-weight: 600;
          cursor: pointer;
          transition: 0.2s;
        ">Subscribe</button>
      </form>
    </div>

  </div>

  <!-- Bottom bar -->
  <div style="
    border-top: 1px solid #374151;
    margin-top: 3rem;
    padding-top: 1.5rem;
    text-align: center;
    font-size: 0.9rem;
  ">
    <div class="container">
      <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(SITE_NAME ?? 'Muhamuktar Global Venture') ?>. All rights reserved.</p>
      <p style="margin-top: 0.6rem;">
        <a href="#" style="color: #9ca3af; margin: 0 0.8rem; text-decoration: none;">Privacy Policy</a> •
        <a href="#" style="color: #9ca3af; margin: 0 0.8rem; text-decoration: none;">Terms of Service</a> •
        <a href="#" style="color: #9ca3af; margin: 0 0.8rem; text-decoration: none;">Cookie Policy</a>
      </p>
    </div>
  </div>
</footer>

<script>
// Optional: Add hover effects for links (if needed beyond CSS)
document.querySelectorAll('footer a').forEach(link => {
  link.addEventListener('mouseenter', () => {
    link.style.color = '#93c5fd';
  });
  link.addEventListener('mouseleave', () => {
    link.style.color = '';
  });
});
</script>