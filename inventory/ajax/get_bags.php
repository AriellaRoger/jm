<?php
// File: inventory/ajax/get_bags.php
// AJAX handler for getting product bags with serial numbers
// Accessible by all inventory module users with branch-based filtering

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

$productId = intval($_GET['product_id'] ?? 0);
if ($productId <= 0) {
    echo '<div class="alert alert-danger">Invalid product ID</div>';
    exit;
}

// Branch filtering based on role and admin selection
$branchId = null;
if ($userRole === 'Branch Operator') {
    // Branch operators can only see their own branch inventory
    $branchId = $_SESSION['branch_id'];
} elseif ($userRole === 'Supervisor' || $userRole === 'Production') {
    // Supervisors and Production can only see HQ inventory (branch_id = 1)
    $branchId = 1;
} elseif ($userRole === 'Administrator' && isset($_GET['branch_id'])) {
    // Administrators can filter by any branch
    $branchId = intval($_GET['branch_id']) ?: null;
}

try {
    $inventoryController = new InventoryController();
    $bags = $inventoryController->getProductBags($productId, $branchId);

    if (empty($bags)) {
        echo '<div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                No bags found for this product' . ($branchId ? ' in your branch' : '') . '.
                <br><small class="text-muted">Bags will appear here after production.</small>
              </div>';

        exit;
    }
    ?>

    <div class="mb-3">
        <h6>Total Bags: <span class="badge bg-primary"><?php echo count($bags); ?></span></h6>
    </div>

    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-sm table-hover">
            <thead class="table-light sticky-top">
                <tr>
                    <th>Serial Number</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Production Date</th>
                    <th>Expiry Date</th>
                    <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Production', 'Branch Operator'])): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bags as $bag): ?>
                <tr>
                    <td>
                        <code class="text-primary"><?php echo htmlspecialchars($bag['serial_number']); ?></code>
                        <?php if ($bag['status'] === 'Opened'): ?>
                        <br><small class="text-muted">
                            Weight: <?php echo number_format($bag['current_weight_kg'], 1); ?>KG / <?php echo number_format($bag['original_weight_kg'], 1); ?>KG
                            <?php if (isset($bag['selling_price_per_kg']) && $bag['selling_price_per_kg'] > 0): ?>
                            <br>Price: <?php echo number_format($bag['selling_price_per_kg']); ?> TZS/KG
                            <br>Value: <?php echo number_format($bag['current_weight_kg'] * $bag['selling_price_per_kg']); ?> TZS
                            <?php endif; ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-info"><?php echo htmlspecialchars($bag['branch_name']); ?></span>
                    </td>
                    <td>
                        <?php
                        $statusColors = [
                            'Sealed' => 'success',
                            'Opened' => 'warning',
                            'Sold' => 'danger'
                        ];
                        $color = $statusColors[$bag['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $color; ?>"><?php echo $bag['status']; ?></span>
                        <?php if ($bag['status'] === 'Opened' && $bag['opened_at']): ?>
                        <br><small class="text-muted">
                            <?php echo date('M j, Y', strtotime($bag['opened_at'])); ?>
                            <br>by <?php echo htmlspecialchars($bag['opened_by_name']); ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M j, Y', strtotime($bag['production_date'])); ?></td>
                    <td>
                        <?php
                        $expiryDate = strtotime($bag['expiry_date']);
                        $isExpired = $expiryDate < time();
                        $isExpiringSoon = $expiryDate < strtotime('+30 days');
                        ?>
                        <span class="<?php echo $isExpired ? 'text-danger' : ($isExpiringSoon ? 'text-warning' : ''); ?>">
                            <?php echo date('M j, Y', $expiryDate); ?>
                        </span>
                        <?php if ($isExpired): ?>
                        <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Expired</small>
                        <?php elseif ($isExpiringSoon): ?>
                        <br><small class="text-warning"><i class="bi bi-clock"></i> Expires Soon</small>
                        <?php endif; ?>
                    </td>
                    <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Production', 'Branch Operator'])): ?>
                    <td>
                        <?php
                        // Determine if user can open this bag
                        $canOpenBag = false;
                        if (in_array($userRole, ['Administrator', 'Supervisor'])) {
                            // Admins and supervisors can open bags from any branch
                            $canOpenBag = true;
                        } elseif ($userRole === 'Production') {
                            // Production can open bags from HQ (branch_id = 1) or their assigned branch
                            $canOpenBag = ($bag['branch_id'] == 1 || $bag['branch_id'] == $_SESSION['branch_id']);
                        } elseif ($userRole === 'Branch Operator') {
                            // Branch operators can only open bags from their own branch
                            $canOpenBag = ($bag['branch_id'] == $_SESSION['branch_id']);
                        }
                        ?>

                        <?php if ($bag['status'] === 'Sealed' && $canOpenBag): ?>
                        <button type="button" class="btn btn-sm btn-outline-warning"
                                onclick="openBag('<?php echo $bag['serial_number']; ?>', '<?php echo $bag['package_size']; ?>')"
                                title="Open this bag for loose sales">
                            <i class="bi bi-scissors"></i> Open
                        </button>
                        <?php elseif ($bag['status'] === 'Sealed' && !$canOpenBag): ?>
                        <small class="text-muted">Different Branch</small>
                        <?php elseif ($bag['status'] === 'Opened'): ?>
                        <span class="badge bg-success"><i class="bi bi-check"></i> Opened</span>
                        <?php elseif ($bag['status'] === 'Sold'): ?>
                        <span class="badge bg-danger"><i class="bi bi-cash"></i> Sold</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>



    <?php
} catch (Exception $e) {
    error_log("Error getting bags: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while loading bags</div>';
}
?>