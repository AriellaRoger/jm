<?php
// File: admin/ajax/download_barcode.php
// Barcode download handler
// Accessible by all inventory users

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/BarcodeController.php';

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(403);
    echo 'Not authenticated';
    exit;
}

$sku = $_GET['sku'] ?? '';
$format = strtoupper($_GET['format'] ?? 'PNG');

if (empty($sku)) {
    http_response_code(400);
    echo 'SKU is required';
    exit;
}

if (!in_array($format, ['PNG', 'SVG'])) {
    http_response_code(400);
    echo 'Invalid format. Use PNG or SVG';
    exit;
}

try {
    $barcodeController = new BarcodeController();

    // Generate barcode data
    if ($format === 'PNG') {
        $barcodeData = $barcodeController->generateBarcodePNG($sku, 2, 60);
        $contentType = 'image/png';
        $extension = 'png';
    } else {
        $barcodeData = $barcodeController->generateBarcodeSVG($sku, 2, 60);
        $contentType = 'image/svg+xml';
        $extension = 'svg';
    }

    // Set headers for download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="barcode_' . $sku . '.' . $extension . '"');
    header('Content-Length: ' . strlen($barcodeData));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Output barcode data
    echo $barcodeData;

} catch (Exception $e) {
    error_log("Error downloading barcode: " . $e->getMessage());
    http_response_code(500);
    echo 'An error occurred while generating barcode';
}
?>