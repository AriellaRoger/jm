<?php
// File: admin/activity_logs.php
// Comprehensive activity logs interface for administrators to view all system activities

session_start();
require_once '../controllers/AuthController.php';
require_once '../controllers/ActivityController.php';
require_once '../controllers/AdminController.php';

$auth = new AuthController();
$activity = new ActivityController();
$admin = new AdminController();

if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$currentUser = $auth->getCurrentUser();
if ($currentUser['role_name'] !== 'Administrator') {
    header('Location: ../dashboard.php');
    exit();
}

// Get filter parameters
$branchFilter = $_GET['branch_filter'] ?? '';
$moduleFilter = $_GET['module_filter'] ?? '';
$userFilter = $_GET['user_filter'] ?? '';
$limit = min(100, max(10, intval($_GET['limit'] ?? 50)));

// Get activities with filters
$activitiesResult = $activity->getAllActivities($limit, $branchFilter ?: null, $moduleFilter ?: null, $userFilter ?: null);
$activities = $activitiesResult['success'] ? $activitiesResult['activities'] : [];

// Get filter options
$branches = $admin->getAllBranches(); // Returns array directly
$users = $admin->getAllUsers(); // Returns array directly

// Get statistics
$moduleStatsResult = $activity->getModuleActivityCount();
$moduleStats = $moduleStatsResult['success'] ? $moduleStatsResult['modules'] : [];

$pageTitle = 'Activity Logs';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-activity text-warning"></i> System Activity Logs
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
        <a href="../dashboard.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-house"></i> Dashboard
        </a>
    </div>
</div>

<!-- Activity Statistics -->
<div class="row mb-4">
    <?php foreach ($moduleStats as $index => $module): ?>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-left-<?php echo $index % 4 === 0 ? 'primary' : ($index % 4 === 1 ? 'success' : ($index % 4 === 2 ? 'info' : 'warning')); ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-uppercase mb-1"><?php echo htmlspecialchars($module['module']); ?></div>
                            <div class="h6 mb-0 font-weight-bold"><?php echo $module['count']; ?> activities</div>
                            <small class="text-muted"><?php echo $module['today_count']; ?> today</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-funnel"></i> Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="branch_filter" class="form-label">Branch</label>
                <select class="form-select" id="branch_filter" name="branch_filter">
                    <option value="">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php echo $branchFilter == $branch['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="module_filter" class="form-label">Module</label>
                <select class="form-select" id="module_filter" name="module_filter">
                    <option value="">All Modules</option>
                    <option value="ADMIN" <?php echo $moduleFilter === 'ADMIN' ? 'selected' : ''; ?>>Admin</option>
                    <option value="PRODUCTION" <?php echo $moduleFilter === 'PRODUCTION' ? 'selected' : ''; ?>>Production</option>
                    <option value="SALES" <?php echo $moduleFilter === 'SALES' ? 'selected' : ''; ?>>Sales</option>
                    <option value="INVENTORY" <?php echo $moduleFilter === 'INVENTORY' ? 'selected' : ''; ?>>Inventory</option>
                    <option value="USER" <?php echo $moduleFilter === 'USER' ? 'selected' : ''; ?>>User</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="user_filter" class="form-label">User</label>
                <select class="form-select" id="user_filter" name="user_filter">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="limit" class="form-label">Limit</label>
                <select class="form-select" id="limit" name="limit">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Activity Logs Table -->
<div class="card shadow">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list-ul"></i> Activity History (<?php echo count($activities); ?> records)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Branch</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $activityItem): ?>
                            <tr>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($activityItem['created_at'])); ?><br>
                                        <?php echo date('g:i A', strtotime($activityItem['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($activityItem['full_name']); ?></strong>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($activityItem['username']); ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($activityItem['role_name']); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $activityItem['branch_type'] === 'HQ' ? 'primary' : 'info'; ?>">
                                        <?php echo htmlspecialchars($activityItem['branch_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $activityItem['module'] === 'PRODUCTION' ? 'info' :
                                             ($activityItem['module'] === 'ADMIN' ? 'warning' :
                                              ($activityItem['module'] === 'SALES' ? 'success' : 'secondary'));
                                    ?>">
                                        <?php echo htmlspecialchars($activityItem['module']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($activityItem['action']); ?></div>
                                    <?php if ($activityItem['record_id']): ?>
                                        <small class="text-muted">ID: <?php echo $activityItem['record_id']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted font-monospace">
                                        <?php
                                        $ipAddress = $activityItem['ip_address'];

                                        // If ip_address is null or contains JSON, try to extract from details
                                        if (empty($ipAddress) || (is_string($ipAddress) && strpos($ipAddress, '{') === 0)) {
                                            if (!empty($activityItem['details'])) {
                                                $details = json_decode($activityItem['details'], true);
                                                if (isset($details['ip_address'])) {
                                                    $ipAddress = $details['ip_address'];
                                                }
                                            }
                                        }

                                        echo htmlspecialchars($ipAddress ?: 'Unknown');
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="activity_detail.php?id=<?php echo $activityItem['id']; ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i><br>
                                No activities found with current filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid #007bff;
}
.border-left-success {
    border-left: 4px solid #28a745;
}
.border-left-info {
    border-left: 4px solid #17a2b8;
}
.border-left-warning {
    border-left: 4px solid #ffc107;
}
</style>

<?php include '../includes/footer.php'; ?>