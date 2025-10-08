<?php
// File: inventory/ajax/open_bag.php
// AJAX handler for opening product bags
// Accessible by Branch Operators for their branch bags

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/InventoryController.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$serialNumber = trim($_POST['serial_number'] ?? '');
$weight = floatval($_POST['weight'] ?? 0);
$sellingPricePerKg = floatval($_POST['selling_price_per_kg'] ?? 0);

// Debug logging
error_log("Bag opening attempt - Serial: $serialNumber, Weight: $weight, Price: $sellingPricePerKg, User: " . $_SESSION['user_id'] . ", Branch: " . $_SESSION['branch_id']);

if (empty($serialNumber)) {
    echo json_encode(['success' => false, 'message' => 'Serial number is required']);
    exit;
}

if ($weight <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid weight is required']);
    exit;
}

if ($sellingPricePerKg <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid selling price per KG is required']);
    exit;
}

try {
    $inventoryController = new InventoryController();
    $result = $inventoryController->openBag($serialNumber, $weight, $sellingPricePerKg, $_SESSION['user_id'], $_SESSION['branch_id']);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Bag opened successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to open bag']);
    }
} catch (Exception $e) {
    error_log("Error opening bag: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>