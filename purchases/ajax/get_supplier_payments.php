<?php
// File: purchases/ajax/get_supplier_payments.php
// Get supplier payments with filtering

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

    // Get supplier filter if provided
    $supplierId = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : null;

    $payments = $purchaseController->getSupplierPayments($supplierId);

    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);

} catch (Exception $e) {
    error_log("Get supplier payments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load payments']);
}
?>