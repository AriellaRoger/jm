<?php
// File: reports/index.php
// Finance and reporting dashboard - Administrator only

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// Check permissions - Administrator only
if ($_SESSION['user_role'] !== 'Administrator') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$userRole = $_SESSION['user_role'];
$userId = $_SESSION['user_id'];

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <h2><i class="bi bi-graph-up"></i> Finance & Reports Dashboard</h2>
    <p class="text-muted">Comprehensive business analytics and performance reporting</p>

    <!-- Key Metrics Summary Cards -->
    <div class="row mb-4" id="keyMetricsCards">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="text-center">
                        <div class="spinner-border" role="status"></div>
                        <p class="mt-2 mb-0">Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Navigation Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#productionReports">
                <i class="bi bi-gear-fill"></i> Production Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#salesReports">
                <i class="bi bi-cart-fill"></i> Sales Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#inventoryReports">
                <i class="bi bi-box-fill"></i> Inventory Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#expenseReports">
                <i class="bi bi-receipt"></i> Expense Reports
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#financialReports">
                <i class="bi bi-graph-up"></i> Financial Reports
            </a>
        </li>
    </ul>

    <!-- Reports Content -->
    <div class="tab-content">
        <!-- Production Reports Tab -->
        <div id="productionReports" class="tab-pane fade show active">
            <div class="row">
                <!-- Production Cost Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-gear"></i> Production Cost Analysis</h5>
                        </div>
                        <div class="card-body">
                            <!-- Date Range Selector -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Report Type</label>
                                    <select class="form-select" id="reportType">
                                        <option value="batch">By Batch</option>
                                        <option value="daily">Daily Summary</option>
                                        <option value="monthly">Monthly Summary</option>
                                        <option value="yearly">Yearly Summary</option>
                                        <option value="custom">Custom Date Range</option>
                                        <option value="all-time">All Time Summary</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="startDateDiv" style="display: none;">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate">
                                </div>
                                <div class="col-md-3" id="endDateDiv" style="display: none;">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate">
                                </div>
                                <div class="col-md-3" id="yearDiv" style="display: none;">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="yearSelector">
                                        <option value="2025">2025</option>
                                        <option value="2024">2024</option>
                                        <option value="2023">2023</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-primary w-100" onclick="generateProductionReport()">
                                        <i class="bi bi-play-fill"></i> Generate Report
                                    </button>
                                </div>
                            </div>

                            <!-- Report Results -->
                            <div id="reportResults">
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-graph-up" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Select report type and click "Generate Report" to view production cost analysis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production Efficiency Metrics -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-speedometer2"></i> Production Efficiency Metrics</h5>
                        </div>
                        <div class="card-body" id="efficiencyMetrics">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading efficiency metrics...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personnel Performance Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5><i class="bi bi-people-fill"></i> Personnel Performance Analysis</h5>
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" id="personnelReportType" onchange="loadPersonnelPerformance()">
                                        <option value="officers">Production Officers</option>
                                        <option value="supervisors">Supervisors</option>
                                        <option value="both">Both Officers & Supervisors</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" id="personnelPerformance">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading personnel performance...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Cost Breakdown -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-pie-chart"></i> Product Cost Breakdown</h5>
                        </div>
                        <div class="card-body" id="productCostBreakdown">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading product cost breakdown...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Reports Tab -->
        <div id="salesReports" class="tab-pane fade">
            <div class="row">
                <!-- Sales Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-cart-fill"></i> Sales Performance Analysis</h5>
                        </div>
                        <div class="card-body">
                            <!-- Date Range and Branch Selector -->
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Report Type</label>
                                    <select class="form-select" id="salesReportType">
                                        <option value="daily">Daily Sales</option>
                                        <option value="monthly">Monthly Sales</option>
                                        <option value="yearly">Yearly Sales</option>
                                        <option value="custom">Custom Range</option>
                                        <option value="all-time">All Time</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="salesStartDateDiv" style="display: none;">
                                    <label class="form-label" id="salesStartDateLabel">Start Date</label>
                                    <input type="date" class="form-control" id="salesStartDate">
                                </div>
                                <div class="col-md-2" id="salesEndDateDiv" style="display: none;">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="salesEndDate">
                                </div>
                                <div class="col-md-2" id="salesYearDiv" style="display: none;">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="salesYearSelector">
                                        <option value="2025">2025</option>
                                        <option value="2024">2024</option>
                                        <option value="2023">2023</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Branch</label>
                                    <select class="form-select" id="salesBranchSelector">
                                        <option value="">All Branches</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-primary w-100" onclick="generateSalesReport()">
                                        <i class="bi bi-play-fill"></i> Generate
                                    </button>
                                </div>
                            </div>

                            <!-- Sales Results -->
                            <div id="salesReportResults">
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-cart-fill" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Select report type and click "Generate" to view sales analysis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Branch Performance Comparison -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-building"></i> Branch Performance Comparison</h5>
                        </div>
                        <div class="card-body" id="branchPerformance">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading branch performance...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Performance Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-box"></i> Product Performance Analysis</h5>
                        </div>
                        <div class="card-body" id="productPerformance">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading product performance...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-credit-card"></i> Payment Method & Credit Analysis</h5>
                        </div>
                        <div class="card-body" id="paymentAnalysis">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading payment analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-people"></i> Top Customers Analysis</h5>
                        </div>
                        <div class="card-body" id="topCustomers">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading top customers...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Reports Tab -->
        <div id="inventoryReports" class="tab-pane fade">
            <div class="row">
                <!-- Inventory Stock Levels Report -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-boxes"></i> Inventory Stock Levels & Values</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Branch</label>
                                    <select class="form-select" id="stockLevelsBranchFilter">
                                        <option value="">All Branches</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-primary d-block" onclick="loadInventoryStockLevels()">
                                        <i class="bi bi-bar-chart"></i> Generate Report
                                    </button>
                                </div>
                            </div>
                            <div id="inventoryStockLevelsResults">
                                <div class="text-center py-4">
                                    <div class="spinner-border" role="status"></div>
                                    <p class="mt-2">Loading inventory stock levels...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Value Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-currency-dollar"></i> Inventory Value Analysis</h5>
                            <div>
                                <select class="form-select-sm me-2" id="valueAnalysisBranchFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Branches</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadInventoryValueAnalysis()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="inventoryValueAnalysis">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading inventory value analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Movements & Reconciliation -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-arrow-left-right"></i> Inventory Movements & Reconciliation</h5>
                            <div>
                                <select class="form-select-sm me-2" id="movementProductTypeFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Product Types</option>
                                    <option value="RAW_MATERIAL">Raw Materials</option>
                                    <option value="FINISHED_PRODUCT">Finished Products</option>
                                    <option value="THIRD_PARTY_PRODUCT">Third Party Products</option>
                                    <option value="PACKAGING_MATERIAL">Packaging Materials</option>
                                </select>
                                <select class="form-select-sm me-2" id="movementBranchFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Branches</option>
                                </select>
                                <input type="date" class="form-control-sm me-2" id="movementStartDate" style="display: inline-block; width: auto;">
                                <input type="date" class="form-control-sm me-2" id="movementEndDate" style="display: inline-block; width: auto;">
                                <button class="btn btn-sm btn-outline-primary" onclick="loadInventoryMovements()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="inventoryMovements">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading inventory movements...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-exclamation-triangle"></i> Low Stock Alerts</h5>
                            <div>
                                <select class="form-select-sm me-2" id="lowStockBranchFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Branches</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadLowStockAlerts()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="lowStockAlerts">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading low stock alerts...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Reports Tab -->
        <div id="expenseReports" class="tab-pane fade">
            <div class="row">
                <!-- Time-based Expense Reports -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-receipt"></i> Expense Analysis & Reports</h5>
                        </div>
                        <div class="card-body">
                            <!-- Controls -->
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <label class="form-label">Report Type</label>
                                    <select class="form-select" id="expenseReportType">
                                        <option value="daily">Daily Expenses</option>
                                        <option value="monthly">Monthly Expenses</option>
                                        <option value="yearly">Yearly Expenses</option>
                                        <option value="custom">Custom Range</option>
                                        <option value="all-time">All Time</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="expenseStartDateDiv" style="display: none;">
                                    <label class="form-label" id="expenseStartDateLabel">Select Date</label>
                                    <input type="date" class="form-control" id="expenseStartDate">
                                </div>
                                <div class="col-md-2" id="expenseEndDateDiv" style="display: none;">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="expenseEndDate">
                                </div>
                                <div class="col-md-2" id="expenseYearDiv" style="display: none;">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="expenseYearSelector">
                                        <option value="2025">2025</option>
                                        <option value="2024">2024</option>
                                        <option value="2023">2023</option>
                                    </select>
                                </div>
                                <div class="col-md-2" id="expenseMonthDiv" style="display: none;">
                                    <label class="form-label">Month</label>
                                    <select class="form-select" id="expenseMonthSelector">
                                        <option value="01">January</option>
                                        <option value="02">February</option>
                                        <option value="03">March</option>
                                        <option value="04">April</option>
                                        <option value="05">May</option>
                                        <option value="06">June</option>
                                        <option value="07">July</option>
                                        <option value="08">August</option>
                                        <option value="09" selected>September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Branch</label>
                                    <select class="form-select" id="expenseBranchFilter">
                                        <option value="">All Branches</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-primary d-block" onclick="generateExpenseReport()">
                                        <i class="bi bi-bar-chart"></i> Generate Report
                                    </button>
                                </div>
                            </div>

                            <!-- Report Results -->
                            <div id="expenseReportResults">
                                <div class="text-center py-4">
                                    <i class="bi bi-info-circle fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">Select report type and click "Generate Report" to view expense analysis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fleet Expense Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-truck"></i> Fleet Expense Analysis</h5>
                            <div>
                                <select class="form-select-sm me-2" id="fleetVehicleFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Vehicles</option>
                                </select>
                                <input type="date" class="form-control-sm me-2" id="fleetExpenseStartDate" style="display: inline-block; width: auto;">
                                <input type="date" class="form-control-sm me-2" id="fleetExpenseEndDate" style="display: inline-block; width: auto;">
                                <button class="btn btn-sm btn-outline-primary" onclick="loadFleetExpenseAnalysis()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="fleetExpenseAnalysis">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading fleet expense analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Machine Expense Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-gear-fill"></i> Machine Expense Analysis</h5>
                            <div>
                                <select class="form-select-sm me-2" id="machineFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Machines</option>
                                </select>
                                <input type="date" class="form-control-sm me-2" id="machineExpenseStartDate" style="display: inline-block; width: auto;">
                                <input type="date" class="form-control-sm me-2" id="machineExpenseEndDate" style="display: inline-block; width: auto;">
                                <button class="btn btn-sm btn-outline-primary" onclick="loadMachineExpenseAnalysis()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="machineExpenseAnalysis">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading machine expense analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expense Type Analysis -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="bi bi-pie-chart"></i> Expense Type Breakdown</h5>
                            <div>
                                <input type="date" class="form-control-sm me-2" id="expenseTypeStartDate" style="display: inline-block; width: auto;">
                                <input type="date" class="form-control-sm me-2" id="expenseTypeEndDate" style="display: inline-block; width: auto;">
                                <select class="form-select-sm me-2" id="expenseTypeBranchFilter" style="display: inline-block; width: auto;">
                                    <option value="">All Branches</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadExpenseTypeAnalysis()">
                                    <i class="bi bi-arrow-repeat"></i> Update
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="expenseTypeAnalysis">
                            <div class="text-center py-4">
                                <div class="spinner-border" role="status"></div>
                                <p class="mt-2">Loading expense type analysis...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Reports Tab -->
        <div id="financialReports" class="tab-pane fade">
            <div class="row">
                <!-- Profit & Loss Report -->
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="bi bi-graph-up"></i> Profit & Loss Statement</h5>
                        </div>
                        <div class="card-body">
                            <!-- Report Type and Date Filters -->
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Report Period</label>
                                    <select class="form-select" id="plReportType">
                                        <option value="all-time">All Time</option>
                                        <option value="daily">Today</option>
                                        <option value="weekly">This Week</option>
                                        <option value="monthly">This Month</option>
                                        <option value="yearly">This Year</option>
                                        <option value="custom">Custom Date Range</option>
                                    </select>
                                </div>
                                <div class="col-md-3" id="plStartDateDiv" style="display: none;">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="plStartDate">
                                </div>
                                <div class="col-md-3" id="plEndDateDiv" style="display: none;">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="plEndDate">
                                </div>
                                <div class="col-md-3" id="plYearDiv" style="display: none;">
                                    <label class="form-label">Year</label>
                                    <select class="form-select" id="plYearSelector">
                                        <option value="2025">2025</option>
                                        <option value="2024">2024</option>
                                        <option value="2023">2023</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-primary w-100" onclick="generatePLReport()">
                                        <i class="bi bi-calculator"></i> Generate P&L Report
                                    </button>
                                </div>
                            </div>

                            <!-- Report Results -->
                            <div id="plReportResults">
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-graph-up" style="font-size: 3rem;"></i>
                                    <p class="mt-2">Select report period and click "Generate P&L Report" to view comprehensive profit & loss analysis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary Cards -->
                <div class="col-md-12 mb-4" id="financialSummaryCards" style="display: none;">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6>Total Revenue</h6>
                                    <h4 id="totalRevenue">0 TZS</h4>
                                    <small id="revenuePeriod">All Time</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6>Cost of Goods Sold</h6>
                                    <h4 id="totalCOGS">0 TZS</h4>
                                    <small id="cogsPeriod">All Time</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6>Operating Expenses</h6>
                                    <h4 id="totalOperatingExpenses">0 TZS</h4>
                                    <small id="expensesPeriod">All Time</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6>Net Profit/Loss</h6>
                                    <h4 id="netProfit">0 TZS</h4>
                                    <small id="profitPeriod">All Time</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadKeyMetrics();
    loadEfficiencyMetrics();
    loadProductCostBreakdown();
    loadPersonnelPerformance();
    loadBranches();
    loadBranchPerformance();
    loadProductPerformance();
    loadPaymentAnalysis();
    loadTopCustomers();

    // Handle production report type change
    document.getElementById('reportType').addEventListener('change', function() {
        const reportType = this.value;
        const startDateDiv = document.getElementById('startDateDiv');
        const endDateDiv = document.getElementById('endDateDiv');
        const yearDiv = document.getElementById('yearDiv');

        // Hide all date inputs first
        startDateDiv.style.display = 'none';
        endDateDiv.style.display = 'none';
        yearDiv.style.display = 'none';

        // Show relevant date inputs
        if (reportType === 'custom') {
            startDateDiv.style.display = 'block';
            endDateDiv.style.display = 'block';
        } else if (reportType === 'monthly') {
            yearDiv.style.display = 'block';
        }
    });

    // Handle sales report type change
    document.getElementById('salesReportType').addEventListener('change', function() {
        const reportType = this.value;
        const startDateDiv = document.getElementById('salesStartDateDiv');
        const endDateDiv = document.getElementById('salesEndDateDiv');
        const yearDiv = document.getElementById('salesYearDiv');

        // Hide all date inputs first
        startDateDiv.style.display = 'none';
        endDateDiv.style.display = 'none';
        yearDiv.style.display = 'none';

        // Show relevant date inputs
        if (reportType === 'daily') {
            startDateDiv.style.display = 'block';
            document.getElementById('salesStartDateLabel').textContent = 'Select Date';
            // Set default to today for daily reports
            document.getElementById('salesStartDate').value = new Date().toISOString().split('T')[0];
        } else if (reportType === 'custom') {
            startDateDiv.style.display = 'block';
            endDateDiv.style.display = 'block';
            document.getElementById('salesStartDateLabel').textContent = 'Start Date';
        } else if (reportType === 'monthly') {
            yearDiv.style.display = 'block';
        }
    });
});

function loadKeyMetrics() {
    fetch('ajax/get_key_metrics.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayKeyMetrics(data.metrics);
        } else {
            console.error('Error loading key metrics:', data.message);
        }
    });
}

function displayKeyMetrics(metrics) {
    const cardsContainer = document.getElementById('keyMetricsCards');

    cardsContainer.innerHTML = `
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Production</h6>
                            <h2 class="mb-0">${metrics.total_batches || 0}</h2>
                            <small>Completed Batches</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-gear-fill" style="font-size: 2rem;"></i>
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
                            <h6 class="card-title">Total Yield</h6>
                            <h2 class="mb-0">${formatNumber(metrics.total_yield) || 0}</h2>
                            <small>KG Produced</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box-fill" style="font-size: 2rem;"></i>
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
                            <h6 class="card-title">Production Cost</h6>
                            <h2 class="mb-0">${formatCurrency(metrics.total_production_cost) || 0}</h2>
                            <small>Total Investment</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Average Cost/KG</h6>
                            <h2 class="mb-0">${formatCurrency(metrics.avg_cost_per_kg) || 0}</h2>
                            <small>Per Kilogram</small>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-calculator" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function loadEfficiencyMetrics() {
    fetch('ajax/get_efficiency_metrics.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayEfficiencyMetrics(data.metrics);
        } else {
            document.getElementById('efficiencyMetrics').innerHTML =
                '<div class="alert alert-warning">No efficiency data available</div>';
        }
    });
}

function displayEfficiencyMetrics(metrics) {
    const container = document.getElementById('efficiencyMetrics');

    if (!metrics || metrics.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No production efficiency data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Batch Number</th>
                        <th>Formula</th>
                        <th>Production Officer</th>
                        <th>Supervisor</th>
                        <th>Expected Yield</th>
                        <th>Actual Yield</th>
                        <th>Efficiency %</th>
                        <th>Wastage %</th>
                        <th>Cost/KG</th>
                        <th>Total Cost</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
    `;

    metrics.forEach(metric => {
        const efficiencyClass = metric.efficiency_percentage >= 95 ? 'text-success' :
                               metric.efficiency_percentage >= 85 ? 'text-warning' : 'text-danger';

        html += `
            <tr>
                <td><strong>${metric.batch_number}</strong></td>
                <td>${metric.formula_name}</td>
                <td><span class="badge bg-primary">${metric.production_officer}</span></td>
                <td><span class="badge bg-secondary">${metric.supervisor}</span></td>
                <td>${formatNumber(metric.expected_yield)} KG</td>
                <td>${formatNumber(metric.actual_yield)} KG</td>
                <td class="${efficiencyClass}"><strong>${formatNumber(metric.efficiency_percentage)}%</strong></td>
                <td>${formatNumber(metric.wastage_percentage)}%</td>
                <td>${formatCurrency(metric.cost_per_kg)}</td>
                <td>${formatCurrency(metric.production_cost)}</td>
                <td>${formatDate(metric.completed_at)}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadPersonnelPerformance() {
    const reportType = document.getElementById('personnelReportType').value;

    fetch('ajax/get_personnel_performance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({report_type: reportType})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPersonnelPerformance(data.performance, reportType);
        } else {
            document.getElementById('personnelPerformance').innerHTML =
                '<div class="alert alert-warning">No personnel performance data available</div>';
        }
    });
}

function displayPersonnelPerformance(performance, reportType) {
    const container = document.getElementById('personnelPerformance');

    if (!performance) {
        container.innerHTML = '<div class="alert alert-info">No personnel performance data available</div>';
        return;
    }

    let html = '';

    // Production Officers Performance
    if (reportType === 'officers' || reportType === 'both') {
        if (performance.officers && performance.officers.length > 0) {
            html += `
                <h6 class="mb-3"><i class="bi bi-person-gear"></i> Production Officers Performance</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Production Officer</th>
                                <th>Total Batches</th>
                                <th>Total Yield (KG)</th>
                                <th>Total Investment</th>
                                <th>Avg Efficiency %</th>
                                <th>Avg Wastage %</th>
                                <th>Avg Cost/KG</th>
                                <th>Total Bags</th>
                                <th>Period</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            performance.officers.forEach(officer => {
                const efficiencyClass = officer.avg_efficiency_percentage >= 95 ? 'text-success' :
                                       officer.avg_efficiency_percentage >= 85 ? 'text-warning' : 'text-danger';
                const wastageClass = officer.avg_wastage_percentage <= 2 ? 'text-success' :
                                    officer.avg_wastage_percentage <= 5 ? 'text-warning' : 'text-danger';

                html += `
                    <tr>
                        <td><strong class="text-primary">${officer.production_officer}</strong></td>
                        <td>${officer.total_batches}</td>
                        <td>${formatNumber(officer.total_yield)}</td>
                        <td>${formatCurrency(officer.total_production_cost)}</td>
                        <td class="${efficiencyClass}"><strong>${formatNumber(officer.avg_efficiency_percentage)}%</strong></td>
                        <td class="${wastageClass}">${formatNumber(officer.avg_wastage_percentage)}%</td>
                        <td>${formatCurrency(officer.avg_cost_per_kg)}</td>
                        <td>${officer.total_bags_produced}</td>
                        <td><small>${formatDate(officer.first_batch)} - ${formatDate(officer.last_batch)}</small></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div><br>';
        }
    }

    // Supervisors Performance
    if (reportType === 'supervisors' || reportType === 'both') {
        if (performance.supervisors && performance.supervisors.length > 0) {
            html += `
                <h6 class="mb-3"><i class="bi bi-person-check"></i> Supervisors Oversight Performance</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Supervisor</th>
                                <th>Batches Supervised</th>
                                <th>Officers Supervised</th>
                                <th>Total Yield (KG)</th>
                                <th>Total Investment</th>
                                <th>Avg Efficiency %</th>
                                <th>Avg Wastage %</th>
                                <th>Avg Cost/KG</th>
                                <th>Total Bags</th>
                                <th>Period</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            performance.supervisors.forEach(supervisor => {
                const efficiencyClass = supervisor.avg_efficiency_percentage >= 95 ? 'text-success' :
                                       supervisor.avg_efficiency_percentage >= 85 ? 'text-warning' : 'text-danger';
                const wastageClass = supervisor.avg_wastage_percentage <= 2 ? 'text-success' :
                                    supervisor.avg_wastage_percentage <= 5 ? 'text-warning' : 'text-danger';

                html += `
                    <tr>
                        <td><strong class="text-secondary">${supervisor.supervisor}</strong></td>
                        <td>${supervisor.total_batches_supervised}</td>
                        <td><span class="badge bg-info">${supervisor.officers_supervised}</span></td>
                        <td>${formatNumber(supervisor.total_yield)}</td>
                        <td>${formatCurrency(supervisor.total_production_cost)}</td>
                        <td class="${efficiencyClass}"><strong>${formatNumber(supervisor.avg_efficiency_percentage)}%</strong></td>
                        <td class="${wastageClass}">${formatNumber(supervisor.avg_wastage_percentage)}%</td>
                        <td>${formatCurrency(supervisor.avg_cost_per_kg)}</td>
                        <td>${supervisor.total_bags_supervised}</td>
                        <td><small>${formatDate(supervisor.first_supervision)} - ${formatDate(supervisor.last_supervision)}</small></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
        }
    }

    if (html === '') {
        html = '<div class="alert alert-info">No personnel performance data available for the selected criteria</div>';
    }

    container.innerHTML = html;
}

function loadProductCostBreakdown() {
    fetch('ajax/get_product_cost_breakdown.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayProductCostBreakdown(data.products);
        } else {
            document.getElementById('productCostBreakdown').innerHTML =
                '<div class="alert alert-warning">No product cost data available</div>';
        }
    });
}

function displayProductCostBreakdown(products) {
    const container = document.getElementById('productCostBreakdown');

    if (!products || products.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No product cost breakdown data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Package Size</th>
                        <th>Total Bags Produced</th>
                        <th>Total Weight (KG)</th>
                        <th>Packaging Cost</th>
                        <th>Avg Cost per KG</th>
                        <th>Avg Cost per Bag</th>
                        <th>Total Batches</th>
                    </tr>
                </thead>
                <tbody>
    `;

    products.forEach(product => {
        html += `
            <tr>
                <td><strong>${product.product_name}</strong></td>
                <td><span class="badge bg-primary">${product.package_size}</span></td>
                <td>${formatNumber(product.total_bags_produced)}</td>
                <td>${formatNumber(product.total_weight_produced)}</td>
                <td>${formatCurrency(product.total_packaging_cost)}</td>
                <td>${formatCurrency(product.avg_cost_per_kg)}</td>
                <td class="text-success"><strong>${formatCurrency(product.avg_cost_per_bag)}</strong></td>
                <td>${product.total_batches}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function generateProductionReport() {
    const reportType = document.getElementById('reportType').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const year = document.getElementById('yearSelector').value;

    // Validate inputs
    if (reportType === 'custom' && (!startDate || !endDate)) {
        alert('Please select both start and end dates for custom range');
        return;
    }

    // Show loading
    document.getElementById('reportResults').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2">Generating ${reportType} production report...</p>
        </div>
    `;

    // Prepare request data
    const requestData = {
        report_type: reportType,
        start_date: startDate,
        end_date: endDate,
        year: year
    };

    fetch('ajax/get_production_report.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayProductionReport(data.report, reportType);
        } else {
            document.getElementById('reportResults').innerHTML =
                `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('reportResults').innerHTML =
            '<div class="alert alert-danger">Error generating report</div>';
    });
}

function displayProductionReport(report, reportType) {
    const container = document.getElementById('reportResults');

    if (reportType === 'batch') {
        displayBatchReport(report, container);
    } else if (reportType === 'daily') {
        displayDailyReport(report, container);
    } else if (reportType === 'monthly') {
        displayMonthlyReport(report, container);
    } else if (reportType === 'yearly') {
        displayYearlyReport(report, container);
    } else if (reportType === 'all_time') {
        displayAllTimeReport(report, container);
    }
}

function displayBatchReport(batches, container) {
    if (!batches || batches.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No production batches found</div>';
        return;
    }

    let html = '<h6 class="mb-3">Production Cost by Batch</h6>';

    batches.forEach(batch => {
        html += `
            <div class="card mb-3">
                <div class="card-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-0">${batch.batch_number}</h6>
                            <small class="text-muted">Formula: ${batch.formula_name}</small>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-${batch.status === 'COMPLETED' ? 'success' : 'warning'}">${batch.status}</span>
                            <br><small class="text-muted">Completed: ${formatDate(batch.completed_at)}</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Production Summary</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td>Batch Size:</td><td><strong>${formatNumber(batch.batch_size)} KG</strong></td></tr>
                                <tr><td>Expected Yield:</td><td>${formatNumber(batch.expected_yield)} KG</td></tr>
                                <tr><td>Actual Yield:</td><td><strong>${formatNumber(batch.actual_yield)} KG</strong></td></tr>
                                <tr><td>Wastage:</td><td>${formatNumber(batch.wastage_percentage)}%</td></tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h6>Cost Breakdown</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td>Raw Materials:</td><td><strong>${formatCurrency(batch.raw_materials_cost)}</strong></td></tr>
                                <tr><td>Packaging:</td><td>${formatCurrency(batch.packaging_cost)}</td></tr>
                                <tr><td>Total Production Cost:</td><td><strong class="text-primary">${formatCurrency(batch.production_cost)}</strong></td></tr>
                                <tr><td>Cost per KG:</td><td><strong class="text-success">${formatCurrency(batch.cost_per_kg)}</strong></td></tr>
                            </table>
                        </div>
                        <div class="col-md-4">
                            <h6>Output & Team</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td>Total Bags:</td><td><strong>${batch.total_bags}</strong></td></tr>
                                <tr><td>Production Officer:</td><td>${batch.production_officer}</td></tr>
                                <tr><td>Supervisor:</td><td>${batch.supervisor}</td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Products Produced -->
                    <h6 class="mt-3">Products Produced</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Package Size</th>
                                    <th>Bags Produced</th>
                                    <th>Weight (KG)</th>
                                    <th>Cost per Bag</th>
                                </tr>
                            </thead>
                            <tbody>
        `;

        if (batch.products) {
            batch.products.forEach(product => {
                html += `
                    <tr>
                        <td>${product.product_name}</td>
                        <td><span class="badge bg-info">${product.package_size}</span></td>
                        <td>${product.bags_produced}</td>
                        <td>${formatNumber(product.total_weight)}</td>
                        <td><strong>${formatCurrency(product.cost_per_bag)}</strong></td>
                    </tr>
                `;
            });
        }

        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function displayDailyReport(dailyReports, container) {
    if (!dailyReports || dailyReports.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No daily production data found for the selected period</div>';
        return;
    }

    let html = `
        <h6 class="mb-3">Daily Production Cost Summary</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Batches</th>
                        <th>Total Yield (KG)</th>
                        <th>Raw Materials Cost</th>
                        <th>Packaging Cost</th>
                        <th>Total Production Cost</th>
                        <th>Bags Produced</th>
                        <th>Avg Cost/KG</th>
                    </tr>
                </thead>
                <tbody>
    `;

    dailyReports.forEach(day => {
        html += `
            <tr>
                <td><strong>${formatDate(day.production_date)}</strong></td>
                <td>${day.total_batches}</td>
                <td>${formatNumber(day.total_yield)}</td>
                <td>${formatCurrency(day.total_raw_materials_cost)}</td>
                <td>${formatCurrency(day.total_packaging_cost)}</td>
                <td><strong>${formatCurrency(day.total_production_cost)}</strong></td>
                <td>${day.total_bags_produced}</td>
                <td><strong class="text-success">${formatCurrency(day.avg_cost_per_kg)}</strong></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayMonthlyReport(monthlyReports, container) {
    if (!monthlyReports || monthlyReports.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No monthly production data found for the selected year</div>';
        return;
    }

    let html = `
        <h6 class="mb-3">Monthly Production Cost Summary</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Batches</th>
                        <th>Total Yield (KG)</th>
                        <th>Raw Materials Cost</th>
                        <th>Packaging Cost</th>
                        <th>Total Production Cost</th>
                        <th>Bags Produced</th>
                        <th>Avg Cost/KG</th>
                    </tr>
                </thead>
                <tbody>
    `;

    monthlyReports.forEach(month => {
        html += `
            <tr>
                <td><strong>${month.month_name} ${month.year}</strong></td>
                <td>${month.total_batches}</td>
                <td>${formatNumber(month.total_yield)}</td>
                <td>${formatCurrency(month.total_raw_materials_cost)}</td>
                <td>${formatCurrency(month.total_packaging_cost)}</td>
                <td><strong>${formatCurrency(month.total_production_cost)}</strong></td>
                <td>${month.total_bags_produced}</td>
                <td><strong class="text-success">${formatCurrency(month.avg_cost_per_kg)}</strong></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayYearlyReport(yearlyReports, container) {
    if (!yearlyReports || yearlyReports.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No yearly production data found</div>';
        return;
    }

    let html = `
        <h6 class="mb-3">Yearly Production Cost Summary</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Total Batches</th>
                        <th>Total Yield (KG)</th>
                        <th>Raw Materials Cost</th>
                        <th>Packaging Cost</th>
                        <th>Total Production Cost</th>
                        <th>Bags Produced</th>
                        <th>Avg Cost/KG</th>
                    </tr>
                </thead>
                <tbody>
    `;

    yearlyReports.forEach(year => {
        html += `
            <tr>
                <td><strong>${year.year}</strong></td>
                <td>${year.total_batches}</td>
                <td>${formatNumber(year.total_yield)}</td>
                <td>${formatCurrency(year.total_raw_materials_cost)}</td>
                <td>${formatCurrency(year.total_packaging_cost)}</td>
                <td><strong>${formatCurrency(year.total_production_cost)}</strong></td>
                <td>${year.total_bags_produced}</td>
                <td><strong class="text-success">${formatCurrency(year.avg_cost_per_kg)}</strong></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function displayAllTimeReport(summary, container) {
    if (!summary) {
        container.innerHTML = '<div class="alert alert-info">No all-time production data available</div>';
        return;
    }

    const html = `
        <div class="row">
            <div class="col-md-12">
                <h6 class="mb-3">All-Time Production Summary</h6>
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h3 class="text-primary">${summary.total_batches}</h3>
                                <p class="mb-0">Total Batches</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-success">${formatNumber(summary.total_yield)} KG</h3>
                                <p class="mb-0">Total Production</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-info">${formatCurrency(summary.total_production_cost)}</h3>
                                <p class="mb-0">Total Investment</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-warning">${formatCurrency(summary.avg_cost_per_kg)}</h3>
                                <p class="mb-0">Average Cost/KG</p>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4 class="text-secondary">${formatCurrency(summary.total_raw_materials_cost)}</h4>
                                <p class="mb-0">Raw Materials Cost</p>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-secondary">${formatCurrency(summary.total_packaging_cost)}</h4>
                                <p class="mb-0">Packaging Cost</p>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-secondary">${summary.total_bags_produced}</h4>
                                <p class="mb-0">Total Bags Produced</p>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <p class="mb-0"><strong>Production Period:</strong> ${formatDate(summary.first_production)} to ${formatDate(summary.last_production)}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.innerHTML = html;
}

// Sales Support Functions
function loadBranches() {
    fetch('ajax/get_branches.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateBranchDropdown(data.branches);
        } else {
            console.error('Error loading branches:', data.message);
        }
    })
    .catch(error => {
        console.error('Error loading branches:', error);
    });
}

function populateBranchDropdown(branches) {
    // List of all branch dropdown selectors to populate
    const branchSelectors = [
        'salesBranchSelector',
        'expenseBranchFilter',
        'expenseTypeBranchFilter',
        'stockLevelsBranchFilter',
        'valueAnalysisBranchFilter',
        'movementBranchFilter',
        'lowStockBranchFilter'
    ];

    branchSelectors.forEach(selectorId => {
        const select = document.getElementById(selectorId);
        if (select) {
            select.innerHTML = '<option value="">All Branches</option>';
            branches.forEach(branch => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                select.appendChild(option);
            });
        }
    });
}

function loadBranchPerformance() {
    fetch('ajax/get_branch_performance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayBranchPerformance(data.branches);
        } else {
            document.getElementById('branchPerformance').innerHTML =
                '<div class="alert alert-info">No branch performance data available</div>';
        }
    })
    .catch(error => {
        document.getElementById('branchPerformance').innerHTML =
            '<div class="alert alert-warning">Error loading branch performance</div>';
    });
}

function displayBranchPerformance(branches) {
    const container = document.getElementById('branchPerformance');

    if (!branches || branches.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No branch performance data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Total Sales</th>
                        <th>Total Revenue</th>
                        <th>Avg Sale Value</th>
                        <th>Cash Sales</th>
                        <th>Credit Sales</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
    `;

    branches.forEach(branch => {
        const performanceClass = branch.performance_score >= 80 ? 'success' :
                                branch.performance_score >= 60 ? 'warning' : 'danger';
        html += `
            <tr>
                <td><strong>${branch.branch_name}</strong></td>
                <td>${branch.total_sales}</td>
                <td class="text-success"><strong>${formatCurrency(branch.total_revenue)}</strong></td>
                <td>${formatCurrency(branch.average_sale_value)}</td>
                <td>${formatCurrency(branch.cash_sales)}</td>
                <td>${formatCurrency(branch.credit_sales)}</td>
                <td><span class="badge bg-${performanceClass}">${formatNumber(branch.performance_score)}%</span></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadProductPerformance() {
    fetch('ajax/get_product_performance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayProductPerformance(data.products);
        } else {
            document.getElementById('productPerformance').innerHTML =
                '<div class="alert alert-info">No product performance data available</div>';
        }
    })
    .catch(error => {
        document.getElementById('productPerformance').innerHTML =
            '<div class="alert alert-warning">Error loading product performance</div>';
    });
}

function displayProductPerformance(products) {
    const container = document.getElementById('productPerformance');

    if (!products || products.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No product performance data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Product Type</th>
                        <th>Quantity Sold</th>
                        <th>Revenue Generated</th>
                        <th>Avg Unit Price</th>
                        <th>Sales Count</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
    `;

    products.forEach(product => {
        const performanceClass = product.performance_score >= 80 ? 'success' :
                                product.performance_score >= 60 ? 'warning' : 'danger';
        html += `
            <tr>
                <td><strong>${product.product_name}</strong></td>
                <td><span class="badge bg-secondary">${product.product_type}</span></td>
                <td>${formatNumber(product.total_quantity)} ${product.unit}</td>
                <td class="text-success"><strong>${formatCurrency(product.total_revenue)}</strong></td>
                <td>${formatCurrency(product.average_unit_price)}</td>
                <td>${product.sales_count}</td>
                <td><span class="badge bg-${performanceClass}">${formatNumber(product.performance_score)}%</span></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadPaymentAnalysis() {
    fetch('ajax/get_payment_analysis.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPaymentAnalysis(data.payment_methods, data.credit_vs_cash);
        } else {
            document.getElementById('paymentAnalysis').innerHTML =
                '<div class="alert alert-info">No payment analysis data available</div>';
        }
    })
    .catch(error => {
        document.getElementById('paymentAnalysis').innerHTML =
            '<div class="alert alert-warning">Error loading payment analysis</div>';
    });
}

function displayPaymentAnalysis(paymentMethods, creditVsCash) {
    const container = document.getElementById('paymentAnalysis');

    let html = '<div class="row">';

    // Payment methods breakdown
    if (paymentMethods && paymentMethods.length > 0) {
        html += `
            <div class="col-md-6">
                <h6><i class="bi bi-credit-card"></i> Payment Methods Breakdown</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th>Amount</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        paymentMethods.forEach(method => {
            html += `
                <tr>
                    <td>${method.payment_method}</td>
                    <td>${formatCurrency(method.total_amount)}</td>
                    <td><span class="badge bg-info">${formatNumber(method.percentage)}%</span></td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
    }

    // Credit vs Cash analysis
    if (creditVsCash) {
        html += `
            <div class="col-md-6">
                <h6><i class="bi bi-bar-chart"></i> Credit vs Cash Analysis</h6>
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-success">${formatCurrency(creditVsCash.cash_total || 0)}</h4>
                                <small>Cash/Bank/Mobile</small>
                                <div class="mt-2">
                                    <span class="badge bg-success">${formatNumber(creditVsCash.cash_percentage || 0)}%</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <h4 class="text-warning">${formatCurrency(creditVsCash.credit_total || 0)}</h4>
                                <small>Credit Sales</small>
                                <div class="mt-2">
                                    <span class="badge bg-warning">${formatNumber(creditVsCash.credit_percentage || 0)}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    html += '</div>';

    if (html === '<div class="row"></div>') {
        html = '<div class="alert alert-info">No payment analysis data available</div>';
    }

    container.innerHTML = html;
}

function loadTopCustomers() {
    fetch('ajax/get_top_customers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({limit: 10})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayTopCustomers(data.customers);
        } else {
            document.getElementById('topCustomers').innerHTML =
                '<div class="alert alert-info">No customer data available</div>';
        }
    })
    .catch(error => {
        document.getElementById('topCustomers').innerHTML =
            '<div class="alert alert-warning">Error loading top customers</div>';
    });
}

function displayTopCustomers(customers) {
    const container = document.getElementById('topCustomers');

    if (!customers || customers.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No customer data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Customer</th>
                        <th>Total Purchases</th>
                        <th>Total Spent</th>
                        <th>Avg Order Value</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
    `;

    customers.forEach((customer, index) => {
        html += `
            <tr>
                <td><span class="badge bg-primary">${index + 1}</span></td>
                <td><strong>${customer.customer_name}</strong></td>
                <td>${customer.total_purchases}</td>
                <td class="text-success"><strong>${formatCurrency(customer.total_spent)}</strong></td>
                <td>${formatCurrency(customer.average_order_value)}</td>
                <td><span class="badge bg-info">${customer.branch_name}</span></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Sales Report Functions
function generateSalesReport() {
    const reportType = document.getElementById('salesReportType').value;
    const branchId = document.getElementById('salesBranchSelector').value;
    let startDate = document.getElementById('salesStartDate').value;
    let endDate = document.getElementById('salesEndDate').value;
    const year = document.getElementById('salesYearSelector').value;

    // Set date range based on report type
    let requestData = { report_type: reportType, branch_id: branchId };

    switch (reportType) {
        case 'daily':
            if (!startDate) startDate = new Date().toISOString().split('T')[0];
            requestData.start_date = startDate;
            requestData.end_date = startDate;
            break;
        case 'weekly':
            if (!startDate) {
                const today = new Date();
                const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
                startDate = firstDay.toISOString().split('T')[0];
            }
            requestData.start_date = startDate;
            break;
        case 'custom':
            if (!startDate || !endDate) {
                alert('Please select both start and end dates for custom range');
                return;
            }
            requestData.start_date = startDate;
            requestData.end_date = endDate;
            break;
        case 'monthly':
            if (!year) {
                alert('Please select a year for monthly report');
                return;
            }
            requestData.year = year;
            break;
        case 'yearly':
        case 'all_time':
            // No additional parameters needed
            break;
    }

    document.getElementById('salesReportResults').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2">Generating ${reportType} sales report...</p>
        </div>
    `;

    fetch('ajax/get_sales_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySalesReport(data.report, data.report_type);
        } else {
            document.getElementById('salesReportResults').innerHTML =
                `<div class="alert alert-danger">Error: ${data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error generating sales report:', error);
        document.getElementById('salesReportResults').innerHTML =
            '<div class="alert alert-danger">Error generating sales report</div>';
    });
}

function displaySalesReport(report, reportType) {
    const container = document.getElementById('salesReportResults');

    if (!report) {
        container.innerHTML = '<div class="alert alert-info">No sales data available for the selected criteria</div>';
        return;
    }

    let html = '<div class="sales-report-results">';

    // Display based on report type
    switch (reportType) {
        case 'daily':
        case 'weekly':
        case 'custom':
            if (report.daily_reports && report.daily_reports.length > 0) {
                html += displayDailySalesReport(report.daily_reports, reportType);
                if (report.summary) {
                    html += displaySalesReportSummary(report.summary);
                }
            } else {
                html += '<div class="alert alert-info">No sales data found for the selected date range</div>';
            }
            break;
        case 'monthly':
            if (report.monthly_reports && report.monthly_reports.length > 0) {
                html += displayMonthlySalesReport(report.monthly_reports);
            }
            break;
        case 'yearly':
            if (report.yearly_reports && report.yearly_reports.length > 0) {
                html += displayYearlySalesReport(report.yearly_reports);
            }
            break;
        case 'all_time':
            html += displayAllTimeSalesReport(report);
            break;
    }

    html += '</div>';
    container.innerHTML = html;
}

function displayDailySalesReport(dailyReports, reportType) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar-day"></i> ${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Sales Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Total Sales</th>
                                <th>Total Amount</th>
                                <th>Cash Sales</th>
                                <th>Credit Sales</th>
                                <th>Other Payments</th>
                                <th>Products Sold</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    dailyReports.forEach(report => {
        html += `
            <tr>
                <td><strong>${formatDate(report.sale_date)}</strong></td>
                <td><span class="badge bg-primary">${report.branch_name}</span></td>
                <td>${report.total_sales}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${formatCurrency(report.cash_amount)}</td>
                <td>${formatCurrency(report.credit_amount)}</td>
                <td>${formatCurrency(report.other_amount)}</td>
                <td>${report.total_products_sold}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

function displayMonthlySalesReport(monthlyReports) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar-month"></i> Monthly Sales Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Sales</th>
                                <th>Total Amount</th>
                                <th>Average Daily Sales</th>
                                <th>Cash vs Credit Ratio</th>
                                <th>Growth vs Previous</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    monthlyReports.forEach(report => {
        const cashRatio = report.total_amount > 0 ? (report.cash_amount / report.total_amount * 100) : 0;
        html += `
            <tr>
                <td><strong>${report.month_name} ${report.year}</strong></td>
                <td>${report.total_sales}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${formatCurrency(report.average_daily_sales)}</td>
                <td>
                    <div class="d-flex">
                        <span class="text-success me-2">${formatNumber(cashRatio)}% Cash</span>
                        <span class="text-warning">${formatNumber(100-cashRatio)}% Credit</span>
                    </div>
                </td>
                <td>
                    ${report.growth_percentage ?
                        (report.growth_percentage > 0 ?
                            `<span class="text-success"><i class="bi bi-arrow-up"></i> ${formatNumber(report.growth_percentage)}%</span>` :
                            `<span class="text-danger"><i class="bi bi-arrow-down"></i> ${formatNumber(Math.abs(report.growth_percentage))}%</span>`
                        ) : '-'
                    }
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

function displayYearlySalesReport(yearlyReports) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar4"></i> Yearly Sales Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Total Sales</th>
                                <th>Total Amount</th>
                                <th>Monthly Average</th>
                                <th>Top Branch</th>
                                <th>Growth Rate</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    yearlyReports.forEach(report => {
        html += `
            <tr>
                <td><strong>${report.year}</strong></td>
                <td>${report.total_sales}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${formatCurrency(report.monthly_average)}</td>
                <td><span class="badge bg-info">${report.top_branch || '-'}</span></td>
                <td>
                    ${report.growth_rate ?
                        (report.growth_rate > 0 ?
                            `<span class="text-success"><i class="bi bi-arrow-up"></i> ${formatNumber(report.growth_rate)}%</span>` :
                            `<span class="text-danger"><i class="bi bi-arrow-down"></i> ${formatNumber(Math.abs(report.growth_rate))}%</span>`
                        ) : '-'
                    }
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

function displayAllTimeSalesReport(report) {
    return `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-infinity"></i> All-Time Sales Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white mb-3">
                            <div class="card-body text-center">
                                <h2>${report.total_sales || 0}</h2>
                                <p class="mb-0">Total Sales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white mb-3">
                            <div class="card-body text-center">
                                <h2>${formatCurrency(report.total_revenue || 0)}</h2>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-info text-white mb-3">
                            <div class="card-body text-center">
                                <h2>${formatCurrency(report.average_sale_value || 0)}</h2>
                                <p class="mb-0">Avg Sale Value</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Payment Method Breakdown:</h6>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-cash"></i> Cash: <strong>${formatCurrency(report.cash_sales || 0)}</strong></li>
                            <li><i class="bi bi-credit-card"></i> Credit: <strong>${formatCurrency(report.credit_sales || 0)}</strong></li>
                            <li><i class="bi bi-phone"></i> Mobile Money: <strong>${formatCurrency(report.mobile_money_sales || 0)}</strong></li>
                            <li><i class="bi bi-bank"></i> Bank Transfer: <strong>${formatCurrency(report.bank_transfer_sales || 0)}</strong></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Performance Metrics:</h6>
                        <ul class="list-unstyled">
                            <li>First Sale: <strong>${formatDate(report.first_sale)}</strong></li>
                            <li>Latest Sale: <strong>${formatDate(report.latest_sale)}</strong></li>
                            <li>Active Customers: <strong>${report.active_customers || 0}</strong></li>
                            <li>Products Sold: <strong>${report.products_sold || 0}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function displaySalesReportSummary(summary) {
    return `
        <div class="card">
            <div class="card-header">
                <h6><i class="bi bi-bar-chart"></i> Period Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-primary">${summary.total_sales || 0}</h4>
                            <small>Total Sales</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-success">${formatCurrency(summary.total_amount || 0)}</h4>
                            <small>Total Revenue</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-info">${formatCurrency(summary.daily_average || 0)}</h4>
                            <small>Daily Average</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h4 class="text-warning">${summary.active_branches || 0}</h4>
                            <small>Active Branches</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Utility functions
function formatCurrency(amount) {
    if (!amount || amount === 0) return '0.00 TZS';
    return parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' TZS';
}

function formatNumber(number) {
    if (!number || number === 0) return '0';
    return parseFloat(number).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// EXPENSE REPORTING FUNCTIONS

// Handle expense report type change
document.getElementById('expenseReportType').addEventListener('change', function() {
    const reportType = this.value;
    const startDateDiv = document.getElementById('expenseStartDateDiv');
    const endDateDiv = document.getElementById('expenseEndDateDiv');
    const yearDiv = document.getElementById('expenseYearDiv');
    const monthDiv = document.getElementById('expenseMonthDiv');

    // Hide all date inputs first
    startDateDiv.style.display = 'none';
    endDateDiv.style.display = 'none';
    yearDiv.style.display = 'none';
    monthDiv.style.display = 'none';

    // Show relevant date inputs
    if (reportType === 'daily') {
        startDateDiv.style.display = 'block';
        document.getElementById('expenseStartDate').value = new Date().toISOString().split('T')[0];
    } else if (reportType === 'custom') {
        startDateDiv.style.display = 'block';
        endDateDiv.style.display = 'block';
    } else if (reportType === 'monthly') {
        yearDiv.style.display = 'block';
        monthDiv.style.display = 'block';
    } else if (reportType === 'yearly') {
        yearDiv.style.display = 'block';
    }
});

// Generate expense report
function generateExpenseReport() {
    const reportType = document.getElementById('expenseReportType').value;
    const branchId = document.getElementById('expenseBranchFilter').value;
    let startDate = document.getElementById('expenseStartDate').value;
    let endDate = document.getElementById('expenseEndDate').value;
    const year = document.getElementById('expenseYearSelector').value;
    const month = document.getElementById('expenseMonthSelector').value;

    // Set date range based on report type
    let requestData = { reportType: reportType, branchId: branchId };

    switch (reportType) {
        case 'daily':
            if (!startDate) startDate = new Date().toISOString().split('T')[0];
            requestData.startDate = startDate;
            requestData.endDate = startDate;
            break;
        case 'custom':
            if (!startDate || !endDate) {
                alert('Please select both start and end dates for custom range');
                return;
            }
            requestData.startDate = startDate;
            requestData.endDate = endDate;
            break;
        case 'monthly':
            if (!year || !month) {
                alert('Please select both year and month for monthly report');
                return;
            }
            requestData.year = year;
            requestData.month = month;
            break;
        case 'yearly':
            if (!year) {
                alert('Please select a year for yearly report');
                return;
            }
            requestData.year = year;
            break;
        case 'all-time':
            // No additional parameters needed
            break;
    }

    document.getElementById('expenseReportResults').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2">Generating ${reportType} expense report...</p>
        </div>
    `;

    fetch('ajax/get_expense_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayExpenseReport(data.data, requestData.reportType);
        } else {
            document.getElementById('expenseReportResults').innerHTML =
                `<div class="alert alert-danger">Error: ${data.error || data.message}</div>`;
        }
    })
    .catch(error => {
        console.error('Error generating expense report:', error);
        document.getElementById('expenseReportResults').innerHTML =
            '<div class="alert alert-danger">Error generating expense report</div>';
    });
}

// Display expense report
function displayExpenseReport(report, reportType) {
    const container = document.getElementById('expenseReportResults');

    if (!report) {
        container.innerHTML = '<div class="alert alert-info">No expense data available for the selected criteria</div>';
        return;
    }

    let html = '<div class="expense-report-results">';

    // Display based on report type
    switch (reportType) {
        case 'daily':
        case 'custom':
            if (report.daily_reports && report.daily_reports.length > 0) {
                html += displayDailyExpenseReport(report.daily_reports, reportType);
            } else {
                html += '<div class="alert alert-info">No expense data found for the selected date range</div>';
            }
            break;
        case 'monthly':
            if (report.monthly_reports && report.monthly_reports.length > 0) {
                html += displayMonthlyExpenseReport(report.monthly_reports);
            } else {
                html += '<div class="alert alert-info">No expense data found for the selected year</div>';
            }
            break;
        case 'yearly':
            if (report.yearly_reports && report.yearly_reports.length > 0) {
                html += displayYearlyExpenseReport(report.yearly_reports);
            } else {
                html += '<div class="alert alert-info">No expense data found</div>';
            }
            break;
        case 'all-time':
            if (report.summary) {
                html += displayAllTimeExpenseReport(report.summary);
            } else {
                html += '<div class="alert alert-info">No expense data found</div>';
            }
            break;
    }

    html += '</div>';
    container.innerHTML = html;
}

// Display daily expense report
function displayDailyExpenseReport(dailyReports, reportType) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar-day"></i> ${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Expense Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Total Expenses</th>
                                <th>Total Amount</th>
                                <th>Fleet Expenses</th>
                                <th>Machine Expenses</th>
                                <th>Fleet Amount</th>
                                <th>Machine Amount</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    dailyReports.forEach(report => {
        html += `
            <tr>
                <td><strong>${formatDate(report.expense_date)}</strong></td>
                <td><span class="badge bg-primary">${report.branch_name}</span></td>
                <td>${report.total_expenses}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${report.fleet_expenses}</td>
                <td>${report.machine_expenses}</td>
                <td class="text-info">${formatCurrency(report.fleet_amount)}</td>
                <td class="text-warning">${formatCurrency(report.machine_amount)}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

// Display monthly expense report
function displayMonthlyExpenseReport(monthlyReports) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar-month"></i> Monthly Expense Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Expenses</th>
                                <th>Total Amount</th>
                                <th>Fleet Expenses</th>
                                <th>Machine Expenses</th>
                                <th>Average Amount</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    monthlyReports.forEach(report => {
        html += `
            <tr>
                <td><strong>${report.month_name} ${report.year}</strong></td>
                <td>${report.total_expenses}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${report.fleet_expenses} (${formatCurrency(report.fleet_amount)})</td>
                <td>${report.machine_expenses} (${formatCurrency(report.machine_amount)})</td>
                <td>${formatCurrency(report.avg_expense_amount)}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

// Display yearly expense report
function displayYearlyExpenseReport(yearlyReports) {
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-calendar3"></i> Yearly Expense Report</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th>Total Expenses</th>
                                <th>Total Amount</th>
                                <th>Fleet Expenses</th>
                                <th>Machine Expenses</th>
                                <th>Average Amount</th>
                                <th>Branches</th>
                            </tr>
                        </thead>
                        <tbody>
    `;

    yearlyReports.forEach(report => {
        html += `
            <tr>
                <td><strong>${report.year}</strong></td>
                <td>${report.total_expenses}</td>
                <td class="text-success"><strong>${formatCurrency(report.total_amount)}</strong></td>
                <td>${report.fleet_expenses} (${formatCurrency(report.fleet_amount)})</td>
                <td>${report.machine_expenses} (${formatCurrency(report.machine_amount)})</td>
                <td>${formatCurrency(report.avg_expense_amount)}</td>
                <td>${report.branches_with_expenses}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div></div></div>';
    return html;
}

// Display all-time expense report
function displayAllTimeExpenseReport(summary) {
    return `
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-infinity"></i> All-Time Expense Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">OVERALL STATISTICS</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td>Total Expenses:</td><td><strong>${summary.total_expenses || 0}</strong></td></tr>
                            <tr><td>Total Amount:</td><td><strong class="text-success">${formatCurrency(summary.total_amount || 0)}</strong></td></tr>
                            <tr><td>Average Amount:</td><td><strong>${formatCurrency(summary.avg_expense_amount || 0)}</strong></td></tr>
                            <tr><td>Branches with Expenses:</td><td><strong>${summary.branches_with_expenses || 0}</strong></td></tr>
                            <tr><td>Expense Categories:</td><td><strong>${summary.expense_categories || 0}</strong></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">FLEET & MACHINE BREAKDOWN</h6>
                        <table class="table table-sm table-borderless">
                            <tr><td>Fleet Expenses:</td><td><strong class="text-info">${summary.fleet_expenses || 0}</strong></td></tr>
                            <tr><td>Fleet Amount:</td><td><strong class="text-info">${formatCurrency(summary.fleet_amount || 0)}</strong></td></tr>
                            <tr><td>Machine Expenses:</td><td><strong class="text-warning">${summary.machine_expenses || 0}</strong></td></tr>
                            <tr><td>Machine Amount:</td><td><strong class="text-warning">${formatCurrency(summary.machine_amount || 0)}</strong></td></tr>
                            <tr><td>Period:</td><td><strong>${formatDate(summary.first_expense)} - ${formatDate(summary.latest_expense)}</strong></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Load fleet expense analysis
function loadFleetExpenseAnalysis() {
    let startDate = document.getElementById('fleetExpenseStartDate').value;
    let endDate = document.getElementById('fleetExpenseEndDate').value;

    // Set default dates if not provided (last 30 days)
    if (!startDate || !endDate) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);

        startDate = thirtyDaysAgo.toISOString().split('T')[0];
        endDate = today.toISOString().split('T')[0];

        // Update the input fields
        document.getElementById('fleetExpenseStartDate').value = startDate;
        document.getElementById('fleetExpenseEndDate').value = endDate;
    }

    const vehicleId = document.getElementById('fleetVehicleFilter').value;

    const requestData = {
        startDate: startDate,
        endDate: endDate
    };

    if (vehicleId) {
        requestData.vehicleId = vehicleId;
    }

    fetch('ajax/get_fleet_expense_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayFleetExpenseAnalysis(data.data.fleet_analysis);
        } else {
            document.getElementById('fleetExpenseAnalysis').innerHTML =
                `<div class="alert alert-danger">Error: ${data.error || 'No fleet expense data available'}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('fleetExpenseAnalysis').innerHTML =
            '<div class="alert alert-warning">Error loading fleet expense analysis</div>';
    });
}

// Display fleet expense analysis
function displayFleetExpenseAnalysis(fleetData) {
    const container = document.getElementById('fleetExpenseAnalysis');

    if (!fleetData || fleetData.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No fleet expense data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Type</th>
                        <th>Make/Model</th>
                        <th>Total Expenses</th>
                        <th>Total Amount</th>
                        <th>Average Amount</th>
                        <th>Expense Types</th>
                        <th>Period</th>
                    </tr>
                </thead>
                <tbody>
    `;

    fleetData.forEach(vehicle => {
        html += `
            <tr>
                <td><strong>${vehicle.vehicle_number}</strong></td>
                <td><span class="badge bg-secondary">${vehicle.vehicle_type}</span></td>
                <td>${vehicle.make} ${vehicle.model}</td>
                <td>${vehicle.total_expenses}</td>
                <td class="text-success"><strong>${formatCurrency(vehicle.total_amount)}</strong></td>
                <td>${formatCurrency(vehicle.avg_expense_amount)}</td>
                <td><small>${vehicle.expense_types}</small></td>
                <td><small>${formatDate(vehicle.first_expense)} - ${formatDate(vehicle.latest_expense)}</small></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Load machine expense analysis
function loadMachineExpenseAnalysis() {
    let startDate = document.getElementById('machineExpenseStartDate').value;
    let endDate = document.getElementById('machineExpenseEndDate').value;

    // Set default dates if not provided (last 30 days)
    if (!startDate || !endDate) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);

        startDate = thirtyDaysAgo.toISOString().split('T')[0];
        endDate = today.toISOString().split('T')[0];

        // Update the input fields
        document.getElementById('machineExpenseStartDate').value = startDate;
        document.getElementById('machineExpenseEndDate').value = endDate;
    }

    const machineId = document.getElementById('machineFilter').value;

    const requestData = {
        startDate: startDate,
        endDate: endDate
    };

    if (machineId) {
        requestData.machineId = machineId;
    }

    fetch('ajax/get_machine_expense_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMachineExpenseAnalysis(data.data.machine_analysis);
        } else {
            document.getElementById('machineExpenseAnalysis').innerHTML =
                `<div class="alert alert-danger">Error: ${data.error || 'No machine expense data available'}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('machineExpenseAnalysis').innerHTML =
            '<div class="alert alert-warning">Error loading machine expense analysis</div>';
    });
}

// Display machine expense analysis
function displayMachineExpenseAnalysis(machineData) {
    const container = document.getElementById('machineExpenseAnalysis');

    if (!machineData || machineData.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No machine expense data available</div>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Machine</th>
                        <th>Type</th>
                        <th>Name/Brand</th>
                        <th>Department</th>
                        <th>Total Expenses</th>
                        <th>Total Amount</th>
                        <th>Average Amount</th>
                        <th>Expense Types</th>
                    </tr>
                </thead>
                <tbody>
    `;

    machineData.forEach(machine => {
        html += `
            <tr>
                <td><strong>${machine.machine_number}</strong></td>
                <td><span class="badge bg-secondary">${machine.machine_type}</span></td>
                <td>${machine.machine_name}<br><small class="text-muted">${machine.brand}</small></td>
                <td><span class="badge bg-info">${machine.department}</span></td>
                <td>${machine.total_expenses}</td>
                <td class="text-success"><strong>${formatCurrency(machine.total_amount)}</strong></td>
                <td>${formatCurrency(machine.avg_expense_amount)}</td>
                <td><small>${machine.expense_types}</small></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Display expense type analysis
function displayExpenseTypeAnalysis(expenseTypes) {
    const container = document.getElementById('expenseTypeAnalysis');

    if (!expenseTypes || expenseTypes.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No expense type data available for the selected period</div>';
        return;
    }

    let html = `
        <h6 class="mb-3">Expense Type Analysis</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Expense Type</th>
                        <th>Category</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                        <th>Avg Amount</th>
                        <th>Min Amount</th>
                        <th>Max Amount</th>
                        <th>Fleet Related</th>
                        <th>Machine Related</th>
                        <th>Branches Used</th>
                    </tr>
                </thead>
                <tbody>
    `;

    expenseTypes.forEach(type => {
        html += `
            <tr>
                <td><strong>${type.expense_type}</strong></td>
                <td><span class="badge bg-primary">${type.category}</span></td>
                <td>${type.total_count}</td>
                <td class="text-success"><strong>${formatCurrency(type.total_amount)}</strong></td>
                <td>${formatCurrency(type.avg_amount)}</td>
                <td>${formatCurrency(type.min_amount)}</td>
                <td>${formatCurrency(type.max_amount)}</td>
                <td>${type.fleet_related || 0}</td>
                <td>${type.machine_related || 0}</td>
                <td>${type.branches_used}</td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// Load expense type analysis
function loadExpenseTypeAnalysis() {
    let startDate = document.getElementById('expenseTypeStartDate').value;
    let endDate = document.getElementById('expenseTypeEndDate').value;
    const branchId = document.getElementById('expenseTypeBranchFilter').value;

    // Set default dates if not provided (last 30 days)
    if (!startDate || !endDate) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);

        startDate = thirtyDaysAgo.toISOString().split('T')[0];
        endDate = today.toISOString().split('T')[0];

        // Update the input fields
        document.getElementById('expenseTypeStartDate').value = startDate;
        document.getElementById('expenseTypeEndDate').value = endDate;
    }

    const requestData = {
        startDate: startDate,
        endDate: endDate
    };
    if (branchId) {
        requestData.branchId = branchId;
    }

    fetch('ajax/get_expense_type_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayExpenseTypeAnalysis(data.data.expense_type_analysis);
        } else {
            document.getElementById('expenseTypeAnalysis').innerHTML =
                `<div class="alert alert-danger">Error: ${data.error || 'No expense type data available'}</div>`;
        }
    })
    .catch(error => {
        document.getElementById('expenseTypeAnalysis').innerHTML =
            '<div class="alert alert-warning">Error loading expense type analysis</div>';
    });
}

// Load fleet vehicles and machines for dropdown filters
function loadFleetAndMachines() {
    fetch('ajax/get_fleet_machines.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate vehicle dropdown
            const vehicleSelect = document.getElementById('fleetVehicleFilter');
            if (vehicleSelect) {
                vehicleSelect.innerHTML = '<option value="">All Vehicles</option>';
                data.vehicles.forEach(vehicle => {
                    vehicleSelect.innerHTML += `<option value="${vehicle.id}">${vehicle.vehicle_number} - ${vehicle.make} ${vehicle.model} (${vehicle.vehicle_type})</option>`;
                });
            }

            // Populate machine dropdown
            const machineSelect = document.getElementById('machineFilter');
            if (machineSelect) {
                machineSelect.innerHTML = '<option value="">All Machines</option>';
                data.machines.forEach(machine => {
                    machineSelect.innerHTML += `<option value="${machine.id}">${machine.machine_number} - ${machine.machine_name} (${machine.machine_type})</option>`;
                });
            }
        }
    })
    .catch(error => {
        console.error('Error loading fleet and machines:', error);
    });
}

// ================================================================================
// INVENTORY REPORTING FUNCTIONS
// ================================================================================

function loadInventoryStockLevels() {
    const branchId = document.getElementById('stockLevelsBranchFilter').value;

    document.getElementById('inventoryStockLevelsResults').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading inventory stock levels...</p></div>';

    fetch('ajax/get_inventory_stock_levels.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            branch_id: branchId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayInventoryStockLevels(data.data);
        } else {
            document.getElementById('inventoryStockLevelsResults').innerHTML =
                `<div class="alert alert-warning">Error: ${data.error || 'No inventory stock data available'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading inventory stock levels:', error);
        document.getElementById('inventoryStockLevelsResults').innerHTML =
            '<div class="alert alert-danger">Error loading inventory stock levels</div>';
    });
}

function displayInventoryStockLevels(stockData) {
    const container = document.getElementById('inventoryStockLevelsResults');

    if (!stockData || (!stockData.raw_materials?.length && !stockData.finished_products?.length &&
                      !stockData.third_party_products?.length && !stockData.packaging_materials?.length)) {
        container.innerHTML = '<div class="alert alert-info">No inventory stock data available</div>';
        return;
    }

    let html = '<div class="row">';

    // Raw Materials
    if (stockData.raw_materials && stockData.raw_materials.length > 0) {
        html += `
            <div class="col-12 mb-4">
                <h6 class="mb-3"><i class="bi bi-boxes text-primary"></i> Raw Materials Stock</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Total Cost Value</th>
                                <th>Total Sell Value</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        stockData.raw_materials.forEach(item => {
            const statusClass = item.stock_status === 'LOW' ? 'badge bg-danger' :
                               item.stock_status === 'MEDIUM' ? 'badge bg-warning' : 'badge bg-success';
            const stockPercentage = item.minimum_stock > 0 ? (item.current_stock / item.minimum_stock * 100) : 100;

            html += `
                <tr>
                    <td><strong>${item.name}</strong></td>
                    <td>
                        <span class="badge bg-info">${formatNumber(item.current_stock)} ${item.unit_of_measure}</span>
                        ${stockPercentage < 100 ? `<div class="progress mt-1" style="height: 3px;"><div class="progress-bar bg-danger" style="width: ${stockPercentage}%"></div></div>` : ''}
                    </td>
                    <td>${formatNumber(item.minimum_stock)} ${item.unit_of_measure}</td>
                    <td><span class="${statusClass}">${item.stock_status}</span></td>
                    <td>${formatCurrency(item.cost_price)}</td>
                    <td>${formatCurrency(item.selling_price || 0)}</td>
                    <td><strong>${formatCurrency(item.total_cost_value)}</strong></td>
                    <td><strong>${formatCurrency(item.total_sell_value)}</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
    }

    // Finished Products
    if (stockData.finished_products && stockData.finished_products.length > 0) {
        html += `
            <div class="col-12 mb-4">
                <h6 class="mb-3"><i class="bi bi-box-seam text-success"></i> Finished Products Stock</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Sealed Bags</th>
                                <th>Opened Bags (KG)</th>
                                <th>Total Weight (KG)</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        stockData.finished_products.forEach(item => {
            html += `
                <tr>
                    <td><strong>${item.name}</strong> (${item.package_size}KG)</td>
                    <td><span class="badge bg-primary">${formatNumber(item.sealed_bags)} bags</span></td>
                    <td><span class="badge bg-warning">${formatNumber(item.opened_weight)} KG</span></td>
                    <td><strong>${formatNumber(item.total_weight)} KG</strong></td>
                    <td>${formatCurrency(item.cost_price_per_kg)}</td>
                    <td>${formatCurrency(item.selling_price_per_kg)}</td>
                    <td><strong>${formatCurrency(item.total_value)}</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
    }

    // Third Party Products
    if (stockData.third_party_products && stockData.third_party_products.length > 0) {
        html += `
            <div class="col-12 mb-4">
                <h6 class="mb-3"><i class="bi bi-shop text-warning"></i> Third Party Products Stock</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        stockData.third_party_products.forEach(item => {
            const statusClass = item.stock_status === 'LOW' ? 'badge bg-danger' :
                               item.stock_status === 'MEDIUM' ? 'badge bg-warning' : 'badge bg-success';

            html += `
                <tr>
                    <td><strong>${item.name}</strong></td>
                    <td><span class="badge bg-secondary">${item.brand}</span></td>
                    <td><span class="badge bg-info">${formatNumber(item.current_stock)} ${item.unit_of_measure}</span></td>
                    <td>${formatNumber(item.minimum_stock)} ${item.unit_of_measure}</td>
                    <td><span class="${statusClass}">${item.stock_status}</span></td>
                    <td>${formatCurrency(item.cost_price)}</td>
                    <td>${formatCurrency(item.selling_price)}</td>
                    <td><strong>${formatCurrency(item.total_value)}</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
    }

    // Packaging Materials
    if (stockData.packaging_materials && stockData.packaging_materials.length > 0) {
        html += `
            <div class="col-12 mb-4">
                <h6 class="mb-3"><i class="bi bi-box text-info"></i> Packaging Materials Stock</h6>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Current Stock</th>
                                <th>Min Stock</th>
                                <th>Status</th>
                                <th>Unit Cost</th>
                                <th>Total Value</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        stockData.packaging_materials.forEach(item => {
            const statusClass = item.stock_status === 'LOW' ? 'badge bg-danger' :
                               item.stock_status === 'MEDIUM' ? 'badge bg-warning' : 'badge bg-success';

            html += `
                <tr>
                    <td><strong>${item.name}</strong></td>
                    <td><span class="badge bg-info">${formatNumber(item.current_stock)} ${item.unit}</span></td>
                    <td>${formatNumber(item.minimum_stock)} ${item.unit}</td>
                    <td><span class="${statusClass}">${item.stock_status}</span></td>
                    <td>${formatCurrency(item.unit_cost)}</td>
                    <td><strong>${formatCurrency(item.total_value)}</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

function loadInventoryValueAnalysis() {
    const branchId = document.getElementById('valueAnalysisBranchFilter').value;

    document.getElementById('inventoryValueAnalysis').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading inventory value analysis...</p></div>';

    fetch('ajax/get_inventory_value_analysis.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            branch_id: branchId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayInventoryValueAnalysis(data.data);
        } else {
            document.getElementById('inventoryValueAnalysis').innerHTML =
                `<div class="alert alert-warning">Error: ${data.error || 'No inventory value data available'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading inventory value analysis:', error);
        document.getElementById('inventoryValueAnalysis').innerHTML =
            '<div class="alert alert-danger">Error loading inventory value analysis</div>';
    });
}

function displayInventoryValueAnalysis(valueData) {
    const container = document.getElementById('inventoryValueAnalysis');

    if (!valueData) {
        container.innerHTML = '<div class="alert alert-info">No inventory value data available</div>';
        return;
    }

    let html = `
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-currency-dollar"></i> Total Cost Value</h5>
                        <h3>${formatCurrency(valueData.summary?.total_cost_value || 0)}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-currency-exchange"></i> Total Selling Value</h5>
                        <h3>${formatCurrency(valueData.summary?.total_selling_value || 0)}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-graph-up-arrow"></i> Potential Profit</h5>
                        <h3>${formatCurrency((valueData.summary?.total_selling_value || 0) - (valueData.summary?.total_cost_value || 0))}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-percent"></i> Profit Margin</h5>
                        <h3>${valueData.summary?.total_cost_value > 0 ? formatNumber(((valueData.summary.total_selling_value - valueData.summary.total_cost_value) / valueData.summary.total_cost_value * 100)) : 0}%</h3>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Category breakdown
    if (valueData.categories && valueData.categories.length > 0) {
        html += `
            <h6 class="mb-3"><i class="bi bi-pie-chart"></i> Value Analysis by Category</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items Count</th>
                            <th>Total Cost Value</th>
                            <th>Total Selling Value</th>
                            <th>Potential Profit</th>
                            <th>Profit Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        valueData.categories.forEach(category => {
            const profit = category.total_selling_value - category.total_cost_value;
            const marginPercent = category.total_cost_value > 0 ? (profit / category.total_cost_value * 100) : 0;
            const marginClass = marginPercent > 30 ? 'text-success' : marginPercent > 15 ? 'text-warning' : 'text-danger';

            html += `
                <tr>
                    <td><strong>${category.category}</strong></td>
                    <td><span class="badge bg-secondary">${category.items_count}</span></td>
                    <td>${formatCurrency(category.total_cost_value)}</td>
                    <td>${formatCurrency(category.total_selling_value)}</td>
                    <td><strong>${formatCurrency(profit)}</strong></td>
                    <td class="${marginClass}"><strong>${formatNumber(marginPercent)}%</strong></td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
    }

    container.innerHTML = html;
}

function loadInventoryMovements() {
    const startDate = document.getElementById('movementStartDate').value;
    const endDate = document.getElementById('movementEndDate').value;
    const branchId = document.getElementById('movementBranchFilter').value;
    const productType = document.getElementById('movementProductTypeFilter').value;

    document.getElementById('inventoryMovements').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading inventory movements...</p></div>';

    fetch('ajax/get_inventory_movements.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            start_date: startDate || null,
            end_date: endDate || null,
            branch_id: branchId || null,
            product_type: productType || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayInventoryMovements(data.data);
        } else {
            document.getElementById('inventoryMovements').innerHTML =
                `<div class="alert alert-warning">Error: ${data.error || 'No inventory movement data available'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading inventory movements:', error);
        document.getElementById('inventoryMovements').innerHTML =
            '<div class="alert alert-danger">Error loading inventory movements</div>';
    });
}

function displayInventoryMovements(movementData) {
    const container = document.getElementById('inventoryMovements');

    if (!movementData || !movementData.movements || movementData.movements.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No inventory movements found for the selected criteria</div>';
        return;
    }

    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Movements</h5>
                        <h3>${movementData.summary?.total_movements || 0}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Stock Additions</h5>
                        <h3>${movementData.summary?.stock_additions || 0}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Stock Deductions</h5>
                        <h3>${movementData.summary?.stock_deductions || 0}</h3>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-3"><i class="bi bi-arrow-left-right"></i> Inventory Movement Details</h6>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Movement Type</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Branch</th>
                        <th>Reference</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
    `;

    movementData.movements.forEach(movement => {
        const movementTypeClass = movement.movement_type === 'IN' ? 'badge bg-success' : 'badge bg-danger';
        const movementIcon = movement.movement_type === 'IN' ? 'bi-plus-circle' : 'bi-dash-circle';

        html += `
            <tr>
                <td>${formatDate(movement.movement_date)}</td>
                <td><strong>${movement.product_name}</strong></td>
                <td><span class="badge bg-secondary">${movement.product_type}</span></td>
                <td><span class="${movementTypeClass}"><i class="bi ${movementIcon}"></i> ${movement.movement_type}</span></td>
                <td><strong>${formatNumber(movement.quantity)}</strong></td>
                <td>${movement.unit}</td>
                <td><span class="badge bg-info">${movement.branch_name}</span></td>
                <td><small>${movement.reference_type}</small></td>
                <td><small>${movement.user_name}</small></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function loadLowStockAlerts() {
    const branchId = document.getElementById('lowStockBranchFilter').value;

    document.getElementById('lowStockAlerts').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Loading low stock alerts...</p></div>';

    fetch('ajax/get_low_stock_alerts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            branch_id: branchId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayLowStockAlerts(data.data);
        } else {
            document.getElementById('lowStockAlerts').innerHTML =
                `<div class="alert alert-warning">Error: ${data.error || 'No low stock alerts available'}</div>`;
        }
    })
    .catch(error => {
        console.error('Error loading low stock alerts:', error);
        document.getElementById('lowStockAlerts').innerHTML =
            '<div class="alert alert-danger">Error loading low stock alerts</div>';
    });
}

function displayLowStockAlerts(alertData) {
    const container = document.getElementById('lowStockAlerts');

    if (!alertData || !alertData.alerts || alertData.alerts.length === 0) {
        container.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> No low stock alerts - All inventory levels are adequate!</div>';
        return;
    }

    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Critical Alerts</h5>
                        <h3>${alertData.summary?.critical_alerts || 0}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h5 class="card-title">Low Stock Items</h5>
                        <h3>${alertData.summary?.low_stock_items || 0}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Reorder Cost</h5>
                        <h3>${formatCurrency(alertData.summary?.total_reorder_cost || 0)}</h3>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-3"><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock Alert Details</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Current Stock</th>
                        <th>Minimum Required</th>
                        <th>Shortage</th>
                        <th>Status</th>
                        <th>Reorder Cost</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
    `;

    alertData.alerts.forEach(alert => {
        const statusClass = alert.alert_level === 'CRITICAL' ? 'badge bg-danger' : 'badge bg-warning';
        const stockPercentage = alert.minimum_stock > 0 ? (alert.current_stock / alert.minimum_stock * 100) : 0;

        html += `
            <tr>
                <td><strong>${alert.product_name}</strong></td>
                <td><span class="badge bg-secondary">${alert.product_type}</span></td>
                <td>
                    <span class="badge bg-info">${formatNumber(alert.current_stock)} ${alert.unit}</span>
                    <div class="progress mt-1" style="height: 3px;">
                        <div class="progress-bar ${alert.alert_level === 'CRITICAL' ? 'bg-danger' : 'bg-warning'}" style="width: ${Math.min(stockPercentage, 100)}%"></div>
                    </div>
                </td>
                <td>${formatNumber(alert.minimum_stock)} ${alert.unit}</td>
                <td><strong class="text-danger">${formatNumber(alert.shortage_quantity)} ${alert.unit}</strong></td>
                <td><span class="${statusClass}">${alert.alert_level}</span></td>
                <td><strong>${formatCurrency(alert.reorder_cost)}</strong></td>
                <td><span class="badge bg-info">${alert.branch_name}</span></td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// P&L Report Type Change Handler
document.getElementById('plReportType').addEventListener('change', function() {
    const reportType = this.value;
    const startDateDiv = document.getElementById('plStartDateDiv');
    const endDateDiv = document.getElementById('plEndDateDiv');
    const yearDiv = document.getElementById('plYearDiv');

    // Hide all date inputs first
    startDateDiv.style.display = 'none';
    endDateDiv.style.display = 'none';
    yearDiv.style.display = 'none';

    // Show relevant date inputs
    if (reportType === 'custom') {
        startDateDiv.style.display = 'block';
        endDateDiv.style.display = 'block';
    } else if (reportType === 'yearly') {
        yearDiv.style.display = 'block';
    }
});

// Generate P&L Report
function generatePLReport() {
    const reportType = document.getElementById('plReportType').value;
    const startDate = document.getElementById('plStartDate').value;
    const endDate = document.getElementById('plEndDate').value;
    const year = document.getElementById('plYearSelector').value;

    // Validate custom date range
    if (reportType === 'custom' && (!startDate || !endDate)) {
        alert('Please select both start and end dates for custom range.');
        return;
    }

    const resultsContainer = document.getElementById('plReportResults');
    resultsContainer.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2">Generating comprehensive P&L report...</p>
        </div>
    `;

    // Prepare request data
    const requestData = {
        report_type: reportType,
        start_date: startDate,
        end_date: endDate,
        year: year
    };

    fetch('ajax/get_profit_loss_report.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('P&L Response:', data);
        if (data.success) {
            console.log('P&L Data:', data.data);
            displayPLReport(data.data);
        } else {
            resultsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error: ${data.error || 'Failed to generate P&L report'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultsContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> Error loading P&L report data
            </div>
        `;
    });
}

// Display P&L Report
function displayPLReport(data) {
    const resultsContainer = document.getElementById('plReportResults');
    const summaryCards = document.getElementById('financialSummaryCards');

    // Update summary cards
    document.getElementById('totalRevenue').textContent = formatCurrency(data.revenue.total_revenue);
    document.getElementById('totalCOGS').textContent = formatCurrency(data.cogs.total_cogs);
    document.getElementById('totalOperatingExpenses').textContent = formatCurrency(data.operating_expenses.total_expenses);
    document.getElementById('netProfit').textContent = formatCurrency(data.net_profit);

    // Update card colors based on profit/loss
    const netProfitCard = document.getElementById('netProfit').closest('.card');
    if (data.net_profit >= 0) {
        netProfitCard.className = 'card bg-success text-white';
    } else {
        netProfitCard.className = 'card bg-danger text-white';
    }

    // Show summary cards
    summaryCards.style.display = 'block';

    // Generate detailed P&L report
    let html = `
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Comprehensive Profit & Loss Statement</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Financial Category</th>
                                        <th class="text-end">Amount (TZS)</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- REVENUE SECTION -->
                                    <tr class="table-success">
                                        <td><strong><i class="bi bi-cash-stack"></i> REVENUE</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.revenue.total_revenue)}</strong></td>
                                        <td class="text-end"><strong>100.0%</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Cash Sales</td>
                                        <td class="text-end">${formatCurrency(data.revenue.cash_sales)}</td>
                                        <td class="text-end">${data.revenue.total_revenue > 0 ? ((data.revenue.cash_sales / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Credit Sales</td>
                                        <td class="text-end">${formatCurrency(data.revenue.credit_sales)}</td>
                                        <td class="text-end">${data.revenue.total_revenue > 0 ? ((data.revenue.credit_sales / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</td>
                                    </tr>

                                    <!-- COGS SECTION -->
                                    <tr class="table-warning">
                                        <td><strong><i class="bi bi-box-seam"></i> COST OF GOODS SOLD</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.cogs.total_cogs)}</strong></td>
                                        <td class="text-end"><strong>${data.revenue.total_revenue > 0 ? ((data.cogs.total_cogs / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Production Costs</td>
                                        <td class="text-end">${formatCurrency(data.cogs.production_costs)}</td>
                                        <td class="text-end">${data.revenue.total_revenue > 0 ? ((data.cogs.production_costs / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</td>
                                    </tr>
                                    <tr>
                                        <td class="ps-4">Raw Materials Purchases</td>
                                        <td class="text-end">${formatCurrency(data.cogs.purchase_costs)}</td>
                                        <td class="text-end">${data.revenue.total_revenue > 0 ? ((data.cogs.purchase_costs / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</td>
                                    </tr>

                                    <!-- GROSS PROFIT -->
                                    <tr class="table-info">
                                        <td><strong><i class="bi bi-graph-up"></i> GROSS PROFIT</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.gross_profit)}</strong></td>
                                        <td class="text-end"><strong>${data.gross_profit_margin}%</strong></td>
                                    </tr>

                                    <!-- OPERATING EXPENSES -->
                                    <tr class="table-secondary">
                                        <td><strong><i class="bi bi-receipt"></i> OPERATING EXPENSES</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.operating_expenses.total_expenses)}</strong></td>
                                        <td class="text-end"><strong>${data.revenue.total_revenue > 0 ? ((data.operating_expenses.total_expenses / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</strong></td>
                                    </tr>
    `;

    // Add operating expense breakdown
    if (data.operating_expenses.breakdown) {
        Object.entries(data.operating_expenses.breakdown).forEach(([category, amount]) => {
            if (amount > 0) {
                html += `
                    <tr>
                        <td class="ps-4">${category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                        <td class="text-end">${formatCurrency(amount)}</td>
                        <td class="text-end">${data.revenue.total_revenue > 0 ? ((amount / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</td>
                    </tr>
                `;
            }
        });
    }

    // Continue with payroll and final calculations
    html += `
                                    <!-- PAYROLL EXPENSES -->
                                    <tr class="table-light">
                                        <td><strong><i class="bi bi-people-fill"></i> PAYROLL EXPENSES</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.payroll_expenses.total_payroll)}</strong></td>
                                        <td class="text-end"><strong>${data.revenue.total_revenue > 0 ? ((data.payroll_expenses.total_payroll / data.revenue.total_revenue) * 100).toFixed(1) : 0.0}%</strong></td>
                                    </tr>

                                    <!-- OPERATING PROFIT -->
                                    <tr class="table-warning">
                                        <td><strong><i class="bi bi-calculator"></i> OPERATING PROFIT</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.operating_profit)}</strong></td>
                                        <td class="text-end"><strong>${data.operating_profit_margin}%</strong></td>
                                    </tr>

                                    <!-- NET PROFIT -->
                                    <tr class="${data.net_profit >= 0 ? 'table-success' : 'table-danger'}">
                                        <td><strong><i class="bi bi-trophy-fill"></i> NET PROFIT/LOSS</strong></td>
                                        <td class="text-end"><strong>${formatCurrency(data.net_profit)}</strong></td>
                                        <td class="text-end"><strong>${data.net_profit_margin}%</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Additional Financial Metrics -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="bi bi-bar-chart"></i> Key Performance Indicators</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6 text-center">
                                                <h5 class="text-primary">${data.gross_profit_margin}%</h5>
                                                <small class="text-muted">Gross Profit Margin</small>
                                            </div>
                                            <div class="col-6 text-center">
                                                <h5 class="text-info">${data.operating_profit_margin}%</h5>
                                                <small class="text-muted">Operating Profit Margin</small>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-12 text-center">
                                                <h5 class="${data.net_profit >= 0 ? 'text-success' : 'text-danger'}">${data.net_profit_margin}%</h5>
                                                <small class="text-muted">Net Profit Margin</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6><i class="bi bi-info-circle"></i> Business Performance Analysis</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert ${data.net_profit >= 0 ? 'alert-success' : 'alert-danger'} mb-0">
                                            <strong>${data.net_profit >= 0 ? 'PROFITABLE' : 'LOSS-MAKING'} OPERATIONS</strong><br>
                                            ${data.net_profit >= 0 ?
                                                'Business is generating positive returns. Focus on scaling operations and maintaining efficiency.' :
                                                'Business is operating at a loss. Review cost structure, pricing strategy, and operational efficiency.'
                                            }
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    resultsContainer.innerHTML = html;
}

// Initialize page data when DOM is loaded
window.addEventListener('DOMContentLoaded', function() {
    loadFleetAndMachines();
    loadBranches();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>