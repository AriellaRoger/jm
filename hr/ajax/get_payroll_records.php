<?php
// File: hr/ajax/get_payroll_records.php
// Get payroll records

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

// Only administrators can view payroll records
if ($_SESSION['user_role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $hrController = new HRController();

    // Get employee filter if provided
    $employeeId = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;

    $records = $hrController->getPayrollRecords($employeeId);

    echo json_encode([
        'success' => true,
        'records' => $records
    ]);

} catch (Exception $e) {
    error_log("Get payroll records error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load payroll records']);
}
?>