<?php
// File: inventory/index.php
// Main inventory management interface for JM Animal Feeds ERP System
// Displays products, bags, and opened bags with role-based access control

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/InventoryController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Check role permissions
$userRole = $_SESSION['user_role'];
$allowedRoles = ['Administrator', 'Supervisor', 'Production', 'Branch Operator'];
if (!in_array($userRole, $allowedRoles)) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$inventoryController = new InventoryController();
$userBranch = $_SESSION['branch_id'];
$isAdmin = ($userRole === 'Administrator');
$isSupervisor = ($userRole === 'Supervisor');
$canManageProducts = $isAdmin; // Only administrators can edit products

// Handle branch filtering for administrators
$selectedBranchId = null;
$currentBranchName = $_SESSION['branch_name'];

if ($isAdmin && isset($_GET['branch_id'])) {
    $selectedBranchId = intval($_GET['branch_id']);
    $branchInfo = $inventoryController->getBranchById($selectedBranchId);
    if ($branchInfo) {
        $currentBranchName = $branchInfo['name'];
    }
} elseif ($isSupervisor) {
    // Set current branch name to headquarters for supervisors
    $hqBranch = $inventoryController->getHeadquartersBranch();
    $currentBranchName = $hqBranch ? $hqBranch['name'] : 'Headquarters';
}

// Get branch-specific data based on role and filtering
if ($userRole === 'Branch Operator') {
    $branchId = $userBranch;
    $inventoryBranchId = $userBranch;
} elseif ($isAdmin && $selectedBranchId) {
    $branchId = $selectedBranchId; // Filter products by selected branch
    $inventoryBranchId = $selectedBranchId; // Show inventory for selected branch
} elseif ($isSupervisor) {
    // Supervisors can only see headquarters inventory (branch type = 'HQ')
    // Get headquarters branch ID for supervisor
    $hqBranch = $inventoryController->getHeadquartersBranch();
    $inventoryBranchId = $hqBranch ? $hqBranch['id'] : 1; // Default to branch 1 if HQ not found
    $branchId = $inventoryBranchId; // Filter products by HQ branch only
} else {
    $branchId = null; // Show all products for admin
    $inventoryBranchId = $userBranch; // Show inventory for user's home branch
}

$products = $inventoryController->getProducts($branchId);

// For supervisors, restrict to headquarters only
$materialsBranchId = $selectedBranchId;
if ($isSupervisor) {
    $materialsBranchId = $inventoryBranchId; // Use HQ branch ID
}

$rawMaterials = $inventoryController->getRawMaterials($materialsBranchId);
$thirdPartyProducts = $inventoryController->getThirdPartyProducts($materialsBranchId);
$packagingMaterials = $inventoryController->getPackagingMaterials($materialsBranchId);
$branchInventory = $inventoryController->getBranchInventory($inventoryBranchId);
$openedBags = $inventoryController->getOpenedBags($inventoryBranchId);

// Get all branches for admin filtering
$allBranches = $isAdmin ? $inventoryController->getAllBranches() : [];

$pageTitle = 'Inventory Management';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 dashboard-title">
            <i class="bi bi-boxes"></i> Inventory Management
        </h1>
        <?php if ($isAdmin): ?>
        <div class="mt-2">
            <label for="branchFilter" class="form-label text-muted small">Filter by Branch:</label>
            <select class="form-select form-select-sm" id="branchFilter" style="width: auto; display: inline-block;">
                <option value="">All Branches</option>
                <?php foreach ($allBranches as $branch): ?>
                <option value="<?php echo $branch['id']; ?>" <?php echo ($selectedBranchId == $branch['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($branch['name']); ?>
                    <?php if ($branch['type'] === 'HQ'): ?>
                    <span class="badge bg-primary">HQ</span>
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($canManageProducts): ?>
        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#productModal">
            <i class="bi bi-plus-circle"></i> Add Product
        </button>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <button type="button" class="btn btn-outline-info" onclick="refreshInventory()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isAdmin && $selectedBranchId): ?>
<div class="alert alert-info mb-3">
    <i class="bi bi-info-circle"></i>
    Viewing inventory for: <strong><?php echo htmlspecialchars($currentBranchName); ?></strong>
    <a href="?branch_id=" class="btn btn-sm btn-outline-primary ms-2">
        <i class="bi bi-eye"></i> View All Branches
    </a>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="stats-icon primary">
                <i class="bi bi-box"></i>
            </div>
            <div class="stats-number"><?php echo count($products); ?></div>
            <div class="stats-label">Finished Products</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="stats-icon success">
                <i class="bi bi-basket"></i>
            </div>
            <div class="stats-number"><?php echo count($rawMaterials); ?></div>
            <div class="stats-label">Raw Materials</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="stats-icon warning">
                <i class="bi bi-shop"></i>
            </div>
            <div class="stats-number"><?php echo count($thirdPartyProducts); ?></div>
            <div class="stats-label">Third Party Products</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="stats-icon info">
                <i class="bi bi-bag"></i>
            </div>
            <div class="stats-number"><?php echo count($packagingMaterials); ?></div>
            <div class="stats-label">Packaging Materials</div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="inventoryTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
            <i class="bi bi-box"></i> Finished Products
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="raw-materials-tab" data-bs-toggle="tab" data-bs-target="#raw-materials" type="button">
            <i class="bi bi-basket"></i> Raw Materials
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="third-party-tab" data-bs-toggle="tab" data-bs-target="#third-party" type="button">
            <i class="bi bi-shop"></i> Third Party Products
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="packaging-tab" data-bs-toggle="tab" data-bs-target="#packaging" type="button">
            <i class="bi bi-bag"></i> Packaging Materials
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bags-tab" data-bs-toggle="tab" data-bs-target="#bags" type="button">
            <i class="bi bi-archive"></i> Bags Inventory
            <?php if ($isAdmin && $selectedBranchId): ?>
            <span class="badge bg-info ms-1"><?php echo htmlspecialchars($currentBranchName); ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="opened-tab" data-bs-toggle="tab" data-bs-target="#opened" type="button">
            <i class="bi bi-archive-fill"></i> Opened Bags
            <?php if ($isAdmin && $selectedBranchId): ?>
            <span class="badge bg-warning ms-1"><?php echo htmlspecialchars($currentBranchName); ?></span>
            <?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="inventoryTabContent">
    <!-- Products Tab -->
    <div class="tab-pane fade show active" id="products" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-box"></i> Products Catalog</h5>
                <?php if ($canManageProducts): ?>
                <small class="text-muted">Admin/Supervisor: Full Management | Branch: View Only</small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Package Size</th>
                                <th>Selling Price (TZS)</th>
                                <th>Cost Price (TZS)</th>
                                <th>Total Bags</th>
                                <th>Sealed</th>
                                <th>Opened</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    <?php if ($product['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($product['package_size']); ?></span></td>
                                <td><?php echo number_format($product['unit_price']); ?></td>
                                <td>
                                    <?php if ($product['cost_price'] > 0): ?>
                                        <?php echo number_format($product['cost_price']); ?>
                                        <br><small class="text-muted">
                                            Margin: <?php echo number_format((($product['unit_price'] - $product['cost_price']) / $product['unit_price']) * 100, 1); ?>%
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">Not calculated</span>
                                        <br><small class="text-muted">Set during production</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary"><?php echo $product['total_bags'] ?? 0; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $product['sealed_bags'] ?? 0; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $product['opened_bags'] ?? 0; ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $product['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $product['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="viewBags(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="bi bi-eye"></i> View Bags
                                        </button>
                                        <?php if ($canManageProducts): ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($isAdmin): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Raw Materials Tab -->
    <div class="tab-pane fade" id="raw-materials" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-basket"></i> Raw Materials Inventory</h5>
                <?php if ($canManageProducts): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rawMaterialModal">
                    <i class="bi bi-plus-circle"></i> Add Raw Material
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="rawMaterialsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Third Party Products Tab -->
    <div class="tab-pane fade" id="third-party" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-shop"></i> Third Party Products</h5>
                <?php if ($canManageProducts): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#thirdPartyModal">
                    <i class="bi bi-plus-circle"></i> Add Product
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="thirdPartyContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Packaging Materials Tab -->
    <div class="tab-pane fade" id="packaging" role="tabpanel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bag"></i> Packaging Materials</h5>
                <?php if ($canManageProducts): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#packagingModal">
                    <i class="bi bi-plus-circle"></i> Add Packaging Material
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body" id="packagingContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Bags Inventory Tab -->
    <div class="tab-pane fade" id="bags" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-archive"></i>
                    Bags Inventory - <?php echo htmlspecialchars($currentBranchName); ?>
                    <?php if ($isAdmin && !$selectedBranchId): ?>
                    <span class="badge bg-primary ms-2">Your Home Branch</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Package Size</th>
                                <th>Selling Price</th>
                                <th>Total Bags</th>
                                <th>Sealed Bags</th>
                                <th>Opened Bags</th>
                                <th>Sold Bags</th>
                                <th>Value (TZS)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branchInventory as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($item['package_size']); ?></span></td>
                                <td><?php echo number_format($item['unit_price']); ?></td>
                                <td><span class="badge bg-primary"><?php echo $item['total_bags']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $item['sealed_bags']; ?></span></td>
                                <td><span class="badge bg-warning"><?php echo $item['opened_bags']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo $item['sold_bags']; ?></span></td>
                                <td><strong><?php echo number_format($item['total_bags'] * $item['unit_price']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Opened Bags Tab -->
    <div class="tab-pane fade" id="opened" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-archive-fill"></i> Opened Bags - <?php echo htmlspecialchars($currentBranchName); ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($openedBags)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> No opened bags in this branch.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Serial Number</th>
                                <th>Product</th>
                                <th>Original Weight</th>
                                <th>Current Weight</th>
                                <th>Remaining %</th>
                                <th>Price per KG (TZS)</th>
                                <th>Current Value (TZS)</th>
                                <th>Opened By</th>
                                <th>Opened Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openedBags as $bag): ?>
                            <?php
                            $percentage = ($bag['current_weight_kg'] / $bag['original_weight_kg']) * 100;
                            $currentValue = $bag['current_weight_kg'] * $bag['selling_price_per_kg'];
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($bag['serial_number']); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bag['product_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($bag['package_size']); ?></small>
                                </td>
                                <td><?php echo number_format($bag['original_weight_kg'], 1); ?> KG</td>
                                <td><?php echo number_format($bag['current_weight_kg'], 1); ?> KG</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $percentage > 50 ? 'success' : ($percentage > 25 ? 'warning' : 'danger'); ?>"
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo number_format($bag['selling_price_per_kg']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo number_format($currentValue); ?></strong>
                                    <br><small class="text-muted"><?php echo number_format($bag['current_weight_kg'], 1); ?> KG × <?php echo number_format($bag['selling_price_per_kg']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($bag['opened_by_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($bag['opened_at'])); ?></td>
                                <td><?php echo htmlspecialchars($bag['notes'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal -->
<?php if ($canManageProducts): ?>
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="productForm">
                <div class="modal-body">
                    <input type="hidden" id="productId" name="product_id">

                    <div class="mb-3">
                        <label for="productName" class="form-label">Product Name *</label>
                        <input type="text" class="form-control" id="productName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="packageSize" class="form-label">Package Size *</label>
                                <select class="form-control" id="packageSize" name="package_size" required>
                                    <option value="">Select Size</option>
                                    <option value="5KG">5KG</option>
                                    <option value="10KG">10KG</option>
                                    <option value="20KG">20KG</option>
                                    <option value="25KG">25KG</option>
                                    <option value="50KG">50KG</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="unitPrice" class="form-label">Selling Price (TZS) *</label>
                                <input type="number" class="form-control" id="unitPrice" name="unit_price" step="0.01" required>
                                <div class="form-text">Price customers pay for this product. Cost price will be calculated during production.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="statusField" style="display: none;">
                        <label for="productStatus" class="form-label">Status</label>
                        <select class="form-control" id="productStatus" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Raw Material Modal -->
<?php if ($canManageProducts): ?>
<div class="modal fade" id="rawMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rawMaterialModalTitle">Add Raw Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rawMaterialForm">
                <div class="modal-body">
                    <input type="hidden" id="rawMaterialId" name="id">

                    <div class="mb-3">
                        <label for="rawMaterialName" class="form-label">Material Name *</label>
                        <input type="text" class="form-control" id="rawMaterialName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="rawMaterialDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="rawMaterialDescription" name="description" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialUOM" class="form-label">Unit of Measure *</label>
                                <select class="form-control" id="rawMaterialUOM" name="unit_of_measure" required>
                                    <option value="">Select UOM</option>
                                    <option value="KG">KG (Kilograms)</option>
                                    <option value="Liters">Liters</option>
                                    <option value="Bags">Bags</option>
                                    <option value="Pieces">Pieces</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialSupplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="rawMaterialSupplier" name="supplier">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialCostPrice" class="form-label">Cost Price (TZS) *</label>
                                <input type="number" class="form-control" id="rawMaterialCostPrice" name="cost_price" step="0.01" required>
                                <div class="form-text">What we pay to buy this material</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialSellingPrice" class="form-label">Selling Price (TZS) *</label>
                                <input type="number" class="form-control" id="rawMaterialSellingPrice" name="selling_price" step="0.01" required>
                                <div class="form-text">What we sell it for (if we sell directly)</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialCurrentStock" class="form-label">Current Stock</label>
                                <input type="number" class="form-control" id="rawMaterialCurrentStock" name="current_stock" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="rawMaterialMinStock" class="form-label">Minimum Stock</label>
                                <input type="number" class="form-control" id="rawMaterialMinStock" name="minimum_stock" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="rawMaterialStatusField" style="display: none;">
                        <label for="rawMaterialStatus" class="form-label">Status</label>
                        <select class="form-control" id="rawMaterialStatus" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Material</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Third Party Product Modal -->
<?php if ($canManageProducts): ?>
<div class="modal fade" id="thirdPartyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="thirdPartyModalTitle">Add Third Party Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="thirdPartyForm">
                <div class="modal-body">
                    <input type="hidden" id="thirdPartyId" name="id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartyName" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="thirdPartyName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartyBrand" class="form-label">Brand *</label>
                                <input type="text" class="form-control" id="thirdPartyBrand" name="brand" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="thirdPartyDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="thirdPartyDescription" name="description" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartyCategory" class="form-label">Category</label>
                                <select class="form-control" id="thirdPartyCategory" name="category">
                                    <option value="">Select Category</option>
                                    <option value="Supplements">Supplements</option>
                                    <option value="Medicines">Medicines</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Tools">Tools</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartyUOM" class="form-label">Unit of Measure *</label>
                                <select class="form-control" id="thirdPartyUOM" name="unit_of_measure" required>
                                    <option value="">Select UOM</option>
                                    <option value="KG">KG (Kilograms)</option>
                                    <option value="Liters">Liters</option>
                                    <option value="Bottles">Bottles</option>
                                    <option value="Boxes">Boxes</option>
                                    <option value="Pieces">Pieces</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartyPackageSize" class="form-label">Package Size</label>
                                <input type="text" class="form-control" id="thirdPartyPackageSize" name="package_size" placeholder="e.g., 500ml, 1kg">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="thirdPartySupplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="thirdPartySupplier" name="supplier">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="thirdPartyCostPrice" class="form-label">Cost Price (TZS) *</label>
                                <input type="number" class="form-control" id="thirdPartyCostPrice" name="cost_price" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="thirdPartySellingPrice" class="form-label">Selling Price (TZS) *</label>
                                <input type="number" class="form-control" id="thirdPartySellingPrice" name="selling_price" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="thirdPartyCurrentStock" class="form-label">Current Stock</label>
                                <input type="number" class="form-control" id="thirdPartyCurrentStock" name="current_stock" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="thirdPartyMinStock" class="form-label">Minimum Stock</label>
                        <input type="number" class="form-control" id="thirdPartyMinStock" name="minimum_stock" step="0.01" value="0">
                    </div>

                    <div class="mb-3" id="thirdPartyStatusField" style="display: none;">
                        <label for="thirdPartyStatus" class="form-label">Status</label>
                        <select class="form-control" id="thirdPartyStatus" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Packaging Material Modal -->
<?php if ($canManageProducts): ?>
<div class="modal fade" id="packagingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="packagingModalTitle">Add Packaging Material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="packagingForm">
                <div class="modal-body">
                    <input type="hidden" id="packagingId" name="id">

                    <div class="mb-3">
                        <label for="packagingName" class="form-label">Material Name *</label>
                        <input type="text" class="form-control" id="packagingName" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="packagingDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="packagingDescription" name="description" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="packagingUnit" class="form-label">Unit *</label>
                                <select class="form-control" id="packagingUnit" name="unit" required>
                                    <option value="">Select Unit</option>
                                    <option value="Pieces">Pieces (individual items)</option>
                                    <option value="Rolls">Rolls</option>
                                    <option value="Sheets">Sheets</option>
                                    <option value="Meters">Meters</option>
                                    <option value="KG">KG (weight)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="packagingSupplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="packagingSupplier" name="supplier">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="packagingCurrentStock" class="form-label">Current Stock</label>
                                <input type="number" class="form-control" id="packagingCurrentStock" name="current_stock" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="packagingMinStock" class="form-label">Minimum Stock</label>
                                <input type="number" class="form-control" id="packagingMinStock" name="minimum_stock" step="0.01" value="0">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="packagingUnitCost" class="form-label">Unit Cost (TZS) *</label>
                        <input type="number" class="form-control" id="packagingUnitCost" name="unit_cost" step="0.01" required>
                        <div class="form-text">Cost per unit for this packaging material</div>
                    </div>

                    <div class="mb-3" id="packagingStatusField" style="display: none;">
                        <label for="packagingStatus" class="form-label">Status</label>
                        <select class="form-control" id="packagingStatus" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Material</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bags Modal -->
<div class="modal fade" id="bagsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bagsModalTitle">Product Bags</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="bagsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Product management functions
<?php if ($canManageProducts): ?>
document.getElementById('productForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const productId = document.getElementById('productId').value;
    const url = productId ? 'ajax/update_product.php' : 'ajax/create_product.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

function editProduct(id) {
    fetch(`ajax/get_product.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productDescription').value = product.description;
            document.getElementById('packageSize').value = product.package_size;
            document.getElementById('unitPrice').value = product.unit_price;
            document.getElementById('productStatus').value = product.status;
            document.getElementById('statusField').style.display = 'block';
            document.getElementById('productModalTitle').textContent = 'Edit Product';

            new bootstrap.Modal(document.getElementById('productModal')).show();
        } else {
            alert('Error loading product: ' + data.message);
        }
    });
}

function deleteProduct(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        fetch('ajax/delete_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Reset form when modal is closed
document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('statusField').style.display = 'none';
    document.getElementById('productModalTitle').textContent = 'Add New Product';
});
<?php endif; ?>

// View bags function
function viewBags(productId, productName) {
    document.getElementById('bagsModalTitle').textContent = `Bags for ${productName}`;

    // Get current branch filter if admin or supervisor
    let url = `ajax/get_bags.php?product_id=${productId}`;
    <?php if ($isAdmin && $selectedBranchId): ?>
    url += `&branch_id=<?php echo $selectedBranchId; ?>`;
    <?php elseif ($isSupervisor): ?>
    url += `&branch_id=<?php echo $inventoryBranchId; ?>`;
    <?php endif; ?>

    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById('bagsContent').innerHTML = html;
        new bootstrap.Modal(document.getElementById('bagsModal')).show();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading bags data');
    });
}

<?php if ($isAdmin): ?>
// Branch filtering for administrators
document.getElementById('branchFilter').addEventListener('change', function() {
    const branchId = this.value;
    const currentUrl = new URL(window.location);

    if (branchId) {
        currentUrl.searchParams.set('branch_id', branchId);
    } else {
        currentUrl.searchParams.delete('branch_id');
    }

    window.location.href = currentUrl.toString();
});

// Refresh inventory function
function refreshInventory() {
    location.reload();
}
<?php endif; ?>

// Tab event listeners to load content
document.addEventListener('DOMContentLoaded', function() {
    // Load raw materials when tab is shown
    document.getElementById('raw-materials-tab').addEventListener('shown.bs.tab', function() {
        loadRawMaterials();
    });

    // Load third party products when tab is shown
    document.getElementById('third-party-tab').addEventListener('shown.bs.tab', function() {
        loadThirdPartyProducts();
    });

    // Load packaging materials when tab is shown
    document.getElementById('packaging-tab').addEventListener('shown.bs.tab', function() {
        loadPackagingMaterials();
    });
});

// Load raw materials data
function loadRawMaterials() {
    let url = 'ajax/get_raw_materials.php';
    <?php if ($isAdmin && $selectedBranchId): ?>
    url += `?branch_id=<?php echo $selectedBranchId; ?>`;
    <?php elseif ($isSupervisor): ?>
    url += `?branch_id=<?php echo $inventoryBranchId; ?>`;
    <?php endif; ?>

    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById('rawMaterialsContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading raw materials:', error);
        document.getElementById('rawMaterialsContent').innerHTML =
            '<div class="alert alert-danger">Error loading raw materials data</div>';
    });
}

// Load third party products data
function loadThirdPartyProducts() {
    let url = 'ajax/get_third_party_products.php';
    <?php if ($isAdmin && $selectedBranchId): ?>
    url += `?branch_id=<?php echo $selectedBranchId; ?>`;
    <?php elseif ($isSupervisor): ?>
    url += `?branch_id=<?php echo $inventoryBranchId; ?>`;
    <?php endif; ?>

    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById('thirdPartyContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading third party products:', error);
        document.getElementById('thirdPartyContent').innerHTML =
            '<div class="alert alert-danger">Error loading third party products data</div>';
    });
}

// Load packaging materials data
function loadPackagingMaterials() {
    let url = 'ajax/get_packaging_materials.php';
    <?php if ($isAdmin && $selectedBranchId): ?>
    url += `?branch_id=<?php echo $selectedBranchId; ?>`;
    <?php elseif ($isSupervisor): ?>
    url += `?branch_id=<?php echo $inventoryBranchId; ?>`;
    <?php endif; ?>

    fetch(url)
    .then(response => response.text())
    .then(html => {
        document.getElementById('packagingContent').innerHTML = html;
    })
    .catch(error => {
        console.error('Error loading packaging materials:', error);
        document.getElementById('packagingContent').innerHTML =
            '<div class="alert alert-danger">Error loading packaging materials data</div>';
    });
}

<?php if ($canManageProducts): ?>
// Raw Material Form Handler
document.getElementById('rawMaterialForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const materialId = document.getElementById('rawMaterialId').value;
    const url = materialId ? 'ajax/update_raw_material.php' : 'ajax/create_raw_material.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('rawMaterialModal')).hide();
            loadRawMaterials(); // Reload the tab content
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

// Third Party Product Form Handler
document.getElementById('thirdPartyForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const productId = document.getElementById('thirdPartyId').value;
    const url = productId ? 'ajax/update_third_party_product.php' : 'ajax/create_third_party_product.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('thirdPartyModal')).hide();
            loadThirdPartyProducts(); // Reload the tab content
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

// Reset raw material form when modal is closed
document.getElementById('rawMaterialModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('rawMaterialForm').reset();
    document.getElementById('rawMaterialId').value = '';
    document.getElementById('rawMaterialStatusField').style.display = 'none';
    document.getElementById('rawMaterialModalTitle').textContent = 'Add Raw Material';
});

// Packaging Material Form Handler
document.getElementById('packagingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const materialId = document.getElementById('packagingId').value;
    const url = materialId ? 'ajax/update_packaging_material.php' : 'ajax/create_packaging_material.php';

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('packagingModal')).hide();
            loadPackagingMaterials(); // Reload the tab content
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
});

// Reset third party form when modal is closed
document.getElementById('thirdPartyModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('thirdPartyForm').reset();
    document.getElementById('thirdPartyId').value = '';
    document.getElementById('thirdPartyStatusField').style.display = 'none';
    document.getElementById('thirdPartyModalTitle').textContent = 'Add Third Party Product';
});

// Reset packaging form when modal is closed
document.getElementById('packagingModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('packagingForm').reset();
    document.getElementById('packagingId').value = '';
    document.getElementById('packagingStatusField').style.display = 'none';
    document.getElementById('packagingModalTitle').textContent = 'Add Packaging Material';
});
<?php endif; ?>

// Global functions for editing materials (available for AJAX-loaded content)
function editRawMaterial(id) {
    fetch(`ajax/get_raw_material.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const material = data.material;
            document.getElementById('rawMaterialId').value = material.id;
            document.getElementById('rawMaterialName').value = material.name;
            document.getElementById('rawMaterialDescription').value = material.description;
            document.getElementById('rawMaterialUOM').value = material.unit_of_measure;
            document.getElementById('rawMaterialSupplier').value = material.supplier;
            document.getElementById('rawMaterialCostPrice').value = material.cost_price;
            document.getElementById('rawMaterialSellingPrice').value = material.selling_price;
            document.getElementById('rawMaterialCurrentStock').value = material.current_stock;
            document.getElementById('rawMaterialMinStock').value = material.minimum_stock;
            document.getElementById('rawMaterialStatus').value = material.status;
            document.getElementById('rawMaterialStatusField').style.display = 'block';
            document.getElementById('rawMaterialModalTitle').textContent = 'Edit Raw Material';

            new bootstrap.Modal(document.getElementById('rawMaterialModal')).show();
        } else {
            alert('Error loading material: ' + data.message);
        }
    });
}

function deleteRawMaterial(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        fetch('ajax/delete_raw_material.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadRawMaterials(); // Reload the tab content
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function editThirdPartyProduct(id) {
    fetch(`ajax/get_third_party_product.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const product = data.product;
            document.getElementById('thirdPartyId').value = product.id;
            document.getElementById('thirdPartyName').value = product.name;
            document.getElementById('thirdPartyBrand').value = product.brand;
            document.getElementById('thirdPartyDescription').value = product.description;
            document.getElementById('thirdPartyCategory').value = product.category;
            document.getElementById('thirdPartyUOM').value = product.unit_of_measure;
            document.getElementById('thirdPartyPackageSize').value = product.package_size;
            document.getElementById('thirdPartySupplier').value = product.supplier;
            document.getElementById('thirdPartyCostPrice').value = product.cost_price;
            document.getElementById('thirdPartySellingPrice').value = product.selling_price;
            document.getElementById('thirdPartyCurrentStock').value = product.current_stock;
            document.getElementById('thirdPartyMinStock').value = product.minimum_stock;
            document.getElementById('thirdPartyStatus').value = product.status;
            document.getElementById('thirdPartyStatusField').style.display = 'block';
            document.getElementById('thirdPartyModalTitle').textContent = 'Edit Third Party Product';

            new bootstrap.Modal(document.getElementById('thirdPartyModal')).show();
        } else {
            alert('Error loading product: ' + data.message);
        }
    });
}

function deleteThirdPartyProduct(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        fetch('ajax/delete_third_party_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadThirdPartyProducts(); // Reload the tab content
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function editPackagingMaterial(id) {
    fetch(`ajax/get_packaging_material.php?id=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const material = data.material;
            document.getElementById('packagingId').value = material.id;
            document.getElementById('packagingName').value = material.name;
            document.getElementById('packagingDescription').value = material.description;
            document.getElementById('packagingUnit').value = material.unit;
            document.getElementById('packagingSupplier').value = material.supplier;
            document.getElementById('packagingCurrentStock').value = material.current_stock;
            document.getElementById('packagingMinStock').value = material.minimum_stock;
            document.getElementById('packagingUnitCost').value = material.unit_cost;
            document.getElementById('packagingStatus').value = material.status;
            document.getElementById('packagingStatusField').style.display = 'block';
            document.getElementById('packagingModalTitle').textContent = 'Edit Packaging Material';

            new bootstrap.Modal(document.getElementById('packagingModal')).show();
        } else {
            alert('Error loading material: ' + data.message);
        }
    });
}

function deletePackagingMaterial(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        fetch('ajax/delete_packaging_material.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadPackagingMaterials(); // Reload the tab content
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Auto-refresh data every 5 minutes for real-time updates (disabled for better UX)
// setInterval(function() {
//     if (!document.querySelector('.modal.show')) {
//         location.reload();
//     }
// }, 300000);

// Bag Opening Functions
function openBag(serialNumber, packageSize) {
    console.log('openBag called with:', serialNumber, packageSize);

    // Extract weight from package size (e.g., "50KG" -> 50)
    const weight = parseFloat(packageSize.replace(/[^0-9.]/g, ''));
    console.log('Extracted weight:', weight);

    // Check if modal exists
    const modalElement = document.getElementById('openBagModal');
    if (!modalElement) {
        console.error('Modal element not found');
        alert('Error: Modal not found. Please refresh the page and try again.');
        return;
    }

    // Populate modal
    document.getElementById('bagSerial').value = serialNumber;
    document.getElementById('bagWeight').value = weight;
    document.getElementById('displaySerial').value = serialNumber;
    document.getElementById('displayWeight').value = weight + ' KG';
    document.getElementById('sellingPricePerKg').value = '';

    // Show modal
    try {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        console.log('Modal shown successfully');
    } catch (error) {
        console.error('Error showing modal:', error);
        alert('Error opening bag form: ' + error.message);
    }
}

function confirmOpenBag() {
    const form = document.getElementById('openBagForm');
    const formData = new FormData(form);

    // Validate selling price
    const sellingPrice = parseFloat(formData.get('selling_price_per_kg'));
    if (!sellingPrice || sellingPrice <= 0) {
        alert('Please enter a valid selling price per KG');
        return;
    }

    // Convert FormData to URLSearchParams for proper encoding
    const params = new URLSearchParams();
    for (let [key, value] of formData) {
        params.append(key, value);
    }

    fetch('ajax/open_bag.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: params.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('openBagModal'));
            modal.hide();

            alert('Bag opened successfully! The bag has been moved to loose stock for sales.');

            // Refresh the page to show updated inventory counts
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

</script>

<!-- Bag Opening Modal -->
<?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Production', 'Branch Operator'])): ?>
<div class="modal fade" id="openBagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Open Bag for Loose Sales</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="openBagForm">
                    <input type="hidden" id="bagSerial" name="serial_number">
                    <input type="hidden" id="bagWeight" name="weight">

                    <div class="mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" class="form-control" id="displaySerial" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Weight (KG)</label>
                        <input type="text" class="form-control" id="displayWeight" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="sellingPricePerKg" class="form-label">Selling Price per KG (TZS) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="sellingPricePerKg" name="selling_price_per_kg"
                               step="0.01" min="0" required placeholder="Enter selling price per KG">
                        <div class="form-text">This will be used for loose sales from this opened bag</div>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> Once opened, this bag cannot be sealed again.
                        Make sure you have the correct selling price per KG.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmOpenBag()">
                    <i class="bi bi-scissors"></i> Open Bag
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>