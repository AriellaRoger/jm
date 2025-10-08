<?php
// File: inventory/ajax/get_third_party_products.php
// AJAX handler for displaying third party products inventory
// Accessible by all inventory module users

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/InventoryController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo '<div class="alert alert-danger">Not authenticated</div>';
    exit;
}

$userRole = $_SESSION['user_role'];
$allowedRoles = ['Administrator', 'Supervisor', 'Production', 'Branch Operator'];
if (!in_array($userRole, $allowedRoles)) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

// Branch filtering logic
$branchId = null;
if ($userRole === 'Administrator' && isset($_GET['branch_id'])) {
    // Administrators can filter by any branch
    $branchId = intval($_GET['branch_id']) ?: null;
} elseif ($userRole === 'Branch Operator') {
    // Branch operators can only see their own branch inventory
    $branchId = $_SESSION['branch_id'];
} elseif ($userRole === 'Supervisor' || $userRole === 'Production') {
    // Supervisors and Production can only see HQ inventory (branch_id = 1)
    $branchId = 1;
}

try {
    $inventoryController = new InventoryController();
    $thirdPartyProducts = $inventoryController->getThirdPartyProducts($branchId);
    $canManageProducts = ($userRole === 'Administrator'); // Only administrators can edit

    if (empty($thirdPartyProducts)) {
        echo '<div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No third party products found. Add products to get started.
              </div>';
        exit;
    }
    ?>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Branch</th>
                    <th>Category</th>
                    <th>UOM / Size</th>
                    <th>Cost Price (TZS)</th>
                    <th>Selling Price (TZS)</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Status</th>
                    <?php if ($canManageProducts): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($thirdPartyProducts as $product): ?>
                <?php
                $isLowStock = $product['current_stock'] <= $product['minimum_stock'];
                $margin = (($product['selling_price'] - $product['cost_price']) / $product['selling_price']) * 100;
                ?>
                <tr class="<?php echo $isLowStock ? 'table-warning' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                        <?php if ($product['description']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($product['description']); ?></small>
                        <?php endif; ?>
                        <?php if ($isLowStock): ?>
                        <br><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Low Stock</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($product['brand']); ?></span></td>
                    <td><span class="badge bg-success"><?php echo htmlspecialchars($product['branch_name'] ?? 'HQ'); ?></span></td>
                    <td><?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge bg-info"><?php echo htmlspecialchars($product['unit_of_measure']); ?></span>
                        <?php if ($product['package_size']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($product['package_size']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($product['cost_price']); ?></td>
                    <td>
                        <?php echo number_format($product['selling_price']); ?>
                        <br><small class="text-muted">Margin: <?php echo number_format($margin, 1); ?>%</small>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $isLowStock ? 'warning' : 'success'; ?>">
                            <?php echo number_format($product['current_stock'], 2); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($product['minimum_stock'], 2); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $product['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                            <?php echo $product['status']; ?>
                        </span>
                    </td>
                    <?php if ($canManageProducts): ?>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="editThirdPartyProduct(<?php echo $product['id']; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($userRole === 'Administrator'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteThirdPartyProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <?php
} catch (Exception $e) {
    error_log("Error getting third party products: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading third party products</div>';
}
?>