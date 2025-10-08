<?php
// File: dashboards/production_dashboard.php
// Production dashboard for JM Animal Feeds ERP System
// Production planning and monitoring access

$pageTitle = 'Production Dashboard';
include 'includes/header.php';

// Get production-specific data
try {
    $db = getDbConnection();

    // Production metrics (placeholder data)
    $todayProduction = [
        'broiler_feed' => 45.2,
        'layer_feed' => 32.8,
        'fish_feed' => 28.5,
        'total' => 106.5
    ];

    $productionTargets = [
        'broiler_feed' => 50,
        'layer_feed' => 40,
        'fish_feed' => 35,
        'total' => 125
    ];

    // Quality control data
    $qualityChecks = [
        ['batch' => 'BF-001', 'product' => 'Broiler Feed', 'status' => 'Passed', 'time' => '08:30'],
        ['batch' => 'LF-002', 'product' => 'Layer Feed', 'status' => 'Passed', 'time' => '10:15'],
        ['batch' => 'FF-003', 'product' => 'Fish Feed', 'status' => 'Pending', 'time' => '12:00'],
    ];

} catch (Exception $e) {
    $error = "Failed to load production data: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-gear-fill text-warning"></i> Production Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-warning">
            <i class="bi bi-plus-circle"></i> New Batch
        </button>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <i class="bi bi-tools"></i>
    <strong>Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    Monitor production lines and manage feed manufacturing processes.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Production Metrics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Broiler Feed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $todayProduction['broiler_feed']; ?> / <?php echo $productionTargets['broiler_feed']; ?> tons
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-primary" style="width: <?php echo ($todayProduction['broiler_feed'] / $productionTargets['broiler_feed']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-egg text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Layer Feed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $todayProduction['layer_feed']; ?> / <?php echo $productionTargets['layer_feed']; ?> tons
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" style="width: <?php echo ($todayProduction['layer_feed'] / $productionTargets['layer_feed']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-egg-fried text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Fish Feed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $todayProduction['fish_feed']; ?> / <?php echo $productionTargets['fish_feed']; ?> tons
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-info" style="width: <?php echo ($todayProduction['fish_feed'] / $productionTargets['fish_feed']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-water text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Production</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $todayProduction['total']; ?> / <?php echo $productionTargets['total']; ?> tons
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-warning" style="width: <?php echo ($todayProduction['total'] / $productionTargets['total']) * 100; ?>%"></div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-speedometer text-warning" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Row -->
<div class="row">
    <!-- Production Lines Control -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-sliders"></i> Production Line Control
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Current Output</th>
                                <th>Temperature</th>
                                <th>Speed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Line A</td>
                                <td>Broiler Feed</td>
                                <td><span class="badge bg-success">Running</span></td>
                                <td>5.2 tons/hr</td>
                                <td>85°C</td>
                                <td>75%</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-warning">Pause</button>
                                        <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Line B</td>
                                <td>Layer Feed</td>
                                <td><span class="badge bg-danger">Stopped</span></td>
                                <td>0 tons/hr</td>
                                <td>25°C</td>
                                <td>0%</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-success">Start</button>
                                        <button class="btn btn-sm btn-outline-secondary">Inspect</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Line C</td>
                                <td>Fish Feed</td>
                                <td><span class="badge bg-success">Running</span></td>
                                <td>4.1 tons/hr</td>
                                <td>80°C</td>
                                <td>85%</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-warning">Pause</button>
                                        <button class="btn btn-sm btn-outline-primary">Adjust</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quality Control -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-check-circle"></i> Quality Control
                </h6>
            </div>
            <div class="card-body">
                <?php foreach ($qualityChecks as $check): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                        <div>
                            <strong><?php echo htmlspecialchars($check['batch']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($check['product']); ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?php echo $check['status'] === 'Passed' ? 'success' : ($check['status'] === 'Pending' ? 'warning' : 'danger'); ?>">
                                <?php echo htmlspecialchars($check['status']); ?>
                            </span><br>
                            <small class="text-muted"><?php echo htmlspecialchars($check['time']); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-grid">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-plus"></i> New Quality Check
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory and Raw Materials -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-box"></i> Raw Materials Inventory
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Current Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Corn</td>
                                <td>245 tons</td>
                                <td><span class="badge bg-success">Good</span></td>
                            </tr>
                            <tr>
                                <td>Soybean Meal</td>
                                <td>85 tons</td>
                                <td><span class="badge bg-warning">Low</span></td>
                            </tr>
                            <tr>
                                <td>Fish Meal</td>
                                <td>32 tons</td>
                                <td><span class="badge bg-danger">Critical</span></td>
                            </tr>
                            <tr>
                                <td>Vitamins</td>
                                <td>15 tons</td>
                                <td><span class="badge bg-success">Good</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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
                        <a href="production/index.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-gear d-block mb-1"></i>
                            Production
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="inventory/index.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-boxes d-block mb-1"></i>
                            Inventory
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <a href="inventory/transfers.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-arrow-left-right d-block mb-1"></i>
                            Transfers
                        </a>
                    </div>
                    <div class="col-6 mb-3">
                        <button class="btn btn-outline-warning w-100" onclick="alert('Quality check module coming soon!')">
                            <i class="bi bi-clipboard-check d-block mb-1"></i>
                            Quality Check
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>