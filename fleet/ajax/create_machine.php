<?php
// File: fleet/ajax/create_machine.php
// Create new company machine

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

// Only administrators and supervisors can create machines
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
    if (empty($input['machine_name']) || empty($input['machine_type']) || empty($input['branch_id'])) {
        throw new Exception('Machine name, machine type, and branch are required');
    }

    // Prepare machine data
    $machineData = [
        'machine_number' => trim($input['machine_number'] ?? ''),
        'machine_name' => trim($input['machine_name']),
        'machine_type' => trim($input['machine_type']),
        'brand' => trim($input['brand'] ?? ''),
        'model' => trim($input['model'] ?? ''),
        'serial_number' => trim($input['serial_number'] ?? ''),
        'branch_id' => intval($input['branch_id']),
        'department' => trim($input['department'] ?? ''),
        'purchase_date' => !empty($input['purchase_date']) ? trim($input['purchase_date']) : null,
        'purchase_cost' => !empty($input['purchase_cost']) ? floatval($input['purchase_cost']) : null,
        'notes' => trim($input['notes'] ?? '')
    ];

    // Validate numeric values
    if ($machineData['purchase_cost'] && $machineData['purchase_cost'] < 0) {
        throw new Exception('Purchase cost cannot be negative');
    }

    $fleetController = new FleetController();
    $result = $fleetController->createMachine($machineData, $_SESSION['user_id']);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Machine created successfully',
            'machine_number' => $result['machine_number'],
            'machine_id' => $result['machine_id']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create machine error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>