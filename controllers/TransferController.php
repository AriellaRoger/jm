<?php
// File: controllers/TransferController.php
// Complete transfer management controller for sending bags and bulk items between branches
// Handles bag transfers by serial number and bulk item transfers by quantity

require_once __DIR__ . '/../config/database.php';

class TransferController {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    // Get all available bags at HQ for transfer selection
    public function getAvailableBags() {
        try {
            $sql = "SELECT pb.id, pb.serial_number, pb.production_date, pb.expiry_date,
                           p.name as product_name, p.package_size
                    FROM product_bags pb
                    JOIN products p ON pb.product_id = p.id
                    WHERE pb.branch_id = 1 AND pb.status = 'Sealed'
                    ORDER BY p.name, pb.production_date ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting available bags: " . $e->getMessage());
            return [];
        }
    }

    // Get all available bulk items at HQ (raw materials, third party, packaging)
    public function getAvailableBulkItems() {
        try {
            $items = [];

            // Raw Materials
            $sql = "SELECT id, name, unit_of_measure, current_stock, 'RAW_MATERIAL' as item_type
                    FROM raw_materials
                    WHERE branch_id = 1 AND status = 'Active' AND current_stock > 0
                    ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $items['raw_materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Third Party Products
            $sql = "SELECT id, name, unit_of_measure, current_stock, 'THIRD_PARTY_PRODUCT' as item_type
                    FROM third_party_products
                    WHERE branch_id = 1 AND status = 'Active' AND current_stock > 0
                    ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $items['third_party_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Packaging Materials
            $sql = "SELECT id, name, unit, current_stock, 'PACKAGING_MATERIAL' as item_type
                    FROM packaging_materials
                    WHERE branch_id = 1 AND status = 'Active' AND current_stock > 0
                    ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $items['packaging_materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $items;
        } catch (Exception $e) {
            error_log("Error getting available bulk items: " . $e->getMessage());
            return [];
        }
    }

    // Create new transfer with bags and bulk items
    public function createTransfer($data) {
        try {
            $this->conn->beginTransaction();

            // Generate transfer number
            $transferNumber = $this->generateTransferNumber();

            // Insert transfer record
            $sql = "INSERT INTO transfers (transfer_number, from_branch_id, to_branch_id, created_by, created_at)
                    VALUES (?, 1, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferNumber, $data['to_branch_id'], $data['created_by']]);
            $transferId = $this->conn->lastInsertId();

            // Insert selected bags
            if (!empty($data['selected_bags'])) {
                foreach ($data['selected_bags'] as $bagId) {
                    // Get bag details
                    $bagSql = "SELECT serial_number FROM product_bags WHERE id = ?";
                    $bagStmt = $this->conn->prepare($bagSql);
                    $bagStmt->execute([$bagId]);
                    $bag = $bagStmt->fetch();

                    if ($bag) {
                        $sql = "INSERT INTO transfer_bags (transfer_id, bag_id, serial_number)
                                VALUES (?, ?, ?)";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$transferId, $bagId, $bag['serial_number']]);
                    }
                }
            }

            // Insert bulk items with item name and unit
            if (!empty($data['bulk_items'])) {
                foreach ($data['bulk_items'] as $item) {
                    // Get item details based on type
                    $itemName = '';
                    $itemUnit = '';

                    switch ($item['item_type']) {
                        case 'RAW_MATERIAL':
                            $itemSql = "SELECT name, unit_of_measure FROM raw_materials WHERE id = ?";
                            break;
                        case 'THIRD_PARTY_PRODUCT':
                            $itemSql = "SELECT name, unit_of_measure FROM third_party_products WHERE id = ?";
                            break;
                        case 'PACKAGING_MATERIAL':
                            $itemSql = "SELECT name, unit FROM packaging_materials WHERE id = ?";
                            break;
                    }

                    $itemStmt = $this->conn->prepare($itemSql);
                    $itemStmt->execute([$item['item_id']]);
                    $itemDetails = $itemStmt->fetch();

                    if ($itemDetails) {
                        $itemName = $itemDetails['name'];
                        $itemUnit = isset($itemDetails['unit_of_measure']) ? $itemDetails['unit_of_measure'] : $itemDetails['unit'];
                    }

                    $sql = "INSERT INTO transfer_items (transfer_id, item_type, item_id, item_name, quantity, unit)
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([$transferId, $item['item_type'], $item['item_id'], $itemName, $item['quantity'], $itemUnit]);
                }
            }

            // Log activity
            $this->logActivity($data['created_by'], 'TRANSFER_CREATED', "Transfer $transferNumber created for branch");

            $this->conn->commit();
            return ['success' => true, 'transfer_id' => $transferId, 'transfer_number' => $transferNumber];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating transfer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating transfer'];
        }
    }

    // Get pending transfers for branch confirmation
    public function getPendingTransfers($branchId = null) {
        try {
            $whereClause = $branchId ? "WHERE t.to_branch_id = ? AND t.status = 'PENDING'" : "WHERE t.status = 'PENDING'";

            $sql = "SELECT t.*, b.name as to_branch_name, u.full_name as created_by_name
                    FROM transfers t
                    JOIN branches b ON t.to_branch_id = b.id
                    JOIN users u ON t.created_by = u.id
                    $whereClause
                    ORDER BY t.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            if ($branchId) {
                $stmt->execute([$branchId]);
            } else {
                $stmt->execute();
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting pending transfers: " . $e->getMessage());
            return [];
        }
    }

    // Get transfer details with bags and items
    public function getTransferDetails($transferId) {
        try {
            // Get transfer info
            $sql = "SELECT t.*, b.name as to_branch_name, u1.full_name as created_by_name, u2.full_name as confirmed_by_name
                    FROM transfers t
                    JOIN branches b ON t.to_branch_id = b.id
                    JOIN users u1 ON t.created_by = u1.id
                    LEFT JOIN users u2 ON t.confirmed_by = u2.id
                    WHERE t.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) return null;

            // Get bags
            $sql = "SELECT tb.*, pb.serial_number, pb.production_date, pb.expiry_date, p.name as product_name, p.package_size
                    FROM transfer_bags tb
                    JOIN product_bags pb ON tb.bag_id = pb.id
                    JOIN products p ON pb.product_id = p.id
                    WHERE tb.transfer_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer['bags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get bulk items from transfer_items table
            $sql = "SELECT item_type, item_id, item_name as name, quantity, unit as unit_of_measure,
                           CASE
                               WHEN item_type = 'RAW_MATERIAL' THEN 'Raw Material'
                               WHEN item_type = 'THIRD_PARTY_PRODUCT' THEN 'Third Party Product'
                               WHEN item_type = 'PACKAGING_MATERIAL' THEN 'Packaging Material'
                               ELSE item_type
                           END as category
                    FROM transfer_items
                    WHERE transfer_id = ?
                    ORDER BY item_type, item_name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $transfer;
        } catch (Exception $e) {
            error_log("Error getting transfer details: " . $e->getMessage());
            return null;
        }
    }

    // Confirm transfer and move inventory
    public function confirmTransfer($transferId, $userId) {
        try {
            $this->conn->beginTransaction();

            // Get transfer details
            $transfer = $this->getTransferDetails($transferId);
            if (!$transfer) {
                throw new Exception("Transfer not found");
            }

            $toBranchId = $transfer['to_branch_id'];

            // Move bags to destination branch
            if (!empty($transfer['bags'])) {
                foreach ($transfer['bags'] as $bag) {
                    $sql = "UPDATE product_bags SET branch_id = ? WHERE id = ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([$toBranchId, $bag['bag_id']]);
                }
            }

            // Process bulk items
            if (!empty($transfer['items'])) {
                foreach ($transfer['items'] as $item) {
                    // Reduce stock at HQ
                    $this->reduceHQStock($item['item_type'], $item['item_id'], $item['quantity']);

                    // Add stock at destination branch (create if not exists)
                    $this->addBranchStock($item['item_type'], $item['item_id'], $item['quantity'], $toBranchId);
                }
            }

            // Update transfer status
            $sql = "UPDATE transfers SET status = 'CONFIRMED', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $transferId]);

            // Log activity
            $this->logActivity($userId, 'TRANSFER_CONFIRMED', "Transfer {$transfer['transfer_number']} confirmed");

            $this->conn->commit();
            return ['success' => true, 'message' => 'Transfer confirmed successfully'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error confirming transfer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error confirming transfer'];
        }
    }

    // Reduce stock at HQ for bulk items
    private function reduceHQStock($itemType, $itemId, $quantity) {
        switch ($itemType) {
            case 'RAW_MATERIAL':
                $sql = "UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND branch_id = 1";
                break;
            case 'THIRD_PARTY_PRODUCT':
                $sql = "UPDATE third_party_products SET current_stock = current_stock - ? WHERE id = ? AND branch_id = 1";
                break;
            case 'PACKAGING_MATERIAL':
                $sql = "UPDATE packaging_materials SET current_stock = current_stock - ? WHERE id = ? AND branch_id = 1";
                break;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$quantity, $itemId]);
    }

    // Add stock at destination branch (create if not exists)
    private function addBranchStock($itemType, $itemId, $quantity, $branchId) {
        switch ($itemType) {
            case 'RAW_MATERIAL':
                // Get item name from HQ first
                $hqSql = "SELECT name, description, unit_of_measure, cost_price, selling_price, minimum_stock, supplier, status, created_by
                          FROM raw_materials WHERE id = ? AND branch_id = 1";
                $hqStmt = $this->conn->prepare($hqSql);
                $hqStmt->execute([$itemId]);
                $hqItem = $hqStmt->fetch();

                if ($hqItem) {
                    // Check if item exists at destination branch by name
                    $checkSql = "SELECT id FROM raw_materials WHERE name = ? AND branch_id = ?";
                    $checkStmt = $this->conn->prepare($checkSql);
                    $checkStmt->execute([$hqItem['name'], $branchId]);

                    if ($checkStmt->fetch()) {
                        // Update existing
                        $sql = "UPDATE raw_materials SET current_stock = current_stock + ? WHERE name = ? AND branch_id = ?";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$quantity, $hqItem['name'], $branchId]);
                    } else {
                        // Create new record
                        $sql = "INSERT INTO raw_materials (name, description, unit_of_measure, cost_price, selling_price, current_stock, minimum_stock, supplier, status, branch_id, created_by, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([
                            $hqItem['name'], $hqItem['description'], $hqItem['unit_of_measure'],
                            $hqItem['cost_price'], $hqItem['selling_price'], $quantity,
                            $hqItem['minimum_stock'], $hqItem['supplier'], $hqItem['status'],
                            $branchId, $hqItem['created_by']
                        ]);
                    }
                }
                break;

            case 'THIRD_PARTY_PRODUCT':
                // Get item details from HQ first
                $hqSql = "SELECT name, brand, description, category, unit_of_measure, package_size, cost_price, selling_price, minimum_stock, supplier, status, created_by
                          FROM third_party_products WHERE id = ? AND branch_id = 1";
                $hqStmt = $this->conn->prepare($hqSql);
                $hqStmt->execute([$itemId]);
                $hqItem = $hqStmt->fetch();

                if ($hqItem) {
                    // Check if item exists at destination branch by name
                    $checkSql = "SELECT id FROM third_party_products WHERE name = ? AND branch_id = ?";
                    $checkStmt = $this->conn->prepare($checkSql);
                    $checkStmt->execute([$hqItem['name'], $branchId]);

                    if ($checkStmt->fetch()) {
                        // Update existing
                        $sql = "UPDATE third_party_products SET current_stock = current_stock + ? WHERE name = ? AND branch_id = ?";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$quantity, $hqItem['name'], $branchId]);
                    } else {
                        // Create new record
                        $sql = "INSERT INTO third_party_products (name, brand, description, category, unit_of_measure, package_size, cost_price, selling_price, current_stock, minimum_stock, supplier, status, branch_id, created_by, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([
                            $hqItem['name'], $hqItem['brand'], $hqItem['description'], $hqItem['category'],
                            $hqItem['unit_of_measure'], $hqItem['package_size'], $hqItem['cost_price'],
                            $hqItem['selling_price'], $quantity, $hqItem['minimum_stock'],
                            $hqItem['supplier'], $hqItem['status'], $branchId, $hqItem['created_by']
                        ]);
                    }
                }
                break;

            case 'PACKAGING_MATERIAL':
                // Get item details from HQ first
                $hqSql = "SELECT name, description, unit, minimum_stock, unit_cost, supplier, status, created_by
                          FROM packaging_materials WHERE id = ? AND branch_id = 1";
                $hqStmt = $this->conn->prepare($hqSql);
                $hqStmt->execute([$itemId]);
                $hqItem = $hqStmt->fetch();

                if ($hqItem) {
                    // Check if item exists at destination branch by name
                    $checkSql = "SELECT id FROM packaging_materials WHERE name = ? AND branch_id = ?";
                    $checkStmt = $this->conn->prepare($checkSql);
                    $checkStmt->execute([$hqItem['name'], $branchId]);

                    if ($checkStmt->fetch()) {
                        // Update existing
                        $sql = "UPDATE packaging_materials SET current_stock = current_stock + ? WHERE name = ? AND branch_id = ?";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$quantity, $hqItem['name'], $branchId]);
                    } else {
                        // Create new record
                        $sql = "INSERT INTO packaging_materials (name, description, unit, current_stock, minimum_stock, unit_cost, supplier, status, branch_id, created_by, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([
                            $hqItem['name'], $hqItem['description'], $hqItem['unit'], $quantity,
                            $hqItem['minimum_stock'], $hqItem['unit_cost'], $hqItem['supplier'],
                            $hqItem['status'], $branchId, $hqItem['created_by']
                        ]);
                    }
                }
                break;
        }
    }

    // Generate unique transfer number
    private function generateTransferNumber() {
        $prefix = 'TR' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM transfers WHERE transfer_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['count'];
        return $prefix . sprintf('%04d', $count + 1);
    }

    // Log activity
    private function logActivity($userId, $action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }

    // Get all branches except HQ
    public function getBranches() {
        try {
            $sql = "SELECT id, name FROM branches WHERE id != 1 ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting branches: " . $e->getMessage());
            return [];
        }
    }

    // Get comprehensive transfer details for printing with all items and serial numbers
    public function getTransferForPrint($transferId) {
        try {
            // Get basic transfer details
            $sql = "SELECT t.*,
                           fb.name as from_branch_name, tb.name as to_branch_name,
                           u.full_name as created_by_name
                    FROM transfers t
                    JOIN branches fb ON t.from_branch_id = fb.id
                    JOIN branches tb ON t.to_branch_id = tb.id
                    JOIN users u ON t.created_by = u.id
                    WHERE t.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return null;
            }

            // Get transfer bags with product details
            $sql = "SELECT tb.bag_id, tb.serial_number,
                           p.name as product_name, p.package_size,
                           pb.production_date, pb.expiry_date
                    FROM transfer_bags tb
                    JOIN product_bags pb ON tb.bag_id = pb.id
                    JOIN products p ON pb.product_id = p.id
                    WHERE tb.transfer_id = ?
                    ORDER BY p.name, tb.serial_number";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer['bags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get transfer bulk items
            $sql = "SELECT item_type, item_id, item_name, quantity, unit
                    FROM transfer_items
                    WHERE transfer_id = ?
                    ORDER BY item_type, item_name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $transfer['bulk_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $transfer;

        } catch (Exception $e) {
            error_log("Error getting transfer for print: " . $e->getMessage());
            return null;
        }
    }

    // Create transfer with driver assignment and QR code
    public function createTransferWithDriver($data) {
        try {
            $this->conn->beginTransaction();

            // Generate transfer number
            $transferNumber = $this->generateTransferNumber();

            // Insert transfer record with driver
            $sql = "INSERT INTO transfers (transfer_number, from_branch_id, to_branch_id, driver_id, created_by, notes, created_at)
                    VALUES (?, 1, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferNumber, $data['to_branch_id'], $data['driver_id'], $data['created_by'], $data['notes']]);
            $transferId = $this->conn->lastInsertId();

            // Insert selected bags
            if (!empty($data['selected_bags'])) {
                foreach ($data['selected_bags'] as $bagId) {
                    $bagSql = "SELECT serial_number FROM product_bags WHERE id = ?";
                    $bagStmt = $this->conn->prepare($bagSql);
                    $bagStmt->execute([$bagId]);
                    $bag = $bagStmt->fetch();

                    if ($bag) {
                        $sql = "INSERT INTO transfer_bags (transfer_id, bag_id, serial_number)
                                VALUES (?, ?, ?)";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$transferId, $bagId, $bag['serial_number']]);
                    }
                }
            }

            // Insert bulk items
            if (!empty($data['selected_items'])) {
                foreach ($data['selected_items'] as $item) {
                    $sql = "INSERT INTO transfer_items (transfer_id, item_type, item_id, item_name, quantity, unit)
                            VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        $transferId,
                        $item['type'],
                        $item['id'],
                        $item['name'],
                        $item['quantity'],
                        $item['unit']
                    ]);
                }
            }

            // Generate QR code
            $qrCode = $this->generateTransferQR($transferId, $transferNumber);

            // Update transfer with QR code
            $sql = "UPDATE transfers SET qr_code = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$qrCode, $transferId]);

            // Log activity
            $this->logActivity($data['created_by'], 'TRANSFER_CREATED', "Transfer $transferNumber created");

            $this->conn->commit();

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'transfer_number' => $transferNumber,
                'qr_code' => $qrCode
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating transfer with driver: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating transfer'];
        }
    }

    // Generate QR code for transfer
    private function generateTransferQR($transferId, $transferNumber) {
        try {
            require_once __DIR__ . '/../phpqrcode/qrlib.php';

            $qrData = "TRANSFER|$transferNumber|ID:$transferId|VERIFY:" . base64_encode($transferId);
            $qrFile = "transfer_qr_$transferId.png";
            $qrPath = __DIR__ . "/../assets/qr_codes/$qrFile";

            // Create directory if it doesn't exist
            $qrDir = dirname($qrPath);
            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0755, true);
            }

            QRcode::png($qrData, $qrPath, QR_ECLEVEL_L, 4);

            return BASE_URL . "/assets/qr_codes/$qrFile";
        } catch (Exception $e) {
            error_log("Error generating QR code: " . $e->getMessage());
            return null;
        }
    }

    // Get drivers for selection
    public function getAvailableDrivers() {
        try {
            $sql = "SELECT id, full_name, phone FROM users
                    WHERE role_id = (SELECT id FROM user_roles WHERE role_name = 'Driver')
                    AND status = 'ACTIVE'
                    ORDER BY full_name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting drivers: " . $e->getMessage());
            return [];
        }
    }

    // Generate transfer form for printing
    public function generateTransferForm($transferId) {
        try {
            $transfer = $this->getTransferDetails($transferId);
            if (!$transfer) {
                return null;
            }

            // Get driver and branch information
            $sql = "SELECT u.full_name as driver_name, u.phone as driver_phone,
                           b1.name as from_branch, b2.name as to_branch
                    FROM transfers t
                    JOIN users u ON t.driver_id = u.id
                    JOIN branches b1 ON t.from_branch_id = b1.id
                    JOIN branches b2 ON t.to_branch_id = b2.id
                    WHERE t.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$transferId]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'transfer' => $transfer,
                'details' => $details
            ];
        } catch (Exception $e) {
            error_log("Error generating transfer form: " . $e->getMessage());
            return null;
        }
    }
}
?>