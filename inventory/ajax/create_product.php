<?php
// File: inventory/ajax/create_product.php
// AJAX handler for creating new products in JM Animal Feeds ERP System
// Only accessible by Administrator and Supervisor roles

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

// Validate input
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$package_size = trim($_POST['package_size'] ?? '');
$unit_price = floatval($_POST['unit_price'] ?? 0);

if (empty($name) || empty($package_size) || $unit_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Valid package sizes
$validSizes = ['5KG', '10KG', '20KG', '25KG', '50KG'];
if (!in_array($package_size, $validSizes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid package size']);
    exit;
}

try {
    $inventoryController = new InventoryController();

    $productData = [
        'name' => $name,
        'description' => $description,
        'package_size' => $package_size,
        'unit_price' => $unit_price,
        'created_by' => $_SESSION['user_id']
    ];

    $result = $inventoryController->createProduct($productData);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Product created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create product']);
    }
} catch (Exception $e) {
    error_log("Error creating product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>