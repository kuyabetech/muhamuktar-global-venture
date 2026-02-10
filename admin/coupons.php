<?php
// admin/deals.php - Manage Deals & Promotions

$page_title = "Manage Deals";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


require_admin();

// Handle form submission (add / edit deal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title             = trim($_POST['title'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $discount_type     = $_POST['discount_type'] ?? 'percentage';
    $discount_value    = (float)($_POST['discount_value'] ?? 0);
    $code              = trim($_POST['code'] ?? '');
    $start_date        = $_POST['start_date'] ?? '';
    $end_date          = $_POST['end_date'] ?? '';
    $min_order_amount  = (float)($_POST['min_order_amount'] ?? 0);
    $usage_limit       = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
    $is_active         = isset($_POST['is_active']) ? 1 : 0;
    $is_featured       = isset($_POST['is_featured']) ? 1 : 0;

    $errors = [];

    if (empty($title)) $errors[] = "Title is required";
    if ($discount_value <= 0) $errors[] = "Discount value must be greater than 0";
    if (strtotime($start_date) >= strtotime($end_date)) $errors[] = "End date must be after start date";

    if (empty($errors)) {
        try {
            if (isset($_POST['add'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO deals (
                        title, description, discount_type, discount_value, code,
                        start_date, end_date, min_order_amount, usage_limit,
                        is_active, is_featured
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $description, $discount_type, $discount_value, $code ?: null,
                    $start_date, $end_date, $min_order_amount, $usage_limit,
                    $is_active, $is_featured
                ]);
                $message = "Deal created successfully!";
            } elseif (isset($_POST['edit']) && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("
                    UPDATE deals SET 
                        title=?, description=?, discount_type=?, discount_value=?, code=?,
                        start_date=?, end_date=?, min_order_amount=?, usage_limit=?,
                        is_active=?, is_featured=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([
                    $title, $description, $discount_type, $discount_value, $code ?: null,
                    $start_date, $end_date, $min_order_amount, $usage_limit,
                    $is_active, $is_featured, $id
                ]);
                $message = "Deal updated successfully!";
            }
        } catch (Exception $e) {
            $errors[] = "Error saving deal: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM deals WHERE id = ?")->execute([$id]);
    $message = "Deal deleted.";
}

// Fetch all deals
$deals = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM deal_products dp WHERE dp.deal_id = d.id) AS product_count
    FROM deals d
    ORDER BY d.is_featured DESC, d.end_date DESC
")->fetchAll();
require_once 'header.php';
?>

<main style="padding:2rem;">

  <h1 style="font-size:2.5rem; margin-bottom:2rem;">
    <i class="fas fa-tags"></i> Manage Deals & Promotions
  </h1>

  <?php if (!empty($errors)): ?>
    <div style="background:#fee2e2; color:#991b1b; padding:1.2rem; border-radius:10px; margin-bottom:2rem;">
      <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($message)): ?>
    <div style="background:#d1fae5; color:#065f46; padding:1.2rem; border-radius:10px; margin-bottom:2rem;">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- Add / Edit Form -->
  <div style="background:white; padding:2rem; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,0.08); margin-bottom:3rem;">
    <h2 style="margin-bottom:1.8rem;">Create or Edit Deal</h2>

    <form method="post">
      <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.8rem;">
        <div style="grid-column:span 2;">
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Deal Title *</label>
          <input type="text" name="title" required style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div style="grid-column:span 2;">
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Description</label>
          <textarea name="description" rows="4" style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Discount Type</label>
          <select name="discount_type" style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Amount (₦)</option>
          </select>
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Discount Value *</label>
          <input type="number" name="discount_value" step="0.01" min="0" required style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Coupon Code (optional)</label>
          <input type="text" name="code" maxlength="20" style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px; text-transform:uppercase;">
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Start Date *</label>
          <input type="datetime-local" name="start_date" required style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">End Date *</label>
          <input type="datetime-local" name="end_date" required style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Minimum Order Amount (₦)</label>
          <input type="number" name="min_order_amount" min="0" step="100" value="0" style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div>
          <label style="display:block; margin-bottom:0.6rem; font-weight:600;">Usage Limit (optional)</label>
          <input type="number" name="usage_limit" min="1" placeholder="Unlimited" style="width:100%; padding:0.9rem; border:1px solid #d1d5db; border-radius:8px;">
        </div>

        <div style="display:flex; flex-direction:column; gap:1rem;">
          <label style="display:flex; align-items:center; gap:0.8rem;">
            <input type="checkbox" name="is_active" value="1" checked> Active
          </label>
          <label style="display:flex; align-items:center; gap:0.8rem;">
            <input type="checkbox" name="is_featured" value="1"> Featured on homepage
          </label>
        </div>
      </div>

      <button type="submit" name="add" style="
        margin-top:2rem;
        padding:1rem 2.5rem;
        background:var(--primary);
        color:white;
        border:none;
        border-radius:8px;
        font-size:1.1rem;
        font-weight:600;
        cursor:pointer;
      ">
        Create Deal
      </button>
    </form>
  </div>

  <!-- Active Deals List -->
  <div style="margin-top:3rem;">
    <h2 style="font-size:2rem; margin-bottom:1.5rem;">Current Deals</h2>

    <?php if (empty($deals)): ?>
      <div style="text-align:center; padding:4rem; background:white; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
        No active deals yet.
      </div>
    <?php else: ?>
      <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:2rem;">
        <?php foreach ($deals as $deal): ?>
          <div style="background:white; border-radius:12px; padding:1.8rem; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
            <h3 style="margin-bottom:1rem;"><?= htmlspecialchars($deal['title']) ?></h3>

            <?php if ($deal['code']): ?>
              <div style="margin-bottom:1rem;">
                Code: <strong style="background:#fefce8; padding:0.3rem 0.8rem; border-radius:6px;"><?= htmlspecialchars($deal['code']) ?></strong>
              </div>
            <?php endif; ?>

            <div style="margin-bottom:1rem;">
              Discount: <strong style="color:#ef4444;">
                <?= $deal['discount_type'] === 'percentage' ? $deal['discount_value'] . '%' : '₦' . number_format($deal['discount_value']) ?>
              </strong>
            </div>

            <div style="margin-bottom:1rem; color:#6b7280;">
              Valid: <?= date('M d, Y H:i', strtotime($deal['start_date'])) ?> — 
                     <?= date('M d, Y H:i', strtotime($deal['end_date'])) ?>
            </div>

            <div style="display:flex; gap:1rem; margin-top:1.5rem;">
              <a href="?edit=<?= $deal['id'] ?>" style="color:var(--primary); font-weight:600;">Edit</a>
              <a href="?delete=<?= $deal['id'] ?>" onclick="return confirm('Delete this deal?')" style="color:var(--danger); font-weight:600;">Delete</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</main>

