<?php
// File: admin/ajax/adjust_stock.php
// AJAX handler to process stock adjustments for all product types
// Administrator-only access with complete activity logging and movement tracking

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/StockAdjustmentController.php';

header('Content-Type: application/json');

// Check authentication and admin access
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$adjustmentType = $_POST['adjustmentType'] ?? '';
$productId = (int)($_POST['productId'] ?? 0);
$branchId = (int)($_POST['branchId'] ?? 0);
$newStock = (float)($_POST['newStock'] ?? 0);
$adjustmentReason = trim($_POST['adjustmentReason'] ?? '');

// Validation
if (empty($adjustmentType) || $productId <= 0 || $branchId <= 0 || $newStock < 0 || empty($adjustmentReason)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required and must be valid']);
    exit;
}

// Validate adjustment type
$validTypes = ['raw_material', 'third_party', 'packaging', 'opened_bag'];
if (!in_array($adjustmentType, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid adjustment type']);
    exit;
}

try {
    $stockController = new StockAdjustmentController();
    $userId = $_SESSION['user_id'];

    $result = false;

    switch ($adjustmentType) {
        case 'raw_material':
            $result = $stockController->adjustRawMaterialStock($productId, $newStock, $adjustmentReason, $userId, $branchId);
            break;

        case 'third_party':
            $result = $stockController->adjustThirdPartyProductStock($productId, $newStock, $adjustmentReason, $userId, $branchId);
            break;

        case 'packaging':
            $result = $stockController->adjustPackagingMaterialStock($productId, $newStock, $adjustmentReason, $userId, $branchId);
            break;

        case 'opened_bag':
            // For opened bags, we're adjusting weight, not quantity
            if ($newStock > 1000) { // Reasonable weight limit check
                echo json_encode(['success' => false, 'message' => 'Weight cannot exceed 1000 KG']);
                exit;
            }
            $result = $stockController->adjustOpenedBagWeight($productId, $newStock, $adjustmentReason, $userId, $branchId);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown adjustment type']);
            exit;
    }

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Stock adjusted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to adjust stock'
        ]);
    }

} catch (Exception $e) {
    error_log("Stock adjustment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>