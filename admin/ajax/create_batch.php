<?php
// File: admin/ajax/create_batch.php
// AJAX handler to create new production batch
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
$requiredFields = ['formula_id', 'batch_size', 'production_officer_id', 'supervisor_id'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $productionController = new ProductionController();
    $result = $productionController->createBatch($input);

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Error creating production batch: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>