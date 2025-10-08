<?php
// File: admin/ajax/delete_branch.php
// AJAX handler for deleting branches

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$branchId = $input['branch_id'] ?? '';

if (empty($branchId)) {
    echo json_encode(['success' => false, 'error' => 'Branch ID required']);
    exit();
}

$result = $admin->deleteBranch($branchId);
echo json_encode($result);
?>