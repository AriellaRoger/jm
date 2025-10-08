<?php
// File: inventory/ajax/delete_packaging_material.php
// AJAX handler for deleting packaging materials (Admin only)

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
    $result = $inventoryController->deletePackagingMaterial($materialId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Packaging material deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete packaging material']);
    }
} catch (Exception $e) {
    error_log("Error deleting packaging material: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>