<?php
// File: inventory/ajax/create_transfer.php
// AJAX handler for creating transfers from HQ to branches

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/TransferController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only Administrators and Supervisors can create transfers
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (empty($input['to_branch_id']) || empty($input['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Branch and driver are required']);
    exit;
}

if (empty($input['selected_bags']) && empty($input['selected_items'])) {
    echo json_encode(['success' => false, 'message' => 'At least one item must be selected']);
    exit;
}

try {
    $transferController = new TransferController();

    $transferData = [
        'to_branch_id' => $input['to_branch_id'],
        'driver_id' => $input['driver_id'],
        'selected_bags' => $input['selected_bags'] ?? [],
        'selected_items' => $input['selected_items'] ?? [],
        'notes' => $input['notes'] ?? '',
        'created_by' => $_SESSION['user_id']
    ];

    $result = $transferController->createTransferWithDriver($transferData);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'transfer_id' => $result['transfer_id'],
            'transfer_number' => $result['transfer_number'],
            'qr_code' => $result['qr_code'] ?? '',
            'message' => 'Transfer created successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }

} catch (Exception $e) {
    error_log("Error creating transfer: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error creating transfer']);
}
?>