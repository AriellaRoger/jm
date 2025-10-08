<?php
// File: admin/ajax/get_packaging_for_adjustment.php
// AJAX handler to get packaging materials for stock adjustment interface
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
$packagingMaterials = $stockController->getPackagingMaterialsForBranch($branch_id);

$html = '';

if (empty($packagingMaterials)) {
    $html = '<div class="text-center py-4">
                <i class="bi bi-inbox display-4 text-muted"></i>
                <p class="text-muted mt-2">No packaging materials found for this branch</p>
             </div>';
} else {
    $html = '<div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Material Name</th>
                            <th>Unit</th>
                            <th>Current Stock</th>
                            <th>Min Stock</th>
                            <th>Unit Cost (TZS)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($packagingMaterials as $material) {
        $stockStatus = '';
        $stockBadge = 'bg-success';

        if ($material['current_stock'] <= $material['minimum_stock']) {
            $stockStatus = 'Low Stock';
            $stockBadge = 'bg-danger';
        } elseif ($material['current_stock'] <= ($material['minimum_stock'] * 1.5)) {
            $stockStatus = 'Warning';
            $stockBadge = 'bg-warning';
        } else {
            $stockStatus = 'Good';
        }

        $html .= '<tr>
                    <td>
                        <span class="badge bg-secondary">' . htmlspecialchars($material['sku'] ?? 'N/A') . '</span>
                    </td>
                    <td>
                        <strong>' . htmlspecialchars($material['name']) . '</strong>';

        if (!empty($material['description'])) {
            $html .= '<br><small class="text-muted">' . htmlspecialchars($material['description']) . '</small>';
        }

        if (!empty($material['supplier'])) {
            $html .= '<br><small class="text-info">Supplier: ' . htmlspecialchars($material['supplier']) . '</small>';
        }

        $html .= '</td>
                    <td><span class="badge bg-info">' . htmlspecialchars($material['unit']) . '</span></td>
                    <td>
                        <strong>' . number_format($material['current_stock'], 2) . '</strong>
                        <span class="badge ' . $stockBadge . ' ms-1">' . $stockStatus . '</span>
                    </td>
                    <td>' . number_format($material['minimum_stock'], 2) . '</td>
                    <td>' . number_format($material['unit_cost'], 0) . '</td>
                    <td><span class="badge bg-success">Active</span></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="adjustStock(\'packaging\', ' . $material['id'] . ', \'' . addslashes($material['name']) . '\', ' . $material['current_stock'] . ', \'' . $material['unit'] . '\')">
                            <i class="bi bi-sliders"></i> Adjust
                        </button>
                    </td>
                  </tr>';
    }

    $html .= '</tbody></table></div>';
}

echo json_encode(['success' => true, 'html' => $html]);
?>