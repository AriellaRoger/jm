<?php
// File: inventory/ajax/delete_third_party_product.php
// AJAX handler for deleting third party products (Admin only)

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
    echo json_encode(['success' => false, 'message' => 'Only administrators can delete products']);
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
    $result = $inventoryController->deleteThirdPartyProduct($productId);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Third party product deleted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete third party product']);
    }
} catch (Exception $e) {
    error_log("Error deleting third party product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>