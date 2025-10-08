<?php
// File: admin/ajax/delete_user.php
// AJAX handler for deleting users

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit();
}

// Prevent deleting own account
if ($userId == $currentUser['id']) {
    echo json_encode(['success' => false, 'error' => 'Cannot delete your own account']);
    exit();
}

$result = $admin->deleteUser($userId);
echo json_encode($result);
?>