<?php
// File: admin/ajax/get_formula_preview.php
// AJAX handler to get formula preview for production
// Administrator and Supervisor access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ProductionController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$formulaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$batchSize = isset($_GET['batch_size']) ? (int)$_GET['batch_size'] : 1;

if ($formulaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid formula ID']);
    exit;
}

try {
    $productionController = new ProductionController();
    $formula = $productionController->getFormulaForProduction($formulaId, $batchSize);

    if (!$formula) {
        echo json_encode(['success' => false, 'message' => 'Formula not found']);
        exit;
    }

    // Generate HTML for formula preview
    $html = '<div class="formula-preview">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Formula Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($formula['name']) . '</td></tr>
                    <tr><td><strong>Batch Size:</strong></td><td>' . $batchSize . 'x</td></tr>
                    <tr><td><strong>Expected Yield:</strong></td><td>' . number_format($formula['total_yield'], 1) . ' ' . htmlspecialchars($formula['yield_unit']) . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Cost Analysis</h6>
                <table class="table table-sm">
                    <tr><td><strong>Total Cost:</strong></td><td>' . number_format($formula['total_cost'], 0) . ' TZS</td></tr>
                    <tr><td><strong>Cost per KG:</strong></td><td>' . number_format($formula['cost_per_kg'], 0) . ' TZS</td></tr>
                </table>
            </div>
        </div>';

    if (!empty($formula['description'])) {
        $html .= '<div class="mb-3">
            <h6>Description</h6>
            <div class="alert alert-light">' . htmlspecialchars($formula['description']) . '</div>
        </div>';
    }

    if ($formula['available']) {
        $html .= '<div class="alert alert-success">
            <h6><i class="bi bi-check-circle"></i> Production Ready</h6>
            <p>All raw materials are available for production. You can proceed with this formula.</p>
        </div>';
    } else {
        $html .= '<div class="alert alert-danger">
            <h6><i class="bi bi-exclamation-triangle"></i> Material Shortages</h6>
            <p>The following materials are insufficient for production:</p>
        </div>';
    }

    // Material requirements table
    $html .= '<h6>Material Requirements</h6>
              <div class="table-responsive">
                  <table class="table table-hover table-sm">
                      <thead>
                          <tr>
                              <th>Raw Material</th>
                              <th>Required</th>
                              <th>Available</th>
                              <th>Status</th>
                              <th>Cost</th>
                          </tr>
                      </thead>
                      <tbody>';

    foreach ($formula['ingredients'] as $ingredient) {
        $statusClass = 'success';
        $statusText = 'Available';
        $statusIcon = 'check-circle';

        if ($ingredient['shortage'] > 0) {
            $statusClass = 'danger';
            $statusText = 'Insufficient';
            $statusIcon = 'x-circle';
        } elseif ($ingredient['current_stock'] < ($ingredient['required_quantity'] * 1.5)) {
            $statusClass = 'warning';
            $statusText = 'Low Stock';
            $statusIcon = 'exclamation-triangle';
        }

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($ingredient['raw_material_name']) . '</strong></td>
            <td>' . number_format($ingredient['required_quantity'], 1) . ' ' . htmlspecialchars($ingredient['unit']) . '</td>
            <td>' . number_format($ingredient['current_stock'], 1) . ' ' . htmlspecialchars($ingredient['unit_of_measure']) . '</td>
            <td>
                <span class="badge bg-' . $statusClass . '">
                    <i class="bi bi-' . $statusIcon . '"></i> ' . $statusText . '
                </span>
            </td>
            <td>' . number_format($ingredient['total_cost'], 0) . ' TZS</td>
        </tr>';
    }

    $html .= '</tbody></table></div>';

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'formula' => $formula
    ]);

} catch (Exception $e) {
    error_log("Error getting formula preview: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>