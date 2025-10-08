<?php
// File: inventory/ajax/delete_raw_material.php
// AJAX handler for deleting raw materials (Admin only)

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/InventoryController.php';

header('Content-Type: application/json');

// Check authentication and authorization
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userRole = $_SESSION['user_role'];
if ($userRole !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Only administrators can delete materials']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$materialId = intval($_POST['id'] ?? 0);
if ($materialId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid material ID']);
    exit;
}

try {
    $inventoryController = new InventoryController();
    $result = $inventoryController->deleteRawMaterial($materialId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Raw material deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete raw material']);
    }
} catch (Exception $e) {
    error_log("Error deleting raw material: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>