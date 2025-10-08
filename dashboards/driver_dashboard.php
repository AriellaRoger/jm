<?php
// File: dashboards/driver_dashboard.php
// Driver dashboard for JM Animal Feeds ERP System
// Delivery and logistics management access

$pageTitle = 'Driver Dashboard';
include 'includes/header.php';

// Get driver-specific data
try {
    $db = getDbConnection();

    // Delivery statistics (placeholder data)
    $deliveryStats = [
        'today_deliveries' => 4,
        'pending_deliveries' => 2,
        'completed_deliveries' => 2,
        'total_distance' => 145 // km
    ];

    // Today's delivery schedule
    $todayDeliveries = [
        [
            'id' => 'DEL-001',
            'customer' => 'Arusha Poultry Farm',
            'destination' => 'Arusha, Tanzania',
            'product' => 'Broiler Feed',
            'quantity' => '5 tons',
            'status' => 'Completed',
            'time' => '08:00 - 10:30'
        ],
        [
            'id' => 'DEL-002',
            'customer' => 'Kilimanjaro Farms',
            'destination' => 'Moshi, Tanzania',
            'product' => 'Layer Feed',
            'quantity' => '3 tons',
            'status' => 'Completed',
            'time' => '11:00 - 13:15'
        ],
        [
            'id' => 'DEL-003',
            'customer' => 'Lake Victoria Fish Farm',
            'destination' => 'Mwanza, Tanzania',
            'product' => 'Fish Feed',
            'quantity' => '2 tons',
            'status' => 'In Transit',
            'time' => '14:00 - 17:00'
        ],
        [
            'id' => 'DEL-004',
            'customer' => 'Dodoma Poultry',
            'destination' => 'Dodoma, Tanzania',
            'product' => 'Broiler Feed',
            'quantity' => '4 tons',
            'status' => 'Pending',
            'time' => '09:00 - 12:00 (Tomorrow)'
        ]
    ];

} catch (Exception $e) {
    $error = "Failed to load delivery data: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-truck text-success"></i> Driver Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-geo-alt"></i> GPS Tracking
            </button>
        </div>
        <button type="button" class="btn btn-sm btn-success">
            <i class="bi bi-check-circle"></i> Update Status
        </button>
    </div>
</div>

<!-- Welcome Message -->
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-truck"></i>
    <strong>Safe travels, <?php echo htmlspecialchars($currentUser['name']); ?>!</strong>
    Manage your delivery routes and update delivery status efficiently.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Delivery Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Today's Deliveries</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $deliveryStats['today_deliveries']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $deliveryStats['completed_deliveries']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
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
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $deliveryStats['pending_deliveries']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
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
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Distance Today</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $deliveryStats['total_distance']; ?> km
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-speedometer2 text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delivery Schedule -->
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-list-check"></i> Delivery Schedule
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Destination</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayDeliveries as $delivery): ?>
                                <tr class="<?php echo $delivery['status'] === 'In Transit' ? 'table-warning' : ''; ?>">
                                    <td><?php echo htmlspecialchars($delivery['id']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['customer']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['destination']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['product']); ?></td>
                                    <td><?php echo htmlspecialchars($delivery['quantity']); ?></td>
                                    <td><small><?php echo htmlspecialchars($delivery['time']); ?></small></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $delivery['status'] === 'Completed' ? 'success' :
                                                ($delivery['status'] === 'In Transit' ? 'warning' :
                                                ($delivery['status'] === 'Pending' ? 'secondary' : 'danger'));
                                        ?>">
                                            <?php echo htmlspecialchars($delivery['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($delivery['status'] === 'Pending'): ?>
                                            <button class="btn btn-sm btn-outline-primary">Start</button>
                                        <?php elseif ($delivery['status'] === 'In Transit'): ?>
                                            <button class="btn btn-sm btn-outline-success">Complete</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Done</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Vehicle Info -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-lightning"></i> Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-success">
                        <i class="bi bi-play-circle"></i> Start Next Delivery
                    </button>
                    <button class="btn btn-outline-warning">
                        <i class="bi bi-geo-alt"></i> Update Location
                    </button>
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-telephone"></i> Contact Customer
                    </button>
                    <button class="btn btn-outline-info">
                        <i class="bi bi-map"></i> View Route
                    </button>
                    <button class="btn btn-outline-secondary">
                        <i class="bi bi-camera"></i> Delivery Photo
                    </button>
                </div>
            </div>
        </div>

        <!-- Vehicle Information -->
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-truck"></i> Vehicle Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12 mb-2">
                        <strong>Vehicle ID:</strong> TR-001
                    </div>
                    <div class="col-12 mb-2">
                        <strong>License Plate:</strong> ABC-123-XY
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Fuel Level:</strong>
                        <div class="progress mt-1">
                            <div class="progress-bar bg-success" style="width: 75%">75%</div>
                        </div>
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Load Capacity:</strong> 8 tons
                    </div>
                    <div class="col-12 mb-2">
                        <strong>Current Load:</strong> 2 tons
                        <div class="progress mt-1">
                            <div class="progress-bar bg-info" style="width: 25%">25%</div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-fuel-pump"></i> Report Fuel Level
                    </button>
                    <button class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-exclamation-triangle"></i> Report Issue
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Route Map Placeholder -->
<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-map"></i> Today's Route Overview
                </h6>
            </div>
            <div class="card-body">
                <div class="bg-light p-5 text-center rounded" style="min-height: 300px;">
                    <i class="bi bi-geo-alt-fill text-muted" style="font-size: 4rem;"></i>
                    <h5 class="text-muted mt-3">Route Map</h5>
                    <p class="text-muted">Interactive route map would be displayed here.<br>
                    Shows current location, delivery points, and optimal routes.</p>
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-map"></i> Open Full Map
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>