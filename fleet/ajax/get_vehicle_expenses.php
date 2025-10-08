<?php
// File: fleet/ajax/get_vehicle_expenses.php
// Get expenses for a specific vehicle

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

// Only administrators and supervisors can view vehicle expenses
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

    // Get vehicle details with expenses
    $vehicle = $fleetController->getVehicleDetails($vehicleId);

    if (!$vehicle) {
        throw new Exception('Vehicle not found');
    }

    // Get expenses for this vehicle
    $expenses = $fleetController->getVehicleExpenses($vehicleId);

    echo json_encode([
        'success' => true,
        'vehicle' => $vehicle,
        'expenses' => $expenses,
        'total_expenses' => array_sum(array_column($expenses, 'amount'))
    ]);

} catch (Exception $e) {
    error_log("Get vehicle expenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>