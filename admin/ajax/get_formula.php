<?php
// File: admin/ajax/get_formula.php
// AJAX handler to get formula details
// Administrator and Supervisor access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/FormulaController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$formulaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($formulaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid formula ID']);
    exit;
}

try {
    $formulaController = new FormulaController();
    $formula = $formulaController->getFormulaDetails($formulaId);

    if (!$formula) {
        echo json_encode(['success' => false, 'message' => 'Formula not found']);
        exit;
    }

    // Generate HTML for formula details view
    $html = '<div class="row mb-3">
        <div class="col-md-6">
            <h6>Formula Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Name:</strong></td><td>' . htmlspecialchars($formula['name']) . '</td></tr>
                <tr><td><strong>Target Yield:</strong></td><td>' . number_format($formula['target_yield'], 1) . ' ' . htmlspecialchars($formula['yield_unit']) . '</td></tr>
                <tr><td><strong>Status:</strong></td><td>';

    $statusColor = $formula['status'] === 'Active' ? 'success' : 'secondary';
    $html .= '<span class="badge bg-' . $statusColor . '">' . $formula['status'] . '</span></td></tr>
                <tr><td><strong>Created By:</strong></td><td>' . htmlspecialchars($formula['created_by_name']) . '</td></tr>
                <tr><td><strong>Created Date:</strong></td><td>' . date('M j, Y H:i', strtotime($formula['created_at'])) . '</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Cost Analysis</h6>
            <table class="table table-sm">
                <tr><td><strong>Total Cost:</strong></td><td>' . number_format($formula['total_cost'], 0) . ' TZS</td></tr>
                <tr><td><strong>Cost per KG:</strong></td><td>' . number_format($formula['cost_per_kg'], 0) . ' TZS</td></tr>
                <tr><td><strong>Ingredients:</strong></td><td>' . count($formula['ingredients']) . ' materials</td></tr>
            </table>
        </div>
    </div>';

    if (!empty($formula['description'])) {
        $html .= '<div class="mb-3">
            <h6>Description</h6>
            <div class="alert alert-light">' . htmlspecialchars($formula['description']) . '</div>
        </div>';
    }

    // Ingredients table
    $html .= '<h6>Formula Ingredients</h6>
              <div class="table-responsive">
                  <table class="table table-hover">
                      <thead>
                          <tr>
                              <th>Raw Material</th>
                              <th>Quantity</th>
                              <th>Unit</th>
                              <th>Percentage</th>
                              <th>Cost (TZS)</th>
                              <th>Available Stock</th>
                          </tr>
                      </thead>
                      <tbody>';

    $totalQuantity = array_sum(array_column($formula['ingredients'], 'quantity'));

    foreach ($formula['ingredients'] as $ingredient) {
        $percentage = $totalQuantity > 0 ? ($ingredient['quantity'] / $totalQuantity) * 100 : 0;
        $cost = $ingredient['quantity'] * $ingredient['cost_price'];

        // Check stock availability
        $stockStatus = 'success';
        $stockText = 'Available';
        if ($ingredient['current_stock'] < $ingredient['quantity']) {
            $stockStatus = 'danger';
            $stockText = 'Insufficient';
        } elseif ($ingredient['current_stock'] < ($ingredient['quantity'] * 2)) {
            $stockStatus = 'warning';
            $stockText = 'Low';
        }

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($ingredient['raw_material_name']) . '</strong></td>
            <td><strong>' . number_format($ingredient['quantity'], 1) . '</strong></td>
            <td>' . htmlspecialchars($ingredient['unit']) . '</td>
            <td>' . number_format($percentage, 1) . '%</td>
            <td>' . number_format($cost, 0) . ' TZS</td>
            <td>
                <span class="badge bg-' . $stockStatus . '">' . $stockText . '</span><br>
                <small>' . number_format($ingredient['current_stock'], 1) . ' ' . htmlspecialchars($ingredient['unit_of_measure']) . '</small>
            </td>
        </tr>';
    }

    $html .= '</tbody></table></div>';

    // Formula preview section
    $html .= '<div class="mt-4">
        <h6>Production Preview</h6>
        <div class="card">
            <div class="card-body">
                <p><strong>To produce ' . number_format($formula['target_yield'], 1) . ' ' . htmlspecialchars($formula['yield_unit']) . ' of ' . htmlspecialchars($formula['name']) . ', you will need:</strong></p>
                <ul>';

    foreach ($formula['ingredients'] as $ingredient) {
        $html .= '<li>' . number_format($ingredient['quantity'], 1) . ' ' . htmlspecialchars($ingredient['unit']) . ' of ' . htmlspecialchars($ingredient['raw_material_name']) . '</li>';
    }

    $html .= '</ul>
                <div class="alert alert-info">
                    <strong>Total Production Cost:</strong> ' . number_format($formula['total_cost'], 0) . ' TZS<br>
                    <strong>Cost per ' . htmlspecialchars($formula['yield_unit']) . ':</strong> ' . number_format($formula['cost_per_kg'], 0) . ' TZS
                </div>
            </div>
        </div>
    </div>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'formula' => $formula
    ]);

} catch (Exception $e) {
    error_log("Error getting formula details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>