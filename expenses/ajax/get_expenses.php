<?php
// File: expenses/ajax/get_expenses.php
// Get expenses with filters

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

try {
    $expenseController = new ExpenseController();

    // Determine filters based on user role
    $userId = $_SESSION['user_role'] === 'Administrator' ? null : $_SESSION['user_id'];
    $branchId = $_SESSION['user_role'] === 'Administrator' ? null : $_SESSION['branch_id'];
    $status = $_GET['status'] ?? null;

    $expenses = $expenseController->getExpensesWithFilters($userId, $branchId, $status);

    echo json_encode([
        'success' => true,
        'expenses' => $expenses
    ]);

} catch (Exception $e) {
    error_log("Get expenses error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load expenses']);
}
?>