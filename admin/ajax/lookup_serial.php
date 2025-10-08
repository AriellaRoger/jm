<?php
// File: admin/ajax/lookup_serial.php
// AJAX handler for admin serial number lookup
// Administrator access only

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

// Check authentication and admin access only
$authController = new AuthController();
if (!$authController->isLoggedIn() || $_SESSION['user_role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['serial_number'])) {
    echo json_encode(['success' => false, 'message' => 'Serial number is required']);
    exit;
}

$serialNumber = trim($input['serial_number']);
if (empty($serialNumber)) {
    echo json_encode(['success' => false, 'message' => 'Serial number cannot be empty']);
    exit;
}

try {
    $conn = Database::getInstance()->getConnection();

    // Debug: Log the lookup attempt
    error_log("Admin lookup attempt for serial: $serialNumber");

    // First, check if bag exists at all
    $checkSql = "SELECT COUNT(*) as count FROM product_bags WHERE serial_number = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$serialNumber]);
    $bagExists = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ($bagExists['count'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Serial number not found in our system']);
        exit;
    }

    // Get basic bag details first (simplified query)
    $sql = "SELECT pb.id, pb.serial_number, pb.production_date, pb.expiry_date, pb.status,
                   pb.created_at as bag_created_at, pb.branch_id,
                   p.name as product_name, p.description as product_description,
                   p.package_size, p.unit_price, p.cost_price,
                   b.name as branch_name
            FROM product_bags pb
            JOIN products p ON pb.product_id = p.id
            JOIN branches b ON pb.branch_id = b.id
            WHERE pb.serial_number = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$serialNumber]);
    $bag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bag) {
        echo json_encode(['success' => false, 'message' => 'Bag details not found']);
        exit;
    }

    // Get production batch details if available
    $batchSql = "SELECT batch.id, batch.batch_number, batch.formula_id, batch.batch_size,
                        batch.expected_yield, batch.actual_yield, batch.wastage_percentage,
                        batch.production_cost, batch.started_at, batch.completed_at, batch.status as batch_status,
                        batch.production_officer_id, batch.supervisor_id,
                        f.name as formula_name, f.description as formula_description, f.target_yield,
                        po.full_name as production_officer, po.email as po_email,
                        sup.full_name as supervisor, sup.email as sup_email
                 FROM production_batches batch
                 LEFT JOIN formulas f ON batch.formula_id = f.id
                 LEFT JOIN users po ON batch.production_officer_id = po.id
                 LEFT JOIN users sup ON batch.supervisor_id = sup.id
                 WHERE DATE(batch.completed_at) = ?
                 ORDER BY batch.completed_at DESC
                 LIMIT 1";

    $batchStmt = $conn->prepare($batchSql);
    $batchStmt->execute([$bag['production_date']]);
    $batchInfo = $batchStmt->fetch(PDO::FETCH_ASSOC);

    // Merge batch info with bag info
    if ($batchInfo) {
        $bag = array_merge($bag, $batchInfo);
    }

    if (!$bag) {
        echo json_encode(['success' => false, 'message' => 'Serial number not found in our system']);
        exit;
    }

    // Get formula ingredients if available
    $ingredients = [];
    if ($bag['formula_id']) {
        $sql = "SELECT rm.name as material_name, fi.quantity, fi.unit, fi.percentage,
                       rm.cost_price, (fi.quantity * rm.cost_price) as ingredient_cost
                FROM formula_ingredients fi
                JOIN raw_materials rm ON fi.raw_material_id = rm.id
                WHERE fi.formula_id = ?
                ORDER BY fi.quantity DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$bag['formula_id']]);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get packaging materials used in batch if available
    $packaging = [];
    if ($bag['batch_number']) {
        $sql = "SELECT bp.packaging_material_id, pm.name as packaging_name,
                       bp.packaging_cost, bp.bags_produced, bp.total_weight
                FROM production_batch_products bp
                JOIN packaging_materials pm ON bp.packaging_material_id = pm.id
                WHERE bp.batch_id = (SELECT id FROM production_batches WHERE batch_number = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$bag['batch_number']]);
        $packaging = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if bag has been opened
    $openedInfo = null;
    $sql = "SELECT ob.original_weight_kg, ob.current_weight_kg, ob.selling_price_per_kg,
                   ob.opened_at, u.full_name as opened_by_name
            FROM opened_bags ob
            JOIN users u ON ob.opened_by = u.id
            WHERE ob.serial_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$serialNumber]);
    $openedInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Generate detailed HTML response
    $html = generateBagDetailsHTML($bag, $ingredients, $packaging, $openedInfo);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'bag_data' => $bag
    ]);

} catch (Exception $e) {
    error_log("Error in serial lookup: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error occurred']);
}

function generateBagDetailsHTML($bag, $ingredients, $packaging, $openedInfo) {
    $html = '<div class="row">';

    // Basic Bag Information
    $html .= '<div class="col-md-6 mb-4">
        <h6 class="text-primary"><i class="bi bi-box-seam"></i> Bag Information</h6>
        <table class="table table-sm">
            <tr><td><strong>Serial Number:</strong></td><td><code>' . htmlspecialchars($bag['serial_number']) . '</code></td></tr>
            <tr><td><strong>Product:</strong></td><td>' . htmlspecialchars($bag['product_name']) . '</td></tr>
            <tr><td><strong>Package Size:</strong></td><td>' . htmlspecialchars($bag['package_size']) . '</td></tr>
            <tr><td><strong>Status:</strong></td><td><span class="badge bg-' . ($bag['status'] === 'Sealed' ? 'success' : 'warning') . '">' . $bag['status'] . '</span></td></tr>
            <tr><td><strong>Production Date:</strong></td><td>' . date('M j, Y', strtotime($bag['production_date'])) . '</td></tr>
            <tr><td><strong>Expiry Date:</strong></td><td>' . date('M j, Y', strtotime($bag['expiry_date'])) . '</td></tr>
            <tr><td><strong>Current Location:</strong></td><td>' . htmlspecialchars($bag['branch_name']) . '</td></tr>
            <tr><td><strong>Selling Price:</strong></td><td>TZS ' . number_format($bag['unit_price'], 2) . '</td></tr>
            <tr><td><strong>Production Cost:</strong></td><td>TZS ' . number_format($bag['cost_price'], 2) . '</td></tr>
        </table>
    </div>';

    // Responsible Officers
    $html .= '<div class="col-md-6 mb-4">
        <h6 class="text-danger"><i class="bi bi-people-fill"></i> Responsible Officers</h6>
        <table class="table table-sm">';


    if ($bag['production_officer']) {
        $html .= '<tr><td><strong>Production Officer:</strong></td><td>' . htmlspecialchars($bag['production_officer']) . '<br><small>' . htmlspecialchars($bag['po_email']) . '</small></td></tr>';
    }

    if ($bag['supervisor']) {
        $html .= '<tr><td><strong>Supervisor:</strong></td><td>' . htmlspecialchars($bag['supervisor']) . '<br><small>' . htmlspecialchars($bag['sup_email']) . '</small></td></tr>';
    }

    $html .= '</table>
    </div>';

    // Production Batch Details
    if ($bag['batch_number']) {
        $html .= '<div class="col-md-12 mb-4">
            <h6 class="text-success"><i class="bi bi-gear-fill"></i> Production Batch Details</h6>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td><strong>Batch Number:</strong></td><td>' . htmlspecialchars($bag['batch_number']) . '</td></tr>
                        <tr><td><strong>Formula:</strong></td><td>' . htmlspecialchars($bag['formula_name']) . '</td></tr>
                        <tr><td><strong>Batch Size:</strong></td><td>' . number_format($bag['batch_size'], 1) . ' KG</td></tr>
                        <tr><td><strong>Expected Yield:</strong></td><td>' . number_format($bag['expected_yield'], 1) . ' KG</td></tr>
                        <tr><td><strong>Actual Yield:</strong></td><td>' . number_format($bag['actual_yield'], 1) . ' KG</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><td><strong>Wastage:</strong></td><td>' . number_format($bag['wastage_percentage'], 2) . '%</td></tr>
                        <tr><td><strong>Total Cost:</strong></td><td>TZS ' . number_format($bag['production_cost'], 2) . '</td></tr>
                        <tr><td><strong>Started:</strong></td><td>' . ($bag['started_at'] ? date('M j, Y H:i', strtotime($bag['started_at'])) : 'N/A') . '</td></tr>
                        <tr><td><strong>Completed:</strong></td><td>' . ($bag['completed_at'] ? date('M j, Y H:i', strtotime($bag['completed_at'])) : 'N/A') . '</td></tr>
                        <tr><td><strong>Batch Status:</strong></td><td><span class="badge bg-success">' . $bag['batch_status'] . '</span></td></tr>
                    </table>
                </div>
            </div>
        </div>';
    }

    // Formula Ingredients
    if (!empty($ingredients)) {
        $html .= '<div class="col-md-6 mb-4">
            <h6 class="text-info"><i class="bi bi-list-check"></i> Formula Ingredients</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost</th>
                        </tr>
                    </thead>
                    <tbody>';

        foreach ($ingredients as $ingredient) {
            $html .= '<tr>
                <td>' . htmlspecialchars($ingredient['material_name']) . '</td>
                <td>' . number_format($ingredient['quantity'], 1) . '</td>
                <td>' . htmlspecialchars($ingredient['unit']) . '</td>
                <td>TZS ' . number_format($ingredient['ingredient_cost'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>
            </div>
        </div>';
    }

    // Packaging Information
    if (!empty($packaging)) {
        $html .= '<div class="col-md-6 mb-4">
            <h6 class="text-warning"><i class="bi bi-archive"></i> Packaging Materials</h6>
            <table class="table table-sm">';

        foreach ($packaging as $pack) {
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($pack['packaging_name']) . ':</strong></td>
                <td>TZS ' . number_format($pack['packaging_cost'], 2) . '</td>
            </tr>';
        }

        $html .= '</table>
        </div>';
    }

    // Opened Bag Information
    if ($openedInfo) {
        $remaining = $openedInfo['current_weight_kg'];
        $original = $openedInfo['original_weight_kg'];
        $percentage = ($remaining / $original) * 100;

        $html .= '<div class="col-md-12 mb-4">
            <div class="alert alert-warning">
                <h6><i class="bi bi-exclamation-triangle"></i> Bag Opening Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Opened By:</strong> ' . htmlspecialchars($openedInfo['opened_by_name']) . '</p>
                        <p><strong>Opened Date:</strong> ' . date('M j, Y H:i', strtotime($openedInfo['opened_at'])) . '</p>
                        <p><strong>Selling Price:</strong> TZS ' . number_format($openedInfo['selling_price_per_kg'], 2) . ' per KG</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Original Weight:</strong> ' . number_format($original, 2) . ' KG</p>
                        <p><strong>Remaining Weight:</strong> ' . number_format($remaining, 2) . ' KG (' . number_format($percentage, 1) . '%)</p>
                        <p><strong>Current Value:</strong> TZS ' . number_format($remaining * $openedInfo['selling_price_per_kg'], 2) . '</p>
                    </div>
                </div>
            </div>
        </div>';
    }

    // QR Code Section
    $qrPath = BASE_URL . '/assets/qrcodes/bag_' . $bag['serial_number'] . '.png';
    $verifyUrl = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/verify.php?serial=' . urlencode($bag['serial_number']);

    $html .= '<div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h6><i class="bi bi-qr-code"></i> QR Code & Verification</h6>
            </div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-md-6">
                        <img src="' . $qrPath . '" alt="QR Code" style="width: 150px; height: 150px;" class="mb-3">
                        <p><small>QR Code for this bag</small></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Public Verification URL:</strong></p>
                        <p><a href="' . $verifyUrl . '" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-link-45deg"></i> Open Verification Page
                        </a></p>
                        <p><small class="text-muted">Customers can scan the QR code or visit this URL to verify product authenticity</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>';

    $html .= '</div>';

    return $html;
}
?>