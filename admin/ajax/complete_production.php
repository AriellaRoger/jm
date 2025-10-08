<?php
// File: admin/ajax/complete_production.php
// AJAX handler to complete production batch with packaging
// Administrator and Supervisor access only

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ProductionController.php';

header('Content-Type: application/json');

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
$requiredFields = ['batch_id', 'actual_yield', 'packaging_data'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$batchId = (int)$input['batch_id'];
$actualYield = (float)$input['actual_yield'];
$wastagePercent = isset($input['wastage_percent']) ? (float)$input['wastage_percent'] : 0;
$packagingData = $input['packaging_data'];

if ($batchId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid batch ID']);
    exit;
}

if ($actualYield <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid actual yield']);
    exit;
}

if (!is_array($packagingData) || empty($packagingData)) {
    echo json_encode(['success' => false, 'message' => 'Packaging data is required']);
    exit;
}

try {
    $productionController = new ProductionController();
    $result = $productionController->completeProduction(
        $batchId,
        $_SESSION['user_id'],
        $packagingData,
        $actualYield,
        $wastagePercent
    );

    if ($result['success'] && isset($result['created_bags'])) {
        // Generate HTML for QR codes display
        $qrHtml = '<div class="qr-codes-display">
            <h6>Production Completed - QR Codes & Serial Numbers</h6>
            <div class="row">';

        foreach ($result['created_bags'] as $bag) {
            $qrPath = BASE_URL . '/assets/qrcodes/bag_' . $bag['serial_number'] . '.png';
            $qrHtml .= '<div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6>' . htmlspecialchars($bag['product_name']) . '</h6>
                        <p class="small">' . htmlspecialchars($bag['package_size']) . '</p>
                        <img src="' . $qrPath . '" alt="QR Code" style="width: 100px; height: 100px;" class="mb-2">
                        <p class="small"><strong>' . htmlspecialchars($bag['serial_number']) . '</strong></p>
                        <p class="small text-muted">Exp: ' . date('M j, Y', strtotime($bag['expiry_date'])) . '</p>
                    </div>
                </div>
            </div>';
        }

        $qrHtml .= '</div>
            <div class="text-center mt-3">
                <button class="btn btn-primary" onclick="printQRCodes(' . $result['batch_id'] . ')">
                    <i class="bi bi-printer"></i> Print All QR Codes
                </button>
            </div>
        </div>';

        $result['qr_html'] = $qrHtml;
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Error completing production: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred'
    ]);
}
?>