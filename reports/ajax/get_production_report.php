<?php
// File: reports/ajax/get_production_report.php
// Generate production cost reports based on type and parameters

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

try {
    switch ($reportType) {
        case 'batch':
            $result = $reportsController->getProductionCostByBatch();
            $report = $result['success'] ? $result['batches'] : null;
            break;

        case 'daily':
        case 'custom':
            $startDate = $input['start_date'] ?? date('Y-m-01');
            $endDate = $input['end_date'] ?? date('Y-m-d');
            $result = $reportsController->getProductionCostByDateRange($startDate, $endDate);
            $report = $result['success'] ? $result['daily_reports'] : null;
            break;

        case 'monthly':
            $year = $input['year'] ?? date('Y');
            $result = $reportsController->getProductionCostByMonth($year);
            $report = $result['success'] ? $result['monthly_reports'] : null;
            break;

        case 'yearly':
            $result = $reportsController->getProductionCostByYear();
            $report = $result['success'] ? $result['yearly_reports'] : null;
            break;

        case 'all_time':
            $result = $reportsController->getAllTimeProductionCost();
            $report = $result['success'] ? $result['all_time_summary'] : null;
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
    echo json_encode(['success' => false, 'message' => 'Error generating report: ' . $e->getMessage()]);
}
?>