<?php
// File: purchases/ajax/get_purchases.php
// Get purchases with user role filtering

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/PurchaseController.php';

header('Content-Type: application/json');

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only authorized roles can access
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $purchaseController = new PurchaseController();

    // Determine branch filter based on user role
    $branchId = $_SESSION['user_role'] === 'Administrator' ? null : $_SESSION['branch_id'];

    $purchases = $purchaseController->getPurchases($branchId);

    echo json_encode([
        'success' => true,
        'purchases' => $purchases
    ]);

} catch (Exception $e) {
    error_log("Get purchases error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load purchases']);
}
?>