<?php
// File: fleet/ajax/assign_driver.php
// Assign driver to vehicle

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

// Only administrators and supervisors can assign drivers
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
    if (empty($input['driver_id']) || empty($input['vehicle_id'])) {
        throw new Exception('Driver and vehicle are required');
    }

    $driverId = intval($input['driver_id']);
    $vehicleId = intval($input['vehicle_id']);

    $fleetController = new FleetController();

    // First, unassign the driver from any other vehicle
    $pdo = new PDO('mysql:host=localhost;dbname=jmerp', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    // Remove driver from other vehicles
    $sql = "UPDATE fleet_vehicles SET assigned_driver_id = NULL WHERE assigned_driver_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$driverId]);

    // Assign driver to the new vehicle
    $sql = "UPDATE fleet_vehicles SET assigned_driver_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$driverId, $vehicleId]);

    // Log activity
    $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION['user_id'],
        'DRIVER_ASSIGNED',
        'FLEET',
        "Driver ID {$driverId} assigned to vehicle ID {$vehicleId}"
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Driver assigned to vehicle successfully'
    ]);

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Assign driver error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>