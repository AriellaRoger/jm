<?php
// File: expenses/index.php
// Main expense request interface for all users

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ExpenseController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];
$branchId = $_SESSION['branch_id'];

$expenseController = new ExpenseController();
$expenseTypes = $expenseController->getExpenseTypes();
$stats = $expenseController->getExpenseStats($userRole === 'Administrator' ? null : $branchId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Requests - JM Animal Feeds</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Stats Cards -->
            <div class="col-12 mb-4">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['pending']; ?></h4>
                                        <p class="card-text">Pending Requests</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock-history fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['approved']; ?></h4>
                                        <p class="card-text">Approved</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['rejected']; ?></h4>
                                        <p class="card-text">Rejected</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-x-circle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo number_format($stats['approved_amount'] ?? 0, 0); ?></h4>
                                        <p class="card-text">TZS Approved</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt"></i> Expense Requests
                        </h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
                            <i class="bi bi-plus-circle"></i> New Request
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="statusFilter" onchange="loadExpenses()">
                                    <option value="">All Status</option>
                                    <option value="PENDING">Pending</option>
                                    <option value="APPROVED">Approved</option>
                                    <option value="REJECTED">Rejected</option>
                                    <option value="PAID">Paid</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-outline-secondary" onclick="loadExpenses()">
                                    <i class="bi bi-arrow-repeat"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Expenses Table -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Expense #</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount (TZS)</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="expensesTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center">Loading expenses...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Expense Modal -->
    <div class="modal fade" id="expenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Expense Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="expenseForm">
                        <div class="mb-3">
                            <label for="expenseType" class="form-label">Expense Type *</label>
                            <select class="form-select" id="expenseType" required onchange="handleExpenseTypeChange()">
                                <option value="">Select expense type</option>
                                <?php foreach ($expenseTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" data-category="<?php echo $type['category']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Fleet Vehicle Selection (shown for fuel and fleet-related expenses) -->
                        <div class="mb-3" id="fleetVehicleGroup" style="display: none;">
                            <label for="fleetVehicle" class="form-label">Fleet Vehicle</label>
                            <select class="form-select" id="fleetVehicle">
                                <option value="">Select vehicle</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                            <small class="text-muted">Required for fuel, insurance, fines, and other fleet-related expenses</small>
                        </div>

                        <!-- Machine Selection (shown for machine-related expenses) -->
                        <div class="mb-3" id="machineGroup" style="display: none;">
                            <label for="machine" class="form-label">Machine/Equipment</label>
                            <select class="form-select" id="machine">
                                <option value="">Select machine</option>
                                <!-- Will be populated via AJAX -->
                            </select>
                            <small class="text-muted">Select the machine this expense relates to</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" rows="3" required
                                placeholder="Brief description of the expense..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (TZS) *</label>
                            <input type="number" class="form-control" id="amount" required
                                step="0.01" min="0" placeholder="0.00">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitExpenseRequest()">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load expenses on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadExpenses();
        });

        // Load expenses
        function loadExpenses() {
            const status = document.getElementById('statusFilter').value;
            const params = new URLSearchParams();
            if (status) params.append('status', status);

            fetch(`ajax/get_expenses.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayExpenses(data.expenses);
                    } else {
                        document.getElementById('expensesTableBody').innerHTML =
                            '<tr><td colspan="7" class="text-center text-danger">Error loading expenses</td></tr>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('expensesTableBody').innerHTML =
                        '<tr><td colspan="7" class="text-center text-danger">Error loading expenses</td></tr>';
                });
        }

        // Display expenses
        function displayExpenses(expenses) {
            const tbody = document.getElementById('expensesTableBody');
            const userRole = '<?php echo $userRole; ?>';

            if (expenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No expenses found</td></tr>';
                return;
            }

            tbody.innerHTML = expenses.map(expense => {
                let actionButtons = `
                    <button class="btn btn-sm btn-outline-primary" onclick="viewExpenseDetails(${expense.id})" title="View Details">
                        <i class="bi bi-eye"></i>
                    </button>
                `;

                // Add approve/reject buttons for administrators on pending expenses
                if (userRole === 'Administrator' && expense.status === 'PENDING') {
                    actionButtons += `
                        <button class="btn btn-sm btn-success ms-1" onclick="approveExpense(${expense.id}, '${expense.expense_number}')" title="Approve">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger ms-1" onclick="rejectExpense(${expense.id}, '${expense.expense_number}')" title="Reject">
                            <i class="bi bi-x"></i>
                        </button>
                    `;
                }

                return `
                    <tr>
                        <td><strong>${expense.expense_number}</strong></td>
                        <td>
                            <span class="badge bg-secondary">${expense.expense_type}</span>
                        </td>
                        <td>${expense.description}</td>
                        <td class="text-end">${parseFloat(expense.amount).toLocaleString()}</td>
                        <td>${getStatusBadge(expense.status)}</td>
                        <td>${new Date(expense.requested_at).toLocaleDateString()}</td>
                        <td>${actionButtons}</td>
                    </tr>
                `;
            }).join('');
        }

        // Get status badge
        function getStatusBadge(status) {
            const badges = {
                'PENDING': '<span class="badge bg-warning">Pending</span>',
                'APPROVED': '<span class="badge bg-success">Approved</span>',
                'REJECTED': '<span class="badge bg-danger">Rejected</span>',
                'PAID': '<span class="badge bg-primary">Paid</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        }

        // Handle expense type change to show/hide fleet and machine fields
        function handleExpenseTypeChange() {
            const expenseTypeSelect = document.getElementById('expenseType');
            const selectedOption = expenseTypeSelect.options[expenseTypeSelect.selectedIndex];
            const category = selectedOption ? selectedOption.dataset.category : '';
            const expenseTypeName = selectedOption ? selectedOption.text.toLowerCase() : '';

            const fleetVehicleGroup = document.getElementById('fleetVehicleGroup');
            const machineGroup = document.getElementById('machineGroup');

            // Hide both by default
            fleetVehicleGroup.style.display = 'none';
            machineGroup.style.display = 'none';

            // Check for MACHINE-related expenses first (more specific)
            if (expenseTypeName.includes('machine') ||
                expenseTypeName.includes('equipment') ||
                expenseTypeName.startsWith('machine ')) {
                machineGroup.style.display = 'block';
                loadMachines();
            }
            // Check for FLEET/VEHICLE-related expenses (excluding machine expenses)
            else if (expenseTypeName.includes('fleet') ||
                     expenseTypeName.includes('vehicle') ||
                     expenseTypeName.includes('fuel') ||
                     expenseTypeName.includes('fine') ||
                     (expenseTypeName.includes('insurance') && !expenseTypeName.includes('machine')) ||
                     category === 'FUEL') {
                fleetVehicleGroup.style.display = 'block';
                loadFleetVehicles();
            }
        }

        // Load fleet vehicles for selection
        function loadFleetVehicles() {
            fetch('../fleet/ajax/get_vehicles_list.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('fleetVehicle');
                    select.innerHTML = '<option value="">Select vehicle</option>';

                    if (data.success && data.vehicles) {
                        data.vehicles.forEach(vehicle => {
                            select.innerHTML += `<option value="${vehicle.id}">${vehicle.vehicle_number} - ${vehicle.make} ${vehicle.model}</option>`;
                        });
                    }
                })
                .catch(error => console.error('Error loading vehicles:', error));
        }

        // Load machines for selection
        function loadMachines() {
            fetch('../fleet/ajax/get_machines_list.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('machine');
                    select.innerHTML = '<option value="">Select machine</option>';

                    if (data.success && data.machines) {
                        data.machines.forEach(machine => {
                            select.innerHTML += `<option value="${machine.id}">${machine.machine_number} - ${machine.machine_name}</option>`;
                        });
                    }
                })
                .catch(error => console.error('Error loading machines:', error));
        }

        // Submit expense request
        function submitExpenseRequest() {
            const expenseType = document.getElementById('expenseType').value;
            const description = document.getElementById('description').value.trim();
            const amount = document.getElementById('amount').value;
            const fleetVehicle = document.getElementById('fleetVehicle').value;
            const machine = document.getElementById('machine').value;

            if (!expenseType || !description || !amount) {
                alert('Please fill in all required fields');
                return;
            }

            if (parseFloat(amount) <= 0) {
                alert('Amount must be greater than 0');
                return;
            }

            // Check if fleet vehicle is required
            const fleetVehicleGroup = document.getElementById('fleetVehicleGroup');
            if (fleetVehicleGroup.style.display !== 'none' && !fleetVehicle) {
                alert('Please select a fleet vehicle for this expense type');
                return;
            }

            const data = {
                expense_type_id: parseInt(expenseType),
                description: description,
                amount: parseFloat(amount),
                fleet_vehicle_id: fleetVehicle ? parseInt(fleetVehicle) : null,
                machine_id: machine ? parseInt(machine) : null
            };

            fetch('ajax/create_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense request submitted successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
                    document.getElementById('expenseForm').reset();
                    loadExpenses();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting the request');
            });
        }

        // View expense details
        function viewExpenseDetails(expenseId) {
            fetch(`ajax/get_expense_details.php?expense_id=${expenseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayExpenseDetails(data.expense);
                    } else {
                        alert('Error loading expense details: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load expense details');
                });
        }

        // Display expense details in modal
        function displayExpenseDetails(expense) {
            const modalHtml = `
                <div class="modal fade" id="expenseDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Expense Details - ${expense.expense_number}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">EXPENSE INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Expense Number:</strong></td>
                                                <td>${expense.expense_number}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Type:</strong></td>
                                                <td><span class="badge bg-secondary">${expense.expense_type}</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Category:</strong></td>
                                                <td>${expense.category}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Amount:</strong></td>
                                                <td><strong class="text-success">${parseFloat(expense.amount).toLocaleString()} TZS</strong></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>${getStatusBadge(expense.status)}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">REQUEST INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Requested By:</strong></td>
                                                <td>${expense.requested_by}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Branch:</strong></td>
                                                <td>${expense.branch_name}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Requested At:</strong></td>
                                                <td>${new Date(expense.requested_at).toLocaleString()}</td>
                                            </tr>
                                            ${expense.approved_at ? `
                                            <tr>
                                                <td><strong>Processed At:</strong></td>
                                                <td>${new Date(expense.approved_at).toLocaleString()}</td>
                                            </tr>
                                            ` : ''}
                                            ${expense.approved_by_name ? `
                                            <tr>
                                                <td><strong>Processed By:</strong></td>
                                                <td>${expense.approved_by_name}</td>
                                            </tr>
                                            ` : ''}
                                        </table>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="text-muted">DESCRIPTION</h6>
                                        <div class="border rounded p-3 bg-light">
                                            ${expense.description}
                                        </div>
                                    </div>
                                </div>

                                ${expense.rejection_reason ? `
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6 class="text-muted text-danger">REJECTION REASON</h6>
                                            <div class="border rounded p-3 bg-danger bg-opacity-10 border-danger">
                                                <i class="bi bi-exclamation-triangle text-danger"></i>
                                                ${expense.rejection_reason}
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('expenseDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('expenseDetailsModal'));
            modal.show();

            // Remove modal from DOM when hidden
            document.getElementById('expenseDetailsModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Approve expense
        function approveExpense(expenseId, expenseNumber) {
            if (confirm(`Are you sure you want to approve expense ${expenseNumber}?`)) {
                fetch('ajax/approve_expense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ expense_id: expenseId })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response ok:', response.ok);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text(); // Get as text first to check for parse errors
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug log
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed response:', data); // Debug log
                        if (data.success) {
                            alert('Expense approved successfully!');
                            loadExpenses();
                            // Refresh stats without full page reload
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('Error approving expense: ' + (data.error || 'Unknown error'));
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response text:', text);
                        alert('Invalid response from server. Check console for details.');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Request failed: ' + error.message + '. Check console for details.');
                });
            }
        }

        // Reject expense
        function rejectExpense(expenseId, expenseNumber) {
            const reason = prompt(`Please provide a reason for rejecting expense ${expenseNumber}:`);
            if (reason && reason.trim()) {
                fetch('ajax/reject_expense.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        expense_id: expenseId,
                        rejection_reason: reason.trim()
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text(); // Get as text first to check for parse errors
                })
                .then(text => {
                    console.log('Raw response:', text); // Debug log
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed response:', data); // Debug log
                        if (data.success) {
                            alert('Expense rejected successfully!');
                            loadExpenses();
                            // Refresh stats without full page reload
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            alert('Error rejecting expense: ' + (data.error || 'Unknown error'));
                        }
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response text:', text);
                        alert('Invalid response from server');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Request failed: ' + error.message + '. Check console for details.');
                });
            } else if (reason !== null) {
                alert('Please provide a rejection reason');
            }
        }
    </script>
</body>
</html>