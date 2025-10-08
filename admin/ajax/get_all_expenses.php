<?php
// File: admin/ajax/get_all_expenses.php
// Get all expenses for admin management

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

// Only administrators can access this
if ($_SESSION['user_role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $expenseController = new ExpenseController();

    $status = $_GET['status'] ?? null;
    $expenses = $expenseController->getExpensesWithFilters(null, null, $status, 100);

    echo json_encode([
        'success' => true,
        'expenses' => $expenses
    ]);

} catch (Exception $e) {
    error_log("Get all expenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load expenses']);
}
?>