<?php
// File: admin/ajax/print_batch_report.php
// Production batch report generator for printing
// Administrator and Supervisor access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/ProductionController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($batchId <= 0) {
    exit('Invalid batch ID');
}

try {
    $productionController = new ProductionController();
    $batch = $productionController->getBatchDetails($batchId);

    if (!$batch) {
        exit('Batch not found');
    }

    // Set content type for printing
    header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Batch Report - <?= htmlspecialchars($batch['batch_number']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-title {
            font-size: 14px;
            font-weight: bold;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 140px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        .signature-box {
            border: 1px solid #ccc;
            padding: 15px;
            text-align: center;
            min-height: 80px;
        }
        .signature-title {
            font-weight: bold;
            margin-bottom: 40px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
        }
        .qr-section {
            margin-top: 30px;
            text-align: center;
        }
        .bags-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .bag-item {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
            font-size: 10px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="margin-bottom: 20px; padding: 10px 20px;">Print Report</button>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="company-name">JM ANIMAL FEEDS ERP SYSTEM</div>
        <div>Production Batch Report</div>
        <div class="report-title">Batch #<?= htmlspecialchars($batch['batch_number']) ?></div>
        <div style="margin-top: 10px;">Generated on: <?= date('M j, Y H:i:s') ?></div>
    </div>

    <!-- Batch Information -->
    <div class="info-section">
        <div class="info-title">Batch Information</div>
        <div class="info-grid">
            <div>
                <div class="info-item">
                    <span class="info-label">Batch Number:</span>
                    <?= htmlspecialchars($batch['batch_number']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Formula:</span>
                    <?= htmlspecialchars($batch['formula_name']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Batch Size:</span>
                    <?= number_format($batch['batch_size'], 1) ?>x
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <?= $batch['status'] ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Expected Yield:</span>
                    <?= number_format($batch['expected_yield'], 1) ?> KG
                </div>
                <?php if ($batch['actual_yield']): ?>
                <div class="info-item">
                    <span class="info-label">Actual Yield:</span>
                    <?= number_format($batch['actual_yield'], 1) ?> KG
                </div>
                <div class="info-item">
                    <span class="info-label">Wastage:</span>
                    <?= number_format($batch['wastage_percentage'], 1) ?>%
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="info-item">
                    <span class="info-label">Production Officer:</span>
                    <?= htmlspecialchars($batch['production_officer_name']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Supervisor:</span>
                    <?= htmlspecialchars($batch['supervisor_name']) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Created:</span>
                    <?= date('M j, Y H:i', strtotime($batch['created_at'])) ?>
                </div>
                <?php if ($batch['started_at']): ?>
                <div class="info-item">
                    <span class="info-label">Started:</span>
                    <?= date('M j, Y H:i', strtotime($batch['started_at'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($batch['completed_at']): ?>
                <div class="info-item">
                    <span class="info-label">Completed:</span>
                    <?= date('M j, Y H:i', strtotime($batch['completed_at'])) ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Duration:</span>
                    <?php
                    $start = new DateTime($batch['started_at']);
                    $end = new DateTime($batch['completed_at']);
                    $duration = $start->diff($end);
                    echo $duration->h . 'h ' . $duration->i . 'm';
                    ?>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="info-label">Production Cost:</span>
                    <?= number_format($batch['production_cost'], 0) ?> TZS
                </div>
            </div>
        </div>
    </div>

    <!-- Raw Materials Used -->
    <?php if (!empty($batch['materials'])): ?>
    <div class="info-section">
        <div class="info-title">Raw Materials Used</div>
        <table>
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Planned Qty</th>
                    <th>Actual Qty</th>
                    <th>Unit</th>
                    <th>Unit Cost (TZS)</th>
                    <th>Total Cost (TZS)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batch['materials'] as $material): ?>
                <tr>
                    <td><?= htmlspecialchars($material['material_name']) ?></td>
                    <td><?= number_format($material['planned_quantity'], 1) ?></td>
                    <td><?= number_format($material['actual_quantity'] ?? $material['planned_quantity'], 1) ?></td>
                    <td><?= htmlspecialchars($material['unit_of_measure']) ?></td>
                    <td><?= number_format($material['unit_cost'], 0) ?></td>
                    <td><?= number_format($material['total_cost'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold;">
                    <td colspan="5">Total Material Cost:</td>
                    <td><?= number_format(array_sum(array_column($batch['materials'], 'total_cost')), 0) ?> TZS</td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Products Produced -->
    <?php if (!empty($batch['products'])): ?>
    <div class="info-section">
        <div class="info-title">Products Produced</div>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Package Size</th>
                    <th>Bags Produced</th>
                    <th>Total Weight (KG)</th>
                    <th>Packaging Material</th>
                    <th>Packaging Cost (TZS)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batch['products'] as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                    <td><?= htmlspecialchars($product['package_size']) ?></td>
                    <td><?= number_format($product['bags_produced']) ?></td>
                    <td><?= number_format($product['total_weight'], 1) ?></td>
                    <td><?= htmlspecialchars($product['packaging_material_name']) ?></td>
                    <td><?= number_format($product['packaging_cost'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight: bold;">
                    <td colspan="5">Total Packaging Cost:</td>
                    <td><?= number_format(array_sum(array_column($batch['products'], 'packaging_cost')), 0) ?> TZS</td>
                </tr>
            </tbody>
        </table>

        <!-- Bag Serial Numbers -->
        <div class="info-title">Bag Serial Numbers & QR Codes</div>
        <?php
        // Get bag serial numbers for this batch
        // First get the total bags produced for this batch
        $countSql = "SELECT SUM(bags_produced) as total_bags FROM production_batch_products WHERE batch_id = ?";
        $countStmt = Database::getInstance()->getConnection()->prepare($countSql);
        $countStmt->execute([$batchId]);
        $totalBags = $countStmt->fetch()['total_bags'] ?? 0;

        // Then get the bags
        $sql = "SELECT pb.serial_number, p.name as product_name, p.package_size
                FROM product_bags pb
                JOIN products p ON pb.product_id = p.id
                JOIN production_batch_products pbp ON p.id = pbp.product_id
                JOIN production_batches batch ON pbp.batch_id = batch.id
                WHERE pbp.batch_id = ? AND pb.branch_id = 1
                AND pb.production_date = DATE(batch.completed_at)
                AND p.package_size = pbp.package_size
                ORDER BY pb.id DESC
                LIMIT ?";
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute([$batchId, (int)$totalBags]);
        $bags = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($bags)):
        ?>
        <div class="bags-grid">
            <?php foreach ($bags as $bag): ?>
            <div class="bag-item">
                <strong><?= htmlspecialchars($bag['serial_number']) ?></strong><br>
                <?= htmlspecialchars($bag['product_name']) ?><br>
                <?= htmlspecialchars($bag['package_size']) ?>
                <div style="margin-top: 5px; font-size: 8px;">
                    QR: <?= htmlspecialchars($bag['serial_number']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Production Timeline -->
    <?php if (!empty($batch['logs'])): ?>
    <div class="info-section">
        <div class="info-title">Production Timeline</div>
        <table>
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Action</th>
                    <th>Performed By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batch['logs'] as $log): ?>
                <tr>
                    <td><?= date('M j, Y H:i', strtotime($log['created_at'])) ?></td>
                    <td><?= str_replace('_', ' ', $log['action']) ?></td>
                    <td><?= htmlspecialchars($log['user_name']) ?></td>
                    <td><?= htmlspecialchars($log['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-title">Production Officer</div>
            <div class="signature-line">
                <?= htmlspecialchars($batch['production_officer_name']) ?>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-title">Supervisor</div>
            <div class="signature-line">
                <?= htmlspecialchars($batch['supervisor_name']) ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #666;">
        <p>This is a computer-generated report from JM Animal Feeds ERP System</p>
        <p>Generated on: <?= date('Y-m-d H:i:s') ?> | Batch ID: <?= $batch['id'] ?></p>
    </div>

</body>
</html>

<?php
} catch (Exception $e) {
    error_log("Error generating batch report: " . $e->getMessage());
    exit('Error generating report');
}
?>