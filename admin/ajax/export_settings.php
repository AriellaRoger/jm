<?php
// File: admin/ajax/export_settings.php
// Export company settings as JSON for backup

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/CompanySettingsController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $settingsController = new CompanySettingsController();
    $settings = $settingsController->exportSettings();

    if ($settings !== false) {
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to export settings'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>