<?php
// File: admin/ajax/get_audit_trail.php
// AJAX handler to get stock adjustment audit trail
// Administrator-only access for viewing adjustment history

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/StockAdjustmentController.php';

header('Content-Type: application/json');

// Check authentication and admin access
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$stockController = new StockAdjustmentController();
$adjustments = $stockController->getRecentStockAdjustments($branch_id, $limit);

$html = '';

if (empty($adjustments)) {
    $html = '<div class="text-center py-4">
                <i class="bi bi-clock-history display-4 text-muted"></i>
                <p class="text-muted mt-2">No stock adjustments found</p>
             </div>';
} else {
    $html = '<div class="mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-clock-history"></i> Recent Stock Adjustments</h6>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="badge bg-info">' . count($adjustments) . ' adjustments</span>
                    </div>
                </div>
             </div>';

    $html .= '<div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Product Type</th>
                            <th>Quantity</th>
                            <th>Movement Type</th>
                            <th>Branch</th>
                            <th>Adjusted By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($adjustments as $adjustment) {
        $movementBadge = 'bg-warning';
        $productTypeBadge = 'bg-secondary';

        switch ($adjustment['product_type']) {
            case 'RAW_MATERIAL':
                $productTypeBadge = 'bg-success';
                $productTypeLabel = 'Raw Material';
                break;
            case 'THIRD_PARTY_PRODUCT':
                $productTypeBadge = 'bg-info';
                $productTypeLabel = 'Third Party';
                break;
            case 'PACKAGING_MATERIAL':
                $productTypeBadge = 'bg-primary';
                $productTypeLabel = 'Packaging';
                break;
            case 'FINISHED_PRODUCT':
                $productTypeBadge = 'bg-warning';
                $productTypeLabel = 'Finished Product';
                break;
            default:
                $productTypeLabel = $adjustment['product_type'];
        }

        $html .= '<tr>
                    <td>
                        <small>' . date('M j, Y', strtotime($adjustment['created_at'])) . '</small><br>
                        <small class="text-muted">' . date('H:i:s', strtotime($adjustment['created_at'])) . '</small>
                    </td>
                    <td>
                        <span class="badge ' . $productTypeBadge . '">' . $productTypeLabel . '</span>
                    </td>
                    <td>
                        <strong>' . number_format($adjustment['quantity'], 2) . '</strong>
                        <small class="text-muted">' . htmlspecialchars($adjustment['unit']) . '</small>
                    </td>
                    <td>
                        <span class="badge ' . $movementBadge . '">' . htmlspecialchars($adjustment['movement_type']) . '</span>
                    </td>
                    <td>
                        <small>' . htmlspecialchars($adjustment['branch_name']) . '</small>
                    </td>
                    <td>
                        <small>' . htmlspecialchars($adjustment['adjusted_by_name']) . '</small>
                    </td>
                    <td>
                        <small>' . htmlspecialchars($adjustment['notes']) . '</small>
                    </td>
                  </tr>';
    }

    $html .= '</tbody></table></div>';

    // Add summary
    $totalAdjustments = count($adjustments);
    $recentAdjustments = array_filter($adjustments, function($adj) {
        return strtotime($adj['created_at']) > strtotime('-24 hours');
    });
    $todayCount = count($recentAdjustments);

    $html .= '<div class="alert alert-light mt-3">
                <div class="row text-center">
                    <div class="col-md-4">
                        <strong>' . $totalAdjustments . '</strong>
                        <small class="d-block text-muted">Total Shown</small>
                    </div>
                    <div class="col-md-4">
                        <strong>' . $todayCount . '</strong>
                        <small class="d-block text-muted">Last 24 Hours</small>
                    </div>
                    <div class="col-md-4">
                        <strong>' . ($branch_id ? 'Branch Specific' : 'All Branches') . '</strong>
                        <small class="d-block text-muted">Scope</small>
                    </div>
                </div>
              </div>';
}

echo json_encode(['success' => true, 'html' => $html]);
?>