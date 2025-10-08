<?php
// File: inventory/ajax/confirm_transfer.php
// AJAX handler for confirming transfer receipt and updating inventory

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/TransferController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['transfer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Transfer ID required']);
    exit;
}

try {
    $transferController = new TransferController();
    $result = $transferController->confirmTransfer($input['transfer_id'], $_SESSION['user_id']);

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Error confirming transfer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error confirming transfer']);
}
?>