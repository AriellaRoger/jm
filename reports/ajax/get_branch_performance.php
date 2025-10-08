<?php
// File: reports/ajax/get_branch_performance.php
// Get branch performance comparison and analysis

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
$startDate = $input['start_date'] ?? null;
$endDate = $input['end_date'] ?? null;

$reportsController = new ReportsController();

try {
    $result = $reportsController->getBranchPerformanceComparison($startDate, $endDate);

    if ($result['success']) {
        echo json_encode(['success' => true, 'branches' => $result['branch_performance']]);
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading branch performance: ' . $e->getMessage()]);
}
?>