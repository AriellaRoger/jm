<?php
// File: inventory/ajax/update_product.php
// AJAX handler for updating product details
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
$productId = intval($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$package_size = trim($_POST['package_size'] ?? '');
$unit_price = floatval($_POST['unit_price'] ?? 0);
$status = trim($_POST['status'] ?? 'Active');

if ($productId <= 0 || empty($name) || empty($package_size) || $unit_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Valid package sizes and statuses
$validSizes = ['5KG', '10KG', '20KG', '25KG', '50KG'];
$validStatuses = ['Active', 'Inactive'];

if (!in_array($package_size, $validSizes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid package size']);
    exit;
}

if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $inventoryController = new InventoryController();

    $productData = [
        'name' => $name,
        'description' => $description,
        'package_size' => $package_size,
        'unit_price' => $unit_price,
        'status' => $status
    ];

    $result = $inventoryController->updateProduct($productId, $productData);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product']);
    }
} catch (Exception $e) {
    error_log("Error updating product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>