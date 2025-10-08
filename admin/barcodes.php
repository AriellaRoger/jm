<?php
// File: admin/barcodes.php
// Barcode and SKU management interface for JM Animal Feeds ERP System
// Accessible by Administrator and Supervisor roles only

session_start();
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/BarcodeController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$userRole = $_SESSION['user_role'];
if (!in_array($userRole, ['Administrator', 'Supervisor'])) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

// Handle bulk SKU generation
$bulkResults = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_skus'])) {
    try {
        $barcodeController = new BarcodeController();
        $bulkResults = $barcodeController->generateSKUsForAllProducts();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Barcode & SKU Management</h1>
                    <p class="text-muted">Industrial-grade product identification system</p>
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Barcodes</li>
                    </ol>
                </nav>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($bulkResults): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <strong>SKU Generation Complete!</strong>
                <ul class="mb-0 mt-2">
                    <li>Finished Products: <?php echo $bulkResults['finished_products']; ?> SKUs generated</li>
                    <li>Raw Materials: <?php echo $bulkResults['raw_materials']; ?> SKUs generated</li>
                    <li>Third Party Products: <?php echo $bulkResults['third_party_products']; ?> SKUs generated</li>
                    <li>Packaging Materials: <?php echo $bulkResults['packaging_materials']; ?> SKUs generated</li>
                </ul>
                <?php if (!empty($bulkResults['errors'])): ?>
                <div class="mt-2">
                    <strong>Errors:</strong>
                    <ul class="mb-0">
                        <?php foreach ($bulkResults['errors'] as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- SKU System Overview -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-diagram-3"></i>
                                Industrial SKU System Format
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary">SKU Format</h6>
                                    <code>[PREFIX]-[CATEGORY]-[SEQUENCE]-[CHECK]</code>

                                    <h6 class="text-primary mt-3">Product Types</h6>
                                    <ul class="list-unstyled">
                                        <li><span class="badge bg-success">FP</span> Finished Products</li>
                                        <li><span class="badge bg-warning">RM</span> Raw Materials</li>
                                        <li><span class="badge bg-info">TP</span> Third Party Products</li>
                                        <li><span class="badge bg-secondary">PM</span> Packaging Materials</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">Examples</h6>
                                    <ul class="list-unstyled">
                                        <li><code>FP-COW-000001-A</code> <small class="text-muted">Dairy cow feed</small></li>
                                        <li><code>RM-GRN-000001-B</code> <small class="text-muted">Grain material</small></li>
                                        <li><code>TP-VET-000001-C</code> <small class="text-muted">VetCare product</small></li>
                                        <li><code>PM-BAG-000001-D</code> <small class="text-muted">Paper bag</small></li>
                                    </ul>

                                    <h6 class="text-primary mt-3">Features</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="bi bi-check-circle text-success"></i> Unique identifiers</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Auto-generation</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Check digit validation</li>
                                        <li><i class="bi bi-check-circle text-success"></i> Barcode integration</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-gear-fill"></i>
                                Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="d-grid gap-2">
                                    <button type="submit" name="generate_skus" class="btn btn-primary">
                                        <i class="bi bi-magic"></i>
                                        Generate All SKUs
                                    </button>
                                    <small class="text-muted">Automatically generate SKUs and barcodes for all products missing them</small>
                                </div>
                            </form>

                            <hr>

                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-secondary" onclick="viewAllProducts()">
                                    <i class="bi bi-list-ul"></i>
                                    View All Products
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="searchBySKU()">
                                    <i class="bi bi-search"></i>
                                    Search by SKU
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Tables with SKUs -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="finished-tab" data-bs-toggle="tab" href="#finished" role="tab">
                                        <i class="bi bi-box-seam"></i>
                                        Finished Products
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="raw-tab" data-bs-toggle="tab" href="#raw" role="tab">
                                        <i class="bi bi-grain"></i>
                                        Raw Materials
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="third-tab" data-bs-toggle="tab" href="#third" role="tab">
                                        <i class="bi bi-building"></i>
                                        Third Party
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="packaging-tab" data-bs-toggle="tab" href="#packaging" role="tab">
                                        <i class="bi bi-box"></i>
                                        Packaging
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <!-- Finished Products Tab -->
                                <div class="tab-pane fade show active" id="finished" role="tabpanel">
                                    <div id="finishedProductsList">
                                        <div class="text-center py-4">
                                            <div class="spinner-border" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Raw Materials Tab -->
                                <div class="tab-pane fade" id="raw" role="tabpanel">
                                    <div id="rawMaterialsList">
                                        <div class="text-center py-4">
                                            <div class="spinner-border" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Third Party Products Tab -->
                                <div class="tab-pane fade" id="third" role="tabpanel">
                                    <div id="thirdPartyProductsList">
                                        <div class="text-center py-4">
                                            <div class="spinner-border" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Packaging Materials Tab -->
                                <div class="tab-pane fade" id="packaging" role="tabpanel">
                                    <div id="packagingMaterialsList">
                                        <div class="text-center py-4">
                                            <div class="spinner-border" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SKU Search Modal -->
<div class="modal fade" id="skuSearchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Search by SKU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="skuSearch" class="form-label">Enter SKU</label>
                    <input type="text" class="form-control" id="skuSearch" placeholder="e.g., FP-COW-000001-A">
                </div>
                <div id="skuSearchResults"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="performSKUSearch()">Search</button>
            </div>
        </div>
    </div>
</div>

<script>
// Load data when tabs are clicked
document.addEventListener('DOMContentLoaded', function() {
    loadFinishedProducts();

    // Tab click handlers
    document.getElementById('finished-tab').addEventListener('click', loadFinishedProducts);
    document.getElementById('raw-tab').addEventListener('click', loadRawMaterials);
    document.getElementById('third-tab').addEventListener('click', loadThirdPartyProducts);
    document.getElementById('packaging-tab').addEventListener('click', loadPackagingMaterials);
});

function loadFinishedProducts() {
    fetch('ajax/get_products_with_skus.php?type=finished')
        .then(response => response.text())
        .then(data => {
            document.getElementById('finishedProductsList').innerHTML = data;
        });
}

function loadRawMaterials() {
    fetch('ajax/get_products_with_skus.php?type=raw')
        .then(response => response.text())
        .then(data => {
            document.getElementById('rawMaterialsList').innerHTML = data;
        });
}

function loadThirdPartyProducts() {
    fetch('ajax/get_products_with_skus.php?type=third_party')
        .then(response => response.text())
        .then(data => {
            document.getElementById('thirdPartyProductsList').innerHTML = data;
        });
}

function loadPackagingMaterials() {
    fetch('ajax/get_products_with_skus.php?type=packaging')
        .then(response => response.text())
        .then(data => {
            document.getElementById('packagingMaterialsList').innerHTML = data;
        });
}

function viewAllProducts() {
    // Refresh all tabs
    loadFinishedProducts();
    loadRawMaterials();
    loadThirdPartyProducts();
    loadPackagingMaterials();
}

function searchBySKU() {
    const modal = new bootstrap.Modal(document.getElementById('skuSearchModal'));
    modal.show();
}

function performSKUSearch() {
    const sku = document.getElementById('skuSearch').value.trim();
    if (!sku) {
        alert('Please enter a SKU to search');
        return;
    }

    fetch('ajax/search_by_sku.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `sku=${encodeURIComponent(sku)}`
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('skuSearchResults').innerHTML = data;
    });
}

function generateSingleSKU(productType, productId) {
    fetch('ajax/generate_single_sku.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=${productType}&id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SKU generated successfully: ' + data.sku);
            viewAllProducts(); // Refresh the displays
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function viewBarcode(sku) {
    window.open('ajax/view_barcode.php?sku=' + encodeURIComponent(sku), '_blank', 'width=600,height=400');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>