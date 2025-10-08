<?php
// File: dashboards/admin_dashboard.php
// Administrator dashboard for JM Animal Feeds ERP System
// Full system access with user management and system overview

$pageTitle = 'Administrator Dashboard';
include 'includes/header.php';

// Get stats for admin dashboard
try {
    $db = getDbConnection();

    // Get user statistics
    $userStats = $db->query("SELECT
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as weekly_active
        FROM users")->fetch();

    // Get branch statistics
    $branchStats = $db->query("SELECT
        COUNT(*) as total_branches,
        SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as active_branches
        FROM branches")->fetch();

    // Get production statistics (with error handling)
    try {
        $productionStats = $db->query("SELECT
            COUNT(*) as total_batches,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_batches,
            SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as active_batches
            FROM production_batches")->fetch();
    } catch (Exception $e) {
        $productionStats = ['total_batches' => 0, 'completed_batches' => 0, 'active_batches' => 0];
    }

    // Get activity statistics (with error handling)
    try {
        $activityStats = $db->query("SELECT
            COUNT(*) as total_activities,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as today_activities
            FROM activity_logs")->fetch();
    } catch (Exception $e) {
        $activityStats = ['total_activities' => 0, 'today_activities' => 0];
    }

    // Get role distribution
    $roleStats = $db->query("SELECT r.role_name, COUNT(u.id) as user_count
        FROM user_roles r
        LEFT JOIN users u ON r.id = u.role_id AND u.status = 'ACTIVE'
        GROUP BY r.id, r.role_name
        ORDER BY user_count DESC")->fetchAll();

    // Enhanced activity logging system
    $recentActivities = [];
    $moduleStats = [];

    // Initialize activity logger and notification manager
    try {
        require_once 'controllers/ActivityLogger.php';
        require_once 'controllers/NotificationManager.php';

        $activityLogger = new ActivityLogger();
        $notificationManager = new NotificationManager();

        // Get recent activities from all modules with detailed information
        $recentActivities = $activityLogger->getRecentActivities(15);

        // Get module activity statistics
        $moduleStats = $activityLogger->getModuleStats(30);

        // Get current user notifications
        $notifications = $notificationManager->getForUser($_SESSION['user_id'], 10);
        $unreadCount = $notificationManager->getUnreadCount($_SESSION['user_id']);

    } catch (Exception $e) {
        // Fallback if tables don't exist yet
        $recentActivities = [];
        $moduleStats = [];
        $notifications = [];
        $unreadCount = 0;
        error_log("Activity system not ready: " . $e->getMessage());
    }

} catch (Exception $e) {
    $error = "Failed to load dashboard data: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4">
    <div class="animate-fade-in">
        <h1 class="dashboard-title mb-2">
            <i class="bi bi-speedometer2"></i> Administrator Dashboard
        </h1>
        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>! Here's what's happening with your business today.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 animate-fade-in">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-primary">
                <i class="bi bi-download"></i> Export Report
            </button>
        </div>
        <a href="admin/users.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Add User
        </a>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-person-check"></i>
    <strong>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    You have full administrative access to the JM Animal Feeds ERP System.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Beautiful Statistics Cards -->
<div class="row mb-5">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in">
            <div class="d-flex align-items-center">
                <div class="stats-icon primary">
                    <i class="bi bi-people"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $userStats['total_users'] ?? 0; ?></div>
                    <div class="stats-label">Total Users</div>
                    <div class="stats-change text-success">
                        <i class="bi bi-arrow-up"></i> <?php echo $userStats['active_users'] ?? 0; ?> active
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.1s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon success">
                    <i class="bi bi-building"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $branchStats['total_branches'] ?? 0; ?></div>
                    <div class="stats-label">Branches</div>
                    <div class="stats-change text-success">
                        <i class="bi bi-check-circle"></i> <?php echo $branchStats['active_branches'] ?? 0; ?> active
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.2s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon info">
                    <i class="bi bi-gear-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $productionStats['total_batches'] ?? 0; ?></div>
                    <div class="stats-label">Production Batches</div>
                    <div class="stats-change text-info">
                        <i class="bi bi-clock"></i> <?php echo $productionStats['active_batches'] ?? 0; ?> in progress
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.3s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon warning">
                    <i class="bi bi-activity"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $activityStats['total_activities'] ?? 0; ?></div>
                    <div class="stats-label">Activities</div>
                    <div class="stats-change text-warning">
                        <i class="bi bi-calendar-day"></i> <?php echo $activityStats['today_activities'] ?? 0; ?> today
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row">
    <!-- Role Distribution Chart -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-pie-chart"></i> User Role Distribution
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($roleStats)): ?>
                    <?php foreach ($roleStats as $role): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($role['role_name']); ?></span>
                                <span><?php echo $role['user_count']; ?> users</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success"
                                     style="width: <?php echo ($role['user_count'] / max(1, $userStats['total_users'])) * 100; ?>%">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No role data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Real-time Activities -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow animate-fade-in">
            <div class="card-header py-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(45deg, #667eea, #764ba2); color: white;">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-activity"></i> Live System Activities
                </h6>
                <div>
                    <button class="btn btn-sm btn-outline-light me-2" onclick="refreshActivities()">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <a href="admin/activity_logs.php" class="btn btn-sm btn-light">View All</a>
                </div>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                <?php if (!empty($recentActivities)): ?>
                    <div class="list-group list-group-flush" id="activityFeed">
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="list-group-item activity-item" data-severity="<?php echo $activity['severity'] ?? 'LOW'; ?>">
                                <div class="d-flex align-items-start">
                                    <div class="activity-icon me-3">
                                        <?php
                                        $iconClass = 'info';
                                        $icon = 'info-circle';
                                        switch($activity['module']) {
                                            case 'SALES': $iconClass = 'success'; $icon = 'cart-fill'; break;
                                            case 'PRODUCTION': $iconClass = 'primary'; $icon = 'gear-fill'; break;
                                            case 'INVENTORY': $iconClass = 'warning'; $icon = 'boxes'; break;
                                            case 'TRANSFERS': $iconClass = 'info'; $icon = 'arrow-left-right'; break;
                                            case 'PURCHASES': $iconClass = 'secondary'; $icon = 'cart-plus-fill'; break;
                                            case 'EXPENSES': $iconClass = 'danger'; $icon = 'receipt-cutoff'; break;
                                            case 'FLEET': $iconClass = 'dark'; $icon = 'truck'; break;
                                            case 'ORDERS': $iconClass = 'warning'; $icon = 'clipboard-check'; break;
                                            case 'USER_MANAGEMENT': $iconClass = 'primary'; $icon = 'people-fill'; break;
                                        }
                                        ?>
                                        <div class="badge bg-<?php echo $iconClass; ?> rounded-circle p-2">
                                            <i class="bi bi-<?php echo $icon; ?>"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($activity['user_name']); ?>
                                                    <span class="badge bg-<?php echo $iconClass; ?> ms-2" style="font-size: 0.7rem;">
                                                        <?php echo htmlspecialchars($activity['module']); ?>
                                                    </span>
                                                </h6>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                <small class="text-muted">
                                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($activity['branch_name']); ?>
                                                    <span class="ms-2">
                                                        <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($activity['role_name']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">
                                                    <?php echo date('M j, H:i', strtotime($activity['created_at'])); ?>
                                                </small>
                                                <?php if ($activity['entity_id']): ?>
                                                <a href="#" onclick="viewActivityDetails(<?php echo $activity['id']; ?>)"
                                                   class="btn btn-xs btn-outline-secondary mt-1">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-activity" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2">No recent activities</p>
                        <p class="small">System activities will appear here as they happen</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Module Activity Overview -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow animate-fade-in">
            <div class="card-header py-3" style="background: linear-gradient(45deg, #4f46e5, #7c3aed); color: white;">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-graph-up-arrow"></i> Module Activity Analytics (Last 30 Days)
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($moduleStats)): ?>
                    <div class="row g-4">
                        <?php foreach ($moduleStats as $module): ?>
                            <div class="col-lg-2 col-md-4 col-sm-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <?php
                                    $moduleIcon = 'activity';
                                    $moduleColor = 'primary';
                                    switch($module['module']) {
                                        case 'SALES': $moduleIcon = 'cart-fill'; $moduleColor = 'success'; break;
                                        case 'PRODUCTION': $moduleIcon = 'gear-fill'; $moduleColor = 'primary'; break;
                                        case 'INVENTORY': $moduleIcon = 'boxes'; $moduleColor = 'warning'; break;
                                        case 'TRANSFERS': $moduleIcon = 'arrow-left-right'; $moduleColor = 'info'; break;
                                        case 'PURCHASES': $moduleIcon = 'cart-plus-fill'; $moduleColor = 'secondary'; break;
                                        case 'EXPENSES': $moduleIcon = 'receipt-cutoff'; $moduleColor = 'danger'; break;
                                        case 'FLEET': $moduleIcon = 'truck'; $moduleColor = 'dark'; break;
                                        case 'ORDERS': $moduleIcon = 'clipboard-check'; $moduleColor = 'warning'; break;
                                        case 'USER_MANAGEMENT': $moduleIcon = 'people-fill'; $moduleColor = 'primary'; break;
                                    }
                                    ?>
                                    <div class="mb-2">
                                        <i class="bi bi-<?php echo $moduleIcon; ?> text-<?php echo $moduleColor; ?>" style="font-size: 2rem;"></i>
                                    </div>
                                    <h4 class="text-<?php echo $moduleColor; ?> mb-1"><?php echo $module['total_activities']; ?></h4>
                                    <p class="mb-1 small fw-bold"><?php echo htmlspecialchars($module['module']); ?></p>
                                    <div class="d-flex justify-content-center align-items-center">
                                        <small class="text-success me-2">
                                            <i class="bi bi-calendar-day"></i> <?php echo $module['today_count']; ?> today
                                        </small>
                                        <?php if ($module['critical_count'] > 0): ?>
                                        <small class="text-danger">
                                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $module['critical_count']; ?> critical
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Quick Module Access -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <a href="admin/activity_logs.php?module=SALES" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-cart-fill"></i> Sales Logs
                                </a>
                                <a href="admin/activity_logs.php?module=PRODUCTION" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-gear-fill"></i> Production Logs
                                </a>
                                <a href="admin/activity_logs.php?module=INVENTORY" class="btn btn-outline-warning btn-sm">
                                    <i class="bi bi-boxes"></i> Inventory Logs
                                </a>
                                <a href="admin/activity_logs.php?module=TRANSFERS" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-arrow-left-right"></i> Transfer Logs
                                </a>
                                <a href="admin/activity_logs.php?module=FLEET" class="btn btn-outline-dark btn-sm">
                                    <i class="bi bi-truck"></i> Fleet Logs
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-graph-up" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3">No activity data available</p>
                        <p class="small">Module activity statistics will appear here once users start using the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- System Modules Grid -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card animate-fade-in">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="mb-0">
                    <i class="bi bi-grid-3x3-gap"></i> System Management
                </h5>
                <small>Complete ERP system modules and features</small>
            </div>
            <div class="card-body p-4">
                <!-- Core Administration -->
                <div class="mb-4">
                    <h6 class="text-muted mb-3 text-uppercase fw-bold"><i class="bi bi-shield-check"></i> Administration</h6>
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/users.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon primary">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">User Management</h6>
                                    <p class="text-muted small mb-0">System users, roles & permissions</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/branches.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon success">
                                        <i class="bi bi-building-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Branch Management</h6>
                                    <p class="text-muted small mb-0">Company branches & locations</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="hr/index.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon info">
                                        <i class="bi bi-person-badge-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">HR Management</h6>
                                    <p class="text-muted small mb-0">Employee, payroll & leave management</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="fleet/index.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon warning">
                                        <i class="bi bi-truck"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Fleet & Machines</h6>
                                    <p class="text-muted small mb-0">Vehicles, equipment & maintenance</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Operations -->
                <div class="mb-4">
                    <h6 class="text-muted mb-3 text-uppercase fw-bold"><i class="bi bi-gear-wide-connected"></i> Operations</h6>
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <a href="inventory/index.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #2563eb, #1d4ed8);">
                                        <i class="bi bi-boxes"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Inventory</h6>
                                    <p class="text-muted small mb-0">Stock management & tracking</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/production.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #7c3aed, #6d28d9);">
                                        <i class="bi bi-gear-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Production</h6>
                                    <p class="text-muted small mb-0">Batches, formulas & tracking</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="inventory/transfers.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #0891b2, #0e7490);">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Transfers</h6>
                                    <p class="text-muted small mb-0">Inter-branch stock transfers</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/formulas.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #ea580c, #dc2626);">
                                        <i class="bi bi-calculator-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Formulas</h6>
                                    <p class="text-muted small mb-0">Production recipes & ingredients</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sales & Finance -->
                <div class="mb-4">
                    <h6 class="text-muted mb-3 text-uppercase fw-bold"><i class="bi bi-currency-dollar"></i> Sales & Finance</h6>
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <a href="sales/pos.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #059669, #047857);">
                                        <i class="bi bi-cart-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Sales</h6>
                                    <p class="text-muted small mb-0">Customer sales & transactions</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="sales/customers.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #3b82f6, #1d4ed8);">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Customers</h6>
                                    <p class="text-muted small mb-0">Customer management & information</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="sales/order_management.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #c2410c, #9a3412);">
                                        <i class="bi bi-kanban"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Order Management</h6>
                                    <p class="text-muted small mb-0">Approve & manage customer orders</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="purchases/index.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #7c2d12, #991b1b);">
                                        <i class="bi bi-cart-plus-fill"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Purchases</h6>
                                    <p class="text-muted small mb-0">Supplier purchases & payments</p>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="row g-4 mt-2">
                        <div class="col-lg-3 col-md-6">
                            <a href="expenses/index.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #b45309, #92400e);">
                                        <i class="bi bi-receipt-cutoff"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Expenses</h6>
                                    <p class="text-muted small mb-0">Business expense management</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Tools & Reports -->
                <div class="mb-4">
                    <h6 class="text-muted mb-3 text-uppercase fw-bold"><i class="bi bi-tools"></i> Tools & Reports</h6>
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/barcodes.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #4338ca, #3730a3);">
                                        <i class="bi bi-upc-scan"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">SKU & Barcodes</h6>
                                    <p class="text-muted small mb-0">Product codes & barcodes</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/serial_lookup.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #be123c, #9f1239);">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">Serial Lookup</h6>
                                    <p class="text-muted small mb-0">Product serial verification</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="<?php echo BASE_URL; ?>/reports/index.php" class="text-decoration-none">
                                <div class="module-card">
                                <div class="stats-icon" style="background: linear-gradient(45deg, #4338ca, #3730a3);">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <h6 class="fw-bold mb-2">Financial Reports</h6>
                                <p class="text-muted small mb-1">Comprehensive financial analytics</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <a href="admin/settings.php" class="text-decoration-none">
                                <div class="module-card">
                                    <div class="stats-icon" style="background: linear-gradient(45deg, #6b7280, #4b5563);">
                                        <i class="bi bi-gear-wide-connected"></i>
                                    </div>
                                    <h6 class="fw-bold mb-2">System Settings</h6>
                                    <p class="text-muted small mb-0">Company information & configuration</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>