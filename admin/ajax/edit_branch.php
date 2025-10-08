<?php
// File: admin/ajax/edit_branch.php
// AJAX handler for editing branches

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

$user = $auth->getCurrentUser();
if ($user['role_name'] !== 'Administrator') {
    echo json_encode(['success' => false, 'error' => 'No permission']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return edit form
    $branchId = $_GET['id'] ?? '';

    if (empty($branchId)) {
        echo json_encode(['success' => false, 'error' => 'Branch ID required']);
        exit();
    }

    $branch = $admin->getBranchById($branchId);

    if (!$branch) {
        echo json_encode(['success' => false, 'error' => 'Branch not found']);
        exit();
    }

    $html = '<form id="editBranchForm">';
    $html .= '<input type="hidden" name="branch_id" value="' . $branch['id'] . '">';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editBranchName" class="form-label">Branch Name *</label>';
    $html .= '<input type="text" class="form-control" id="editBranchName" name="name" value="' . htmlspecialchars($branch['name']) . '" required>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editBranchLocation" class="form-label">Location *</label>';
    $html .= '<input type="text" class="form-control" id="editBranchLocation" name="location" value="' . htmlspecialchars($branch['location']) . '" required>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editBranchType" class="form-label">Branch Type *</label>';
    $html .= '<select class="form-select" id="editBranchType" name="type" required>';
    $html .= '<option value="BRANCH"' . ($branch['type'] === 'BRANCH' ? ' selected' : '') . '>Regional Branch</option>';
    $html .= '<option value="HQ"' . ($branch['type'] === 'HQ' ? ' selected' : '') . '>Headquarters</option>';
    $html .= '</select>';
    $html .= '</div>';

    $html .= '<div class="mb-3">';
    $html .= '<label for="editBranchStatus" class="form-label">Status *</label>';
    $html .= '<select class="form-select" id="editBranchStatus" name="status" required>';
    $html .= '<option value="ACTIVE"' . ($branch['status'] === 'ACTIVE' ? ' selected' : '') . '>Active</option>';
    $html .= '<option value="INACTIVE"' . ($branch['status'] === 'INACTIVE' ? ' selected' : '') . '>Inactive</option>';
    $html .= '</select>';
    $html .= '</div>';

    $html .= '<div class="modal-footer">';
    $html .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>';
    $html .= '<button type="submit" class="btn btn-warning">Update Branch</button>';
    $html .= '</div>';

    $html .= '</form>';

    // Add JavaScript for form submission
    $html .= '<script>';
    $html .= 'document.getElementById("editBranchForm").addEventListener("submit", function(e) {';
    $html .= '  e.preventDefault();';
    $html .= '  const formData = new FormData(this);';
    $html .= '  fetch("ajax/edit_branch.php", {';
    $html .= '    method: "POST",';
    $html .= '    body: formData';
    $html .= '  })';
    $html .= '  .then(response => response.json())';
    $html .= '  .then(data => {';
    $html .= '    if (data.success) {';
    $html .= '      alert("Branch updated successfully!");';
    $html .= '      location.reload();';
    $html .= '    } else {';
    $html .= '      alert("Error updating branch: " + data.error);';
    $html .= '    }';
    $html .= '  });';
    $html .= '});';
    $html .= '</script>';

    echo json_encode(['success' => true, 'html' => $html]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle branch update
    $branchId = $_POST['branch_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $type = $_POST['type'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($branchId) || empty($name) || empty($location) || empty($type) || empty($status)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit();
    }

    $result = $admin->updateBranch($branchId, $name, $location, $type, $status);
    echo json_encode($result);
}
?>