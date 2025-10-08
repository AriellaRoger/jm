<?php
// File: controllers/AuthController.php
// Authentication controller for JM Animal Feeds ERP System
// Handles user login, logout, session management, and authentication checks

require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $db;

    public function __construct() {
        $this->db = getDbConnection();
    }

    // Authenticate user login
    public function login($username, $password) {
        try {
            // Get user with role and branch information
            $sql = "SELECT u.*, r.role_name, b.name as branch_name, b.type as branch_type
                    FROM users u
                    JOIN user_roles r ON u.role_id = r.id
                    JOIN branches b ON u.branch_id = b.id
                    WHERE (u.username = :username OR u.email = :email)
                    AND u.status = 'ACTIVE'";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $updateSql = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->bindParam(':user_id', $user['id']);
                $updateStmt->execute();

                // Create session
                $this->createSession($user);

                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $user
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ];
        }
    }

    // Create user session
    private function createSession($user) {
        // Start session only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Store user information in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['branch_id'] = $user['branch_id'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['branch_type'] = $user['branch_type'];
        $_SESSION['login_time'] = time();

        // Generate session token for additional security (64 chars = 32 bytes hex)
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $sessionToken;

        // Store session in database
        $this->storeSessionToken($user['id'], $sessionToken);
    }

    // Store session token in database
    private function storeSessionToken($userId, $token) {
        try {
            // Clean up old sessions for this user
            $cleanupSql = "DELETE FROM user_sessions WHERE user_id = :user_id OR expires_at < NOW()";
            $cleanupStmt = $this->db->prepare($cleanupSql);
            $cleanupStmt->bindParam(':user_id', $userId);
            $cleanupStmt->execute();

            // Insert new session
            $sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
                    VALUES (:user_id, :token, :ip_address, :user_agent, DATE_ADD(NOW(), INTERVAL 24 HOUR))";

            $stmt = $this->db->prepare($sql);
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->execute();
        } catch (Exception $e) {
            // Log error but don't fail login
            error_log("Failed to store session token: " . $e->getMessage());
        }
    }

    // Check if user is authenticated
    public function isAuthenticated() {
        // Start session only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }

        // Verify session token in database
        try {
            $sql = "SELECT id FROM user_sessions
                    WHERE user_id = :user_id AND session_token = :token
                    AND expires_at > NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':token', $_SESSION['session_token']);
            $stmt->execute();

            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Session verification failed: " . $e->getMessage());
            return false;
        }
    }

    // Check if user has specific role
    public function hasRole($requiredRoles) {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userRole = $_SESSION['user_role'];

        if (is_string($requiredRoles)) {
            return $userRole === $requiredRoles;
        }

        if (is_array($requiredRoles)) {
            return in_array($userRole, $requiredRoles);
        }

        return false;
    }

    // Logout user
    public function logout() {
        // Start session only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
            // Remove session from database
            try {
                $sql = "DELETE FROM user_sessions
                        WHERE user_id = :user_id AND session_token = :token";

                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':token', $_SESSION['session_token']);
                $stmt->execute();
            } catch (Exception $e) {
                error_log("Failed to remove session from database: " . $e->getMessage());
            }
        }

        // Destroy session
        session_destroy();

        return [
            'success' => true,
            'message' => 'Logout successful'
        ];
    }

    // Require authentication (redirect to login if not authenticated)
    public function requireAuth($allowedRoles = null) {
        if (!$this->isAuthenticated()) {
            header('Location: ' . BASE_URL . '/login.php');
            exit();
        }

        if ($allowedRoles && !$this->hasRole($allowedRoles)) {
            header('HTTP/1.1 403 Forbidden');
            include '../errors/403.php';
            exit();
        }
    }

    // Alias for isAuthenticated (for compatibility)
    public function isLoggedIn() {
        return $this->isAuthenticated();
    }

    // Get current user info
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['user_name'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role_name' => $_SESSION['user_role'],
            'role' => $_SESSION['user_role'],
            'role_id' => $_SESSION['role_id'],
            'branch_id' => $_SESSION['branch_id'],
            'branch_name' => $_SESSION['branch_name'],
            'branch_type' => $_SESSION['branch_type']
        ];
    }

    // Clean up expired sessions
    public function cleanupSessions() {
        try {
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to cleanup sessions: " . $e->getMessage());
        }
    }

    // Update user profile
    public function updateProfile($userId, $fullName, $email, $phone = null) {
        try {
            // Check if email is already used by another user
            $sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$email, $userId]);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Email is already used by another user'];
            }

            // Update user profile
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fullName, $email, $phone, $userId]);

            // Update session data if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                $_SESSION['user_name'] = $fullName;
                $_SESSION['email'] = $email;
            }

            return ['success' => true, 'message' => 'Profile updated successfully'];

        } catch (Exception $e) {
            error_log("Profile update failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update profile: ' . $e->getMessage()];
        }
    }

    // Change user password
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $sql = "SELECT password_hash FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }

            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Update password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$newPasswordHash, $userId]);

            return ['success' => true, 'message' => 'Password changed successfully'];

        } catch (Exception $e) {
            error_log("Password change failed: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to change password: ' . $e->getMessage()];
        }
    }
}
?>