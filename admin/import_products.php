<?php
// admin/import_products.php - Custom CSV Import Handler for products-100.csv

$page_title = "Import Products";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Admin only
require_admin();

// Create necessary directories
$upload_dir = '../uploads/imports/';
$logs_dir = '../uploads/imports/logs/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
if (!is_dir($logs_dir)) mkdir($logs_dir, 0755, true);

// Initialize variables
$import_results = [];
$errors = [];
$success = false;
$import_id = date('Ymd_His') . '_' . uniqid();

// Handle file upload and import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_ext !== 'csv') {
        $errors[] = "Please upload a CSV file.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed. Error code: " . $file['error'];
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
        $errors[] = "File size exceeds 5MB limit.";
    } else {
        // Process the CSV
        $import_results = processCSVImport($file['tmp_name'], $pdo);
        $success = true;
        
        // Save import log
        $log_data = [
            'import_id' => $import_id,
            'filename' => $file['name'],
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $import_results,
            'user_id' => $_SESSION['user_id']
        ];
        
        file_put_contents($logs_dir . $import_id . '.json', json_encode($log_data, JSON_PRETTY_PRINT));
    }
}

// Function to process CSV import
function processCSVImport($filepath, $pdo) {
    $results = [
        'total' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'details' => [],
        'errors' => []
    ];
    
    if (($handle = fopen($filepath, 'r')) !== FALSE) {
        // Read headers
        $headers = fgetcsv($handle);
        
        // Define column mapping (based on your CSV structure)
        $column_map = [
            'Index' => 'index',
            'Name' => 'name',
            'Description' => 'description',
            'Brand' => 'brand',
            'Category' => 'category',
            'Price' => 'price',
            'Currency' => 'currency',
            'Stock' => 'stock',
            'EAN' => 'ean',
            'Color' => 'color',
            'Size' => 'size',
            'Availability' => 'availability',
            'Internal ID' => 'internal_id'
        ];
        
        $row_number = 1;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                $results['total']++;
                
                // Map data to associative array
                $row_data = [];
                foreach ($headers as $index => $header) {
                    $header = trim($header);
                    if (isset($column_map[$header]) && isset($data[$index])) {
                        $row_data[$column_map[$header]] = trim($data[$index]);
                    }
                }
                
                // Skip empty rows
                if (empty(array_filter($row_data))) {
                    $results['skipped']++;
                    $results['details'][] = "Row $row_number: Skipped - empty row";
                    continue;
                }
                
                // Validate required fields
                $validation_errors = [];
                
                if (empty($row_data['name'])) {
                    $validation_errors[] = "Product name is required";
                }
                
                if (empty($row_data['price']) || !is_numeric($row_data['price'])) {
                    $validation_errors[] = "Valid price is required";
                }
                
                if (!empty($validation_errors)) {
                    $results['failed']++;
                    $results['errors'][] = "Row $row_number: " . implode(', ', $validation_errors);
                    continue;
                }
                
                // Process the product
                try {
                    // Handle category
                    $category_id = null;
                    if (!empty($row_data['category'])) {
                        $category_id = getOrCreateCategory($row_data['category'], $pdo);
                    }
                    
                    // Generate slug
                    $slug = createSlug($row_data['name']);
                    
                    // Prepare product data
                    $price = floatval($row_data['price']);
                    
                    // Determine status based on availability
                    $status = determineStatus($row_data['availability'] ?? '');
                    
                    // Check if product exists (by EAN or name)
                    $existing_product = null;
                    
                    if (!empty($row_data['ean'])) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE ean = ?");
                        $stmt->execute([$row_data['ean']]);
                        $existing_product = $stmt->fetch();
                    }
                    
                    if (!$existing_product && !empty($row_data['name'])) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                        $stmt->execute([$row_data['name']]);
                        $existing_product = $stmt->fetch();
                    }
                    
                    // Build description with additional attributes
                    $description = $row_data['description'] ?? '';
                    if (!empty($row_data['color']) || !empty($row_data['size'])) {
                        $description .= "\n\nAdditional Specifications:\n";
                        if (!empty($row_data['color'])) {
                            $description .= "Color: " . $row_data['color'] . "\n";
                        }
                        if (!empty($row_data['size'])) {
                            $description .= "Size: " . $row_data['size'] . "\n";
                        }
                    }
                    
                    if ($existing_product) {
                        // Update existing product
                        $sql = "UPDATE products SET 
                                name = ?, slug = ?, description = ?, price = ?, 
                                stock = ?, ean = ?, brand = ?, category_id = ?, 
                                status = ?, updated_at = NOW()
                                WHERE id = ?";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $row_data['name'],
                            $slug,
                            $description,
                            $price,
                            intval($row_data['stock'] ?? 0),
                            $row_data['ean'] ?? null,
                            $row_data['brand'] ?? null,
                            $category_id,
                            $status,
                            $existing_product['id']
                        ]);
                        
                        $results['updated']++;
                        $results['details'][] = "Row $row_number: Updated product - " . $row_data['name'];
                        
                    } else {
                        // Insert new product
                        $sql = "INSERT INTO products (
                                name, slug, description, price, stock, ean, 
                                brand, category_id, status, created_at, updated_at
                                ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                                )";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $row_data['name'],
                            $slug,
                            $description,
                            $price,
                            intval($row_data['stock'] ?? 0),
                            $row_data['ean'] ?? null,
                            $row_data['brand'] ?? null,
                            $category_id,
                            $status
                        ]);
                        
                        $product_id = $pdo->lastInsertId();
                        
                        // Save additional attributes as product attributes
                        if (!empty($row_data['color'])) {
                            saveProductAttribute($product_id, 'Color', $row_data['color'], $pdo);
                        }
                        
                        if (!empty($row_data['size'])) {
                            saveProductAttribute($product_id, 'Size', $row_data['size'], $pdo);
                        }
                        
                        if (!empty($row_data['internal_id'])) {
                            saveProductAttribute($product_id, 'Internal ID', $row_data['internal_id'], $pdo);
                        }
                        
                        if (!empty($row_data['currency']) && $row_data['currency'] !== 'USD') {
                            saveProductAttribute($product_id, 'Currency', $row_data['currency'], $pdo);
                        }
                        
                        $results['imported']++;
                        $results['details'][] = "Row $row_number: Imported new product - " . $row_data['name'];
                    }
                    
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Row $row_number: Database error - " . $e->getMessage();
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $results['errors'][] = "Transaction failed: " . $e->getMessage();
        }
        
        fclose($handle);
    }
    
    return $results;
}

// Helper function to get or create category
function getOrCreateCategory($category_name, $pdo) {
    $category_name = trim($category_name);
    $slug = createSlug($category_name);
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
    $stmt->execute([$category_name, $slug]);
    $category = $stmt->fetch();
    
    if ($category) {
        return $category['id'];
    }
    
    // Create new category
    $stmt = $pdo->prepare("INSERT INTO categories (name, slug, status) VALUES (?, ?, 'active')");
    $stmt->execute([$category_name, $slug]);
    
    return $pdo->lastInsertId();
}

// Helper function to determine product status
function determineStatus($availability) {
    $availability = strtolower(trim($availability));
    
    switch ($availability) {
        case 'in_stock':
        case 'in stock':
            return 'active';
        case 'out_of_stock':
        case 'out of stock':
        case 'discontinued':
            return 'inactive';
        case 'pre_order':
        case 'pre-order':
        case 'preorder':
        case 'backorder':
        case 'limited_stock':
        case 'limited stock':
            return 'active';
        default:
            return 'draft';
    }
}

// Helper function to save product attributes
function saveProductAttribute($product_id, $name, $value, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO product_attributes (product_id, attribute_name, attribute_value) VALUES (?, ?, ?)");
    $stmt->execute([$product_id, $name, $value]);
}

// Helper function to create slug
if (!function_exists('createSlug')) {
    function createSlug($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        
        if (empty($text)) {
            return 'product-' . uniqid();
        }
        
        return $text;
    }
}

require_once 'header.php';
?>

<div class="admin-main">
    <div class="page-header">
        <h1><i class="fas fa-file-import"></i> Import Products from CSV</h1>
        <p>Upload your products-100.csv file to import products into your catalog</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success && !empty($import_results)): ?>
        <div class="import-results">
            <div class="results-header <?= $import_results['failed'] > 0 ? 'warning' : 'success' ?>">
                <h2><i class="fas fa-check-circle"></i> Import Complete</h2>
                <p>Import ID: <?= $import_id ?></p>
            </div>
            
            <div class="stats-grid" style="margin-top: 1.5rem;">
                <div class="stat-card">
                    <div class="stat-value"><?= $import_results['total'] ?></div>
                    <div class="stat-label">Total Rows Processed</div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-value"><?= $import_results['imported'] ?></div>
                    <div class="stat-label">New Products Imported</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-value"><?= $import_results['updated'] ?></div>
                    <div class="stat-label">Products Updated</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-value"><?= $import_results['skipped'] ?></div>
                    <div class="stat-label">Skipped (Empty/Duplicate)</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-value"><?= $import_results['failed'] ?></div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>

            <?php if (!empty($import_results['details'])): ?>
                <div class="card" style="margin-top: 1.5rem;">
                    <h3>Import Log</h3>
                    <div style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                        <?php foreach ($import_results['details'] as $detail): ?>
                            <div style="padding: 0.25rem 0; border-bottom: 1px solid #e5e7eb;">
                                <small><?= htmlspecialchars($detail) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($import_results['errors'])): ?>
                <div class="card" style="margin-top: 1.5rem; border-left: 4px solid #ef4444;">
                    <h3 style="color: #ef4444;">Errors Encountered</h3>
                    <div style="max-height: 200px; overflow-y: auto; background: #fef2f2; padding: 1rem; border-radius: 8px;">
                        <?php foreach ($import_results['errors'] as $error): ?>
                            <div style="padding: 0.25rem 0; color: #991b1b;">
                                <small>⚠️ <?= htmlspecialchars($error) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                <a href="products.php" class="btn btn-primary">
                    <i class="fas fa-box"></i> View All Products
                </a>
                <a href="import_products.php" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Import Another File
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Upload Form -->
        <div class="card">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Drag & Drop your CSV file here</h3>
                <p>or click to browse</p>
                <p class="file-info">Supported format: CSV (Max size: 5MB)</p>
                
                <form method="post" enctype="multipart/form-data" id="importForm">
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" style="display: none;" required>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('csv_file').click()">
                        <i class="fas fa-folder-open"></i> Choose File
                    </button>
                    <span id="file_name" style="margin-left: 1rem; color: var(--admin-gray);"></span>
                </form>
            </div>
        </div>

        <!-- File Preview Template -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3>CSV File Structure Expected</h3>
            <p>Your CSV should have the following columns:</p>
            
            <div style="overflow-x: auto;">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Column</th>
                            <th>Example</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Name</td><td>Compact Printer Air Advanced Digital</td><td>✅</td><td>Product name/title</td></tr>
                        <tr><td>Description</td><td>Situation organization these memory much off.</td><td>❌</td><td>Product description</td></tr>
                        <tr><td>Brand</td><td>Garner, Boyle and Flynn</td><td>❌</td><td>Brand name</td></tr>
                        <tr><td>Category</td><td>Books & Stationery</td><td>❌</td><td>Product category</td></tr>
                        <tr><td>Price</td><td>265</td><td>✅</td><td>Product price (numeric)</td></tr>
                        <tr><td>Currency</td><td>USD</td><td>❌</td><td>Currency code</td></tr>
                        <tr><td>Stock</td><td>774</td><td>❌</td><td>Inventory quantity</td></tr>
                        <tr><td>EAN</td><td>2091465262179</td><td>❌</td><td>EAN barcode</td></tr>
                        <tr><td>Color</td><td>ForestGreen</td><td>❌</td><td>Product color</td></tr>
                        <tr><td>Size</td><td>Large</td><td>❌</td><td>Product size</td></tr>
                        <tr><td>Availability</td><td>pre_order</td><td>❌</td><td>Stock status</td></tr>
                        <tr><td>Internal ID</td><td>56</td><td>❌</td><td>Your internal reference</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                <h4>📋 Status Mapping:</h4>
                <ul style="list-style: none; padding: 0; display: flex; flex-wrap: wrap; gap: 1rem;">
                    <li><span class="badge success">in_stock</span> → Active</li>
                    <li><span class="badge success">pre_order</span> → Active</li>
                    <li><span class="badge success">backorder</span> → Active</li>
                    <li><span class="badge success">limited_stock</span> → Active</li>
                    <li><span class="badge warning">out_of_stock</span> → Inactive</li>
                    <li><span class="badge danger">discontinued</span> → Inactive</li>
                    <li><span class="badge">unknown</span> → Draft</li>
                </ul>
            </div>
        </div>

        <!-- Sample Data Preview -->
        <div class="card" style="margin-top: 1.5rem;">
            <h3>Sample Data Preview (First 5 rows)</h3>
            <div style="overflow-x: auto;">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Availability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Compact Printer Air Advanced Digital</td>
                            <td>Garner, Boyle and Flynn</td>
                            <td>Books & Stationery</td>
                            <td>$265</td>
                            <td>774</td>
                            <td><span class="badge success">pre_order</span></td>
                        </tr>
                        <tr>
                            <td>Tablet</td>
                            <td>Mueller Inc</td>
                            <td>Shoes & Footwear</td>
                            <td>$502</td>
                            <td>81</td>
                            <td><span class="badge success">in_stock</span></td>
                        </tr>
                        <tr>
                            <td>Smart Blender Cooker</td>
                            <td>Lawson, Keller and Winters</td>
                            <td>Kitchen Appliances</td>
                            <td>$227</td>
                            <td>726</td>
                            <td><span class="badge success">in_stock</span></td>
                        </tr>
                        <tr>
                            <td>Advanced Router Rechargeable</td>
                            <td>Gallagher and Sons</td>
                            <td>Kitchen Appliances</td>
                            <td>$121</td>
                            <td>896</td>
                            <td><span class="badge danger">discontinued</span></td>
                        </tr>
                        <tr>
                            <td>Portable Mouse Monitor Phone</td>
                            <td>Irwin LLC</td>
                            <td>Kids' Clothing</td>
                            <td>$1</td>
                            <td>925</td>
                            <td><span class="badge danger">discontinued</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Page Styles */
.admin-main {
    margin-left: 260px;
    margin-top: 70px;
    padding: 2rem;
    background: #f8fafc;
    min-height: calc(100vh - 70px);
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.page-header p {
    color: #6b7280;
    font-size: 1rem;
}

/* Upload Area */
.upload-area {
    text-align: center;
    padding: 3rem;
    border: 3px dashed #d1d5db;
    border-radius: 12px;
    background: #f9fafb;
    transition: all 0.3s ease;
}

.upload-area:hover {
    border-color: #4f46e5;
    background: #f3f4f6;
}

.upload-area i {
    font-size: 4rem;
    color: #4f46e5;
    margin-bottom: 1rem;
}

.upload-area h3 {
    font-size: 1.5rem;
    color: #374151;
    margin-bottom: 0.5rem;
}

.upload-area p {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.file-info {
    font-size: 0.875rem;
    color: #9ca3af;
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    border: 1px solid #e5e7eb;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin: 1.5rem 0;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.25rem;
}

.stat-card .stat-label {
    color: #6b7280;
    font-size: 0.875rem;
}

.stat-card.success .stat-value { color: #10b981; }
.stat-card.info .stat-value { color: #3b82f6; }
.stat-card.warning .stat-value { color: #f59e0b; }
.stat-card.danger .stat-value { color: #ef4444; }

/* Buttons */
.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #4f46e5);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.btn-secondary {
    background: white;
    color: #374151;
    border: 1px solid #d1d5db;
}

.btn-secondary:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Preview Table */
.preview-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.preview-table th {
    background: #f3f4f6;
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #374151;
}

.preview-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e5e7eb;
}

.preview-table tr:hover {
    background: #f9fafb;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.success {
    background: #d1fae5;
    color: #065f46;
}

.badge.warning {
    background: #fef3c7;
    color: #92400e;
}

.badge.danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}

.alert-danger {
    background: #fee2e2;
    border-left-color: #ef4444;
    color: #991b1b;
}

/* Import Results */
.import-results {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.results-header {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.results-header.success {
    background: #d1fae5;
    color: #065f46;
}

.results-header.warning {
    background: #fef3c7;
    color: #92400e;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-main {
        margin-left: 0;
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.getElementById('csv_file').addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : 'No file selected';
    document.getElementById('file_name').textContent = fileName;
    
    if (e.target.files.length > 0) {
        if (confirm('Start importing this file? This may take a few moments.')) {
            document.getElementById('importForm').submit();
        }
    }
});

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    uploadArea.style.borderColor = '#4f46e5';
    uploadArea.style.background = '#f3f4f6';
}

function unhighlight() {
    uploadArea.style.borderColor = '#d1d5db';
    uploadArea.style.background = '#f9fafb';
}

uploadArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        const file = files[0];
        if (file.name.endsWith('.csv')) {
            document.getElementById('csv_file').files = files;
            document.getElementById('file_name').textContent = file.name;
            
            if (confirm('Start importing this file? This may take a few moments.')) {
                document.getElementById('importForm').submit();
            }
        } else {
            alert('Please drop a CSV file.');
        }
    }
}
</script>

