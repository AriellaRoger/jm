<?php
// File: purchases/ajax/create_supplier.php
// Create new supplier

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

// Only supervisors and administrators can create suppliers
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
    if (empty($input['name']) || empty($input['phone'])) {
        throw new Exception('Company name and phone are required');
    }

    $name = trim($input['name']);
    $contactPerson = trim($input['contact_person'] ?? '');
    $phone = trim($input['phone']);
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    $paymentTerms = trim($input['payment_terms'] ?? 'Net 30');
    $creditLimit = floatval($input['credit_limit'] ?? 0);

    $purchaseController = new PurchaseController();
    $result = $purchaseController->createSupplier(
        $name,
        $contactPerson,
        $phone,
        $email,
        $address,
        $paymentTerms,
        $creditLimit,
        $_SESSION['user_id']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Supplier created successfully',
            'supplier_code' => $result['supplier_code']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create supplier error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>