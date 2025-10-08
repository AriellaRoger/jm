<?php
// File: reports/ajax/get_expense_report.php
// AJAX handler for expense reports (daily, weekly, monthly, yearly, all-time)

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

    $reportType = $input['reportType'] ?? '';
    $branchId = $input['branchId'] ?? null;
    $startDate = $input['startDate'] ?? null;
    $endDate = $input['endDate'] ?? null;
    $year = $input['year'] ?? null;
    $month = $input['month'] ?? null;

    $result = null;

    switch ($reportType) {
        case 'daily':
        case 'weekly':
        case 'custom':
            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'error' => 'Start date and end date required']);
                exit;
            }
            $result = $reportsController->getExpenseReportByDateRange($startDate, $endDate, $branchId);
            break;

        case 'monthly':
            if (!$year || !$month) {
                echo json_encode(['success' => false, 'error' => 'Year and month required']);
                exit;
            }
            $result = $reportsController->getExpenseReportByMonth($year, $month, $branchId);
            break;

        case 'yearly':
            if (!$year) {
                echo json_encode(['success' => false, 'error' => 'Year required']);
                exit;
            }
            $result = $reportsController->getExpenseReportByYear($year, $branchId);
            break;

        case 'all-time':
            $result = $reportsController->getAllTimeExpenseSummary($branchId);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid report type']);
            exit;
    }

    if ($result && $result['success']) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Failed to generate report']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>