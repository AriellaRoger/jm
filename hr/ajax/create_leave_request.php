<?php
// File: hr/ajax/create_leave_request.php
// Create leave request

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

// Only administrators can create leave requests
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
    if (empty($input['employee_id']) || empty($input['leave_type_id']) ||
        empty($input['start_date']) || empty($input['end_date']) || empty($input['reason'])) {
        throw new Exception('All fields are required');
    }

    $employeeId = intval($input['employee_id']);
    $leaveTypeId = intval($input['leave_type_id']);
    $startDate = trim($input['start_date']);
    $endDate = trim($input['end_date']);
    $reason = trim($input['reason']);

    // Validate dates
    if (strtotime($startDate) > strtotime($endDate)) {
        throw new Exception('Start date cannot be after end date');
    }

    $hrController = new HRController();
    $result = $hrController->createLeaveRequest($employeeId, $leaveTypeId, $startDate, $endDate, $reason);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Leave request created successfully',
            'request_number' => $result['request_number']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create leave request error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>