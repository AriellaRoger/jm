<?php
// File: controllers/ActivityLogger.php
// Comprehensive activity logging system for all ERP modules
// Tracks user actions, system events, and module interactions

class ActivityLogger {
    private $pdo;

    public function __construct() {
        $this->pdo = getDbConnection();
    }

    /**
     * Log an activity with comprehensive details
     */
    public function log($module, $action, $description, $options = []) {
        try {
            $userId = $_SESSION['user_id'] ?? 1;
            $branchId = $_SESSION['branch_id'] ?? 1;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $sql = "INSERT INTO activity_logs
                    (user_id, module, action, entity_type, entity_id, description, metadata,
                     ip_address, user_agent, branch_id, severity, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $userId,
                $module,
                $action,
                $options['entity_type'] ?? null,
                $options['entity_id'] ?? null,
                $description,
                json_encode($options['metadata'] ?? []),
                $ipAddress,
                $userAgent,
                $branchId,
                $options['severity'] ?? 'LOW',
                $options['status'] ?? 'SUCCESS'
            ]);
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recent activities for administrator dashboard
     */
    public function getRecentActivities($limit = 20, $branchId = null) {
        try {
            $sql = "SELECT a.*, u.name as user_name, u.email, b.name as branch_name, ur.role_name
                    FROM activity_logs a
                    JOIN users u ON a.user_id = u.id
                    JOIN branches b ON a.branch_id = b.id
                    JOIN user_roles ur ON u.role_id = ur.id";

            $params = [];
            if ($branchId) {
                $sql .= " WHERE a.branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " ORDER BY a.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity statistics by module
     */
    public function getModuleStats($days = 30) {
        try {
            $sql = "SELECT module,
                           COUNT(*) as total_activities,
                           COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_count,
                           COUNT(CASE WHEN severity = 'HIGH' OR severity = 'CRITICAL' THEN 1 END) as critical_count
                    FROM activity_logs
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY module
                    ORDER BY total_activities DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch module stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activities by module
     */
    public function getActivitiesByModule($module, $limit = 50) {
        try {
            $sql = "SELECT a.*, u.name as user_name, b.name as branch_name
                    FROM activity_logs a
                    JOIN users u ON a.user_id = u.id
                    JOIN branches b ON a.branch_id = b.id
                    WHERE a.module = ?
                    ORDER BY a.created_at DESC
                    LIMIT ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$module, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch module activities: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get activity details for full view
     */
    public function getActivityDetails($id) {
        try {
            $sql = "SELECT a.*, u.name as user_name, u.email, b.name as branch_name, ur.role_name
                    FROM activity_logs a
                    JOIN users u ON a.user_id = u.id
                    JOIN branches b ON a.branch_id = b.id
                    JOIN user_roles ur ON u.role_id = ur.id
                    WHERE a.id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch activity details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Module-specific logging methods
     */
    public function logSale($action, $saleId, $description, $severity = 'MEDIUM') {
        return $this->log('SALES', $action, $description, [
            'entity_type' => 'sale',
            'entity_id' => $saleId,
            'severity' => $severity
        ]);
    }

    public function logProduction($action, $batchId, $description, $severity = 'MEDIUM') {
        return $this->log('PRODUCTION', $action, $description, [
            'entity_type' => 'production_batch',
            'entity_id' => $batchId,
            'severity' => $severity
        ]);
    }

    public function logTransfer($action, $transferId, $description, $severity = 'MEDIUM') {
        return $this->log('TRANSFERS', $action, $description, [
            'entity_type' => 'transfer',
            'entity_id' => $transferId,
            'severity' => $severity
        ]);
    }

    public function logInventory($action, $entityType, $entityId, $description, $severity = 'LOW') {
        return $this->log('INVENTORY', $action, $description, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity
        ]);
    }

    public function logPurchase($action, $purchaseId, $description, $severity = 'MEDIUM') {
        return $this->log('PURCHASES', $action, $description, [
            'entity_type' => 'purchase',
            'entity_id' => $purchaseId,
            'severity' => $severity
        ]);
    }

    public function logExpense($action, $expenseId, $description, $severity = 'LOW') {
        return $this->log('EXPENSES', $action, $description, [
            'entity_type' => 'expense',
            'entity_id' => $expenseId,
            'severity' => $severity
        ]);
    }

    public function logFleet($action, $entityType, $entityId, $description, $severity = 'LOW') {
        return $this->log('FLEET', $action, $description, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'severity' => $severity
        ]);
    }

    public function logUser($action, $userId, $description, $severity = 'HIGH') {
        return $this->log('USER_MANAGEMENT', $action, $description, [
            'entity_type' => 'user',
            'entity_id' => $userId,
            'severity' => $severity
        ]);
    }

    public function logOrder($action, $orderId, $description, $severity = 'MEDIUM') {
        return $this->log('ORDERS', $action, $description, [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'severity' => $severity
        ]);
    }
}

// Global function for easy logging
function logActivity($module, $action, $description, $options = []) {
    static $logger = null;
    if ($logger === null) {
        $logger = new ActivityLogger();
    }
    return $logger->log($module, $action, $description, $options);
}
?>