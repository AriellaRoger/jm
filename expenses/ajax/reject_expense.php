<?php
// File: expenses/ajax/reject_expense.php
// Reject expense request

error_reporting(0);
ini_set('display_errors', 0);
ob_start(); // Start output buffering to catch any unwanted output

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clean any output that might have been generated
ob_clean();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../controllers/AuthController.php';
    require_once __DIR__ . '/../../controllers/ExpenseController.php';

    $authController = new AuthController();
    if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['expense_id']) || !isset($input['rejection_reason'])) {
        echo json_encode(['success' => false, 'error' => 'Expense ID and rejection reason required']);
        exit;
    }

    $expenseController = new ExpenseController();
    $result = $expenseController->rejectExpense(
        $input['expense_id'],
        $_SESSION['user_id'],
        $input['rejection_reason']
    );

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error processing request: ' . $e->getMessage()]);
}
?>