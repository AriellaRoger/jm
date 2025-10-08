<?php
// File: controllers/ExpenseController.php
// Expense management controller for handling expense requests and approvals

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/NotificationManager.php';

class ExpenseController {
    private $pdo;
    private $activityLogger;
    private $notificationManager;

    public function __construct() {
        $this->pdo = getDbConnection();
        $this->activityLogger = new ActivityLogger();
        $this->notificationManager = new NotificationManager();
    }

    // Generate expense number
    private function generateExpenseNumber() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM expenses WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'EXP' . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Get expense types
    public function getExpenseTypes() {
        $sql = "SELECT * FROM expense_types WHERE status = 'ACTIVE' ORDER BY category, name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create expense request
    public function createExpenseRequest($userId, $branchId, $expenseTypeId, $description, $amount, $fleetVehicleId = null, $machineId = null) {
        try {
            $this->pdo->beginTransaction();

            $expenseNumber = $this->generateExpenseNumber();

            $sql = "INSERT INTO expenses (expense_number, user_id, branch_id, expense_type_id, description, amount, fleet_vehicle_id, machine_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expenseNumber, $userId, $branchId, $expenseTypeId, $description, $amount, $fleetVehicleId, $machineId]);

            $expenseId = $this->pdo->lastInsertId();

            // Get expense type and user details for notifications
            $expenseType = $this->getExpenseTypeById($expenseTypeId);
            $user = $this->getUserById($userId);
            $branch = $this->getBranchById($branchId);

            // Enhanced activity logging and notifications
            $this->activityLogger->logExpense(
                $userId,
                'EXPENSE_REQUESTED',
                "Expense request {$expenseNumber} for {$expenseType['name']} - TZS " . number_format($amount, 2),
                [
                    'entity_type' => 'expense',
                    'entity_id' => $expenseId,
                    'expense_number' => $expenseNumber,
                    'expense_type' => $expenseType['name'],
                    'amount' => $amount,
                    'branch_id' => $branchId,
                    'branch_name' => $branch['name'],
                    'fleet_vehicle_id' => $fleetVehicleId,
                    'machine_id' => $machineId
                ]
            );

            // Notify administrators about new expense request requiring approval
            $vehicleInfo = $fleetVehicleId ? " for vehicle " . $this->getVehicleNumber($fleetVehicleId) : "";
            $machineInfo = $machineId ? " for machine " . $this->getMachineNumber($machineId) : "";

            $this->notificationManager->createForRole(
                'Administrator',
                'New Expense Request Awaiting Approval',
                "{$user['full_name']} from {$branch['name']} requested TZS " . number_format($amount, 0) . " for {$expenseType['name']}{$vehicleInfo}{$machineInfo}",
                'APPROVAL_REQUIRED',
                'EXPENSES',
                [
                    'entity_type' => 'expense',
                    'entity_id' => $expenseId,
                    'expense_number' => $expenseNumber,
                    'action_url' => "/admin/expenses.php?expense_id=$expenseId",
                    'urgency' => $amount >= 100000 ? 'high' : 'normal'
                ]
            );

            $this->pdo->commit();
            return ['success' => true, 'expense_number' => $expenseNumber];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get expenses with filters
    public function getExpensesWithFilters($userId = null, $branchId = null, $status = null, $limit = 50) {
        $sql = "SELECT e.*, et.name as expense_type, et.category,
                       u.full_name as requested_by, u.email as requester_email,
                       b.name as branch_name,
                       a.full_name as approved_by_name
                FROM expenses e
                JOIN expense_types et ON e.expense_type_id = et.id
                JOIN users u ON e.user_id = u.id
                JOIN branches b ON e.branch_id = b.id
                LEFT JOIN users a ON e.approved_by = a.id
                WHERE 1=1";

        $params = [];

        if ($userId !== null) {
            $sql .= " AND e.user_id = ?";
            $params[] = $userId;
        }

        if ($branchId !== null) {
            $sql .= " AND e.branch_id = ?";
            $params[] = $branchId;
        }

        if ($status !== null) {
            $sql .= " AND e.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY e.created_at DESC LIMIT " . intval($limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve expense
    public function approveExpense($expenseId, $approvedBy) {
        try {
            $this->pdo->beginTransaction();

            // Get expense details
            $sql = "SELECT e.*, u.full_name as requester_name FROM expenses e
                    JOIN users u ON e.user_id = u.id WHERE e.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if (!$expense) {
                throw new Exception('Expense not found');
            }

            if ($expense['status'] !== 'PENDING') {
                throw new Exception('Expense has already been processed');
            }

            // Update expense status
            $sql = "UPDATE expenses SET status = 'APPROVED', approved_by = ?, approved_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$approvedBy, $expenseId]);

            // Enhanced activity logging and notifications
            $expenseType = $this->getExpenseTypeById($expense['expense_type_id']);
            $branch = $this->getBranchById($expense['branch_id']);

            $this->activityLogger->logExpense(
                $approvedBy,
                'EXPENSE_APPROVED',
                "Expense {$expense['expense_number']} for {$expenseType['name']} approved - TZS " . number_format($expense['amount'], 2),
                [
                    'entity_type' => 'expense',
                    'entity_id' => $expenseId,
                    'expense_number' => $expense['expense_number'],
                    'expense_type' => $expenseType['name'],
                    'amount' => $expense['amount'],
                    'requester_id' => $expense['user_id'],
                    'requester_name' => $expense['requester_name'],
                    'branch_id' => $expense['branch_id'],
                    'branch_name' => $branch['name']
                ]
            );

            // Notify the expense requester about approval
            $this->notificationManager->createForUser(
                $expense['user_id'],
                'Expense Request Approved',
                "Your expense request {$expense['expense_number']} for TZS " . number_format($expense['amount'], 0) . " ({$expenseType['name']}) has been approved",
                'SUCCESS',
                'EXPENSES',
                [
                    'entity_type' => 'expense',
                    'entity_id' => $expenseId,
                    'expense_number' => $expense['expense_number'],
                    'action_url' => "/expenses/index.php?expense_id=$expenseId"
                ]
            );

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Reject expense
    public function rejectExpense($expenseId, $rejectedBy, $rejectionReason) {
        try {
            $this->pdo->beginTransaction();

            // Get expense details
            $sql = "SELECT e.*, u.full_name as requester_name FROM expenses e
                    JOIN users u ON e.user_id = u.id WHERE e.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if (!$expense) {
                throw new Exception('Expense not found');
            }

            if ($expense['status'] !== 'PENDING') {
                throw new Exception('Expense has already been processed');
            }

            // Update expense status
            $sql = "UPDATE expenses SET status = 'REJECTED', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$rejectedBy, $rejectionReason, $expenseId]);

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $rejectedBy,
                'EXPENSE_REJECTED',
                'EXPENSES',
                "Expense {$expense['expense_number']} rejected for {$expense['requester_name']}"
            ]);

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get expense statistics
    public function getExpenseStats($branchId = null) {
        $sql = "SELECT
                    COUNT(CASE WHEN status = 'PENDING' THEN 1 END) as pending,
                    COUNT(CASE WHEN status = 'APPROVED' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'REJECTED' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'PAID' THEN 1 END) as paid,
                    SUM(CASE WHEN status = 'APPROVED' THEN amount ELSE 0 END) as approved_amount,
                    SUM(CASE WHEN status = 'PAID' THEN amount ELSE 0 END) as paid_amount
                FROM expenses
                WHERE 1=1";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Helper methods for notification system
    private function getExpenseTypeById($expenseTypeId) {
        try {
            $sql = "SELECT * FROM expense_types WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expenseTypeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting expense type: " . $e->getMessage());
            return ['name' => 'Unknown Expense Type'];
        }
    }

    private function getUserById($userId) {
        try {
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user: " . $e->getMessage());
            return ['full_name' => 'Unknown User'];
        }
    }

    private function getBranchById($branchId) {
        try {
            $sql = "SELECT * FROM branches WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$branchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting branch: " . $e->getMessage());
            return ['name' => 'Unknown Branch'];
        }
    }

    private function getVehicleNumber($vehicleId) {
        try {
            $sql = "SELECT vehicle_number FROM fleet_vehicles WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$vehicleId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['vehicle_number'] : 'Unknown Vehicle';
        } catch (Exception $e) {
            return 'Unknown Vehicle';
        }
    }

    private function getMachineNumber($machineId) {
        try {
            $sql = "SELECT machine_number FROM company_machines WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$machineId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['machine_number'] : 'Unknown Machine';
        } catch (Exception $e) {
            return 'Unknown Machine';
        }
    }
}
?>