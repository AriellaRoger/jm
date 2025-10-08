<?php
// File: reports/ajax/get_top_customers.php
// Get top customers analysis and performance

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
$branchId = $input['branch_id'] ?? null;
$limit = $input['limit'] ?? 10;

$reportsController = new ReportsController();

try {
    $result = $reportsController->getTopCustomersAnalysis($limit, $startDate, $endDate);

    if ($result['success']) {
        echo json_encode(['success' => true, 'customers' => $result['top_customers']]);
    } else {
        echo json_encode($result);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading top customers: ' . $e->getMessage()]);
}
?>