<?php
// File: controllers/PurchaseController.php
// Purchase and supplier management controller

class PurchaseController {
    private $pdo;

   public function __construct() {
        $this->pdo = getDbConnection();
    }

    // Generate purchase number
    private function generatePurchaseNumber() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM purchases WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'PUR' . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Generate supplier code
    private function generateSupplierCode() {
        $sql = "SELECT COUNT(*) as count FROM suppliers";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'SUP' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    // Get suppliers
    public function getSuppliers($status = 'ACTIVE') {
        $sql = "SELECT * FROM suppliers WHERE status = ? ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Create supplier
    public function createSupplier($name, $contactPerson, $phone, $email, $address, $paymentTerms, $creditLimit, $createdBy) {
        try {
            $this->pdo->beginTransaction();

            $supplierCode = $this->generateSupplierCode();

            $sql = "INSERT INTO suppliers (supplier_code, name, contact_person, phone, email, address, payment_terms, credit_limit, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierCode, $name, $contactPerson, $phone, $email, $address, $paymentTerms, $creditLimit, $createdBy]);

            $supplierId = $this->pdo->lastInsertId();

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $createdBy,
                'SUPPLIER_CREATED',
                'SUPPLIERS',
                "Supplier {$supplierCode} - {$name} created"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'supplier_code' => $supplierCode];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get products for purchase
    public function getProductsForPurchase($branchId) {
        $products = [];

        // Raw materials
        $sql = "SELECT id, name, unit_of_measure as unit, cost_price, current_stock, 'RAW_MATERIAL' as type
                FROM raw_materials WHERE branch_id = ? AND status = 'Active' ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$branchId]);
        $rawMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Third party products
        $sql = "SELECT id, name, unit_of_measure as unit, cost_price, current_stock, 'THIRD_PARTY_PRODUCT' as type
                FROM third_party_products WHERE branch_id = ? AND status = 'Active' ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$branchId]);
        $thirdParty = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Packaging materials
        $sql = "SELECT id, name, unit, unit_cost as cost_price, current_stock, 'PACKAGING_MATERIAL' as type
                FROM packaging_materials WHERE branch_id = ? AND status = 'Active' ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$branchId]);
        $packaging = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_merge($rawMaterials, $thirdParty, $packaging);
    }

    // Create purchase
    public function createPurchase($supplierId, $branchId, $purchasedBy, $purchaseDate, $paymentMethod, $items, $notes = '') {
        try {
            $this->pdo->beginTransaction();

            $purchaseNumber = $this->generatePurchaseNumber();
            $totalAmount = 0;

            // Calculate total amount
            foreach ($items as $item) {
                $totalAmount += $item['quantity'] * $item['unit_cost'];
            }

            // Determine payment status
            $paymentStatus = ($paymentMethod === 'CREDIT') ? 'PENDING' : 'PAID';
            $amountPaid = ($paymentMethod === 'CREDIT') ? 0 : $totalAmount;
            $amountDue = ($paymentMethod === 'CREDIT') ? $totalAmount : 0;

            // Create purchase record
            $sql = "INSERT INTO purchases (purchase_number, supplier_id, branch_id, purchased_by, purchase_date,
                                         total_amount, payment_method, payment_status, amount_paid, amount_due, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $purchaseNumber, $supplierId, $branchId, $purchasedBy, $purchaseDate,
                $totalAmount, $paymentMethod, $paymentStatus, $amountPaid, $amountDue, $notes
            ]);

            $purchaseId = $this->pdo->lastInsertId();

            // Add purchase items and update inventory
            foreach ($items as $item) {
                $totalCost = $item['quantity'] * $item['unit_cost'];

                // Get current cost for comparison
                $currentCost = $this->getCurrentCost($item['product_type'], $item['product_id'], $branchId);

                // Insert purchase item
                $sql = "INSERT INTO purchase_items (purchase_id, product_type, product_id, product_name,
                                                  quantity, unit, unit_cost, total_cost, previous_cost)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $purchaseId, $item['product_type'], $item['product_id'], $item['product_name'],
                    $item['quantity'], $item['unit'], $item['unit_cost'], $totalCost, $currentCost
                ]);

                // Update inventory stock and cost
                $this->updateInventoryFromPurchase($item['product_type'], $item['product_id'], $branchId, $item['quantity'], $item['unit_cost']);
            }

            // Update supplier balance if credit purchase
            if ($paymentMethod === 'CREDIT') {
                $sql = "UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$totalAmount, $supplierId]);
            }

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $purchasedBy,
                'PURCHASE_CREATED',
                'PURCHASES',
                "Purchase {$purchaseNumber} created for " . number_format($totalAmount, 2) . " TZS"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'purchase_number' => $purchaseNumber];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get current cost of product
    private function getCurrentCost($productType, $productId, $branchId) {
        switch ($productType) {
            case 'RAW_MATERIAL':
                $sql = "SELECT cost_price FROM raw_materials WHERE id = ? AND branch_id = ?";
                break;
            case 'THIRD_PARTY_PRODUCT':
                $sql = "SELECT cost_price FROM third_party_products WHERE id = ? AND branch_id = ?";
                break;
            case 'PACKAGING_MATERIAL':
                $sql = "SELECT unit_cost as cost_price FROM packaging_materials WHERE id = ? AND branch_id = ?";
                break;
            default:
                return 0;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$productId, $branchId]);
        $result = $stmt->fetch();
        return $result ? $result['cost_price'] : 0;
    }

    // Update inventory from purchase
    private function updateInventoryFromPurchase($productType, $productId, $branchId, $quantity, $unitCost) {
        switch ($productType) {
            case 'RAW_MATERIAL':
                $sql = "UPDATE raw_materials
                        SET current_stock = current_stock + ?, cost_price = ?
                        WHERE id = ? AND branch_id = ?";
                break;
            case 'THIRD_PARTY_PRODUCT':
                $sql = "UPDATE third_party_products
                        SET current_stock = current_stock + ?, cost_price = ?
                        WHERE id = ? AND branch_id = ?";
                break;
            case 'PACKAGING_MATERIAL':
                $sql = "UPDATE packaging_materials
                        SET current_stock = current_stock + ?, unit_cost = ?
                        WHERE id = ? AND branch_id = ?";
                break;
            default:
                return;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$quantity, $unitCost, $productId, $branchId]);
    }

    // Get purchases
    public function getPurchases($branchId = null, $limit = 50) {
        $sql = "SELECT p.*, s.name as supplier_name, b.name as branch_name, u.full_name as purchased_by_name
                FROM purchases p
                JOIN suppliers s ON p.supplier_id = s.id
                JOIN branches b ON p.branch_id = b.id
                JOIN users u ON p.purchased_by = u.id
                WHERE 1=1";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND p.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT " . intval($limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get purchase statistics
    public function getPurchaseStats($branchId = null) {
        $sql = "SELECT
                    COUNT(*) as total_purchases,
                    SUM(total_amount) as total_value,
                    SUM(CASE WHEN payment_status = 'PENDING' THEN amount_due ELSE 0 END) as total_due,
                    COUNT(CASE WHEN payment_status = 'PENDING' THEN 1 END) as pending_payments
                FROM purchases
                WHERE 1=1";

        $params = [];
        if ($branchId !== null) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Generate payment number
    private function generatePaymentNumber() {
        $date = date('Ymd');
        $sql = "SELECT COUNT(*) as count FROM supplier_payments WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $count = $stmt->fetch()['count'] + 1;
        return 'PAY' . $date . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    // Record supplier payment
    public function recordSupplierPayment($supplierId, $amount, $paymentMethod, $referenceNumber, $paymentDate, $notes, $paidBy, $branchId, $purchaseId = null) {
        try {
            $this->pdo->beginTransaction();

            // Get supplier details
            $sql = "SELECT name, current_balance FROM suppliers WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch();

            if (!$supplier) {
                throw new Exception('Supplier not found');
            }

            if ($amount <= 0) {
                throw new Exception('Payment amount must be greater than 0');
            }

            if ($amount > $supplier['current_balance']) {
                throw new Exception('Payment amount cannot exceed outstanding balance');
            }

            $paymentNumber = $this->generatePaymentNumber();

            // Record payment
            $sql = "INSERT INTO supplier_payments (payment_number, supplier_id, purchase_id, amount, payment_method,
                                                 reference_number, payment_date, notes, paid_by, branch_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $paymentNumber, $supplierId, $purchaseId, $amount, $paymentMethod,
                $referenceNumber, $paymentDate, $notes, $paidBy, $branchId
            ]);

            // Update supplier balance
            $sql = "UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$amount, $supplierId]);

            // Update purchase payment status if specific purchase payment
            if ($purchaseId) {
                $sql = "UPDATE purchases SET amount_paid = amount_paid + ?, amount_due = amount_due - ? WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$amount, $amount, $purchaseId]);

                // Check if purchase is fully paid
                $sql = "SELECT amount_due FROM purchases WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$purchaseId]);
                $purchase = $stmt->fetch();

                if ($purchase && $purchase['amount_due'] <= 0) {
                    $sql = "UPDATE purchases SET payment_status = 'PAID' WHERE id = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$purchaseId]);
                }
            }

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $paidBy,
                'SUPPLIER_PAYMENT',
                'PURCHASES',
                "Payment {$paymentNumber} of " . number_format($amount, 2) . " TZS to {$supplier['name']}"
            ]);

            $this->pdo->commit();
            return ['success' => true, 'payment_number' => $paymentNumber];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get supplier payments
    public function getSupplierPayments($supplierId = null, $limit = 50) {
        $sql = "SELECT sp.*, s.name as supplier_name, s.supplier_code,
                       u.full_name as paid_by_name, b.name as branch_name,
                       p.purchase_number
                FROM supplier_payments sp
                JOIN suppliers s ON sp.supplier_id = s.id
                JOIN users u ON sp.paid_by = u.id
                JOIN branches b ON sp.branch_id = b.id
                LEFT JOIN purchases p ON sp.purchase_id = p.id
                WHERE 1=1";

        $params = [];
        if ($supplierId !== null) {
            $sql .= " AND sp.supplier_id = ?";
            $params[] = $supplierId;
        }

        $sql .= " ORDER BY sp.created_at DESC LIMIT " . intval($limit);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get suppliers with outstanding balances
    public function getSuppliersWithBalances() {
        $sql = "SELECT * FROM suppliers WHERE current_balance > 0 AND status = 'ACTIVE' ORDER BY current_balance DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get detailed purchase information with items
    public function getPurchaseDetails($purchaseId) {
        try {
            // Get purchase information
            $sql = "SELECT p.*, s.name as supplier_name, s.supplier_code, s.contact_person, s.phone,
                           u.full_name as purchased_by_name, b.name as branch_name
                    FROM purchases p
                    JOIN suppliers s ON p.supplier_id = s.id
                    JOIN users u ON p.purchased_by = u.id
                    JOIN branches b ON p.branch_id = b.id
                    WHERE p.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$purchaseId]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$purchase) {
                return ['success' => false, 'error' => 'Purchase not found'];
            }

            // Get purchase items
            $sql = "SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY product_name";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$purchaseId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'purchase' => $purchase,
                'items' => $items
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get detailed supplier information with purchase history
    public function getSupplierDetails($supplierId) {
        try {
            // Get supplier information
            $sql = "SELECT s.*, u.full_name as created_by_name
                    FROM suppliers s
                    JOIN users u ON s.created_by = u.id
                    WHERE s.id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return ['success' => false, 'error' => 'Supplier not found'];
            }

            // Get purchase history
            $sql = "SELECT p.*, b.name as branch_name, u.full_name as purchased_by_name
                    FROM purchases p
                    JOIN branches b ON p.branch_id = b.id
                    JOIN users u ON p.purchased_by = u.id
                    WHERE p.supplier_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT 20";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payment history
            $sql = "SELECT sp.*, u.full_name as paid_by_name, b.name as branch_name,
                           p.purchase_number
                    FROM supplier_payments sp
                    JOIN users u ON sp.paid_by = u.id
                    JOIN branches b ON sp.branch_id = b.id
                    LEFT JOIN purchases p ON sp.purchase_id = p.id
                    WHERE sp.supplier_id = ?
                    ORDER BY sp.created_at DESC
                    LIMIT 20";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'supplier' => $supplier,
                'purchases' => $purchases,
                'payments' => $payments
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Update supplier information
    public function updateSupplier($supplierId, $name, $contactPerson, $phone, $email, $address, $paymentTerms, $creditLimit, $status, $updatedBy) {
        try {
            $this->pdo->beginTransaction();

            // Check if supplier exists
            $sql = "SELECT id FROM suppliers WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            if (!$stmt->fetch()) {
                throw new Exception('Supplier not found');
            }

            // Update supplier
            $sql = "UPDATE suppliers SET
                        name = ?,
                        contact_person = ?,
                        phone = ?,
                        email = ?,
                        address = ?,
                        payment_terms = ?,
                        credit_limit = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $name, $contactPerson, $phone, $email, $address,
                $paymentTerms, $creditLimit, $status, $supplierId
            ]);

            // Log activity
            $sql = "INSERT INTO activity_logs (user_id, action, module, details, created_at)
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $updatedBy,
                'SUPPLIER_UPDATED',
                'PURCHASES',
                "Supplier {$name} updated"
            ]);

            $this->pdo->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Get pending purchases for a specific supplier
    public function getPendingPurchases($supplierId) {
        try {
            // Get purchases with pending payments for this supplier
            $sql = "SELECT id, purchase_number, total_amount, amount_paid, amount_due,
                           purchase_date, payment_status
                    FROM purchases
                    WHERE supplier_id = ?
                    AND payment_status IN ('PENDING', 'PARTIAL')
                    AND amount_due > 0
                    ORDER BY purchase_date ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$supplierId]);
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'purchases' => $purchases
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>