<?php
// File: inventory/ajax/create_third_party_product.php
// AJAX handler for creating third party products (Admin/Supervisor only)

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

// Validate required fields
$requiredFields = ['name', 'brand', 'unit_of_measure', 'cost_price', 'selling_price'];
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
        'brand' => trim($_POST['brand']),
        'description' => trim($_POST['description'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'unit_of_measure' => $_POST['unit_of_measure'],
        'package_size' => trim($_POST['package_size'] ?? ''),
        'cost_price' => floatval($_POST['cost_price']),
        'selling_price' => floatval($_POST['selling_price']),
        'current_stock' => floatval($_POST['current_stock']),
        'minimum_stock' => floatval($_POST['minimum_stock']),
        'supplier' => trim($_POST['supplier'] ?? ''),
        'created_by' => $_SESSION['user_id']
    ];

    $result = $inventoryController->createThirdPartyProduct($data);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Third party product created successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create third party product']);
    }
} catch (Exception $e) {
    error_log("Error creating third party product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>