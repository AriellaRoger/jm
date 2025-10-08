<?php
// File: purchases/ajax/update_supplier.php
// Update supplier information

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

// Only administrators and supervisors can update suppliers
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
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
    if (empty($input['supplier_id']) || empty($input['name']) || empty($input['phone'])) {
        throw new Exception('Supplier ID, name, and phone are required');
    }

    $supplierId = intval($input['supplier_id']);
    $name = trim($input['name']);
    $contactPerson = trim($input['contact_person'] ?? '');
    $phone = trim($input['phone']);
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    $paymentTerms = trim($input['payment_terms'] ?? 'Net 30');
    $creditLimit = floatval($input['credit_limit'] ?? 0);
    $status = trim($input['status'] ?? 'ACTIVE');

    // Validate numeric values
    if ($creditLimit < 0) {
        throw new Exception('Credit limit cannot be negative');
    }

    // Validate status
    if (!in_array($status, ['ACTIVE', 'INACTIVE'])) {
        throw new Exception('Invalid status');
    }

    $purchaseController = new PurchaseController();
    $result = $purchaseController->updateSupplier($supplierId, $name, $contactPerson, $phone, $email, $address, $paymentTerms, $creditLimit, $status, $_SESSION['user_id']);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Supplier updated successfully'
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Update supplier error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>