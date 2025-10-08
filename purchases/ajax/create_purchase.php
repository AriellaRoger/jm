<?php
// File: purchases/ajax/create_purchase.php
// Create new purchase order

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

// Only authorized roles can create purchases
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    if (empty($input['supplier_id']) || empty($input['branch_id']) || empty($input['purchase_date']) ||
        empty($input['payment_method']) || empty($input['items'])) {
        throw new Exception('All required fields must be provided');
    }

    $supplierId = intval($input['supplier_id']);
    $branchId = intval($input['branch_id']);
    $purchaseDate = $input['purchase_date'];
    $paymentMethod = $input['payment_method'];
    $items = $input['items'];
    $notes = trim($input['notes'] ?? '');

    // Validate branch access for non-admin users
    if ($_SESSION['user_role'] !== 'Administrator' && $branchId != $_SESSION['branch_id']) {
        throw new Exception('You can only create purchases for your branch');
    }

    if (empty($items)) {
        throw new Exception('At least one item is required');
    }

    // Validate items
    foreach ($items as $item) {
        if (empty($item['product_id']) || empty($item['product_type']) || empty($item['product_name']) ||
            !isset($item['quantity']) || !isset($item['unit_cost']) || $item['quantity'] <= 0 || $item['unit_cost'] <= 0) {
            throw new Exception('Invalid item data provided');
        }
    }

    $purchaseController = new PurchaseController();
    $result = $purchaseController->createPurchase(
        $supplierId,
        $branchId,
        $_SESSION['user_id'],
        $purchaseDate,
        $paymentMethod,
        $items,
        $notes
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Purchase created successfully',
            'purchase_number' => $result['purchase_number']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create purchase error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>