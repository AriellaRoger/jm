<?php
// File: controllers/StockAdjustmentController.php
// Stock adjustment controller for JM Animal Feeds ERP System
// Handles stock adjustments for all product types with complete activity logging and movement tracking
// Administrator-only access with branch navigation support

require_once __DIR__ . '/../config/database.php';

class StockAdjustmentController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Get all branches for navigation
    public function getAllBranches() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, location, type, status
                FROM branches
                WHERE status = 'ACTIVE'
                ORDER BY type DESC, name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branches: " . $e->getMessage());
            return [];
        }
    }

    // Get branch inventory summary for specific branch
    public function getBranchInventorySummary($branch_id) {
        try {
            $summary = [];

            // Raw materials count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM raw_materials WHERE branch_id = ? AND status = 'Active'");
            $stmt->execute([$branch_id]);
            $summary['raw_materials_count'] = $stmt->fetchColumn();

            // Third party products count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM third_party_products WHERE branch_id = ? AND status = 'Active'");
            $stmt->execute([$branch_id]);
            $summary['third_party_count'] = $stmt->fetchColumn();

            // Packaging materials count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM packaging_materials WHERE branch_id = ? AND status = 'Active'");
            $stmt->execute([$branch_id]);
            $summary['packaging_count'] = $stmt->fetchColumn();

            // Finished products (bags) count for this branch
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_bags WHERE branch_id = ? AND status = 'Sealed'");
            $stmt->execute([$branch_id]);
            $summary['sealed_bags_count'] = $stmt->fetchColumn();

            // Opened bags count for this branch
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM opened_bags ob JOIN product_bags pb ON ob.bag_id = pb.id WHERE pb.branch_id = ? AND ob.current_weight_kg > 0");
            $stmt->execute([$branch_id]);
            $summary['opened_bags_count'] = $stmt->fetchColumn();

            return $summary;
        } catch (PDOException $e) {
            error_log("Error getting branch inventory summary: " . $e->getMessage());
            return [];
        }
    }

    // Get raw materials for specific branch
    public function getRawMaterialsForBranch($branch_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, sku, name, description, unit_of_measure, current_stock, minimum_stock, cost_price, selling_price, supplier
                FROM raw_materials
                WHERE branch_id = ? AND status = 'Active'
                ORDER BY name
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting raw materials: " . $e->getMessage());
            return [];
        }
    }

    // Get third party products for specific branch
    public function getThirdPartyProductsForBranch($branch_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, sku, name, brand, description, category, unit_of_measure, package_size, current_stock, minimum_stock, cost_price, selling_price, supplier
                FROM third_party_products
                WHERE branch_id = ? AND status = 'Active'
                ORDER BY brand, name
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting third party products: " . $e->getMessage());
            return [];
        }
    }

    // Get packaging materials for specific branch
    public function getPackagingMaterialsForBranch($branch_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, sku, name, description, unit, current_stock, minimum_stock, unit_cost, supplier
                FROM packaging_materials
                WHERE branch_id = ? AND status = 'Active'
                ORDER BY name
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting packaging materials: " . $e->getMessage());
            return [];
        }
    }

    // Get finished product bags for specific branch
    public function getFinishedProductBagsForBranch($branch_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT pb.id, pb.serial_number, pb.status, pb.production_date, pb.expiry_date,
                       p.id as product_id, p.name as product_name, p.package_size,
                       p.unit_price, p.cost_price
                FROM product_bags pb
                JOIN products p ON pb.product_id = p.id
                WHERE pb.branch_id = ? AND pb.status = 'Sealed'
                ORDER BY p.name, pb.serial_number
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting finished product bags: " . $e->getMessage());
            return [];
        }
    }

    // Get opened bags for specific branch
    public function getOpenedBagsForBranch($branch_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT ob.id, ob.serial_number, ob.original_weight_kg, ob.current_weight_kg,
                       ob.selling_price_per_kg, ob.opened_at,
                       p.id as product_id, p.name as product_name, p.package_size,
                       opener.full_name as opened_by_name
                FROM opened_bags ob
                JOIN product_bags pb ON ob.bag_id = pb.id
                JOIN products p ON pb.product_id = p.id
                JOIN users opener ON ob.opened_by = opener.id
                WHERE pb.branch_id = ? AND ob.current_weight_kg > 0
                ORDER BY p.name, ob.opened_at DESC
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting opened bags: " . $e->getMessage());
            return [];
        }
    }

    // Adjust raw material stock
    public function adjustRawMaterialStock($material_id, $new_stock, $adjustment_reason, $user_id, $branch_id) {
        try {
            $this->db->beginTransaction();

            // Get current stock
            $stmt = $this->db->prepare("SELECT current_stock, name FROM raw_materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                throw new Exception("Raw material not found");
            }

            $old_stock = $material['current_stock'];
            $adjustment = $new_stock - $old_stock;

            // Update stock
            $stmt = $this->db->prepare("UPDATE raw_materials SET current_stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $material_id]);

            // Record inventory movement
            $movement_type = $adjustment > 0 ? 'STOCK_ADJUSTMENT' : 'STOCK_ADJUSTMENT';
            $this->recordInventoryMovement(
                $branch_id,
                'RAW_MATERIAL',
                $material_id,
                $movement_type,
                abs($adjustment),
                'KG', // Default unit for raw materials
                'STOCK_ADJUSTMENT',
                null,
                $adjustment_reason . " (Stock adjusted from {$old_stock} to {$new_stock})",
                $user_id
            );

            // Log activity
            $this->logActivity('STOCK_ADJUSTED', [
                'product_type' => 'RAW_MATERIAL',
                'product_id' => $material_id,
                'product_name' => $material['name'],
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'adjustment' => $adjustment,
                'reason' => $adjustment_reason,
                'branch_id' => $branch_id
            ], $user_id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error adjusting raw material stock: " . $e->getMessage());
            throw $e;
        }
    }

    // Adjust third party product stock
    public function adjustThirdPartyProductStock($product_id, $new_stock, $adjustment_reason, $user_id, $branch_id) {
        try {
            $this->db->beginTransaction();

            // Get current stock
            $stmt = $this->db->prepare("SELECT current_stock, name, brand FROM third_party_products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception("Third party product not found");
            }

            $old_stock = $product['current_stock'];
            $adjustment = $new_stock - $old_stock;

            // Update stock
            $stmt = $this->db->prepare("UPDATE third_party_products SET current_stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $product_id]);

            // Record inventory movement
            $movement_type = 'STOCK_ADJUSTMENT';
            $this->recordInventoryMovement(
                $branch_id,
                'THIRD_PARTY_PRODUCT',
                $product_id,
                $movement_type,
                abs($adjustment),
                'Pieces', // Default unit
                'STOCK_ADJUSTMENT',
                null,
                $adjustment_reason . " (Stock adjusted from {$old_stock} to {$new_stock})",
                $user_id
            );

            // Log activity
            $this->logActivity('STOCK_ADJUSTED', [
                'product_type' => 'THIRD_PARTY_PRODUCT',
                'product_id' => $product_id,
                'product_name' => $product['brand'] . ' ' . $product['name'],
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'adjustment' => $adjustment,
                'reason' => $adjustment_reason,
                'branch_id' => $branch_id
            ], $user_id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error adjusting third party product stock: " . $e->getMessage());
            throw $e;
        }
    }

    // Adjust packaging material stock
    public function adjustPackagingMaterialStock($material_id, $new_stock, $adjustment_reason, $user_id, $branch_id) {
        try {
            $this->db->beginTransaction();

            // Get current stock
            $stmt = $this->db->prepare("SELECT current_stock, name FROM packaging_materials WHERE id = ?");
            $stmt->execute([$material_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$material) {
                throw new Exception("Packaging material not found");
            }

            $old_stock = $material['current_stock'];
            $adjustment = $new_stock - $old_stock;

            // Update stock
            $stmt = $this->db->prepare("UPDATE packaging_materials SET current_stock = ? WHERE id = ?");
            $stmt->execute([$new_stock, $material_id]);

            // Record inventory movement
            $movement_type = 'STOCK_ADJUSTMENT';
            $this->recordInventoryMovement(
                $branch_id,
                'PACKAGING_MATERIAL',
                $material_id,
                $movement_type,
                abs($adjustment),
                'Pieces', // Default unit
                'STOCK_ADJUSTMENT',
                null,
                $adjustment_reason . " (Stock adjusted from {$old_stock} to {$new_stock})",
                $user_id
            );

            // Log activity
            $this->logActivity('STOCK_ADJUSTED', [
                'product_type' => 'PACKAGING_MATERIAL',
                'product_id' => $material_id,
                'product_name' => $material['name'],
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'adjustment' => $adjustment,
                'reason' => $adjustment_reason,
                'branch_id' => $branch_id
            ], $user_id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error adjusting packaging material stock: " . $e->getMessage());
            throw $e;
        }
    }

    // Adjust opened bag weight
    public function adjustOpenedBagWeight($opened_bag_id, $new_weight, $adjustment_reason, $user_id, $branch_id) {
        try {
            $this->db->beginTransaction();

            // Get current weight and product info
            $stmt = $this->db->prepare("
                SELECT ob.current_weight_kg, ob.serial_number, p.name as product_name, p.id as product_id
                FROM opened_bags ob
                JOIN product_bags pb ON ob.bag_id = pb.id
                JOIN products p ON pb.product_id = p.id
                WHERE ob.id = ?
            ");
            $stmt->execute([$opened_bag_id]);
            $bag = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bag) {
                throw new Exception("Opened bag not found");
            }

            $old_weight = $bag['current_weight_kg'];
            $weight_adjustment = $new_weight - $old_weight;

            // Update weight
            $stmt = $this->db->prepare("UPDATE opened_bags SET current_weight_kg = ? WHERE id = ?");
            $stmt->execute([$new_weight, $opened_bag_id]);

            // Record inventory movement
            $movement_type = 'STOCK_ADJUSTMENT';
            $this->recordInventoryMovement(
                $branch_id,
                'FINISHED_PRODUCT',
                $bag['product_id'],
                $movement_type,
                abs($weight_adjustment),
                'KG',
                'STOCK_ADJUSTMENT',
                $opened_bag_id,
                $adjustment_reason . " (Opened bag {$bag['serial_number']} weight adjusted from {$old_weight}KG to {$new_weight}KG)",
                $user_id
            );

            // Log activity
            $this->logActivity('OPENED_BAG_ADJUSTED', [
                'opened_bag_id' => $opened_bag_id,
                'serial_number' => $bag['serial_number'],
                'product_name' => $bag['product_name'],
                'old_weight' => $old_weight,
                'new_weight' => $new_weight,
                'weight_adjustment' => $weight_adjustment,
                'reason' => $adjustment_reason,
                'branch_id' => $branch_id
            ], $user_id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error adjusting opened bag weight: " . $e->getMessage());
            throw $e;
        }
    }

    // Record inventory movement
    private function recordInventoryMovement($branch_id, $product_type, $product_id, $movement_type, $quantity, $unit, $reference_type, $reference_id, $notes, $user_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_movements (branch_id, product_type, product_id, movement_type, quantity, unit, reference_type, reference_id, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([
                $branch_id,
                $product_type,
                $product_id,
                $movement_type,
                $quantity,
                $unit,
                $reference_type,
                $reference_id,
                $notes,
                $user_id
            ]);
        } catch (PDOException $e) {
            error_log("Error recording inventory movement: " . $e->getMessage());
            throw $e;
        }
    }

    // Log activity
    private function logActivity($action, $details, $user_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, module, details, ip_address, created_at)
                VALUES (?, ?, 'STOCK_ADJUSTMENT', ?, ?, NOW())
            ");
            return $stmt->execute([
                $user_id,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'localhost'
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }

    // Get recent stock adjustments for audit trail
    public function getRecentStockAdjustments($branch_id = null, $limit = 50) {
        try {
            $sql = "
                SELECT im.*, u.full_name as adjusted_by_name, b.name as branch_name
                FROM inventory_movements im
                JOIN users u ON im.created_by = u.id
                JOIN branches b ON im.branch_id = b.id
                WHERE im.movement_type = 'STOCK_ADJUSTMENT'
            ";

            $params = [];
            if ($branch_id) {
                $sql .= " AND im.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " ORDER BY im.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recent stock adjustments: " . $e->getMessage());
            return [];
        }
    }
}
?>