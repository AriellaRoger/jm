<?php
// File: fleet/ajax/get_machine.php
// Get machine details for editing

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/FleetController.php';

header('Content-Type: application/json');

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only administrators and supervisors can edit machines
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $machineId = isset($_GET['machine_id']) ? intval($_GET['machine_id']) : 0;

    if (!$machineId) {
        throw new Exception('Machine ID is required');
    }

    $fleetController = new FleetController();
    $machine = $fleetController->getMachineDetails($machineId);

    if (!$machine) {
        throw new Exception('Machine not found');
    }

    echo json_encode([
        'success' => true,
        'machine' => $machine
    ]);

} catch (Exception $e) {
    error_log("Get machine error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>