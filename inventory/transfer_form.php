<?php
// File: inventory/transfer_form.php
// Printable transfer form with QR code for A4 paper

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/TransferController.php';

$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$transferId = $_GET['id'] ?? null;
if (!$transferId) {
    die('Transfer ID required');
}

$transferController = new TransferController();
$formData = $transferController->generateTransferForm($transferId);

if (!$formData) {
    die('Transfer not found');
}

$transfer = $formData['transfer'];
$details = $formData['details'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Form - <?php echo $transfer['transfer_number']; ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-title {
            font-size: 16px;
            font-weight: bold;
            background-color: #f0f0f0;
            padding: 5px;
            margin: 10px 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-box {
            border: 1px solid #000;
            padding: 10px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 100px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }

        .signature-box {
            border: 1px solid #000;
            padding: 15px;
            height: 80px;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .qr-section {
            text-align: center;
            margin: 20px 0;
        }

        .qr-code {
            border: 1px solid #000;
            padding: 10px;
            display: inline-block;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">JM ANIMAL FEEDS LTD</div>
        <div>P.O. Box 123, Dar es Salaam, Tanzania | Tel: +255 123 456 789</div>
    </div>

    <div class="form-title">TRANSFER DELIVERY FORM</div>

    <div class="info-grid">
        <div class="info-box">
            <div><span class="info-label">Transfer No:</span> <?php echo $transfer['transfer_number']; ?></div>
            <div><span class="info-label">Date:</span> <?php echo date('d/m/Y', strtotime($transfer['created_at'])); ?></div>
            <div><span class="info-label">From:</span> <?php echo $details['from_branch']; ?></div>
            <div><span class="info-label">To:</span> <?php echo $details['to_branch']; ?></div>
        </div>
        <div class="info-box">
            <div><span class="info-label">Driver:</span> <?php echo $details['driver_name']; ?></div>
            <div><span class="info-label">Phone:</span> <?php echo $details['driver_phone']; ?></div>
            <div><span class="info-label">Status:</span> <?php echo $transfer['status']; ?></div>
        </div>
    </div>

    <?php if (!empty($transfer['bags'])): ?>
    <div class="form-title">FINISHED PRODUCTS (Individual Bags)</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Serial Number</th>
                <th>Product Name</th>
                <th>Package Size</th>
                <th>Production Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transfer['bags'] as $bag): ?>
            <tr>
                <td><?php echo $bag['serial_number']; ?></td>
                <td><?php echo $bag['product_name']; ?></td>
                <td><?php echo $bag['package_size']; ?></td>
                <td><?php echo date('d/m/Y', strtotime($bag['production_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($transfer['items'])): ?>
    <div class="form-title">BULK ITEMS</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Category</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transfer['items'] as $item): ?>
            <tr>
                <td><?php echo $item['category']; ?></td>
                <td><?php echo $item['name']; ?></td>
                <td><?php echo number_format($item['quantity']); ?></td>
                <td><?php echo $item['unit_of_measure']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($transfer['notes']): ?>
    <div class="form-title">NOTES</div>
    <div style="border: 1px solid #000; padding: 10px; margin-bottom: 20px;">
        <?php echo nl2br(htmlspecialchars($transfer['notes'])); ?>
    </div>
    <?php endif; ?>

    <div class="qr-section">
        <div class="form-title">VERIFICATION QR CODE</div>
        <?php if ($transfer['qr_code']): ?>
        <div class="qr-code">
            <img src="<?php echo $transfer['qr_code']; ?>" alt="Transfer QR Code" style="width: 120px; height: 120px;">
        </div>
        <div style="margin-top: 10px; font-size: 10px;">
            Scan this QR code to verify transfer authenticity
        </div>
        <?php endif; ?>
    </div>

    <div class="signatures">
        <div class="signature-box">
            <div class="signature-title">PREPARED BY</div>
            <div>Name: _________________</div>
            <div>Signature: _____________</div>
            <div>Date: _________________</div>
        </div>

        <div class="signature-box">
            <div class="signature-title">DRIVER</div>
            <div>Name: <?php echo $details['driver_name']; ?></div>
            <div>Signature: _____________</div>
            <div>Date: _________________</div>
        </div>

        <div class="signature-box">
            <div class="signature-title">RECEIVED BY</div>
            <div>Name: _________________</div>
            <div>Signature: _____________</div>
            <div>Date: _________________</div>
        </div>
    </div>

    <div style="margin-top: 20px; font-size: 10px; text-align: center;">
        This is an official transfer document. Any alterations will invalidate this form.
        <br>For queries, contact: operations@jmfeeds.co.tz | +255 123 456 789
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; cursor: pointer;">
            🖨️ Print This Form
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; cursor: pointer; margin-left: 10px;">
            ✖️ Close
        </button>
    </div>
</body>
</html>