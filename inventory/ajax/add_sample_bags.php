<?php
// File: inventory/ajax/add_sample_bags.php
// AJAX handler for adding sample bags for testing (Admin/Supervisor only)
// This will be replaced by production module functionality

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

$productId = intval($_POST['product_id'] ?? 0);
$count = intval($_POST['count'] ?? 0);
$branchId = intval($_POST['branch_id'] ?? 1); // Default to HQ

if ($productId <= 0 || $count <= 0 || $count > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters (max 100 bags)']);
    exit;
}

try {
    $inventoryController = new InventoryController();
    $result = $inventoryController->addSampleBags($productId, $branchId, $count, $_SESSION['user_id']);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully added {$count} sample bags"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add sample bags']);
    }
} catch (Exception $e) {
    error_log("Error adding sample bags: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>