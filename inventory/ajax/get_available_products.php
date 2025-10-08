<?php
// File: inventory/ajax/get_available_products.php
// Get available products from HQ for order creation

session_start();
require_once __DIR__ . '/../../controllers/AuthController.php';

header('Content-Type: application/json');

// Check authentication
$authController = new AuthController();
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Role-based access check
if (!in_array($_SESSION['user_role'], ['Administrator', 'Supervisor', 'Branch Operator'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$type = $_GET['type'] ?? '';

if (empty($type)) {
    http_response_code(400);
    echo json_encode(['error' => 'Product type is required']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=jmerp', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $products = [];

    switch ($type) {
        case 'finished':
            // Get finished products with available bags at HQ
            $sql = "SELECT p.id, p.name, p.package_size, p.unit_price,
                           COUNT(pb.id) as available_bags
                    FROM products p
                    LEFT JOIN product_bags pb ON p.id = pb.product_id
                        AND pb.branch_id = 1
                        AND pb.status = 'Sealed'
                    WHERE p.status = 'Active'
                    GROUP BY p.id, p.name, p.package_size, p.unit_price
                    HAVING available_bags > 0
                    ORDER BY p.name";
            break;

        case 'raw':
            // Get raw materials available at HQ
            $sql = "SELECT id, name, unit_of_measure, current_stock, selling_price
                    FROM raw_materials
                    WHERE branch_id = 1
                        AND status = 'Active'
                        AND current_stock > 0
                        AND selling_price > 0
                    ORDER BY name";
            break;

        case 'third_party':
            // Get third party products available at HQ
            $sql = "SELECT id, name, brand, unit_of_measure, package_size,
                           current_stock, selling_price
                    FROM third_party_products
                    WHERE branch_id = 1
                        AND status = 'Active'
                        AND current_stock > 0
                    ORDER BY name";
            break;

        case 'packaging':
            // Get packaging materials available at HQ
            $sql = "SELECT id, name, unit, current_stock, unit_cost as selling_price
                    FROM packaging_materials
                    WHERE branch_id = 1
                        AND status = 'Active'
                        AND current_stock > 0
                    ORDER BY name";
            break;

        default:
            throw new Exception('Invalid product type');
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format results for consistent structure
    foreach ($results as $result) {
        $product = [
            'id' => $result['id'],
            'name' => $result['name'],
            'current_stock' => $result['current_stock'] ?? null,
            'available_bags' => $result['available_bags'] ?? null,
            'unit_of_measure' => $result['unit_of_measure'] ?? $result['unit'] ?? 'pieces',
            'package_size' => $result['package_size'] ?? null,
            'selling_price' => $result['selling_price'] ?? $result['unit_price'] ?? 0
        ];

        // Add type-specific fields
        if ($type === 'third_party') {
            $product['brand'] = $result['brand'];
        }

        $products[] = $product;
    }

    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    error_log("Get available products error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load products']);
}
?>