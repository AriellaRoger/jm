<?php
// File: dashboard.php
// Main dashboard for JM Animal Feeds ERP System
// Routes users to appropriate role-based dashboard

require_once 'controllers/AuthController.php';

$auth = new AuthController();

// Require authentication
$auth->requireAuth();

// Get current user info
$currentUser = $auth->getCurrentUser();

// Route to appropriate dashboard based on role
switch ($currentUser['role']) {
    case 'Administrator':
        include 'dashboards/admin_dashboard.php';
        break;
    case 'Supervisor':
        include 'dashboards/supervisor_dashboard.php';
        break;
    case 'Production':
        include 'dashboards/production_dashboard.php';
        break;
    case 'Driver':
        include 'dashboards/driver_dashboard.php';
        break;
    case 'Branch Operator':
        include 'dashboards/branch_dashboard.php';
        break;
    default:
        // Default fallback dashboard
        include 'dashboards/default_dashboard.php';
        break;
}
?>