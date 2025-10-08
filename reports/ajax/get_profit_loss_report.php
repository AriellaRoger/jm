<?php
// File: reports/ajax/get_profit_loss_report.php
// AJAX handler for Profit & Loss reports with time-based filtering

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
    if (!in_array($currentUser['role_name'], ['Administrator'])) {
        echo json_encode(['success' => false, 'error' => 'Access denied - Administrator only']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $reportType = $input['report_type'] ?? 'all-time';
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $year = $input['year'] ?? null;

    $result = $reportsController->getProfitAndLossReport($reportType, $startDate, $endDate, $year);

    if ($result && $result['success']) {
        // Remove the outer 'success' wrapper and send the data directly
        unset($result['success']);
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Failed to generate P&L report']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>