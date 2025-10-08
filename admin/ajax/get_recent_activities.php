<?php
// File: admin/ajax/get_recent_activities.php
// Get recent activities for real-time dashboard updates

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ActivityLogger.php';

$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $activityLogger = new ActivityLogger();
    $activities = $activityLogger->getRecentActivities(15);

    echo json_encode([
        'success' => true,
        'activities' => $activities
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>