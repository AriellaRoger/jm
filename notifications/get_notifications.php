<?php
// File: notifications/get_notifications.php
// Get user notifications for real-time updates

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
    $notifications = $notificationManager->getForUser($_SESSION['user_id'], 10);
    $unreadCount = $notificationManager->getUnreadCount($_SESSION['user_id']);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>