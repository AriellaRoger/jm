<?php
// File: inventory/ajax/update_raw_material.php
// AJAX handler for updating raw materials (Admin/Supervisor only)

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
if (!in_array($userRole, ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
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

// Validate required fields
$requiredFields = ['name', 'unit_of_measure', 'cost_price', 'selling_price', 'status'];
foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
        exit;
    }
}

// Validate numeric fields
if (!is_numeric($_POST['cost_price']) || !is_numeric($_POST['selling_price'])) {
    echo json_encode(['success' => false, 'message' => 'Cost price and selling price must be numeric']);
    exit;
}

if (!is_numeric($_POST['current_stock']) || !is_numeric($_POST['minimum_stock'])) {
    echo json_encode(['success' => false, 'message' => 'Stock values must be numeric']);
    exit;
}

try {
    $inventoryController = new InventoryController();

    $data = [
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'unit_of_measure' => $_POST['unit_of_measure'],
        'cost_price' => floatval($_POST['cost_price']),
        'selling_price' => floatval($_POST['selling_price']),
        'current_stock' => floatval($_POST['current_stock']),
        'minimum_stock' => floatval($_POST['minimum_stock']),
        'supplier' => trim($_POST['supplier'] ?? ''),
        'status' => $_POST['status']
    ];

    $result = $inventoryController->updateRawMaterial($materialId, $data);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Raw material updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update raw material']);
    }
} catch (Exception $e) {
    error_log("Error updating raw material: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>