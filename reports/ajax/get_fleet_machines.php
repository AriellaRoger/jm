<?php
// File: reports/ajax/get_fleet_machines.php
// AJAX handler to load fleet vehicles and machines for dropdown filters

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../controllers/AuthController.php';
    require_once __DIR__ . '/../../config/database.php';

    $authController = new AuthController();

    if (!$authController->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $currentUser = $authController->getCurrentUser();
    if (!in_array($currentUser['role_name'], ['Administrator', 'Supervisor'])) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $pdo = getDbConnection();

    // Get fleet vehicles
    $vehiclesSql = "SELECT id, vehicle_number, make, model, vehicle_type, status
                    FROM fleet_vehicles
                    WHERE status IN ('ACTIVE', 'MAINTENANCE')
                    ORDER BY vehicle_number";
    $vehiclesStmt = $pdo->prepare($vehiclesSql);
    $vehiclesStmt->execute();
    $vehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get company machines
    $machinesSql = "SELECT id, machine_number, machine_name, machine_type, status
                    FROM company_machines
                    WHERE status IN ('ACTIVE', 'MAINTENANCE')
                    ORDER BY machine_number";
    $machinesStmt = $pdo->prepare($machinesSql);
    $machinesStmt->execute();
    $machines = $machinesStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles,
        'machines' => $machines
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>