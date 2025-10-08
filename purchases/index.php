<?php
// File: purchases/index.php
// Main purchases interface for authorized users

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/PurchaseController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Only supervisors, administrators, and branch operators can access
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$branchId = $_SESSION['branch_id'];

$purchaseController = new PurchaseController();
$suppliers = $purchaseController->getSuppliers();

// Get branch stats
$stats = $purchaseController->getPurchaseStats($userRole === 'Administrator' ? null : $branchId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases & Suppliers - JM Animal Feeds</title>
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
                    <h2><i class="bi bi-cart-plus"></i> Purchases & Suppliers</h2>
                    <div>
                        <div class="dropdown">
                            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-plus-circle"></i> New
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#supplierModal">
                                    <i class="bi bi-building"></i> New Supplier
                                </a></li>
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                                    <i class="bi bi-cart-plus"></i> New Purchase
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="col-12 mb-4">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['total_purchases']; ?></h4>
                                        <p class="card-text">Total Purchases</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-cart fs-1"></i>
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
                                        <h4 class="card-title"><?php echo number_format($stats['total_value'] ?? 0, 0); ?></h4>
                                        <p class="card-text">TZS Total Value</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar fs-1"></i>
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
                                        <h4 class="card-title"><?php echo $stats['pending_payments']; ?></h4>
                                        <p class="card-text">Pending Payments</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-clock fs-1"></i>
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
                                        <h4 class="card-title"><?php echo number_format($stats['total_due'] ?? 0, 0); ?></h4>
                                        <p class="card-text">TZS Amount Due</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle fs-1"></i>
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
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="mainTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                                    <i class="bi bi-cart"></i> Purchases
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#suppliers" type="button" role="tab">
                                    <i class="bi bi-building"></i> Suppliers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                                    <i class="bi bi-credit-card"></i> Payments
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="mainTabContent">
                            <!-- Purchases Tab -->
                            <div class="tab-pane fade show active" id="purchases" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Purchase #</th>
                                                <th>Supplier</th>
                                                <th>Branch</th>
                                                <th>Date</th>
                                                <th>Amount (TZS)</th>
                                                <th>Payment</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="purchasesTableBody">
                                            <tr><td colspan="8" class="text-center">Loading purchases...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Suppliers Tab -->
                            <div class="tab-pane fade" id="suppliers" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Name</th>
                                                <th>Contact</th>
                                                <th>Phone</th>
                                                <th>Payment Terms</th>
                                                <th>Credit Limit</th>
                                                <th>Balance</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suppliers as $supplier): ?>
                                            <tr>
                                                <td><strong><?php echo $supplier['supplier_code']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['payment_terms']); ?></td>
                                                <td class="text-end"><?php echo number_format($supplier['credit_limit'], 0); ?></td>
                                                <td class="text-end <?php echo $supplier['current_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo number_format($supplier['current_balance'], 0); ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewSupplierDetails(<?php echo $supplier['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <?php if ($supplier['current_balance'] > 0): ?>
                                                    <button class="btn btn-sm btn-success ms-1" onclick="openPaymentModal(<?php echo $supplier['id']; ?>)">
                                                        <i class="bi bi-credit-card"></i> Pay
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Payments Tab -->
                            <div class="tab-pane fade" id="payments" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Supplier Payments</h6>
                                    <button class="btn btn-primary btn-sm" onclick="openPaymentModal()">
                                        <i class="bi bi-plus-circle"></i> Record Payment
                                    </button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Payment #</th>
                                                <th>Supplier</th>
                                                <th>Amount (TZS)</th>
                                                <th>Method</th>
                                                <th>Reference</th>
                                                <th>Date</th>
                                                <th>Paid By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="paymentsTableBody">
                                            <tr><td colspan="7" class="text-center">Loading payments...</td></tr>
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

    <!-- New Supplier Modal -->
    <div class="modal fade" id="supplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="supplierForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="supplierName" class="form-label">Company Name *</label>
                                <input type="text" class="form-control" id="supplierName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contactPerson" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contactPerson">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplierPhone" class="form-label">Phone *</label>
                                <input type="text" class="form-control" id="supplierPhone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="supplierEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="supplierEmail">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="supplierAddress" class="form-label">Address</label>
                                <textarea class="form-control" id="supplierAddress" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="paymentTerms" class="form-label">Payment Terms</label>
                                <select class="form-select" id="paymentTerms">
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30" selected>Net 30</option>
                                    <option value="Net 45">Net 45</option>
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="creditLimit" class="form-label">Credit Limit (TZS)</label>
                                <input type="number" class="form-control" id="creditLimit" value="0" step="0.01">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createSupplier()">Create Supplier</button>
                </div>
            </div>
        </div>
    </div>

    <!-- New Purchase Modal -->
    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="purchaseForm">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="purchaseSupplier" class="form-label">Supplier *</label>
                                <select class="form-select" id="purchaseSupplier" required>
                                    <option value="">Select supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($userRole === 'Administrator'): ?>
                            <div class="col-md-4">
                                <label for="purchaseBranch" class="form-label">Branch *</label>
                                <select class="form-select" id="purchaseBranch" required onchange="loadProducts()">
                                    <option value="">Select branch</option>
                                    <option value="1">Headquarters</option>
                                    <option value="2">Arusha Branch</option>
                                    <option value="3">Mwanza Branch</option>
                                    <option value="4">Dodoma Branch</option>
                                    <option value="5">Mbeya Branch</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <input type="hidden" id="purchaseBranch" value="<?php echo $branchId; ?>">
                            <?php endif; ?>
                            <div class="col-md-4">
                                <label for="purchaseDate" class="form-label">Purchase Date *</label>
                                <input type="date" class="form-control" id="purchaseDate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="paymentMethod" class="form-label">Payment Method *</label>
                                <select class="form-select" id="paymentMethod" required>
                                    <option value="CASH">Cash</option>
                                    <option value="CREDIT">Credit</option>
                                    <option value="BANK_TRANSFER">Bank Transfer</option>
                                    <option value="MOBILE_MONEY">Mobile Money</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="purchaseNotes" class="form-label">Notes</label>
                                <input type="text" class="form-control" id="purchaseNotes" placeholder="Optional notes...">
                            </div>
                        </div>

                        <hr>
                        <h6>Purchase Items</h6>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="productSelect" class="form-label">Product</label>
                                <select class="form-select" id="productSelect">
                                    <option value="">Select product</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="itemQuantity" class="form-label">Quantity</label>
                                <input type="number" class="form-control" id="itemQuantity" step="0.01" min="0">
                            </div>
                            <div class="col-md-2">
                                <label for="itemUnit" class="form-label">Unit</label>
                                <input type="text" class="form-control" id="itemUnit" readonly>
                            </div>
                            <div class="col-md-2">
                                <label for="itemCost" class="form-label">Unit Cost</label>
                                <input type="number" class="form-control" id="itemCost" step="0.01" min="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-success w-100" onclick="addPurchaseItem()">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Qty</th>
                                        <th>Unit</th>
                                        <th>Cost</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="purchaseItemsBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No items added</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Grand Total:</th>
                                        <th id="grandTotal">0.00 TZS</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createPurchase()">Create Purchase</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Supplier Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Supplier Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <div class="mb-3">
                            <label for="paymentSupplier" class="form-label">Supplier *</label>
                            <select class="form-select" id="paymentSupplier" required onchange="updatePaymentBalance()">
                                <option value="">Select supplier with outstanding balance</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="currentBalance" class="form-label">Current Outstanding Balance</label>
                            <input type="text" class="form-control" id="currentBalance" readonly>
                        </div>
                        <div class="mb-3" id="purchaseSelectionGroup" style="display: none;">
                            <label for="paymentPurchase" class="form-label">Select Purchase to Pay</label>
                            <select class="form-select" id="paymentPurchase" onchange="updatePurchasePayment()">
                                <option value="">General payment (not linked to specific purchase)</option>
                            </select>
                            <small class="text-muted">Optional: Link payment to a specific purchase</small>
                        </div>
                        <div class="mb-3">
                            <label for="paymentAmount" class="form-label">Payment Amount (TZS) *</label>
                            <input type="number" class="form-control" id="paymentAmount" required step="0.01" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="paymentMethodSelect" class="form-label">Payment Method *</label>
                            <select class="form-select" id="paymentMethodSelect" required>
                                <option value="CASH">Cash</option>
                                <option value="BANK_TRANSFER">Bank Transfer</option>
                                <option value="MOBILE_MONEY">Mobile Money</option>
                                <option value="CHECK">Check</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="paymentReference" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="paymentReference" placeholder="Transaction/Check reference">
                        </div>
                        <div class="mb-3">
                            <label for="paymentDateInput" class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" id="paymentDateInput" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="paymentNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="paymentNotes" rows="2" placeholder="Optional payment notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="recordPayment()">Record Payment</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let purchaseItems = [];
        let products = [];

        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPurchases();
            <?php if ($userRole !== 'Administrator'): ?>
            loadProducts();
            <?php endif; ?>

            // Load payments when Payments tab is shown
            const paymentsTab = document.querySelector('button[data-bs-target="#payments"]');
            if (paymentsTab) {
                paymentsTab.addEventListener('shown.bs.tab', function() {
                    loadPayments();
                });
            }
        });

        // Load products for purchase
        function loadProducts() {
            const branchId = document.getElementById('purchaseBranch').value;
            if (!branchId) return;

            fetch(`ajax/get_products.php?branch_id=${branchId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        products = data.products;
                        const select = document.getElementById('productSelect');
                        select.innerHTML = '<option value="">Select product</option>';
                        products.forEach(product => {
                            select.innerHTML += `<option value="${product.id}" data-type="${product.type}" data-unit="${product.unit}" data-cost="${product.cost_price}">${product.name}</option>`;
                        });
                    }
                });
        }

        // Product selection change
        document.getElementById('productSelect').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                document.getElementById('itemUnit').value = option.getAttribute('data-unit');
                document.getElementById('itemCost').value = option.getAttribute('data-cost');
            } else {
                document.getElementById('itemUnit').value = '';
                document.getElementById('itemCost').value = '';
            }
        });

        // Add purchase item
        function addPurchaseItem() {
            const productSelect = document.getElementById('productSelect');
            const quantity = parseFloat(document.getElementById('itemQuantity').value);
            const cost = parseFloat(document.getElementById('itemCost').value);

            if (!productSelect.value || !quantity || !cost) {
                alert('Please fill all item fields');
                return;
            }

            const option = productSelect.options[productSelect.selectedIndex];
            const item = {
                product_id: option.value,
                product_type: option.getAttribute('data-type'),
                product_name: option.text,
                quantity: quantity,
                unit: option.getAttribute('data-unit'),
                unit_cost: cost,
                total_cost: quantity * cost
            };

            purchaseItems.push(item);
            updatePurchaseItemsTable();

            // Reset form
            document.getElementById('productSelect').value = '';
            document.getElementById('itemQuantity').value = '';
            document.getElementById('itemUnit').value = '';
            document.getElementById('itemCost').value = '';
        }

        // Update purchase items table
        function updatePurchaseItemsTable() {
            const tbody = document.getElementById('purchaseItemsBody');
            let grandTotal = 0;

            if (purchaseItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No items added</td></tr>';
            } else {
                tbody.innerHTML = purchaseItems.map((item, index) => {
                    grandTotal += item.total_cost;
                    return `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.quantity}</td>
                            <td>${item.unit}</td>
                            <td>${item.unit_cost.toLocaleString()}</td>
                            <td>${item.total_cost.toLocaleString()}</td>
                            <td>
                                <button class="btn btn-sm btn-danger" onclick="removePurchaseItem(${index})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            document.getElementById('grandTotal').textContent = grandTotal.toLocaleString() + ' TZS';
        }

        // Remove purchase item
        function removePurchaseItem(index) {
            purchaseItems.splice(index, 1);
            updatePurchaseItemsTable();
        }

        // Create purchase
        function createPurchase() {
            if (purchaseItems.length === 0) {
                alert('Please add at least one item');
                return;
            }

            const data = {
                supplier_id: parseInt(document.getElementById('purchaseSupplier').value),
                branch_id: parseInt(document.getElementById('purchaseBranch').value),
                purchase_date: document.getElementById('purchaseDate').value,
                payment_method: document.getElementById('paymentMethod').value,
                notes: document.getElementById('purchaseNotes').value,
                items: purchaseItems
            };

            fetch('ajax/create_purchase.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Purchase created successfully: ' + data.purchase_number);
                    bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
                    document.getElementById('purchaseForm').reset();
                    purchaseItems = [];
                    updatePurchaseItemsTable();
                    loadPurchases();
                    location.reload(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        // Load purchases
        function loadPurchases() {
            fetch('ajax/get_purchases.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPurchases(data.purchases);
                    }
                });
        }

        // Display purchases
        function displayPurchases(purchases) {
            const tbody = document.getElementById('purchasesTableBody');

            if (purchases.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">No purchases found</td></tr>';
                return;
            }

            tbody.innerHTML = purchases.map(purchase => `
                <tr>
                    <td><strong>${purchase.purchase_number}</strong></td>
                    <td>${purchase.supplier_name}</td>
                    <td>${purchase.branch_name}</td>
                    <td>${new Date(purchase.purchase_date).toLocaleDateString()}</td>
                    <td class="text-end">${parseFloat(purchase.total_amount).toLocaleString()}</td>
                    <td>${purchase.payment_method}</td>
                    <td>${getPaymentStatusBadge(purchase.payment_status)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewPurchaseDetails(${purchase.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Get payment status badge
        function getPaymentStatusBadge(status) {
            const badges = {
                'PAID': '<span class="badge bg-success">Paid</span>',
                'PENDING': '<span class="badge bg-warning">Pending</span>',
                'PARTIAL': '<span class="badge bg-info">Partial</span>'
            };
            return badges[status] || status;
        }

        // Create supplier
        function createSupplier() {
            const data = {
                name: document.getElementById('supplierName').value,
                contact_person: document.getElementById('contactPerson').value,
                phone: document.getElementById('supplierPhone').value,
                email: document.getElementById('supplierEmail').value,
                address: document.getElementById('supplierAddress').value,
                payment_terms: document.getElementById('paymentTerms').value,
                credit_limit: parseFloat(document.getElementById('creditLimit').value)
            };

            fetch('ajax/create_supplier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Supplier created successfully: ' + data.supplier_code);
                    bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        // Payment functions
        function openPaymentModal(supplierId = null) {
            console.log('Opening payment modal, supplierId:', supplierId);

            // Load suppliers with outstanding balances
            fetch('ajax/get_suppliers_with_balances.php')
                .then(response => {
                    console.log('Raw response status:', response.status);
                    console.log('Raw response headers:', response.headers);
                    return response.json();
                })
                .then(data => {
                    console.log('Full suppliers response:', data);

                    if (data.success) {
                        const select = document.getElementById('paymentSupplier');
                        select.innerHTML = '<option value="">Select supplier with outstanding balance</option>';

                        console.log('Number of suppliers found:', data.suppliers ? data.suppliers.length : 0);

                        if (data.suppliers && data.suppliers.length > 0) {
                            data.suppliers.forEach(supplier => {
                                console.log('Processing supplier:', supplier);
                                const selected = supplierId == supplier.id ? 'selected' : '';
                                select.innerHTML += `<option value="${supplier.id}" data-balance="${supplier.current_balance}" ${selected}>${supplier.name} (${supplier.supplier_code}) - ${parseFloat(supplier.current_balance).toLocaleString()} TZS</option>`;
                            });
                            console.log('✓ Suppliers loaded successfully into dropdown');
                        } else {
                            select.innerHTML += '<option value="">No suppliers with outstanding balances</option>';
                            console.log('⚠ No suppliers with outstanding balances found');
                        }

                        // If supplier was pre-selected, update balance
                        if (supplierId) {
                            updatePaymentBalance();
                        }
                    } else {
                        console.error('❌ AJAX Error - Server returned error:', data.error);
                        alert('Error loading suppliers: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('❌ Network/Parse Error:', error);
                    console.error('Error details:', error.message);
                    alert('Failed to load suppliers. Check browser console for details.');
                });

            // Show modal
            new bootstrap.Modal(document.getElementById('paymentModal')).show();
        }

        function updatePaymentBalance() {
            const select = document.getElementById('paymentSupplier');
            const option = select.options[select.selectedIndex];
            const balanceField = document.getElementById('currentBalance');
            const purchaseGroup = document.getElementById('purchaseSelectionGroup');
            const purchaseSelect = document.getElementById('paymentPurchase');

            if (option.value) {
                const balance = parseFloat(option.getAttribute('data-balance'));
                balanceField.value = balance.toLocaleString() + ' TZS';
                document.getElementById('paymentAmount').max = balance;

                // Show purchase selection and load pending purchases
                purchaseGroup.style.display = 'block';
                loadPendingPurchases(option.value);
            } else {
                balanceField.value = '';
                document.getElementById('paymentAmount').removeAttribute('max');
                purchaseGroup.style.display = 'none';
                purchaseSelect.innerHTML = '<option value="">General payment (not linked to specific purchase)</option>';
            }
        }

        function loadPendingPurchases(supplierId) {
            fetch(`ajax/get_pending_purchases.php?supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('paymentPurchase');
                    select.innerHTML = '<option value="">General payment (not linked to specific purchase)</option>';

                    if (data.success && data.purchases.length > 0) {
                        data.purchases.forEach(purchase => {
                            const option = document.createElement('option');
                            option.value = purchase.id;
                            option.textContent = `${purchase.purchase_number} - ${parseFloat(purchase.amount_due).toLocaleString()} TZS due`;
                            option.setAttribute('data-amount-due', purchase.amount_due);
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading pending purchases:', error);
                });
        }

        function updatePurchasePayment() {
            const purchaseSelect = document.getElementById('paymentPurchase');
            const amountField = document.getElementById('paymentAmount');
            const option = purchaseSelect.options[purchaseSelect.selectedIndex];

            if (option.value && option.getAttribute('data-amount-due')) {
                const amountDue = parseFloat(option.getAttribute('data-amount-due'));
                amountField.value = amountDue;
                amountField.max = amountDue;
            } else {
                // Reset to supplier balance max if no specific purchase selected
                updatePaymentBalance();
            }
        }

        function recordPayment() {
            const purchaseSelect = document.getElementById('paymentPurchase');
            const data = {
                supplier_id: parseInt(document.getElementById('paymentSupplier').value),
                amount: parseFloat(document.getElementById('paymentAmount').value),
                payment_method: document.getElementById('paymentMethodSelect').value,
                reference_number: document.getElementById('paymentReference').value,
                payment_date: document.getElementById('paymentDateInput').value,
                notes: document.getElementById('paymentNotes').value
            };

            // Add purchase_id if specific purchase is selected
            if (purchaseSelect.value) {
                data.purchase_id = parseInt(purchaseSelect.value);
            }

            // Validation
            if (!data.supplier_id || !data.amount || !data.payment_method || !data.payment_date) {
                alert('Please fill all required fields');
                return;
            }

            const selectedOption = document.getElementById('paymentSupplier').options[document.getElementById('paymentSupplier').selectedIndex];
            const currentBalance = parseFloat(selectedOption.getAttribute('data-balance'));

            if (data.amount > currentBalance) {
                alert('Payment amount cannot exceed outstanding balance of ' + currentBalance.toLocaleString() + ' TZS');
                return;
            }

            fetch('ajax/record_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment recorded successfully: ' + data.payment_number);
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                    document.getElementById('paymentForm').reset();
                    loadPayments();
                    location.reload(); // Refresh supplier balances
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to record payment');
            });
        }

        function loadPayments() {
            fetch('ajax/get_supplier_payments.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPayments(data.payments);
                    }
                });
        }

        function displayPayments(payments) {
            const tbody = document.getElementById('paymentsTableBody');

            if (payments.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No payments found</td></tr>';
                return;
            }

            tbody.innerHTML = payments.map(payment => `
                <tr>
                    <td><strong>${payment.payment_number}</strong></td>
                    <td>${payment.supplier_name} (${payment.supplier_code})</td>
                    <td class="text-end">${parseFloat(payment.amount).toLocaleString()}</td>
                    <td>${payment.payment_method}</td>
                    <td>${payment.reference_number || '-'}</td>
                    <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
                    <td>${payment.paid_by_name}</td>
                </tr>
            `).join('');
        }


        // View functions (placeholder)
        function viewPurchaseDetails(purchaseId) {
            fetch(`ajax/get_purchase_details.php?purchase_id=${purchaseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPurchaseDetailsModal(data.purchase, data.items);
                    } else {
                        alert('Error loading purchase details: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load purchase details');
                });
        }

        function viewSupplierDetails(supplierId) {
            fetch(`ajax/get_supplier_details.php?supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displaySupplierDetailsModal(data.supplier, data.purchases, data.payments);
                    } else {
                        alert('Error loading supplier details: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load supplier details');
                });
        }

        // Display purchase details modal
        function displayPurchaseDetailsModal(purchase, items) {
            const modalHtml = `
                <div class="modal fade" id="purchaseDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Purchase Details - ${purchase.purchase_number}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">PURCHASE INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Purchase Number:</strong></td>
                                                <td>${purchase.purchase_number}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Purchase Date:</strong></td>
                                                <td>${new Date(purchase.purchase_date).toLocaleDateString()}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Amount:</strong></td>
                                                <td><strong class="text-success">${parseFloat(purchase.total_amount).toLocaleString()} TZS</strong></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Payment Method:</strong></td>
                                                <td><span class="badge bg-info">${purchase.payment_method}</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Payment Status:</strong></td>
                                                <td>${getPaymentStatusBadge(purchase.payment_status)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Amount Paid:</strong></td>
                                                <td>${parseFloat(purchase.amount_paid).toLocaleString()} TZS</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Amount Due:</strong></td>
                                                <td class="text-danger">${parseFloat(purchase.amount_due).toLocaleString()} TZS</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">SUPPLIER INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Supplier:</strong></td>
                                                <td>${purchase.supplier_name} (${purchase.supplier_code})</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Contact Person:</strong></td>
                                                <td>${purchase.contact_person || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td>${purchase.phone}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Purchased By:</strong></td>
                                                <td>${purchase.purchased_by_name}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Branch:</strong></td>
                                                <td>${purchase.branch_name}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created:</strong></td>
                                                <td>${new Date(purchase.created_at).toLocaleString()}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="text-muted">PURCHASED ITEMS</h6>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Product Name</th>
                                                        <th>Type</th>
                                                        <th>Quantity</th>
                                                        <th>Unit</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${items.map(item => `
                                                        <tr>
                                                            <td>${item.product_name}</td>
                                                            <td><span class="badge bg-secondary">${item.product_type}</span></td>
                                                            <td>${parseFloat(item.quantity).toLocaleString()}</td>
                                                            <td>${item.unit}</td>
                                                            <td class="text-end">${parseFloat(item.unit_cost).toLocaleString()}</td>
                                                            <td class="text-end"><strong>${parseFloat(item.total_cost).toLocaleString()}</strong></td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                ${purchase.notes ? `
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <h6 class="text-muted">NOTES</h6>
                                            <div class="border rounded p-3 bg-light">
                                                ${purchase.notes}
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
            const existingModal = document.getElementById('purchaseDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('purchaseDetailsModal'));
            modal.show();

            // Remove modal from DOM when hidden
            document.getElementById('purchaseDetailsModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Display supplier details modal
        function displaySupplierDetailsModal(supplier, purchases, payments) {
            const modalHtml = `
                <div class="modal fade" id="supplierDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Supplier Details - ${supplier.supplier_code}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">SUPPLIER INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Supplier Code:</strong></td>
                                                <td>${supplier.supplier_code}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Company Name:</strong></td>
                                                <td>${supplier.name}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Contact Person:</strong></td>
                                                <td>${supplier.contact_person || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td>${supplier.phone}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td>${supplier.email || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Address:</strong></td>
                                                <td>${supplier.address || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">FINANCIAL INFORMATION</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Payment Terms:</strong></td>
                                                <td>${supplier.payment_terms}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Credit Limit:</strong></td>
                                                <td>${parseFloat(supplier.credit_limit).toLocaleString()} TZS</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Current Balance:</strong></td>
                                                <td class="${parseFloat(supplier.current_balance) > 0 ? 'text-danger' : 'text-success'}">
                                                    <strong>${parseFloat(supplier.current_balance).toLocaleString()} TZS</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td><span class="badge ${supplier.status === 'ACTIVE' ? 'bg-success' : 'bg-danger'}">${supplier.status}</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created By:</strong></td>
                                                <td>${supplier.created_by_name}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created:</strong></td>
                                                <td>${new Date(supplier.created_at).toLocaleDateString()}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- Tabs for Purchase History and Payment History -->
                                <ul class="nav nav-tabs mt-4" id="supplierDetailsTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                                            <i class="bi bi-cart"></i> Purchase History (${purchases.length})
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                                            <i class="bi bi-cash-coin"></i> Payment History (${payments.length})
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content mt-3" id="supplierDetailsTabContent">
                                    <div class="tab-pane fade show active" id="purchases" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Purchase #</th>
                                                        <th>Date</th>
                                                        <th>Amount</th>
                                                        <th>Payment Status</th>
                                                        <th>Branch</th>
                                                        <th>Purchased By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${purchases.length > 0 ? purchases.map(purchase => `
                                                        <tr>
                                                            <td><strong>${purchase.purchase_number}</strong></td>
                                                            <td>${new Date(purchase.purchase_date).toLocaleDateString()}</td>
                                                            <td class="text-end">${parseFloat(purchase.total_amount).toLocaleString()}</td>
                                                            <td>${getPaymentStatusBadge(purchase.payment_status)}</td>
                                                            <td>${purchase.branch_name}</td>
                                                            <td>${purchase.purchased_by_name}</td>
                                                        </tr>
                                                    `).join('') : '<tr><td colspan="6" class="text-center text-muted">No purchases found</td></tr>'}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="payments" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Payment #</th>
                                                        <th>Date</th>
                                                        <th>Amount</th>
                                                        <th>Method</th>
                                                        <th>Reference</th>
                                                        <th>Branch</th>
                                                        <th>Paid By</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${payments.length > 0 ? payments.map(payment => `
                                                        <tr>
                                                            <td><strong>${payment.payment_number}</strong></td>
                                                            <td>${new Date(payment.payment_date).toLocaleDateString()}</td>
                                                            <td class="text-end">${parseFloat(payment.amount).toLocaleString()}</td>
                                                            <td><span class="badge bg-info">${payment.payment_method}</span></td>
                                                            <td>${payment.reference_number || 'N/A'}</td>
                                                            <td>${payment.branch_name}</td>
                                                            <td>${payment.paid_by_name}</td>
                                                        </tr>
                                                    `).join('') : '<tr><td colspan="7" class="text-center text-muted">No payments found</td></tr>'}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                ${supplier.status === 'ACTIVE' ? `
                                    <button type="button" class="btn btn-primary" onclick="editSupplier(${supplier.id})">
                                        <i class="bi bi-pencil"></i> Edit Supplier
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('supplierDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('supplierDetailsModal'));
            modal.show();

            // Remove modal from DOM when hidden
            document.getElementById('supplierDetailsModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Get payment status badge
        function getPaymentStatusBadge(status) {
            const badges = {
                'PAID': '<span class="badge bg-success">Paid</span>',
                'PENDING': '<span class="badge bg-warning">Pending</span>',
                'PARTIAL': '<span class="badge bg-info">Partial</span>'
            };
            return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
        }

        // Edit supplier function
        function editSupplier(supplierId) {
            // Close the details modal first
            const detailsModal = bootstrap.Modal.getInstance(document.getElementById('supplierDetailsModal'));
            if (detailsModal) {
                detailsModal.hide();
            }

            // Fetch supplier details for editing
            fetch(`ajax/get_supplier_details.php?supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditSupplierModal(data.supplier);
                    } else {
                        alert('Error loading supplier for editing: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load supplier for editing');
                });
        }

        // Show edit supplier modal
        function showEditSupplierModal(supplier) {
            const modalHtml = `
                <div class="modal fade" id="editSupplierModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Supplier - ${supplier.supplier_code}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editSupplierForm">
                                    <input type="hidden" id="editSupplierId" value="${supplier.id}">

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="editSupplierName" class="form-label">Company Name *</label>
                                                <input type="text" class="form-control" id="editSupplierName" required
                                                    value="${supplier.name}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="editContactPerson" class="form-label">Contact Person</label>
                                                <input type="text" class="form-control" id="editContactPerson"
                                                    value="${supplier.contact_person || ''}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="editSupplierPhone" class="form-label">Phone *</label>
                                                <input type="text" class="form-control" id="editSupplierPhone" required
                                                    value="${supplier.phone}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="editSupplierEmail" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="editSupplierEmail"
                                                    value="${supplier.email || ''}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="editSupplierAddress" class="form-label">Address</label>
                                                <textarea class="form-control" id="editSupplierAddress" rows="3">${supplier.address || ''}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="editPaymentTerms" class="form-label">Payment Terms</label>
                                                <select class="form-select" id="editPaymentTerms">
                                                    <option value="Cash on Delivery" ${supplier.payment_terms === 'Cash on Delivery' ? 'selected' : ''}>Cash on Delivery</option>
                                                    <option value="Net 15" ${supplier.payment_terms === 'Net 15' ? 'selected' : ''}>Net 15</option>
                                                    <option value="Net 30" ${supplier.payment_terms === 'Net 30' ? 'selected' : ''}>Net 30</option>
                                                    <option value="Net 45" ${supplier.payment_terms === 'Net 45' ? 'selected' : ''}>Net 45</option>
                                                    <option value="Net 60" ${supplier.payment_terms === 'Net 60' ? 'selected' : ''}>Net 60</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="editCreditLimit" class="form-label">Credit Limit (TZS)</label>
                                                <input type="number" class="form-control" id="editCreditLimit"
                                                    step="0.01" min="0" value="${supplier.credit_limit}">
                                            </div>
                                            <div class="mb-3">
                                                <label for="editSupplierStatus" class="form-label">Status</label>
                                                <select class="form-select" id="editSupplierStatus">
                                                    <option value="ACTIVE" ${supplier.status === 'ACTIVE' ? 'selected' : ''}>Active</option>
                                                    <option value="INACTIVE" ${supplier.status === 'INACTIVE' ? 'selected' : ''}>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="updateSupplier()">
                                    <i class="bi bi-check-circle"></i> Update Supplier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('editSupplierModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
            modal.show();

            // Remove modal from DOM when hidden
            document.getElementById('editSupplierModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Update supplier
        function updateSupplier() {
            const supplierId = document.getElementById('editSupplierId').value;
            const name = document.getElementById('editSupplierName').value.trim();
            const contactPerson = document.getElementById('editContactPerson').value.trim();
            const phone = document.getElementById('editSupplierPhone').value.trim();
            const email = document.getElementById('editSupplierEmail').value.trim();
            const address = document.getElementById('editSupplierAddress').value.trim();
            const paymentTerms = document.getElementById('editPaymentTerms').value;
            const creditLimit = parseFloat(document.getElementById('editCreditLimit').value) || 0;
            const status = document.getElementById('editSupplierStatus').value;

            if (!name || !phone) {
                alert('Please fill in all required fields (Name and Phone)');
                return;
            }

            if (creditLimit < 0) {
                alert('Credit limit cannot be negative');
                return;
            }

            const data = {
                supplier_id: parseInt(supplierId),
                name: name,
                contact_person: contactPerson,
                phone: phone,
                email: email,
                address: address,
                payment_terms: paymentTerms,
                credit_limit: creditLimit,
                status: status
            };

            fetch('ajax/update_supplier.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Supplier updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('editSupplierModal')).hide();
                    loadSuppliers(); // Refresh suppliers list
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the supplier');
            });
        }
    </script>
</body>
</html>