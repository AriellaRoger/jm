<?php
// File: admin/stock_adjustments.php
// Administrator-only stock adjustment interface for JM Animal Feeds ERP System
// Allows adjustment of all product types across all branches with complete audit trail

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/StockAdjustmentController.php';

// Check authentication and admin access
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Ensure only administrators can access
if ($_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$stockController = new StockAdjustmentController();
$branches = $stockController->getAllBranches();

// Get selected branch (default to HQ)
$selected_branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 1;
$selected_branch = null;
foreach ($branches as $branch) {
    if ($branch['id'] == $selected_branch_id) {
        $selected_branch = $branch;
        break;
    }
}

$inventory_summary = $stockController->getBranchInventorySummary($selected_branch_id);

$pageTitle = "Stock Adjustments";
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-sliders"></i> Stock Adjustments</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-info" onclick="viewAuditTrail()">
                <i class="bi bi-clock-history"></i> Audit Trail
            </button>
        </div>
    </div>
</div>

<!-- Branch Selection -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-building"></i> Branch Selection</h5>
            </div>
            <div class="card-body">
                <select class="form-select" id="branchSelect" onchange="changeBranch()">
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" <?php echo $branch['id'] == $selected_branch_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?> - <?php echo htmlspecialchars($branch['location']); ?>
                            <?php echo $branch['type'] === 'HQ' ? '(Headquarters)' : '(Branch)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Select branch to view and adjust inventory</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Inventory Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3">
                        <strong><?php echo $inventory_summary['raw_materials_count'] ?? 0; ?></strong>
                        <small class="d-block text-muted">Raw Materials</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <strong><?php echo $inventory_summary['third_party_count'] ?? 0; ?></strong>
                        <small class="d-block text-muted">Third Party</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <strong><?php echo $inventory_summary['packaging_count'] ?? 0; ?></strong>
                        <small class="d-block text-muted">Packaging</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <strong><?php echo ($inventory_summary['sealed_bags_count'] ?? 0) + ($inventory_summary['opened_bags_count'] ?? 0); ?></strong>
                        <small class="d-block text-muted">Product Bags</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert container -->
<div id="alertContainer"></div>

<!-- Stock Adjustment Tabs -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="stockTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="raw-materials-tab" data-bs-toggle="tab" data-bs-target="#raw-materials" type="button" role="tab">
                    <i class="bi bi-grain"></i> Raw Materials
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="third-party-tab" data-bs-toggle="tab" data-bs-target="#third-party" type="button" role="tab">
                    <i class="bi bi-shop"></i> Third Party Products
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="packaging-tab" data-bs-toggle="tab" data-bs-target="#packaging" type="button" role="tab">
                    <i class="bi bi-box-seam"></i> Packaging Materials
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="finished-bags-tab" data-bs-toggle="tab" data-bs-target="#finished-bags" type="button" role="tab">
                    <i class="bi bi-boxes"></i> Product Bags
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="opened-bags-tab" data-bs-toggle="tab" data-bs-target="#opened-bags" type="button" role="tab">
                    <i class="bi bi-bag-check"></i> Opened Bags
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="stockTabContent">
            <!-- Raw Materials Tab -->
            <div class="tab-pane fade show active" id="raw-materials" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Raw Materials Stock Adjustment</h5>
                    <span class="badge bg-secondary">Branch: <?php echo htmlspecialchars($selected_branch['name']); ?></span>
                </div>
                <div id="rawMaterialsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading raw materials...</p>
                    </div>
                </div>
            </div>

            <!-- Third Party Products Tab -->
            <div class="tab-pane fade" id="third-party" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Third Party Products Stock Adjustment</h5>
                    <span class="badge bg-secondary">Branch: <?php echo htmlspecialchars($selected_branch['name']); ?></span>
                </div>
                <div id="thirdPartyContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading third party products...</p>
                    </div>
                </div>
            </div>

            <!-- Packaging Materials Tab -->
            <div class="tab-pane fade" id="packaging" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Packaging Materials Stock Adjustment</h5>
                    <span class="badge bg-secondary">Branch: <?php echo htmlspecialchars($selected_branch['name']); ?></span>
                </div>
                <div id="packagingContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading packaging materials...</p>
                    </div>
                </div>
            </div>

            <!-- Finished Product Bags Tab -->
            <div class="tab-pane fade" id="finished-bags" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Product Bags Information</h5>
                    <span class="badge bg-secondary">Branch: <?php echo htmlspecialchars($selected_branch['name']); ?></span>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <strong>Note:</strong> Finished product bags are managed through production and sales modules.
                    This view shows current sealed bags for reference only.
                </div>
                <div id="finishedBagsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading product bags...</p>
                    </div>
                </div>
            </div>

            <!-- Opened Bags Tab -->
            <div class="tab-pane fade" id="opened-bags" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>Opened Bags Weight Adjustment</h5>
                    <span class="badge bg-secondary">Branch: <?php echo htmlspecialchars($selected_branch['name']); ?></span>
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Caution:</strong> Adjusting opened bag weights affects loose sales inventory.
                    Only adjust for verified weight discrepancies.
                </div>
                <div id="openedBagsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading opened bags...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stockAdjustmentForm">
                    <input type="hidden" id="adjustmentType" name="adjustmentType">
                    <input type="hidden" id="productId" name="productId">
                    <input type="hidden" id="branchId" name="branchId" value="<?php echo $selected_branch_id; ?>">

                    <div id="productInfo" class="alert alert-info mb-3"></div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="currentStock" class="form-label">Current Stock/Weight</label>
                            <input type="number" class="form-control" id="currentStock" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="newStock" class="form-label">New Stock/Weight *</label>
                            <input type="number" class="form-control" id="newStock" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="adjustmentReason" class="form-label">Adjustment Reason *</label>
                        <textarea class="form-control" id="adjustmentReason" rows="3" required
                                placeholder="Provide detailed reason for stock adjustment (e.g., Physical count variance, Damaged stock, etc.)"></textarea>
                    </div>

                    <div class="mt-3" id="adjustmentSummary" style="display: none;">
                        <div class="alert alert-secondary">
                            <strong>Adjustment Summary:</strong>
                            <span id="adjustmentDetails"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmAdjustment">
                    <i class="bi bi-arrow-repeat"></i> Confirm Adjustment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit Trail Modal -->
<div class="modal fade" id="auditTrailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Adjustment Audit Trail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="auditTrailContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentBranchId = <?php echo $selected_branch_id; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Load initial tab content
    loadRawMaterials();

    // Tab change event listeners
    document.getElementById('raw-materials-tab').addEventListener('click', loadRawMaterials);
    document.getElementById('third-party-tab').addEventListener('click', loadThirdPartyProducts);
    document.getElementById('packaging-tab').addEventListener('click', loadPackagingMaterials);
    document.getElementById('finished-bags-tab').addEventListener('click', loadFinishedBags);
    document.getElementById('opened-bags-tab').addEventListener('click', loadOpenedBags);

    // Stock adjustment form handling
    document.getElementById('newStock').addEventListener('input', updateAdjustmentSummary);
    document.getElementById('confirmAdjustment').addEventListener('click', confirmStockAdjustment);
});

function changeBranch() {
    const branchSelect = document.getElementById('branchSelect');
    const newBranchId = branchSelect.value;
    window.location.href = `?branch_id=${newBranchId}`;
}

function loadRawMaterials() {
    fetch(`ajax/get_raw_materials_for_adjustment.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('rawMaterialsContent').innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error loading raw materials:', error);
            showAlert('Error loading raw materials', 'danger');
        });
}

function loadThirdPartyProducts() {
    fetch(`ajax/get_third_party_for_adjustment.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('thirdPartyContent').innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error loading third party products:', error);
            showAlert('Error loading third party products', 'danger');
        });
}

function loadPackagingMaterials() {
    fetch(`ajax/get_packaging_for_adjustment.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('packagingContent').innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error loading packaging materials:', error);
            showAlert('Error loading packaging materials', 'danger');
        });
}

function loadFinishedBags() {
    fetch(`ajax/get_finished_bags_for_adjustment.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('finishedBagsContent').innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error loading finished bags:', error);
            showAlert('Error loading finished bags', 'danger');
        });
}

function loadOpenedBags() {
    fetch(`ajax/get_opened_bags_for_adjustment.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('openedBagsContent').innerHTML = data.html;
        })
        .catch(error => {
            console.error('Error loading opened bags:', error);
            showAlert('Error loading opened bags', 'danger');
        });
}

function adjustStock(type, productId, productName, currentStock, unit = '') {
    document.getElementById('adjustmentType').value = type;
    document.getElementById('productId').value = productId;
    document.getElementById('branchId').value = currentBranchId;
    document.getElementById('currentStock').value = currentStock;
    document.getElementById('newStock').value = currentStock;
    document.getElementById('adjustmentReason').value = '';

    const modalTitle = document.getElementById('modalTitle');
    const productInfo = document.getElementById('productInfo');

    let typeLabel = '';
    switch(type) {
        case 'raw_material': typeLabel = 'Raw Material'; break;
        case 'third_party': typeLabel = 'Third Party Product'; break;
        case 'packaging': typeLabel = 'Packaging Material'; break;
        case 'opened_bag': typeLabel = 'Opened Bag Weight'; break;
    }

    modalTitle.textContent = `${typeLabel} Stock Adjustment`;
    productInfo.innerHTML = `
        <strong>${typeLabel}:</strong> ${productName}<br>
        <strong>Current Stock:</strong> ${currentStock} ${unit}
    `;

    document.getElementById('adjustmentSummary').style.display = 'none';

    new bootstrap.Modal(document.getElementById('stockAdjustmentModal')).show();
}

function updateAdjustmentSummary() {
    const currentStock = parseFloat(document.getElementById('currentStock').value) || 0;
    const newStock = parseFloat(document.getElementById('newStock').value) || 0;
    const adjustment = newStock - currentStock;

    if (adjustment !== 0) {
        const adjustmentType = adjustment > 0 ? 'Increase' : 'Decrease';
        const adjustmentColor = adjustment > 0 ? 'text-success' : 'text-danger';

        document.getElementById('adjustmentDetails').innerHTML = `
            <span class="${adjustmentColor}">
                ${adjustmentType} of ${Math.abs(adjustment).toFixed(2)} units
            </span>
        `;
        document.getElementById('adjustmentSummary').style.display = 'block';
    } else {
        document.getElementById('adjustmentSummary').style.display = 'none';
    }
}

function confirmStockAdjustment() {
    const form = document.getElementById('stockAdjustmentForm');
    const formData = new FormData(form);

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const confirmBtn = document.getElementById('confirmAdjustment');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adjusting...';
    confirmBtn.disabled = true;

    fetch('ajax/adjust_stock.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Stock adjusted successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal')).hide();

            // Reload current tab content
            const activeTab = document.querySelector('.nav-link.active').id;
            switch(activeTab) {
                case 'raw-materials-tab': loadRawMaterials(); break;
                case 'third-party-tab': loadThirdPartyProducts(); break;
                case 'packaging-tab': loadPackagingMaterials(); break;
                case 'opened-bags-tab': loadOpenedBags(); break;
            }

            // Reload page to update inventory summary
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Error adjusting stock', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error adjusting stock', 'danger');
    })
    .finally(() => {
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
}

function viewAuditTrail() {
    fetch(`ajax/get_audit_trail.php?branch_id=${currentBranchId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('auditTrailContent').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('auditTrailModal')).show();
        })
        .catch(error => {
            console.error('Error loading audit trail:', error);
            showAlert('Error loading audit trail', 'danger');
        });
}

function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    alertContainer.appendChild(alertDiv);

    // Auto-hide success alerts
    if (type === 'success') {
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>