<?php
// File: purchases/ajax/record_payment.php
// Record supplier payment

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

// Only authorized roles can record payments
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
    if (empty($input['supplier_id']) || empty($input['amount']) || empty($input['payment_method']) || empty($input['payment_date'])) {
        throw new Exception('Supplier, amount, payment method, and payment date are required');
    }

    $supplierId = intval($input['supplier_id']);
    $amount = floatval($input['amount']);
    $paymentMethod = trim($input['payment_method']);
    $paymentDate = trim($input['payment_date']);
    $referenceNumber = trim($input['reference_number'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $purchaseId = !empty($input['purchase_id']) ? intval($input['purchase_id']) : null;

    // Validate amount
    if ($amount <= 0) {
        throw new Exception('Payment amount must be greater than 0');
    }

    // Validate payment method
    $validMethods = ['CASH', 'BANK_TRANSFER', 'MOBILE_MONEY', 'CHECK'];
    if (!in_array($paymentMethod, $validMethods)) {
        throw new Exception('Invalid payment method');
    }

    $purchaseController = new PurchaseController();
    $result = $purchaseController->recordSupplierPayment(
        $supplierId,
        $amount,
        $paymentMethod,
        $referenceNumber,
        $paymentDate,
        $notes,
        $_SESSION['user_id'],
        $_SESSION['branch_id'],
        $purchaseId
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'payment_number' => $result['payment_number']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Record payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>