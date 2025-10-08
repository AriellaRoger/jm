<?php
// File: inventory/ajax/get_raw_material.php
// AJAX handler for getting single raw material for editing

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/InventoryController.php';

header('Content-Type: application/json');

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userRole = $_SESSION['user_role'];
$allowedRoles = ['Administrator', 'Supervisor', 'Production', 'Branch Operator'];
if (!in_array($userRole, $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$materialId = intval($_GET['id'] ?? 0);
if ($materialId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit;
}

try {
    $inventoryController = new InventoryController();
    $material = $inventoryController->getRawMaterialById($materialId);

    if ($material) {
        echo json_encode([
            'success' => true,
            'material' => $material
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Material not found']);
    }
} catch (Exception $e) {
    error_log("Error getting raw material: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>