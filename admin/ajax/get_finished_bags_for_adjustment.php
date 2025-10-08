<?php
// File: admin/ajax/get_finished_bags_for_adjustment.php
// AJAX handler to get finished product bags for viewing (read-only for administrators)
// Shows sealed bags for reference only - no adjustments allowed

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
$finishedBags = $stockController->getFinishedProductBagsForBranch($branch_id);

$html = '';

if (empty($finishedBags)) {
    $html = '<div class="text-center py-4">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-2">No sealed product bags found for this branch</p>
                <small class="text-muted">Bags are created through the production module</small>
             </div>';
} else {
    // Group bags by product
    $groupedBags = [];
    foreach ($finishedBags as $bag) {
        $key = $bag['product_id'];
        if (!isset($groupedBags[$key])) {
            $groupedBags[$key] = [
                'product_name' => $bag['product_name'],
                'package_size' => $bag['package_size'],
                'unit_price' => $bag['unit_price'],
                'cost_price' => $bag['cost_price'],
                'bags' => []
            ];
        }
        $groupedBags[$key]['bags'][] = $bag;
    }

    $html = '<div class="row">';

    foreach ($groupedBags as $productId => $product) {
        $bagCount = count($product['bags']);
        $html .= '<div class="col-md-6 col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-box"></i> ' . htmlspecialchars($product['product_name']) . '
                                <span class="badge bg-light text-dark ms-2">' . $bagCount . ' bags</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Package Size:</strong> ' . htmlspecialchars($product['package_size']) . '<br>
                                <strong>Unit Price:</strong> ' . number_format($product['unit_price'], 0) . ' TZS
                            </p>';

        if ($bagCount <= 10) {
            // Show individual bags if count is manageable
            $html .= '<div class="mt-2">
                        <strong>Serial Numbers:</strong>
                        <div class="mt-1">';
            foreach ($product['bags'] as $bag) {
                $html .= '<span class="badge bg-secondary me-1 mb-1">' . htmlspecialchars($bag['serial_number']) . '</span>';
            }
            $html .= '</div></div>';
        } else {
            // Show sample of bags if too many
            $html .= '<div class="mt-2">
                        <strong>Sample Serial Numbers:</strong>
                        <div class="mt-1">';
            for ($i = 0; $i < 5; $i++) {
                if (isset($product['bags'][$i])) {
                    $html .= '<span class="badge bg-secondary me-1 mb-1">' . htmlspecialchars($product['bags'][$i]['serial_number']) . '</span>';
                }
            }
            $html .= '<span class="text-muted">... and ' . ($bagCount - 5) . ' more</span>
                        </div></div>';
        }

        $html .= '      </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Bags managed through production/sales modules
                            </small>
                        </div>
                    </div>
                  </div>';
    }

    $html .= '</div>';

    if (count($groupedBags) > 0) {
        $totalBags = array_sum(array_column($groupedBags, function($product) { return count($product['bags']); }));
        $html .= '<div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i>
                    <strong>Summary:</strong> ' . count($groupedBags) . ' products with ' . $totalBags . ' sealed bags total.
                  </div>';
    }
}

echo json_encode(['success' => true, 'html' => $html]);
?>