<?php
// File: fleet/ajax/get_maintenance_details.php
// Get detailed maintenance information with items

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

// Only administrators and supervisors can view maintenance details
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!isset($_GET['maintenance_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Maintenance ID is required']);
    exit;
}

try {
    $maintenanceId = intval($_GET['maintenance_id']);
    $fleetController = new FleetController();

    $result = $fleetController->getMaintenanceDetails($maintenanceId);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'maintenance' => $result['maintenance'],
            'items' => $result['items']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }

} catch (Exception $e) {
    error_log("Get maintenance details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>