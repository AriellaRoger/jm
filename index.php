<?php
// File: index.php
// Main entry point for JM Animal Feeds ERP System
// Handles initial routing and redirects authenticated users to dashboard

require_once 'config/database.php';
require_once 'controllers/AuthController.php';

$auth = new AuthController();

// Clean up expired sessions
$auth->cleanupSessions();

// Check if user is already authenticated
if ($auth->isAuthenticated()) {
    // Redirect to dashboard if logged in
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

// If not authenticated, redirect to login
header('Location: ' . BASE_URL . '/login.php');
exit();
?>