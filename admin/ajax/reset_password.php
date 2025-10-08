<?php
// File: admin/ajax/reset_password.php
// AJAX handler for resetting user passwords

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

// Generate temporary password
$tempPassword = 'temp' . rand(1000, 9999);

$result = $admin->resetUserPassword($userId, $tempPassword);

if ($result['success']) {
    $result['temp_password'] = $tempPassword;
    $result['message'] = 'Password reset successfully. Temporary password: ' . $tempPassword;
}

echo json_encode($result);
?>