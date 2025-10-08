<?php
// File: controllers/NotificationManager.php
// Role-based notification system for real-time user alerts
// Manages notifications for approvals, alerts, and system events

require_once __DIR__ . '/../config/database.php';

class NotificationManager {
    private $pdo;

    public function __construct() {
        $this->pdo = getDbConnection();
    }

    /**
     * Create a notification for specific user(s)
     */
    public function create($userId, $title, $message, $type, $module, $options = []) {
        return $this->createForUser($userId, $title, $message, $type, $module, $options);
    }

    public function createForUser($userId, $title, $message, $type, $module, $options = []) {
        try {
            $createdBy = $_SESSION['user_id'] ?? 1;
            $branchId = $_SESSION['branch_id'] ?? 1;

            $sql = "INSERT INTO notifications
                    (user_id, title, message, type, module, entity_type, entity_id,
                     action_url, is_urgent, created_by, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $userId,
                $title,
                $message,
                $type,
                $module,
                $options['entity_type'] ?? null,
                $options['entity_id'] ?? null,
                $options['action_url'] ?? null,
                $options['is_urgent'] ?? false,
                $createdBy,
                $branchId
            ]);

            if ($result) {
                // Notification created successfully
                error_log("Notification created for user $userId: $title");
            }

            return $result;
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create notifications for multiple users by role
     */
    public function createForRole($roleNames, $title, $message, $type, $module, $options = []) {
        try {
            if (!is_array($roleNames)) {
                $roleNames = [$roleNames];
            }

            $placeholders = str_repeat('?,', count($roleNames) - 1) . '?';
            $sql = "SELECT u.id FROM users u
                    JOIN user_roles ur ON u.role_id = ur.id
                    WHERE ur.role_name IN ({$placeholders})
                    AND u.status = 'ACTIVE'";

            if (isset($options['branch_id'])) {
                $sql .= " AND u.branch_id = ?";
                $roleNames[] = $options['branch_id'];
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($roleNames);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            foreach ($users as $userId) {
                if ($this->create($userId, $title, $message, $type, $module, $options)) {
                    $count++;
                }
            }

            return $count;
        } catch (Exception $e) {
            error_log("Failed to create role notifications: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get notifications for a user
     */
    public function getForUser($userId, $limit = 20, $unreadOnly = false) {
        try {
            $sql = "SELECT n.*, u.name as created_by_name
                    FROM notifications n
                    JOIN users u ON n.created_by = u.id
                    WHERE n.user_id = ?";

            $params = [$userId];

            if ($unreadOnly) {
                $sql .= " AND n.is_read = FALSE";
            }

            $sql .= " ORDER BY n.is_urgent DESC, n.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch user notifications: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread count for user
     */
    public function getUnreadCount($userId) {
        try {
            $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Failed to fetch unread count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        try {
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW()
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($userId) {
        try {
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW()
                    WHERE user_id = ? AND is_read = FALSE";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Module-specific notification methods
     */
    public function notifyExpenseApproval($expenseId, $expenseNumber, $amount, $isApproved) {
        try {
            // Get expense details
            $sql = "SELECT e.user_id, u.name as user_name FROM expenses e
                    JOIN users u ON e.user_id = u.id WHERE e.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch();

            if ($expense) {
                $status = $isApproved ? 'approved' : 'rejected';
                $type = $isApproved ? 'SUCCESS' : 'ERROR';
                $title = "Expense {$status}";
                $message = "Your expense {$expenseNumber} (TZS " . number_format($amount) . ") has been {$status}.";

                return $this->create($expense['user_id'], $title, $message, $type, 'EXPENSES', [
                    'entity_type' => 'expense',
                    'entity_id' => $expenseId,
                    'action_url' => BASE_URL . '/expenses/index.php?view=' . $expenseId
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to notify expense approval: " . $e->getMessage());
        }
        return false;
    }

    public function notifyTransferRequest($transferId, $transferNumber, $requestingBranchName) {
        // Notify supervisors and administrators about new transfer requests
        $title = "New Transfer Request";
        $message = "Transfer request {$transferNumber} from {$requestingBranchName} requires approval.";

        return $this->createForRole(['Administrator', 'Supervisor'], $title, $message, 'APPROVAL_REQUIRED', 'TRANSFERS', [
            'entity_type' => 'transfer',
            'entity_id' => $transferId,
            'action_url' => BASE_URL . '/inventory/transfers.php?view=' . $transferId,
            'is_urgent' => true
        ]);
    }

    public function notifyLowStock($productName, $currentStock, $minStock, $branchName) {
        // Notify administrators and branch supervisors about low stock
        $title = "Low Stock Alert";
        $message = "{$productName} is running low at {$branchName}. Current: {$currentStock}, Minimum: {$minStock}.";

        return $this->createForRole(['Administrator', 'Supervisor'], $title, $message, 'WARNING', 'INVENTORY', [
            'action_url' => BASE_URL . '/inventory/index.php',
            'is_urgent' => true
        ]);
    }

    public function notifyOrderApproval($orderId, $orderNumber, $customerName, $isApproved) {
        try {
            // Get order details
            $sql = "SELECT o.requested_by FROM customer_orders o WHERE o.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if ($order) {
                $status = $isApproved ? 'approved' : 'rejected';
                $type = $isApproved ? 'SUCCESS' : 'ERROR';
                $title = "Order {$status}";
                $message = "Customer order {$orderNumber} for {$customerName} has been {$status}.";

                return $this->create($order['requested_by'], $title, $message, $type, 'ORDERS', [
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'action_url' => BASE_URL . '/orders/index.php?view=' . $orderId
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to notify order approval: " . $e->getMessage());
        }
        return false;
    }

    public function notifyProductionComplete($batchId, $batchNumber, $productName, $bagsProduced) {
        // Notify supervisors and administrators about production completion
        $title = "Production Completed";
        $message = "Production batch {$batchNumber} for {$productName} completed. {$bagsProduced} bags produced.";

        return $this->createForRole(['Administrator', 'Supervisor'], $title, $message, 'SUCCESS', 'PRODUCTION', [
            'entity_type' => 'production_batch',
            'entity_id' => $batchId,
            'action_url' => BASE_URL . '/admin/production.php?view=' . $batchId
        ]);
    }

    public function notifyPaymentReceived($customerId, $customerName, $amount, $paymentMethod) {
        // Notify administrators and branch operators about customer payments
        $title = "Payment Received";
        $message = "Payment of TZS " . number_format($amount) . " received from {$customerName} via {$paymentMethod}.";

        return $this->createForRole(['Administrator', 'Branch Operator'], $title, $message, 'SUCCESS', 'SALES', [
            'entity_type' => 'payment',
            'entity_id' => $customerId,
            'action_url' => BASE_URL . '/sales/pos.php'
        ]);
    }
}

// Global function for easy notification creation
function createNotification($userId, $title, $message, $type, $module, $options = []) {
    static $manager = null;
    if ($manager === null) {
        $manager = new NotificationManager();
    }
    return $manager->create($userId, $title, $message, $type, $module, $options);
}

function notifyRole($roleNames, $title, $message, $type, $module, $options = []) {
    static $manager = null;
    if ($manager === null) {
        $manager = new NotificationManager();
    }
    return $manager->createForRole($roleNames, $title, $message, $type, $module, $options);
}
?>