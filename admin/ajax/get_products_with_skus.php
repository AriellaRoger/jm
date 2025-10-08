<?php
// File: admin/ajax/get_products_with_skus.php
// AJAX handler for displaying products with SKUs and barcodes
// Accessible by Administrator and Supervisor roles only

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo '<div class="alert alert-danger">Not authenticated</div>';
    exit;
}

$userRole = $_SESSION['user_role'];
if (!in_array($userRole, ['Administrator', 'Supervisor'])) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$type = $_GET['type'] ?? 'finished';

try {
    $db = Database::getInstance()->getConnection();

    switch ($type) {
        case 'finished':
            $sql = "SELECT id, sku, name, description, package_size, unit_price, status, created_at FROM products ORDER BY id DESC";
            $title = "Finished Products";
            $productType = "finished_product";
            break;
        case 'raw':
            $sql = "SELECT id, sku, name, description, unit_of_measure, cost_price, selling_price, current_stock, status, created_at FROM raw_materials ORDER BY id DESC";
            $title = "Raw Materials";
            $productType = "raw_material";
            break;
        case 'third_party':
            $sql = "SELECT id, sku, name, brand, description, category, unit_of_measure, cost_price, selling_price, current_stock, status, created_at FROM third_party_products ORDER BY id DESC";
            $title = "Third Party Products";
            $productType = "third_party_product";
            break;
        case 'packaging':
            $sql = "SELECT id, sku, name, description, unit, unit_cost, current_stock, status, created_at FROM packaging_materials ORDER BY id DESC";
            $title = "Packaging Materials";
            $productType = "packaging_material";
            break;
        default:
            throw new Exception("Invalid product type");
    }

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        echo '<div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No ' . strtolower($title) . ' found.
              </div>';
        exit;
    }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><?php echo $title; ?> (<?php echo count($products); ?>)</h6>
        <small class="text-muted">Industrial SKU & Barcode System</small>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>SKU</th>
                    <th>Product Details</th>
                    <?php if ($type === 'finished'): ?>
                    <th>Package Size</th>
                    <th>Price (TZS)</th>
                    <?php elseif ($type === 'raw'): ?>
                    <th>UOM</th>
                    <th>Cost/Selling Price</th>
                    <th>Stock</th>
                    <?php elseif ($type === 'third_party'): ?>
                    <th>Brand/Category</th>
                    <th>UOM</th>
                    <th>Prices</th>
                    <th>Stock</th>
                    <?php elseif ($type === 'packaging'): ?>
                    <th>Unit</th>
                    <th>Unit Cost</th>
                    <th>Stock</th>
                    <?php endif; ?>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?php if ($product['sku']): ?>
                        <div class="d-flex align-items-center">
                            <code class="bg-light p-1 rounded me-2"><?php echo htmlspecialchars($product['sku']); ?></code>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="viewBarcode('<?php echo $product['sku']; ?>')" title="View Barcode">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="d-flex align-items-center">
                            <span class="text-muted me-2">No SKU</span>
                            <button type="button" class="btn btn-sm btn-warning"
                                    onclick="generateSingleSKU('<?php echo $productType; ?>', <?php echo $product['id']; ?>)"
                                    title="Generate SKU">
                                <i class="bi bi-magic"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                        <?php if ($product['description']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                        <?php endif; ?>
                    </td>

                    <?php if ($type === 'finished'): ?>
                    <td><span class="badge bg-info"><?php echo htmlspecialchars($product['package_size']); ?></span></td>
                    <td><?php echo number_format($product['unit_price']); ?></td>

                    <?php elseif ($type === 'raw'): ?>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['unit_of_measure']); ?></span></td>
                    <td>
                        Cost: <?php echo number_format($product['cost_price']); ?><br>
                        <small class="text-muted">Sell: <?php echo number_format($product['selling_price']); ?></small>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $product['current_stock'] > 0 ? 'success' : 'warning'; ?>">
                            <?php echo number_format($product['current_stock'], 1); ?>
                        </span>
                    </td>

                    <?php elseif ($type === 'third_party'): ?>
                    <td>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($product['brand']); ?></span>
                        <?php if ($product['category']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($product['category']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['unit_of_measure']); ?></span></td>
                    <td>
                        Cost: <?php echo number_format($product['cost_price']); ?><br>
                        <small class="text-muted">Sell: <?php echo number_format($product['selling_price']); ?></small>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $product['current_stock'] > 0 ? 'success' : 'warning'; ?>">
                            <?php echo number_format($product['current_stock'], 1); ?>
                        </span>
                    </td>

                    <?php elseif ($type === 'packaging'): ?>
                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($product['unit']); ?></span></td>
                    <td><?php echo number_format($product['unit_cost']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $product['current_stock'] > 0 ? 'success' : 'warning'; ?>">
                            <?php echo number_format($product['current_stock'], 1); ?>
                        </span>
                    </td>
                    <?php endif; ?>

                    <td>
                        <span class="badge bg-<?php echo $product['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                            <?php echo $product['status']; ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <?php if ($product['sku']): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="viewBarcode('<?php echo $product['sku']; ?>')" title="View Barcode">
                                <i class="bi bi-upc-scan"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    onclick="downloadBarcode('<?php echo $product['sku']; ?>')" title="Download Barcode">
                                <i class="bi bi-download"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-warning"
                                    onclick="generateSingleSKU('<?php echo $productType; ?>', <?php echo $product['id']; ?>)"
                                    title="Generate SKU">
                                <i class="bi bi-magic"></i> Generate SKU
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function downloadBarcode(sku) {
        window.open('ajax/download_barcode.php?sku=' + encodeURIComponent(sku) + '&format=PNG', '_blank');
    }
    </script>

    <?php
} catch (Exception $e) {
    error_log("Error getting products with SKUs: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading products</div>';
}
?>