<?php
// File: includes/header.php
// Common header file for JM Animal Feeds ERP System
// Contains HTML head, navigation, and Bootstrap styling for mobile-first design

// Include config for BASE_URL
require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['user_role'] : '';
$userName = $isLoggedIn ? $_SESSION['user_name'] : '';
$branchName = $isLoggedIn ? $_SESSION['branch_name'] : '';

// Get user notifications
$notifications = [];
$unreadCount = 0;
if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/../controllers/NotificationManager.php';
        $notificationManager = new NotificationManager();
        $notifications = $notificationManager->getForUser($_SESSION['user_id'], 10);
        $unreadCount = $notificationManager->getUnreadCount($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Error loading notifications: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>JM Animal Feeds ERP</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --secondary-color: #64748b;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --sidebar-bg: linear-gradient(180deg, #1e40af 0%, #1d4ed8 100%);
            --header-bg: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --card-shadow-hover: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        /* Navigation Styles */
        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: white !important;
            font-size: 1.5rem;
        }

        .navbar {
            background: var(--header-bg) !important;
            box-shadow: var(--card-shadow);
            padding: 1rem 0;
        }

        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: var(--sidebar-bg);
            box-shadow: var(--card-shadow);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            margin: 0.25rem 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }

        .sidebar .nav-link:hover::before {
            left: 100%;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
        }

        .sidebar .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-heading {
            color: rgba(255, 255, 255, 0.7) !important;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 1.5rem 1.25rem 0.5rem 1.25rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-heading:first-child {
            margin-top: 1rem;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--card-shadow-hover);
            transform: translateY(-2px);
        }

        .card-header {
            border-bottom: none;
            font-weight: 600;
            border-radius: 1rem 1rem 0 0 !important;
        }

        /* Statistics Cards */
        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stats-icon.primary { background: linear-gradient(45deg, var(--primary-color), var(--primary-dark)); }
        .stats-icon.success { background: linear-gradient(45deg, var(--success-color), #059669); }
        .stats-icon.warning { background: linear-gradient(45deg, var(--warning-color), #d97706); }
        .stats-icon.info { background: linear-gradient(45deg, var(--info-color), #0891b2); }
        .stats-icon.danger { background: linear-gradient(45deg, var(--danger-color), #dc2626); }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1;
        }

        .stats-label {
            color: var(--secondary-color);
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-change {
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Button Styles */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #059669);
            border: none;
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning-color), #d97706);
            border: none;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #dc2626);
            border: none;
        }

        .btn-info {
            background: linear-gradient(45deg, var(--info-color), #0891b2);
            border: none;
        }

        /* Main Content */
        .main-content {
            min-height: 100vh;
            background: transparent;
        }

        /* Dashboard specific styles */
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary-color), var(--info-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Badges */
        .badge {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
        }

        /* User Info */
        .user-info {
            font-size: 0.9em;
        }

        .role-badge {
            font-size: 0.8em;
        }

        /* Tables */
        .table {
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            font-weight: 600;
        }

        /* Form Controls */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.1);
        }

        /* Alerts */
        .alert {
            border-radius: 0.75rem;
            border: none;
            font-weight: 500;
        }

        /* Module Cards */
        .module-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
            text-decoration: none;
        }

        .module-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary-color), var(--info-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .stats-number {
                font-size: 1.75rem;
            }

            .dashboard-title {
                font-size: 1.75rem;
            }

            .stats-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .module-card {
                padding: 1.5rem;
            }

            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease-in-out;
                background: var(--sidebar-bg);
                overflow-y: auto;
            }

            .sidebar.show {
                left: 0;
            }

            .main-content {
                padding-left: 0 !important;
            }

            .navbar-brand div:last-child div:last-child {
                display: none;
            }

            .card-body {
                padding: 1rem;
            }

            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .stats-change {
                font-size: 0.75rem;
            }

            /* Mobile notification badge fix */
            .dropdown .position-absolute {
                right: -5px;
                top: 5px;
            }
        }

        @media (max-width: 576px) {
            .stats-number {
                font-size: 1.5rem;
            }

            .dashboard-title {
                font-size: 1.5rem;
            }

            .stats-card {
                text-align: center;
            }

            .stats-card .d-flex {
                flex-direction: column;
                align-items: center;
            }

            .stats-icon {
                margin-bottom: 0.5rem;
            }

            .module-card {
                padding: 1rem;
            }

            .btn-toolbar .btn-group {
                margin-bottom: 0.5rem;
            }

            .table-responsive {
                font-size: 0.875rem;
            }
        }

        /* Sidebar mobile overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease-in-out;
        }

        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Enhanced button styles */
        .btn-group .btn-link {
            border-radius: 0.5rem;
            margin: 0 0.125rem;
            transition: all 0.2s ease;
        }

        .btn-group .btn-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Notification Styles */
        .notification-dropdown {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 1rem;
        }

        .notification-item {
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
        }

        .notification-item.unread {
            background-color: #e3f2fd;
            border-left: 4px solid var(--primary-color);
        }

        .notification-content {
            cursor: pointer;
            border-bottom: 1px solid #dee2e6;
        }

        .notification-content:hover {
            background-color: rgba(99, 102, 241, 0.05);
        }

        .notification-icon .badge {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-title {
            font-size: 0.9rem;
            color: #2d3748;
        }

        .notification-message {
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .unread-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.7; }
            70% { transform: scale(1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }

        .notification-badge {
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 20%, 53%, 80%, 100% { transform: translate(-50%, -50%) scale(1); }
            40%, 43% { transform: translate(-50%, -50%) scale(1.1); }
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-list::-webkit-scrollbar {
            width: 4px;
        }

        .notification-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .notification-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        .notification-list::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>/dashboard.php">
                <div style="background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 0.5rem; margin-right: 0.75rem;">
                    <i class="bi bi-grain" style="font-size: 1.5rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.25rem; font-weight: 700;">JM Animal Feeds</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Enterprise Resource Planning</div>
                </div>
            </a>

            <?php if ($isLoggedIn): ?>
                <!-- Mobile menu toggle -->
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarNav">
                    <i class="bi bi-list" style="font-size: 1.5rem;"></i>
                </button>

                <div class="d-flex align-items-center ms-auto">
                    <!-- Real-time Notifications -->
                    <div class="dropdown me-3 position-relative">
                        <button class="btn btn-link text-white p-2 position-relative" type="button" data-bs-toggle="dropdown" id="notificationDropdown">
                            <i class="bi bi-bell-fill" style="font-size: 1.2rem;"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="font-size: 0.6rem;">
                                    <?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 380px; max-height: 500px; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                <h6 class="mb-0 fw-bold">Notifications</h6>
                                <div>
                                    <?php if ($unreadCount > 0): ?>
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick="markAllNotificationsRead()">
                                            <i class="bi bi-check2-all"></i> Mark all read
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="refreshNotifications()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="notificationList" class="notification-list">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>"
                                             data-id="<?php echo $notification['id']; ?>">
                                            <div class="d-flex align-items-start p-3 border-bottom notification-content"
                                                 onclick="handleNotificationClick(<?php echo $notification['id']; ?>, '<?php echo $notification['action_url']; ?>')">
                                                <div class="notification-icon me-3">
                                                    <?php
                                                    $iconClass = 'info';
                                                    $icon = 'info-circle';
                                                    switch($notification['type']) {
                                                        case 'SUCCESS': $iconClass = 'success'; $icon = 'check-circle'; break;
                                                        case 'WARNING': $iconClass = 'warning'; $icon = 'exclamation-triangle'; break;
                                                        case 'ERROR': $iconClass = 'danger'; $icon = 'x-circle'; break;
                                                        case 'APPROVAL_REQUIRED': $iconClass = 'warning'; $icon = 'clock'; break;
                                                        default: $iconClass = 'info'; $icon = 'info-circle'; break;
                                                    }
                                                    ?>
                                                    <div class="badge bg-<?php echo $iconClass; ?> rounded-circle p-2">
                                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1 fw-bold notification-title">
                                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                                <?php if ($notification['is_urgent']): ?>
                                                                    <i class="bi bi-exclamation-circle text-danger ms-1" title="Urgent"></i>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="mb-1 small text-muted notification-message">
                                                                <?php echo htmlspecialchars($notification['message']); ?>
                                                            </p>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock"></i> <?php echo date('M j, H:i', strtotime($notification['created_at'])); ?>
                                                                <span class="ms-2">
                                                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($notification['module']); ?>
                                                                </span>
                                                            </small>
                                                        </div>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <div class="unread-indicator">
                                                                <div class="badge bg-primary rounded-pill">&nbsp;</div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center text-muted">
                                        <i class="bi bi-bell-slash" style="font-size: 3rem; opacity: 0.3;"></i>
                                        <p class="mt-2">No notifications</p>
                                        <p class="small">You're all caught up!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($notifications)): ?>
                                <div class="p-2 border-top text-center">
                                    <a href="<?php echo BASE_URL; ?>/notifications.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-list-ul"></i> View All Notifications
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- User Profile -->
                    <div class="dropdown">
                        <button class="btn btn-link text-white d-flex align-items-center text-decoration-none" type="button" data-bs-toggle="dropdown">
                            <div style="background: rgba(255,255,255,0.2); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem;">
                                <i class="bi bi-person-fill" style="font-size: 1.2rem;"></i>
                            </div>
                            <div class="text-start d-none d-md-block">
                                <div style="font-size: 0.9rem; font-weight: 600;"><?php echo htmlspecialchars($userName); ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.8;"><?php echo htmlspecialchars($userRole); ?></div>
                            </div>
                            <i class="bi bi-chevron-down ms-2"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text">
                                <div class="d-flex align-items-center">
                                    <div style="background: var(--primary-color); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; color: white;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($userName); ?></strong><br>
                                        <span class="badge bg-primary role-badge"><?php echo htmlspecialchars($userRole); ?></span><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($branchName); ?></small>
                                    </div>
                                </div>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php"><i class="bi bi-person me-2"></i> Profile Settings</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Preferences</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($isLoggedIn): ?>
    <!-- Dashboard Layout -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebarNav">
                <div class="position-sticky pt-3">
                    <!-- Main Navigation -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="<?php echo BASE_URL; ?>/dashboard.php">
                                <i class="bi bi-house-fill"></i> Dashboard
                            </a>
                        </li>
                    </ul>

                    <!-- Administrator Only Sections -->
                    <?php if ($userRole === 'Administrator'): ?>
                        <h6 class="sidebar-heading">Administration</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/users.php">
                                    <i class="bi bi-people-fill"></i> User Management
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/branches.php">
                                    <i class="bi bi-building-fill"></i> Branches
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/hr/index.php">
                                    <i class="bi bi-person-badge-fill"></i> HR Management
                                </a>
                            </li>
                        </ul>

                        <h6 class="sidebar-heading">Tools & Reports</h6>
                        <ul class="nav flex-column">
                            <?php if ($userRole === 'Administrator'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/reports/index.php">
                                    <i class="bi bi-graph-up"></i> Finance & Reports
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/barcodes.php">
                                    <i class="bi bi-upc-scan"></i> SKU & Barcodes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/serial_lookup.php">
                                    <i class="bi bi-search"></i> Serial Lookup
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/reports/index.php">
                                    <i class="bi bi-graph-up"></i> Financial Reports
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>

                    <!-- Operations Section -->
                    <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Production'])): ?>
                        <h6 class="sidebar-heading">Operations</h6>
                        <ul class="nav flex-column">
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/formulas.php">
                                        <i class="bi bi-calculator-fill"></i> Formulas
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/production.php">
                                        <i class="bi bi-gear-fill"></i> Production
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/fleet/index.php">
                                        <i class="bi bi-truck"></i> Fleet & Machines
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Production', 'Branch Operator'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/inventory/index.php">
                                        <i class="bi bi-boxes"></i> Inventory
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/inventory/transfers.php">
                                        <i class="bi bi-arrow-left-right"></i> Transfers
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php endif; ?>

                    <!-- Sales & Finance Section -->
                    <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Branch Operator'])): ?>
                        <h6 class="sidebar-heading">Sales & Finance</h6>
                        <ul class="nav flex-column">
                            <?php if (in_array($userRole, ['Administrator', 'Branch Operator'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/sales/pos.php">
                                        <i class="bi bi-cart-fill"></i> Sales
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/sales/customers.php">
                                    <i class="bi bi-people-fill"></i> Customers
                                </a>
                            </li>
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Branch Operator'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/sales/orders.php">
                                        <i class="bi bi-clipboard-data"></i> Customer Orders
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/sales/order_management.php">
                                        <i class="bi bi-kanban"></i> Order Management
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if (in_array($userRole, ['Administrator', 'Supervisor', 'Branch Operator'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo BASE_URL; ?>/requests/index.php">
                                        <i class="bi bi-clipboard-check"></i> Stock Requests
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/purchases/index.php">
                                    <i class="bi bi-cart-plus-fill"></i> Purchases
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/expenses/index.php">
                                    <i class="bi bi-receipt-cutoff"></i> Expenses
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>

                    <!-- Driver Specific Section -->
                    <?php if ($userRole === 'Driver'): ?>
                        <h6 class="sidebar-heading">Transport</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/driver/deliveries.php">
                                    <i class="bi bi-truck"></i> My Deliveries
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/expenses/index.php">
                                    <i class="bi bi-receipt"></i> Expenses
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>

                    <!-- Branch Operator Specific -->
                    <?php if ($userRole === 'Branch Operator'): ?>
                        <h6 class="sidebar-heading">Branch Operations</h6>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/inventory/transfers.php">
                                    <i class="bi bi-check-circle-fill"></i> Transfer Requests
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/inventory/transfer_confirmation.php">
                                    <i class="bi bi-check-circle"></i> Confirm Transfers
                                </a>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="pt-3">
    <?php else: ?>
    <!-- Non-authenticated layout -->
    <div class="container-fluid">
    <?php endif; ?>