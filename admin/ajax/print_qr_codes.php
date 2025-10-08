<?php
// File: admin/ajax/print_qr_codes.php
// Print QR codes for production batch
// Administrator and Supervisor access only

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ProductionController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
if ($batchId <= 0) {
    exit('Invalid batch ID');
}

try {
    // Get bags for this specific batch - get exactly the number of bags produced
    $conn = Database::getInstance()->getConnection();

    // First get the total bags produced for this batch
    $countSql = "SELECT SUM(bags_produced) as total_bags FROM production_batch_products WHERE batch_id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute([$batchId]);
    $totalBags = $countStmt->fetch()['total_bags'] ?? 0;

    // Then get the bags
    $sql = "SELECT pb.id, pb.serial_number, pb.production_date, pb.expiry_date,
                   p.name as product_name, p.package_size, p.description
            FROM product_bags pb
            JOIN products p ON pb.product_id = p.id
            JOIN production_batch_products pbp ON p.id = pbp.product_id
            JOIN production_batches batch ON pbp.batch_id = batch.id
            WHERE pbp.batch_id = ? AND pb.branch_id = 1
            AND pb.production_date = DATE(batch.completed_at)
            AND p.package_size = pbp.package_size
            ORDER BY pb.id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$batchId, (int)$totalBags]);
    $bags = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bags)) {
        exit('No bags found for printing');
    }

    // Set content type for printing
    header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Codes - Production Batch</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 10px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .qr-item {
            border: 2px solid #333;
            padding: 10px;
            text-align: center;
            page-break-inside: avoid;
            min-height: 200px;
        }
        .qr-code {
            width: 120px;
            height: 120px;
            margin: 10px auto;
            display: block;
        }
        .product-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .serial-number {
            font-weight: bold;
            font-size: 12px;
            background: #f0f0f0;
            padding: 3px;
            margin: 5px 0;
        }
        .details {
            font-size: 10px;
            margin: 3px 0;
        }
        .verification-url {
            font-size: 8px;
            word-wrap: break-word;
            margin-top: 5px;
            color: #666;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        @page {
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="margin-bottom: 20px; padding: 10px 20px;">Print QR Codes</button>
    </div>

    <div class="header">
        <h2>JM ANIMAL FEEDS - PRODUCT QR CODES</h2>
        <p>Production Date: <?= date('M j, Y') ?> | Total Bags: <?= count($bags) ?></p>
    </div>

    <div class="qr-grid">
        <?php foreach ($bags as $bag):
            $qrPath = __DIR__ . '/../../assets/qrcodes/bag_' . $bag['serial_number'] . '.png';
            $qrUrl = BASE_URL . '/assets/qrcodes/bag_' . $bag['serial_number'] . '.png';
            $verificationUrl = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/verify.php?serial=' . urlencode($bag['serial_number']);
        ?>
            <div class="qr-item">
                <div class="product-name"><?= htmlspecialchars($bag['product_name']) ?></div>
                <div class="details">Package: <?= htmlspecialchars($bag['package_size']) ?></div>

                <?php if (file_exists($qrPath)): ?>
                    <img src="<?= $qrUrl ?>" alt="QR Code" class="qr-code">
                <?php else: ?>
                    <div style="width: 120px; height: 120px; border: 1px solid #ccc; margin: 10px auto; display: flex; align-items: center; justify-content: center;">
                        QR Code Not Found
                    </div>
                <?php endif; ?>

                <div class="serial-number"><?= htmlspecialchars($bag['serial_number']) ?></div>
                <div class="details">Produced: <?= date('M j, Y', strtotime($bag['production_date'])) ?></div>
                <div class="details">Best Before: <?= date('M j, Y', strtotime($bag['expiry_date'])) ?></div>
                <div class="verification-url">Verify: <?= $verificationUrl ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
        <p>Scan QR codes to verify product authenticity at: <?= $_SERVER['HTTP_HOST'] . BASE_URL ?>/verify.php</p>
        <p>Generated on: <?= date('Y-m-d H:i:s') ?></p>
    </div>

</body>
</html>

<?php
} catch (Exception $e) {
    error_log("Error generating QR codes print: " . $e->getMessage());
    exit('Error generating QR codes');
}
?>