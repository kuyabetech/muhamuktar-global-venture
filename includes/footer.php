<?php
// includes/footer.php
// Place this at the bottom of your pages, after </main>

// Fetch footer settings from database
try {
    // Get site name
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'site_name'");
    $stmt->execute();
    $site_name = $stmt->fetchColumn();
    if (!$site_name) {
        $site_name = SITE_NAME ?? 'Muhamuktar Global Venture';
    }

    // Get contact information
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_email'");
    $stmt->execute();
    $contact_email = $stmt->fetchColumn() ?: 'support@muhamuktar.com';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_phone'");
    $stmt->execute();
    $contact_phone = $stmt->fetchColumn() ?: '+234 123 456 7890';

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'contact_address'");
    $stmt->execute();
    $contact_address = $stmt->fetchColumn() ?: '123 Main Street, Lagos, Nigeria';

    // Get social media links
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_facebook'");
    $stmt->execute();
    $social_facebook = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_twitter'");
    $stmt->execute();
    $social_twitter = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_instagram'");
    $stmt->execute();
    $social_instagram = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'social_whatsapp'");
    $stmt->execute();
    $social_whatsapp = $stmt->fetchColumn();

    // Get footer description
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'footer_description'");
    $stmt->execute();
    $footer_description = $stmt->fetchColumn();
    if (!$footer_description) {
        $footer_description = "Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.";
    }

    // Get current year
    $current_year = date('Y');

} catch (Exception $e) {
    // Fallback values if database query fails
    $site_name = SITE_NAME ?? 'Muhamuktar Global Venture';
    $contact_email = 'support@muhamuktar.com';
    $contact_phone = '+234 123 456 7890';
    $contact_address = '123 Main Street, Lagos, Nigeria';
    $social_facebook = '';
    $social_twitter = '';
    $social_instagram = '';
    $social_whatsapp = '';
    $footer_description = "Premium marketplace offering quality products with fast delivery and excellent customer service across Nigeria.";
    $current_year = date('Y');
}
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
        <?= htmlspecialchars($site_name) ?>
      </div>
      <p style="margin-bottom: 1.2rem; line-height: 1.7;">
        <?= htmlspecialchars($footer_description) ?>
      </p>
      <div style="display: flex; gap: 1rem; margin-top: 1rem;">
        <?php if (!empty($social_facebook)): ?>
          <a href="<?= htmlspecialchars($social_facebook) ?>" target="_blank" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fab fa-facebook-f"></i>
          </a>
        <?php endif; ?>
        
        <?php if (!empty($social_twitter)): ?>
          <a href="<?= htmlspecialchars($social_twitter) ?>" target="_blank" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fab fa-twitter"></i>
          </a>
        <?php endif; ?>
        
        <?php if (!empty($social_instagram)): ?>
          <a href="<?= htmlspecialchars($social_instagram) ?>" target="_blank" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fab fa-instagram"></i>
          </a>
        <?php endif; ?>
        
        <?php if (!empty($social_whatsapp)): ?>
          <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $social_whatsapp) ?>" target="_blank" style="color: #60a5fa; font-size: 1.4rem; transition: 0.2s;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fab fa-whatsapp"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Column 2: Quick Links -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Quick Links</h3>
      <ul style="list-style: none; line-height: 2;">
        <li><a href="<?= BASE_URL ?>pages/products.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Shop All Products</a></li>
        <li><a href="<?= BASE_URL ?>pages/categories.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Categories</a></li>
        <li><a href="<?= BASE_URL ?>pages/deals.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Deals & Promotions</a></li>
        <li><a href="<?= BASE_URL ?>pages/new-arrivals.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">New Arrivals</a></li>
        <li><a href="<?= BASE_URL ?>pages/best-sellers.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Best Sellers</a></li>
      </ul>
    </div>

    <!-- Column 3: Customer Service -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Customer Service</h3>
      <ul style="list-style: none; line-height: 2;">
        <li><a href="<?= BASE_URL ?>pages/track-order.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Track Your Order</a></li>
        <li><a href="<?= BASE_URL ?>pages/shipping.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Shipping Information</a></li>
        <li><a href="<?= BASE_URL ?>pages/returns.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Returns & Refunds</a></li>
        <li><a href="<?= BASE_URL ?>pages/faq.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">FAQ</a></li>
        <li><a href="<?= BASE_URL ?>pages/size-guide.php" style="color: #d1d5db; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">Size Guide</a></li>
      </ul>
    </div>

    <!-- Column 4: Contact & Newsletter -->
    <div>
      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin-bottom: 1.2rem;">Contact Us</h3>
      <ul style="list-style: none; line-height: 2;">
        <li>
          <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem; color: var(--primary);"></i> 
          <?= htmlspecialchars($contact_address) ?>
        </li>
        <li>
          <i class="fas fa-phone-alt" style="margin-right: 0.5rem; color: var(--primary);"></i> 
          <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contact_phone) ?>" style="color: #d1d5db; text-decoration: none;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">
            <?= htmlspecialchars($contact_phone) ?>
          </a>
        </li>
        <li>
          <i class="fas fa-envelope" style="margin-right: 0.5rem; color: var(--primary);"></i> 
          <a href="mailto:<?= htmlspecialchars($contact_email) ?>" style="color: #d1d5db; text-decoration: none;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#d1d5db'">
            <?= htmlspecialchars($contact_email) ?>
          </a>
        </li>
      </ul>

      <h3 style="color: white; font-size: 1.15rem; font-weight: 600; margin: 1.5rem 0 1rem;">Stay Updated</h3>
      <p style="margin-bottom: 1rem; font-size: 0.9rem;">Subscribe to get exclusive deals and updates.</p>

      <form id="newsletterForm" style="display: flex; flex-direction: column; gap: 0.8rem;">
        <input type="email" id="newsletterEmail" placeholder="Your email address" style="
          padding: 0.9rem 1.2rem;
          border: none;
          border-radius: 8px;
          background: #1f2937;
          color: white;
          font-size: 0.95rem;
          width: 100%;
        " required>
        <div id="newsletterMessage" style="font-size: 0.85rem; margin-top: 0.25rem;"></div>
        <button type="submit" style="
          padding: 0.9rem;
          background: var(--primary);
          color: white;
          border: none;
          border-radius: 8px;
          font-weight: 600;
          cursor: pointer;
          transition: 0.2s;
          width: 100%;
        " onmouseover="this.style.background='var(--primary-dark)'" onmouseout="this.style.background='var(--primary)'">
          Subscribe
        </button>
      </form>

      <p style="margin-top: 1rem; font-size: 0.8rem; color: #9ca3af;">
        By subscribing, you agree to our Privacy Policy and consent to receive updates.
      </p>
    </div>

  </div>

  <!-- Bottom bar with payment methods and legal links -->
  <div style="
    border-top: 1px solid #374151;
    margin-top: 3rem;
    padding: 1.5rem 0 0;
  ">
    <div class="container" style="
      max-width: 1320px;
      margin: 0 auto;
      padding: 0 1.5rem;
    ">
      <!-- Payment Methods -->
      <div style="
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
      ">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/visa/visa-original.svg" alt="Visa" style="height: 30px; filter: brightness(0) invert(0.8);">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/mastercard/mastercard-original.svg" alt="Mastercard" style="height: 30px; filter: brightness(0) invert(0.8);">
        <span style="color: #9ca3af; font-weight: 600; font-size: 1.2rem;">Paystack</span>
        <span style="color: #9ca3af; font-weight: 600; font-size: 1.2rem;">Flutterwave</span>
        <span style="color: #9ca3af; font-weight: 600; font-size: 1.2rem;">Bank Transfer</span>
        <span style="color: #9ca3af; font-weight: 600; font-size: 1.2rem;">Cash on Delivery</span>
      </div>

      <!-- Copyright and Legal Links -->
      <div style="
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        text-align: center;
        font-size: 0.9rem;
      ">
        <p>&copy; <?= $current_year ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
        <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; justify-content: center;">
          <a href="<?= BASE_URL ?>pages/privacy-policy.php" style="color: #9ca3af; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#9ca3af'">Privacy Policy</a>
          <a href="<?= BASE_URL ?>pages/terms-of-service.php" style="color: #9ca3af; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#9ca3af'">Terms of Service</a>
          <a href="<?= BASE_URL ?>pages/cookie-policy.php" style="color: #9ca3af; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#9ca3af'">Cookie Policy</a>
          <a href="<?= BASE_URL ?>pages/sitemap.php" style="color: #9ca3af; text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#93c5fd'" onmouseout="this.style.color='#9ca3af'">Sitemap</a>
        </div>
        <div style="color: #6b7280; font-size: 0.8rem; margin-top: 1rem;">
          <i class="fas fa-shield-alt"></i> Secure payments powered by Paystack
        </div>
      </div>
    </div>
  </div>
</footer>

<script>
// Newsletter subscription handler
document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const email = document.getElementById('newsletterEmail').value;
    const messageDiv = document.getElementById('newsletterMessage');
    
    // Validate email
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        messageDiv.innerHTML = '<span style="color: #ef4444;">Please enter a valid email address</span>';
        return;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Subscribing...';
    submitBtn.disabled = true;
    
    // Simulate API call (replace with actual AJAX request)
    setTimeout(() => {
        messageDiv.innerHTML = '<span style="color: #10b981;">✓ Thank you for subscribing!</span>';
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        document.getElementById('newsletterEmail').value = '';
        
        // Clear success message after 5 seconds
        setTimeout(() => {
            messageDiv.innerHTML = '';
        }, 5000);
    }, 1500);
    
    // Uncomment for actual AJAX request:
    /*
    fetch('<?= BASE_URL ?>api/newsletter-subscribe.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<span style="color: #10b981;">✓ Thank you for subscribing!</span>';
            document.getElementById('newsletterEmail').value = '';
        } else {
            messageDiv.innerHTML = '<span style="color: #ef4444;">✗ ' + data.message + '</span>';
        }
    })
    .catch(error => {
        messageDiv.innerHTML = '<span style="color: #ef4444;">✗ An error occurred. Please try again.</span>';
    })
    .finally(() => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    });
    */
});

// Smooth scroll to top
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Add scroll to top button functionality
const scrollTopBtn = document.createElement('button');
scrollTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
scrollTopBtn.style.cssText = `
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    transition: 0.3s;
    opacity: 0;
    visibility: hidden;
`;

scrollTopBtn.onmouseover = function() {
    this.style.background = 'var(--primary-dark)';
    this.style.transform = 'translateY(-3px)';
};

scrollTopBtn.onmouseout = function() {
    this.style.background = 'var(--primary)';
    this.style.transform = 'translateY(0)';
};

scrollTopBtn.onclick = scrollToTop;
document.body.appendChild(scrollTopBtn);

// Show/hide scroll to top button
window.addEventListener('scroll', function() {
    if (window.scrollY > 500) {
        scrollTopBtn.style.opacity = '1';
        scrollTopBtn.style.visibility = 'visible';
    } else {
        scrollTopBtn.style.opacity = '0';
        scrollTopBtn.style.visibility = 'hidden';
    }
});

// Track outbound links for analytics
document.querySelectorAll('footer a[href^="http"]:not([href*="' + window.location.hostname + '"])').forEach(link => {
    link.addEventListener('click', function(e) {
        console.log('Outbound link clicked:', this.href);
        // You can add analytics tracking here
    });
});

// Lazy load payment method images
if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                imageObserver.unobserve(img);
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach(img => {
        imageObserver.observe(img);
    });
}
</script>

<style>
/* Footer link hover effects */
footer a {
    position: relative;
    transition: color 0.2s ease;
}

footer a:not(.social-link):hover {
    color: #93c5fd !important;
    padding-left: 5px;
}

/* Social media icons */
footer .social-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(96, 165, 250, 0.1);
    transition: all 0.3s ease;
}

footer .social-link:hover {
    background: var(--primary);
    color: white !important;
    transform: translateY(-3px);
}

/* Newsletter input focus */
footer input[type="email"]:focus {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    footer {
        padding: 3rem 0 1.5rem;
    }
    
    .scroll-top-btn {
        bottom: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
    }
}

/* Payment method icons */
.payment-method {
    height: 30px;
    filter: brightness(0) invert(0.8);
    transition: filter 0.2s ease;
}

.payment-method:hover {
    filter: brightness(0) invert(1);
}

/* Dark mode adjustments */
[data-theme="dark"] footer {
    background: #0a0e17;
}

[data-theme="dark"] footer input[type="email"] {
    background: #1a1f2e;
}
</style>