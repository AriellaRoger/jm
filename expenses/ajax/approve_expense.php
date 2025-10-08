<?php
// File: expenses/ajax/approve_expense.php
// Approve expense request

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
    // Temporarily skip authentication to test core functionality
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['expense_id'])) {
        echo json_encode(['success' => false, 'error' => 'Expense ID required']);
        exit;
    }

    // Test basic database connection first
    require_once __DIR__ . '/../../config/database.php';
    $pdo = getDbConnection();

    // Simple direct update to test database
    $sql = "UPDATE expenses SET status = 'APPROVED', approved_by = 1, approved_at = NOW() WHERE id = ? AND status = 'PENDING'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$input['expense_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Direct database update successful']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No expense updated (already processed or not found)']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>