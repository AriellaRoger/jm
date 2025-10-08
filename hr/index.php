<?php
// File: hr/index.php
// HR Management main interface - Administrator only

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/HRController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Only administrators can access HR management
if ($_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$hrController = new HRController();
$stats = $hrController->getHRStats();
$employees = $hrController->getEmployees();
$leaveTypes = $hrController->getLeaveTypes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management - JM Animal Feeds</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-people"></i> HR Management</h2>
                <p class="text-muted">Employee, Leave, and Payroll Management</p>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['total_employees'] ?? 0; ?></h4>
                                        <p class="card-text">Total Employees</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['pending_leaves'] ?? 0; ?></h4>
                                        <p class="card-text">Pending Leaves</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-x fs-1"></i>
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
                                        <h4 class="card-title"><?php echo $stats['monthly_payrolls'] ?? 0; ?></h4>
                                        <p class="card-text">Monthly Payrolls</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-cash-stack fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo number_format($stats['monthly_payroll_total'] ?? 0, 0); ?></h4>
                                        <p class="card-text">TZS Monthly Total</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <ul class="nav nav-tabs" id="hrTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                            <i class="bi bi-people"></i> Employees
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab">
                            <i class="bi bi-calendar-check"></i> Leave Management
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                            <i class="bi bi-cash"></i> Payroll
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="hrTabContent">
                    <!-- Employees Tab -->
                    <div class="tab-pane fade show active" id="employees" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center my-3">
                            <h6>Employee Management</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee #</th>
                                        <th>Name</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Branch</th>
                                        <th>Basic Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="employeesTableBody">
                                    <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><strong><?php echo $employee['employee_number'] ?? 'N/A'; ?></strong></td>
                                        <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['job_title'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($employee['branch_name']); ?></td>
                                        <td><?php echo number_format($employee['basic_salary'] ?? 0, 0); ?> TZS</td>
                                        <td>
                                            <?php if (($employee['employment_status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo $employee['employment_status']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editEmployee(<?php echo $employee['id']; ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="createPayroll(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name']); ?>')">
                                                <i class="bi bi-cash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Leave Management Tab -->
                    <div class="tab-pane fade" id="leaves" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center my-3">
                            <h6>Leave Requests</h6>
                            <button class="btn btn-primary btn-sm" onclick="openLeaveModal()">
                                <i class="bi bi-plus-circle"></i> New Leave Request
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="leavesTableBody">
                                    <!-- Leave requests will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Payroll Tab -->
                    <div class="tab-pane fade" id="payroll" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center my-3">
                            <h6>Payroll Records</h6>
                            <button class="btn btn-primary btn-sm" onclick="openPayrollModal()">
                                <i class="bi bi-plus-circle"></i> Generate Payroll
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Payroll #</th>
                                        <th>Employee</th>
                                        <th>Period</th>
                                        <th>Gross Salary</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="payrollTableBody">
                                    <!-- Payroll records will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Edit Modal -->
    <div class="modal fade" id="employeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="employeeForm">
                        <input type="hidden" id="employeeId">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jobTitle" class="form-label">Job Title *</label>
                                <input type="text" class="form-control" id="jobTitle" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department</label>
                                <select class="form-select" id="department">
                                    <option value="Management">Management</option>
                                    <option value="Production">Production</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Finance">Finance</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="basicSalary" class="form-label">Basic Salary (TZS) *</label>
                                <input type="number" class="form-control" id="basicSalary" required step="0.01">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="allowances" class="form-label">Allowances (TZS)</label>
                                <input type="number" class="form-control" id="allowances" step="0.01">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="contractType" class="form-label">Contract Type</label>
                            <select class="form-select" id="contractType">
                                <option value="PERMANENT">Permanent</option>
                                <option value="CONTRACT">Contract</option>
                                <option value="CASUAL">Casual</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="bankName" class="form-label">Bank Name</label>
                                <input type="text" class="form-control" id="bankName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="bankAccount" class="form-label">Bank Account</label>
                                <input type="text" class="form-control" id="bankAccount">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="emergencyContactName" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergencyContactName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="emergencyContactPhone" class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" id="emergencyContactPhone">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateEmployee()">Update Employee</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Request Modal -->
    <div class="modal fade" id="leaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Leave Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="leaveForm">
                        <div class="mb-3">
                            <label for="leaveEmployee" class="form-label">Employee *</label>
                            <select class="form-select" id="leaveEmployee" required>
                                <option value="">Select employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['full_name']); ?> (<?php echo $employee['employee_number'] ?? 'N/A'; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="leaveType" class="form-label">Leave Type *</label>
                            <select class="form-select" id="leaveType" required>
                                <option value="">Select leave type</option>
                                <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['days_per_year']; ?> days/year)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="startDate" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="startDate" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="endDate" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="endDate" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="leaveReason" class="form-label">Reason *</label>
                            <textarea class="form-control" id="leaveReason" rows="3" required placeholder="Reason for leave request..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createLeaveRequest()">Submit Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Modal -->
    <div class="modal fade" id="payrollModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="payrollForm">
                        <input type="hidden" id="payrollEmployeeId">
                        <div class="mb-3">
                            <label for="payrollEmployee" class="form-label">Employee *</label>
                            <select class="form-select" id="payrollEmployee" required>
                                <option value="">Select employee</option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" data-salary="<?php echo $employee['basic_salary'] ?? 0; ?>" data-allowances="<?php echo $employee['allowances'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($employee['full_name']); ?> (<?php echo $employee['employee_number'] ?? 'N/A'; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="payPeriodStart" class="form-label">Pay Period Start *</label>
                                <input type="date" class="form-control" id="payPeriodStart" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="payPeriodEnd" class="form-label">Pay Period End *</label>
                                <input type="date" class="form-control" id="payPeriodEnd" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="overtimeHours" class="form-label">Overtime Hours</label>
                                <input type="number" class="form-control" id="overtimeHours" step="0.5" min="0" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="deductions" class="form-label">Deductions (TZS)</label>
                                <input type="number" class="form-control" id="deductions" step="0.01" min="0" value="0">
                            </div>
                        </div>
                        <div class="bg-light p-3 rounded">
                            <h6>Salary Breakdown:</h6>
                            <p class="mb-1">Basic Salary: <span id="displayBasicSalary">0</span> TZS</p>
                            <p class="mb-1">Allowances: <span id="displayAllowances">0</span> TZS</p>
                            <p class="mb-1">Overtime: <span id="displayOvertime">0</span> TZS</p>
                            <p class="mb-1">Gross Salary: <span id="displayGross">0</span> TZS</p>
                            <p class="mb-1">Deductions: <span id="displayDeductions">0</span> TZS</p>
                            <hr>
                            <p class="mb-0"><strong>Net Salary: <span id="displayNet">0</span> TZS</strong></p>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="generatePayroll()">Generate Payroll</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load data when tabs are shown
        document.addEventListener('DOMContentLoaded', function() {
            // Load leaves when tab is shown
            document.getElementById('leaves-tab').addEventListener('shown.bs.tab', function() {
                loadLeaveRequests();
            });

            // Load payroll when tab is shown
            document.getElementById('payroll-tab').addEventListener('shown.bs.tab', function() {
                loadPayrollRecords();
            });
        });

        // Employee functions
        function editEmployee(employeeId) {
            // This would fetch employee details and populate the modal
            // For now, just show the modal
            new bootstrap.Modal(document.getElementById('employeeModal')).show();
            document.getElementById('employeeId').value = employeeId;
        }

        function updateEmployee() {
            alert('Employee update functionality - coming soon!');
        }

        // Leave functions
        function openLeaveModal() {
            new bootstrap.Modal(document.getElementById('leaveModal')).show();
        }

        function createLeaveRequest() {
            const data = {
                employee_id: document.getElementById('leaveEmployee').value,
                leave_type_id: document.getElementById('leaveType').value,
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value,
                reason: document.getElementById('leaveReason').value
            };

            if (!data.employee_id || !data.leave_type_id || !data.start_date || !data.end_date || !data.reason) {
                alert('Please fill all required fields');
                return;
            }

            fetch('ajax/create_leave_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Leave request created successfully: ' + data.request_number);
                    bootstrap.Modal.getInstance(document.getElementById('leaveModal')).hide();
                    document.getElementById('leaveForm').reset();
                    loadLeaveRequests();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function loadLeaveRequests() {
            fetch('ajax/get_leave_requests.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLeaveRequests(data.requests);
                    }
                });
        }

        function displayLeaveRequests(requests) {
            const tbody = document.getElementById('leavesTableBody');
            if (requests.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No leave requests found</td></tr>';
                return;
            }

            tbody.innerHTML = requests.map(request => `
                <tr>
                    <td><strong>${request.request_number}</strong></td>
                    <td>${request.employee_name}</td>
                    <td>${request.leave_type}</td>
                    <td>${new Date(request.start_date).toLocaleDateString()}</td>
                    <td>${new Date(request.end_date).toLocaleDateString()}</td>
                    <td>${request.days_requested}</td>
                    <td>${getStatusBadge(request.status)}</td>
                    <td>
                        ${request.status === 'PENDING' ? `
                            <button class="btn btn-sm btn-success" onclick="processLeave(${request.id}, 'approve')">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="processLeave(${request.id}, 'reject')">Reject</button>
                        ` : '-'}
                    </td>
                </tr>
            `).join('');
        }

        function processLeave(requestId, action) {
            const notes = action === 'reject' ? prompt('Rejection reason:') : '';
            if (action === 'reject' && !notes) return;

            fetch('ajax/process_leave_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: requestId, action: action, notes: notes })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Leave request ${action}d successfully`);
                    loadLeaveRequests();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        // Payroll functions
        function openPayrollModal() {
            new bootstrap.Modal(document.getElementById('payrollModal')).show();
        }

        function createPayroll(employeeId, employeeName) {
            document.getElementById('payrollEmployeeId').value = employeeId;
            document.getElementById('payrollEmployee').value = employeeId;
            updatePayrollCalculation();
            new bootstrap.Modal(document.getElementById('payrollModal')).show();
        }

        // Payroll calculation
        document.getElementById('payrollEmployee').addEventListener('change', updatePayrollCalculation);
        document.getElementById('overtimeHours').addEventListener('input', updatePayrollCalculation);
        document.getElementById('deductions').addEventListener('input', updatePayrollCalculation);

        function updatePayrollCalculation() {
            const select = document.getElementById('payrollEmployee');
            const option = select.options[select.selectedIndex];

            if (option.value) {
                const basicSalary = parseFloat(option.getAttribute('data-salary')) || 0;
                const allowances = parseFloat(option.getAttribute('data-allowances')) || 0;
                const overtimeHours = parseFloat(document.getElementById('overtimeHours').value) || 0;
                const deductions = parseFloat(document.getElementById('deductions').value) || 0;

                const overtimeRate = basicSalary / 30 / 8; // Daily rate / 8 hours
                const overtimeAmount = overtimeHours * overtimeRate;
                const grossSalary = basicSalary + allowances + overtimeAmount;
                const netSalary = grossSalary - deductions;

                document.getElementById('displayBasicSalary').textContent = basicSalary.toLocaleString();
                document.getElementById('displayAllowances').textContent = allowances.toLocaleString();
                document.getElementById('displayOvertime').textContent = overtimeAmount.toLocaleString();
                document.getElementById('displayGross').textContent = grossSalary.toLocaleString();
                document.getElementById('displayDeductions').textContent = deductions.toLocaleString();
                document.getElementById('displayNet').textContent = netSalary.toLocaleString();
            }
        }

        function generatePayroll() {
            const data = {
                employee_id: document.getElementById('payrollEmployee').value,
                pay_period_start: document.getElementById('payPeriodStart').value,
                pay_period_end: document.getElementById('payPeriodEnd').value,
                overtime_hours: parseFloat(document.getElementById('overtimeHours').value) || 0,
                deductions: parseFloat(document.getElementById('deductions').value) || 0
            };

            if (!data.employee_id || !data.pay_period_start || !data.pay_period_end) {
                alert('Please fill all required fields');
                return;
            }

            fetch('ajax/create_payroll.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payroll generated successfully: ' + data.payroll_number + '\nNet Salary: ' + data.net_salary.toLocaleString() + ' TZS');
                    bootstrap.Modal.getInstance(document.getElementById('payrollModal')).hide();
                    document.getElementById('payrollForm').reset();
                    loadPayrollRecords();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function loadPayrollRecords() {
            fetch('ajax/get_payroll_records.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPayrollRecords(data.records);
                    }
                });
        }

        function displayPayrollRecords(records) {
            const tbody = document.getElementById('payrollTableBody');
            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No payroll records found</td></tr>';
                return;
            }

            tbody.innerHTML = records.map(record => `
                <tr>
                    <td><strong>${record.payroll_number}</strong></td>
                    <td>${record.employee_name}</td>
                    <td>${new Date(record.pay_period_start).toLocaleDateString()} - ${new Date(record.pay_period_end).toLocaleDateString()}</td>
                    <td class="text-end">${parseFloat(record.gross_salary).toLocaleString()}</td>
                    <td class="text-end">${parseFloat(record.deductions).toLocaleString()}</td>
                    <td class="text-end">${parseFloat(record.net_salary).toLocaleString()}</td>
                    <td>${getStatusBadge(record.status)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewPayrollDetails(${record.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function getStatusBadge(status) {
            const badges = {
                'PENDING': '<span class="badge bg-warning">Pending</span>',
                'APPROVED': '<span class="badge bg-success">Approved</span>',
                'REJECTED': '<span class="badge bg-danger">Rejected</span>',
                'CANCELLED': '<span class="badge bg-secondary">Cancelled</span>',
                'DRAFT': '<span class="badge bg-info">Draft</span>',
                'PAID': '<span class="badge bg-success">Paid</span>'
            };
            return badges[status] || status;
        }

        function viewPayrollDetails(recordId) {
            alert('Payroll details - coming soon!');
        }
    </script>
</body>
</html>