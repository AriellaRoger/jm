<?php
// File: admin/ajax/print_transfer_form.php
// Professional transfer form with comprehensive item listing, serial numbers, and signatures
// Administrator and Supervisor access only

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/TransferController.php';

// Check authentication and role access
$authController = new AuthController();
if (!$authController->isLoggedIn() || !in_array($_SESSION['user_role'], ['Administrator', 'Supervisor'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$transferId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($transferId <= 0) {
    exit('Invalid transfer ID');
}

try {
    $transferController = new TransferController();
    $transfer = $transferController->getTransferForPrint($transferId);

    if (!$transfer) {
        exit('Transfer not found');
    }

    // Generate QR code for transfer verification
    require_once __DIR__ . '/../../phpqrcode/qrlib.php';

    $qrData = json_encode([
        'transfer_id' => $transfer['id'],
        'transfer_number' => $transfer['transfer_number'],
        'verification_code' => md5($transfer['transfer_number'] . $transfer['created_at']),
        'verify_url' => 'https://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/verify_transfer.php?id=' . $transfer['id']
    ]);

    $qrPath = __DIR__ . '/../../assets/qrcodes/transfer_' . $transfer['transfer_number'] . '.png';
    $qrDir = dirname($qrPath);
    if (!is_dir($qrDir)) {
        mkdir($qrDir, 0755, true);
    }

    QRcode::png($qrData, $qrPath, QR_ECLEVEL_L, 4, 2);
    $qrUrl = BASE_URL . '/assets/qrcodes/transfer_' . $transfer['transfer_number'] . '.png';

    // Set content type for printing
    header('Content-Type: text/html; charset=utf-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Form - <?= htmlspecialchars($transfer['transfer_number']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            font-size: 11px;
            line-height: 1.2;
            color: #333;
        }
        .transfer-form {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 3px solid #2c5282;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c5282;
            margin: 0;
        }
        .form-title {
            font-size: 18px;
            font-weight: bold;
            margin: 5px 0;
            color: #1a365d;
        }
        .form-subtitle {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        .form-info {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 20px;
            margin-bottom: 15px;
            align-items: start;
        }
        .info-section {
            border: 1px solid #e2e8f0;
            padding: 8px;
            border-radius: 4px;
        }
        .info-title {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .info-item {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
        .qr-section {
            text-align: center;
            border: 1px solid #e2e8f0;
            padding: 8px;
            border-radius: 4px;
        }
        .qr-code {
            width: 80px;
            height: 80px;
            margin: 5px auto;
        }
        .items-section {
            margin-bottom: 15px;
        }
        .section-title {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 8px;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 10px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            text-align: left;
        }
        .items-table th {
            background: #f7fafc;
            font-weight: bold;
            color: #2d3748;
        }
        .items-table td {
            vertical-align: top;
        }
        .serial-list {
            font-size: 9px;
            line-height: 1.1;
            max-width: 200px;
        }
        .signatures-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            page-break-inside: avoid;
        }
        .signature-box {
            border: 1px solid #e2e8f0;
            padding: 8px;
            min-height: 80px;
            border-radius: 4px;
        }
        .signature-title {
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 11px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #666;
            margin-top: 40px;
            padding-top: 3px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            .transfer-form { margin: 0; }
        }
        @page {
            margin: 10mm;
            size: A4;
        }
        .priority-notice {
            background: #fed7d7;
            border: 1px solid #fc8181;
            color: #9b2c2c;
            padding: 6px;
            margin-bottom: 10px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2c5282; color: white; border: none; border-radius: 4px; cursor: pointer;">
            🖨️ Print Transfer Form
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #e53e3e; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            ✕ Close
        </button>
    </div>

    <div class="transfer-form">
        <div class="header">
            <h1 class="company-name">JM ANIMAL FEEDS</h1>
            <h2 class="form-title">INVENTORY TRANSFER FORM</h2>
            <p class="form-subtitle">Inter-Branch Transfer Documentation</p>
        </div>

        <div class="priority-notice">
            ⚠️ THIS FORM MUST BE SIGNED BY ALL PARTIES AND ACCOMPANY THE TRANSFERRED ITEMS
        </div>

        <div class="form-info">
            <div class="info-section">
                <div class="info-title">TRANSFER DETAILS</div>
                <div class="info-item">
                    <span>Transfer #:</span>
                    <strong><?= htmlspecialchars($transfer['transfer_number']) ?></strong>
                </div>
                <div class="info-item">
                    <span>Date Created:</span>
                    <span><?= date('M j, Y H:i', strtotime($transfer['created_at'])) ?></span>
                </div>
                <div class="info-item">
                    <span>From:</span>
                    <span><?= htmlspecialchars($transfer['from_branch_name']) ?></span>
                </div>
                <div class="info-item">
                    <span>To:</span>
                    <strong><?= htmlspecialchars($transfer['to_branch_name']) ?></strong>
                </div>
                <div class="info-item">
                    <span>Created By:</span>
                    <span><?= htmlspecialchars($transfer['created_by_name']) ?></span>
                </div>
            </div>

            <div class="qr-section">
                <div class="info-title">VERIFICATION</div>
                <?php if (file_exists($qrPath)): ?>
                    <img src="<?= $qrUrl ?>" alt="Transfer QR" class="qr-code">
                <?php else: ?>
                    <div class="qr-code" style="border: 1px solid #ccc; display: flex; align-items: center; justify-content: center;">
                        QR Code
                    </div>
                <?php endif; ?>
                <div style="font-size: 8px; color: #666;">Scan to verify</div>
            </div>

            <div class="info-section">
                <div class="info-title">SUMMARY</div>
                <div class="info-item">
                    <span>Total Bags:</span>
                    <strong><?= count($transfer['bags']) ?></strong>
                </div>
                <div class="info-item">
                    <span>Bulk Items:</span>
                    <strong><?= count($transfer['bulk_items']) ?></strong>
                </div>
                <div class="info-item">
                    <span>Status:</span>
                    <span style="color: #2c5282; font-weight: bold;"><?= htmlspecialchars($transfer['status']) ?></span>
                </div>
                <div class="info-item">
                    <span>Priority:</span>
                    <span style="color: #e53e3e; font-weight: bold;">HIGH</span>
                </div>
            </div>
        </div>

        <!-- Finished Products with Serial Numbers -->
        <?php if (!empty($transfer['bags'])): ?>
        <div class="items-section">
            <div class="section-title">
                🎯 FINISHED PRODUCTS - INDIVIDUAL BAGS WITH SERIAL NUMBERS
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">Product Name</th>
                        <th style="width: 15%;">Package Size</th>
                        <th style="width: 10%;">Quantity</th>
                        <th style="width: 50%;">Serial Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $bagsByProduct = [];
                    foreach ($transfer['bags'] as $bag) {
                        $key = $bag['product_name'] . '_' . $bag['package_size'];
                        if (!isset($bagsByProduct[$key])) {
                            $bagsByProduct[$key] = [
                                'product_name' => $bag['product_name'],
                                'package_size' => $bag['package_size'],
                                'serials' => []
                            ];
                        }
                        $bagsByProduct[$key]['serials'][] = $bag['serial_number'];
                    }

                    foreach ($bagsByProduct as $productGroup): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($productGroup['product_name']) ?></strong></td>
                        <td><?= htmlspecialchars($productGroup['package_size']) ?></td>
                        <td style="text-align: center;"><strong><?= count($productGroup['serials']) ?></strong></td>
                        <td>
                            <div class="serial-list">
                                <?php foreach ($productGroup['serials'] as $serial): ?>
                                    <div><?= htmlspecialchars($serial) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Bulk Items -->
        <?php if (!empty($transfer['bulk_items'])): ?>
        <div class="items-section">
            <div class="section-title">
                📦 BULK INVENTORY ITEMS
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Item Name</th>
                        <th style="width: 20%;">Category</th>
                        <th style="width: 15%;">Quantity</th>
                        <th style="width: 10%;">Unit</th>
                        <th style="width: 25%;">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transfer['bulk_items'] as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                        <td><?= str_replace('_', ' ', $item['item_type']) ?></td>
                        <td style="text-align: center;"><strong><?= number_format($item['quantity'], 2) ?></strong></td>
                        <td><?= htmlspecialchars($item['unit'] ?? 'KG') ?></td>
                        <td style="font-size: 9px;">Handle with care</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Signatures Section -->
        <div class="signatures-section">
            <div class="signature-box">
                <div class="signature-title">SENDER AUTHORIZATION</div>
                <div style="font-size: 10px; margin-bottom: 5px;">
                    <strong>Name:</strong> <?= htmlspecialchars($transfer['created_by_name']) ?><br>
                    <strong>Role:</strong> <?= htmlspecialchars($_SESSION['user_role']) ?><br>
                    <strong>Date:</strong> <?= date('M j, Y') ?>
                </div>
                <div class="signature-line">
                    Signature & Company Stamp
                </div>
            </div>

            <div class="signature-box">
                <div class="signature-title">DRIVER CONFIRMATION</div>
                <div style="font-size: 10px; margin-bottom: 5px;">
                    <strong>Driver Name:</strong> _________________<br>
                    <strong>Vehicle #:</strong> _________________<br>
                    <strong>Phone:</strong> _________________
                </div>
                <div class="signature-line">
                    Driver Signature & Date
                </div>
            </div>

            <div class="signature-box">
                <div class="signature-title">RECEIVER CONFIRMATION</div>
                <div style="font-size: 10px; margin-bottom: 5px;">
                    <strong>Branch:</strong> <?= htmlspecialchars($transfer['to_branch_name']) ?><br>
                    <strong>Received By:</strong> _________________<br>
                    <strong>Date/Time:</strong> _________________
                </div>
                <div class="signature-line">
                    Branch Operator Signature
                </div>
            </div>
        </div>

        <div class="footer">
            <p><strong>IMPORTANT INSTRUCTIONS:</strong></p>
            <p>1. All items must be checked against this form before signing | 2. Any discrepancies must be noted above signatures | 3. QR code verification required at destination</p>
            <p>4. Keep this form with branch records | 5. Report any issues immediately to headquarters</p>
            <br>
            <p>Generated on: <?= date('Y-m-d H:i:s') ?> | JM Animal Feeds ERP System</p>
        </div>
    </div>

</body>
</html>

<?php
} catch (Exception $e) {
    error_log("Error generating transfer form: " . $e->getMessage());
    exit('Error generating transfer form');
}
?>