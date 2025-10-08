<?php
// File: inventory/transfer_confirmation.php
// Branch operator interface for confirming received transfers
// Allows branch operators to confirm receipt and complete inventory movement

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransferController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    header('Location: ../login.php');
    exit;
}

$transferController = new TransferController();

// Get user's branch for branch operators
$userBranchId = null;
if ($_SESSION['user_role'] === 'Branch Operator') {
    $userBranchId = $_SESSION['branch_id'];
}

$pendingTransfers = $transferController->getPendingTransfers($userBranchId);

include_once __DIR__ . '/../includes/header.php';
?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Transfer Confirmation</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <span class="badge bg-warning fs-6"><?= count($pendingTransfers) ?> Pending Transfers</span>
                </div>
            </div>

            <?php if ($_SESSION['user_role'] === 'Branch Operator'): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    You can confirm transfers sent to your branch: <strong><?= htmlspecialchars($_SESSION['branch_name']) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Pending Transfers for Confirmation -->
            <div class="card">
                <div class="card-header">
                    <h5>Transfers Awaiting Confirmation</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingTransfers)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> No pending transfers to confirm
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Transfer #</th>
                                        <th>To Branch</th>
                                        <th>Created By</th>
                                        <th>Created Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransfers as $transfer): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($transfer['transfer_number']) ?></strong></td>
                                            <td>
                                                <span class="badge bg-primary"><?= htmlspecialchars($transfer['to_branch_name']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($transfer['created_by_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($transfer['created_at'])) ?></td>
                                            <td>
                                                <span class="badge bg-warning">PENDING</span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info me-1" onclick="viewTransferDetails(<?= $transfer['id'] ?>)">
                                                    <i class="bi bi-eye"></i> View Details
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="confirmTransfer(<?= $transfer['id'] ?>, '<?= htmlspecialchars($transfer['transfer_number']) ?>')">
                                                    <i class="bi bi-check-circle"></i> Confirm Receipt
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instructions -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6><i class="bi bi-info-circle"></i> How Transfer Confirmation Works</h6>
                        </div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li><strong>Review Transfer Details:</strong> Click "View Details" to see all bags and items in the transfer</li>
                                <li><strong>Verify Physical Receipt:</strong> Ensure all items have been physically received at your branch</li>
                                <li><strong>Confirm Receipt:</strong> Click "Confirm Receipt" to complete the transfer</li>
                                <li><strong>Automatic Updates:</strong> Inventory will be automatically updated at your branch</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6><i class="bi bi-check-circle"></i> What Happens After Confirmation</h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li><strong>Finished Product Bags:</strong> Moved to your branch inventory with serial numbers</li>
                                <li><strong>Raw Materials:</strong> Added to your branch stock or new records created</li>
                                <li><strong>Third Party Products:</strong> Stock levels updated at your branch</li>
                                <li><strong>Packaging Materials:</strong> Available for use at your branch</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>

<!-- Transfer Details Modal -->
<div class="modal fade" id="transferDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transferDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Transfer Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Important:</strong> Only confirm this transfer if you have physically received all items.
                </div>
                <p>Are you sure you want to confirm receipt of transfer <strong id="confirmTransferNumber"></strong>?</p>
                <p class="text-muted">This action will:</p>
                <ul class="text-muted">
                    <li>Move all bags to your branch inventory</li>
                    <li>Update bulk item stock levels at your branch</li>
                    <li>Mark the transfer as completed</li>
                    <li>This action cannot be undone</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="executeConfirmation()">
                    <i class="bi bi-check-circle"></i> Yes, Confirm Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentTransferId = null;

// View transfer details
function viewTransferDetails(transferId) {
    fetch(`../admin/ajax/get_transfer_details.php?id=${transferId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('transferDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('transferDetailsModal'));
                modal.show();
            } else {
                alert('Error loading transfer details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading transfer details');
        });
}

// Show confirmation modal
function confirmTransfer(transferId, transferNumber) {
    currentTransferId = transferId;
    document.getElementById('confirmTransferNumber').textContent = transferNumber;
    const modal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    modal.show();
}

// Execute confirmation
function executeConfirmation() {
    if (!currentTransferId) return;

    fetch('ajax/confirm_transfer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            transfer_id: currentTransferId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Transfer confirmed successfully! Inventory has been updated.');
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
            modal.hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error confirming transfer');
    });
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>