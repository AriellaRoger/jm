<?php
// File: reports/ajax/get_efficiency_metrics.php
// Get production efficiency metrics

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ReportsController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$reportsController = new ReportsController();
$result = $reportsController->getProductionEfficiencyMetrics();

if ($result['success']) {
    echo json_encode(['success' => true, 'metrics' => $result['efficiency_metrics']]);
} else {
    echo json_encode($result);
}
?>