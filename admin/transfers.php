<?php
// File: admin/transfers.php
// Transfer management interface for administrators and supervisors
// Allows selecting bags by serial number and bulk items by quantity for branch transfers

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransferController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('Location: ../login.php');
    exit;
}

$transferController = new TransferController();
$availableBags = $transferController->getAvailableBags();
$availableItems = $transferController->getAvailableBulkItems();
$branches = $transferController->getBranches();
$pendingTransfers = $transferController->getPendingTransfers();

include_once __DIR__ . '/../includes/header.php';
?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Transfer Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" onclick="showCreateTransfer()">
                        <i class="bi bi-plus-circle"></i> New Transfer
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5><?= count($availableBags) ?></h5>
                            <p class="mb-0">Available Bags at HQ</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5><?= count($availableItems['raw_materials']) ?></h5>
                            <p class="mb-0">Raw Materials Available</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5><?= count($availableItems['third_party_products']) ?></h5>
                            <p class="mb-0">Third Party Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5><?= count($pendingTransfers) ?></h5>
                            <p class="mb-0">Pending Transfers</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Transfers -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Pending Transfers</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingTransfers)): ?>
                        <div class="alert alert-info">No pending transfers</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Transfer #</th>
                                        <th>To Branch</th>
                                        <th>Created By</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransfers as $transfer): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($transfer['transfer_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($transfer['to_branch_name']) ?></td>
                                            <td><?= htmlspecialchars($transfer['created_by_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($transfer['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewTransferDetails(<?= $transfer['id'] ?>)">
                                                    <i class="bi bi-eye"></i> View
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
        </main>

<!-- Create Transfer Modal -->
<div class="modal fade" id="createTransferModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createTransferForm">
                    <!-- Branch Selection -->
                    <div class="mb-3">
                        <label class="form-label">Destination Branch</label>
                        <select class="form-select" name="to_branch_id" required>
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= htmlspecialchars($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tabs for different types -->
                    <ul class="nav nav-tabs" id="transferTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="bags-tab" data-bs-toggle="tab" data-bs-target="#bags" type="button" role="tab">
                                Finished Product Bags (<?= count($availableBags) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="raw-materials-tab" data-bs-toggle="tab" data-bs-target="#raw-materials" type="button" role="tab">
                                Raw Materials (<?= count($availableItems['raw_materials']) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="third-party-tab" data-bs-toggle="tab" data-bs-target="#third-party" type="button" role="tab">
                                Third Party (<?= count($availableItems['third_party_products']) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="packaging-tab" data-bs-toggle="tab" data-bs-target="#packaging" type="button" role="tab">
                                Packaging (<?= count($availableItems['packaging_materials']) ?>)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="transferTabContent">
                        <!-- Finished Product Bags -->
                        <div class="tab-pane fade show active" id="bags" role="tabpanel">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllBags()">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllBags()">Clear All</button>
                            </div>
                            <div class="row">
                                <?php foreach ($availableBags as $bag): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="card border">
                                            <div class="card-body p-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="selected_bags[]" value="<?= $bag['id'] ?>" id="bag_<?= $bag['id'] ?>">
                                                    <label class="form-check-label" for="bag_<?= $bag['id'] ?>">
                                                        <strong><?= htmlspecialchars($bag['product_name']) ?></strong> - <?= htmlspecialchars($bag['package_size']) ?><br>
                                                        <small class="text-muted">Serial: <?= htmlspecialchars($bag['serial_number']) ?></small><br>
                                                        <small class="text-muted">Production: <?= date('M j, Y', strtotime($bag['production_date'])) ?></small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($availableBags)): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">No sealed bags available at HQ</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Raw Materials -->
                        <div class="tab-pane fade" id="raw-materials" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Available Stock</th>
                                            <th>Unit</th>
                                            <th>Quantity to Transfer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableItems['raw_materials'] as $item): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td><?= number_format($item['current_stock'], 1) ?></td>
                                                <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm"
                                                           name="raw_material_<?= $item['id'] ?>"
                                                           step="0.1" min="0" max="<?= $item['current_stock'] ?>"
                                                           placeholder="0.0" style="width: 100px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($availableItems['raw_materials'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No raw materials available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Third Party Products -->
                        <div class="tab-pane fade" id="third-party" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Available Stock</th>
                                            <th>Unit</th>
                                            <th>Quantity to Transfer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableItems['third_party_products'] as $item): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td><?= number_format($item['current_stock'], 1) ?></td>
                                                <td><?= htmlspecialchars($item['unit_of_measure']) ?></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm"
                                                           name="third_party_<?= $item['id'] ?>"
                                                           step="0.1" min="0" max="<?= $item['current_stock'] ?>"
                                                           placeholder="0.0" style="width: 100px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($availableItems['third_party_products'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No third party products available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Packaging Materials -->
                        <div class="tab-pane fade" id="packaging" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Material</th>
                                            <th>Available Stock</th>
                                            <th>Unit</th>
                                            <th>Quantity to Transfer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($availableItems['packaging_materials'] as $item): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                                <td><?= number_format($item['current_stock'], 1) ?></td>
                                                <td><?= htmlspecialchars($item['unit']) ?></td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm"
                                                           name="packaging_<?= $item['id'] ?>"
                                                           step="0.1" min="0" max="<?= $item['current_stock'] ?>"
                                                           placeholder="0.0" style="width: 100px;">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($availableItems['packaging_materials'])): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No packaging materials available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createTransfer()">Create Transfer</button>
            </div>
        </div>
    </div>
</div>

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

<script>
// Show create transfer modal
function showCreateTransfer() {
    const modal = new bootstrap.Modal(document.getElementById('createTransferModal'));
    modal.show();
}

// Select all bags
function selectAllBags() {
    document.querySelectorAll('input[name="selected_bags[]"]').forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

// Clear all bags
function clearAllBags() {
    document.querySelectorAll('input[name="selected_bags[]"]').forEach(function(checkbox) {
        checkbox.checked = false;
    });
}

// Create transfer
function createTransfer() {
    const form = document.getElementById('createTransferForm');
    const formData = new FormData(form);

    // Add bulk items
    const bulkItems = [];

    // Raw materials
    <?php foreach ($availableItems['raw_materials'] as $item): ?>
        const rawMaterial<?= $item['id'] ?> = document.querySelector('input[name="raw_material_<?= $item['id'] ?>"]').value;
        if (rawMaterial<?= $item['id'] ?> > 0) {
            bulkItems.push({
                item_type: 'RAW_MATERIAL',
                item_id: <?= $item['id'] ?>,
                quantity: parseFloat(rawMaterial<?= $item['id'] ?>)
            });
        }
    <?php endforeach; ?>

    // Third party products
    <?php foreach ($availableItems['third_party_products'] as $item): ?>
        const thirdParty<?= $item['id'] ?> = document.querySelector('input[name="third_party_<?= $item['id'] ?>"]').value;
        if (thirdParty<?= $item['id'] ?> > 0) {
            bulkItems.push({
                item_type: 'THIRD_PARTY_PRODUCT',
                item_id: <?= $item['id'] ?>,
                quantity: parseFloat(thirdParty<?= $item['id'] ?>)
            });
        }
    <?php endforeach; ?>

    // Packaging materials
    <?php foreach ($availableItems['packaging_materials'] as $item): ?>
        const packaging<?= $item['id'] ?> = document.querySelector('input[name="packaging_<?= $item['id'] ?>"]').value;
        if (packaging<?= $item['id'] ?> > 0) {
            bulkItems.push({
                item_type: 'PACKAGING_MATERIAL',
                item_id: <?= $item['id'] ?>,
                quantity: parseFloat(packaging<?= $item['id'] ?>)
            });
        }
    <?php endforeach; ?>

    // Get selected bags
    const selectedBags = [];
    document.querySelectorAll('input[name="selected_bags[]"]:checked').forEach(checkbox => {
        selectedBags.push(parseInt(checkbox.value));
    });

    // Validate
    if (!formData.get('to_branch_id')) {
        alert('Please select a destination branch');
        return;
    }

    if (selectedBags.length === 0 && bulkItems.length === 0) {
        alert('Please select at least one bag or specify quantities for bulk items');
        return;
    }

    // Prepare data
    const transferData = {
        to_branch_id: formData.get('to_branch_id'),
        selected_bags: selectedBags,
        bulk_items: bulkItems
    };

    // Submit
    fetch('ajax/create_transfer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(transferData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Transfer created successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('createTransferModal'));
            modal.hide();

            // Immediately open print form in new window
            if (data.print_ready && data.print_url) {
                const printWindow = window.open(data.print_url, '_blank', 'width=900,height=800,scrollbars=yes,resizable=yes');
                if (printWindow) {
                    printWindow.focus();
                    // Auto-print after page loads
                    printWindow.onload = function() {
                        setTimeout(() => {
                            printWindow.print();
                        }, 1000);
                    };
                }
            }

            // Reload the page to refresh transfer list
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating transfer');
    });
}

// View transfer details
function viewTransferDetails(transferId) {
    fetch(`ajax/get_transfer_details.php?id=${transferId}`)
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
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>