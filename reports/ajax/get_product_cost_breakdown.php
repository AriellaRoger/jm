<?php
// File: reports/ajax/get_product_cost_breakdown.php
// Get product cost breakdown analysis

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
$result = $reportsController->getProductionCostByProduct();

if ($result['success']) {
    echo json_encode(['success' => true, 'products' => $result['product_costs']]);
} else {
    echo json_encode($result);
}
?>