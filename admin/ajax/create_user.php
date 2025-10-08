<?php
// File: admin/ajax/create_user.php
// AJAX handler for creating new users

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

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$fullName = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$roleId = $_POST['role_id'] ?? '';
$branchId = $_POST['branch_id'] ?? '';

if (empty($username) || empty($email) || empty($password) || empty($fullName) || empty($roleId) || empty($branchId)) {
    echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit();
}

$result = $admin->createUser($username, $email, $password, $fullName, $phone, $roleId, $branchId);
echo json_encode($result);
?>