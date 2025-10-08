<?php
// File: controllers/HRController.php
// HR and Payroll management controller

class HRController {
    private $pdo;

    public function __construct() {
        $this->pdo = getDbConnection();
    }

    // Get all employees with details
    public function getEmployees($branchId = null) {
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, ur.role_name,
                       b.name as branch_name, ed.employee_number, ed.job_title,
                       ed.department, ed.basic_salary, ed.allowances, ed.hire_date,
                       ed.contract_type, ed.status as employment_status
                FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
                JOIN branches b ON u.branch_id = b.id
                LEFT JOIN employee_details ed ON u.id = ed.user_id
                WHERE u.status = 'ACTIVE'";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND u.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " ORDER BY ed.hire_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update employee details
    public function updateEmployeeDetails($userId, $data) {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE employee_details SET
                        job_title = ?, department = ?, basic_salary = ?, allowances = ?,
                        contract_type = ?, bank_name = ?, bank_account = ?,
                        emergency_contact_name = ?, emergency_contact_phone = ?
                    WHERE user_id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['job_title'], $data['department'], $data['basic_salary'],
                $data['allowances'], $data['contract_type'], $data['bank_name'],
                $data['bank_account'], $data['emergency_contact_name'],
                $data['emergency_contact_phone'], $userId
            ]);

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Generate leave request number
    private function generateLeaveRequestNumber() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'LR' . $date . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Create leave request
    public function createLeaveRequest($employeeId, $leaveTypeId, $startDate, $endDate, $reason) {
        try {
            $this->pdo->beginTransaction();

            // Calculate days
            $start = new DateTime($startDate);
            $end = new DateTime($endDate);
            $days = $start->diff($end)->days + 1;

            $requestNumber = $this->generateLeaveRequestNumber();

            $sql = "INSERT INTO leave_requests (request_number, employee_id, leave_type_id,
                                              start_date, end_date, days_requested, reason)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$requestNumber, $employeeId, $leaveTypeId, $startDate, $endDate, $days, $reason]);

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $employeeId, 'LEAVE_REQUEST_CREATED', 'HR',
                "Leave request {$requestNumber} created for {$days} days"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'request_number' => $requestNumber];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get leave requests
    public function getLeaveRequests($employeeId = null, $status = null) {
        $sql = "SELECT lr.*, u.full_name as employee_name, lt.name as leave_type,
                       approver.full_name as approved_by_name
                FROM leave_requests lr
                JOIN users u ON lr.employee_id = u.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users approver ON lr.approved_by = approver.id
                WHERE 1=1";

        $params = [];
        if ($employeeId !== null) {
            $sql .= " AND lr.employee_id = ?";
            $params[] = $employeeId;
        }
        if ($status !== null) {
            $sql .= " AND lr.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY lr.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve/Reject leave request
    public function processLeaveRequest($requestId, $action, $approvedBy, $notes = '') {
        try {
            $this->pdo->beginTransaction();

            $status = $action === 'approve' ? 'APPROVED' : 'REJECTED';
            $field = $action === 'approve' ? 'approved_at' : 'rejection_reason';
            $value = $action === 'approve' ? 'NOW()' : '?';

            $sql = "UPDATE leave_requests SET status = ?, approved_by = ?, {$field} = {$value} WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);

            if ($action === 'approve') {
                $stmt->execute([$status, $approvedBy, $requestId]);
            } else {
                $stmt->execute([$status, $approvedBy, $notes, $requestId]);
            }

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Generate payroll number
    private function generatePayrollNumber() {
        $date = date('Ym');
        $sql = "SELECT COUNT(*) as count FROM payroll_records WHERE DATE(created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'PAY' . $date . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Create payroll record
    public function createPayrollRecord($employeeId, $payPeriodStart, $payPeriodEnd, $overtimeHours, $deductions, $createdBy) {
        try {
            $this->pdo->beginTransaction();

            // Get employee salary details
            $sql = "SELECT basic_salary, allowances FROM employee_details WHERE user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch();

            if (!$employee) {
                throw new Exception('Employee details not found');
            }

            $basicSalary = $employee['basic_salary'];
            $allowances = $employee['allowances'];
            $overtimeRate = $basicSalary / 30 / 8; // Daily rate / 8 hours
            $overtimeAmount = $overtimeHours * $overtimeRate;
            $grossSalary = $basicSalary + $allowances + $overtimeAmount;
            $netSalary = $grossSalary - $deductions;

            $payrollNumber = $this->generatePayrollNumber();

            $sql = "INSERT INTO payroll_records (payroll_number, employee_id, pay_period_start, pay_period_end,
                                               basic_salary, allowances, overtime_hours, overtime_rate, overtime_amount,
                                               gross_salary, deductions, net_salary, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $payrollNumber, $employeeId, $payPeriodStart, $payPeriodEnd,
                $basicSalary, $allowances, $overtimeHours, $overtimeRate, $overtimeAmount,
                $grossSalary, $deductions, $netSalary, $createdBy
            ]);

            $this->pdo->commit();
            return ['success' => true, 'payroll_number' => $payrollNumber, 'net_salary' => $netSalary];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get payroll records
    public function getPayrollRecords($employeeId = null, $limit = 50) {
        $sql = "SELECT pr.*, u.full_name as employee_name, ed.employee_number
                FROM payroll_records pr
                JOIN users u ON pr.employee_id = u.id
                JOIN employee_details ed ON u.id = ed.user_id
                WHERE 1=1";

        $params = [];
        if ($employeeId !== null) {
            $sql .= " AND pr.employee_id = ?";
            $params[] = $employeeId;
        }

        $sql .= " ORDER BY pr.created_at DESC LIMIT " . intval($limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get leave types
    public function getLeaveTypes() {
        $sql = "SELECT * FROM leave_types WHERE status = 'ACTIVE' ORDER BY name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get HR statistics
    public function getHRStats() {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM users WHERE status = 'ACTIVE') as total_employees,
                    (SELECT COUNT(*) FROM leave_requests WHERE status = 'PENDING') as pending_leaves,
                    (SELECT COUNT(*) FROM payroll_records WHERE MONTH(created_at) = MONTH(CURDATE())) as monthly_payrolls,
                    (SELECT COALESCE(SUM(net_salary), 0) FROM payroll_records WHERE MONTH(created_at) = MONTH(CURDATE())) as monthly_payroll_total";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>