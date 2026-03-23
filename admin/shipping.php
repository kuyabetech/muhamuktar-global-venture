<?php
// admin/shipping.php - Shipping Zones Management

$page_title = "Shipping Zones";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'header.php';

// Admin only
require_admin();

// Initialize database tables
try {
    // Shipping zones table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shipping_zones (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            countries TEXT,
            states TEXT,
            cities TEXT,
            postal_codes TEXT,
            status ENUM('active','inactive') DEFAULT 'active',
            priority INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_priority (priority)
        )
    ");

    // Shipping methods table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shipping_methods (
            id INT PRIMARY KEY AUTO_INCREMENT,
            zone_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) UNIQUE,
            type ENUM('flat','free','percentage','weight_based','price_based','pickup') DEFAULT 'flat',
            cost DECIMAL(10,2) DEFAULT 0,
            min_order DECIMAL(10,2) DEFAULT NULL,
            max_order DECIMAL(10,2) DEFAULT NULL,
            min_weight DECIMAL(10,2) DEFAULT NULL,
            max_weight DECIMAL(10,2) DEFAULT NULL,
            free_shipping_threshold DECIMAL(10,2) DEFAULT NULL,
            estimated_days_min INT DEFAULT NULL,
            estimated_days_max INT DEFAULT NULL,
            description TEXT,
            status ENUM('active','inactive') DEFAULT 'active',
            is_default TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_type (type),
            INDEX idx_is_default (is_default)
        )
    ");

    // Shipping rates table (for weight/price based calculations)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shipping_rates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            method_id INT NOT NULL,
            min_value DECIMAL(10,2) NOT NULL,
            max_value DECIMAL(10,2) NOT NULL,
            cost DECIMAL(10,2) NOT NULL,
            additional_item_cost DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (method_id) REFERENCES shipping_methods(id) ON DELETE CASCADE
        )
    ");
} catch (Exception $e) {
    error_log("Shipping tables error: " . $e->getMessage());
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle actions
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$method_id = (int)($_GET['method_id'] ?? 0);
$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';


// Handle shipping zone POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['zone_action'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $countries = isset($_POST['countries']) ? implode(',', $_POST['countries']) : '';
        $states = trim($_POST['states'] ?? '');
        $cities = trim($_POST['cities'] ?? '');
        $postal_codes = trim($_POST['postal_codes'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $priority = (int)($_POST['priority'] ?? 0);

        $errors = [];
        if (empty($name)) {
            $errors[] = "Zone name is required";
        }

        if (empty($errors)) {
            try {
                if ($_POST['zone_action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO shipping_zones (name, description, countries, states, cities, postal_codes, status, priority)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $countries, $states, $cities, $postal_codes, $status, $priority]);
                    $success_msg = "Shipping zone added successfully";
                    
                } elseif ($_POST['zone_action'] === 'edit' && $id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE shipping_zones SET 
                            name = ?, description = ?, countries = ?, states = ?, 
                            cities = ?, postal_codes = ?, status = ?, priority = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $countries, $states, $cities, $postal_codes, $status, $priority, $id]);
                    $success_msg = "Shipping zone updated successfully";
                }
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }

    // FIX: Use output buffering and JavaScript redirect instead of header()
    if (!empty($success_msg)) {
        echo "<script>window.location.href='shipping.php?success=" . urlencode($success_msg) . "';</script>";
        exit;
    } else if (!empty($error_msg)) {
        echo "<script>window.location.href='shipping.php?error=" . urlencode($error_msg) . "';</script>";
        exit;
    }
}
// Handle shipping method POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['method_action'])) {
    if (!hash_equals($csrf_token, $_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token";
    } else {
        $zone_id = (int)($_POST['zone_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $type = $_POST['type'] ?? 'flat';
        $cost = (float)($_POST['cost'] ?? 0);
        $min_order = !empty($_POST['min_order']) ? (float)$_POST['min_order'] : null;
        $max_order = !empty($_POST['max_order']) ? (float)$_POST['max_order'] : null;
        $min_weight = !empty($_POST['min_weight']) ? (float)$_POST['min_weight'] : null;
        $max_weight = !empty($_POST['max_weight']) ? (float)$_POST['max_weight'] : null;
        $free_shipping_threshold = !empty($_POST['free_shipping_threshold']) ? (float)$_POST['free_shipping_threshold'] : null;
        $estimated_days_min = !empty($_POST['estimated_days_min']) ? (int)$_POST['estimated_days_min'] : null;
        $estimated_days_max = !empty($_POST['estimated_days_max']) ? (int)$_POST['estimated_days_max'] : null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        $errors = [];
        if (empty($name)) {
            $errors[] = "Method name is required";
        }
        if ($zone_id <= 0) {
            $errors[] = "Invalid shipping zone";
        }

        // Generate unique code if not provided
        if (empty($code)) {
            $code = strtolower(str_replace(' ', '_', $name)) . '_' . uniqid();
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // If this is set as default, remove default from other methods in this zone
                if ($is_default) {
                    $stmt = $pdo->prepare("UPDATE shipping_methods SET is_default = 0 WHERE zone_id = ?");
                    $stmt->execute([$zone_id]);
                }

                if ($_POST['method_action'] === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO shipping_methods (
                            zone_id, name, code, type, cost, min_order, max_order, min_weight, max_weight,
                            free_shipping_threshold, estimated_days_min, estimated_days_max, description, status, is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $zone_id, $name, $code, $type, $cost, $min_order, $max_order, $min_weight, $max_weight,
                        $free_shipping_threshold, $estimated_days_min, $estimated_days_max, $description, $status, $is_default
                    ]);
                    
                    $method_id = $pdo->lastInsertId();
                    
                    // Handle tiered rates for percentage/weight_based/price_based methods
                    if (in_array($type, ['weight_based', 'price_based']) && isset($_POST['rates'])) {
                        foreach ($_POST['rates'] as $rate) {
                            if (!empty($rate['min']) && !empty($rate['max']) && isset($rate['cost'])) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO shipping_rates (method_id, min_value, max_value, cost, additional_item_cost)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $method_id, 
                                    (float)$rate['min'], 
                                    (float)$rate['max'], 
                                    (float)$rate['cost'],
                                    (float)($rate['additional'] ?? 0)
                                ]);
                            }
                        }
                    }
                    
                    $success_msg = "Shipping method added successfully";
                    
                } elseif ($_POST['method_action'] === 'edit' && $method_id > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE shipping_methods SET 
                            name = ?, code = ?, type = ?, cost = ?, min_order = ?, max_order = ?,
                            min_weight = ?, max_weight = ?, free_shipping_threshold = ?,
                            estimated_days_min = ?, estimated_days_max = ?, description = ?,
                            status = ?, is_default = ?
                        WHERE id = ? AND zone_id = ?
                    ");
                    $stmt->execute([
                        $name, $code, $type, $cost, $min_order, $max_order, $min_weight, $max_weight,
                        $free_shipping_threshold, $estimated_days_min, $estimated_days_max, $description,
                        $status, $is_default, $method_id, $zone_id
                    ]);
                    
                    // Update rates
                    if (in_array($type, ['weight_based', 'price_based']) && isset($_POST['rates'])) {
                        // Delete existing rates
                        $stmt = $pdo->prepare("DELETE FROM shipping_rates WHERE method_id = ?");
                        $stmt->execute([$method_id]);
                        
                        // Insert new rates
                        foreach ($_POST['rates'] as $rate) {
                            if (!empty($rate['min']) && !empty($rate['max']) && isset($rate['cost'])) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO shipping_rates (method_id, min_value, max_value, cost, additional_item_cost)
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $method_id, 
                                    (float)$rate['min'], 
                                    (float)$rate['max'], 
                                    (float)$rate['cost'],
                                    (float)($rate['additional'] ?? 0)
                                ]);
                            }
                        }
                    }
                    
                    $success_msg = "Shipping method updated successfully";
                }

                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $error_msg = implode("<br>", $errors);
        }
    }

    if (!empty($success_msg)) {
        header("Location: shipping.php?tab=methods&zone_id=$zone_id&success=" . urlencode($success_msg));
    } else {
        header("Location: shipping.php?tab=methods&zone_id=$zone_id&error=" . urlencode($error_msg));
    }
    exit;
}

// Handle delete zone
if ($action === 'delete_zone' && $id > 0) {
    try {
        // Check if zone has methods
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipping_methods WHERE zone_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $error_msg = "Cannot delete zone with shipping methods. Delete methods first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM shipping_zones WHERE id = ?");
            $stmt->execute([$id]);
            $success_msg = "Shipping zone deleted successfully";
        }
    } catch (Exception $e) {
        $error_msg = "Error deleting zone: " . $e->getMessage();
    }
    header("Location: shipping.php?success=" . urlencode($success_msg));
    exit;
}

// Handle delete method
if ($action === 'delete_method' && $method_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM shipping_methods WHERE id = ?");
        $stmt->execute([$method_id]);
        $success_msg = "Shipping method deleted successfully";
    } catch (Exception $e) {
        $error_msg = "Error deleting method: " . $e->getMessage();
    }
    header("Location: shipping.php?tab=methods&zone_id=" . ($_GET['zone_id'] ?? 0) . "&success=" . urlencode($success_msg));
    exit;
}

// Fetch all shipping zones
$zones = $pdo->query("
    SELECT z.*, 
           (SELECT COUNT(*) FROM shipping_methods WHERE zone_id = z.id) AS method_count
    FROM shipping_zones z
    ORDER BY z.priority ASC, z.name ASC
")->fetchAll();

// Get zone for editing
$edit_zone = null;
if ($action === 'edit_zone' && $id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM shipping_zones WHERE id = ?");
    $stmt->execute([$id]);
    $edit_zone = $stmt->fetch();
}

// Get method for editing
$edit_method = null;
if ($action === 'edit_method' && $method_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM shipping_methods WHERE id = ?");
    $stmt->execute([$method_id]);
    $edit_method = $stmt->fetch();
    
    if ($edit_method) {
        // Get rates
        $stmt = $pdo->prepare("SELECT * FROM shipping_rates WHERE method_id = ? ORDER BY min_value ASC");
        $stmt->execute([$method_id]);
        $edit_method['rates'] = $stmt->fetchAll();
    }
}

// Get current zone for method view
$current_zone_id = $_GET['zone_id'] ?? 0;
$current_tab = $_GET['tab'] ?? 'zones';

// Available countries list
$countries = [
    'NG' => 'Nigeria',
    'GH' => 'Ghana',
    'KE' => 'Kenya',
    'ZA' => 'South Africa',
    'US' => 'United States',
    'UK' => 'United Kingdom',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'DE' => 'Germany',
    'FR' => 'France',
    'IT' => 'Italy',
    'ES' => 'Spain',
    'CN' => 'China',
    'JP' => 'Japan',
    'IN' => 'India',
    'BR' => 'Brazil',
    'AE' => 'UAE',
    'SA' => 'Saudi Arabia',
];
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
h1 { font-size: clamp(1.8rem, 4vw, 2rem); }
h2 { font-size: clamp(1.3rem, 3vw, 1.5rem); }
h3 { font-size: clamp(1.1rem, 2.5vw, 1.25rem); }

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
    margin-bottom: 0.5rem;
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

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
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

/* Card Component */
.card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border: 1px solid #e5e7eb;
    margin-bottom: 1.5rem;
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

/* Form Elements */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
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
    border-radius: 12px;
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

.form-control:disabled {
    background: #f3f4f6;
    cursor: not-allowed;
}

select.form-control[multiple] {
    min-height: 200px;
}

small {
    display: block;
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    white-space: nowrap;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(75, 85, 99, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

/* Tabs */
.tabs-container {
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 2rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.tabs-scroll {
    display: flex;
    gap: 1rem;
    padding: 0 0.5rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
}

.tab-btn:hover {
    color: #4f46e5;
    border-bottom-color: #e5e7eb;
}

.tab-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
}

/* Status Badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-inactive {
    background: #f3f4f6;
    color: #374151;
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 12px;
    margin: 1rem 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

th {
    text-align: left;
    padding: 1rem;
    background: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    font-size: 0.85rem;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
}

td {
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
    color: #1f2937;
    font-size: 0.9rem;
    vertical-align: middle;
}

tr:hover {
    background: #f9fafb;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Zone Header */
.zone-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    background: #f9fafb;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.zone-info h3 {
    margin: 0 0 0.5rem;
    color: #1f2937;
}

.zone-info p {
    margin: 0;
    color: #6b7280;
}

/* Rate Row */
.rate-row {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.rate-row input {
    min-width: 100px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #e5e7eb;
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
    .admin-main {
        padding: 1.5rem;
    }
    
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
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
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .card {
        padding: 1.25rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
    }
    
    .zone-header {
        flex-direction: column;
        text-align: center;
    }
    
    .rate-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .rate-row input {
        width: 100%;
    }
    
    .rate-row .btn {
        width: auto;
    }
    
    .tabs-container {
        margin-bottom: 1.5rem;
    }
    
    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    .table-responsive {
        margin: 0 -1rem;
        width: calc(100% + 2rem);
        border-radius: 0;
    }
}

@media (max-width: 480px) {
    .admin-main {
        padding: 0.75rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .form-label {
        font-size: 0.9rem;
    }
    
    .form-control {
        padding: 0.6rem 0.75rem;
    }
    
    .btn-sm {
        width: auto;
    }
    
    .empty-state {
        padding: 2rem;
    }
    
    .empty-state i {
        font-size: 2rem;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .page-header, .card {
        background: #1f2937;
        border-color: #374151;
    }
    
    .form-label {
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
    
    .form-control:disabled {
        background: #4b5563;
    }
    
    table {
        background: #1f2937;
    }
    
    th {
        background: #374151;
        color: #e5e7eb;
    }
    
    td {
        color: #d1d5db;
    }
    
    tr:hover {
        background: #374151;
    }
    
    .zone-header {
        background: #374151;
    }
    
    .zone-info h3 {
        color: #f3f4f6;
    }
    
    .zone-info p {
        color: #9ca3af;
    }
    
    small {
        color: #9ca3af;
    }
    
    .tab-btn {
        color: #9ca3af;
    }
    
    .tab-btn:hover {
        color: #818cf8;
    }
    
    .tab-btn.active {
        color: #818cf8;
        border-bottom-color: #818cf8;
    }
}

/* Print Styles */
@media print {
    .btn, .action-buttons, .header-actions {
        display: none !important;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #000;
        page-break-inside: avoid;
    }
    
    table {
        border-collapse: collapse;
    }
    
    th, td {
        border: 1px solid #000;
    }
}
</style>

<div class="admin-main">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> Shipping Zones</h1>
            <p style="color: #6b7280; margin-top: 0.5rem;">Configure shipping zones, methods, and rates</p>
        </div>
        <div class="header-actions">
            <a href="?action=add_zone" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Shipping Zone
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg) ?>
        </div>
    <?php endif; ?>

    <!-- Main Tabs -->
    <div class="tabs-container">
        <div class="tabs-scroll">
            <a href="?tab=zones" class="tab-btn <?= $current_tab === 'zones' ? 'active' : '' ?>">
                <i class="fas fa-globe"></i> Shipping Zones
            </a>
            <a href="?tab=methods&zone_id=<?= $current_zone_id ?>" class="tab-btn <?= $current_tab === 'methods' ? 'active' : '' ?>">
                <i class="fas fa-truck"></i> Shipping Methods
            </a>
        </div>
    </div>

    <?php if ($current_tab === 'zones'): ?>
        <!-- Zones Management -->
        <?php if ($action === 'add_zone' || $edit_zone): ?>
            <!-- Add/Edit Zone Form -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;">
                    <i class="fas fa-<?= $edit_zone ? 'edit' : 'plus' ?>"></i>
                    <?= $edit_zone ? 'Edit Shipping Zone' : 'Add New Shipping Zone' ?>
                </h2>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="zone_action" value="<?= $edit_zone ? 'edit' : 'add' ?>">
                    <?php if ($edit_zone): ?>
                        <input type="hidden" name="id" value="<?= $edit_zone['id'] ?>">
                    <?php endif; ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Zone Name *</label>
                            <input type="text" name="name" 
                                   value="<?= htmlspecialchars($edit_zone['name'] ?? '') ?>" 
                                   required class="form-control" placeholder="e.g., Local, International">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <input type="number" name="priority" 
                                   value="<?= htmlspecialchars($edit_zone['priority'] ?? 0) ?>" 
                                   class="form-control" min="0">
                            <small>Lower numbers are checked first</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" 
                                  placeholder="Zone description"><?= htmlspecialchars($edit_zone['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Countries</label>
                        <select name="countries[]" multiple class="form-control" size="10">
                            <?php foreach ($countries as $code => $name): ?>
                                <option value="<?= $code ?>" 
                                    <?= $edit_zone && strpos($edit_zone['countries'] ?? '', $code) !== false ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Hold Ctrl to select multiple. Leave empty for "All Countries"</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">States (comma separated)</label>
                        <input type="text" name="states" 
                               value="<?= htmlspecialchars($edit_zone['states'] ?? '') ?>" 
                               class="form-control" placeholder="Lagos, Abuja, Rivers">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cities (comma separated)</label>
                        <input type="text" name="cities" 
                               value="<?= htmlspecialchars($edit_zone['cities'] ?? '') ?>" 
                               class="form-control" placeholder="Ikeja, Victoria Island">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Postal Codes (comma separated, wildcards allowed)</label>
                        <input type="text" name="postal_codes" 
                               value="<?= htmlspecialchars($edit_zone['postal_codes'] ?? '') ?>" 
                               class="form-control" placeholder="100001, 100002, 10*">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="active" <?= ($edit_zone['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($edit_zone['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= $edit_zone ? 'Update Zone' : 'Add Zone' ?>
                        </button>
                        <?php if ($edit_zone): ?>
                            <a href="shipping.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Zones List -->
            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Zone Name</th>
                                <th>Coverage</th>
                                <th>Methods</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($zones)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="fas fa-globe"></i>
                                        <p>No shipping zones configured</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($zones as $zone): ?>
                                    <tr>
                                        <td style="text-align: center;"><?= $zone['priority'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($zone['name']) ?></strong>
                                            <?php if (!empty($zone['description'])): ?>
                                                <div style="font-size: 0.8rem; color: #6b7280;">
                                                    <?= htmlspecialchars(substr($zone['description'], 0, 100)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $coverage = [];
                                            if (!empty($zone['countries'])) {
                                                $country_codes = explode(',', $zone['countries']);
                                                foreach (array_slice($country_codes, 0, 3) as $code) {
                                                    $coverage[] = $countries[trim($code)] ?? trim($code);
                                                }
                                                if (count($country_codes) > 3) {
                                                    $coverage[] = '...';
                                                }
                                            } else {
                                                $coverage[] = 'All Countries';
                                            }
                                            echo htmlspecialchars(implode(', ', $coverage));
                                            ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <a href="?tab=methods&zone_id=<?= $zone['id'] ?>" class="btn btn-secondary btn-sm">
                                                <?= $zone['method_count'] ?> Methods
                                            </a>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $zone['status'] ?>">
                                                <?= ucfirst($zone['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?action=edit_zone&id=<?= $zone['id'] ?>" 
                                                   class="btn btn-secondary btn-sm" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?tab=methods&zone_id=<?= $zone['id'] ?>" 
                                                   class="btn btn-secondary btn-sm" title="Methods">
                                                    <i class="fas fa-truck"></i>
                                                </a>
                                                <?php if ($zone['method_count'] == 0): ?>
                                                    <a href="?action=delete_zone&id=<?= $zone['id'] ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Delete zone <?= addslashes($zone['name']) ?>?')"
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($current_tab === 'methods'): ?>
        <!-- Methods Management -->
        <?php
        // Get current zone info
        $current_zone = null;
        if ($current_zone_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM shipping_zones WHERE id = ?");
            $stmt->execute([$current_zone_id]);
            $current_zone = $stmt->fetch();
        }

        // Get methods for current zone
        $methods = [];
        if ($current_zone_id > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM shipping_methods 
                WHERE zone_id = ? 
                ORDER BY is_default DESC, name ASC
            ");
            $stmt->execute([$current_zone_id]);
            $methods = $stmt->fetchAll();
        }
        ?>

        <div style="margin-bottom: 2rem;">
            <a href="?tab=zones" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Zones
            </a>
        </div>

        <?php if ($current_zone): ?>
            <!-- Zone Header -->
            <div class="zone-header">
                <div class="zone-info">
                    <h3><?= htmlspecialchars($current_zone['name']) ?></h3>
                    <p><?= htmlspecialchars($current_zone['description'] ?? 'No description') ?></p>
                </div>
                <a href="?action=add_method&zone_id=<?= $current_zone['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Shipping Method
                </a>
            </div>

            <?php if ($action === 'add_method' || $edit_method): ?>
                <!-- Add/Edit Method Form -->
                <div class="card">
                    <h2 style="margin-bottom: 1.5rem;">
                        <i class="fas fa-<?= $edit_method ? 'edit' : 'plus' ?>"></i>
                        <?= $edit_method ? 'Edit Shipping Method' : 'Add Shipping Method' ?>
                    </h2>

                    <form method="post" id="methodForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="method_action" value="<?= $edit_method ? 'edit' : 'add' ?>">
                        <input type="hidden" name="zone_id" value="<?= $current_zone['id'] ?>">
                        <?php if ($edit_method): ?>
                            <input type="hidden" name="method_id" value="<?= $edit_method['id'] ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Method Name *</label>
                                <input type="text" name="name" 
                                       value="<?= htmlspecialchars($edit_method['name'] ?? '') ?>" 
                                       required class="form-control" placeholder="e.g., Standard Shipping">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Method Code</label>
                                <input type="text" name="code" 
                                       value="<?= htmlspecialchars($edit_method['code'] ?? '') ?>" 
                                       class="form-control" placeholder="standard_shipping">
                                <small>Unique identifier (auto-generated if empty)</small>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-control" id="methodType" onchange="toggleRateFields()">
                                    <option value="flat" <?= ($edit_method['type'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat Rate</option>
                                    <option value="free" <?= ($edit_method['type'] ?? '') === 'free' ? 'selected' : '' ?>>Free Shipping</option>
                                    <option value="percentage" <?= ($edit_method['type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage of Order</option>
                                    <option value="weight_based" <?= ($edit_method['type'] ?? '') === 'weight_based' ? 'selected' : '' ?>>Weight Based</option>
                                    <option value="price_based" <?= ($edit_method['type'] ?? '') === 'price_based' ? 'selected' : '' ?>>Price Based</option>
                                    <option value="pickup" <?= ($edit_method['type'] ?? '') === 'pickup' ? 'selected' : '' ?>>Local Pickup</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Cost (₦)</label>
                                <input type="number" name="cost" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['cost'] ?? 0) ?>" 
                                       class="form-control" id="costField">
                                <small>For flat rate and base cost</small>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Min Order Amount</label>
                                <input type="number" name="min_order" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['min_order'] ?? '') ?>" 
                                       class="form-control" placeholder="Optional">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Max Order Amount</label>
                                <input type="number" name="max_order" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['max_order'] ?? '') ?>" 
                                       class="form-control" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Min Weight (kg)</label>
                                <input type="number" name="min_weight" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['min_weight'] ?? '') ?>" 
                                       class="form-control" placeholder="Optional">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Max Weight (kg)</label>
                                <input type="number" name="max_weight" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['max_weight'] ?? '') ?>" 
                                       class="form-control" placeholder="Optional">
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Free Shipping Threshold</label>
                                <input type="number" name="free_shipping_threshold" step="0.01" min="0" 
                                       value="<?= htmlspecialchars($edit_method['free_shipping_threshold'] ?? '') ?>" 
                                       class="form-control" placeholder="Optional">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Estimated Delivery (days)</label>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <input type="number" name="estimated_days_min" 
                                           value="<?= htmlspecialchars($edit_method['estimated_days_min'] ?? '') ?>" 
                                           class="form-control" placeholder="Min" style="flex: 1;">
                                    <span style="align-self: center;">to</span>
                                    <input type="number" name="estimated_days_max" 
                                           value="<?= htmlspecialchars($edit_method['estimated_days_max'] ?? '') ?>" 
                                           class="form-control" placeholder="Max" style="flex: 1;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control" 
                                      placeholder="Method description"><?= htmlspecialchars($edit_method['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Tiered Rates (for weight_based and price_based) -->
                        <div id="ratesContainer" style="<?= in_array($edit_method['type'] ?? '', ['weight_based', 'price_based']) ? '' : 'display: none;' ?>">
                            <h3 style="margin: 2rem 0 1rem;">
                                <?= ($edit_method['type'] ?? '') === 'weight_based' ? 'Weight' : 'Price' ?> Based Rates
                            </h3>
                            <div id="ratesList">
                                <?php if (!empty($edit_method['rates'])): ?>
                                    <?php foreach ($edit_method['rates'] as $index => $rate): ?>
                                        <div class="rate-row">
                                            <input type="number" name="rates[<?= $index ?>][min]" 
                                                   value="<?= $rate['min_value'] ?>" class="form-control" 
                                                   placeholder="Min">
                                            <input type="number" name="rates[<?= $index ?>][max]" 
                                                   value="<?= $rate['max_value'] ?>" class="form-control" 
                                                   placeholder="Max">
                                            <input type="number" name="rates[<?= $index ?>][cost]" 
                                                   value="<?= $rate['cost'] ?>" class="form-control" 
                                                   placeholder="Cost">
                                            <input type="number" name="rates[<?= $index ?>][additional]" 
                                                   value="<?= $rate['additional_item_cost'] ?>" class="form-control" 
                                                   placeholder="Additional per item">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.rate-row').remove()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="rate-row">
                                        <input type="number" name="rates[0][min]" class="form-control" placeholder="Min">
                                        <input type="number" name="rates[0][max]" class="form-control" placeholder="Max">
                                        <input type="number" name="rates[0][cost]" class="form-control" placeholder="Cost">
                                        <input type="number" name="rates[0][additional]" class="form-control" placeholder="Additional per item">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.rate-row').remove()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addRateRow()">
                                <i class="fas fa-plus"></i> Add Rate
                            </button>
                        </div>

                        <div class="form-grid" style="margin-top: 2rem;">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?= ($edit_method['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($edit_method['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-check-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="is_default" value="1" 
                                           <?= ($edit_method['is_default'] ?? 0) ? 'checked' : '' ?>>
                                    Set as Default Method
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= $edit_method ? 'Update Method' : 'Add Method' ?>
                            </button>
                            <a href="shipping.php?tab=methods&zone_id=<?= $current_zone['id'] ?>" class="btn btn-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Methods List -->
                <div class="card">
                    <?php if (empty($methods)): ?>
                        <div class="empty-state">
                            <i class="fas fa-truck"></i>
                            <h3>No shipping methods</h3>
                            <p>Add your first shipping method for this zone</p>
                            <a href="?action=add_method&zone_id=<?= $current_zone['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Method
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Cost</th>
                                        <th>Conditions</th>
                                        <th>Est. Delivery</th>
                                        <th>Status</th>
                                        <th>Default</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($methods as $method): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($method['name']) ?></strong>
                                                <?php if (!empty($method['description'])): ?>
                                                    <div style="font-size: 0.8rem; color: #6b7280;">
                                                        <?= htmlspecialchars(substr($method['description'], 0, 50)) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= ucfirst(str_replace('_', ' ', $method['type'])) ?></td>
                                            <td>
                                                <?php if ($method['type'] === 'free'): ?>
                                                    Free
                                                <?php elseif ($method['type'] === 'percentage'): ?>
                                                    <?= $method['cost'] ?>% of order
                                                <?php else: ?>
                                                    ₦<?= number_format($method['cost'], 2) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $conditions = [];
                                                if ($method['min_order']) $conditions[] = "Min order: ₦{$method['min_order']}";
                                                if ($method['max_order']) $conditions[] = "Max order: ₦{$method['max_order']}";
                                                if ($method['min_weight']) $conditions[] = "Min weight: {$method['min_weight']}kg";
                                                if ($method['max_weight']) $conditions[] = "Max weight: {$method['max_weight']}kg";
                                                if ($method['free_shipping_threshold']) $conditions[] = "Free above ₦{$method['free_shipping_threshold']}";
                                                echo empty($conditions) ? 'No conditions' : implode('<br>', $conditions);
                                                ?>
                                            </td>
                                            <td>
                                                <?= $method['estimated_days_min'] && $method['estimated_days_max'] ? 
                                                    "{$method['estimated_days_min']}-{$method['estimated_days_max']} days" : 
                                                    ($method['estimated_days_min'] ? "{$method['estimated_days_min']}+ days" : 'Not specified') ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $method['status'] ?>">
                                                    <?= ucfirst($method['status']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($method['is_default']): ?>
                                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?action=edit_method&method_id=<?= $method['id'] ?>&zone_id=<?= $current_zone['id'] ?>" 
                                                       class="btn btn-secondary btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?action=delete_method&method_id=<?= $method['id'] ?>&zone_id=<?= $current_zone['id'] ?>" 
                                                       class="btn btn-danger btn-sm" 
                                                       onclick="return confirm('Delete method <?= addslashes($method['name']) ?>?')"
                                                       title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <p>Please select a shipping zone to manage methods.</p>
                    <a href="?tab=zones" class="btn btn-primary">Go to Zones</a>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleRateFields() {
    const type = document.getElementById('methodType').value;
    const ratesContainer = document.getElementById('ratesContainer');
    const costField = document.getElementById('costField');
    
    if (type === 'weight_based' || type === 'price_based') {
        ratesContainer.style.display = 'block';
        costField.disabled = true;
    } else {
        ratesContainer.style.display = 'none';
        costField.disabled = false;
    }
}

function addRateRow() {
    const ratesList = document.getElementById('ratesList');
    const index = ratesList.children.length;
    
    const row = document.createElement('div');
    row.className = 'rate-row';
    row.innerHTML = `
        <input type="number" name="rates[${index}][min]" class="form-control" placeholder="Min">
        <input type="number" name="rates[${index}][max]" class="form-control" placeholder="Max">
        <input type="number" name="rates[${index}][cost]" class="form-control" placeholder="Cost">
        <input type="number" name="rates[${index}][additional]" class="form-control" placeholder="Additional per item">
        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.rate-row').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    ratesList.appendChild(row);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleRateFields();
    
    // Prevent form resubmission on refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + S to save form
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        const form = document.querySelector('form');
        if (form) {
            form.submit();
        }
    }
});
</script>

