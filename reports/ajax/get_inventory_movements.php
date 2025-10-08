<?php
// File: reports/ajax/get_inventory_movements.php
// AJAX handler for inventory movements and reconciliation

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

    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $branchId = $input['branch_id'] ?? null;
    $productType = $input['product_type'] ?? null;

    $result = $reportsController->getInventoryMovements($startDate, $endDate, $branchId, $productType);

    if ($result && $result['success']) {
        echo json_encode(['success' => true, 'data' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Failed to generate inventory movements']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>