<?php
// File: admin/ajax/get_third_party_for_adjustment.php
// AJAX handler to get third party products for stock adjustment interface
// Administrator-only access for stock adjustment functionality

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
$thirdPartyProducts = $stockController->getThirdPartyProductsForBranch($branch_id);

$html = '';

if (empty($thirdPartyProducts)) {
    $html = '<div class="text-center py-4">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-2">No third party products found for this branch</p>
             </div>';
} else {
    $html = '<div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Product Details</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Min Stock</th>
                            <th>Cost Price (TZS)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($thirdPartyProducts as $product) {
        $stockStatus = '';
        $stockBadge = 'bg-success';

        if ($product['current_stock'] <= $product['minimum_stock']) {
            $stockStatus = 'Low Stock';
            $stockBadge = 'bg-danger';
        } elseif ($product['current_stock'] <= ($product['minimum_stock'] * 1.5)) {
            $stockStatus = 'Warning';
            $stockBadge = 'bg-warning';
        } else {
            $stockStatus = 'Good';
        }

        $html .= '<tr>
                    <td>
                        <span class="badge bg-secondary">' . htmlspecialchars($product['sku'] ?? 'N/A') . '</span>
                    </td>
                    <td>
                        <strong>' . htmlspecialchars($product['brand']) . '</strong><br>
                        <span class="text-primary">' . htmlspecialchars($product['name']) . '</span>';

        if (!empty($product['package_size'])) {
            $html .= '<br><small class="text-muted">Package: ' . htmlspecialchars($product['package_size']) . '</small>';
        }

        if (!empty($product['description'])) {
            $html .= '<br><small class="text-muted">' . htmlspecialchars($product['description']) . '</small>';
        }

        $html .= '</td>
                    <td><span class="badge bg-info">' . htmlspecialchars($product['category']) . '</span></td>
                    <td><span class="badge bg-primary">' . htmlspecialchars($product['unit_of_measure']) . '</span></td>
                    <td>
                        <strong>' . number_format($product['current_stock'], 2) . '</strong>
                        <span class="badge ' . $stockBadge . ' ms-1">' . $stockStatus . '</span>
                    </td>
                    <td>' . number_format($product['minimum_stock'], 2) . '</td>
                    <td>' . number_format($product['cost_price'], 0) . '</td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="adjustStock(\'third_party\', ' . $product['id'] . ', \'' . addslashes($product['brand'] . ' ' . $product['name']) . '\', ' . $product['current_stock'] . ', \'' . $product['unit_of_measure'] . '\')">
                            <i class="bi bi-sliders"></i> Adjust
                        </button>
                    </td>
                  </tr>';
    }

    $html .= '</tbody></table></div>';
}

echo json_encode(['success' => true, 'html' => $html]);
?>