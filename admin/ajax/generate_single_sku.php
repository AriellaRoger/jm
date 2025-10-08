<?php
// File: admin/ajax/generate_single_sku.php
// AJAX handler for generating single product SKU
// Accessible by Administrator and Supervisor roles only

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/BarcodeController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userRole = $_SESSION['user_role'];
if (!in_array($userRole, ['Administrator', 'Supervisor'])) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$productType = $_POST['type'] ?? '';
$productId = intval($_POST['id'] ?? 0);

if (empty($productType) || $productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product type and ID are required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $barcodeController = new BarcodeController();

    // Get product details for category/brand determination
    $category = '';
    $brand = '';

    switch ($productType) {
        case 'finished_product':
            $stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $category = $product['name'] ?? '';
            break;
        case 'raw_material':
            $stmt = $db->prepare("SELECT name FROM raw_materials WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $category = $product['name'] ?? '';
            break;
        case 'third_party_product':
            $stmt = $db->prepare("SELECT brand FROM third_party_products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $brand = $product['brand'] ?? '';
            break;
        case 'packaging_material':
            $stmt = $db->prepare("SELECT name FROM packaging_materials WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $category = $product['name'] ?? '';
            break;
        default:
            throw new Exception("Invalid product type");
    }

    if (!$product) {
        throw new Exception("Product not found");
    }

    // Generate and assign SKU
    $sku = $barcodeController->assignSKUToProduct($productType, $productId, $category, $brand);

    if ($sku) {
        echo json_encode(['success' => true, 'sku' => $sku, 'message' => 'SKU generated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate SKU']);
    }

} catch (Exception $e) {
    error_log("Error generating single SKU: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>