<?php
// File: fleet/ajax/get_machines_list.php
// Get simplified machine list for expense forms

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
    $machines = $fleetController->getCompanyMachines();

    // Simplify data for dropdown
    $simplifiedMachines = array_map(function($machine) {
        return [
            'id' => $machine['id'],
            'machine_number' => $machine['machine_number'],
            'machine_name' => $machine['machine_name'],
            'machine_type' => $machine['machine_type'],
            'status' => $machine['status']
        ];
    }, $machines);

    echo json_encode([
        'success' => true,
        'machines' => $simplifiedMachines
    ]);

} catch (Exception $e) {
    error_log("Get machines list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>