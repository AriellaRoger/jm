<?php
// File: admin/ajax/update_formula.php
// AJAX handler to update formulas
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$formulaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($formulaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid formula ID']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$requiredFields = ['name', 'target_yield', 'yield_unit', 'ingredients'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

// Validate ingredients
if (!is_array($input['ingredients']) || count($input['ingredients']) === 0) {
    echo json_encode(['success' => false, 'message' => 'At least one ingredient is required']);
    exit;
}

try {
    $formulaController = new FormulaController();
    $result = $formulaController->updateFormula($formulaId, $input);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Formula updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Error updating formula'
        ]);
    }

} catch (Exception $e) {
    error_log("Error updating formula: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>