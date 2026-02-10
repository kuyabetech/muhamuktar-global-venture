<?php
// admin/reviews.php - Moderate Product Reviews

$page_title = "Manage Reviews";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Admin only
require_admin();

// Handle actions
$action = $_GET['action'] ?? '';
$review_id = (int)($_GET['id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) || isset($_POST['reject']) || isset($_POST['delete'])) {
        $new_status = '';
        if (isset($_POST['approve'])) $new_status = 'approved';
        elseif (isset($_POST['reject'])) $new_status = 'rejected';

        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE reviews SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $review_id]);
            $message = "Review has been " . $new_status . ".";
        } elseif (isset($_POST['delete'])) {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$review_id]);
            $message = "Review deleted successfully.";
        }
    } elseif (isset($_POST['reply'])) {
        $reply_text = trim($_POST['reply_text'] ?? '');
        if ($reply_text && $review_id > 0) {
            $stmt = $pdo->prepare("UPDATE reviews SET admin_reply = ?, replied_at = NOW() WHERE id = ?");
            $stmt->execute([$reply_text, $review_id]);
            $message = "Reply added successfully.";
        }
    }

    header("Location: " . BASE_URL . "admin/reviews.php?msg=" . urlencode($message));
    exit;
}

$msg = $_GET['msg'] ?? '';

// Filters
$status_filter = $_GET['status'] ?? '';
$product_id    = (int)($_GET['product'] ?? 0);
$search        = trim($_GET['search'] ?? '');

// Build query
$sql = "SELECT r.*, p.name AS product_name, u.full_name AS customer_name
        FROM reviews r
        LEFT JOIN products p ON r.product_id = p.id
        LEFT JOIN users u ON r.user_id = u.id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if ($product_id > 0) {
    $sql .= " AND r.product_id = ?";
    $params[] = $product_id;
}

if ($search) {
    $sql .= " AND (r.comment LIKE ? OR u.full_name LIKE ? OR p.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY r.created_at DESC";

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$countSql = "SELECT COUNT(*) FROM reviews r WHERE 1=1" . 
            (strpos($sql, "AND") !== false ? substr($sql, strpos($sql, "WHERE") + 5, strpos($sql, "ORDER") - strpos($sql, "WHERE") - 5) : "");
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

$total_pages = max(1, ceil($total / $per_page));

$sql .= " LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// For product filter dropdown
$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();
?>

<main style="margin-left:260px; padding:2rem;">

  <h1 style="font-size:2.3rem; margin-bottom:2rem;">Manage Reviews & Ratings</h1>

  <?php if ($msg): ?>
    <div style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:2rem;">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div style="background:white; padding:1.5rem; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:2rem; display:flex; gap:1.5rem; flex-wrap:wrap;">
    <div style="flex:1; min-width:300px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Search (comment / customer / product)</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
    </div>

    <div style="min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Status</label>
      <select name="status" onchange="window.location='?status='+this.value" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        <option value="">All</option>
        <option value="pending"   <?= $status_filter === 'pending'   ? 'selected' : '' ?>>Pending</option>
        <option value="approved"  <?= $status_filter === 'approved'  ? 'selected' : '' ?>>Approved</option>
        <option value="rejected"  <?= $status_filter === 'rejected'  ? 'selected' : '' ?>>Rejected</option>
      </select>
    </div>

    <div style="min-width:220px;">
      <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Product</label>
      <select name="product" onchange="window.location='?product='+this.value" style="width:100%; padding:0.9rem; border:1px solid var(--border); border-radius:8px;">
        <option value="">All Products</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $product_id == $p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="align-self:flex-end;">
      <button onclick="window.location='?'" style="padding:0.9rem 1.5rem; background:#6b7280; color:white; border:none; border-radius:8px; cursor:pointer;">
        Reset Filters
      </button>
    </div>
  </div>

  <!-- Reviews Table -->
  <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08);">

    <?php if (empty($reviews)): ?>
      <div style="padding:4rem; text-align:center; color:#6b7280;">
        No reviews found matching your filters.
      </div>
    <?php else: ?>
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr style="background:#f8f9fc;">
            <th style="padding:1.2rem; text-align:left;">Product</th>
            <th style="padding:1.2rem; text-align:left;">Customer</th>
            <th style="padding:1.2rem; text-align:center;">Rating</th>
            <th style="padding:1.2rem; text-align:left;">Comment</th>
            <th style="padding:1.2rem; text-align:center;">Status</th>
            <th style="padding:1.2rem; text-align:center;">Date</th>
            <th style="padding:1.2rem; text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reviews as $r): ?>
            <?php
              $stars = str_repeat('<i class="fas fa-star" style="color:#fbbf24;"></i>', $r['rating']) .
                       str_repeat('<i class="far fa-star" style="color:#fbbf24;"></i>', 5 - $r['rating']);
              $status_color = match($r['status']) {
                'pending'  => '#d97706',
                'approved' => '#059669',
                'rejected' => '#dc2626',
                default    => '#6b7280'
              };
            ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:1.2rem;">
                <?= htmlspecialchars($r['product_name'] ?? 'Deleted Product') ?>
              </td>
              <td style="padding:1.2rem;"><?= htmlspecialchars($r['customer_name'] ?? 'Guest') ?></td>
              <td style="padding:1.2rem; text-align:center;">
                <?= $stars ?><br>
                <small>(<?= $r['rating'] ?>/5)</small>
              </td>
              <td style="padding:1.2rem; max-width:300px;">
                <?= nl2br(htmlspecialchars(substr($r['comment'], 0, 150))) . (strlen($r['comment']) > 150 ? '...' : '') ?>
              </td>
              <td style="padding:1.2rem; text-align:center;">
                <span style="
                  background: <?= $status_color ?>;
                  color: white;
                  padding: 0.5rem 1rem;
                  border-radius: 999px;
                  font-size: 0.9rem;
                  font-weight: 600;
                ">
                  <?= ucfirst($r['status']) ?>
                </span>
              </td>
              <td style="padding:1.2rem; color:#6b7280; text-align:center;">
                <?= date('M d, Y', strtotime($r['created_at'])) ?>
              </td>
              <td style="padding:1.2rem; text-align:right;">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit" name="approve" style="background:var(--success); color:white; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer;">Approve</button>
                    <button type="submit" name="reject" style="background:var(--danger); color:white; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer; margin-left:0.5rem;">Reject</button>
                  </form>
                <?php endif; ?>

                <form method="post" style="display:inline; margin-left:0.8rem;">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" name="delete" onclick="return confirm('Delete this review?')" style="background:#991b1b; color:white; border:none; padding:0.5rem 1rem; border-radius:6px; cursor:pointer;">Delete</button>
                </form>

                <?php if ($r['admin_reply']): ?>
                  <div style="margin-top:0.8rem; font-size:0.9rem; color:#6b7280;">
                    Replied: <?= htmlspecialchars(substr($r['admin_reply'], 0, 50)) ?>...
                  </div>
                <?php endif; ?>
              </td>
            </tr>

            <!-- Reply form (collapsible) -->
            <?php if ($r['status'] === 'approved'): ?>
              <tr>
                <td colspan="7" style="padding:1rem; background:#f8f9fc;">
                  <form method="post">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <textarea name="reply_text" rows="2" placeholder="Write your reply..." style="width:100%; padding:0.8rem; border:1px solid var(--border); border-radius:8px;"></textarea>
                    <button type="submit" name="reply" style="
                      margin-top:0.8rem;
                      padding:0.6rem 1.2rem;
                      background:var(--primary);
                      color:white;
                      border:none;
                      border-radius:6px;
                      cursor:pointer;
                    ">Send Reply</button>
                  </form>
                </td>
              </tr>
            <?php endif; ?>

          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div style="padding:1.5rem; text-align:center;">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&product=<?= $product_id ?>&search=<?= urlencode($search) ?>" style="
              display:inline-block;
              padding:0.6rem 1rem;
              margin:0 0.3rem;
              background:<?= $page == $i ? 'var(--primary)' : '#f3f4f6' ?>;
              color:<?= $page == $i ? 'white' : 'inherit' ?>;
              border-radius:8px;
              text-decoration:none;
            ">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

</main>

<?php require_once '../includes/footer.php'; ?>