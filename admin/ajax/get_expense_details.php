<?php
// File: admin/ajax/get_expense_details.php
// Get detailed expense information for admin review

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

// Only administrators can access expense details
if ($_SESSION['user_role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    if (empty($_GET['expense_id'])) {
        throw new Exception('Expense ID is required');
    }

    $expenseId = intval($_GET['expense_id']);

    $pdo = new PDO('mysql:host=localhost;dbname=jmerp', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get detailed expense information
    $sql = "SELECT e.*, et.name as expense_type, et.category,
                   u.full_name as requested_by, u.email as requester_email, u.phone as requester_phone,
                   b.name as branch_name,
                   a.full_name as approved_by_name
            FROM expenses e
            JOIN expense_types et ON e.expense_type_id = et.id
            JOIN users u ON e.user_id = u.id
            JOIN branches b ON e.branch_id = b.id
            LEFT JOIN users a ON e.approved_by = a.id
            WHERE e.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$expenseId]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$expense) {
        throw new Exception('Expense not found');
    }

    echo json_encode([
        'success' => true,
        'expense' => $expense
    ]);

} catch (Exception $e) {
    error_log("Get expense details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>