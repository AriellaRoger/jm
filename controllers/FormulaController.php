<?php
// File: controllers/FormulaController.php
// Formula management controller for JM Animal Feeds ERP System
// Handles CRUD operations for production formulas and ingredients
// Administrator and Supervisor access only

require_once __DIR__ . '/../config/database.php';

class FormulaController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // Get all formulas
    public function getAllFormulas() {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, u.full_name as created_by_name,
                       COUNT(fi.id) as ingredient_count
                FROM formulas f
                LEFT JOIN users u ON f.created_by = u.id
                LEFT JOIN formula_ingredients fi ON f.id = fi.formula_id
                GROUP BY f.id
                ORDER BY f.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting formulas: " . $e->getMessage());
            return [];
        }
    }

    // Get available raw materials from HQ
    public function getAvailableRawMaterials() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, unit_of_measure as unit, current_stock, cost_price
                FROM raw_materials
                WHERE branch_id = 1 AND status = 'Active' AND current_stock > 0
                ORDER BY name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting raw materials: " . $e->getMessage());
            return [];
        }
    }

    // Create new formula
    public function createFormula($data) {
        try {
            $this->db->beginTransaction();

            // Insert formula
            $stmt = $this->db->prepare("
                INSERT INTO formulas (name, description, target_yield, yield_unit, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['target_yield'],
                $data['yield_unit'],
                $data['created_by']
            ]);

            $formulaId = $this->db->lastInsertId();

            // Insert ingredients
            if (!empty($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingredient) {
                    $stmt = $this->db->prepare("
                        INSERT INTO formula_ingredients (formula_id, raw_material_id, quantity, unit, percentage)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $formulaId,
                        $ingredient['raw_material_id'],
                        $ingredient['quantity'],
                        $ingredient['unit'],
                        $ingredient['percentage'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            return ['success' => true, 'formula_id' => $formulaId];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating formula: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get formula details with ingredients
    public function getFormulaDetails($formulaId) {
        try {
            // Get formula basic info
            $stmt = $this->db->prepare("
                SELECT f.*, u.full_name as created_by_name
                FROM formulas f
                LEFT JOIN users u ON f.created_by = u.id
                WHERE f.id = ?
            ");
            $stmt->execute([$formulaId]);
            $formula = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$formula) {
                return null;
            }

            // Get ingredients with raw material details
            $stmt = $this->db->prepare("
                SELECT fi.*, rm.name as raw_material_name, rm.unit_of_measure, rm.cost_price, rm.current_stock
                FROM formula_ingredients fi
                JOIN raw_materials rm ON fi.raw_material_id = rm.id
                WHERE fi.formula_id = ?
                ORDER BY fi.id
            ");
            $stmt->execute([$formulaId]);
            $formula['ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total cost
            $totalCost = 0;
            foreach ($formula['ingredients'] as $ingredient) {
                $totalCost += $ingredient['quantity'] * $ingredient['cost_price'];
            }
            $formula['total_cost'] = $totalCost;
            $formula['cost_per_kg'] = $formula['target_yield'] > 0 ? $totalCost / $formula['target_yield'] : 0;

            return $formula;

        } catch (PDOException $e) {
            error_log("Error getting formula details: " . $e->getMessage());
            return null;
        }
    }

    // Update formula
    public function updateFormula($formulaId, $data) {
        try {
            $this->db->beginTransaction();

            // Update formula basic info
            $stmt = $this->db->prepare("
                UPDATE formulas
                SET name = ?, description = ?, target_yield = ?, yield_unit = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['target_yield'],
                $data['yield_unit'],
                $data['status'] ?? 'Active',
                $formulaId
            ]);

            // Delete existing ingredients
            $stmt = $this->db->prepare("DELETE FROM formula_ingredients WHERE formula_id = ?");
            $stmt->execute([$formulaId]);

            // Insert updated ingredients
            if (!empty($data['ingredients'])) {
                foreach ($data['ingredients'] as $ingredient) {
                    $stmt = $this->db->prepare("
                        INSERT INTO formula_ingredients (formula_id, raw_material_id, quantity, unit, percentage)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $formulaId,
                        $ingredient['raw_material_id'],
                        $ingredient['quantity'],
                        $ingredient['unit'],
                        $ingredient['percentage'] ?? null
                    ]);
                }
            }

            $this->db->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating formula: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Delete formula
    public function deleteFormula($formulaId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM formulas WHERE id = ?");
            $result = $stmt->execute([$formulaId]);
            return $result && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error deleting formula: " . $e->getMessage());
            return false;
        }
    }

    // Check if formula can be produced (stock availability)
    public function checkFormulaAvailability($formulaId, $batchSize = 1) {
        try {
            $formula = $this->getFormulaDetails($formulaId);
            if (!$formula) {
                return ['available' => false, 'message' => 'Formula not found'];
            }

            $shortages = [];
            $canProduce = true;

            foreach ($formula['ingredients'] as $ingredient) {
                $requiredQuantity = $ingredient['quantity'] * $batchSize;
                if ($ingredient['current_stock'] < $requiredQuantity) {
                    $shortages[] = [
                        'material' => $ingredient['raw_material_name'],
                        'required' => $requiredQuantity,
                        'available' => $ingredient['current_stock'],
                        'shortage' => $requiredQuantity - $ingredient['current_stock'],
                        'unit' => $ingredient['unit']
                    ];
                    $canProduce = false;
                }
            }

            return [
                'available' => $canProduce,
                'shortages' => $shortages,
                'batch_size' => $batchSize,
                'total_yield' => $formula['target_yield'] * $batchSize
            ];

        } catch (Exception $e) {
            error_log("Error checking formula availability: " . $e->getMessage());
            return ['available' => false, 'message' => 'Error checking availability'];
        }
    }

    // Calculate formula percentages
    public function calculatePercentages($ingredients) {
        $totalQuantity = array_sum(array_column($ingredients, 'quantity'));

        if ($totalQuantity == 0) return $ingredients;

        foreach ($ingredients as &$ingredient) {
            $ingredient['percentage'] = round(($ingredient['quantity'] / $totalQuantity) * 100, 2);
        }

        return $ingredients;
    }
}
?>