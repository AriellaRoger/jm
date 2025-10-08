<?php
// File: reports/ajax/get_payment_analysis.php
// Get payment method analysis and credit vs cash comparison

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

$reportsController = new ReportsController();

try {
    // Get both payment method analysis and credit vs cash analysis
    $paymentResult = $reportsController->getPaymentMethodAnalysis($startDate, $endDate, $branchId);
    $creditResult = $reportsController->getCreditVsCashAnalysis($startDate, $endDate, $branchId);

    if ($paymentResult['success'] && $creditResult['success']) {
        echo json_encode([
            'success' => true,
            'payment_methods' => $paymentResult['payment_analysis'],
            'credit_vs_cash' => $creditResult['analysis']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error loading payment analysis']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading payment analysis: ' . $e->getMessage()]);
}
?>