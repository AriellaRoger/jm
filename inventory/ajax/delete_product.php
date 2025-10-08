<?php
// File: inventory/ajax/delete_product.php
// AJAX handler for deleting products (Administrator only)
// Only accessible by Administrator role

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
    echo json_encode(['success' => false, 'message' => 'Access denied - Administrator only']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$productId = intval($_POST['id'] ?? 0);
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $inventoryController = new InventoryController();
    $result = $inventoryController->deleteProduct($productId);

    if ($result === true) {
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } elseif (is_array($result)) {
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
    }
} catch (Exception $e) {
    error_log("Error deleting product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>