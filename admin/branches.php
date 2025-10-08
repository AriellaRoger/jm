<?php
// File: admin/branches.php
// Branch management interface for JM Animal Feeds ERP System
// Allows administrators to manage all company branches

session_start();
require_once '../controllers/AuthController.php';
require_once '../controllers/AdminController.php';

$auth = new AuthController();
$admin = new AdminController();

// Check if user is logged in and is an administrator
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$user = $auth->getCurrentUser();
if ($user['role_name'] !== 'Administrator') {
    header('Location: ../dashboard.php');
    exit();
}

// Get data for the page
$branches = $admin->getAllBranches();
$branchStats = $admin->getBranchStatistics();

$pageTitle = 'Branch Management';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-building"></i> Branch Management</h2>
        <div>
            <a href="../dashboard.php" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newBranchModal">
                <i class="bi bi-plus-circle"></i> Add New Branch
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $branchStats['total_branches']; ?></h4>
                            <p class="mb-0">Total Branches</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-building h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $branchStats['active_branches']; ?></h4>
                            <p class="mb-0">Active Branches</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $branchStats['hq_count']; ?></h4>
                            <p class="mb-0">Headquarters</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-house h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $branchStats['branch_count']; ?></h4>
                            <p class="mb-0">Regional Branches</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-geo-alt h2 mb-0"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Branches List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-list"></i> All Branches</h5>
        </div>
        <div class="card-body">
            <?php if (empty($branches)): ?>
                <p class="text-muted">No branches found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($branch['name']); ?></strong>
                                        <?php if ($branch['type'] === 'HQ'): ?>
                                            <span class="badge bg-primary ms-2">HQ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($branch['location']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $branch['type'] === 'HQ' ? 'primary' : 'info'; ?>">
                                            <?php echo $branch['type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $branch['status'] === 'ACTIVE' ? 'success' : 'secondary'; ?>">
                                            <?php echo $branch['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $branch['user_count']; ?> total</span>
                                        <br><small class="text-muted"><?php echo $branch['active_users']; ?> active</small>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($branch['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button class="btn btn-outline-warning" onclick="editBranch(<?php echo $branch['id']; ?>)" title="Edit Branch">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="../admin/users.php?branch_id=<?php echo $branch['id']; ?>" class="btn btn-outline-info" title="View Users">
                                                <i class="bi bi-people"></i>
                                            </a>
                                            <?php if ($branch['type'] !== 'HQ' && $branch['user_count'] == 0): ?>
                                                <button class="btn btn-outline-danger" onclick="deleteBranch(<?php echo $branch['id']; ?>)" title="Delete Branch">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Branch Modal -->
<div class="modal fade" id="newBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newBranchForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="branchName" class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" id="branchName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="branchLocation" class="form-label">Location *</label>
                        <input type="text" class="form-control" id="branchLocation" name="location" required placeholder="City, Region, Country">
                    </div>
                    <div class="mb-3">
                        <label for="branchType" class="form-label">Branch Type *</label>
                        <select class="form-select" id="branchType" name="type" required>
                            <option value="">Select Type</option>
                            <option value="BRANCH">Regional Branch</option>
                            <option value="HQ">Headquarters</option>
                        </select>
                        <div class="form-text">Note: Only one headquarters should exist</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editBranchBody">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
// Create new branch
document.getElementById('newBranchForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);

    fetch('ajax/create_branch.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Branch created successfully!');
            location.reload();
        } else {
            alert('Error creating branch: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating branch');
    });
});

// Edit branch
function editBranch(branchId) {
    fetch(`ajax/edit_branch.php?id=${branchId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('editBranchBody').innerHTML = data.html;
            const modal = new bootstrap.Modal(document.getElementById('editBranchModal'));
            modal.show();
        } else {
            alert('Error loading branch: ' + data.error);
        }
    });
}

// Delete branch
function deleteBranch(branchId) {
    if (confirm('Are you sure you want to delete this branch? This action cannot be undone.')) {
        fetch('ajax/delete_branch.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({branch_id: branchId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Branch deleted successfully!');
                location.reload();
            } else {
                alert('Error deleting branch: ' + data.error);
            }
        });
    }
}
</script>

<?php include '../includes/footer.php'; ?>