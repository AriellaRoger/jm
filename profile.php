<?php
// File: profile.php
// User profile page for JM Animal Feeds ERP System
// Allows all users to view and edit their profile information and change passwords

session_start();
require_once 'config/database.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/ActivityController.php';

$auth = new AuthController();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = $auth->getCurrentUser();
$message = '';
$error = '';

// Get complete user data from database for profile display
try {
    $db = getDbConnection();
    $stmt = $db->prepare("
        SELECT u.*, r.role_name, b.name as branch_name, b.type as branch_type
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        JOIN branches b ON u.branch_id = b.id
        WHERE u.id = ?
    ");
    $stmt->execute([$currentUser['id']]);
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($userProfile) {
        // Merge session data with database data
        $currentUser = array_merge($currentUser, $userProfile);
    }
} catch (Exception $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($fullName) || empty($email)) {
            $error = 'Full name and email are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Update profile
            $result = $auth->updateProfile($currentUser['id'], $fullName, $email, $phone);
            if ($result['success']) {
                $message = 'Profile updated successfully';
                // Refresh current user data
                $currentUser = $auth->getCurrentUser();

                // Log activity
                ActivityController::log(
                    $currentUser['id'],
                    'PROFILE_UPDATED',
                    'USER',
                    $currentUser['id'],
                    ['full_name' => $fullName, 'email' => $email, 'phone' => $phone]
                );
            } else {
                $error = $result['error'];
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } else {
            // Change password
            $result = $auth->changePassword($currentUser['id'], $currentPassword, $newPassword);
            if ($result['success']) {
                $message = 'Password changed successfully';

                // Log activity
                ActivityController::log(
                    $currentUser['id'],
                    'PASSWORD_CHANGED',
                    'USER',
                    $currentUser['id']
                );
            } else {
                $error = $result['error'];
            }
        }
    }
}

$pageTitle = 'My Profile';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-person-circle text-primary"></i> My Profile
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="dashboard.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Profile Information Card -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-person-badge"></i> Profile Information
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-avatar mb-3">
                    <i class="bi bi-person-circle text-primary" style="font-size: 6rem;"></i>
                </div>
                <h4><?php echo htmlspecialchars($currentUser['full_name']); ?></h4>
                <p class="text-muted mb-1"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                <span class="badge bg-success mb-2"><?php echo htmlspecialchars($currentUser['role_name']); ?></span>

                <hr>

                <div class="row text-center">
                    <div class="col-12 mb-2">
                        <strong>Branch:</strong><br>
                        <span class="badge bg-info"><?php echo htmlspecialchars($currentUser['branch_name']); ?></span>
                    </div>
                </div>

                <hr>

                <div class="text-start">
                    <p class="mb-1"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($currentUser['email']); ?></p>
                    <?php if (!empty($currentUser['phone'])): ?>
                        <p class="mb-1"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($currentUser['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($currentUser['created_at'])): ?>
                        <p class="mb-1"><i class="bi bi-calendar"></i> Joined: <?php echo date('M j, Y', strtotime($currentUser['created_at'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($currentUser['last_login'])): ?>
                        <p class="mb-0"><i class="bi bi-clock"></i> Last login: <?php echo date('M j, Y g:i A', strtotime($currentUser['last_login'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Settings -->
    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="card shadow mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square"></i> Edit Profile
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                   value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username"
                                   value="<?php echo htmlspecialchars($currentUser['username']); ?>" readonly>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($currentUser['role_name']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($currentUser['branch_name']); ?>" readonly>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-warning">
                        <i class="bi bi-check-circle"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Form -->
        <div class="card shadow">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-shield-lock"></i> Change Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="changePasswordForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                   minlength="6" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   minlength="6" required>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-danger">
                        <i class="bi bi-shield-check"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;

    if (confirmPassword && newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include 'includes/footer.php'; ?>