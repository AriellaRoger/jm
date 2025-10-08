<?php
// File: admin/ajax/resume_production.php
// AJAX handler to resume paused production batch
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

$batchId = isset($input['batch_id']) ? (int)$input['batch_id'] : 0;

if ($batchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit;
}

try {
    $productionController = new ProductionController();
    $result = $productionController->resumeProduction($batchId, $_SESSION['user_id']);

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Error resuming production: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>