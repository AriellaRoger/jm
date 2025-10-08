<?php
// File: dashboards/default_dashboard.php
// Default fallback dashboard for JM Animal Feeds ERP System
// Used when user role doesn't match specific dashboards

$pageTitle = 'Dashboard';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-house text-primary"></i> Dashboard
    </h1>
</div>

<!-- Welcome Message -->
<div class="alert alert-primary alert-dismissible fade show" role="alert">
    <i class="bi bi-person-circle"></i>
    <strong>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    Welcome to the JM Animal Feeds ERP System.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<!-- User Information -->
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-person-badge"></i> User Information
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo htmlspecialchars($currentUser['name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Username:</strong></td>
                        <td><?php echo htmlspecialchars($currentUser['username']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo htmlspecialchars($currentUser['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Role:</strong></td>
                        <td>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($currentUser['role']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Branch:</strong></td>
                        <td><?php echo htmlspecialchars($currentUser['branch_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Branch Type:</strong></td>
                        <td>
                            <span class="badge bg-<?php echo $currentUser['branch_type'] === 'HQ' ? 'success' : 'info'; ?>">
                                <?php echo htmlspecialchars($currentUser['branch_type']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-info-circle"></i> System Information
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h5><i class="bi bi-grain"></i> JM Animal Feeds ERP System</h5>
                    <p class="mb-0">
                        A comprehensive enterprise resource planning system designed for
                        animal feed production and distribution businesses.
                    </p>
                </div>

                <div class="mt-3">
                    <h6>System Features:</h6>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success"></i> User Management & Authentication</li>
                        <li><i class="bi bi-check-circle text-success"></i> Role-based Access Control</li>
                        <li><i class="bi bi-check-circle text-success"></i> Multi-branch Support</li>
                        <li><a href="production/index.php" class="text-decoration-none"><i class="bi bi-gear text-success"></i> Production Management</a></li>
                        <li><a href="inventory/index.php" class="text-decoration-none"><i class="bi bi-boxes text-success"></i> Inventory Management</a></li>
                        <li><i class="bi bi-gear text-muted"></i> Sales & CRM (Coming Soon)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Available Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-lightning"></i> Available Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-person-circle text-primary" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-2">Profile</h5>
                                <p class="card-text">View and update your profile information.</p>
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person"></i> View Profile
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-shield-check text-success" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-2">Security</h5>
                                <p class="card-text">Change your password and security settings.</p>
                                <button class="btn btn-outline-success" disabled>
                                    <i class="bi bi-lock"></i> Security Settings
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-question-circle text-info" style="font-size: 3rem;"></i>
                                <h5 class="card-title mt-2">Help & Support</h5>
                                <p class="card-text">Get help and contact system administrators.</p>
                                <button class="btn btn-outline-info" disabled>
                                    <i class="bi bi-question-circle"></i> Get Help
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>