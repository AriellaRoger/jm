<?php
// File: controllers/AdminController.php
// Admin management controller for JM Animal Feeds ERP System
// Handles branch management, user management, and role assignments

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityController.php';

class AdminController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // ============================
    // BRANCH MANAGEMENT METHODS
    // ============================

    // Get all branches
    public function getAllBranches() {
        try {
            $sql = "SELECT b.*,
                           COUNT(u.id) as user_count,
                           COUNT(CASE WHEN u.status = 'ACTIVE' THEN 1 END) as active_users
                    FROM branches b
                    LEFT JOIN users u ON b.id = u.branch_id
                    GROUP BY b.id
                    ORDER BY b.type DESC, b.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branches: " . $e->getMessage());
            return [];
        }
    }

    // Get branch by ID
    public function getBranchById($branchId) {
        try {
            $sql = "SELECT * FROM branches WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branch: " . $e->getMessage());
            return null;
        }
    }

    // Create new branch
    public function createBranch($name, $location, $type) {
        try {
            // Check if branch name already exists
            $sql = "SELECT id FROM branches WHERE name = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name]);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Branch name already exists'];
            }

            // Create branch
            $sql = "INSERT INTO branches (name, location, type) VALUES (?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $location, $type]);

            $branchId = $this->db->lastInsertId();

            // Log activity
            if (isset($_SESSION['user_id'])) {
                ActivityController::log(
                    $_SESSION['user_id'],
                    'BRANCH_CREATED',
                    'ADMIN',
                    $branchId,
                    ['name' => $name, 'location' => $location, 'type' => $type]
                );
            }

            return [
                'success' => true,
                'branch_id' => $branchId,
                'message' => 'Branch created successfully'
            ];

        } catch (PDOException $e) {
            error_log("Error creating branch: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Update branch
    public function updateBranch($branchId, $name, $location, $type, $status) {
        try {
            // Check if branch name already exists (excluding current branch)
            $sql = "SELECT id FROM branches WHERE name = ? AND id != ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $branchId]);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Branch name already exists'];
            }

            // Update branch
            $sql = "UPDATE branches SET name = ?, location = ?, type = ?, status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $location, $type, $status, $branchId]);

            return ['success' => true, 'message' => 'Branch updated successfully'];

        } catch (PDOException $e) {
            error_log("Error updating branch: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Delete branch
    public function deleteBranch($branchId) {
        try {
            // Check if branch has users
            $sql = "SELECT COUNT(*) as user_count FROM users WHERE branch_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branchId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['user_count'] > 0) {
                return ['success' => false, 'error' => 'Cannot delete branch with existing users. Move users first.'];
            }

            // Check if it's the HQ branch
            $sql = "SELECT type FROM branches WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branchId]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($branch && $branch['type'] === 'HQ') {
                return ['success' => false, 'error' => 'Cannot delete headquarters branch'];
            }

            // Delete branch
            $sql = "DELETE FROM branches WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branchId]);

            return ['success' => true, 'message' => 'Branch deleted successfully'];

        } catch (PDOException $e) {
            error_log("Error deleting branch: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // ============================
    // USER MANAGEMENT METHODS
    // ============================

    // Get all users with details
    public function getAllUsers() {
        try {
            $sql = "SELECT u.*, r.role_name, b.name as branch_name, b.type as branch_type
                    FROM users u
                    JOIN user_roles r ON u.role_id = r.id
                    JOIN branches b ON u.branch_id = b.id
                    ORDER BY u.status, r.role_name, u.full_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting users: " . $e->getMessage());
            return [];
        }
    }

    // Get user by ID
    public function getUserById($userId) {
        try {
            $sql = "SELECT u.*, r.role_name, b.name as branch_name, b.type as branch_type
                    FROM users u
                    JOIN user_roles r ON u.role_id = r.id
                    JOIN branches b ON u.branch_id = b.id
                    WHERE u.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user: " . $e->getMessage());
            return null;
        }
    }

    // Create new user
    public function createUser($username, $email, $password, $fullName, $phone, $roleId, $branchId) {
        try {
            $this->db->beginTransaction();

            // Check if username already exists
            $sql = "SELECT id FROM users WHERE username = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Username already exists'];
            }

            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Email already exists'];
            }

            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            // Create user
            $sql = "INSERT INTO users (username, email, password_hash, full_name, phone, role_id, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $email, $passwordHash, $fullName, $phone, $roleId, $branchId]);

            $userId = $this->db->lastInsertId();

            // Log activity
            if (isset($_SESSION['user_id'])) {
                ActivityController::log(
                    $_SESSION['user_id'],
                    'USER_CREATED',
                    'ADMIN',
                    $userId,
                    ['username' => $username, 'email' => $email, 'full_name' => $fullName, 'role_id' => $roleId, 'branch_id' => $branchId]
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'User created successfully'
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Update user
    public function updateUser($userId, $username, $email, $fullName, $phone, $roleId, $branchId, $status, $newPassword = null) {
        try {
            $this->db->beginTransaction();

            // Check if username already exists (excluding current user)
            $sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$username, $userId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Username already exists'];
            }

            // Check if email already exists (excluding current user)
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email, $userId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Email already exists'];
            }

            // Prepare update query
            if ($newPassword) {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET username = ?, email = ?, password_hash = ?, full_name = ?, phone = ?, role_id = ?, branch_id = ?, status = ? WHERE id = ?";
                $params = [$username, $email, $passwordHash, $fullName, $phone, $roleId, $branchId, $status, $userId];
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, role_id = ?, branch_id = ?, status = ? WHERE id = ?";
                $params = [$username, $email, $fullName, $phone, $roleId, $branchId, $status, $userId];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();

            return ['success' => true, 'message' => 'User updated successfully'];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Delete user
    public function deleteUser($userId) {
        try {
            $this->db->beginTransaction();

            // Check if user has created any production batches or formulas
            $sql = "SELECT
                        (SELECT COUNT(*) FROM production_batches WHERE supervisor_id = ? OR production_officer_id = ?) as batch_count,
                        (SELECT COUNT(*) FROM production_formulas WHERE created_by = ?) as formula_count";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $userId, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['batch_count'] > 0 || $result['formula_count'] > 0) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Cannot delete user with production history. Deactivate instead.'];
            }

            // Delete user sessions first
            $sql = "DELETE FROM user_sessions WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            // Delete user
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            $this->db->commit();

            return ['success' => true, 'message' => 'User deleted successfully'];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error deleting user: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Get all user roles
    public function getAllRoles() {
        try {
            $sql = "SELECT * FROM user_roles ORDER BY role_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting roles: " . $e->getMessage());
            return [];
        }
    }

    // Reset user password
    public function resetUserPassword($userId, $newPassword) {
        try {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$passwordHash, $userId]);

            return ['success' => true, 'message' => 'Password reset successfully'];

        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Get user statistics
    public function getUserStatistics() {
        try {
            $sql = "SELECT
                        COUNT(*) as total_users,
                        COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_users,
                        COUNT(CASE WHEN status = 'INACTIVE' THEN 1 END) as inactive_users
                    FROM users";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user statistics: " . $e->getMessage());
            return ['total_users' => 0, 'active_users' => 0, 'inactive_users' => 0];
        }
    }

    // Get branch statistics
    public function getBranchStatistics() {
        try {
            $sql = "SELECT
                        COUNT(*) as total_branches,
                        COUNT(CASE WHEN status = 'ACTIVE' THEN 1 END) as active_branches,
                        COUNT(CASE WHEN type = 'HQ' THEN 1 END) as hq_count,
                        COUNT(CASE WHEN type = 'BRANCH' THEN 1 END) as branch_count
                    FROM branches";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branch statistics: " . $e->getMessage());
            return ['total_branches' => 0, 'active_branches' => 0, 'hq_count' => 0, 'branch_count' => 0];
        }
    }
}
?>