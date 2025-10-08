<?php
// File: controllers/ProductionController.php
// Production management controller for creating and managing production batches
// Handles formula-based production, batch tracking, and finished product generation

require_once __DIR__ . '/../config/database.php';

class ProductionController {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    // Get all active formulas for production selection
    public function getActiveFormulas() {
        try {
            $sql = "SELECT f.*, u.full_name as created_by_name,
                           COUNT(fi.id) as ingredient_count,
                           (SELECT SUM(fi.quantity * rm.cost_price)
                            FROM formula_ingredients fi
                            JOIN raw_materials rm ON fi.raw_material_id = rm.id
                            WHERE fi.formula_id = f.id AND rm.branch_id = 1) as total_cost
                    FROM formulas f
                    JOIN users u ON f.created_by = u.id
                    LEFT JOIN formula_ingredients fi ON f.id = fi.formula_id
                    WHERE f.status = 'Active'
                    GROUP BY f.id
                    ORDER BY f.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $formulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate cost per KG for each formula
            foreach ($formulas as &$formula) {
                $formula['cost_per_kg'] = $formula['target_yield'] > 0 ?
                    $formula['total_cost'] / $formula['target_yield'] : 0;
            }

            return $formulas;
        } catch (Exception $e) {
            error_log("Error getting active formulas: " . $e->getMessage());
            return [];
        }
    }

    // Get formula details with availability check
    public function getFormulaForProduction($formulaId, $batchSize = 1) {
        try {
            // Get formula details
            $sql = "SELECT f.*, u.full_name as created_by_name
                    FROM formulas f
                    JOIN users u ON f.created_by = u.id
                    WHERE f.id = ? AND f.status = 'Active'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$formulaId]);
            $formula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$formula) return null;

            // Get ingredients with availability check
            $sql = "SELECT fi.*, rm.name as raw_material_name, rm.unit_of_measure,
                           rm.current_stock, rm.cost_price,
                           (fi.quantity * ?) as required_quantity,
                           (fi.quantity * ? * rm.cost_price) as total_cost
                    FROM formula_ingredients fi
                    JOIN raw_materials rm ON fi.raw_material_id = rm.id
                    WHERE fi.formula_id = ? AND rm.branch_id = 1
                    ORDER BY fi.id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchSize, $batchSize, $formulaId]);
            $formula['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Check availability
            $formula['available'] = true;
            $formula['total_yield'] = $formula['target_yield'] * $batchSize;
            $formula['total_cost'] = 0;

            foreach ($formula['ingredients'] as &$ingredient) {
                $ingredient['available'] = $ingredient['current_stock'] >= $ingredient['required_quantity'];
                $ingredient['shortage'] = max(0, $ingredient['required_quantity'] - $ingredient['current_stock']);

                if (!$ingredient['available']) {
                    $formula['available'] = false;
                }

                $formula['total_cost'] += $ingredient['total_cost'];
            }

            $formula['cost_per_kg'] = $formula['total_yield'] > 0 ?
                $formula['total_cost'] / $formula['total_yield'] : 0;

            return $formula;
        } catch (Exception $e) {
            error_log("Error getting formula for production: " . $e->getMessage());
            return null;
        }
    }

    // Get production officers (users with Production role)
    public function getProductionOfficers() {
        try {
            $sql = "SELECT u.id, u.full_name, u.username, b.name as branch_name
                    FROM users u
                    JOIN user_roles ur ON u.role_id = ur.id
                    JOIN branches b ON u.branch_id = b.id
                    WHERE ur.role_name = 'Production' AND u.status = 'ACTIVE'
                    ORDER BY u.full_name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting production officers: " . $e->getMessage());
            return [];
        }
    }

    // Create new production batch
    public function createBatch($data) {
        try {
            $this->conn->beginTransaction();

            // Generate batch number
            $batchNumber = $this->generateBatchNumber();

            // Get formula details
            $formula = $this->getFormulaForProduction($data['formula_id'], $data['batch_size']);
            if (!$formula || !$formula['available']) {
                throw new Exception("Formula not available for production");
            }

            // Create production batch
            $sql = "INSERT INTO production_batches (batch_number, formula_id, batch_size, expected_yield,
                                                   production_officer_id, supervisor_id, production_cost, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $batchNumber,
                $data['formula_id'],
                $data['batch_size'],
                $formula['total_yield'],
                $data['production_officer_id'],
                $data['supervisor_id'],
                $formula['total_cost']
            ]);
            $batchId = $this->conn->lastInsertId();

            // Insert batch materials
            foreach ($formula['ingredients'] as $ingredient) {
                $sql = "INSERT INTO production_batch_materials (batch_id, raw_material_id, planned_quantity, unit_cost, total_cost)
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    $batchId,
                    $ingredient['raw_material_id'],
                    $ingredient['required_quantity'],
                    $ingredient['cost_price'],
                    $ingredient['total_cost']
                ]);
            }

            // Log batch creation
            $this->logProductionAction($batchId, 'BATCH_CREATED', $data['supervisor_id'],
                "Production batch {$batchNumber} created for formula: {$formula['name']}");

            $this->conn->commit();
            return ['success' => true, 'batch_id' => $batchId, 'batch_number' => $batchNumber];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating production batch: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Start production batch
    public function startProduction($batchId, $userId) {
        try {
            $this->conn->beginTransaction();

            // Update batch status and start time
            $sql = "UPDATE production_batches
                    SET status = 'IN_PROGRESS', started_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND status = 'PLANNED'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Batch not found or not in planned status");
            }

            // Deduct raw materials from HQ stock
            $this->deductRawMaterials($batchId);

            // Log production start
            $this->logProductionAction($batchId, 'PRODUCTION_STARTED', $userId,
                "Production started - raw materials deducted from HQ stock");

            $this->conn->commit();
            return ['success' => true, 'message' => 'Production started successfully'];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error starting production: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Pause production batch
    public function pauseProduction($batchId, $userId, $reason = '') {
        try {
            $sql = "UPDATE production_batches
                    SET status = 'PAUSED', paused_at = NOW(), updated_at = NOW()
                    WHERE id = ? AND status = 'IN_PROGRESS'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Batch not found or not in progress");
            }

            $this->logProductionAction($batchId, 'PRODUCTION_PAUSED', $userId,
                "Production paused" . ($reason ? ": {$reason}" : ""));

            return ['success' => true, 'message' => 'Production paused successfully'];

        } catch (Exception $e) {
            error_log("Error pausing production: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Resume production batch
    public function resumeProduction($batchId, $userId) {
        try {
            $sql = "UPDATE production_batches
                    SET status = 'IN_PROGRESS', paused_at = NULL, updated_at = NOW()
                    WHERE id = ? AND status = 'PAUSED'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Batch not found or not paused");
            }

            $this->logProductionAction($batchId, 'PRODUCTION_RESUMED', $userId, "Production resumed");

            return ['success' => true, 'message' => 'Production resumed successfully'];

        } catch (Exception $e) {
            error_log("Error resuming production: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Complete production with packaging
    public function completeProduction($batchId, $userId, $packagingData, $actualYield, $wastagePercent = 0) {
        try {
            $this->conn->beginTransaction();

            // Update batch completion
            $sql = "UPDATE production_batches
                    SET status = 'COMPLETED', completed_at = NOW(), actual_yield = ?,
                        wastage_percentage = ?, updated_at = NOW()
                    WHERE id = ? AND status IN ('IN_PROGRESS', 'PAUSED')";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$actualYield, $wastagePercent, $batchId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Batch not found or not in progress/paused");
            }

            // Get batch details
            $batch = $this->getBatchDetails($batchId);
            $totalPackagingCost = 0;

            // Process packaging and create bags
            foreach ($packagingData as $package) {
                $packageWeight = (float)str_replace('KG', '', $package['package_size']);
                $bagsProduced = $package['bags_count'];
                $totalWeight = $packageWeight * $bagsProduced;

                // Get packaging material cost
                $sql = "SELECT unit_cost FROM packaging_materials WHERE id = ? AND branch_id = 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$package['packaging_material_id']]);
                $packagingCost = $stmt->fetchColumn() * $bagsProduced;
                $totalPackagingCost += $packagingCost;

                // Insert batch product record
                $sql = "INSERT INTO production_batch_products (batch_id, product_id, package_size, bags_produced,
                                                             total_weight, packaging_material_id, packaging_cost)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    $batchId, $package['product_id'], $package['package_size'],
                    $bagsProduced, $totalWeight, $package['packaging_material_id'], $packagingCost
                ]);

                // Create individual bags with serial numbers and QR codes
                $this->createProductBags($package['product_id'], $package['package_size'], $bagsProduced, $batchId);

                // Deduct packaging materials
                $sql = "UPDATE packaging_materials
                        SET current_stock = current_stock - ?
                        WHERE id = ? AND branch_id = 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$bagsProduced, $package['packaging_material_id']]);
            }

            // Update total production cost including packaging
            $sql = "UPDATE production_batches
                    SET production_cost = production_cost + ?
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$totalPackagingCost, $batchId]);

            // Log completion using legacy method
            $this->logProductionAction($batchId, 'PRODUCTION_COMPLETED', $userId,
                "Production completed - {$actualYield} KG produced with {$wastagePercent}% wastage");

            // Get created bags for this specific batch only
            $sql = "SELECT pb.id, pb.serial_number, pb.production_date, pb.expiry_date,
                           p.name as product_name, p.package_size
                    FROM product_bags pb
                    JOIN products p ON pb.product_id = p.id
                    JOIN production_batch_products pbp ON p.id = pbp.product_id
                    WHERE pbp.batch_id = ? AND pb.branch_id = 1
                    AND pb.production_date = CURDATE()
                    AND p.package_size = pbp.package_size
                    ORDER BY pb.id DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            $createdBags = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->conn->commit();
            return [
                'success' => true,
                'message' => 'Production completed successfully',
                'batch_id' => $batchId,
                'created_bags' => $createdBags
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error completing production: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production batches
    public function getProductionBatches($status = null, $limit = 50) {
        try {
            $whereClause = $status ? "WHERE pb.status = ?" : "";

            $sql = "SELECT pb.*, f.name as formula_name, f.target_yield as formula_yield,
                           u1.full_name as production_officer_name,
                           u2.full_name as supervisor_name,
                           TIMESTAMPDIFF(MINUTE, pb.started_at,
                               COALESCE(pb.completed_at, pb.paused_at, NOW())) as duration_minutes
                    FROM production_batches pb
                    JOIN formulas f ON pb.formula_id = f.id
                    JOIN users u1 ON pb.production_officer_id = u1.id
                    JOIN users u2 ON pb.supervisor_id = u2.id
                    {$whereClause}
                    ORDER BY pb.created_at DESC
                    LIMIT ?";

            $stmt = $this->conn->prepare($sql);
            $params = $status ? [$status, $limit] : [$limit];
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting production batches: " . $e->getMessage());
            return [];
        }
    }

    // Get detailed batch information
    public function getBatchDetails($batchId) {
        try {
            // Get batch info
            $sql = "SELECT pb.*, f.name as formula_name, f.description as formula_description,
                           u1.full_name as production_officer_name,
                           u2.full_name as supervisor_name
                    FROM production_batches pb
                    JOIN formulas f ON pb.formula_id = f.id
                    JOIN users u1 ON pb.production_officer_id = u1.id
                    JOIN users u2 ON pb.supervisor_id = u2.id
                    WHERE pb.id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch) return null;

            // Get materials used
            $sql = "SELECT pbm.*, rm.name as material_name, rm.unit_of_measure
                    FROM production_batch_materials pbm
                    JOIN raw_materials rm ON pbm.raw_material_id = rm.id
                    WHERE pbm.batch_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            $batch['materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get products produced
            $sql = "SELECT pbp.*, p.name as product_name, pm.name as packaging_material_name
                    FROM production_batch_products pbp
                    JOIN products p ON pbp.product_id = p.id
                    JOIN packaging_materials pm ON pbp.packaging_material_id = pm.id
                    WHERE pbp.batch_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            $batch['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get production logs
            $sql = "SELECT pl.*, u.full_name as user_name
                    FROM production_logs pl
                    JOIN users u ON pl.performed_by = u.id
                    WHERE pl.batch_id = ?
                    ORDER BY pl.created_at ASC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            $batch['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $batch;

        } catch (Exception $e) {
            error_log("Error getting batch details: " . $e->getMessage());
            return null;
        }
    }

    // Generate batch number
    private function generateBatchNumber() {
        $prefix = 'PB' . date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM production_batches WHERE batch_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['count'];
        return $prefix . sprintf('%04d', $count + 1);
    }

    // Deduct raw materials from HQ stock
    private function deductRawMaterials($batchId) {
        $sql = "SELECT pbm.raw_material_id, pbm.planned_quantity
                FROM production_batch_materials pbm
                WHERE pbm.batch_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$batchId]);
        $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($materials as $material) {
            $sql = "UPDATE raw_materials
                    SET current_stock = current_stock - ?
                    WHERE id = ? AND branch_id = 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$material['planned_quantity'], $material['raw_material_id']]);

            // Update actual quantity used
            $sql = "UPDATE production_batch_materials
                    SET actual_quantity = planned_quantity
                    WHERE batch_id = ? AND raw_material_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId, $material['raw_material_id']]);
        }
    }

    // Create product bags with serial numbers and QR codes
    private function createProductBags($productId, $packageSize, $bagsCount, $batchId) {
        // Get the current highest bag number for this batch to avoid duplicates
        $sql = "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(serial_number, '-', -1) AS UNSIGNED)), 0) as max_bag_number
                FROM product_bags pb
                JOIN production_batch_products pbp ON pb.product_id = pbp.product_id
                WHERE pbp.batch_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$batchId]);
        $maxBagNumber = $stmt->fetchColumn();

        for ($i = 1; $i <= $bagsCount; $i++) {
            // Generate serial number using batch number with incremental bag number
            $bagNumber = $maxBagNumber + $i;
            $serialNumber = $this->generateSerialNumber($batchId, $bagNumber);

            // Create bag record
            $sql = "INSERT INTO product_bags (product_id, serial_number, branch_id, production_date, expiry_date, status)
                    VALUES (?, ?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 12 MONTH), 'Sealed')";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$productId, $serialNumber]);

            // Generate QR code for the bag
            $this->generateQRCode($serialNumber, $batchId);
        }
    }

    // Generate serial number for bags using batch number
    private function generateSerialNumber($batchId, $bagNumber) {
        // Get the batch number for this batch ID
        $sql = "SELECT batch_number FROM production_batches WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$batchId]);
        $batchNumber = $stmt->fetchColumn();

        if (!$batchNumber) {
            throw new Exception("Batch not found for ID: $batchId");
        }

        // Format: JM-BATCH_NUMBER-BAG_NUMBER (e.g., JM-PB202509260016-001)
        return 'JM-' . $batchNumber . '-' . sprintf('%03d', $bagNumber);
    }

    // Generate QR code for bag
    private function generateQRCode($serialNumber, $batchId) {
        // Create QR code directory if it doesn't exist
        $qrDir = __DIR__ . '/../assets/qrcodes/';
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }

        // QR code data
        $qrData = json_encode([
            'serial_number' => $serialNumber,
            'batch_id' => $batchId,
            'type' => 'PRODUCT_BAG',
            'generated_at' => date('Y-m-d H:i:s')
        ]);

        // Generate QR code using phpqrcode library
        require_once __DIR__ . '/../phpqrcode/qrlib.php';

        $filename = $qrDir . 'bag_' . $serialNumber . '.png';
        QRcode::png($qrData, $filename, QR_ECLEVEL_M, 4, 2);
    }

    // Log production action
    private function logProductionAction($batchId, $action, $userId, $notes = '') {
        try {
            $sql = "INSERT INTO production_logs (batch_id, action, performed_by, notes, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId, $action, $userId, $notes]);

            // Also log in activity_logs
            $sql = "INSERT INTO activity_logs (user_id, action, description, created_at)
                    VALUES (?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $action, $notes]);

        } catch (Exception $e) {
            error_log("Error logging production action: " . $e->getMessage());
        }
    }

    // Get finished products for packaging selection
    public function getFinishedProducts() {
        try {
            $sql = "SELECT id, name, package_size, unit_price, cost_price, status
                    FROM products
                    WHERE status = 'Active'
                    ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting finished products: " . $e->getMessage());
            return [];
        }
    }

    // Get packaging materials for selection
    public function getPackagingMaterials() {
        try {
            $sql = "SELECT id, name, unit, current_stock, unit_cost, status
                    FROM packaging_materials
                    WHERE branch_id = 1 AND status = 'Active' AND current_stock > 0
                    ORDER BY name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting packaging materials: " . $e->getMessage());
            return [];
        }
    }

    // Helper methods for notification system
    private function getBatchById($batchId) {
        try {
            $sql = "SELECT * FROM production_batches WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$batchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting batch: " . $e->getMessage());
            return ['batch_number' => 'Unknown Batch'];
        }
    }

    private function getFormulaById($formulaId) {
        try {
            $sql = "SELECT * FROM formulas WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$formulaId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting formula: " . $e->getMessage());
            return ['name' => 'Unknown Formula'];
        }
    }

    private function getUserById($userId) {
        try {
            $sql = "SELECT * FROM users WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user: " . $e->getMessage());
            return ['full_name' => 'Unknown User'];
        }
    }
}
?>