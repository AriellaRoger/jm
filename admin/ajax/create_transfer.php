<?php
// File: admin/ajax/create_transfer.php
// AJAX handler to create new transfers with bags and bulk items
// Administrator and Supervisor access only

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/TransferController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($input['to_branch_id']) || empty($input['to_branch_id'])) {
    echo json_encode(['success' => false, 'message' => 'Destination branch is required']);
    exit;
}

// Validate that at least one item is selected
$selectedBags = isset($input['selected_bags']) ? $input['selected_bags'] : [];
$bulkItems = isset($input['bulk_items']) ? $input['bulk_items'] : [];

if (empty($selectedBags) && empty($bulkItems)) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one bag or specify quantities for bulk items']);
    exit;
}

try {
    $transferController = new TransferController();

    // Prepare transfer data
    $transferData = [
        'to_branch_id' => (int)$input['to_branch_id'],
        'created_by' => $_SESSION['user_id'],
        'selected_bags' => $selectedBags,
        'bulk_items' => $bulkItems
    ];

    $result = $transferController->createTransfer($transferData);

    if ($result['success']) {
        // Get complete transfer details for printing
        $transferDetails = $transferController->getTransferForPrint($result['transfer_id']);

        echo json_encode([
            'success' => true,
            'message' => 'Transfer created successfully',
            'transfer_id' => $result['transfer_id'],
            'transfer_number' => $result['transfer_number'],
            'print_ready' => true,
            'print_url' => BASE_URL . '/admin/ajax/print_transfer_form.php?id=' . $result['transfer_id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Error creating transfer'
        ]);
    }

} catch (Exception $e) {
    error_log("Error creating transfer: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>