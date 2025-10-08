<?php
// File: controllers/InventoryController.php
// Inventory management controller for JM Animal Feeds ERP System
// Handles CRUD operations for products, product bags, and opened bags with role-based access control

require_once __DIR__ . '/../config/database.php';

class InventoryController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Get all products with bag counts per branch
    public function getProducts($branch_id = null) {
        try {
            $sql = "SELECT p.*,
                           u.full_name as created_by_name,
                           SUM(CASE WHEN pb.status IN ('Sealed', 'Opened') THEN 1 ELSE 0 END) as total_bags,
                           SUM(CASE WHEN pb.status = 'Sealed' THEN 1 ELSE 0 END) as sealed_bags,
                           SUM(CASE WHEN pb.status = 'Opened' THEN 1 ELSE 0 END) as opened_bags,
                           SUM(CASE WHEN pb.status = 'Sold' THEN 1 ELSE 0 END) as sold_bags
                    FROM products p
                    LEFT JOIN users u ON p.created_by = u.id
                    LEFT JOIN product_bags pb ON p.id = pb.product_id";

            $params = [];
            if ($branch_id) {
                $sql .= " AND pb.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " GROUP BY p.id ORDER BY p.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting products: " . $e->getMessage());
            return [];
        }
    }

    // Get product by ID
    public function getProductById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting product: " . $e->getMessage());
            return false;
        }
    }

    // Create new product (Admin/Supervisor only)
    public function createProduct($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO products (name, description, package_size, unit_price, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['package_size'],
                $data['unit_price'],
                $data['created_by']
            ]);
        } catch (PDOException $e) {
            error_log("Error creating product: " . $e->getMessage());
            return false;
        }
    }

    // Update product (Admin/Supervisor only)
    public function updateProduct($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE products
                SET name = ?, description = ?, package_size = ?, unit_price = ?, status = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['package_size'],
                $data['unit_price'],
                $data['status'],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            return false;
        }
    }

    // Update product cost price (will be called from production module)
    public function updateProductCostPrice($productId, $costPrice) {
        try {
            $stmt = $this->db->prepare("
                UPDATE products
                SET cost_price = ?
                WHERE id = ?
            ");
            return $stmt->execute([$costPrice, $productId]);
        } catch (PDOException $e) {
            error_log("Error updating product cost price: " . $e->getMessage());
            return false;
        }
    }

    // Delete product (Admin only)
    public function deleteProduct($id) {
        try {
            // Check if product has any bags
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM product_bags WHERE product_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Cannot delete product with existing bags'];
            }

            $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting product: " . $e->getMessage());
            return false;
        }
    }

    // Get product bags for a specific product and branch
    public function getProductBags($product_id, $branch_id = null) {
        try {
            $sql = "SELECT pb.*, p.name as product_name, p.package_size, b.name as branch_name,
                           ob.original_weight_kg, ob.current_weight_kg, ob.selling_price_per_kg, ob.opened_at,
                           opener.full_name as opened_by_name
                    FROM product_bags pb
                    JOIN products p ON pb.product_id = p.id
                    JOIN branches b ON pb.branch_id = b.id
                    LEFT JOIN opened_bags ob ON pb.id = ob.bag_id
                    LEFT JOIN users opener ON ob.opened_by = opener.id
                    WHERE pb.product_id = ?";

            $params = [$product_id];
            if ($branch_id) {
                $sql .= " AND pb.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " ORDER BY pb.serial_number";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting product bags: " . $e->getMessage());
            return [];
        }
    }

    // Get all bags for a branch with product info
    public function getBranchInventory($branch_id) {
        try {
            $sql = "SELECT p.name as product_name, p.package_size, p.unit_price,
                           SUM(CASE WHEN pb.status IN ('Sealed', 'Opened') THEN 1 ELSE 0 END) as total_bags,
                           SUM(CASE WHEN pb.status = 'Sealed' THEN 1 ELSE 0 END) as sealed_bags,
                           SUM(CASE WHEN pb.status = 'Opened' THEN 1 ELSE 0 END) as opened_bags,
                           SUM(CASE WHEN pb.status = 'Sold' THEN 1 ELSE 0 END) as sold_bags
                    FROM products p
                    LEFT JOIN product_bags pb ON p.id = pb.product_id AND pb.branch_id = ?
                    WHERE p.status = 'Active'
                    GROUP BY p.id
                    ORDER BY p.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branch inventory: " . $e->getMessage());
            return [];
        }
    }

    // Get opened bags for a branch
    public function getOpenedBags($branch_id) {
        try {
            $sql = "SELECT pb.serial_number, p.name as product_name, p.package_size,
                           ob.original_weight_kg, ob.current_weight_kg, ob.selling_price_per_kg, ob.opened_at,
                           opener.full_name as opened_by_name, ob.notes
                    FROM opened_bags ob
                    JOIN product_bags pb ON ob.bag_id = pb.id
                    JOIN products p ON pb.product_id = p.id
                    JOIN users opener ON ob.opened_by = opener.id
                    WHERE pb.branch_id = ? AND ob.current_weight_kg > 0
                    ORDER BY ob.opened_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$branch_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting opened bags: " . $e->getMessage());
            return [];
        }
    }


    // Get all branches for filtering
    public function getAllBranches() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, location, type
                FROM branches
                WHERE status = 'Active'
                ORDER BY type DESC, name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branches: " . $e->getMessage());
            return [];
        }
    }

    // Get branch info by ID
    public function getBranchById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM branches WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting branch: " . $e->getMessage());
            return false;
        }
    }

    // Get headquarters branch (for supervisor access)
    public function getHeadquartersBranch() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM branches WHERE type = 'HQ' AND status = 'Active' LIMIT 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting headquarters branch: " . $e->getMessage());
            return false;
        }
    }

    // ==================== RAW MATERIALS METHODS ====================

    // Get all raw materials with branch filtering
    public function getRawMaterials($branch_id = null) {
        try {
            $sql = "SELECT rm.*, u.full_name as created_by_name, b.name as branch_name
                    FROM raw_materials rm
                    LEFT JOIN users u ON rm.created_by = u.id
                    LEFT JOIN branches b ON rm.branch_id = b.id";

            $params = [];
            if ($branch_id) {
                $sql .= " WHERE rm.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " ORDER BY rm.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting raw materials: " . $e->getMessage());
            return [];
        }
    }

    // Get raw material by ID
    public function getRawMaterialById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM raw_materials WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting raw material: " . $e->getMessage());
            return false;
        }
    }

    // Create new raw material
    public function createRawMaterial($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO raw_materials (name, description, unit_of_measure, cost_price, selling_price,
                                         current_stock, minimum_stock, supplier, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['unit_of_measure'],
                $data['cost_price'],
                $data['selling_price'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['supplier'],
                $data['created_by']
            ]);
        } catch (PDOException $e) {
            error_log("Error creating raw material: " . $e->getMessage());
            return false;
        }
    }

    // Update raw material
    public function updateRawMaterial($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE raw_materials
                SET name = ?, description = ?, unit_of_measure = ?, cost_price = ?, selling_price = ?,
                    current_stock = ?, minimum_stock = ?, supplier = ?, status = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['unit_of_measure'],
                $data['cost_price'],
                $data['selling_price'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['supplier'],
                $data['status'],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating raw material: " . $e->getMessage());
            return false;
        }
    }

    // Delete raw material
    public function deleteRawMaterial($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM raw_materials WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting raw material: " . $e->getMessage());
            return false;
        }
    }

    // ==================== PACKAGING MATERIALS METHODS ====================

    // Get all packaging materials with branch filtering
    public function getPackagingMaterials($branch_id = null) {
        try {
            $sql = "SELECT pm.*, u.full_name as created_by_name, b.name as branch_name
                    FROM packaging_materials pm
                    LEFT JOIN users u ON pm.created_by = u.id
                    LEFT JOIN branches b ON pm.branch_id = b.id";

            $params = [];
            if ($branch_id) {
                $sql .= " WHERE pm.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " ORDER BY pm.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting packaging materials: " . $e->getMessage());
            return [];
        }
    }

    // Get packaging material by ID
    public function getPackagingMaterialById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM packaging_materials WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting packaging material: " . $e->getMessage());
            return false;
        }
    }

    // Create new packaging material
    public function createPackagingMaterial($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO packaging_materials (name, description, unit, current_stock, minimum_stock, unit_cost, supplier, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['unit'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['unit_cost'],
                $data['supplier'],
                $data['created_by']
            ]);
        } catch (PDOException $e) {
            error_log("Error creating packaging material: " . $e->getMessage());
            return false;
        }
    }

    // Update packaging material
    public function updatePackagingMaterial($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE packaging_materials
                SET name = ?, description = ?, unit = ?, current_stock = ?, minimum_stock = ?, unit_cost = ?, supplier = ?, status = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['description'],
                $data['unit'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['unit_cost'],
                $data['supplier'],
                $data['status'],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating packaging material: " . $e->getMessage());
            return false;
        }
    }

    // Delete packaging material
    public function deletePackagingMaterial($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM packaging_materials WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting packaging material: " . $e->getMessage());
            return false;
        }
    }

    // ==================== THIRD PARTY PRODUCTS METHODS ====================

    // Get all third party products with branch filtering
    public function getThirdPartyProducts($branch_id = null) {
        try {
            $sql = "SELECT tp.*, u.full_name as created_by_name, b.name as branch_name
                    FROM third_party_products tp
                    LEFT JOIN users u ON tp.created_by = u.id
                    LEFT JOIN branches b ON tp.branch_id = b.id";

            $params = [];
            if ($branch_id) {
                $sql .= " WHERE tp.branch_id = ?";
                $params[] = $branch_id;
            }

            $sql .= " ORDER BY tp.brand, tp.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting third party products: " . $e->getMessage());
            return [];
        }
    }

    // Get third party product by ID
    public function getThirdPartyProductById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM third_party_products WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting third party product: " . $e->getMessage());
            return false;
        }
    }

    // Create new third party product
    public function createThirdPartyProduct($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO third_party_products (name, brand, description, category, unit_of_measure,
                                                 package_size, cost_price, selling_price, current_stock,
                                                 minimum_stock, supplier, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['name'],
                $data['brand'],
                $data['description'],
                $data['category'],
                $data['unit_of_measure'],
                $data['package_size'],
                $data['cost_price'],
                $data['selling_price'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['supplier'],
                $data['created_by']
            ]);
        } catch (PDOException $e) {
            error_log("Error creating third party product: " . $e->getMessage());
            return false;
        }
    }

    // Update third party product
    public function updateThirdPartyProduct($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE third_party_products
                SET name = ?, brand = ?, description = ?, category = ?, unit_of_measure = ?,
                    package_size = ?, cost_price = ?, selling_price = ?, current_stock = ?,
                    minimum_stock = ?, supplier = ?, status = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['brand'],
                $data['description'],
                $data['category'],
                $data['unit_of_measure'],
                $data['package_size'],
                $data['cost_price'],
                $data['selling_price'],
                $data['current_stock'],
                $data['minimum_stock'],
                $data['supplier'],
                $data['status'],
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Error updating third party product: " . $e->getMessage());
            return false;
        }
    }

    // Delete third party product
    public function deleteThirdPartyProduct($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM third_party_products WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("Error deleting third party product: " . $e->getMessage());
            return false;
        }
    }

    // Log activity
    // Open a sealed bag for loose sales
    public function openBag($serial_number, $weight, $selling_price_per_kg, $user_id, $branch_id) {
        try {
            $this->db->beginTransaction();

            // Get the bag information
            $stmt = $this->db->prepare("
                SELECT pb.*, p.name as product_name
                FROM product_bags pb
                JOIN products p ON pb.product_id = p.id
                WHERE pb.serial_number = ? AND pb.status = 'Sealed'
            ");
            $stmt->execute([$serial_number]);
            $bag = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bag) {
                throw new Exception("Bag not found or already opened");
            }

            // Update bag status to opened
            $stmt = $this->db->prepare("
                UPDATE product_bags
                SET status = 'Opened'
                WHERE serial_number = ?
            ");
            $stmt->execute([$serial_number]);

            // Insert into opened_bags table with selling price
            $stmt = $this->db->prepare("
                INSERT INTO opened_bags (bag_id, serial_number, branch_id, original_weight_kg, current_weight_kg, selling_price_per_kg, opened_by, opened_at, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'Bag opened for loose sales')
            ");
            $stmt->execute([
                $bag['id'],
                $serial_number,
                $branch_id,
                $weight,
                $weight,
                $selling_price_per_kg,
                $user_id
            ]);

            // Record inventory movement for bag opening
            $stmt = $this->db->prepare("
                INSERT INTO inventory_movements (branch_id, product_type, product_id, movement_type, quantity, unit, reference_type, reference_id, notes, created_by, created_at)
                VALUES (?, 'FINISHED_PRODUCT', ?, 'BAG_OPENED', 1, 'BAGS', 'BAG_OPENING', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $branch_id,
                $bag['product_id'],
                $bag['id'],
                "Bag {$serial_number} opened for loose sales - {$weight}KG at {$selling_price_per_kg} TZS/KG",
                $user_id
            ]);

            // Record loose stock addition movement
            $stmt = $this->db->prepare("
                INSERT INTO inventory_movements (branch_id, product_type, product_id, movement_type, quantity, unit, reference_type, reference_id, notes, created_by, created_at)
                VALUES (?, 'FINISHED_PRODUCT', ?, 'STOCK_ADDITION', ?, 'KG', 'BAG_OPENING', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $branch_id,
                $bag['product_id'],
                $weight,
                $bag['id'],
                "Loose stock added from opened bag {$serial_number} - {$weight}KG at {$selling_price_per_kg} TZS/KG",
                $user_id
            ]);

            // Log the activity
            $this->logActivity('BAG_OPENED', [
                'serial_number' => $serial_number,
                'product_name' => $bag['product_name'],
                'product_id' => $bag['product_id'],
                'weight_kg' => $weight,
                'selling_price_per_kg' => $selling_price_per_kg,
                'branch_id' => $branch_id,
                'loose_stock_added' => $weight
            ], $user_id);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error opening bag: " . $e->getMessage());
            throw $e;
        }
    }

    private function logActivity($action, $details, $user_id) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, action, module, details, ip_address, created_at)
                VALUES (?, ?, 'INVENTORY', ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'localhost'
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>