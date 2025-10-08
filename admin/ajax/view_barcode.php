<?php
// File: admin/ajax/view_barcode.php
// Barcode viewer for SKUs
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

$sku = $_GET['sku'] ?? '';

if (empty($sku)) {
    echo '<div class="alert alert-danger">SKU is required</div>';
    exit;
}

try {
    $barcodeController = new BarcodeController();
    $product = $barcodeController->getProductBySKU($sku);

    if (!$product) {
        echo '<div class="alert alert-danger">Product not found for SKU: ' . htmlspecialchars($sku) . '</div>';
        exit;
    }

    // Generate barcode SVG for display
    $barcodeSVG = $barcodeController->generateBarcodeSVG($sku, 2, 60);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barcode: <?php echo htmlspecialchars($sku); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
            .barcode-container {
                text-align: center;
                padding: 20px;
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                background: #f8f9fa;
            }
            .barcode-svg { max-width: 100%; height: auto; }
            .print-section {
                background: white;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #dee2e6;
                border-radius: 8px;
            }
            @media print {
                .no-print { display: none !important; }
                .print-section { border: none; margin: 0; padding: 10px; }
                body { margin: 0; }
            }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="row no-print">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Barcode Viewer</h4>
                        <div>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="bi bi-printer"></i> Print
                            </button>
                            <button onclick="window.close()" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="print-section">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Product Information</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>SKU:</strong></td>
                                <td><code><?php echo htmlspecialchars($sku); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'products' => 'Finished Product',
                                        'raw_materials' => 'Raw Material',
                                        'third_party_products' => 'Third Party Product',
                                        'packaging_materials' => 'Packaging Material'
                                    ];
                                    echo $typeLabels[$product['product_type']] ?? 'Unknown';
                                    ?>
                                </td>
                            </tr>
                            <?php if (isset($product['brand'])): ?>
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
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>Barcode</h5>
                        <div class="barcode-container">
                            <?php echo $barcodeSVG; ?>
                            <div class="mt-2">
                                <strong><?php echo htmlspecialchars($sku); ?></strong>
                            </div>
                            <small class="text-muted">CODE 128</small>
                        </div>
                    </div>
                </div>

                <div class="row mt-4 no-print">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Usage Instructions:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Use this barcode for product identification and tracking</li>
                                <li>The barcode follows CODE 128 standard for industrial compatibility</li>
                                <li>SKU format: [PREFIX]-[CATEGORY]-[SEQUENCE]-[CHECK]</li>
                                <li>Print this page for physical product labeling</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>

    <?php
} catch (Exception $e) {
    error_log("Error viewing barcode: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while generating barcode</div>';
}
?>