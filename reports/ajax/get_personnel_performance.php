<?php
// File: reports/ajax/get_personnel_performance.php
// Get personnel performance analysis (officers and supervisors)

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
$reportType = $input['report_type'] ?? 'both';

$reportsController = new ReportsController();

try {
    $performance = [];

    if ($reportType === 'officers' || $reportType === 'both') {
        $officerResult = $reportsController->getProductionOfficerPerformance();
        if ($officerResult['success']) {
            $performance['officers'] = $officerResult['officer_performance'];
        }
    }

    if ($reportType === 'supervisors' || $reportType === 'both') {
        $supervisorResult = $reportsController->getSupervisorPerformance();
        if ($supervisorResult['success']) {
            $performance['supervisors'] = $supervisorResult['supervisor_performance'];
        }
    }

    echo json_encode(['success' => true, 'performance' => $performance]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading personnel performance: ' . $e->getMessage()]);
}
?>