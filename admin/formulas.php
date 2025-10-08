<?php
// File: admin/formulas.php
// Formula management interface for JM Animal Feeds ERP System
// Administrator and Supervisor access only for formula CRUD operations

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/FormulaController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Check role access - Administrator and Supervisor only
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$formulaController = new FormulaController();
$formulas = $formulaController->getAllFormulas();
$rawMaterials = $formulaController->getAvailableRawMaterials();

$pageTitle = "Formula Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="bi bi-calculator"></i> Formula Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" onclick="showNewFormulaModal()">
            <i class="bi bi-plus-circle"></i> New Formula
        </button>
        <button type="button" class="btn btn-outline-secondary ms-2" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<!-- Alert container -->
<div id="alertContainer"></div>

<!-- Formula Statistics -->
<div class="row mb-4">
    <?php
    $activeCount = count(array_filter($formulas, fn($f) => $f['status'] === 'Active'));
    $inactiveCount = count(array_filter($formulas, fn($f) => $f['status'] === 'Inactive'));
    $totalIngredients = array_sum(array_column($formulas, 'ingredient_count'));
    ?>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stats-number"><?php echo $activeCount; ?></div>
            <div class="stats-label">Active Formulas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon secondary">
                <i class="bi bi-pause-circle"></i>
            </div>
            <div class="stats-number"><?php echo $inactiveCount; ?></div>
            <div class="stats-label">Inactive Formulas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon primary">
                <i class="bi bi-list-ul"></i>
            </div>
            <div class="stats-number"><?php echo $totalIngredients; ?></div>
            <div class="stats-label">Total Ingredients</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <div class="stats-icon info">
                <i class="bi bi-bag"></i>
            </div>
            <div class="stats-number"><?php echo count($rawMaterials); ?></div>
            <div class="stats-label">Available Materials</div>
        </div>
    </div>
</div>

<!-- Formulas List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-list"></i> Production Formulas
            <span class="badge bg-secondary ms-2"><?php echo count($formulas); ?> total</span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($formulas)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calculator display-4 text-muted"></i>
                <p class="text-muted mt-3">No formulas found</p>
                <button class="btn btn-primary" onclick="showNewFormulaModal()">
                    <i class="bi bi-plus-circle"></i> Create Your First Formula
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Formula Name</th>
                            <th>Target Yield</th>
                            <th>Ingredients</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formulas as $formula): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($formula['name']); ?></strong>
                                    <?php if (!empty($formula['description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($formula['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format($formula['target_yield'], 1); ?> <?php echo htmlspecialchars($formula['yield_unit']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $formula['ingredient_count']; ?> ingredients</span>
                                </td>
                                <td>
                                    <?php
                                    $statusColor = $formula['status'] === 'Active' ? 'success' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $statusColor; ?>"><?php echo $formula['status']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($formula['created_by_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($formula['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewFormula(<?php echo $formula['id']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="editFormula(<?php echo $formula['id']; ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-outline-info" onclick="checkAvailability(<?php echo $formula['id']; ?>)">
                                            <i class="bi bi-check2-square"></i> Check Stock
                                        </button>
                                        <?php if ($_SESSION['user_role'] === 'Administrator'): ?>
                                            <button class="btn btn-outline-danger" onclick="deleteFormula(<?php echo $formula['id']; ?>, '<?php echo addslashes($formula['name']); ?>')">
                                                <i class="bi bi-trash"></i> Delete
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

<!-- New/Edit Formula Modal -->
<div class="modal fade" id="formulaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="formulaModalTitle">New Formula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formulaForm">
                    <input type="hidden" id="formulaId" value="">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="formulaName" class="form-label">Formula Name *</label>
                            <input type="text" class="form-control" id="formulaName" required>
                        </div>
                        <div class="col-md-3">
                            <label for="targetYield" class="form-label">Target Yield *</label>
                            <input type="number" class="form-control" id="targetYield" step="0.1" min="0.1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="yieldUnit" class="form-label">Yield Unit</label>
                            <select class="form-select" id="yieldUnit">
                                <option value="KG">KG</option>
                                <option value="Liters">Liters</option>
                                <option value="Tonnes">Tonnes</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="formulaDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="formulaDescription" rows="2" placeholder="Optional description..."></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6>Formula Ingredients</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addIngredient()">
                                <i class="bi bi-plus"></i> Add Ingredient
                            </button>
                        </div>
                        <div id="ingredientsList">
                            <p class="text-muted">No ingredients added yet</p>
                        </div>
                    </div>

                    <div class="mb-3" id="formulaSummary" style="display: none;">
                        <h6>Formula Summary</h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Total Input:</strong>
                                        <div id="totalInput">0 KG</div>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Expected Yield:</strong>
                                        <div id="expectedYield">0 KG</div>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Estimated Cost:</strong>
                                        <div id="estimatedCost">0 TZS</div>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Cost per KG:</strong>
                                        <div id="costPerKg">0 TZS</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFormula">
                    <i class="bi bi-save"></i> Save Formula
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Formula Modal -->
<div class="modal fade" id="viewFormulaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Formula Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="formulaDetailsContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<!-- Stock Availability Modal -->
<div class="modal fade" id="stockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Stock Availability Check</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="stockCheckContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
let selectedIngredients = [];
let editingFormulaId = null;

// Raw materials data for dropdowns
const rawMaterials = <?php echo json_encode($rawMaterials); ?>;

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('saveFormula').addEventListener('click', saveFormula);
});

function showNewFormulaModal() {
    editingFormulaId = null;
    document.getElementById('formulaModalTitle').textContent = 'New Formula';
    document.getElementById('formulaForm').reset();
    document.getElementById('formulaId').value = '';
    selectedIngredients = [];
    updateIngredientsDisplay();
    updateFormulaSummary();
    new bootstrap.Modal(document.getElementById('formulaModal')).show();
}

function addIngredient() {
    if (rawMaterials.length === 0) {
        showAlert('No raw materials available', 'warning');
        return;
    }

    const ingredient = {
        raw_material_id: '',
        quantity: '',
        unit: 'KG'
    };

    selectedIngredients.push(ingredient);
    updateIngredientsDisplay();
}

function removeIngredient(index) {
    selectedIngredients.splice(index, 1);
    updateIngredientsDisplay();
    updateFormulaSummary();
}

function updateIngredientsDisplay() {
    const container = document.getElementById('ingredientsList');

    if (selectedIngredients.length === 0) {
        container.innerHTML = '<p class="text-muted">No ingredients added yet</p>';
        document.getElementById('formulaSummary').style.display = 'none';
        return;
    }

    let html = '';
    selectedIngredients.forEach((ingredient, index) => {
        html += `
            <div class="row mb-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Raw Material</label>
                    <select class="form-select form-select-sm" onchange="updateIngredient(${index}, 'raw_material_id', this.value)">
                        <option value="">Select material...</option>`;

        rawMaterials.forEach(material => {
            const selected = material.id == ingredient.raw_material_id ? 'selected' : '';
            html += `<option value="${material.id}" ${selected}>${material.name} (${material.current_stock} ${material.unit})</option>`;
        });

        html += `
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Quantity</label>
                    <input type="number" class="form-select form-select-sm" step="0.1" min="0.1" value="${ingredient.quantity}"
                           onchange="updateIngredient(${index}, 'quantity', this.value)" placeholder="0.0">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Unit</label>
                    <select class="form-select form-select-sm" onchange="updateIngredient(${index}, 'unit', this.value)">
                        <option value="KG" ${ingredient.unit === 'KG' ? 'selected' : ''}>KG</option>
                        <option value="Liters" ${ingredient.unit === 'Liters' ? 'selected' : ''}>Liters</option>
                        <option value="Tonnes" ${ingredient.unit === 'Tonnes' ? 'selected' : ''}>Tonnes</option>
                        <option value="Pieces" ${ingredient.unit === 'Pieces' ? 'selected' : ''}>Pieces</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeIngredient(${index})">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
            </div>`;
    });

    container.innerHTML = html;
    document.getElementById('formulaSummary').style.display = 'block';
    updateFormulaSummary();
}

function updateIngredient(index, field, value) {
    selectedIngredients[index][field] = value;
    updateFormulaSummary();
}

function updateFormulaSummary() {
    let totalInput = 0;
    let totalCost = 0;

    selectedIngredients.forEach(ingredient => {
        if (ingredient.quantity && ingredient.raw_material_id) {
            const material = rawMaterials.find(m => m.id == ingredient.raw_material_id);
            if (material) {
                totalInput += parseFloat(ingredient.quantity || 0);
                totalCost += parseFloat(ingredient.quantity || 0) * parseFloat(material.cost_price || 0);
            }
        }
    });

    const targetYield = parseFloat(document.getElementById('targetYield').value || 0);
    const costPerKg = targetYield > 0 ? totalCost / targetYield : 0;

    document.getElementById('totalInput').textContent = totalInput.toFixed(1) + ' KG';
    document.getElementById('expectedYield').textContent = targetYield.toFixed(1) + ' ' + document.getElementById('yieldUnit').value;
    document.getElementById('estimatedCost').textContent = totalCost.toLocaleString() + ' TZS';
    document.getElementById('costPerKg').textContent = costPerKg.toLocaleString() + ' TZS';
}

// Event listeners for target yield changes
document.getElementById('targetYield').addEventListener('input', updateFormulaSummary);
document.getElementById('yieldUnit').addEventListener('change', updateFormulaSummary);

function saveFormula() {
    if (!document.getElementById('formulaForm').checkValidity()) {
        document.getElementById('formulaForm').reportValidity();
        return;
    }

    if (selectedIngredients.length === 0) {
        showAlert('Please add at least one ingredient', 'warning');
        return;
    }

    // Validate all ingredients have material and quantity
    for (let i = 0; i < selectedIngredients.length; i++) {
        if (!selectedIngredients[i].raw_material_id || !selectedIngredients[i].quantity) {
            showAlert(`Please complete ingredient ${i + 1}`, 'warning');
            return;
        }
    }

    const data = {
        name: document.getElementById('formulaName').value,
        description: document.getElementById('formulaDescription').value,
        target_yield: document.getElementById('targetYield').value,
        yield_unit: document.getElementById('yieldUnit').value,
        ingredients: selectedIngredients,
        created_by: <?php echo $_SESSION['user_id']; ?>
    };

    const url = editingFormulaId ? `ajax/update_formula.php?id=${editingFormulaId}` : 'ajax/create_formula.php';
    const method = 'POST';

    const saveBtn = document.getElementById('saveFormula');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

    fetch(url, {
        method: method,
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`Formula ${editingFormulaId ? 'updated' : 'created'} successfully`, 'success');
            bootstrap.Modal.getInstance(document.getElementById('formulaModal')).hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Error saving formula', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error saving formula', 'danger');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
}

function viewFormula(formulaId) {
    const modal = new bootstrap.Modal(document.getElementById('viewFormulaModal'));
    const content = document.getElementById('formulaDetailsContent');

    content.innerHTML = '<div class="text-center py-3"><div class="spinner-border"></div></div>';
    modal.show();

    fetch(`ajax/get_formula.php?id=${formulaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading formula details</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading formula details</div>';
        });
}

function editFormula(formulaId) {
    fetch(`ajax/get_formula.php?id=${formulaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.formula) {
                editingFormulaId = formulaId;
                const formula = data.formula;

                document.getElementById('formulaModalTitle').textContent = 'Edit Formula';
                document.getElementById('formulaId').value = formula.id;
                document.getElementById('formulaName').value = formula.name;
                document.getElementById('formulaDescription').value = formula.description || '';
                document.getElementById('targetYield').value = formula.target_yield;
                document.getElementById('yieldUnit').value = formula.yield_unit;

                selectedIngredients = formula.ingredients.map(ing => ({
                    raw_material_id: ing.raw_material_id,
                    quantity: ing.quantity,
                    unit: ing.unit
                }));

                updateIngredientsDisplay();
                new bootstrap.Modal(document.getElementById('formulaModal')).show();
            } else {
                showAlert('Error loading formula for editing', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Error loading formula for editing', 'danger');
        });
}

function checkAvailability(formulaId) {
    const modal = new bootstrap.Modal(document.getElementById('stockModal'));
    const content = document.getElementById('stockCheckContent');

    content.innerHTML = '<div class="text-center py-3"><div class="spinner-border"></div></div>';
    modal.show();

    fetch(`ajax/check_availability.php?id=${formulaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error checking availability</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="alert alert-danger">Error checking availability</div>';
        });
}

function deleteFormula(formulaId, formulaName) {
    if (!confirm(`Are you sure you want to delete the formula "${formulaName}"? This action cannot be undone.`)) {
        return;
    }

    fetch(`ajax/delete_formula.php?id=${formulaId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Formula deleted successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(data.message || 'Error deleting formula', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error deleting formula', 'danger');
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

    if (type === 'success') {
        setTimeout(() => alertDiv.remove(), 3000);
    }
}
</script>

<style>
.stats-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}
.stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 24px;
    color: white;
}
.stats-icon.success { background-color: #198754; }
.stats-icon.secondary { background-color: #6c757d; }
.stats-icon.primary { background-color: #0d6efd; }
.stats-icon.info { background-color: #0dcaf0; }
.stats-number {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}
.stats-label {
    color: #666;
    font-size: 14px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>