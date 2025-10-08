<?php
// File: dashboards/branch_dashboard.php
// Branch Operator dashboard for JM Animal Feeds ERP System
// Branch-specific operations and sales management access

$pageTitle = 'Branch Dashboard';
include 'includes/header.php';

// Get branch-specific data
try {
    $db = getDbConnection();

    // Initialize controllers
    require_once __DIR__ . '/../controllers/SalesController.php';
    require_once __DIR__ . '/../controllers/InventoryController.php';

    $salesController = new SalesController();
    $inventoryController = new InventoryController();
    $branchId = $_SESSION['branch_id'];

    // Get real branch statistics
    $branchStats = $salesController->getBranchDashboardStats($branchId);

    // Get recent sales data
    $recentSalesRaw = $salesController->getRecentSales($branchId, 5);
    $recentSales = [];

    foreach ($recentSalesRaw as $sale) {
        $recentSales[] = [
            'id' => $sale['sale_number'] ?? 'SALE-' . $sale['id'],
            'customer' => $sale['customer_name'] ?? 'Walk-in Customer',
            'customer_number' => $sale['customer_number'] ?? '',
            'total_amount' => $sale['final_amount'] ?? $sale['total_amount'],
            'total_quantity' => $sale['total_quantity'] ?? 0,
            'payment_status' => $sale['status'] ?? 'COMPLETED',
            'time' => date('H:i', strtotime($sale['created_at']))
        ];
    }

    // Get inventory levels at branch
    $inventoryLevelsRaw = $salesController->getBranchInventoryLevels($branchId);
    $inventoryLevels = [];

    foreach ($inventoryLevelsRaw as $item) {
        $currentStock = $item['current_stock'] ?? 0;
        $maxCapacity = $item['max_capacity'] ?? 1;
        $percentage = $maxCapacity > 0 ? ($currentStock / $maxCapacity) * 100 : 0;

        $status = 'good';
        if ($percentage < 20) {
            $status = 'critical';
        } elseif ($percentage < 50) {
            $status = 'low';
        }

        $inventoryLevels[] = [
            'product' => $item['product_name'],
            'current' => round($currentStock, 2),
            'capacity' => round($maxCapacity, 2),
            'status' => $status
        ];
    }

    // If no inventory data, show message
    if (empty($inventoryLevels)) {
        $inventoryLevels = [
            ['product' => 'No inventory data', 'current' => 0, 'capacity' => 1, 'status' => 'info']
        ];
    }

} catch (Exception $e) {
    $error = "Failed to load branch data: " . $e->getMessage();

    // Fallback data in case of error
    $branchStats = [
        'daily_sales' => 0, 'daily_revenue' => 0, 'daily_sales_count' => 0,
        'monthly_sales' => 0, 'monthly_revenue' => 0, 'monthly_sales_count' => 0,
        'customers_served_today' => 0, 'total_customers' => 0
    ];
    $recentSales = [];
    $inventoryLevels = [];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-building text-info"></i> <?php echo htmlspecialchars($currentUser['branch_name']); ?> Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
        <a href="<?php echo BASE_URL; ?>/sales/pos.php" class="btn btn-sm btn-info">
            <i class="bi bi-plus-circle"></i> New Sale
        </a>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-building"></i>
    <strong>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    Manage your branch operations, sales, and inventory efficiently.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Branch Statistics -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Today's Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $branchStats['daily_sales']; ?> kg
                        </div>
                        <div class="text-xs text-success">
                            TZS <?php echo number_format($branchStats['daily_revenue']); ?>
                            (<?php echo $branchStats['daily_sales_count']; ?> sales)
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-cart-check text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Monthly Sales</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $branchStats['monthly_sales']; ?> kg
                        </div>
                        <div class="text-xs text-primary">
                            TZS <?php echo number_format($branchStats['monthly_revenue']); ?>
                            (<?php echo $branchStats['monthly_sales_count']; ?> sales)
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-graph-up text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Customer Statistics</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $branchStats['customers_served_today']; ?>
                        </div>
                        <div class="text-xs text-info">
                            Served this month | <?php echo $branchStats['total_customers']; ?> total active
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row">
    <!-- Recent Sales -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-receipt"></i> Recent Sales
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sale ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentSales)): ?>
                                <?php foreach ($recentSales as $sale): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($sale['id']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($sale['customer']); ?>
                                            <?php if (!empty($sale['customer_number'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($sale['customer_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>Mixed Items</td>
                                        <td><?php echo number_format($sale['total_quantity'], 2); ?> kg</td>
                                        <td><strong>TZS <?php echo number_format($sale['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                echo $sale['payment_status'] === 'PAID' ? 'success' :
                                                    ($sale['payment_status'] === 'PARTIAL' ? 'warning' : 'danger');
                                            ?>">
                                                <?php echo htmlspecialchars($sale['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['time']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewSaleDetails(<?php echo $sale['id']; ?>)">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">
                                        <i class="bi bi-cart-x"></i> No recent sales found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Branch Inventory Status -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-box"></i> Inventory Status
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($inventoryLevels as $item): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between">
                            <span><?php echo htmlspecialchars($item['product']); ?></span>
                            <span class="badge bg-<?php
                                echo $item['status'] === 'good' ? 'success' :
                                    ($item['status'] === 'low' ? 'warning' : 'danger');
                            ?>">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </div>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-<?php
                                echo $item['status'] === 'good' ? 'success' :
                                    ($item['status'] === 'low' ? 'warning' : 'danger');
                            ?>" style="width: <?php echo ($item['current'] / $item['capacity']) * 100; ?>%">
                            </div>
                        </div>
                        <small class="text-muted">
                            <?php echo $item['current']; ?> / <?php echo $item['capacity']; ?> tons
                        </small>
                    </div>
                <?php endforeach; ?>

                <div class="d-grid gap-2 mt-3">
                    <a href="<?php echo BASE_URL; ?>/requests/index.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left-right"></i> Request Stock
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Customer Management -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-lightning"></i> Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/sales/pos.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-plus-circle d-block mb-1" style="font-size: 1.5rem;"></i>
                            New Sale
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/inventory/index.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-boxes d-block mb-1" style="font-size: 1.5rem;"></i>
                            Inventory
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/requests/index.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-arrow-left-right d-block mb-1" style="font-size: 1.5rem;"></i>
                            Stock Requests
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/sales/customers.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-people-fill d-block mb-1" style="font-size: 1.5rem;"></i>
                            Customers
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/sales/orders.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-clipboard-check d-block mb-1" style="font-size: 1.5rem;"></i>
                            Orders
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/reports/index.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-graph-up d-block mb-1" style="font-size: 1.5rem;"></i>
                            Reports
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="<?php echo BASE_URL; ?>/purchases/index.php" class="btn btn-outline-dark w-100">
                            <i class="bi bi-cart-plus d-block mb-1" style="font-size: 1.5rem;"></i>
                            Purchases
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Summary -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-trophy"></i> Branch Performance
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Monthly Sales Target</span>
                        <span>84%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: 84%"></div>
                    </div>
                    <small class="text-muted">1,250.8 / 1,500 tons</small>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Customer Satisfaction</span>
                        <span>92%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-info" style="width: 92%"></div>
                    </div>
                    <small class="text-muted">Based on customer feedback</small>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span>Inventory Efficiency</span>
                        <span>78%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: 78%"></div>
                    </div>
                    <small class="text-muted">Stock rotation and management</small>
                </div>

                <div class="text-center mt-3">
                    <span class="badge bg-success">Excellent Performance</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewSaleDetails(saleId) {
    // Redirect to sales detail page or show modal
    window.location.href = '<?php echo BASE_URL; ?>/sales/pos.php?view_sale=' + saleId;
}
</script>

<?php include 'includes/footer.php'; ?>