<?php
// File: admin/ajax/get_transfer_details.php
// AJAX handler to get transfer details with bags and items
// Administrator, Supervisor, and Branch Operator access

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/TransferController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$transferId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($transferId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid transfer ID']);
    exit;
}

try {
    $transferController = new TransferController();
    $transfer = $transferController->getTransferDetails($transferId);

    if (!$transfer) {
        echo json_encode(['success' => false, 'message' => 'Transfer not found']);
        exit;
    }

    // Generate HTML for transfer details
    $html = '<div class="transfer-details">
        <div class="row mb-3">
            <div class="col-md-6">
                <h6>Transfer Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Transfer Number:</strong></td><td>' . htmlspecialchars($transfer['transfer_number']) . '</td></tr>
                    <tr><td><strong>To Branch:</strong></td><td><span class="badge bg-primary">' . htmlspecialchars($transfer['to_branch_name']) . '</span></td></tr>
                    <tr><td><strong>Created By:</strong></td><td>' . htmlspecialchars($transfer['created_by_name']) . '</td></tr>
                    <tr><td><strong>Created Date:</strong></td><td>' . date('M j, Y H:i', strtotime($transfer['created_at'])) . '</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Status Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Status:</strong></td><td>';

    $statusColor = $transfer['status'] === 'PENDING' ? 'warning' : ($transfer['status'] === 'CONFIRMED' ? 'success' : 'secondary');
    $html .= '<span class="badge bg-' . $statusColor . '">' . $transfer['status'] . '</span></td></tr>';

    if ($transfer['confirmed_by_name']) {
        $html .= '<tr><td><strong>Confirmed By:</strong></td><td>' . htmlspecialchars($transfer['confirmed_by_name']) . '</td></tr>';
        $html .= '<tr><td><strong>Confirmed Date:</strong></td><td>' . date('M j, Y H:i', strtotime($transfer['confirmed_at'])) . '</td></tr>';
    }

    $html .= '</table>
            </div>
        </div>';

    // Finished Product Bags
    if (!empty($transfer['bags'])) {
        $html .= '<h6>Finished Product Bags (' . count($transfer['bags']) . ')</h6>
                  <div class="table-responsive mb-4">
                      <table class="table table-sm table-hover">
                          <thead>
                              <tr>
                                  <th>Product</th>
                                  <th>Package Size</th>
                                  <th>Serial Number</th>
                                  <th>Production Date</th>
                                  <th>Expiry Date</th>
                              </tr>
                          </thead>
                          <tbody>';

        foreach ($transfer['bags'] as $bag) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($bag['product_name']) . '</strong></td>
                <td>' . htmlspecialchars($bag['package_size']) . '</td>
                <td><code>' . htmlspecialchars($bag['serial_number']) . '</code></td>
                <td>' . date('M j, Y', strtotime($bag['production_date'])) . '</td>
                <td>' . date('M j, Y', strtotime($bag['expiry_date'])) . '</td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
    }

    // Bulk Items
    if (!empty($transfer['items'])) {
        $html .= '<h6>Bulk Items (' . count($transfer['items']) . ')</h6>
                  <div class="table-responsive">
                      <table class="table table-sm table-hover">
                          <thead>
                              <tr>
                                  <th>Category</th>
                                  <th>Item Name</th>
                                  <th>Quantity</th>
                                  <th>Unit</th>
                              </tr>
                          </thead>
                          <tbody>';

        foreach ($transfer['items'] as $item) {
            $categoryColor = 'secondary';
            if ($item['category'] === 'Raw Material') $categoryColor = 'info';
            elseif ($item['category'] === 'Third Party Product') $categoryColor = 'success';
            elseif ($item['category'] === 'Packaging Material') $categoryColor = 'warning';

            $html .= '<tr>
                <td><span class="badge bg-' . $categoryColor . '">' . htmlspecialchars($item['category']) . '</span></td>
                <td><strong>' . htmlspecialchars($item['name']) . '</strong></td>
                <td>' . number_format($item['quantity'], 1) . '</td>
                <td>' . htmlspecialchars($item['unit_of_measure']) . '</td>
            </tr>';
        }

        $html .= '</tbody></table></div>';
    }

    // Transfer Summary
    $totalBags = count($transfer['bags']);
    $totalItems = count($transfer['items']);

    $html .= '<div class="alert alert-light mt-4">
        <h6><i class="bi bi-info-circle"></i> Transfer Summary</h6>
        <div class="row">
            <div class="col-md-6">
                <p><strong>Finished Product Bags:</strong> ' . $totalBags . ' bags</p>
                <p><strong>Bulk Items:</strong> ' . $totalItems . ' different items</p>
            </div>
            <div class="col-md-6">
                <p><strong>Total Items in Transfer:</strong> ' . ($totalBags + $totalItems) . '</p>
                <p><strong>Transfer Status:</strong> <span class="badge bg-' . $statusColor . '">' . $transfer['status'] . '</span></p>
            </div>
        </div>
    </div>';

    $html .= '</div>';

    echo json_encode([
        'success' => true,
        'html' => $html,
        'transfer' => $transfer
    ]);

} catch (Exception $e) {
    error_log("Error getting transfer details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>