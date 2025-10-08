<?php
// File: admin/production.php
// Production management interface for administrators and supervisors
// Formula selection, batch creation, and production workflow management

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ProductionController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('Location: ../login.php');
    exit;
}

$productionController = new ProductionController();
$activeFormulas = $productionController->getActiveFormulas();
$productionOfficers = $productionController->getProductionOfficers();
$finishedProducts = $productionController->getFinishedProducts();
$packagingMaterials = $productionController->getPackagingMaterials();

// Get production batches
$activeProduction = $productionController->getProductionBatches('IN_PROGRESS', 10);
$pausedProduction = $productionController->getProductionBatches('PAUSED', 10);
$recentCompleted = $productionController->getProductionBatches('COMPLETED', 10);

include_once __DIR__ . '/../includes/header.php';
?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Production Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" onclick="showNewProduction()">
                        <i class="bi bi-plus-circle"></i> New Production Batch
                    </button>
                </div>
            </div>

            <!-- Production Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5><?= count($activeFormulas) ?></h5>
                            <p class="mb-0">Active Formulas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5><?= count($activeProduction) ?></h5>
                            <p class="mb-0">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5><?= count($pausedProduction) ?></h5>
                            <p class="mb-0">Paused</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5><?= count($recentCompleted) ?></h5>
                            <p class="mb-0">Recently Completed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Production Tabs -->
            <ul class="nav nav-tabs" id="productionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Active Production (<?= count($activeProduction) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="paused-tab" data-bs-toggle="tab" data-bs-target="#paused" type="button" role="tab">
                        Paused (<?= count($pausedProduction) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                        Completed (<?= count($recentCompleted) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content mt-3" id="productionTabContent">
                <!-- Active Production -->
                <div class="tab-pane fade show active" id="active" role="tabpanel">
                    <?php if (empty($activeProduction)): ?>
                        <div class="alert alert-info">No active production batches</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch #</th>
                                        <th>Formula</th>
                                        <th>Production Officer</th>
                                        <th>Started</th>
                                        <th>Expected Yield</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeProduction as $batch): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($batch['batch_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($batch['formula_name']) ?></td>
                                            <td><?= htmlspecialchars($batch['production_officer_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($batch['started_at'])) ?></td>
                                            <td><?= number_format($batch['expected_yield'], 1) ?> <?= $batch['formula_yield'] > 0 ? 'KG' : '' ?></td>
                                            <td><?= intval($batch['duration_minutes']) ?> mins</td>
                                            <td>
                                                <button class="btn btn-sm btn-info me-1" onclick="viewBatchDetails(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning me-1" onclick="pauseProduction(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-pause"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="completeProduction(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-check-circle"></i> Complete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paused Production -->
                <div class="tab-pane fade" id="paused" role="tabpanel">
                    <?php if (empty($pausedProduction)): ?>
                        <div class="alert alert-info">No paused production batches</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch #</th>
                                        <th>Formula</th>
                                        <th>Production Officer</th>
                                        <th>Paused At</th>
                                        <th>Expected Yield</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pausedProduction as $batch): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($batch['batch_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($batch['formula_name']) ?></td>
                                            <td><?= htmlspecialchars($batch['production_officer_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($batch['paused_at'])) ?></td>
                                            <td><?= number_format($batch['expected_yield'], 1) ?> KG</td>
                                            <td>
                                                <button class="btn btn-sm btn-info me-1" onclick="viewBatchDetails(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success me-1" onclick="resumeProduction(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-play"></i> Resume
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="completeProduction(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-check-circle"></i> Complete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Completed Production -->
                <div class="tab-pane fade" id="completed" role="tabpanel">
                    <?php if (empty($recentCompleted)): ?>
                        <div class="alert alert-info">No recently completed batches</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Batch #</th>
                                        <th>Formula</th>
                                        <th>Production Officer</th>
                                        <th>Completed</th>
                                        <th>Actual Yield</th>
                                        <th>Wastage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentCompleted as $batch): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($batch['batch_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($batch['formula_name']) ?></td>
                                            <td><?= htmlspecialchars($batch['production_officer_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($batch['completed_at'])) ?></td>
                                            <td><?= number_format($batch['actual_yield'], 1) ?> KG</td>
                                            <td><?= number_format($batch['wastage_percentage'], 1) ?>%</td>
                                            <td>
                                                <button class="btn btn-sm btn-info me-1" onclick="viewBatchDetails(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="printBatchReport(<?= $batch['id'] ?>)">
                                                    <i class="bi bi-printer"></i> Report
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

<!-- New Production Modal -->
<div class="modal fade" id="newProductionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Production Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Formula Selection -->
                <div id="step1" class="production-step">
                    <h6>Step 1: Select Formula</h6>
                    <div class="row">
                        <?php foreach ($activeFormulas as $formula): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border formula-card" onclick="selectFormula(<?= $formula['id'] ?>)" style="cursor: pointer;">
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($formula['name']) ?></h6>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($formula['description']) ?></p>
                                        <div class="row">
                                            <div class="col-6">
                                                <small><strong>Target Yield:</strong> <?= number_format($formula['target_yield'], 1) ?> <?= $formula['yield_unit'] ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small><strong>Cost:</strong> <?= number_format($formula['total_cost'], 0) ?> TZS</small>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <small><strong>Ingredients:</strong> <?= $formula['ingredient_count'] ?></small>
                                            </div>
                                            <div class="col-6">
                                                <small><strong>Cost/KG:</strong> <?= number_format($formula['cost_per_kg'], 0) ?> TZS</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Formula Preview -->
                <div id="step2" class="production-step" style="display: none;">
                    <h6>Step 2: Formula Preview</h6>
                    <div id="formulaPreview">
                        <!-- Formula preview will be loaded here -->
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Batch Size (multiplier)</label>
                            <input type="number" class="form-control" id="batchSize" value="1" min="1" max="10" onchange="updateFormulaPreview()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Production Officer</label>
                            <select class="form-select" id="productionOfficer" required>
                                <option value="">Select Production Officer</option>
                                <?php foreach ($productionOfficers as $officer): ?>
                                    <option value="<?= $officer['id'] ?>"><?= htmlspecialchars($officer['full_name']) ?> (<?= htmlspecialchars($officer['branch_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Production Completion -->
                <div id="step3" class="production-step" style="display: none;">
                    <h6>Step 3: Complete Production</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Actual Yield (KG)</label>
                            <input type="number" class="form-control" id="actualYield" step="0.1" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Wastage Percentage</label>
                            <input type="number" class="form-control" id="wastagePercent" step="0.1" min="0" max="100" value="0">
                        </div>
                    </div>

                    <h6>Packaging Details</h6>
                    <div id="packagingContainer">
                        <div class="packaging-item card mb-2">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Finished Product</label>
                                        <select class="form-select packaging-product" required>
                                            <option value="">Select Product</option>
                                            <?php foreach ($finishedProducts as $product): ?>
                                                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Package Size</label>
                                        <select class="form-select packaging-size" required>
                                            <option value="">Select Size</option>
                                            <option value="5KG">5KG</option>
                                            <option value="10KG">10KG</option>
                                            <option value="20KG">20KG</option>
                                            <option value="25KG">25KG</option>
                                            <option value="50KG">50KG</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Bags Count</label>
                                        <input type="number" class="form-control packaging-bags" min="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Packaging Material</label>
                                        <select class="form-select packaging-material" required>
                                            <option value="">Select Material</option>
                                            <?php foreach ($packagingMaterials as $material): ?>
                                                <option value="<?= $material['id'] ?>"><?= htmlspecialchars($material['name']) ?> (<?= number_format($material['current_stock'], 0) ?> <?= $material['unit'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Actions</label><br>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="removePackaging(this)">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addPackaging()">
                        <i class="bi bi-plus"></i> Add Another Package
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-secondary" id="backBtn" onclick="previousStep()" style="display: none;">Back</button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()">Next</button>
                <button type="button" class="btn btn-success" id="startBtn" onclick="startProduction()" style="display: none;">Start Production</button>
                <button type="button" class="btn btn-success" id="completeBtn" onclick="submitCompletion()" style="display: none;">Complete Production</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Details Modal -->
<div class="modal fade" id="batchDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Batch Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="batchDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- QR Codes Display Modal -->
<div class="modal fade" id="qrCodesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Production Complete - QR Codes & Serial Numbers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="qrCodesContent">
                <!-- QR codes content loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Production List
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
let selectedFormulaId = null;
let currentBatchId = null;

// Show new production modal
function showNewProduction() {
    currentStep = 1;
    selectedFormulaId = null;
    currentBatchId = null;
    showStep(1);
    const modal = new bootstrap.Modal(document.getElementById('newProductionModal'));
    modal.show();
}

// Show specific step
function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.production-step').forEach(el => el.style.display = 'none');

    // Show current step
    document.getElementById('step' + step).style.display = 'block';

    // Update buttons
    document.getElementById('backBtn').style.display = step > 1 ? 'inline-block' : 'none';
    document.getElementById('nextBtn').style.display = step < 3 ? 'inline-block' : 'none';
    document.getElementById('startBtn').style.display = step === 2 ? 'inline-block' : 'none';
    document.getElementById('completeBtn').style.display = step === 3 ? 'inline-block' : 'none';

    currentStep = step;
}

// Select formula
function selectFormula(formulaId) {
    selectedFormulaId = formulaId;

    // Highlight selected formula
    document.querySelectorAll('.formula-card').forEach(card => {
        card.classList.remove('border-primary');
    });
    event.target.closest('.formula-card').classList.add('border-primary');

    // Load formula preview
    loadFormulaPreview(formulaId, 1);
}

// Load formula preview
function loadFormulaPreview(formulaId, batchSize) {
    fetch(`ajax/get_formula_preview.php?id=${formulaId}&batch_size=${batchSize}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('formulaPreview').innerHTML = data.html;
            }
        })
        .catch(error => console.error('Error:', error));
}

// Update formula preview when batch size changes
function updateFormulaPreview() {
    if (selectedFormulaId) {
        const batchSize = document.getElementById('batchSize').value;
        loadFormulaPreview(selectedFormulaId, batchSize);
    }
}

// Next step
function nextStep() {
    if (currentStep === 1) {
        if (!selectedFormulaId) {
            alert('Please select a formula first');
            return;
        }
        showStep(2);
    } else if (currentStep === 2) {
        showStep(3);
    }
}

// Previous step
function previousStep() {
    if (currentStep > 1) {
        showStep(currentStep - 1);
    }
}

// Start production
function startProduction() {
    if (!selectedFormulaId) {
        alert('Please select a formula');
        return;
    }

    const productionOfficer = document.getElementById('productionOfficer').value;
    if (!productionOfficer) {
        alert('Please select a production officer');
        return;
    }

    const batchSize = document.getElementById('batchSize').value;

    const data = {
        formula_id: selectedFormulaId,
        batch_size: parseFloat(batchSize),
        production_officer_id: parseInt(productionOfficer),
        supervisor_id: <?= $_SESSION['user_id'] ?>
    };

    fetch('ajax/create_batch.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentBatchId = data.batch_id;

            // Start production immediately
            return fetch('ajax/start_production.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({batch_id: currentBatchId})
            });
        } else {
            throw new Error(data.message);
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Production started successfully!');
            showStep(3);
        } else {
            alert('Error starting production: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

// Submit completion
function submitCompletion() {
    if (!currentBatchId) {
        alert('No active batch found');
        return;
    }

    const actualYield = document.getElementById('actualYield').value;
    const wastagePercent = document.getElementById('wastagePercent').value;

    if (!actualYield || actualYield <= 0) {
        alert('Please enter actual yield');
        return;
    }

    // Get packaging data
    const packagingData = [];
    document.querySelectorAll('.packaging-item').forEach(item => {
        const product = item.querySelector('.packaging-product').value;
        const size = item.querySelector('.packaging-size').value;
        const bags = item.querySelector('.packaging-bags').value;
        const material = item.querySelector('.packaging-material').value;

        if (product && size && bags && material) {
            packagingData.push({
                product_id: parseInt(product),
                package_size: size,
                bags_count: parseInt(bags),
                packaging_material_id: parseInt(material)
            });
        }
    });

    if (packagingData.length === 0) {
        alert('Please add at least one packaging configuration');
        return;
    }

    const data = {
        batch_id: currentBatchId,
        actual_yield: parseFloat(actualYield),
        wastage_percent: parseFloat(wastagePercent),
        packaging_data: packagingData
    };

    fetch('ajax/complete_production.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hide the production modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newProductionModal'));
            modal.hide();

            // Show QR codes if available
            if (data.qr_html) {
                document.getElementById('qrCodesContent').innerHTML = data.qr_html;
                const qrModal = new bootstrap.Modal(document.getElementById('qrCodesModal'));
                qrModal.show();
            } else {
                alert('Production completed successfully!');
                location.reload();
            }
        } else {
            alert('Error completing production: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error completing production');
    });
}

// Add packaging configuration
function addPackaging() {
    const container = document.getElementById('packagingContainer');
    const newItem = container.querySelector('.packaging-item').cloneNode(true);

    // Clear values
    newItem.querySelectorAll('input, select').forEach(el => el.value = '');

    container.appendChild(newItem);
}

// Remove packaging configuration
function removePackaging(button) {
    const packagingItems = document.querySelectorAll('.packaging-item');
    if (packagingItems.length > 1) {
        button.closest('.packaging-item').remove();
    } else {
        alert('At least one packaging configuration is required');
    }
}

// View batch details
function viewBatchDetails(batchId) {
    fetch(`ajax/get_batch_details.php?id=${batchId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('batchDetailsContent').innerHTML = data.html;
                const modal = new bootstrap.Modal(document.getElementById('batchDetailsModal'));
                modal.show();
            } else {
                alert('Error loading batch details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading batch details');
        });
}

// Pause production
function pauseProduction(batchId) {
    const reason = prompt('Reason for pausing production (optional):');

    fetch('ajax/pause_production.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            batch_id: batchId,
            reason: reason || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Production paused successfully');
            location.reload();
        } else {
            alert('Error pausing production: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error pausing production');
    });
}

// Resume production
function resumeProduction(batchId) {
    fetch('ajax/resume_production.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({batch_id: batchId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Production resumed successfully');
            location.reload();
        } else {
            alert('Error resuming production: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error resuming production');
    });
}

// Complete production (from active/paused list)
function completeProduction(batchId) {
    // For existing batches, show completion form
    currentBatchId = batchId;
    showStep(3);
    const modal = new bootstrap.Modal(document.getElementById('newProductionModal'));
    modal.show();
}

// Print batch report
function printBatchReport(batchId) {
    window.open(`ajax/print_batch_report.php?id=${batchId}`, '_blank');
}

// Print QR codes
function printQRCodes(batchId) {
    window.open(`ajax/print_qr_codes.php?batch_id=${batchId}`, '_blank');
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>