<?php
// File: purchases/ajax/get_purchase_details.php
// Get detailed purchase information with items

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

if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!isset($_GET['purchase_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Purchase ID is required']);
    exit;
}

try {
    $purchaseId = intval($_GET['purchase_id']);
    $purchaseController = new PurchaseController();

    $result = $purchaseController->getPurchaseDetails($purchaseId);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'purchase' => $result['purchase'],
            'items' => $result['items']
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }

} catch (Exception $e) {
    error_log("Get purchase details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>