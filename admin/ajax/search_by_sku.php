<?php
// File: admin/ajax/search_by_sku.php
// SKU search handler
// Accessible by all inventory users

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/BarcodeController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo '<div class="alert alert-danger">Not authenticated</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div class="alert alert-danger">Invalid request method</div>';
    exit;
}

$sku = trim($_POST['sku'] ?? '');

if (empty($sku)) {
    echo '<div class="alert alert-warning">Please enter a SKU to search</div>';
    exit;
}

try {
    $barcodeController = new BarcodeController();
    $product = $barcodeController->getProductBySKU($sku);

    if (!$product) {
        echo '<div class="alert alert-warning">
                <i class="bi bi-search"></i>
                No product found with SKU: <code>' . htmlspecialchars($sku) . '</code>
              </div>';
        exit;
    }

    // Determine product type display name
    $typeLabels = [
        'products' => 'Finished Product',
        'raw_materials' => 'Raw Material',
        'third_party_products' => 'Third Party Product',
        'packaging_materials' => 'Packaging Material'
    ];
    $productTypeLabel = $typeLabels[$product['product_type']] ?? 'Unknown';

    ?>
    <div class="alert alert-success">
        <h6><i class="bi bi-check-circle"></i> Product Found!</h6>

        <div class="row mt-3">
            <div class="col-md-8">
                <table class="table table-sm">
                    <tr>
                        <td><strong>SKU:</strong></td>
                        <td><code><?php echo htmlspecialchars($product['sku']); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Type:</strong></td>
                        <td><span class="badge bg-secondary"><?php echo $productTypeLabel; ?></span></td>
                    </tr>
                    <?php if (isset($product['brand']) && $product['brand']): ?>
                    <tr>
                        <td><strong>Brand:</strong></td>
                        <td><?php echo htmlspecialchars($product['brand']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($product['description']) && $product['description']): ?>
                    <tr>
                        <td><strong>Description:</strong></td>
                        <td><?php echo htmlspecialchars($product['description']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($product['current_stock'])): ?>
                    <tr>
                        <td><strong>Current Stock:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo $product['current_stock'] > 0 ? 'success' : 'warning'; ?>">
                                <?php echo number_format($product['current_stock'], 1); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo $product['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                <?php echo $product['status']; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-4 text-center">
                <div class="border rounded p-2 bg-light">
                    <small class="text-muted d-block mb-2">Quick Actions</small>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-primary"
                                onclick="viewBarcode('<?php echo $product['sku']; ?>')">
                            <i class="bi bi-upc-scan"></i> View Barcode
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick="downloadBarcode('<?php echo $product['sku']; ?>')">
                            <i class="bi bi-download"></i> Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function downloadBarcode(sku) {
        window.open('ajax/download_barcode.php?sku=' + encodeURIComponent(sku) + '&format=PNG', '_blank');
    }
    </script>

    <?php
} catch (Exception $e) {
    error_log("Error searching by SKU: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while searching for the product</div>';
}
?>