<?php
// File: fleet/ajax/create_vehicle.php
// Create new fleet vehicle with driver assignment

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

// Only administrators and supervisors can create vehicles
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    if (empty($input['license_plate']) || empty($input['vehicle_type']) || empty($input['branch_id'])) {
        throw new Exception('License plate, vehicle type, and branch are required');
    }

    // Prepare vehicle data
    $vehicleData = [
        'vehicle_number' => trim($input['vehicle_number'] ?? ''),
        'license_plate' => trim($input['license_plate']),
        'make' => trim($input['make'] ?? ''),
        'model' => trim($input['model'] ?? ''),
        'vehicle_type' => trim($input['vehicle_type']),
        'year_manufacture' => !empty($input['year_manufacture']) ? intval($input['year_manufacture']) : null,
        'fuel_type' => trim($input['fuel_type'] ?? 'Diesel'),
        'capacity_tonnes' => !empty($input['capacity_tonnes']) ? floatval($input['capacity_tonnes']) : null,
        'assigned_driver_id' => !empty($input['assigned_driver_id']) ? intval($input['assigned_driver_id']) : null,
        'branch_id' => intval($input['branch_id']),
        'purchase_date' => !empty($input['purchase_date']) ? trim($input['purchase_date']) : null,
        'purchase_cost' => !empty($input['purchase_cost']) ? floatval($input['purchase_cost']) : null,
        'notes' => trim($input['notes'] ?? '')
    ];

    // Validate numeric values
    if ($vehicleData['year_manufacture'] && ($vehicleData['year_manufacture'] < 2000 || $vehicleData['year_manufacture'] > 2025)) {
        throw new Exception('Year manufacture must be between 2000 and 2025');
    }

    if ($vehicleData['capacity_tonnes'] && $vehicleData['capacity_tonnes'] < 0) {
        throw new Exception('Capacity cannot be negative');
    }

    if ($vehicleData['purchase_cost'] && $vehicleData['purchase_cost'] < 0) {
        throw new Exception('Purchase cost cannot be negative');
    }

    $fleetController = new FleetController();
    $result = $fleetController->createFleetVehicle($vehicleData, $_SESSION['user_id']);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Vehicle created successfully',
            'vehicle_number' => $result['vehicle_number'],
            'vehicle_id' => $result['vehicle_id']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create vehicle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>