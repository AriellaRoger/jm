<?php
// File: hr/ajax/create_payroll.php
// Generate payroll record

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

// Only administrators can create payroll
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
    if (empty($input['employee_id']) || empty($input['pay_period_start']) || empty($input['pay_period_end'])) {
        throw new Exception('Employee, pay period start, and pay period end are required');
    }

    $employeeId = intval($input['employee_id']);
    $payPeriodStart = trim($input['pay_period_start']);
    $payPeriodEnd = trim($input['pay_period_end']);
    $overtimeHours = floatval($input['overtime_hours'] ?? 0);
    $deductions = floatval($input['deductions'] ?? 0);

    // Validate dates
    if (strtotime($payPeriodStart) > strtotime($payPeriodEnd)) {
        throw new Exception('Pay period start cannot be after end date');
    }

    // Validate numeric values
    if ($overtimeHours < 0 || $deductions < 0) {
        throw new Exception('Overtime hours and deductions cannot be negative');
    }

    $hrController = new HRController();
    $result = $hrController->createPayrollRecord(
        $employeeId,
        $payPeriodStart,
        $payPeriodEnd,
        $overtimeHours,
        $deductions,
        $_SESSION['user_id']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Payroll generated successfully',
            'payroll_number' => $result['payroll_number'],
            'net_salary' => $result['net_salary']
        ]);
    } else {
        throw new Exception($result['error']);
    }

} catch (Exception $e) {
    error_log("Create payroll error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>