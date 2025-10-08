<?php
// File: admin/ajax/check_availability.php
// AJAX handler to check raw material availability for formulas
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

$batchSize = isset($_GET['batch_size']) ? (int)$_GET['batch_size'] : 1;
if ($batchSize < 1) $batchSize = 1;

try {
    $formulaController = new FormulaController();
    $availability = $formulaController->checkFormulaAvailability($formulaId, $batchSize);

    if (!isset($availability['available'])) {
        echo json_encode(['success' => false, 'message' => 'Error checking availability']);
        exit;
    }

    // Get formula details for display
    $formula = $formulaController->getFormulaDetails($formulaId);
    if (!$formula) {
        echo json_encode(['success' => false, 'message' => 'Formula not found']);
        exit;
    }

    // Generate HTML for availability check
    $html = '<div class="mb-3">
        <h6>Formula: ' . htmlspecialchars($formula['name']) . '</h6>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Batch Size:</strong> ' . $batchSize . '</p>
                <p><strong>Expected Yield:</strong> ' . number_format($availability['total_yield'], 1) . ' ' . htmlspecialchars($formula['yield_unit']) . '</p>
            </div>
            <div class="col-md-6">
                <p><strong>Production Cost:</strong> ' . number_format($formula['total_cost'] * $batchSize, 0) . ' TZS</p>
                <p><strong>Cost per KG:</strong> ' . number_format($formula['cost_per_kg'], 0) . ' TZS</p>
            </div>
        </div>
    </div>';

    if ($availability['available']) {
        $html .= '<div class="alert alert-success">
            <h5><i class="bi bi-check-circle"></i> Production Ready</h5>
            <p>All raw materials are available for production. You can proceed with this formula.</p>
        </div>';
    } else {
        $html .= '<div class="alert alert-danger">
            <h5><i class="bi bi-exclamation-triangle"></i> Material Shortages</h5>
            <p>The following materials are insufficient for production:</p>
        </div>';
    }

    // Material requirements table
    $html .= '<h6>Material Requirements</h6>
              <div class="table-responsive">
                  <table class="table table-hover">
                      <thead>
                          <tr>
                              <th>Material</th>
                              <th>Required</th>
                              <th>Available</th>
                              <th>Status</th>
                              <th>Shortage</th>
                          </tr>
                      </thead>
                      <tbody>';

    foreach ($formula['ingredients'] as $ingredient) {
        $required = $ingredient['quantity'] * $batchSize;
        $available = $ingredient['current_stock'];
        $shortage = max(0, $required - $available);

        $statusClass = 'success';
        $statusText = 'Available';
        $statusIcon = 'check-circle';

        if ($shortage > 0) {
            $statusClass = 'danger';
            $statusText = 'Insufficient';
            $statusIcon = 'x-circle';
        } elseif ($available < ($required * 1.5)) {
            $statusClass = 'warning';
            $statusText = 'Low Stock';
            $statusIcon = 'exclamation-triangle';
        }

        $html .= '<tr>
            <td><strong>' . htmlspecialchars($ingredient['raw_material_name']) . '</strong></td>
            <td>' . number_format($required, 1) . ' ' . htmlspecialchars($ingredient['unit']) . '</td>
            <td>' . number_format($available, 1) . ' ' . htmlspecialchars($ingredient['unit_of_measure']) . '</td>
            <td>
                <span class="badge bg-' . $statusClass . '">
                    <i class="bi bi-' . $statusIcon . '"></i> ' . $statusText . '
                </span>
            </td>
            <td>';

        if ($shortage > 0) {
            $html .= '<span class="text-danger">' . number_format($shortage, 1) . ' ' . htmlspecialchars($ingredient['unit']) . '</span>';
        } else {
            $html .= '<span class="text-success">-</span>';
        }

        $html .= '</td></tr>';
    }

    $html .= '</tbody></table></div>';

    // Batch size selector
    $html .= '<div class="mt-3">
        <label for="batchSizeSelector" class="form-label">Check Different Batch Size:</label>
        <div class="input-group" style="max-width: 300px;">
            <input type="number" class="form-control" id="batchSizeSelector" value="' . $batchSize . '" min="1" max="10">
            <button class="btn btn-outline-primary" onclick="checkDifferentBatch(' . $formulaId . ')">
                <i class="bi bi-search"></i> Check
            </button>
        </div>
    </div>';

    // Add JavaScript for batch size checking
    $html .= '<script>
    function checkDifferentBatch(formulaId) {
        const batchSize = document.getElementById("batchSizeSelector").value;
        if (batchSize && batchSize > 0) {
            fetch(`ajax/check_availability.php?id=${formulaId}&batch_size=${batchSize}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById("stockCheckContent").innerHTML = data.html;
                    }
                })
                .catch(error => console.error("Error:", error));
        }
    }
    </script>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'availability' => $availability
    ]);

} catch (Exception $e) {
    error_log("Error checking formula availability: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>