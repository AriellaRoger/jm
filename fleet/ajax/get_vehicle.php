<?php
// File: fleet/ajax/get_vehicle.php
// Get vehicle details for editing

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

// Only administrators and supervisors can edit vehicles
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $vehicleId = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

    if (!$vehicleId) {
        throw new Exception('Vehicle ID is required');
    }

    $fleetController = new FleetController();
    $vehicle = $fleetController->getVehicleDetails($vehicleId);

    if (!$vehicle) {
        throw new Exception('Vehicle not found');
    }

    echo json_encode([
        'success' => true,
        'vehicle' => $vehicle
    ]);

} catch (Exception $e) {
    error_log("Get vehicle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>