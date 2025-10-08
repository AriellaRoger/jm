<?php
// File: purchases/ajax/get_products.php
// Get products for purchase by branch

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
    if (empty($_GET['branch_id'])) {
        throw new Exception('Branch ID is required');
    }

    $branchId = intval($_GET['branch_id']);

    // Non-admin users can only access their own branch
    if ($_SESSION['user_role'] !== 'Administrator' && $branchId != $_SESSION['branch_id']) {
        throw new Exception('Access denied to this branch');
    }

    $purchaseController = new PurchaseController();
    $products = $purchaseController->getProductsForPurchase($branchId);

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    error_log("Get products error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>