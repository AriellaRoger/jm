<?php
// File: fleet/ajax/get_vehicles_list.php
// Get simplified vehicle list for expense forms

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

try {
    $fleetController = new FleetController();
    $vehicles = $fleetController->getFleetVehicles();

    // Simplify data for dropdown
    $simplifiedVehicles = array_map(function($vehicle) {
        return [
            'id' => $vehicle['id'],
            'vehicle_number' => $vehicle['vehicle_number'],
            'make' => $vehicle['make'],
            'model' => $vehicle['model'],
            'status' => $vehicle['status']
        ];
    }, $vehicles);

    echo json_encode([
        'success' => true,
        'vehicles' => $simplifiedVehicles
    ]);

} catch (Exception $e) {
    error_log("Get vehicles list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>