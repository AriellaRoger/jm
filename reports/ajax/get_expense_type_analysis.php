<?php
// File: reports/ajax/get_expense_type_analysis.php
// AJAX handler for expense type analysis and breakdown

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_clean();
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../controllers/AuthController.php';
    require_once __DIR__ . '/../../controllers/ReportsController.php';

    $authController = new AuthController();
    $reportsController = new ReportsController();

    if (!$authController->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $currentUser = $authController->getCurrentUser();
    if (!in_array($currentUser['role_name'], ['Administrator', 'Supervisor'])) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $startDate = $input['startDate'] ?? null;
    $endDate = $input['endDate'] ?? null;
    $branchId = $input['branchId'] ?? null;

    if (!$startDate || !$endDate) {
        echo json_encode(['success' => false, 'error' => 'Start date and end date required']);
        exit;
    }

    $result = $reportsController->getExpenseTypeAnalysis($startDate, $endDate, $branchId);

    if ($result && $result['success']) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Failed to generate expense type analysis']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>