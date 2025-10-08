<?php
// File: expenses/ajax/create_expense.php
// Create new expense request

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ExpenseController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
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
    if (empty($input['expense_type_id']) || empty($input['description']) || empty($input['amount'])) {
        throw new Exception('All fields are required');
    }

    $expenseTypeId = intval($input['expense_type_id']);
    $description = trim($input['description']);
    $amount = floatval($input['amount']);
    $fleetVehicleId = isset($input['fleet_vehicle_id']) ? intval($input['fleet_vehicle_id']) : null;
    $machineId = isset($input['machine_id']) ? intval($input['machine_id']) : null;

    if ($amount <= 0) {
        throw new Exception('Amount must be greater than 0');
    }

    $expenseController = new ExpenseController();
    $result = $expenseController->createExpenseRequest(
        $_SESSION['user_id'],
        $_SESSION['branch_id'],
        $expenseTypeId,
        $description,
        $amount,
        $fleetVehicleId,
        $machineId
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Expense request submitted successfully',
            'expense_number' => $result['expense_number']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create expense error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>