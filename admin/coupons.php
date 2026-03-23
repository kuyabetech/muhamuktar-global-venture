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

<style>
/* Base Admin Styles */
.admin-main {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Responsive Typography */
h1 { font-size: clamp(1.8rem, 4vw, 2.5rem); }
h2 { font-size: clamp(1.3rem, 3vw, 2rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.3rem); }

/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #6b7280;
    flex-wrap: wrap;
}

.breadcrumb a {
    color: #4f46e5;
    text-decoration: none;
    transition: color 0.3s;
}

.breadcrumb a:hover {
    color: #4338ca;
    text-decoration: underline;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
    background: white;
    padding: 1.5rem 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.page-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Messages */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

@keyframes slideIn {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Form Card */
.form-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 3rem;
    border: 1px solid #e5e7eb;
}

.form-card h2 {
    margin-bottom: 1.8rem;
    color: #1f2937;
    font-weight: 700;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.full-width {
    grid-column: 1 / -1;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.form-control:hover {
    border-color: #9ca3af;
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Checkbox Group */
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1rem 0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #4f46e5;
}

.checkbox-item input[type="checkbox"]:hover {
    transform: scale(1.1);
}

/* Submit Button */
.submit-btn {
    margin-top: 2rem;
    padding: 1rem 2.5rem;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

/* Deals Grid */
.deals-section {
    margin-top: 3rem;
}

.deals-section h2 {
    font-size: 2rem;
    margin-bottom: 1.5rem;
    color: #1f2937;
    font-weight: 700;
}

.deals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}

.deal-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    transition: all 0.3s;
    position: relative;
    display: flex;
    flex-direction: column;
}

.deal-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    border-color: #4f46e5;
}

.deal-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
    border-radius: 16px 16px 0 0;
    opacity: 0;
    transition: opacity 0.3s;
}

.deal-card:hover::before {
    opacity: 1;
}

.deal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.deal-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.deal-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.deal-badge.featured {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
    color: white;
}

.deal-badge.active {
    background: #10b981;
    color: white;
}

.deal-badge.inactive {
    background: #ef4444;
    color: white;
}

.deal-code {
    margin-bottom: 1rem;
    background: #f3f4f6;
    padding: 0.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.deal-code strong {
    background: #fefce8;
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    font-family: monospace;
    font-size: 1.1rem;
    color: #92400e;
}

.deal-discount {
    margin-bottom: 1rem;
    font-size: 1.1rem;
    color: #1f2937;
}

.deal-discount strong {
    color: #ef4444;
    font-size: 1.3rem;
}

.deal-dates {
    margin-bottom: 1rem;
    color: #6b7280;
    font-size: 0.9rem;
    background: #f9fafb;
    padding: 0.75rem;
    border-radius: 8px;
}

.deal-dates i {
    margin-right: 0.5rem;
    color: #9ca3af;
}

.deal-stats {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.75rem 0;
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
}

.stat-item {
    flex: 1;
    text-align: center;
}

.stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1f2937;
}

.stat-label {
    font-size: 0.7rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.deal-actions {
    display: flex;
    gap: 1rem;
    margin-top: auto;
    padding-top: 1rem;
}

.deal-actions a {
    flex: 1;
    text-align: center;
    padding: 0.6rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
}

.edit-btn {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
}

.edit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.delete-btn {
    background: #ef4444;
    color: white;
}

.delete-btn:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 5rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
}

.empty-icon {
    font-size: 4rem;
    color: #e5e7eb;
    margin-bottom: 1.5rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.empty-title {
    font-size: 1.3rem;
    margin-bottom: 0.75rem;
    color: #1f2937;
    font-weight: 700;
}

.empty-description {
    color: #6b7280;
    font-size: 1rem;
    max-width: 400px;
    margin: 0 auto;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .form-card {
        padding: 1.5rem;
    }
    
    .deals-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .admin-main {
        padding: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 1.25rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .header-actions a {
        flex: 1;
        text-align: center;
    }
    
    .form-card {
        padding: 1.25rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .full-width {
        grid-column: auto;
    }
    
    .checkbox-group {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .checkbox-item {
        flex: 1;
        min-width: 150px;
    }
    
    .submit-btn {
        width: 100%;
        justify-content: center;
    }
    
    .deals-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .deal-card {
        padding: 1.25rem;
    }
    
    .deal-stats {
        flex-wrap: wrap;
    }
    
    .stat-item {
        min-width: 80px;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .form-card h2 {
        font-size: 1.3rem;
    }
    
    .form-control {
        padding: 0.6rem 0.75rem;
    }
    
    .checkbox-group {
        flex-direction: column;
    }
    
    .checkbox-item {
        width: 100%;
    }
    
    .deal-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .deal-actions a {
        width: 100%;
    }
    
    .empty-icon {
        font-size: 3rem;
    }
    
    .empty-title {
        font-size: 1.1rem;
    }
    
    .empty-description {
        font-size: 0.9rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .page-header, .form-card, .deal-card, .empty-state {
        background: #1f2937;
        border-color: #374151;
    }
    
    .page-header h1 {
        color: #f3f4f6;
    }
    
    .form-card h2, .deals-section h2, .deal-title {
        color: #f3f4f6;
    }
    
    .form-group label {
        color: #e5e7eb;
    }
    
    .form-control {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }
    
    .form-control:focus {
        border-color: #818cf8;
    }
    
    .deal-code {
        background: #374151;
    }
    
    .deal-code strong {
        background: #92400e;
        color: #fef3c7;
    }
    
    .deal-dates {
        background: #374151;
        color: #9ca3af;
    }
    
    .deal-dates i {
        color: #6b7280;
    }
    
    .stat-value {
        color: #f3f4f6;
    }
    
    .stat-label {
        color: #9ca3af;
    }
    
    .deal-stats {
        border-color: #4b5563;
    }
}

/* Print Styles */
@media print {
    .submit-btn, .deal-actions, .header-actions {
        display: none !important;
    }
    
    .deal-card {
        box-shadow: none;
        border: 1px solid #000;
        page-break-inside: avoid;
    }
}
</style>

<main class="admin-main">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="<?= BASE_URL ?>admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <i class="fas fa-chevron-right"></i>
        <span>Manage Deals</span>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-tags"></i> Manage Deals & Promotions</h1>
        <div class="header-actions">
            <button onclick="document.querySelector('.form-card').scrollIntoView({behavior: 'smooth'})" class="filter-btn">
                <i class="fas fa-plus"></i> New Deal
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Error:</strong>
                <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="form-card">
        <h2>Create New Deal</h2>
        <form method="post">
            <div class="form-grid">
                <!-- Title -->
                <div class="form-group full-width">
                    <label>Deal Title *</label>
                    <input type="text" name="title" required class="form-control" placeholder="e.g., Summer Sale 2024">
                </div>

                <!-- Description -->
                <div class="form-group full-width">
                    <label>Description</label>
                    <textarea name="description" rows="4" class="form-control" placeholder="Describe the deal..."></textarea>
                </div>

                <!-- Discount Type -->
                <div class="form-group">
                    <label>Discount Type</label>
                    <select name="discount_type" class="form-control">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₦)</option>
                    </select>
                </div>

                <!-- Discount Value -->
                <div class="form-group">
                    <label>Discount Value *</label>
                    <input type="number" name="discount_value" step="0.01" min="0" required class="form-control" placeholder="0.00">
                </div>

                <!-- Coupon Code -->
                <div class="form-group">
                    <label>Coupon Code</label>
                    <input type="text" name="code" maxlength="20" class="form-control" placeholder="e.g., SUMMER20" style="text-transform:uppercase;">
                </div>

                <!-- Min Order Amount -->
                <div class="form-group">
                    <label>Minimum Order Amount (₦)</label>
                    <input type="number" name="min_order_amount" min="0" step="100" value="0" class="form-control" placeholder="0">
                </div>

                <!-- Usage Limit -->
                <div class="form-group">
                    <label>Usage Limit</label>
                    <input type="number" name="usage_limit" min="1" class="form-control" placeholder="Unlimited">
                </div>

                <!-- Start Date -->
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="datetime-local" name="start_date" required class="form-control">
                </div>

                <!-- End Date -->
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="datetime-local" name="end_date" required class="form-control">
                </div>

                <!-- Checkboxes -->
                <div class="form-group full-width">
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Active (deal is live)</span>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="is_featured" value="1">
                            <span>Featured on homepage</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" name="add" class="submit-btn">
                <i class="fas fa-plus-circle"></i> Create Deal
            </button>
        </form>
    </div>

    <!-- Active Deals List -->
    <div class="deals-section">
        <h2>Current Deals</h2>

        <?php if (empty($deals)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <h3 class="empty-title">No deals yet</h3>
                <p class="empty-description">Create your first promotional deal to start attracting customers!</p>
            </div>
        <?php else: ?>
            <div class="deals-grid">
                <?php foreach ($deals as $deal): ?>
                    <div class="deal-card">
                        <div class="deal-header">
                            <h3 class="deal-title"><?= htmlspecialchars($deal['title']) ?></h3>
                            <div class="deal-badges">
                                <?php if ($deal['is_featured']): ?>
                                    <span class="deal-badge featured">Featured</span>
                                <?php endif; ?>
                                <span class="deal-badge <?= $deal['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $deal['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($deal['description'])): ?>
                            <p style="color: #6b7280; margin-bottom: 1rem; font-size: 0.9rem;">
                                <?= htmlspecialchars(substr($deal['description'], 0, 100)) ?><?= strlen($deal['description']) > 100 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($deal['code']): ?>
                            <div class="deal-code">
                                <span>Coupon Code:</span>
                                <strong><?= htmlspecialchars($deal['code']) ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="deal-discount">
                            <strong>
                                <?= $deal['discount_type'] === 'percentage' ? $deal['discount_value'] . '%' : '₦' . number_format($deal['discount_value']) ?> OFF
                            </strong>
                        </div>

                        <div class="deal-dates">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('M d, Y H:i', strtotime($deal['start_date'])) ?> —
                            <?= date('M d, Y H:i', strtotime($deal['end_date'])) ?>
                        </div>

                        <div class="deal-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= $deal['product_count'] ?></div>
                                <div class="stat-label">Products</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= $deal['usage_limit'] ?? '∞' ?></div>
                                <div class="stat-label">Usage Limit</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">₦<?= number_format($deal['min_order_amount']) ?></div>
                                <div class="stat-label">Min Order</div>
                            </div>
                        </div>

                        <div class="deal-actions">
                            <a href="?edit=<?= $deal['id'] ?>" class="edit-btn">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?delete=<?= $deal['id'] ?>" onclick="return confirm('Are you sure you want to delete this deal?')" class="delete-btn">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto uppercase for coupon code
    const codeInput = document.querySelector('input[name="code"]');
    if (codeInput) {
        codeInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    }

    // Handle edit mode
    <?php if (isset($_GET['edit'])): 
        $id = (int)$_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ?");
        $stmt->execute([$id]);
        $edit_deal = $stmt->fetch();
        if ($edit_deal):
    ?>
        // Populate form with deal data
        document.querySelector('input[name="title"]').value = <?= json_encode($edit_deal['title']) ?>;
        document.querySelector('textarea[name="description"]').value = <?= json_encode($edit_deal['description']) ?>;
        document.querySelector('select[name="discount_type"]').value = <?= json_encode($edit_deal['discount_type']) ?>;
        document.querySelector('input[name="discount_value"]').value = <?= $edit_deal['discount_value'] ?>;
        document.querySelector('input[name="code"]').value = <?= json_encode($edit_deal['code']) ?>;
        document.querySelector('input[name="min_order_amount"]').value = <?= $edit_deal['min_order_amount'] ?>;
        document.querySelector('input[name="usage_limit"]').value = <?= $edit_deal['usage_limit'] ?? '' ?>;
        
        // Format dates for datetime-local
        if (document.querySelector('input[name="start_date"]')) {
            const startDate = new Date('<?= $edit_deal['start_date'] ?>');
            const startStr = startDate.toISOString().slice(0, 16);
            document.querySelector('input[name="start_date"]').value = startStr;
        }
        
        if (document.querySelector('input[name="end_date"]')) {
            const endDate = new Date('<?= $edit_deal['end_date'] ?>');
            const endStr = endDate.toISOString().slice(0, 16);
            document.querySelector('input[name="end_date"]').value = endStr;
        }
        
        document.querySelector('input[name="is_active"]').checked = <?= $edit_deal['is_active'] ? 'true' : 'false' ?>;
        document.querySelector('input[name="is_featured"]').checked = <?= $edit_deal['is_featured'] ? 'true' : 'false' ?>;
        
        // Change button to update
        const submitBtn = document.querySelector('button[name="add"]');
        submitBtn.name = 'edit';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Deal';
        
        // Add hidden input for ID
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = <?= $id ?>;
        document.querySelector('form').appendChild(idInput);
        
        // Scroll to form
        document.querySelector('.form-card').scrollIntoView({behavior: 'smooth'});
    <?php 
        endif;
    endif; 
    ?>

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = new Date(document.querySelector('input[name="start_date"]').value);
        const endDate = new Date(document.querySelector('input[name="end_date"]').value);
        
        if (startDate >= endDate) {
            e.preventDefault();
            alert('End date must be after start date');
        }
        
        const discountValue = parseFloat(document.querySelector('input[name="discount_value"]').value);
        if (discountValue <= 0) {
            e.preventDefault();
            alert('Discount value must be greater than 0');
        }
    });

    // Add keyboard shortcut for new deal (Ctrl+N)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            document.querySelector('.form-card').scrollIntoView({behavior: 'smooth'});
            document.querySelector('input[name="title"]').focus();
        }
    });
});
</script>