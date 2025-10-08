<?php
// File: controllers/ReportsController.php
// Finance and reporting controller for comprehensive business analytics

require_once __DIR__ . '/../config/database.php';

class ReportsController {
    private $conn;

    public function __construct() {
        $this->conn = getDbConnection();
    }

    // Get production cost report by batch
    public function getProductionCostByBatch($batchId = null) {
        try {
            $whereClause = $batchId ? "WHERE pb.id = ?" : "";
            $params = $batchId ? [$batchId] : [];

            $sql = "SELECT pb.id, pb.batch_number, pb.batch_size, pb.expected_yield, pb.actual_yield,
                           pb.production_cost, pb.wastage_percentage, pb.status,
                           pb.created_at, pb.completed_at,
                           f.name as formula_name,
                           u1.full_name as production_officer,
                           u2.full_name as supervisor,
                           -- Raw materials cost
                           (SELECT SUM(pbm.total_cost) FROM production_batch_materials pbm WHERE pbm.batch_id = pb.id) as raw_materials_cost,
                           -- Packaging cost
                           (SELECT SUM(pbp.packaging_cost) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id) as packaging_cost,
                           -- Total bags produced
                           (SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id) as total_bags,
                           -- Cost per kg
                           CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END as cost_per_kg
                    FROM production_batches pb
                    JOIN formulas f ON pb.formula_id = f.id
                    JOIN users u1 ON pb.production_officer_id = u1.id
                    JOIN users u2 ON pb.supervisor_id = u2.id
                    {$whereClause}
                    ORDER BY pb.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $batches = $stmt->fetchAll();

            // Get detailed materials and products for each batch
            foreach ($batches as &$batch) {
                // Get materials used
                $materialSql = "SELECT pbm.*, rm.name as material_name, rm.unit_of_measure
                               FROM production_batch_materials pbm
                               JOIN raw_materials rm ON pbm.raw_material_id = rm.id
                               WHERE pbm.batch_id = ?";
                $materialStmt = $this->conn->prepare($materialSql);
                $materialStmt->execute([$batch['id']]);
                $batch['materials'] = $materialStmt->fetchAll();

                // Get products produced
                $productSql = "SELECT pbp.*, p.name as product_name
                              FROM production_batch_products pbp
                              JOIN products p ON pbp.product_id = p.id
                              WHERE pbp.batch_id = ?";
                $productStmt = $this->conn->prepare($productSql);
                $productStmt->execute([$batch['id']]);
                $products = $productStmt->fetchAll();

                // Calculate cost per bag for each product
                foreach ($products as &$product) {
                    $bagWeight = (float)str_replace('KG', '', $product['package_size']);
                    $product['cost_per_bag'] = $batch['cost_per_kg'] * $bagWeight;
                }
                $batch['products'] = $products;
            }

            return ['success' => true, 'batches' => $batches];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production cost report by date range
    public function getProductionCostByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT DATE(pb.completed_at) as production_date,
                           COUNT(pb.id) as total_batches,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           SUM((SELECT SUM(pbm.total_cost) FROM production_batch_materials pbm WHERE pbm.batch_id = pb.id)) as total_raw_materials_cost,
                           SUM((SELECT SUM(pbp.packaging_cost) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_packaging_cost,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_produced,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg
                    FROM production_batches pb
                    WHERE pb.status = 'COMPLETED'
                    AND DATE(pb.completed_at) BETWEEN ? AND ?
                    GROUP BY DATE(pb.completed_at)
                    ORDER BY production_date DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return ['success' => true, 'daily_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production cost report by month
    public function getProductionCostByMonth($year = null) {
        try {
            $currentYear = $year ?: date('Y');

            $sql = "SELECT YEAR(pb.completed_at) as year,
                           MONTH(pb.completed_at) as month,
                           MONTHNAME(pb.completed_at) as month_name,
                           COUNT(pb.id) as total_batches,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           SUM((SELECT SUM(pbm.total_cost) FROM production_batch_materials pbm WHERE pbm.batch_id = pb.id)) as total_raw_materials_cost,
                           SUM((SELECT SUM(pbp.packaging_cost) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_packaging_cost,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_produced,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg
                    FROM production_batches pb
                    WHERE pb.status = 'COMPLETED'
                    AND YEAR(pb.completed_at) = ?
                    GROUP BY YEAR(pb.completed_at), MONTH(pb.completed_at)
                    ORDER BY year DESC, month DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$currentYear]);
            return ['success' => true, 'monthly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production cost report by year
    public function getProductionCostByYear() {
        try {
            $sql = "SELECT YEAR(pb.completed_at) as year,
                           COUNT(pb.id) as total_batches,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           SUM((SELECT SUM(pbm.total_cost) FROM production_batch_materials pbm WHERE pbm.batch_id = pb.id)) as total_raw_materials_cost,
                           SUM((SELECT SUM(pbp.packaging_cost) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_packaging_cost,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_produced,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg
                    FROM production_batches pb
                    WHERE pb.status = 'COMPLETED'
                    GROUP BY YEAR(pb.completed_at)
                    ORDER BY year DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'yearly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get all-time production cost summary
    public function getAllTimeProductionCost() {
        try {
            $sql = "SELECT COUNT(pb.id) as total_batches,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           SUM((SELECT SUM(pbm.total_cost) FROM production_batch_materials pbm WHERE pbm.batch_id = pb.id)) as total_raw_materials_cost,
                           SUM((SELECT SUM(pbp.packaging_cost) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_packaging_cost,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_produced,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg,
                           MIN(pb.completed_at) as first_production,
                           MAX(pb.completed_at) as last_production
                    FROM production_batches pb
                    WHERE pb.status = 'COMPLETED'";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'all_time_summary' => $stmt->fetch()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production cost breakdown by product type
    public function getProductionCostByProduct() {
        try {
            $sql = "SELECT p.name as product_name,
                           pbp.package_size,
                           SUM(pbp.bags_produced) as total_bags_produced,
                           SUM(pbp.total_weight) as total_weight_produced,
                           SUM(pbp.packaging_cost) as total_packaging_cost,
                           COUNT(DISTINCT pb.id) as total_batches,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg
                    FROM production_batch_products pbp
                    JOIN products p ON pbp.product_id = p.id
                    JOIN production_batches pb ON pbp.batch_id = pb.id
                    WHERE pb.status = 'COMPLETED'
                    GROUP BY p.id, pbp.package_size
                    ORDER BY p.name, pbp.package_size";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $products = $stmt->fetchAll();

            // Calculate cost per bag for each product
            foreach ($products as &$product) {
                $bagWeight = (float)str_replace('KG', '', $product['package_size']);
                $product['avg_cost_per_bag'] = $product['avg_cost_per_kg'] * $bagWeight;
            }

            return ['success' => true, 'product_costs' => $products];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production efficiency metrics
    public function getProductionEfficiencyMetrics() {
        try {
            $sql = "SELECT pb.batch_number,
                           pb.expected_yield,
                           pb.actual_yield,
                           pb.wastage_percentage,
                           CASE WHEN pb.expected_yield > 0 THEN (pb.actual_yield / pb.expected_yield) * 100 ELSE 0 END as efficiency_percentage,
                           pb.production_cost,
                           CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END as cost_per_kg,
                           pb.completed_at,
                           f.name as formula_name,
                           u1.full_name as production_officer,
                           u2.full_name as supervisor
                    FROM production_batches pb
                    JOIN formulas f ON pb.formula_id = f.id
                    JOIN users u1 ON pb.production_officer_id = u1.id
                    JOIN users u2 ON pb.supervisor_id = u2.id
                    WHERE pb.status = 'COMPLETED'
                    ORDER BY pb.completed_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'efficiency_metrics' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get production officer performance summary
    public function getProductionOfficerPerformance() {
        try {
            $sql = "SELECT u.full_name as production_officer,
                           COUNT(pb.id) as total_batches,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           AVG(CASE WHEN pb.expected_yield > 0 THEN (pb.actual_yield / pb.expected_yield) * 100 ELSE 0 END) as avg_efficiency_percentage,
                           AVG(pb.wastage_percentage) as avg_wastage_percentage,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg,
                           MIN(pb.completed_at) as first_batch,
                           MAX(pb.completed_at) as last_batch,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_produced
                    FROM production_batches pb
                    JOIN users u ON pb.production_officer_id = u.id
                    WHERE pb.status = 'COMPLETED'
                    GROUP BY pb.production_officer_id, u.full_name
                    ORDER BY total_batches DESC, avg_efficiency_percentage DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'officer_performance' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get supervisor oversight performance summary
    public function getSupervisorPerformance() {
        try {
            $sql = "SELECT u.full_name as supervisor,
                           COUNT(pb.id) as total_batches_supervised,
                           SUM(pb.batch_size) as total_batch_size,
                           SUM(pb.actual_yield) as total_yield,
                           SUM(pb.production_cost) as total_production_cost,
                           AVG(CASE WHEN pb.expected_yield > 0 THEN (pb.actual_yield / pb.expected_yield) * 100 ELSE 0 END) as avg_efficiency_percentage,
                           AVG(pb.wastage_percentage) as avg_wastage_percentage,
                           AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg,
                           MIN(pb.completed_at) as first_supervision,
                           MAX(pb.completed_at) as last_supervision,
                           SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags_supervised,
                           COUNT(DISTINCT pb.production_officer_id) as officers_supervised
                    FROM production_batches pb
                    JOIN users u ON pb.supervisor_id = u.id
                    WHERE pb.status = 'COMPLETED'
                    GROUP BY pb.supervisor_id, u.full_name
                    ORDER BY total_batches_supervised DESC, avg_efficiency_percentage DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'supervisor_performance' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get detailed personnel performance by time period
    public function getPersonnelPerformanceByPeriod($startDate, $endDate, $personnelType = 'both') {
        try {
            $whereClause = "WHERE pb.status = 'COMPLETED' AND DATE(pb.completed_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];

            // Production Officer Performance
            if ($personnelType === 'officers' || $personnelType === 'both') {
                $officerSql = "SELECT 'PRODUCTION_OFFICER' as role,
                                      u.full_name as personnel_name,
                                      COUNT(pb.id) as total_batches,
                                      SUM(pb.actual_yield) as total_yield,
                                      SUM(pb.production_cost) as total_production_cost,
                                      AVG(CASE WHEN pb.expected_yield > 0 THEN (pb.actual_yield / pb.expected_yield) * 100 ELSE 0 END) as avg_efficiency,
                                      AVG(pb.wastage_percentage) as avg_wastage,
                                      AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg,
                                      SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags
                               FROM production_batches pb
                               JOIN users u ON pb.production_officer_id = u.id
                               {$whereClause}
                               GROUP BY pb.production_officer_id, u.full_name";

                $officerStmt = $this->conn->prepare($officerSql);
                $officerStmt->execute($params);
                $officers = $officerStmt->fetchAll();
            }

            // Supervisor Performance
            if ($personnelType === 'supervisors' || $personnelType === 'both') {
                $supervisorSql = "SELECT 'SUPERVISOR' as role,
                                         u.full_name as personnel_name,
                                         COUNT(pb.id) as total_batches,
                                         SUM(pb.actual_yield) as total_yield,
                                         SUM(pb.production_cost) as total_production_cost,
                                         AVG(CASE WHEN pb.expected_yield > 0 THEN (pb.actual_yield / pb.expected_yield) * 100 ELSE 0 END) as avg_efficiency,
                                         AVG(pb.wastage_percentage) as avg_wastage,
                                         AVG(CASE WHEN pb.actual_yield > 0 THEN pb.production_cost / pb.actual_yield ELSE 0 END) as avg_cost_per_kg,
                                         SUM((SELECT SUM(pbp.bags_produced) FROM production_batch_products pbp WHERE pbp.batch_id = pb.id)) as total_bags,
                                         COUNT(DISTINCT pb.production_officer_id) as officers_supervised
                                  FROM production_batches pb
                                  JOIN users u ON pb.supervisor_id = u.id
                                  {$whereClause}
                                  GROUP BY pb.supervisor_id, u.full_name";

                $supervisorStmt = $this->conn->prepare($supervisorSql);
                $supervisorStmt->execute($params);
                $supervisors = $supervisorStmt->fetchAll();
            }

            $result = [];
            if (isset($officers)) $result['officers'] = $officers;
            if (isset($supervisors)) $result['supervisors'] = $supervisors;

            return ['success' => true, 'personnel_performance' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ===========================================
    // SALES REPORTING METHODS
    // ===========================================

    // Get sales report by date range with branch filtering
    public function getSalesReportByDateRange($startDate, $endDate, $branchId = null) {
        try {
            $whereClause = "WHERE DATE(s.created_at) BETWEEN ? AND ?";
            $params = [$startDate, $endDate];

            if ($branchId) {
                $whereClause .= " AND s.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT DATE(s.created_at) as sale_date,
                           b.name as branch_name,
                           COUNT(s.id) as total_sales,
                           SUM(s.final_amount) as total_amount,
                           SUM(CASE WHEN s.sale_type = 'CASH' THEN s.final_amount ELSE 0 END) as cash_amount,
                           SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END) as credit_amount,
                           SUM(CASE WHEN s.payment_method = 'MOBILE_MONEY' THEN s.final_amount ELSE 0 END) +
                           SUM(CASE WHEN s.payment_method = 'BANK_TRANSFER' THEN s.final_amount ELSE 0 END) as other_amount,
                           (SELECT COUNT(DISTINCT si.product_name) FROM sale_items si WHERE si.sale_id IN (SELECT id FROM sales s2 WHERE DATE(s2.created_at) = DATE(s.created_at)" .
                           ($branchId ? " AND s2.branch_id = ?" : "") . ")) as total_products_sold
                    FROM sales s
                    JOIN branches b ON s.branch_id = b.id
                    {$whereClause}
                    GROUP BY DATE(s.created_at), s.branch_id, b.name
                    ORDER BY sale_date DESC";

            $stmt = $this->conn->prepare($sql);
            if ($branchId) {
                $stmt->execute(array_merge($params, [$branchId]));
            } else {
                $stmt->execute($params);
            }
            return ['success' => true, 'daily_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get sales report by month/year
    public function getSalesReportByMonth($year = null, $branchId = null) {
        try {
            $currentYear = $year ?: date('Y');
            $whereConditions = ["YEAR(s.created_at) = ?"];
            $params = [$currentYear];

            if ($branchId) {
                $whereConditions[] = "s.branch_id = ?";
                $params[] = $branchId;
            }

            $whereClause = "WHERE " . implode(" AND ", $whereConditions);

            $sql = "SELECT YEAR(s.created_at) as year,
                           MONTH(s.created_at) as month,
                           MONTHNAME(s.created_at) as month_name,
                           COUNT(s.id) as total_sales,
                           SUM(s.final_amount) as total_amount,
                           SUM(CASE WHEN s.sale_type = 'CASH' THEN s.final_amount ELSE 0 END) as cash_amount,
                           SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END) as credit_amount,
                           AVG(s.final_amount) as average_daily_sales,
                           COUNT(DISTINCT s.customer_id) as unique_customers
                    FROM sales s
                    {$whereClause}
                    GROUP BY YEAR(s.created_at), MONTH(s.created_at)
                    ORDER BY year DESC, month DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'monthly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get sales report by year
    public function getSalesReportByYear($branchId = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($branchId) {
                $whereClause = "WHERE s.branch_id = ?";
                $params = [$branchId];
            }

            $sql = "SELECT YEAR(s.created_at) as year,
                           COUNT(s.id) as total_sales,
                           SUM(s.final_amount) as total_amount,
                           SUM(s.final_amount) / 12 as monthly_average,
                           (SELECT b.name FROM branches b JOIN sales s2 ON b.id = s2.branch_id WHERE YEAR(s2.created_at) = YEAR(s.created_at) GROUP BY b.id ORDER BY SUM(s2.final_amount) DESC LIMIT 1) as top_branch
                    FROM sales s
                    {$whereClause}
                    GROUP BY YEAR(s.created_at)
                    ORDER BY year DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'yearly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get all-time sales summary
    public function getAllTimeSalesSummary($branchId = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($branchId) {
                $whereClause = "WHERE s.branch_id = ?";
                $params = [$branchId];
            }

            $sql = "SELECT COUNT(s.id) as total_sales,
                           SUM(s.final_amount) as total_revenue,
                           AVG(s.final_amount) as average_sale_value,
                           SUM(CASE WHEN s.payment_method = 'CASH' THEN s.final_amount ELSE 0 END) as cash_sales,
                           SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END) as credit_sales,
                           SUM(CASE WHEN s.payment_method = 'MOBILE_MONEY' THEN s.final_amount ELSE 0 END) as mobile_money_sales,
                           SUM(CASE WHEN s.payment_method = 'BANK_TRANSFER' THEN s.final_amount ELSE 0 END) as bank_transfer_sales,
                           COUNT(DISTINCT s.customer_id) as active_customers,
                           COUNT(DISTINCT si.product_name) as products_sold,
                           MIN(s.created_at) as first_sale,
                           MAX(s.created_at) as latest_sale
                    FROM sales s
                    LEFT JOIN sale_items si ON s.id = si.sale_id
                    {$whereClause}";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'summary' => $stmt->fetch()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get branch performance comparison
    public function getBranchPerformanceComparison($startDate = null, $endDate = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = "WHERE DATE(s.created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }

            $dateCondition = "";
            if ($whereClause) {
                $dateCondition = " AND " . str_replace("WHERE ", "", $whereClause);
            }

            $sql = "SELECT b.name as branch_name,
                           COALESCE(COUNT(s.id), 0) as total_sales,
                           COALESCE(SUM(s.final_amount), 0) as total_revenue,
                           COALESCE(SUM(CASE WHEN s.sale_type = 'CASH' THEN s.final_amount ELSE 0 END), 0) as cash_sales,
                           COALESCE(SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END), 0) as credit_sales,
                           COALESCE(AVG(s.final_amount), 0) as average_sale_value,
                           COALESCE(COUNT(DISTINCT s.customer_id), 0) as unique_customers,
                           CASE
                               WHEN COUNT(s.id) > 0 THEN 75 + (COUNT(s.id) * 5)
                               ELSE 0
                           END as performance_score
                    FROM branches b
                    LEFT JOIN sales s ON b.id = s.branch_id{$dateCondition}
                    WHERE b.status = 'ACTIVE'
                    GROUP BY b.id, b.name
                    ORDER BY total_revenue DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'branch_performance' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get product performance analysis
    public function getProductPerformanceAnalysis($startDate = null, $endDate = null, $branchId = null) {
        try {
            $whereConditions = [];
            $params = [];

            if ($startDate && $endDate) {
                $whereConditions[] = "DATE(s.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($branchId) {
                $whereConditions[] = "s.branch_id = ?";
                $params[] = $branchId;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "SELECT si.product_type,
                           si.product_name,
                           COUNT(si.id) as sales_count,
                           SUM(si.quantity) as total_quantity,
                           si.unit,
                           SUM(si.total_price) as total_revenue,
                           AVG(si.unit_price) as average_unit_price,
                           COUNT(DISTINCT s.customer_id) as unique_customers,
                           COUNT(DISTINCT s.branch_id) as branches_sold_in,
                           CASE
                               WHEN SUM(si.total_price) > 100000 THEN 85 + (COUNT(si.id) * 2)
                               WHEN SUM(si.total_price) > 50000 THEN 70 + (COUNT(si.id) * 3)
                               ELSE 60 + (COUNT(si.id) * 4)
                           END as performance_score
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    {$whereClause}
                    GROUP BY si.product_type, si.product_name, si.unit
                    ORDER BY total_revenue DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'product_performance' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get payment method analysis
    public function getPaymentMethodAnalysis($startDate = null, $endDate = null, $branchId = null) {
        try {
            $whereConditions = [];
            $params = [];

            if ($startDate && $endDate) {
                $whereConditions[] = "DATE(s.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($branchId) {
                $whereConditions[] = "s.branch_id = ?";
                $params[] = $branchId;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "SELECT s.payment_method,
                           COUNT(s.id) as transaction_count,
                           SUM(s.final_amount) as total_amount,
                           AVG(s.final_amount) as avg_transaction_amount
                    FROM sales s
                    {$whereClause}
                    GROUP BY s.payment_method
                    ORDER BY total_amount DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            // Calculate percentages manually to avoid complex subquery
            $total = array_sum(array_column($results, 'total_amount'));
            foreach ($results as &$result) {
                $result['percentage'] = $total > 0 ? round(($result['total_amount'] / $total) * 100, 2) : 0;
            }
            return ['success' => true, 'payment_analysis' => $results];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get top customers analysis
    public function getTopCustomersAnalysis($limit = 10, $startDate = null, $endDate = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = "WHERE DATE(s.created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }

            $params[] = $limit;

            $sql = "SELECT c.name as customer_name,
                           c.customer_number,
                           b.name as branch_name,
                           COUNT(s.id) as total_purchases,
                           SUM(s.final_amount) as total_spent,
                           AVG(s.final_amount) as average_order_value,
                           SUM(CASE WHEN s.sale_type = 'CASH' THEN s.final_amount ELSE 0 END) as cash_purchases,
                           SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END) as credit_purchases,
                           MIN(s.created_at) as first_purchase,
                           MAX(s.created_at) as last_purchase
                    FROM customers c
                    JOIN sales s ON c.id = s.customer_id
                    JOIN branches b ON c.branch_id = b.id
                    {$whereClause}
                    GROUP BY c.id, c.name, c.customer_number, b.name
                    ORDER BY total_spent DESC
                    LIMIT ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'top_customers' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get credit vs cash sales comparison
    public function getCreditVsCashAnalysis($startDate = null, $endDate = null, $branchId = null) {
        try {
            $whereConditions = [];
            $params = [];

            if ($startDate && $endDate) {
                $whereConditions[] = "DATE(s.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($branchId) {
                $whereConditions[] = "s.branch_id = ?";
                $params[] = $branchId;
            }

            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

            $sql = "SELECT
                        SUM(CASE WHEN s.sale_type = 'CASH' OR s.payment_method IN ('CASH', 'MOBILE_MONEY', 'BANK_TRANSFER') THEN s.final_amount ELSE 0 END) as cash_total,
                        SUM(CASE WHEN s.sale_type = 'CREDIT' THEN s.final_amount ELSE 0 END) as credit_total,
                        COUNT(CASE WHEN s.sale_type = 'CASH' OR s.payment_method IN ('CASH', 'MOBILE_MONEY', 'BANK_TRANSFER') THEN 1 END) as cash_count,
                        COUNT(CASE WHEN s.sale_type = 'CREDIT' THEN 1 END) as credit_count,
                        SUM(s.final_amount) as total_sales
                    FROM sales s {$whereClause}";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            // Calculate percentages
            $totalSales = $result['total_sales'] ?: 1; // Avoid division by zero
            $analysis = [
                'cash_total' => $result['cash_total'] ?: 0,
                'credit_total' => $result['credit_total'] ?: 0,
                'cash_count' => $result['cash_count'] ?: 0,
                'credit_count' => $result['credit_count'] ?: 0,
                'cash_percentage' => ($result['cash_total'] / $totalSales) * 100,
                'credit_percentage' => ($result['credit_total'] / $totalSales) * 100,
                'total_sales' => $totalSales
            ];

            return ['success' => true, 'analysis' => $analysis];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // EXPENSE REPORTING METHODS

    // Get expense reports by date range (daily, weekly, custom)
    public function getExpenseReportByDateRange($startDate, $endDate, $branchId = null) {
        try {
            $whereClause = "WHERE DATE(e.created_at) BETWEEN ? AND ? AND e.status = 'APPROVED'";
            $params = [$startDate, $endDate];

            if ($branchId) {
                $whereClause .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT DATE(e.created_at) as expense_date,
                           b.name as branch_name,
                           COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           COUNT(DISTINCT et.category) as expense_categories,
                           COUNT(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN 1 END) as fleet_expenses,
                           COUNT(CASE WHEN e.machine_id IS NOT NULL THEN 1 END) as machine_expenses,
                           SUM(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN e.amount ELSE 0 END) as fleet_amount,
                           SUM(CASE WHEN e.machine_id IS NOT NULL THEN e.amount ELSE 0 END) as machine_amount
                    FROM expenses e
                    JOIN branches b ON e.branch_id = b.id
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY DATE(e.created_at), e.branch_id, b.name
                    ORDER BY expense_date DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'daily_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get monthly expense reports
    public function getExpenseReportByMonth($year, $month, $branchId = null) {
        try {
            $whereClause = "WHERE YEAR(e.created_at) = ? AND MONTH(e.created_at) = ? AND e.status = 'APPROVED'";
            $params = [$year, $month];

            if ($branchId) {
                $whereClause .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT YEAR(e.created_at) as year,
                           MONTH(e.created_at) as month,
                           MONTHNAME(e.created_at) as month_name,
                           COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           COUNT(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN 1 END) as fleet_expenses,
                           COUNT(CASE WHEN e.machine_id IS NOT NULL THEN 1 END) as machine_expenses,
                           SUM(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN e.amount ELSE 0 END) as fleet_amount,
                           SUM(CASE WHEN e.machine_id IS NOT NULL THEN e.amount ELSE 0 END) as machine_amount,
                           AVG(e.amount) as avg_expense_amount
                    FROM expenses e
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY YEAR(e.created_at), MONTH(e.created_at)
                    ORDER BY year DESC, month DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'monthly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get yearly expense reports
    public function getExpenseReportByYear($year, $branchId = null) {
        try {
            $whereClause = "WHERE YEAR(e.created_at) = ? AND e.status = 'APPROVED'";
            $params = [$year];

            if ($branchId) {
                $whereClause .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT YEAR(e.created_at) as year,
                           COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           COUNT(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN 1 END) as fleet_expenses,
                           COUNT(CASE WHEN e.machine_id IS NOT NULL THEN 1 END) as machine_expenses,
                           SUM(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN e.amount ELSE 0 END) as fleet_amount,
                           SUM(CASE WHEN e.machine_id IS NOT NULL THEN e.amount ELSE 0 END) as machine_amount,
                           AVG(e.amount) as avg_expense_amount,
                           COUNT(DISTINCT e.branch_id) as branches_with_expenses
                    FROM expenses e
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY YEAR(e.created_at)
                    ORDER BY year DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'yearly_reports' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get all-time expense summary
    public function getAllTimeExpenseSummary($branchId = null) {
        try {
            $whereClause = "WHERE e.status = 'APPROVED'";
            $params = [];

            if ($branchId) {
                $whereClause .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           AVG(e.amount) as avg_expense_amount,
                           COUNT(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN 1 END) as fleet_expenses,
                           COUNT(CASE WHEN e.machine_id IS NOT NULL THEN 1 END) as machine_expenses,
                           SUM(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN e.amount ELSE 0 END) as fleet_amount,
                           SUM(CASE WHEN e.machine_id IS NOT NULL THEN e.amount ELSE 0 END) as machine_amount,
                           COUNT(DISTINCT e.branch_id) as branches_with_expenses,
                           COUNT(DISTINCT et.category) as expense_categories,
                           MIN(e.created_at) as first_expense,
                           MAX(e.created_at) as latest_expense
                    FROM expenses e
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'summary' => $stmt->fetch()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get fleet vehicle expense analysis
    public function getFleetExpenseAnalysis($startDate = null, $endDate = null, $vehicleId = null) {
        try {
            $whereClause = "WHERE e.status = 'APPROVED' AND e.fleet_vehicle_id IS NOT NULL";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause .= " AND DATE(e.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($vehicleId) {
                $whereClause .= " AND e.fleet_vehicle_id = ?";
                $params[] = $vehicleId;
            }

            $sql = "SELECT fv.vehicle_number,
                           fv.make,
                           fv.model,
                           fv.vehicle_type,
                           COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           AVG(e.amount) as avg_expense_amount,
                           GROUP_CONCAT(DISTINCT et.name ORDER BY et.name SEPARATOR ', ') as expense_types,
                           MIN(e.created_at) as first_expense,
                           MAX(e.created_at) as latest_expense
                    FROM expenses e
                    JOIN fleet_vehicles fv ON e.fleet_vehicle_id = fv.id
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY fv.id, fv.vehicle_number, fv.make, fv.model, fv.vehicle_type
                    ORDER BY total_amount DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'fleet_analysis' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get machine expense analysis
    public function getMachineExpenseAnalysis($startDate = null, $endDate = null, $machineId = null) {
        try {
            $whereClause = "WHERE e.status = 'APPROVED' AND e.machine_id IS NOT NULL";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause .= " AND DATE(e.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($machineId) {
                $whereClause .= " AND e.machine_id = ?";
                $params[] = $machineId;
            }

            $sql = "SELECT cm.machine_number,
                           cm.machine_name,
                           cm.machine_type,
                           cm.brand,
                           cm.department,
                           COUNT(e.id) as total_expenses,
                           SUM(e.amount) as total_amount,
                           AVG(e.amount) as avg_expense_amount,
                           GROUP_CONCAT(DISTINCT et.name ORDER BY et.name SEPARATOR ', ') as expense_types,
                           MIN(e.created_at) as first_expense,
                           MAX(e.created_at) as latest_expense
                    FROM expenses e
                    JOIN company_machines cm ON e.machine_id = cm.id
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY cm.id, cm.machine_number, cm.machine_name, cm.machine_type, cm.brand, cm.department
                    ORDER BY total_amount DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'machine_analysis' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get expense type analysis
    public function getExpenseTypeAnalysis($startDate = null, $endDate = null, $branchId = null) {
        try {
            $whereClause = "WHERE e.status = 'APPROVED'";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause .= " AND DATE(e.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($branchId) {
                $whereClause .= " AND e.branch_id = ?";
                $params[] = $branchId;
            }

            $sql = "SELECT et.name as expense_type,
                           et.category,
                           COUNT(e.id) as total_count,
                           SUM(e.amount) as total_amount,
                           AVG(e.amount) as avg_amount,
                           MIN(e.amount) as min_amount,
                           MAX(e.amount) as max_amount,
                           COUNT(CASE WHEN e.fleet_vehicle_id IS NOT NULL THEN 1 END) as fleet_related,
                           COUNT(CASE WHEN e.machine_id IS NOT NULL THEN 1 END) as machine_related,
                           COUNT(DISTINCT e.branch_id) as branches_used
                    FROM expenses e
                    JOIN expense_types et ON e.expense_type_id = et.id
                    {$whereClause}
                    GROUP BY et.id, et.name, et.category
                    ORDER BY total_amount DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'expense_type_analysis' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // INVENTORY REPORTING METHODS

    // Get comprehensive inventory stock levels across all branches
    public function getInventoryStockLevels($branchId = null) {
        try {
            $result = [];

            // Raw Materials
            $whereClause = $branchId ? "WHERE rm.branch_id = ? AND rm.status = 'Active'" : "WHERE rm.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT rm.name, rm.unit_of_measure, rm.current_stock, rm.minimum_stock,
                           rm.cost_price, rm.selling_price,
                           CASE
                               WHEN rm.current_stock = 0 THEN 'OUT_OF_STOCK'
                               WHEN rm.current_stock <= rm.minimum_stock THEN 'LOW'
                               WHEN rm.current_stock <= (rm.minimum_stock * 2) THEN 'MEDIUM'
                               ELSE 'HIGH'
                           END as stock_status,
                           (rm.current_stock * rm.cost_price) as total_cost_value,
                           (rm.current_stock * COALESCE(rm.selling_price, rm.cost_price)) as total_sell_value
                    FROM raw_materials rm
                    {$whereClause}
                    ORDER BY rm.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['raw_materials'] = $stmt->fetchAll();

            // Finished Products (from product_bags and opened_bags)
            $whereClause = $branchId ? "AND pb.branch_id = ?" : "";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT p.name, p.package_size,
                           COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) as sealed_bags,
                           COUNT(CASE WHEN pb.status = 'OPENED' THEN 1 END) as opened_bags,
                           (COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) * CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2))) as sealed_weight,
                           COALESCE(SUM(ob.current_weight_kg), 0) as opened_weight,
                           (COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) * CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2)) + COALESCE(SUM(ob.current_weight_kg), 0)) as total_weight,
                           CASE WHEN p.cost_price > 0 THEN p.cost_price / CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2)) ELSE 3000 END as cost_price_per_kg,
                           CASE WHEN p.unit_price > 0 THEN p.unit_price / CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2)) ELSE 4500 END as selling_price_per_kg,
                           (COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) * CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2)) + COALESCE(SUM(ob.current_weight_kg), 0)) *
                           CASE WHEN p.unit_price > 0 THEN p.unit_price / CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2)) ELSE 4500 END as total_value
                    FROM products p
                    LEFT JOIN product_bags pb ON p.id = pb.product_id
                    LEFT JOIN opened_bags ob ON pb.id = ob.bag_id
                    WHERE p.status = 'Active' {$whereClause}
                    GROUP BY p.id, p.name, p.package_size, p.cost_price, p.unit_price
                    HAVING COUNT(pb.id) > 0
                    ORDER BY p.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['finished_products'] = $stmt->fetchAll();

            // Third Party Products
            $whereClause = $branchId ? "WHERE tp.branch_id = ? AND tp.status = 'Active'" : "WHERE tp.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT tp.name, tp.brand, tp.unit_of_measure, tp.current_stock, tp.minimum_stock,
                           tp.cost_price, tp.selling_price,
                           CASE
                               WHEN tp.current_stock = 0 THEN 'OUT_OF_STOCK'
                               WHEN tp.current_stock <= tp.minimum_stock THEN 'LOW'
                               WHEN tp.current_stock <= (tp.minimum_stock * 2) THEN 'MEDIUM'
                               ELSE 'HIGH'
                           END as stock_status,
                           (tp.current_stock * tp.cost_price) as total_cost_value,
                           (tp.current_stock * tp.selling_price) as total_sell_value,
                           (tp.current_stock * tp.selling_price) as total_value
                    FROM third_party_products tp
                    {$whereClause}
                    ORDER BY tp.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['third_party_products'] = $stmt->fetchAll();

            // Packaging Materials
            $whereClause = $branchId ? "WHERE pm.branch_id = ? AND pm.status = 'Active'" : "WHERE pm.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT pm.name, pm.unit, pm.current_stock, pm.minimum_stock, pm.unit_cost,
                           CASE
                               WHEN pm.current_stock = 0 THEN 'OUT_OF_STOCK'
                               WHEN pm.current_stock <= pm.minimum_stock THEN 'LOW'
                               WHEN pm.current_stock <= (pm.minimum_stock * 2) THEN 'MEDIUM'
                               ELSE 'HIGH'
                           END as stock_status,
                           (pm.current_stock * pm.unit_cost) as total_value
                    FROM packaging_materials pm
                    {$whereClause}
                    ORDER BY pm.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['packaging_materials'] = $stmt->fetchAll();

            return ['success' => true] + $result;
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get inventory movements for reconciliation
    public function getInventoryMovements($startDate = null, $endDate = null, $branchId = null, $productType = null) {
        try {
            $whereClause = "WHERE 1=1";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause .= " AND DATE(im.created_at) BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            if ($branchId) {
                $whereClause .= " AND im.branch_id = ?";
                $params[] = $branchId;
            }

            if ($productType) {
                $whereClause .= " AND im.product_type = ?";
                $params[] = $productType;
            }

            $sql = "SELECT im.*, b.name as branch_name, u.full_name as user_name,
                           CASE im.product_type
                               WHEN 'RAW_MATERIAL' THEN rm.name
                               WHEN 'THIRD_PARTY_PRODUCT' THEN tp.name
                               WHEN 'PACKAGING_MATERIAL' THEN pm.name
                               WHEN 'FINISHED_PRODUCT' THEN p.name
                           END as product_name,
                           DATE(im.created_at) as movement_date
                    FROM inventory_movements im
                    JOIN branches b ON im.branch_id = b.id
                    JOIN users u ON im.created_by = u.id
                    LEFT JOIN raw_materials rm ON im.product_type = 'RAW_MATERIAL' AND im.product_id = rm.id
                    LEFT JOIN third_party_products tp ON im.product_type = 'THIRD_PARTY_PRODUCT' AND im.product_id = tp.id
                    LEFT JOIN packaging_materials pm ON im.product_type = 'PACKAGING_MATERIAL' AND im.product_id = pm.id
                    LEFT JOIN products p ON im.product_type = 'FINISHED_PRODUCT' AND im.product_id = p.id
                    {$whereClause}
                    ORDER BY im.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $movements = $stmt->fetchAll();

            // Calculate summary statistics
            $summary = [
                'total_movements' => count($movements),
                'stock_additions' => 0,
                'stock_deductions' => 0
            ];

            foreach ($movements as $movement) {
                if (in_array($movement['movement_type'], ['STOCK_IN', 'TRANSFER_IN', 'STOCK_ADDITION', 'PRODUCTION'])) {
                    $summary['stock_additions']++;
                } else {
                    $summary['stock_deductions']++;
                }
            }

            return [
                'success' => true,
                'movements' => $movements,
                'summary' => $summary
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get inventory value analysis
    public function getInventoryValueAnalysis($branchId = null) {
        try {
            // Calculate totals for summary
            $totalCostValue = 0;
            $totalSellingValue = 0;
            $categories = [];

            // Raw Materials Value
            $whereClause = $branchId ? "WHERE rm.branch_id = ? AND rm.status = 'Active'" : "WHERE rm.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'Raw Materials' as category,
                           COUNT(*) as items_count,
                           SUM(rm.current_stock * rm.cost_price) as total_cost_value,
                           SUM(rm.current_stock * COALESCE(rm.selling_price, rm.cost_price)) as total_selling_value
                    FROM raw_materials rm
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rawMaterials = $stmt->fetch();
            if ($rawMaterials) {
                $categories[] = $rawMaterials;
                $totalCostValue += $rawMaterials['total_cost_value'];
                $totalSellingValue += $rawMaterials['total_selling_value'];
            }

            // Finished Products Value - Calculate from bags and opened weight
            $whereClause = $branchId ? "AND pb.branch_id = ?" : "";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT p.id, p.name, p.package_size,
                           COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) as sealed_bags,
                           COALESCE(SUM(ob.current_weight_kg), 0) as opened_weight,
                           (COUNT(CASE WHEN pb.status = 'SEALED' THEN 1 END) * CAST(REPLACE(p.package_size, 'KG', '') AS DECIMAL(10,2))) as sealed_weight
                    FROM products p
                    LEFT JOIN product_bags pb ON p.id = pb.product_id
                    LEFT JOIN opened_bags ob ON pb.id = ob.bag_id
                    WHERE p.status = 'Active' {$whereClause}
                    GROUP BY p.id, p.name, p.package_size
                    HAVING COUNT(pb.id) > 0";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $finishedProducts = $stmt->fetchAll();

            if ($finishedProducts && count($finishedProducts) > 0) {
                $fpCostValue = 0;
                $fpSellingValue = 0;

                foreach ($finishedProducts as $product) {
                    $totalWeight = $product['sealed_weight'] + $product['opened_weight'];
                    $fpCostValue += $totalWeight * 3000; // 3000 TZS per KG cost
                    $fpSellingValue += $totalWeight * 4500; // 4500 TZS per KG selling
                }

                $categories[] = [
                    'category' => 'Finished Products',
                    'items_count' => count($finishedProducts),
                    'total_cost_value' => $fpCostValue,
                    'total_selling_value' => $fpSellingValue
                ];
                $totalCostValue += $fpCostValue;
                $totalSellingValue += $fpSellingValue;
            }

            // Third Party Products Value
            $whereClause = $branchId ? "WHERE tp.branch_id = ? AND tp.status = 'Active'" : "WHERE tp.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'Third Party Products' as category,
                           COUNT(*) as items_count,
                           SUM(tp.current_stock * tp.cost_price) as total_cost_value,
                           SUM(tp.current_stock * tp.selling_price) as total_selling_value
                    FROM third_party_products tp
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $thirdParty = $stmt->fetch();
            if ($thirdParty) {
                $categories[] = $thirdParty;
                $totalCostValue += $thirdParty['total_cost_value'];
                $totalSellingValue += $thirdParty['total_selling_value'];
            }

            // Packaging Materials Value
            $whereClause = $branchId ? "WHERE pm.branch_id = ? AND pm.status = 'Active'" : "WHERE pm.status = 'Active'";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'Packaging Materials' as category,
                           COUNT(*) as items_count,
                           SUM(pm.current_stock * pm.unit_cost) as total_cost_value,
                           SUM(pm.current_stock * pm.unit_cost) as total_selling_value
                    FROM packaging_materials pm
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $packaging = $stmt->fetch();
            if ($packaging) {
                $categories[] = $packaging;
                $totalCostValue += $packaging['total_cost_value'];
                $totalSellingValue += $packaging['total_selling_value'];
            }

            return [
                'success' => true,
                'summary' => [
                    'total_cost_value' => $totalCostValue,
                    'total_selling_value' => $totalSellingValue
                ],
                'categories' => $categories
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get low stock alerts
    public function getLowStockAlerts($branchId = null) {
        try {
            $result = [];

            // Raw Materials Low Stock
            $whereClause = $branchId ? "WHERE rm.branch_id = ? AND rm.current_stock <= rm.minimum_stock" : "WHERE rm.current_stock <= rm.minimum_stock";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'RAW_MATERIAL' as type, rm.name, rm.current_stock, rm.minimum_stock,
                           rm.unit_of_measure, b.name as branch_name,
                           ((rm.minimum_stock - rm.current_stock) * rm.cost_price) as reorder_cost
                    FROM raw_materials rm
                    JOIN branches b ON rm.branch_id = b.id
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['raw_materials'] = $stmt->fetchAll();

            // Third Party Products Low Stock
            $whereClause = $branchId ? "WHERE tp.branch_id = ? AND tp.current_stock <= tp.minimum_stock" : "WHERE tp.current_stock <= tp.minimum_stock";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'THIRD_PARTY' as type, tp.name, tp.current_stock, tp.minimum_stock,
                           tp.unit_of_measure, b.name as branch_name,
                           ((tp.minimum_stock - tp.current_stock) * tp.cost_price) as reorder_cost
                    FROM third_party_products tp
                    JOIN branches b ON tp.branch_id = b.id
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['third_party_products'] = $stmt->fetchAll();

            // Packaging Materials Low Stock
            $whereClause = $branchId ? "WHERE pm.branch_id = ? AND pm.current_stock <= pm.minimum_stock" : "WHERE pm.current_stock <= pm.minimum_stock";
            $params = $branchId ? [$branchId] : [];

            $sql = "SELECT 'PACKAGING' as type, pm.name, pm.current_stock, pm.minimum_stock,
                           pm.unit, b.name as branch_name,
                           ((pm.minimum_stock - pm.current_stock) * pm.unit_cost) as reorder_cost
                    FROM packaging_materials pm
                    JOIN branches b ON pm.branch_id = b.id
                    {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result['packaging_materials'] = $stmt->fetchAll();

            return ['success' => true, 'low_stock_alerts' => $result];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // PROFIT & LOSS REPORTING METHODS

    // Get comprehensive Profit & Loss report with time-based filtering
    public function getProfitAndLossReport($reportType = 'all-time', $startDate = null, $endDate = null, $year = null) {
        try {
            // Determine date filter based on report type
            $dateFilter = $this->getPLDateFilter($reportType, $startDate, $endDate, $year);

            // Calculate all financial components
            $revenue = $this->calculateRevenue($dateFilter['where'], $dateFilter['params']);
            $cogs = $this->calculateCOGS($dateFilter['where'], $dateFilter['params']);
            $opex = $this->calculateOperatingExpenses($dateFilter['where'], $dateFilter['params']);
            $payroll = $this->calculatePayrollExpenses($dateFilter['where'], $dateFilter['params']);

            // Build result with frontend-expected structure
            $result = [
                'period' => $dateFilter['period'],
                'revenue' => [
                    'total_revenue' => floatval($revenue['total'] ?: 0),
                    'cash_sales' => floatval($revenue['cash_sales'] ?: 0),
                    'credit_sales' => floatval($revenue['credit_sales'] ?: 0),
                    'total_transactions' => intval($revenue['total_sales'] ?: 0)
                ],
                'cogs' => [
                    'total_cogs' => floatval($cogs['total'] ?: 0),
                    'production_costs' => floatval($cogs['breakdown']['production_costs'] ?: 0),
                    'purchase_costs' => floatval($cogs['breakdown']['inventory_purchases'] ?: 0)
                ],
                'operating_expenses' => [
                    'total_expenses' => floatval($opex['total'] ?: 0),
                    'breakdown' => $opex['by_category'] ?: []
                ],
                'payroll_expenses' => [
                    'total_payroll' => floatval($payroll['total'] ?: 0),
                    'basic_salary' => floatval($payroll['basic_salary'] ?: 0),
                    'allowances' => floatval($payroll['allowances'] ?: 0),
                    'overtime' => floatval($payroll['overtime'] ?: 0)
                ]
            ];

            // Calculate profit metrics
            $result['gross_profit'] = $result['revenue']['total_revenue'] - $result['cogs']['total_cogs'];
            $result['operating_profit'] = $result['gross_profit'] - $result['operating_expenses']['total_expenses'];
            $result['net_profit'] = $result['operating_profit'] - $result['payroll_expenses']['total_payroll'];

            // Calculate margins (rounded to 1 decimal)
            $result['gross_profit_margin'] = $result['revenue']['total_revenue'] > 0 ?
                round(($result['gross_profit'] / $result['revenue']['total_revenue'] * 100), 1) : 0.0;
            $result['operating_profit_margin'] = $result['revenue']['total_revenue'] > 0 ?
                round(($result['operating_profit'] / $result['revenue']['total_revenue'] * 100), 1) : 0.0;
            $result['net_profit_margin'] = $result['revenue']['total_revenue'] > 0 ?
                round(($result['net_profit'] / $result['revenue']['total_revenue'] * 100), 1) : 0.0;

            return ['success' => true] + $result;

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Helper: Get date filter conditions for P&L reports
    private function getPLDateFilter($reportType, $startDate, $endDate, $year) {
        $where = "";
        $params = [];
        $period = "";

        switch ($reportType) {
            case 'daily':
                $date = $startDate ?: date('Y-m-d');
                $where = "DATE(created_at) = ?";
                $params = [$date];
                $period = "Daily P&L for " . date('M d, Y', strtotime($date));
                break;

            case 'weekly':
                $endDate = $startDate ? date('Y-m-d', strtotime($startDate . ' +6 days')) : date('Y-m-d');
                $startDate = $startDate ?: date('Y-m-d', strtotime('-6 days'));
                $where = "DATE(created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
                $period = "Weekly P&L (" . date('M d', strtotime($startDate)) . " - " . date('M d, Y', strtotime($endDate)) . ")";
                break;

            case 'monthly':
                $year = $year ?: date('Y');
                $month = $startDate ?: date('m');
                $where = "YEAR(created_at) = ? AND MONTH(created_at) = ?";
                $params = [$year, $month];
                $period = "Monthly P&L for " . date('F Y', mktime(0, 0, 0, $month, 1, $year));
                break;

            case 'custom':
                $where = "DATE(created_at) BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
                $period = "P&L for " . date('M d', strtotime($startDate)) . " - " . date('M d, Y', strtotime($endDate));
                break;

            case 'yearly':
                $year = $year ?: date('Y');
                $where = "YEAR(created_at) = ?";
                $params = [$year];
                $period = "Annual P&L for " . $year;
                break;

            default: // all-time
                $where = "1=1";
                $params = [];
                $period = "All-Time P&L";
                break;
        }

        return ['where' => $where, 'params' => $params, 'period' => $period];
    }

    // Calculate total revenue from sales
    private function calculateRevenue($whereClause, $params) {
        try {
            // Total sales revenue
            $sql = "SELECT COUNT(*) as total_sales,
                           SUM(final_amount) as total_revenue,
                           SUM(CASE WHEN sale_type = 'CASH' THEN final_amount ELSE 0 END) as cash_sales,
                           SUM(CASE WHEN sale_type = 'CREDIT' THEN final_amount ELSE 0 END) as credit_sales
                    FROM sales
                    WHERE {$whereClause}";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $revenue = $stmt->fetch();

            return [
                'total' => floatval($revenue['total_revenue'] ?: 0),
                'cash_sales' => floatval($revenue['cash_sales'] ?: 0),
                'credit_sales' => floatval($revenue['credit_sales'] ?: 0),
                'total_transactions' => intval($revenue['total_sales'] ?: 0)
            ];

        } catch (Exception $e) {
            return ['total' => 0, 'cash_sales' => 0, 'credit_sales' => 0, 'total_transactions' => 0];
        }
    }

    // Calculate Cost of Goods Sold (COGS)
    private function calculateCOGS($whereClause, $params) {
        try {
            $totalCOGS = 0;
            $breakdown = [];

            // Production costs
            $sql = "SELECT COUNT(*) as batches, SUM(production_cost) as total_production_cost
                    FROM production_batches
                    WHERE status = 'COMPLETED' AND {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $production = $stmt->fetch();
            $productionCost = floatval($production['total_production_cost'] ?: 0);
            $totalCOGS += $productionCost;
            $breakdown['production_costs'] = $productionCost;

            // Raw materials consumed in production
            $sql = "SELECT SUM(pbm.total_cost) as raw_materials_cost
                    FROM production_batch_materials pbm
                    JOIN production_batches pb ON pbm.batch_id = pb.id
                    WHERE pb.status = 'COMPLETED' AND {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rawMaterials = $stmt->fetch();
            $rawMaterialsCost = floatval($rawMaterials['raw_materials_cost'] ?: 0);
            // Note: Raw materials are already included in production_cost, so we track separately
            $breakdown['raw_materials_consumed'] = $rawMaterialsCost;

            // Inventory purchases (for resale items)
            $sql = "SELECT COUNT(*) as purchases, SUM(total_amount) as total_purchases_cost
                    FROM purchases
                    WHERE {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $purchases = $stmt->fetch();
            $purchasesCost = floatval($purchases['total_purchases_cost'] ?: 0);
            $totalCOGS += $purchasesCost;
            $breakdown['inventory_purchases'] = $purchasesCost;

            return [
                'total' => $totalCOGS,
                'breakdown' => $breakdown
            ];

        } catch (Exception $e) {
            return ['total' => 0, 'breakdown' => []];
        }
    }

    // Calculate operating expenses
    private function calculateOperatingExpenses($whereClause, $params) {
        try {
            // Total operating expenses by category
            $sql = "SELECT et.category, et.name as expense_type,
                           COUNT(e.id) as count, SUM(e.amount) as amount
                    FROM expenses e
                    JOIN expense_types et ON e.expense_type_id = et.id
                    WHERE e.status = 'APPROVED' AND {$whereClause}
                    GROUP BY et.category, et.name
                    ORDER BY amount DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $expenseBreakdown = $stmt->fetchAll();

            // Calculate totals by category
            $totalExpenses = 0;
            $categories = [];

            foreach ($expenseBreakdown as $expense) {
                $amount = floatval($expense['amount']);
                $totalExpenses += $amount;

                $category = $expense['category'];
                if (!isset($categories[$category])) {
                    $categories[$category] = 0;
                }
                $categories[$category] += $amount;
            }

            return [
                'total' => $totalExpenses,
                'by_category' => $categories,
                'detailed_breakdown' => $expenseBreakdown
            ];

        } catch (Exception $e) {
            return ['total' => 0, 'by_category' => [], 'detailed_breakdown' => []];
        }
    }

    // Calculate payroll expenses
    private function calculatePayrollExpenses($whereClause, $params) {
        try {
            $sql = "SELECT COUNT(*) as payroll_count,
                           SUM(net_salary) as total_payroll,
                           SUM(basic_salary) as total_basic,
                           SUM(allowances) as total_allowances,
                           SUM(overtime_amount) as total_overtime
                    FROM payroll_records
                    WHERE {$whereClause}";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $payroll = $stmt->fetch();

            return [
                'total' => floatval($payroll['total_payroll'] ?: 0),
                'basic_salary' => floatval($payroll['total_basic'] ?: 0),
                'allowances' => floatval($payroll['total_allowances'] ?: 0),
                'overtime' => floatval($payroll['total_overtime'] ?: 0),
                'payroll_records' => intval($payroll['payroll_count'] ?: 0)
            ];

        } catch (Exception $e) {
            return ['total' => 0, 'basic_salary' => 0, 'allowances' => 0, 'overtime' => 0, 'payroll_records' => 0];
        }
    }
}
?>