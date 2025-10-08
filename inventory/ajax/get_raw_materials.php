<?php
// File: inventory/ajax/get_raw_materials.php
// AJAX handler for displaying raw materials inventory
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
    $rawMaterials = $inventoryController->getRawMaterials($branchId);
    $canManageProducts = ($userRole === 'Administrator'); // Only administrators can edit

    if (empty($rawMaterials)) {
        echo '<div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No raw materials found. Add raw materials to get started.
              </div>';
        exit;
    }
    ?>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Material Name</th>
                    <th>Branch</th>
                    <th>UOM</th>
                    <th>Cost Price (TZS)</th>
                    <th>Selling Price (TZS)</th>
                    <th>Current Stock</th>
                    <th>Min Stock</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <?php if ($canManageProducts): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rawMaterials as $material): ?>
                <?php
                $isLowStock = $material['current_stock'] <= $material['minimum_stock'];
                $margin = (($material['selling_price'] - $material['cost_price']) / $material['selling_price']) * 100;
                ?>
                <tr class="<?php echo $isLowStock ? 'table-warning' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($material['name']); ?></strong>
                        <?php if ($material['description']): ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($material['description']); ?></small>
                        <?php endif; ?>
                        <?php if ($isLowStock): ?>
                        <br><small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Low Stock</small>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-primary"><?php echo htmlspecialchars($material['branch_name'] ?? 'HQ'); ?></span></td>
                    <td><span class="badge bg-info"><?php echo htmlspecialchars($material['unit_of_measure']); ?></span></td>
                    <td><?php echo number_format($material['cost_price']); ?></td>
                    <td>
                        <?php echo number_format($material['selling_price']); ?>
                        <br><small class="text-muted">Margin: <?php echo number_format($margin, 1); ?>%</small>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $isLowStock ? 'warning' : 'success'; ?>">
                            <?php echo number_format($material['current_stock'], 2); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($material['minimum_stock'], 2); ?></td>
                    <td><?php echo htmlspecialchars($material['supplier'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $material['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                            <?php echo $material['status']; ?>
                        </span>
                    </td>
                    <?php if ($canManageProducts): ?>
                    <td>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="editRawMaterial(<?php echo $material['id']; ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($userRole === 'Administrator'): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRawMaterial(<?php echo $material['id']; ?>, '<?php echo htmlspecialchars($material['name']); ?>')">
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
    error_log("Error getting raw materials: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading raw materials</div>';
}
?>