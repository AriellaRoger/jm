<?php
// File: hr/ajax/process_leave_request.php
// Approve or reject leave request

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/HRController.php';

header('Content-Type: application/json');

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Only administrators can process leave requests
if ($_SESSION['user_role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    if (empty($input['request_id']) || empty($input['action'])) {
        throw new Exception('Request ID and action are required');
    }

    $requestId = intval($input['request_id']);
    $action = trim($input['action']);
    $notes = trim($input['notes'] ?? '');

    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid action');
    }

    $hrController = new HRController();
    $result = $hrController->processLeaveRequest($requestId, $action, $_SESSION['user_id'], $notes);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Leave request {$action}d successfully"
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Process leave request error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>