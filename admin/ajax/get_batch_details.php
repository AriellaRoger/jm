<?php
// File: admin/ajax/get_batch_details.php
// AJAX handler to get detailed batch information
// Administrator and Supervisor access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ProductionController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($batchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit;
}

try {
    $productionController = new ProductionController();
    $batch = $productionController->getBatchDetails($batchId);

    if (!$batch) {
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
        exit;
    }

    // Generate HTML for batch details
    $html = '<div class="batch-details">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Batch Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Batch Number:</strong></td><td>' . htmlspecialchars($batch['batch_number']) . '</td></tr>
                    <tr><td><strong>Formula:</strong></td><td>' . htmlspecialchars($batch['formula_name']) . '</td></tr>
                    <tr><td><strong>Batch Size:</strong></td><td>' . number_format($batch['batch_size'], 1) . 'x</td></tr>
                    <tr><td><strong>Status:</strong></td><td>';

    $statusColor = [
        'PLANNED' => 'secondary',
        'IN_PROGRESS' => 'warning',
        'PAUSED' => 'danger',
        'COMPLETED' => 'success',
        'CANCELLED' => 'dark'
    ];
    $html .= '<span class="badge bg-' . ($statusColor[$batch['status']] ?? 'secondary') . '">' . $batch['status'] . '</span></td></tr>';

    $html .= '</table>
            </div>
            <div class="col-md-6">
                <h6>Production Details</h6>
                <table class="table table-sm">
                    <tr><td><strong>Production Officer:</strong></td><td>' . htmlspecialchars($batch['production_officer_name']) . '</td></tr>
                    <tr><td><strong>Supervisor:</strong></td><td>' . htmlspecialchars($batch['supervisor_name']) . '</td></tr>';

    if ($batch['started_at']) {
        $html .= '<tr><td><strong>Started:</strong></td><td>' . date('M j, Y H:i', strtotime($batch['started_at'])) . '</td></tr>';
    }
    if ($batch['completed_at']) {
        $html .= '<tr><td><strong>Completed:</strong></td><td>' . date('M j, Y H:i', strtotime($batch['completed_at'])) . '</td></tr>';
    }

    $html .= '</table>
            </div>
        </div>';

    if (!empty($batch['formula_description'])) {
        $html .= '<div class="mb-3">
            <h6>Formula Description</h6>
            <div class="alert alert-light">' . htmlspecialchars($batch['formula_description']) . '</div>
        </div>';
    }

    // Yield and cost information
    $html .= '<div class="row mb-3">
        <div class="col-md-6">
            <h6>Yield Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Expected Yield:</strong></td><td>' . number_format($batch['expected_yield'], 1) . ' KG</td></tr>';

    if ($batch['actual_yield']) {
        $html .= '<tr><td><strong>Actual Yield:</strong></td><td>' . number_format($batch['actual_yield'], 1) . ' KG</td></tr>
                  <tr><td><strong>Wastage:</strong></td><td>' . number_format($batch['wastage_percentage'], 1) . '%</td></tr>';
    }

    $html .= '</table>
        </div>
        <div class="col-md-6">
            <h6>Cost Information</h6>
            <table class="table table-sm">
                <tr><td><strong>Production Cost:</strong></td><td>' . number_format($batch['production_cost'], 0) . ' TZS</td></tr>';

    if ($batch['actual_yield'] > 0) {
        $costPerKg = $batch['production_cost'] / $batch['actual_yield'];
        $html .= '<tr><td><strong>Cost per KG:</strong></td><td>' . number_format($costPerKg, 0) . ' TZS</td></tr>';
    }

    $html .= '</table>
        </div>
    </div>';

    // Raw materials used
    if (!empty($batch['materials'])) {
        $html .= '<h6>Raw Materials Used</h6>
                  <div class="table-responsive mb-3">
                      <table class="table table-sm table-hover">
                          <thead>
                              <tr>
                                  <th>Material</th>
                                  <th>Planned</th>
                                  <th>Actual</th>
                                  <th>Unit Cost</th>
                                  <th>Total Cost</th>
                              </tr>
                          </thead>
                          <tbody>';

        foreach ($batch['materials'] as $material) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($material['material_name']) . '</strong></td>
                <td>' . number_format($material['planned_quantity'], 1) . ' ' . htmlspecialchars($material['unit_of_measure']) . '</td>
                <td>' . number_format($material['actual_quantity'] ?? $material['planned_quantity'], 1) . ' ' . htmlspecialchars($material['unit_of_measure']) . '</td>
                <td>' . number_format($material['unit_cost'], 0) . ' TZS</td>
                <td>' . number_format($material['total_cost'], 0) . ' TZS</td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
    }

    // Products produced
    if (!empty($batch['products'])) {
        $html .= '<h6>Products Produced</h6>
                  <div class="table-responsive mb-3">
                      <table class="table table-sm table-hover">
                          <thead>
                              <tr>
                                  <th>Product</th>
                                  <th>Package Size</th>
                                  <th>Bags Produced</th>
                                  <th>Total Weight</th>
                                  <th>Packaging Material</th>
                                  <th>Packaging Cost</th>
                              </tr>
                          </thead>
                          <tbody>';

        foreach ($batch['products'] as $product) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($product['product_name']) . '</strong></td>
                <td>' . htmlspecialchars($product['package_size']) . '</td>
                <td>' . number_format($product['bags_produced']) . '</td>
                <td>' . number_format($product['total_weight'], 1) . ' KG</td>
                <td>' . htmlspecialchars($product['packaging_material_name']) . '</td>
                <td>' . number_format($product['packaging_cost'], 0) . ' TZS</td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
    }

    // Production logs
    if (!empty($batch['logs'])) {
        $html .= '<h6>Production Timeline</h6>
                  <div class="table-responsive">
                      <table class="table table-sm">
                          <thead>
                              <tr>
                                  <th>Date/Time</th>
                                  <th>Action</th>
                                  <th>Performed By</th>
                                  <th>Notes</th>
                              </tr>
                          </thead>
                          <tbody>';

        foreach ($batch['logs'] as $log) {
            $actionLabels = [
                'BATCH_CREATED' => 'Batch Created',
                'PRODUCTION_STARTED' => 'Production Started',
                'PRODUCTION_PAUSED' => 'Production Paused',
                'PRODUCTION_RESUMED' => 'Production Resumed',
                'PRODUCTION_COMPLETED' => 'Production Completed',
                'PRODUCTION_CANCELLED' => 'Production Cancelled'
            ];

            $html .= '<tr>
                <td>' . date('M j, Y H:i', strtotime($log['created_at'])) . '</td>
                <td><strong>' . ($actionLabels[$log['action']] ?? $log['action']) . '</strong></td>
                <td>' . htmlspecialchars($log['user_name']) . '</td>
                <td>' . htmlspecialchars($log['notes']) . '</td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
    }

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'batch' => $batch
    ]);

} catch (Exception $e) {
    error_log("Error getting batch details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>