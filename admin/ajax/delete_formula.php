<?php
// File: admin/ajax/delete_formula.php
// AJAX handler to delete formulas
// Administrator access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/FormulaController.php';

header('Content-Type: application/json');

// Check authentication and role access - Administrator only for deletion
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$formulaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($formulaId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid formula ID']);
    exit;
}

try {
    $formulaController = new FormulaController();
    $result = $formulaController->deleteFormula($formulaId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Formula deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Formula not found or could not be deleted'
        ]);
    }

} catch (Exception $e) {
    error_log("Error deleting formula: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>