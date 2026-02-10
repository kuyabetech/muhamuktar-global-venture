<?php
// pages/deals.php - Active Deals & Promotions

$page_title = "Hot Deals & Offers";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Fetch active deals (current date between start & end)
$current_time = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT d.*, 
           (SELECT COUNT(*) FROM deal_products dp WHERE dp.deal_id = d.id) AS product_count
    FROM deals d
    WHERE d.is_active = 1 
      AND d.start_date <= ? 
      AND d.end_date >= ?
    ORDER BY d.is_featured DESC, d.end_date ASC
");
$stmt->execute([$current_time, $current_time]);
$deals = $stmt->fetchAll();
?>

<main class="container" style="padding: 3rem 0;">

  <div style="text-align:center; margin-bottom:3rem;">
    <h1 style="font-size:3rem; margin-bottom:0.8rem; color:var(--primary);">🔥 Hot Deals & Offers</h1>
    <p style="font-size:1.3rem; color:#4b5563; max-width:700px; margin:0 auto;">
      Limited-time discounts, flash sales, and exclusive promotions — don't miss out!
    </p>
  </div>

  <?php if (empty($deals)): ?>
    <div style="text-align:center; padding:5rem 1rem; background:white; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
      <i class="fas fa-tags" style="font-size:6rem; color:#d1d5db; margin-bottom:1.5rem;"></i>
      <h2 style="font-size:1.8rem; margin-bottom:1rem;">No active deals right now</h2>
      <p style="font-size:1.1rem; color:#6b7280;">Check back soon — new offers are added regularly!</p>
      <a href="<?= BASE_URL ?>pages/products.php" style="
        display:inline-block;
        margin-top:2rem;
        padding:1rem 2.5rem;
        background:var(--primary);
        color:white;
        border-radius:50px;
        text-decoration:none;
        font-weight:600;
        font-size:1.1rem;
      ">Browse All Products</a>
    </div>
  <?php else: ?>

    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:2rem;">
      <?php foreach ($deals as $deal): ?>
        <?php
          $now = time();
          $start = strtotime($deal['start_date']);
          $end   = strtotime($deal['end_date']);
          $time_left = $end - $now;
          $days_left = floor($time_left / 86400);
          $hours_left = floor(($time_left % 86400) / 3600);
        ?>

        <div style="
          background:white;
          border-radius:16px;
          overflow:hidden;
          box-shadow:0 10px 25px rgba(0,0,0,0.1);
          transition:transform 0.3s, box-shadow 0.3s;
          position:relative;
        " onmouseover="this.style.transform='translateY(-8px)'; this.style.boxShadow='0 20px 40px rgba(0,0,0,0.15)'">

          <?php if ($deal['is_featured']): ?>
            <div style="
              position:absolute;
              top:16px;
              left:16px;
              background:#ef4444;
              color:white;
              padding:0.4rem 1.2rem;
              border-radius:50px;
              font-weight:700;
              font-size:0.95rem;
              z-index:2;
            ">
              FEATURED DEAL
            </div>
          <?php endif; ?>

          <!-- Countdown -->
          <?php if ($time_left > 0): ?>
            <div style="
              position:absolute;
              top:16px;
              right:16px;
              background:rgba(0,0,0,0.7);
              color:white;
              padding:0.6rem 1rem;
              border-radius:12px;
              font-size:0.9rem;
              z-index:2;
              backdrop-filter:blur(4px);
            ">
              Ends in: 
              <?php if ($days_left > 0): ?>
                <strong><?= $days_left ?>d <?= $hours_left ?>h</strong>
              <?php else: ?>
                <strong><?= $hours_left ?>h left!</strong>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div style="padding:2rem; text-align:center; background:linear-gradient(135deg, #f3f4f6, #e5e7eb);">
            <h3 style="font-size:1.8rem; margin-bottom:1rem; color:var(--primary);">
              <?= htmlspecialchars($deal['title']) ?>
            </h3>

            <?php if ($deal['discount_type'] === 'percentage'): ?>
              <div style="font-size:3.5rem; font-weight:800; color:#ef4444; margin:1rem 0;">
                <?= $deal['discount_value'] ?>% OFF
              </div>
            <?php else: ?>
              <div style="font-size:3.5rem; font-weight:800; color:#ef4444; margin:1rem 0;">
                ₦<?= number_format($deal['discount_value']) ?> OFF
              </div>
            <?php endif; ?>

            <?php if ($deal['code']): ?>
              <div style="margin:1.5rem 0; font-size:1.3rem; color:#374151;">
                Use code: <strong style="background:#fefce8; padding:0.3rem 0.8rem; border-radius:8px;"><?= htmlspecialchars($deal['code']) ?></strong>
              </div>
            <?php endif; ?>

            <?php if ($deal['min_order_amount'] > 0): ?>
              <div style="font-size:0.95rem; color:#6b7280; margin-bottom:1rem;">
                Min. order: ₦<?= number_format($deal['min_order_amount']) ?>
              </div>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>pages/products.php<?= $deal['product_count'] > 0 ? '?deal=' . $deal['id'] : '' ?>" style="
              display:inline-block;
              padding:1rem 2.5rem;
              background:var(--primary);
              color:white;
              border-radius:50px;
              font-size:1.1rem;
              font-weight:600;
              text-decoration:none;
              transition:0.3s;
            " onmouseover="this.style.transform='scale(1.05)'">
              Shop Now →
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</main>

<?php require_once '../includes/footer.php'; ?>