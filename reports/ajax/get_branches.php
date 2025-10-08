<?php
// File: reports/ajax/get_branches.php
// Get all branches for dropdown population

error_reporting(0);
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE status = 'ACTIVE' ORDER BY name");
    $stmt->execute();
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'branches' => $branches]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error loading branches: ' . $e->getMessage()]);
}
?>