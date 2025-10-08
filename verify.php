<?php
// File: verify.php
// Public product verification page for QR code scanning
// Shows product authenticity and details to customers

require_once __DIR__ . '/config/database.php';

$serialNumber = isset($_GET['serial']) ? trim($_GET['serial']) : '';
$product = null;
$error = '';

if ($serialNumber) {
    try {
        $conn = Database::getInstance()->getConnection();

        // Get product details with batch information
        $sql = "SELECT pb.id, pb.serial_number, pb.production_date, pb.expiry_date, pb.status,
                       p.name as product_name, p.description as product_description, p.package_size,
                       pb_batch.batch_number, pb_batch.production_cost, pb_batch.actual_yield,
                       pb_batch.formula_id, pb_batch.batch_size,
                       u1.full_name as production_officer, u2.full_name as supervisor
                FROM product_bags pb
                JOIN products p ON pb.product_id = p.id
                LEFT JOIN production_batches pb_batch ON DATE(pb_batch.completed_at) = pb.production_date
                LEFT JOIN users u1 ON pb_batch.production_officer_id = u1.id
                LEFT JOIN users u2 ON pb_batch.supervisor_id = u2.id
                WHERE pb.serial_number = ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$serialNumber]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            // Get formula ingredients with exact ratios for this specific bag
            if ($product['formula_id']) {
                $sql = "SELECT rm.name as material_name, fi.quantity, fi.unit, fi.percentage, rm.description
                        FROM formula_ingredients fi
                        JOIN raw_materials rm ON fi.raw_material_id = rm.id
                        WHERE fi.formula_id = ?
                        ORDER BY fi.quantity DESC";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$product['formula_id']]);
                $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate exact amounts for this specific bag size
                $bagWeight = (float)str_replace('KG', '', $product['package_size']);
                $product['ingredients'] = [];

                // Calculate total formula weight first
                $totalFormulaWeight = 0;
                foreach ($ingredients as $ingredient) {
                    // Convert everything to KG for calculation
                    $weightInKg = $ingredient['unit'] === 'KG' ? $ingredient['quantity'] :
                                 ($ingredient['unit'] === 'Liters' ? $ingredient['quantity'] * 0.9 : $ingredient['quantity']); // Assume 0.9 density for liquids
                    $totalFormulaWeight += $weightInKg;
                }

                foreach ($ingredients as $ingredient) {
                    // Convert ingredient amount to KG equivalent for percentage calculation
                    $ingredientWeightKg = $ingredient['unit'] === 'KG' ? $ingredient['quantity'] :
                                         ($ingredient['unit'] === 'Liters' ? $ingredient['quantity'] * 0.9 : $ingredient['quantity']);

                    // Calculate percentage based on total formula weight
                    $percentageInBag = ($ingredientWeightKg / $totalFormulaWeight) * 100;

                    // Calculate actual amount in this bag
                    $actualAmountInBag = ($percentageInBag / 100) * $bagWeight;

                    // Convert back to original unit if needed
                    if ($ingredient['unit'] === 'Liters' && $ingredientWeightKg > 0) {
                        $actualAmountInBag = $actualAmountInBag / 0.9; // Convert back to liters
                    }

                    $product['ingredients'][] = [
                        'material_name' => $ingredient['material_name'],
                        'percentage_in_bag' => $percentageInBag,
                        'actual_amount' => $actualAmountInBag,
                        'unit' => $ingredient['unit'],
                        'description' => $ingredient['description']
                    ];
                }
            }
        } else {
            $error = 'Product not found. Please check the serial number.';
        }

    } catch (Exception $e) {
        error_log("Error in product verification: " . $e->getMessage());
        $error = 'Error verifying product. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Verification - JM Animal Feeds</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
        }
        .verify-container {
            max-width: 800px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .authentic-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .product-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .ingredients-list {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .feeding-guide {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .search-form {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verify-container">
            <div class="header">
                <h1><i class="bi bi-shield-check"></i> JM ANIMAL FEEDS</h1>
                <h4>Product Verification System</h4>
                <p class="mb-0">Verify the authenticity of your animal feed products</p>
            </div>

            <div class="content">
                <!-- Search Form -->
                <div class="search-form">
                    <form method="GET" action="">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-lg"
                                   name="serial"
                                   placeholder="Enter product serial number (e.g., JM-20240921-1-0001)"
                                   value="<?= htmlspecialchars($serialNumber) ?>">
                            <button class="btn btn-primary btn-lg" type="submit">
                                <i class="bi bi-search"></i> Verify Product
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($error): ?>
                    <div class="error-message">
                        <h5><i class="bi bi-exclamation-triangle"></i> Verification Failed</h5>
                        <p><?= htmlspecialchars($error) ?></p>
                        <small>If you believe this is an error, please contact JM Animal Feeds customer service.</small>
                    </div>
                <?php elseif ($product): ?>

                    <!-- Authentic Product Badge -->
                    <div class="authentic-badge">
                        <h4><i class="bi bi-patch-check-fill"></i> AUTHENTIC PRODUCT VERIFIED</h4>
                        <p class="mb-0">This is a genuine JM Animal Feeds product</p>
                    </div>

                    <!-- Product Information -->
                    <div class="product-info">
                        <h5><i class="bi bi-box-seam"></i> Product Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Product Name:</strong> <?= htmlspecialchars($product['product_name']) ?></p>
                                <p><strong>Package Size:</strong> <?= htmlspecialchars($product['package_size']) ?></p>
                                <p><strong>Serial Number:</strong> <code><?= htmlspecialchars($product['serial_number']) ?></code></p>
                                <p><strong>Production Date:</strong> <?= date('M j, Y', strtotime($product['production_date'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Best Before:</strong>
                                    <span class="<?= strtotime($product['expiry_date']) < time() ? 'text-danger' : 'text-success' ?>">
                                        <?= date('M j, Y', strtotime($product['expiry_date'])) ?>
                                    </span>
                                </p>
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?= $product['status'] === 'Sealed' ? 'success' : 'warning' ?>">
                                        <?= $product['status'] ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <?php if ($product['product_description']): ?>
                        <div class="mt-3">
                            <strong>Product Description:</strong>
                            <p><?= htmlspecialchars($product['product_description']) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Ingredients & Ratios -->
                    <?php if (!empty($product['ingredients'])): ?>
                    <div class="ingredients-list">
                        <h6><i class="bi bi-list-check"></i> Ingredients - Exact Ratios for this <?= htmlspecialchars($product['package_size']) ?> bag</h6>
                        <div class="row">
                            <?php foreach ($product['ingredients'] as $ingredient): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1"><?= htmlspecialchars($ingredient['material_name']) ?></h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-primary"><?= number_format($ingredient['percentage_in_bag'], 1) ?>%</span>
                                            <small class="text-muted"><?= number_format($ingredient['actual_amount'], 2) ?> <?= htmlspecialchars($ingredient['unit']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-info mt-3">
                            <small><i class="bi bi-info-circle"></i>
                            <strong>This shows the exact ingredient composition for your <?= htmlspecialchars($product['package_size']) ?> bag.</strong>
                            Percentages and quantities are calculated based on the production batch formula.
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Feeding Guide -->
                    <div class="feeding-guide">
                        <h6><i class="bi bi-info-circle"></i> How to Use This Product</h6>

                        <?php
                        $feedingInstructions = [
                            'Dairy Cow Feed' => 'Feed 2-4 kg per day mixed with roughage. Provide fresh water at all times.',
                            'Poultry Layers' => 'Feed 120-150g per bird per day. Best results when fed morning and evening.',
                            'Broiler' => 'Feed ad-libitum (free choice). Ensure feeders are always filled.',
                            'Pig' => 'Feed 1.5-3 kg per day depending on animal weight. Mix with water if desired.',
                            'Fish Feed' => 'Feed 2-3% of body weight daily. Feed in small portions 2-3 times per day.'
                        ];

                        $instruction = 'Feed according to animal requirements. Consult with animal nutrition specialist for best results.';
                        foreach ($feedingInstructions as $type => $guide) {
                            if (stripos($product['product_name'], $type) !== false) {
                                $instruction = $guide;
                                break;
                            }
                        }
                        ?>

                        <p class="mb-2"><?= $instruction ?></p>

                        <div class="row">
                            <div class="col-md-6">
                                <small><strong>Storage:</strong> Store in cool, dry place away from direct sunlight.</small>
                            </div>
                            <div class="col-md-6">
                                <small><strong>Shelf Life:</strong> 12 months from production date when stored properly.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Details -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0"><i class="bi bi-shield-check"></i> Verification Confirmed</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><strong>This product is verified as authentic JM Animal Feeds product.</strong></p>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="bg-light p-2 rounded">
                                                <i class="bi bi-check-circle text-success fs-4"></i>
                                                <div class="small">Authentic</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-light p-2 rounded">
                                                <i class="bi bi-calendar-check text-success fs-4"></i>
                                                <div class="small">Fresh</div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-light p-2 rounded">
                                                <i class="bi bi-award text-success fs-4"></i>
                                                <div class="small">Quality</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($serialNumber): ?>
                    <div class="text-center">
                        <p class="text-muted">Enter a product serial number to verify its authenticity.</p>
                    </div>
                <?php endif; ?>

                <!-- Contact Information -->
                <div class="text-center mt-4 pt-3 border-top">
                    <h6>Need Help?</h6>
                    <p class="text-muted mb-2">Contact JM Animal Feeds Customer Service</p>
                    <div class="row">
                        <div class="col-md-4">
                            <small><i class="bi bi-telephone"></i> +255 123 456 789</small>
                        </div>
                        <div class="col-md-4">
                            <small><i class="bi bi-envelope"></i> support@jmfeeds.co.tz</small>
                        </div>
                        <div class="col-md-4">
                            <small><i class="bi bi-clock"></i> Mon-Fri 8AM-6PM</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>