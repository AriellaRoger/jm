<?php
// File: notifications/mark_all_read.php
// Mark all notifications as read for current user

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/NotificationManager.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $notificationManager = new NotificationManager();
    $success = $notificationManager->markAllAsRead($_SESSION['user_id']);

    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>