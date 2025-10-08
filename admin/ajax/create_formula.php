<?php
// File: admin/ajax/create_formula.php
// AJAX handler to create new formulas
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$requiredFields = ['name', 'target_yield', 'yield_unit', 'created_by', 'ingredients'];
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

// Validate each ingredient
foreach ($input['ingredients'] as $index => $ingredient) {
    $requiredIngredientFields = ['raw_material_id', 'quantity', 'unit'];
    foreach ($requiredIngredientFields as $field) {
        if (!isset($ingredient[$field]) || empty($ingredient[$field])) {
            echo json_encode(['success' => false, 'message' => "Ingredient $index: Missing required field $field"]);
            exit;
        }
    }

    // Validate quantity
    if (!is_numeric($ingredient['quantity']) || $ingredient['quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => "Ingredient $index: Invalid quantity"]);
        exit;
    }
}

try {
    $formulaController = new FormulaController();
    $result = $formulaController->createFormula($input);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Formula created successfully',
            'formula_id' => $result['formula_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Error creating formula'
        ]);
    }

} catch (Exception $e) {
    error_log("Error creating formula: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>