<?php
// File: controllers/ActivityController.php
// Controller for managing system-wide activity logging across all modules
// Tracks user activities from production, sales, admin operations, and more

require_once __DIR__ . '/../config/database.php';

class ActivityController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function logActivity($userId, $action, $module, $recordId = null, $details = null, $ipAddress = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, module, record_id, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $action,
                $module,
                $recordId,
                $details ? json_encode($details) : null,
                $ipAddress ?: $_SERVER['REMOTE_ADDR'] ?? null
            ]);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getAllActivities($limit = 50, $branchFilter = null, $moduleFilter = null, $userFilter = null) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];

            if ($branchFilter) {
                $whereClause .= " AND b.id = ?";
                $params[] = $branchFilter;
            }

            if ($moduleFilter) {
                $whereClause .= " AND al.module = ?";
                $params[] = $moduleFilter;
            }

            if ($userFilter) {
                $whereClause .= " AND u.id = ?";
                $params[] = $userFilter;
            }

            $stmt = $this->db->prepare("
                SELECT
                    al.*,
                    u.full_name,
                    u.username,
                    ur.role_name,
                    b.name as branch_name,
                    b.type as branch_type
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                JOIN user_roles ur ON u.role_id = ur.id
                JOIN branches b ON u.branch_id = b.id
                {$whereClause}
                ORDER BY al.created_at DESC
                LIMIT ?
            ");

            $params[] = $limit;
            $stmt->execute($params);

            return ['success' => true, 'activities' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getActivityDetail($activityId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    al.*,
                    u.full_name,
                    u.username,
                    u.email,
                    ur.role_name,
                    b.name as branch_name,
                    b.location as branch_location,
                    b.type as branch_type
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                JOIN user_roles ur ON u.role_id = ur.id
                JOIN branches b ON u.branch_id = b.id
                WHERE al.id = ?
            ");

            $stmt->execute([$activityId]);
            $activity = $stmt->fetch();

            if (!$activity) {
                return ['success' => false, 'error' => 'Activity not found'];
            }

            // Parse details if JSON
            if ($activity['details']) {
                $activity['details_parsed'] = json_decode($activity['details'], true);
            }

            return ['success' => true, 'activity' => $activity];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getModuleActivityCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    module,
                    COUNT(*) as count,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_count
                FROM activity_logs
                GROUP BY module
                ORDER BY count DESC
            ");

            $stmt->execute();
            return ['success' => true, 'modules' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBranchActivityCount() {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    b.name as branch_name,
                    b.id as branch_id,
                    COUNT(al.id) as count,
                    COUNT(CASE WHEN al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_count
                FROM branches b
                LEFT JOIN users u ON b.id = u.branch_id
                LEFT JOIN activity_logs al ON u.id = al.user_id
                GROUP BY b.id, b.name
                ORDER BY count DESC
            ");

            $stmt->execute();
            return ['success' => true, 'branches' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function log($userId, $action, $module, $recordId = null, $details = null) {
        $controller = new self();
        return $controller->logActivity($userId, $action, $module, $recordId, $details);
    }
}
?>