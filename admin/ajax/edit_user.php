<?php
// File: admin/ajax/edit_user.php
// AJAX handler for editing users

session_start();
require_once '../../controllers/AuthController.php';
require_once '../../controllers/AdminController.php';

header('Content-Type: application/json');

$auth = new AuthController();
$admin = new AdminController();

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$currentUser = $auth->getCurrentUser();
if ($currentUser['role_name'] !== 'Administrator') {
    echo json_encode(['success' => false, 'error' => 'No permission']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return edit form
    $userId = $_GET['id'] ?? '';

    if (empty($userId)) {
        echo json_encode(['success' => false, 'error' => 'User ID required']);
        exit();
    }

    $user = $admin->getUserById($userId);
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }

    $branches = $admin->getAllBranches();
    $roles = $admin->getAllRoles();

    $html = '<form id="editUserForm">';
    $html .= '<input type="hidden" name="user_id" value="' . $user['id'] . '">';

    $html .= '<div class="row">';
    $html .= '<div class="col-md-6">';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserFullName" class="form-label">Full Name *</label>';
    $html .= '<input type="text" class="form-control" id="editUserFullName" name="full_name" value="' . htmlspecialchars($user['full_name']) . '" required>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserUsername" class="form-label">Username *</label>';
    $html .= '<input type="text" class="form-control" id="editUserUsername" name="username" value="' . htmlspecialchars($user['username']) . '" required>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserEmail" class="form-label">Email *</label>';
    $html .= '<input type="email" class="form-control" id="editUserEmail" name="email" value="' . htmlspecialchars($user['email']) . '" required>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '<div class="col-md-6">';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserPhone" class="form-label">Phone</label>';
    $html .= '<input type="tel" class="form-control" id="editUserPhone" name="phone" value="' . htmlspecialchars($user['phone']) . '">';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserRole" class="form-label">Role *</label>';
    $html .= '<select class="form-select" id="editUserRole" name="role_id" required>';
    foreach ($roles as $role) {
        $selected = ($role['id'] == $user['role_id']) ? 'selected' : '';
        $html .= '<option value="' . $role['id'] . '" ' . $selected . '>' . htmlspecialchars($role['role_name']) . '</option>';
    }
    $html .= '</select>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserBranch" class="form-label">Branch *</label>';
    $html .= '<select class="form-select" id="editUserBranch" name="branch_id" required>';
    foreach ($branches as $branch) {
        $selected = ($branch['id'] == $user['branch_id']) ? 'selected' : '';
        $html .= '<option value="' . $branch['id'] . '" ' . $selected . '>' . htmlspecialchars($branch['name']) . ' (' . $branch['type'] . ')</option>';
    }
    $html .= '</select>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="mb-3">';
    $html .= '<label for="editUserStatus" class="form-label">Status *</label>';
    $html .= '<select class="form-select" id="editUserStatus" name="status" required>';
    $html .= '<option value="ACTIVE"' . ($user['status'] === 'ACTIVE' ? ' selected' : '') . '>Active</option>';
    $html .= '<option value="INACTIVE"' . ($user['status'] === 'INACTIVE' ? ' selected' : '') . '>Inactive</option>';
    $html .= '</select>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="mb-3">';
    $html .= '<div class="form-check">';
    $html .= '<input class="form-check-input" type="checkbox" id="changePassword">';
    $html .= '<label class="form-check-label" for="changePassword">Change Password</label>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div id="passwordFields" style="display: none;">';
    $html .= '<div class="row">';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="mb-3">';
    $html .= '<label for="editNewPassword" class="form-label">New Password</label>';
    $html .= '<input type="password" class="form-control" id="editNewPassword" name="new_password" minlength="6">';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="mb-3">';
    $html .= '<label for="editConfirmPassword" class="form-label">Confirm Password</label>';
    $html .= '<input type="password" class="form-control" id="editConfirmPassword" minlength="6">';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div class="modal-footer">';
    $html .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
    $html .= '<button type="submit" class="btn btn-warning">Update User</button>';
    $html .= '</div>';

    $html .= '</form>';

    // Add JavaScript for form functionality
    $html .= '<script>';
    $html .= 'document.getElementById("changePassword").addEventListener("change", function() {';
    $html .= '  const passwordFields = document.getElementById("passwordFields");';
    $html .= '  const newPasswordField = document.getElementById("editNewPassword");';
    $html .= '  if (this.checked) {';
    $html .= '    passwordFields.style.display = "block";';
    $html .= '    newPasswordField.required = true;';
    $html .= '  } else {';
    $html .= '    passwordFields.style.display = "none";';
    $html .= '    newPasswordField.required = false;';
    $html .= '    newPasswordField.value = "";';
    $html .= '    document.getElementById("editConfirmPassword").value = "";';
    $html .= '  }';
    $html .= '});';

    $html .= 'document.getElementById("editConfirmPassword").addEventListener("input", function() {';
    $html .= '  const newPassword = document.getElementById("editNewPassword").value;';
    $html .= '  if (this.value !== newPassword) {';
    $html .= '    this.setCustomValidity("Passwords do not match");';
    $html .= '  } else {';
    $html .= '    this.setCustomValidity("");';
    $html .= '  }';
    $html .= '});';

    $html .= 'document.getElementById("editUserForm").addEventListener("submit", function(e) {';
    $html .= '  e.preventDefault();';
    $html .= '  const changePassword = document.getElementById("changePassword").checked;';
    $html .= '  if (changePassword) {';
    $html .= '    const newPassword = document.getElementById("editNewPassword").value;';
    $html .= '    const confirmPassword = document.getElementById("editConfirmPassword").value;';
    $html .= '    if (newPassword !== confirmPassword) {';
    $html .= '      alert("Passwords do not match");';
    $html .= '      return;';
    $html .= '    }';
    $html .= '  }';

    $html .= '  const formData = new FormData(this);';
    $html .= '  fetch("ajax/edit_user.php", {';
    $html .= '    method: "POST",';
    $html .= '    body: formData';
    $html .= '  })';
    $html .= '  .then(response => response.json())';
    $html .= '  .then(data => {';
    $html .= '    if (data.success) {';
    $html .= '      alert("User updated successfully!");';
    $html .= '      location.reload();';
    $html .= '    } else {';
    $html .= '      alert("Error updating user: " + data.error);';
    $html .= '    }';
    $html .= '  });';
    $html .= '});';
    $html .= '</script>';

    echo json_encode(['success' => true, 'html' => $html]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle user update
    $userId = $_POST['user_id'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $roleId = $_POST['role_id'] ?? '';
    $branchId = $_POST['branch_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $newPassword = $_POST['new_password'] ?? null;

    if (empty($userId) || empty($username) || empty($email) || empty($fullName) || empty($roleId) || empty($branchId) || empty($status)) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
        exit();
    }

    if ($newPassword && strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
        exit();
    }

    $result = $admin->updateUser($userId, $username, $email, $fullName, $phone, $roleId, $branchId, $status, $newPassword);
    echo json_encode($result);
}
?>