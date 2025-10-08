<?php
// File: admin/activity_detail.php
// Activity detail view for administrators - shows detailed information about specific activities

session_start();
require_once '../controllers/AuthController.php';
require_once '../controllers/ActivityController.php';

$auth = new AuthController();
$activity = new ActivityController();

if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$currentUser = $auth->getCurrentUser();
if ($currentUser['role_name'] !== 'Administrator') {
    header('Location: ../dashboard.php');
    exit();
}

$activityId = $_GET['id'] ?? null;

if (!$activityId) {
    header('Location: ../dashboard.php');
    exit();
}

$result = $activity->getActivityDetail($activityId);

if (!$result['success']) {
    $error = $result['error'];
}

$pageTitle = 'Activity Detail';
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-info-circle text-primary"></i> Activity Detail
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="../dashboard.php" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php elseif ($result['success']): ?>
    <?php $activityData = $result['activity']; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-data"></i> Activity Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Activity Details</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Action:</strong></td>
                                    <td><?php echo htmlspecialchars($activityData['action']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Module:</strong></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($activityData['module']); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Record ID:</strong></td>
                                    <td><?php echo $activityData['record_id'] ? htmlspecialchars($activityData['record_id']) : 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date & Time:</strong></td>
                                    <td><?php echo date('F j, Y g:i A', strtotime($activityData['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>IP Address:</strong></td>
                                    <td>
                                        <?php
                                        $ipAddress = $activityData['ip_address'];

                                        // If ip_address is null or contains JSON, try to extract from details
                                        if (empty($ipAddress) || (is_string($ipAddress) && strpos($ipAddress, '{') === 0)) {
                                            if (!empty($activityData['details'])) {
                                                $details = json_decode($activityData['details'], true);
                                                if (isset($details['ip_address'])) {
                                                    $ipAddress = $details['ip_address'];
                                                }
                                            }
                                        }

                                        echo htmlspecialchars($ipAddress ?: 'Unknown');
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">User Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Full Name:</strong></td>
                                    <td><?php echo htmlspecialchars($activityData['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Username:</strong></td>
                                    <td><?php echo htmlspecialchars($activityData['username']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($activityData['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Role:</strong></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($activityData['role_name']); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Branch:</strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($activityData['branch_name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($activityData['branch_location']); ?>)</small>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($activityData['details_parsed'])): ?>
                <div class="card shadow mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul"></i> Activity Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded"><?php echo json_encode($activityData['details_parsed'], JSON_PRETTY_PRINT); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($activityData['module'] === 'PRODUCTION' && $activityData['record_id']): ?>
                        <a href="../production/batch_report.php?id=<?php echo $activityData['record_id']; ?>"
                           class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-file-text"></i> View Production Batch
                        </a>
                    <?php endif; ?>

                    <?php if ($activityData['module'] === 'ADMIN' && $activityData['action'] === 'USER_CREATED'): ?>
                        <a href="../admin/users.php" class="btn btn-outline-success w-100 mb-2">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                    <?php endif; ?>

                    <?php if ($activityData['module'] === 'ADMIN' && $activityData['action'] === 'BRANCH_CREATED'): ?>
                        <a href="../admin/branches.php" class="btn btn-outline-info w-100 mb-2">
                            <i class="bi bi-building"></i> Manage Branches
                        </a>
                    <?php endif; ?>

                    <hr>

                    <a href="../dashboard.php" class="btn btn-secondary w-100">
                        <i class="bi bi-house"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="card shadow mt-3">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bi bi-shield-check"></i> Security Information
                    </h6>
                </div>
                <div class="card-body">
                    <small class="text-muted">
                        <strong>Activity ID:</strong> <?php echo $activityData['id']; ?><br>
                        <strong>User ID:</strong> <?php echo $activityData['user_id']; ?><br>
                        <strong>Logged:</strong> <?php echo date('c', strtotime($activityData['created_at'])); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>