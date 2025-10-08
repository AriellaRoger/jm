<?php
// File: admin/ajax/create_branch.php
// AJAX handler for creating new branches

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

$name = trim($_POST['name'] ?? '');
$location = trim($_POST['location'] ?? '');
$type = $_POST['type'] ?? '';

if (empty($name) || empty($location) || empty($type)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit();
}

if (!in_array($type, ['HQ', 'BRANCH'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid branch type']);
    exit();
}

$result = $admin->createBranch($name, $location, $type);
echo json_encode($result);
?>