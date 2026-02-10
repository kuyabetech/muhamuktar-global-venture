<?php
// pages/product.php?slug=example-product-slug

$page_title = "Product Detail";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get product by slug (example - secure it in production)
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: " . BASE_URL . "pages/products.php");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: " . BASE_URL . "pages/products.php");
        exit;
    }

    // Fetch related products (same category, exclude current)
    $relatedStmt = $pdo->prepare("
        SELECT id, name, slug, price, discount_price
        FROM products
        WHERE category_id = ? AND id != ? AND status = 'active'
        ORDER BY RAND()
        LIMIT 6
    ");
    $relatedStmt->execute([$product['category_id'], $product['id']]);
    $related = $relatedStmt->fetchAll();

} catch (Exception $e) {
    $product = null;
    $related = [];
}
?>

<main class="container" style="padding: 2rem 0;">

  <?php if (!$product): ?>
    <div style="text-align:center; padding:4rem 0; color:#6b7280;">
      <h2>Product not found</h2>
      <a href="<?= BASE_URL ?>pages/products.php">Back to shop</a>
    </div>
  <?php else: ?>

    <!-- Breadcrumb -->
    <nav style="margin-bottom:1.5rem; font-size:0.9rem; color:#6b7280;">
      <a href="<?= BASE_URL ?>" style="color:var(--primary);">Home</a> /
      <a href="<?= BASE_URL ?>pages/products.php" style="color:var(--primary);">Products</a> /
      <span><?= htmlspecialchars($product['name']) ?></span>
    </nav>

    <div style="
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2.5rem;
      margin-bottom: 3rem;
    " class="product-detail-grid">

      <!-- Left: Images -->
      <div>
        <div style="
          background:#f3f4f6;
          border-radius:12px;
          overflow:hidden;
          margin-bottom:1rem;
          height:460px;
          display:flex;
          align-items:center;
          justify-content:center;
        ">
          <!-- Main image (replace with real upload path) -->
          <img src="https://via.placeholder.com/600x600?text=Product+Image" alt="<?= htmlspecialchars($product['name']) ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
        </div>

        <!-- Thumbnails (placeholder - add real loop later) -->
        <div style="display:flex; gap:0.8rem; overflow-x:auto; padding-bottom:0.5rem;">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <div style="
              width:80px;
              height:80px;
              background:#f3f4f6;
              border-radius:8px;
              cursor:pointer;
              border:2px solid transparent;
              flex-shrink:0;
            " onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='transparent'">
              <img src="https://via.placeholder.com/80?text=Img+<?= $i ?>" alt="Thumbnail" style="width:100%; height:100%; object-fit:cover; border-radius:6px;">
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Right: Info -->
      <div>
        <h1 style="font-size:1.95rem; margin-bottom:0.8rem; line-height:1.3;">
          <?= htmlspecialchars($product['name']) ?>
        </h1>

        <div style="margin-bottom:1.2rem;">
          <span style="color:#6b7280;">Category:</span>
          <a href="#" style="color:var(--primary); font-weight:500;"><?= htmlspecialchars($product['category_name'] ?? 'General') ?></a>
        </div>

        <!-- Price -->
        <?php
          $final_price = $product['discount_price'] ?? $product['price'];
          $old_price   = $product['discount_price'] ? $product['price'] : null;
        ?>
        <div style="margin:1.5rem 0;">
          <span style="font-size:2.4rem; font-weight:700; color:#ef4444;">
            ₦<?= number_format($final_price) ?>
          </span>
          <?php if ($old_price): ?>
            <del style="font-size:1.3rem; color:#9ca3af; margin-left:1rem;">
              ₦<?= number_format($old_price) ?>
            </del>
            <span style="background:#fee2e2; color:#dc2626; padding:0.3rem 0.8rem; border-radius:6px; margin-left:0.8rem; font-weight:600;">
              <?= round((($old_price - $final_price) / $old_price) * 100) ?>% OFF
            </span>
          <?php endif; ?>
        </div>

        <!-- Rating -->
        <div style="margin:1.2rem 0; font-size:1.1rem;">
          <i class="fas fa-star" style="color:#fbbf24;"></i>
          <i class="fas fa-star" style="color:#fbbf24;"></i>
          <i class="fas fa-star" style="color:#fbbf24;"></i>
          <i class="fas fa-star" style="color:#fbbf24;"></i>
          <i class="fas fa-star-half-alt" style="color:#fbbf24;"></i>
          <span style="margin-left:0.6rem; color:#4b5563;">4.8 (1,234 reviews)</span>
        </div>

        <!-- Stock & Shipping -->
        <div style="margin:1.5rem 0; padding:1rem; background:#f0fdf4; border-radius:10px;">
          <strong style="color:var(--success);">In Stock (<?= $product['stock'] ?> items left)</strong><br>
          <small>Free shipping • Estimated delivery: 2–5 business days</small>
        </div>

        <!-- Quantity & Cart -->
        <div style="display:flex; align-items:center; gap:1rem; margin:2rem 0;">
          <div style="display:flex; align-items:center; border:1px solid var(--border); border-radius:8px; overflow:hidden;">
            <button style="width:48px; height:48px; background:#f3f4f6; border:none; font-size:1.3rem; cursor:pointer;">-</button>
            <input type="number" value="1" min="1" style="width:70px; text-align:center; border:none; font-size:1.1rem; padding:0.8rem 0;" readonly>
            <button style="width:48px; height:48px; background:#f3f4f6; border:none; font-size:1.3rem; cursor:pointer;">+</button>
          </div>

          <button style="
            flex:1;
            padding:1.1rem;
            background:var(--primary);
            color:white;
            border:none;
            border-radius:10px;
            font-size:1.1rem;
            font-weight:600;
            cursor:pointer;
            transition:0.25s;
          " onmouseover="this.style.background=var(--primary-dark)" onmouseout="this.style.background=var(--primary)">
            <i class="fas fa-cart-plus" style="margin-right:0.6rem;"></i> Add to Cart
          </button>
        </div>

        <!-- Tabs -->
        <div style="margin-top:2.5rem;">
          <div style="border-bottom:2px solid var(--border); display:flex; gap:2rem; margin-bottom:1.5rem;">
            <button style="padding:0.8rem 0; font-size:1.1rem; font-weight:600; border:none; background:none; border-bottom:3px solid var(--primary); color:var(--primary);">Description</button>
            <button style="padding:0.8rem 0; font-size:1.1rem; font-weight:600; border:none; background:none; color:var(--text-light);">Specifications</button>
            <button style="padding:0.8rem 0; font-size:1.1rem; font-weight:600; border:none; background:none; color:var(--text-light);">Reviews (1,234)</button>
          </div>

          <div style="line-height:1.8; color:#374151;">
            <p><?= nl2br(htmlspecialchars($product['description'] ?? 'No description available.')) ?></p>
            <ul style="margin-top:1rem; padding-left:1.5rem;">
              <li>High-quality materials</li>
              <li>Fast nationwide delivery</li>
              <li>Secure payment via Paystack</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
      <section style="margin-top:4rem;">
        <h2 style="font-size:2rem; margin-bottom:1.8rem; text-align:center;">You May Also Like</h2>

        <div style="
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
          gap: 1.5rem;
        ">
          <?php foreach ($related as $r): ?>
            <a href="<?= BASE_URL ?>pages/product.php?slug=<?= htmlspecialchars($r['slug']) ?>" style="
              background:white;
              border-radius:12px;
              overflow:hidden;
              text-decoration:none;
              box-shadow:0 2px 8px rgba(0,0,0,0.08);
              transition:all 0.22s;
            " onmouseover="this.style.transform='translateY(-6px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.12)'">
              <div style="height:220px; background:#f3f4f6; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-box-open" style="font-size:5rem; color:#d1d5db;"></i>
              </div>
              <div style="padding:1rem;">
                <h3 style="font-size:1.05rem; margin-bottom:0.5rem; height:2.8em; overflow:hidden;">
                  <?= htmlspecialchars($r['name']) ?>
                </h3>
                <div style="font-size:1.2rem; font-weight:700; color:#ef4444;">
                  ₦<?= number_format($r['discount_price'] ?? $r['price']) ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

  <?php endif; ?>

</main>

<?php require_once '../includes/footer.php'; ?>