<?php
// File: dashboards/supervisor_dashboard.php
// Supervisor dashboard for JM Animal Feeds ERP System
// Production oversight and staff management access

$pageTitle = 'Supervisor Dashboard';
include 'includes/header.php';

// Get supervisor-specific data
try {
    $db = getDbConnection();

    // Get production statistics
    try {
        $productionStats = $db->query("SELECT
            COUNT(*) as total_batches,
            SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) as completed_batches,
            SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as active_batches
            FROM production_batches
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch();
    } catch (Exception $e) {
        $productionStats = ['total_batches' => 0, 'completed_batches' => 0, 'active_batches' => 0];
    }

    // Get transfer statistics
    try {
        $transferStats = $db->query("SELECT
            COUNT(*) as total_transfers,
            SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_transfers,
            SUM(CASE WHEN status = 'IN_TRANSIT' THEN 1 ELSE 0 END) as in_transit
            FROM transfer_requests
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch();
    } catch (Exception $e) {
        $transferStats = ['total_transfers' => 0, 'pending_transfers' => 0, 'in_transit' => 0];
    }

    // Get staff count
    $staffCount = $db->query("SELECT COUNT(*) as count FROM users
        WHERE branch_id = {$currentUser['branch_id']}
        AND status = 'ACTIVE'")->fetch();

    // Order statistics removed - no order system

    // Get recent activities
    try {
        $recentActivities = $db->query("SELECT
            action,
            details,
            DATE_FORMAT(created_at, '%H:%i') as time,
            CASE
                WHEN action LIKE '%CREATE%' THEN 'success'
                WHEN action LIKE '%UPDATE%' THEN 'primary'
                WHEN action LIKE '%DELETE%' THEN 'danger'
                ELSE 'secondary'
            END as status
            FROM activity_logs
            WHERE DATE(created_at) = CURDATE()
            ORDER BY created_at DESC
            LIMIT 5")->fetchAll();
    } catch (Exception $e) {
        $recentActivities = [];
    }

    // Get production lines status from actual product data
    try {
        $productionLines = $db->query("SELECT
            p.id,
            p.name as product_name,
            p.package_size,
            COUNT(CASE WHEN pb.status IN ('Sealed', 'Opened') THEN 1 END) as current_stock,
            COUNT(CASE WHEN pb.created_at >= CURDATE() THEN 1 END) as daily_production,
            CASE
                WHEN COUNT(CASE WHEN pb.created_at >= CURDATE() THEN 1 END) > 0 THEN 'Running'
                WHEN COUNT(CASE WHEN pb.status IN ('Sealed', 'Opened') THEN 1 END) > 10 THEN 'Standby'
                ELSE 'Maintenance'
            END as status
            FROM products p
            LEFT JOIN product_bags pb ON p.id = pb.product_id AND pb.branch_id = 1
            WHERE p.status = 'Active'
            GROUP BY p.id, p.name, p.package_size
            ORDER BY p.name
            LIMIT 5")->fetchAll();
    } catch (Exception $e) {
        $productionLines = [];
    }

    // Get inventory summary for headquarters
    try {
        $inventorySummary = $db->query("SELECT
            COUNT(DISTINCT p.id) as total_products,
            COUNT(CASE WHEN pb.status = 'Sealed' THEN 1 END) as sealed_bags,
            COUNT(CASE WHEN pb.status = 'Opened' THEN 1 END) as opened_bags,
            COUNT(CASE WHEN pb.status IN ('Sealed', 'Opened') THEN 1 END) as available_bags
            FROM products p
            LEFT JOIN product_bags pb ON p.id = pb.product_id AND pb.branch_id = 1
            WHERE p.status = 'Active'")->fetch();
    } catch (Exception $e) {
        $inventorySummary = ['total_products' => 0, 'sealed_bags' => 0, 'opened_bags' => 0, 'available_bags' => 0];
    }

} catch (Exception $e) {
    $error = "Failed to load dashboard data: " . $e->getMessage();
    $productionStats = ['total_batches' => 0, 'completed_batches' => 0, 'active_batches' => 0];
    $transferStats = ['total_transfers' => 0, 'pending_transfers' => 0, 'in_transit' => 0];
    $staffCount = ['count' => 0];
    $recentActivities = [];
    $productionLines = [];
    $inventorySummary = ['total_products' => 0, 'sealed_bags' => 0, 'opened_bags' => 0, 'available_bags' => 0];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-4 pb-3 mb-4">
    <div class="animate-fade-in">
        <h1 class="dashboard-title mb-2">
            <i class="bi bi-person-badge-fill"></i> Supervisor Dashboard
        </h1>
        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?>! Monitor operations and manage your team effectively.</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0 animate-fade-in">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-primary">
                <i class="bi bi-calendar-event"></i> Schedule Meeting
            </button>
        </div>
        </a>
    </div>
</div>

<!-- Alert for Supervisor -->
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-info-circle"></i>
    <strong>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    You have supervisory access to production, transfers, and team management functions.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-5">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in">
            <div class="d-flex align-items-center">
                <div class="stats-icon primary">
                    <i class="bi bi-gear-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $productionStats['total_batches'] ?? 0; ?></div>
                    <div class="stats-label">Production Batches</div>
                    <div class="stats-change text-info">
                        <i class="bi bi-clock"></i> <?php echo $productionStats['active_batches'] ?? 0; ?> active
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.1s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon success">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $transferStats['total_transfers'] ?? 0; ?></div>
                    <div class="stats-label">Transfers</div>
                    <div class="stats-change text-warning">
                        <i class="bi bi-hourglass-split"></i> <?php echo $transferStats['pending_transfers'] ?? 0; ?> pending
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.2s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon info">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $inventorySummary['available_bags'] ?? 0; ?></div>
                    <div class="stats-label">Available Bags</div>
                    <div class="stats-change text-success">
                        <i class="bi bi-check-circle"></i> <?php echo $inventorySummary['sealed_bags'] ?? 0; ?> sealed
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stats-card animate-fade-in" style="animation-delay: 0.3s;">
            <div class="d-flex align-items-center">
                <div class="stats-icon warning">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="stats-number"><?php echo $staffCount['count'] ?? 0; ?></div>
                    <div class="stats-label">Team Members</div>
                    <div class="stats-change text-success">
                        <i class="bi bi-check-circle"></i> Active staff
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Supervisor Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card animate-fade-in">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="mb-0">
                    <i class="bi bi-lightning-fill"></i> Quick Actions
                </h5>
                <small>Supervisory functions and team management</small>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Production Management -->
                    <div class="col-lg-3 col-md-6">
                        <a href="admin/production.php" class="text-decoration-none">
                            <div class="module-card">
                                <div class="stats-icon" style="background: linear-gradient(45deg, #7c3aed, #6d28d9);">
                                    <i class="bi bi-gear-fill"></i>
                                </div>
                                <h6 class="fw-bold mb-2">Production</h6>
                                <p class="text-muted small mb-0">Monitor & manage production batches</p>
                            </div>
                        </a>
                    </div>

                    <!-- Transfer Management -->
                    <div class="col-lg-3 col-md-6">
                        <a href="inventory/transfers.php" class="text-decoration-none">
                            <div class="module-card">
                                <div class="stats-icon" style="background: linear-gradient(45deg, #0891b2, #0e7490);">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <h6 class="fw-bold mb-2">Transfers</h6>
                                <p class="text-muted small mb-0">Approve & manage stock transfers</p>
                            </div>
                        </a>
                    </div>

                    <!-- Order Management -->
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

                    <!-- Customer Management -->
                    <div class="col-lg-3 col-md-6">
                        <a href="sales/customers.php" class="text-decoration-none">
                            <div class="module-card">
                                <div class="stats-icon" style="background: linear-gradient(45deg, #3b82f6, #1d4ed8);">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <h6 class="fw-bold mb-2">Customers</h6>
                                <p class="text-muted small mb-0">Manage customer information</p>
                            </div>
                        </a>
                    </div>

                    <!-- Fleet Management -->
                    <div class="col-lg-3 col-md-6">
                        <a href="fleet/index.php" class="text-decoration-none">
                            <div class="module-card">
                                <div class="stats-icon warning">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <h6 class="fw-bold mb-2">Fleet Management</h6>
                                <p class="text-muted small mb-0">Manage vehicles & transport</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row">
    <!-- Recent Activities -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-activity"></i> Today's Activities
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="badge bg-<?php echo $activity['status']; ?> rounded-pill">
                                    <?php echo $activity['time']; ?>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="mb-0"><?php echo htmlspecialchars($activity['details']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-lightning"></i> Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="admin/production.php" class="btn btn-outline-success">
                        <i class="bi bi-gear"></i> Production Control
                    </a>
                    <a href="inventory/index.php" class="btn btn-outline-primary">
                        <i class="bi bi-boxes"></i> Inventory Management
                    </a>
                    <a href="inventory/transfers.php" class="btn btn-outline-info">
                        <i class="bi bi-arrow-left-right"></i> Stock Transfers
                    </a>
                    <a href="sales/order_management.php" class="btn btn-outline-warning">
                        <i class="bi bi-kanban"></i> Order Management
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Production Overview -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-factory"></i> Production Lines Status
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Output Today</th>
                                <th>Target</th>
                                <th>Efficiency</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($productionLines)): ?>
                                <?php foreach ($productionLines as $index => $line): ?>
                                    <?php
                                    $lineNumber = chr(65 + $index); // A, B, C, etc.
                                    $statusColor = $line['status'] === 'Running' ? 'success' :
                                                 ($line['status'] === 'Standby' ? 'warning' : 'danger');
                                    $dailyTarget = 50; // Default target, could be made configurable
                                    $efficiency = $dailyTarget > 0 ? min(100, ($line['daily_production'] / $dailyTarget) * 100) : 0;
                                    $actionText = $line['status'] === 'Running' ? 'Monitor' :
                                                ($line['status'] === 'Standby' ? 'Start' : 'Check');
                                    $actionColor = $line['status'] === 'Running' ? 'primary' :
                                                 ($line['status'] === 'Standby' ? 'success' : 'warning');
                                    ?>
                                    <tr>
                                        <td>Line <?php echo $lineNumber; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($line['product_name']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($line['package_size']); ?></small>
                                        </td>
                                        <td><span class="badge bg-<?php echo $statusColor; ?>"><?php echo $line['status']; ?></span></td>
                                        <td><?php echo $line['daily_production']; ?> bags</td>
                                        <td><?php echo $dailyTarget; ?> bags</td>
                                        <td><?php echo number_format($efficiency, 1); ?>%</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-<?php echo $actionColor; ?>"><?php echo $actionText; ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        <i class="bi bi-info-circle"></i> No production data available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>