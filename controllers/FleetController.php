<?php
// File: controllers/FleetController.php
// Fleet and Machine management controller with expense integration

class FleetController {
    private $pdo;

   public function __construct() {
        $this->pdo = getDbConnection();
    }

    // Generate vehicle number
    private function generateVehicleNumber() {
        $sql = "SELECT COUNT(*) as count FROM fleet_vehicles";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'JMF-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Generate machine number
    private function generateMachineNumber() {
        $sql = "SELECT COUNT(*) as count FROM company_machines";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'MCH-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Get all fleet vehicles
    public function getFleetVehicles($branchId = null) {
        $sql = "SELECT fv.*, u.full_name as driver_name, b.name as branch_name
                FROM fleet_vehicles fv
                LEFT JOIN users u ON fv.assigned_driver_id = u.id
                JOIN branches b ON fv.branch_id = b.id
                WHERE 1=1";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND fv.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " ORDER BY fv.vehicle_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get available drivers (users with Driver role)
    public function getAvailableDrivers() {
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, b.name as branch_name
                FROM users u
                JOIN user_roles ur ON u.role_id = ur.id
                JOIN branches b ON u.branch_id = b.id
                WHERE ur.role_name = 'Driver' AND u.status = 'ACTIVE'
                ORDER BY u.full_name";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create new fleet vehicle
    public function createFleetVehicle($data, $createdBy) {
        try {
            $this->pdo->beginTransaction();

            $vehicleNumber = !empty($data['vehicle_number']) ? $data['vehicle_number'] : $this->generateVehicleNumber();

            $sql = "INSERT INTO fleet_vehicles (vehicle_number, license_plate, make, model, vehicle_type,
                                             year_manufacture, fuel_type, capacity_tonnes, assigned_driver_id,
                                             branch_id, purchase_date, purchase_cost, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $vehicleNumber, $data['license_plate'], $data['make'], $data['model'],
                $data['vehicle_type'], $data['year_manufacture'], $data['fuel_type'],
                $data['capacity_tonnes'], $data['assigned_driver_id'], $data['branch_id'],
                $data['purchase_date'], $data['purchase_cost'], $data['notes'], $createdBy
            ]);

            $vehicleId = $this->pdo->lastInsertId();

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $createdBy, 'FLEET_VEHICLE_CREATED', 'FLEET',
                "Fleet vehicle {$vehicleNumber} ({$data['make']} {$data['model']}) created"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'vehicle_number' => $vehicleNumber, 'vehicle_id' => $vehicleId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Update fleet vehicle
    public function updateFleetVehicle($vehicleId, $data, $updatedBy) {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE fleet_vehicles SET
                        license_plate = ?, make = ?, model = ?, vehicle_type = ?,
                        year_manufacture = ?, fuel_type = ?, capacity_tonnes = ?,
                        assigned_driver_id = ?, branch_id = ?, status = ?,
                        purchase_date = ?, purchase_cost = ?, current_mileage = ?, notes = ?
                    WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['license_plate'], $data['make'], $data['model'], $data['vehicle_type'],
                $data['year_manufacture'], $data['fuel_type'], $data['capacity_tonnes'],
                $data['assigned_driver_id'], $data['branch_id'], $data['status'],
                $data['purchase_date'], $data['purchase_cost'], $data['current_mileage'],
                $data['notes'], $vehicleId
            ]);

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $updatedBy, 'FLEET_VEHICLE_UPDATED', 'FLEET',
                "Fleet vehicle ID {$vehicleId} updated"
            ]);

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get company machines
    public function getCompanyMachines($branchId = null) {
        $sql = "SELECT cm.*, b.name as branch_name
                FROM company_machines cm
                JOIN branches b ON cm.branch_id = b.id
                WHERE 1=1";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND cm.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " ORDER BY cm.machine_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create new machine
    public function createMachine($data, $createdBy) {
        try {
            $this->pdo->beginTransaction();

            $machineNumber = !empty($data['machine_number']) ? $data['machine_number'] : $this->generateMachineNumber();

            $sql = "INSERT INTO company_machines (machine_number, machine_name, machine_type, brand, model,
                                                serial_number, branch_id, department, purchase_date,
                                                purchase_cost, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $machineNumber, $data['machine_name'], $data['machine_type'], $data['brand'],
                $data['model'], $data['serial_number'], $data['branch_id'], $data['department'],
                $data['purchase_date'], $data['purchase_cost'], $data['notes'], $createdBy
            ]);

            $machineId = $this->pdo->lastInsertId();

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $createdBy, 'MACHINE_CREATED', 'FLEET',
                "Machine {$machineNumber} ({$data['machine_name']}) created"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'machine_number' => $machineNumber, 'machine_id' => $machineId];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Update machine
    public function updateMachine($machineId, $data, $updatedBy) {
        try {
            $this->pdo->beginTransaction();

            $sql = "UPDATE company_machines SET
                        machine_name = ?, machine_type = ?, brand = ?, model = ?,
                        serial_number = ?, branch_id = ?, department = ?, status = ?,
                        purchase_date = ?, purchase_cost = ?, notes = ?
                    WHERE id = ?";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['machine_name'], $data['machine_type'], $data['brand'], $data['model'],
                $data['serial_number'], $data['branch_id'], $data['department'], $data['status'],
                $data['purchase_date'], $data['purchase_cost'], $data['notes'], $machineId
            ]);

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $updatedBy, 'MACHINE_UPDATED', 'FLEET',
                "Machine ID {$machineId} updated"
            ]);

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get fleet and machine expense types
    public function getFleetExpenseTypes() {
        $sql = "SELECT * FROM expense_types
                WHERE name LIKE '%Fleet%' OR name LIKE '%Machine%' OR category = 'FUEL'
                AND status = 'ACTIVE'
                ORDER BY category, name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get vehicle expenses
    public function getVehicleExpenses($vehicleId) {
        $sql = "SELECT e.*, et.name as expense_type_name, u.full_name as requested_by_name
                FROM expenses e
                JOIN expense_types et ON e.expense_type_id = et.id
                JOIN users u ON e.user_id = u.id
                WHERE e.fleet_vehicle_id = ?
                ORDER BY e.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get machine expenses
    public function getMachineExpenses($machineId) {
        $sql = "SELECT e.*, et.name as expense_type_name, u.full_name as requested_by_name
                FROM expenses e
                JOIN expense_types et ON e.expense_type_id = et.id
                JOIN users u ON e.user_id = u.id
                WHERE e.machine_id = ?
                ORDER BY e.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$machineId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update vehicle total expenses
    public function updateVehicleExpenses($vehicleId) {
        $sql = "UPDATE fleet_vehicles SET
                    total_expenses = (
                        SELECT COALESCE(SUM(amount), 0)
                        FROM expenses
                        WHERE fleet_vehicle_id = ? AND status = 'APPROVED'
                    )
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vehicleId, $vehicleId]);
    }

    // Update machine total expenses
    public function updateMachineExpenses($machineId) {
        $sql = "UPDATE company_machines SET
                    total_expenses = (
                        SELECT COALESCE(SUM(amount), 0)
                        FROM expenses
                        WHERE machine_id = ? AND status = 'APPROVED'
                    )
                WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$machineId, $machineId]);
    }

    // Get fleet statistics
    public function getFleetStats() {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM fleet_vehicles WHERE status = 'ACTIVE') as active_vehicles,
                    (SELECT COUNT(*) FROM fleet_vehicles) as total_vehicles,
                    (SELECT COUNT(*) FROM company_machines WHERE status = 'ACTIVE') as active_machines,
                    (SELECT COUNT(*) FROM company_machines) as total_machines,
                    (SELECT COUNT(*) FROM users u JOIN user_roles ur ON u.role_id = ur.id WHERE ur.role_name = 'Driver' AND u.status = 'ACTIVE') as available_drivers,
                    (SELECT COALESCE(SUM(total_expenses), 0) FROM fleet_vehicles) as total_fleet_expenses,
                    (SELECT COALESCE(SUM(total_expenses), 0) FROM company_machines) as total_machine_expenses";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get vehicle details with expenses
    public function getVehicleDetails($vehicleId) {
        $sql = "SELECT fv.*, u.full_name as driver_name, u.phone as driver_phone,
                       b.name as branch_name
                FROM fleet_vehicles fv
                LEFT JOIN users u ON fv.assigned_driver_id = u.id
                JOIN branches b ON fv.branch_id = b.id
                WHERE fv.id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vehicle) {
            $vehicle['expenses'] = $this->getVehicleExpenses($vehicleId);
        }

        return $vehicle;
    }

    // Get machine details with expenses
    public function getMachineDetails($machineId) {
        $sql = "SELECT cm.*, b.name as branch_name
                FROM company_machines cm
                JOIN branches b ON cm.branch_id = b.id
                WHERE cm.id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$machineId]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($machine) {
            $machine['expenses'] = $this->getMachineExpenses($machineId);
        }

        return $machine;
    }

    // Get branches for selection
    public function getBranches() {
        $sql = "SELECT * FROM branches ORDER BY name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get maintenance records
    public function getMaintenanceRecords($vehicleId = null, $machineId = null, $limit = 50) {
        $sql = "SELECT fm.*,
                       fv.vehicle_number, fv.make as vehicle_make, fv.model as vehicle_model,
                       cm.machine_number, cm.machine_name,
                       u1.full_name as performed_by_name,
                       u2.full_name as approved_by_name,
                       b.name as branch_name
                FROM fleet_maintenance fm
                LEFT JOIN fleet_vehicles fv ON fm.vehicle_id = fv.id
                LEFT JOIN company_machines cm ON fm.machine_id = cm.id
                JOIN users u1 ON fm.performed_by = u1.id
                LEFT JOIN users u2 ON fm.approved_by = u2.id
                JOIN branches b ON fm.branch_id = b.id
                WHERE 1=1";

        $params = [];
        if ($vehicleId !== null) {
            $sql .= " AND fm.vehicle_id = ?";
            $params[] = $vehicleId;
        }
        if ($machineId !== null) {
            $sql .= " AND fm.machine_id = ?";
            $params[] = $machineId;
        }

        $sql .= " ORDER BY fm.maintenance_date DESC, fm.created_at DESC LIMIT " . intval($limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get maintenance details with items
    public function getMaintenanceDetails($maintenanceId) {
        try {
            // Get maintenance record
            $sql = "SELECT fm.*,
                           fv.vehicle_number, fv.make as vehicle_make, fv.model as vehicle_model, fv.license_plate,
                           cm.machine_number, cm.machine_name, cm.machine_type,
                           u1.full_name as performed_by_name,
                           u2.full_name as approved_by_name,
                           b.name as branch_name
                    FROM fleet_maintenance fm
                    LEFT JOIN fleet_vehicles fv ON fm.vehicle_id = fv.id
                    LEFT JOIN company_machines cm ON fm.machine_id = cm.id
                    JOIN users u1 ON fm.performed_by = u1.id
                    LEFT JOIN users u2 ON fm.approved_by = u2.id
                    JOIN branches b ON fm.branch_id = b.id
                    WHERE fm.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$maintenanceId]);
            $maintenance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$maintenance) {
                return ['success' => false, 'error' => 'Maintenance record not found'];
            }

            // Get maintenance items
            $sql = "SELECT * FROM maintenance_items WHERE maintenance_id = ? ORDER BY item_name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$maintenanceId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'maintenance' => $maintenance,
                'items' => $items
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Generate maintenance number
    private function generateMaintenanceNumber() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM fleet_maintenance WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'MNT' . $date . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
?>