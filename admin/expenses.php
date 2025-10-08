<?php
// File: admin/expenses.php
// Admin expense approval and management interface

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ExpenseController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Only administrators can access this page
if ($_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$expenseController = new ExpenseController();
$stats = $expenseController->getExpenseStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management - JM Animal Feeds</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Page Header -->
            <div class="col-12 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="bi bi-receipt-cutoff"></i> Expense Management</h2>
                    <div>
                        <button class="btn btn-outline-primary" onclick="loadExpenses()">
                            <i class="bi bi-arrow-repeat"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="col-12 mb-4">
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <div class="card bg-warning text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-clock-history fs-1"></i>
                                <h4 class="mt-2"><?php echo $stats['pending']; ?></h4>
                                <p class="mb-0">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-check-circle fs-1"></i>
                                <h4 class="mt-2"><?php echo $stats['approved']; ?></h4>
                                <p class="mb-0">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="card bg-danger text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-x-circle fs-1"></i>
                                <h4 class="mt-2"><?php echo $stats['rejected']; ?></h4>
                                <p class="mb-0">Rejected</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="card bg-primary text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-cash-stack fs-1"></i>
                                <h4 class="mt-2"><?php echo $stats['paid']; ?></h4>
                                <p class="mb-0">Paid</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="card bg-info text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-currency-dollar fs-1"></i>
                                <h6 class="mt-2"><?php echo number_format($stats['approved_amount'] ?? 0, 0); ?></h6>
                                <p class="mb-0">TZS Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="card bg-dark text-white h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-cash-coin fs-1"></i>
                                <h6 class="mt-2"><?php echo number_format($stats['paid_amount'] ?? 0, 0); ?></h6>
                                <p class="mb-0">TZS Paid</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="expensesTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                                    <i class="bi bi-clock-history"></i> Pending Approval
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                                    <i class="bi bi-list-ul"></i> All Expenses
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="expensesTabContent">
                            <!-- Pending Expenses -->
                            <div class="tab-pane fade show active" id="pending" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Expense #</th>
                                                <th>Employee</th>
                                                <th>Branch</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Amount (TZS)</th>
                                                <th>Requested</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pendingExpensesBody">
                                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- All Expenses -->
                            <div class="tab-pane fade" id="all" role="tabpanel">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <select class="form-select" id="statusFilter">
                                            <option value="">All Status</option>
                                            <option value="PENDING">Pending</option>
                                            <option value="APPROVED">Approved</option>
                                            <option value="REJECTED">Rejected</option>
                                            <option value="PAID">Paid</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-outline-secondary" onclick="loadAllExpenses()">
                                            <i class="bi bi-funnel"></i> Filter
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Expense #</th>
                                                <th>Employee</th>
                                                <th>Branch</th>
                                                <th>Type</th>
                                                <th>Amount (TZS)</th>
                                                <th>Status</th>
                                                <th>Requested</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="allExpensesBody">
                                            <tr><td colspan="8" class="text-center">Loading...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Details Modal -->
    <div class="modal fade" id="expenseDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Expense Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="expenseDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer" id="expenseDetailsActions">
                    <!-- Actions will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rejectExpenseId">
                    <div class="mb-3">
                        <label for="rejectionReason" class="form-label">Rejection Reason *</label>
                        <textarea class="form-control" id="rejectionReason" rows="3" required
                            placeholder="Please provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmRejection()">Reject Expense</button>
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

        // Load pending expenses
        function loadExpenses() {
            fetch('ajax/get_all_expenses.php?status=PENDING')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPendingExpenses(data.expenses);
                    }
                });

            loadAllExpenses();
        }

        // Load all expenses
        function loadAllExpenses() {
            const status = document.getElementById('statusFilter').value;
            const params = new URLSearchParams();
            if (status) params.append('status', status);

            fetch(`ajax/get_all_expenses.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAllExpenses(data.expenses);
                    }
                });
        }

        // Display pending expenses
        function displayPendingExpenses(expenses) {
            const tbody = document.getElementById('pendingExpensesBody');

            if (expenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No pending expenses</td></tr>';
                return;
            }

            tbody.innerHTML = expenses.map(expense => `
                <tr>
                    <td><strong>${expense.expense_number}</strong></td>
                    <td>${expense.requested_by}</td>
                    <td>${expense.branch_name}</td>
                    <td><span class="badge bg-secondary">${expense.expense_type}</span></td>
                    <td class="text-truncate" style="max-width: 200px;">${expense.description}</td>
                    <td class="text-end"><strong>${parseFloat(expense.amount).toLocaleString()}</strong></td>
                    <td>${new Date(expense.requested_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="viewExpenseDetails(${expense.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-success me-1" onclick="approveExpense(${expense.id})">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="showRejectModal(${expense.id})">
                            <i class="bi bi-x"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Display all expenses
        function displayAllExpenses(expenses) {
            const tbody = document.getElementById('allExpensesBody');

            if (expenses.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No expenses found</td></tr>';
                return;
            }

            tbody.innerHTML = expenses.map(expense => `
                <tr>
                    <td><strong>${expense.expense_number}</strong></td>
                    <td>${expense.requested_by}</td>
                    <td>${expense.branch_name}</td>
                    <td><span class="badge bg-secondary">${expense.expense_type}</span></td>
                    <td class="text-end">${parseFloat(expense.amount).toLocaleString()}</td>
                    <td>${getStatusBadge(expense.status)}</td>
                    <td>${new Date(expense.requested_at).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewExpenseDetails(${expense.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
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

        // Approve expense
        function approveExpense(expenseId) {
            if (!confirm('Are you sure you want to approve this expense?')) return;

            fetch('ajax/approve_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ expense_id: expenseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense approved successfully!');
                    loadExpenses();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        // Show reject modal
        function showRejectModal(expenseId) {
            document.getElementById('rejectExpenseId').value = expenseId;
            document.getElementById('rejectionReason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectionModal'));
            modal.show();
        }

        // Confirm rejection
        function confirmRejection() {
            const expenseId = document.getElementById('rejectExpenseId').value;
            const reason = document.getElementById('rejectionReason').value.trim();

            if (!reason) {
                alert('Please provide a rejection reason');
                return;
            }

            fetch('ajax/reject_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    expense_id: parseInt(expenseId),
                    rejection_reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense rejected successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
                    loadExpenses();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
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
            const content = document.getElementById('expenseDetailsContent');
            const actions = document.getElementById('expenseDetailsActions');

            content.innerHTML = `
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
                        <h6 class="text-muted">REQUESTER INFORMATION</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td>${expense.requested_by}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>${expense.requester_email || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td>${expense.requester_phone || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Branch:</strong></td>
                                <td>${expense.branch_name}</td>
                            </tr>
                            <tr>
                                <td><strong>Requested:</strong></td>
                                <td>${new Date(expense.requested_at).toLocaleString()}</td>
                            </tr>
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

                ${expense.status !== 'PENDING' ? `
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-muted">APPROVAL INFORMATION</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Processed By:</strong></td>
                                    <td>${expense.approved_by_name || 'N/A'}</td>
                                </tr>
                                <tr>
                                    <td><strong>Processed At:</strong></td>
                                    <td>${expense.approved_at ? new Date(expense.approved_at).toLocaleString() : 'N/A'}</td>
                                </tr>
                                ${expense.rejection_reason ? `
                                <tr>
                                    <td><strong>Rejection Reason:</strong></td>
                                    <td class="text-danger">${expense.rejection_reason}</td>
                                </tr>
                                ` : ''}
                            </table>
                        </div>
                    </div>
                ` : ''}
            `;

            // Set action buttons based on status
            if (expense.status === 'PENDING') {
                actions.innerHTML = `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success me-2" onclick="approveExpenseFromModal(${expense.id})">
                        <i class="bi bi-check-circle"></i> Approve
                    </button>
                    <button type="button" class="btn btn-danger" onclick="showRejectModalFromDetails(${expense.id})">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                `;
            } else {
                actions.innerHTML = `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                `;
            }

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('expenseDetailsModal'));
            modal.show();
        }

        // Approve expense from modal
        function approveExpenseFromModal(expenseId) {
            if (!confirm('Are you sure you want to approve this expense?')) return;

            fetch('ajax/approve_expense.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ expense_id: expenseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Expense approved successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('expenseDetailsModal')).hide();
                    loadExpenses();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        // Show reject modal from details view
        function showRejectModalFromDetails(expenseId) {
            bootstrap.Modal.getInstance(document.getElementById('expenseDetailsModal')).hide();
            setTimeout(() => {
                showRejectModal(expenseId);
            }, 300);
        }
    </script>
</body>
</html>