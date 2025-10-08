<?php
// File: admin/ajax/get_opened_bags_for_adjustment.php
// AJAX handler to get opened bags for weight adjustment interface
// Administrator-only access for weight adjustment functionality

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/StockAdjustmentController.php';

header('Content-Type: application/json');

// Check authentication and admin access
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$stockController = new StockAdjustmentController();
$openedBags = $stockController->getOpenedBagsForBranch($branch_id);

$html = '';

if (empty($openedBags)) {
    $html = '<div class="text-center py-4">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-2">No opened bags found for this branch</p>
                <small class="text-muted">Bags are opened through the inventory module</small>
             </div>';
} else {
    $html = '<div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Serial Number</th>
                            <th>Product</th>
                            <th>Original Weight</th>
                            <th>Current Weight</th>
                            <th>Weight Remaining</th>
                            <th>Price/KG</th>
                            <th>Current Value</th>
                            <th>Opened By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($openedBags as $bag) {
        $weightRemaining = $bag['current_weight_kg'];
        $currentValue = $weightRemaining * $bag['selling_price_per_kg'];
        $weightPercentage = ($bag['current_weight_kg'] / $bag['original_weight_kg']) * 100;

        // Determine weight status
        $weightBadge = 'bg-success';
        $weightStatus = 'Good';
        if ($weightPercentage <= 25) {
            $weightBadge = 'bg-danger';
            $weightStatus = 'Low';
        } elseif ($weightPercentage <= 50) {
            $weightBadge = 'bg-warning';
            $weightStatus = 'Medium';
        }

        $html .= '<tr>
                    <td>
                        <span class="badge bg-primary">' . htmlspecialchars($bag['serial_number']) . '</span>
                    </td>
                    <td>
                        <strong>' . htmlspecialchars($bag['product_name']) . '</strong><br>
                        <small class="text-muted">' . htmlspecialchars($bag['package_size']) . '</small>
                    </td>
                    <td>' . number_format($bag['original_weight_kg'], 2) . ' KG</td>
                    <td>
                        <strong>' . number_format($bag['current_weight_kg'], 2) . ' KG</strong>
                    </td>
                    <td>
                        <div class="progress mb-1" style="height: 8px;">
                            <div class="progress-bar ' . $weightBadge . '" style="width: ' . $weightPercentage . '%"></div>
                        </div>
                        <small class="' . str_replace('bg-', 'text-', $weightBadge) . '">' . number_format($weightPercentage, 1) . '% (' . $weightStatus . ')</small>
                    </td>
                    <td>' . number_format($bag['selling_price_per_kg'], 0) . ' TZS</td>
                    <td>
                        <strong>' . number_format($currentValue, 0) . ' TZS</strong>
                    </td>
                    <td>
                        <small>' . htmlspecialchars($bag['opened_by_name']) . '</small><br>
                        <small class="text-muted">' . date('M j, Y', strtotime($bag['opened_at'])) . '</small>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="adjustStock(\'opened_bag\', ' . $bag['id'] . ', \'' . addslashes($bag['serial_number'] . ' - ' . $bag['product_name']) . '\', ' . $bag['current_weight_kg'] . ', \'KG\')">
                            <i class="bi bi-sliders"></i> Adjust Weight
                        </button>
                    </td>
                  </tr>';
    }

    $html .= '</tbody></table></div>';

    // Add summary information
    $totalBags = count($openedBags);
    $totalOriginalWeight = array_sum(array_column($openedBags, 'original_weight_kg'));
    $totalCurrentWeight = array_sum(array_column($openedBags, 'current_weight_kg'));
    $totalValue = array_sum(array_map(function($bag) {
        return $bag['current_weight_kg'] * $bag['selling_price_per_kg'];
    }, $openedBags));

    $html .= '<div class="alert alert-info mt-3">
                <div class="row text-center">
                    <div class="col-md-3">
                        <strong>' . $totalBags . '</strong>
                        <small class="d-block text-muted">Total Opened Bags</small>
                    </div>
                    <div class="col-md-3">
                        <strong>' . number_format($totalOriginalWeight, 2) . ' KG</strong>
                        <small class="d-block text-muted">Original Weight</small>
                    </div>
                    <div class="col-md-3">
                        <strong>' . number_format($totalCurrentWeight, 2) . ' KG</strong>
                        <small class="d-block text-muted">Current Weight</small>
                    </div>
                    <div class="col-md-3">
                        <strong>' . number_format($totalValue, 0) . ' TZS</strong>
                        <small class="d-block text-muted">Current Value</small>
                    </div>
                </div>
              </div>';
}

echo json_encode(['success' => true, 'html' => $html]);
?>