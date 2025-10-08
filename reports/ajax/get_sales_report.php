<?php
// File: reports/ajax/get_sales_report.php
// Generate comprehensive sales reports based on type and parameters

error_reporting(0);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ReportsController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['report_type'])) {
    echo json_encode(['success' => false, 'message' => 'Report type required']);
    exit;
}

$reportsController = new ReportsController();
$reportType = $input['report_type'];
$branchId = $input['branch_id'] ?? null;

try {
    switch ($reportType) {
        case 'daily':
        case 'weekly':
        case 'custom':
            $startDate = $input['start_date'] ?? date('Y-m-d');
            $endDate = $input['end_date'] ?? date('Y-m-d');
            $result = $reportsController->getSalesReportByDateRange($startDate, $endDate, $branchId);
            $report = $result['success'] ? $result : null;
            break;

        case 'monthly':
            $year = $input['year'] ?? date('Y');
            $result = $reportsController->getSalesReportByMonth($year, $branchId);
            $report = $result['success'] ? $result : null;
            break;

        case 'yearly':
            $result = $reportsController->getSalesReportByYear($branchId);
            $report = $result['success'] ? $result : null;
            break;

        case 'all_time':
            $result = $reportsController->getAllTimeSalesSummary($branchId);
            $report = $result['success'] ? $result['summary'] : null;
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
            exit;
    }

    if ($result['success']) {
        echo json_encode(['success' => true, 'report' => $report, 'report_type' => $reportType]);
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error generating sales report: ' . $e->getMessage()]);
}
?>