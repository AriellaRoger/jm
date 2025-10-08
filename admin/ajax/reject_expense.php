<?php
// File: admin/ajax/reject_expense.php
// Reject expense request

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ExpenseController.php';

header('Content-Type: application/json');

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only administrators can reject expenses
if ($_SESSION['user_role'] !== 'Administrator') {
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

    if (!$input || empty($input['expense_id']) || empty($input['rejection_reason'])) {
        throw new Exception('Expense ID and rejection reason are required');
    }

    $expenseId = intval($input['expense_id']);
    $rejectionReason = trim($input['rejection_reason']);

    $expenseController = new ExpenseController();
    $result = $expenseController->rejectExpense($expenseId, $_SESSION['user_id'], $rejectionReason);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Expense rejected successfully'
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Reject expense error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>