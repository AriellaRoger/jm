<?php
// File: admin/users.php
// User management interface for JM Animal Feeds ERP System
// Allows administrators to manage all system users and role assignments

session_start();
require_once '../controllers/AuthController.php';
require_once '../controllers/AdminController.php';

$auth = new AuthController();
$admin = new AdminController();

// Check if user is logged in and is an administrator
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$user = $auth->getCurrentUser();
if ($user['role_name'] !== 'Administrator') {
    header('Location: ../dashboard.php');
    exit();
}

// Get filter parameters
$branchFilter = $_GET['branch_id'] ?? '';
$roleFilter = $_GET['role_id'] ?? '';

// Get data for the page
$users = $admin->getAllUsers();
$branches = $admin->getAllBranches();
$roles = $admin->getAllRoles();
$userStats = $admin->getUserStatistics();

// Filter users if parameters are provided
if (!empty($branchFilter)) {
    $users = array_filter($users, function($u) use ($branchFilter) {
        return $u['branch_id'] == $branchFilter;
    });
}

if (!empty($roleFilter)) {
    $users = array_filter($users, function($u) use ($roleFilter) {
        return $u['role_id'] == $roleFilter;
    });
}

$pageTitle = 'User Management';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-people"></i> User Management</h2>
        <div>
            <a href="branches.php" class="btn btn-outline-info me-2">
                <i class="bi bi-building"></i> Manage Branches
            </a>
            <a href="../dashboard.php" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newUserModal">
                <i class="bi bi-person-plus"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $userStats['total_users']; ?></h4>
                            <p class="mb-0">Total Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $userStats['active_users']; ?></h4>
                            <p class="mb-0">Active Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-check h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $userStats['inactive_users']; ?></h4>
                            <p class="mb-0">Inactive Users</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-x h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="branchFilter" class="form-label">Filter by Branch</label>
                    <select class="form-select" id="branchFilter" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo $branchFilter == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="roleFilter" class="form-label">Filter by Role</label>
                    <select class="form-select" id="roleFilter" name="role_id">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $roleFilter == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="users.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-list"></i> All Users <?php echo (!empty($branchFilter) || !empty($roleFilter)) ? '(Filtered)' : ''; ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <p class="text-muted">No users found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Branch</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                                            <br><small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($u['email']); ?></small>
                                            <?php if ($u['phone']): ?>
                                                <br><small><i class="bi bi-phone"></i> <?php echo htmlspecialchars($u['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo match($u['role_name']) {
                                                'Administrator' => 'danger',
                                                'Supervisor' => 'warning text-dark',
                                                'Production' => 'info',
                                                'Driver' => 'secondary',
                                                'Branch Operator' => 'success',
                                                default => 'primary'
                                            };
                                        ?>">
                                            <?php echo htmlspecialchars($u['role_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars($u['branch_name']); ?>
                                            <?php if ($u['branch_type'] === 'HQ'): ?>
                                                <br><small class="badge bg-primary">HQ</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['status'] === 'ACTIVE' ? 'success' : 'secondary'; ?>">
                                            <?php echo $u['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['last_login']): ?>
                                            <small><?php echo date('M d, Y H:i', strtotime($u['last_login'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-warning" onclick="editUser(<?php echo $u['id']; ?>)" title="Edit User">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="resetPassword(<?php echo $u['id']; ?>)" title="Reset Password">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <?php if ($u['id'] != $user['id']): // Can't delete yourself ?>
                                                <button class="btn btn-outline-danger" onclick="deleteUser(<?php echo $u['id']; ?>)" title="Delete User">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New User Modal -->
<div class="modal fade" id="newUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newUserForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userFullName" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="userFullName" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="userUsername" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="userUsername" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="userEmail" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="userEmail" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userPhone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="userPhone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="userPassword" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="userPassword" name="password" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirmPassword" required minlength="6">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userRole" class="form-label">Role *</label>
                                <select class="form-select" id="userRole" name="role_id" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="userBranch" class="form-label">Branch *</label>
                                <select class="form-select" id="userBranch" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <?php if ($branch['status'] === 'ACTIVE'): ?>
                                            <option value="<?php echo $branch['id']; ?>">
                                                <?php echo htmlspecialchars($branch['name']); ?> (<?php echo $branch['type']; ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editUserBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset User Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="resetPasswordForm">
                <input type="hidden" id="resetUserId" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password *</label>
                        <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label for="confirmNewPassword" class="form-label">Confirm New Password *</label>
                        <input type="password" class="form-control" id="confirmNewPassword" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
function validatePasswords(password1Id, password2Id) {
    const password1 = document.getElementById(password1Id);
    const password2 = document.getElementById(password2Id);

    if (password1.value !== password2.value) {
        password2.setCustomValidity('Passwords do not match');
    } else {
        password2.setCustomValidity('');
    }
}

document.getElementById('confirmPassword').addEventListener('input', function() {
    validatePasswords('userPassword', 'confirmPassword');
});

document.getElementById('confirmNewPassword').addEventListener('input', function() {
    validatePasswords('newPassword', 'confirmNewPassword');
});

// Create new user
document.getElementById('newUserForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const password = document.getElementById('userPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (password !== confirmPassword) {
        alert('Passwords do not match');
        return;
    }

    const formData = new FormData(this);

    fetch('ajax/create_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('User created successfully!');
            location.reload();
        } else {
            alert('Error creating user: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating user');
    });
});

// Edit user
function editUser(userId) {
    fetch(`ajax/edit_user.php?id=${userId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('editUserBody').innerHTML = data.html;
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        } else {
            alert('Error loading user: ' + data.error);
        }
    });
}

// Reset password
function resetPassword(userId) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmNewPassword').value = '';
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmNewPassword').value;

    if (newPassword !== confirmPassword) {
        alert('Passwords do not match');
        return;
    }

    const formData = new FormData(this);

    fetch('ajax/reset_password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password reset successfully!');
            bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
        } else {
            alert('Error resetting password: ' + data.error);
        }
    });
});

// Delete user
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('ajax/delete_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({user_id: userId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully!');
                location.reload();
            } else {
                alert('Error deleting user: ' + data.error);
            }
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>