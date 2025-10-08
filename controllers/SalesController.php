<?php
// File: controllers/SalesController.php
// Complete sales and customer management controller
// Handles POS operations, customer management, credit tracking, and receipt generation

require_once __DIR__ . '/../config/database.php';

class SalesController {
    private $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function getActiveBranches() {
        try {
            $sql = "SELECT id, name, location FROM branches WHERE status = 'ACTIVE' ORDER BY name";
            $stmt = $this->conn->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting branches: " . $e->getMessage());
            return [];
        }
    }

    // Get all customers for a branch (or all for admin)
    public function getCustomers($branchId = null) {
        try {
            $sql = "SELECT c.*, b.name as branch_name,
                           u.full_name as created_by_name
                    FROM customers c
                    JOIN branches b ON c.branch_id = b.id
                    LEFT JOIN users u ON c.created_by = u.id";
            $params = [];

            if ($branchId) {
                $sql .= " WHERE c.branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " ORDER BY c.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting customers: " . $e->getMessage());
            return [];
        }
    }

    // Search customers by name or phone
    public function searchCustomers($query, $branchId = null) {
        try {
            $sql = "SELECT c.*, b.name as branch_name
                    FROM customers c
                    JOIN branches b ON c.branch_id = b.id
                    WHERE (c.name LIKE ? OR c.phone LIKE ? OR c.customer_number LIKE ?)
                    AND c.status = 'ACTIVE'";
            $params = ["%$query%", "%$query%", "%$query%"];

            if ($branchId) {
                $sql .= " AND c.branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " ORDER BY c.name LIMIT 10";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error searching customers: " . $e->getMessage());
            return [];
        }
    }

    // Create new customer (quick creation with name and phone)
    public function createCustomer($data) {
        try {
            $this->conn->beginTransaction();

            // Generate customer number
            $customerNumber = $this->generateCustomerNumber($data['branch_id']);

            $sql = "INSERT INTO customers (customer_number, name, phone, email, address, branch_id, credit_limit, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $customerNumber,
                $data['name'],
                $data['phone'],
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['branch_id'],
                $data['credit_limit'] ?? 0.00,
                $data['created_by']
            ]);

            $customerId = $this->conn->lastInsertId();

            // Log activity (basic logging)
            $this->logActivity($data['created_by'], 'CUSTOMER_CREATED', "Customer $customerNumber created");

            $this->conn->commit();
            return ['success' => true, 'customer_id' => $customerId, 'customer_number' => $customerNumber];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating customer: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating customer'];
        }
    }

    // Get all available products for sale in a branch
    public function getAvailableProducts($branchId) {
        try {
            $products = [];

            // Sealed bags (finished products)
            $sql = "SELECT pb.id as bag_id, pb.serial_number, pb.production_date, pb.expiry_date,
                           p.id as product_id, p.name, p.package_size, p.unit_price,
                           'FINISHED_PRODUCT' as product_type
                    FROM product_bags pb
                    JOIN products p ON pb.product_id = p.id
                    WHERE pb.branch_id = ? AND pb.status = 'Sealed'
                    ORDER BY p.name, pb.production_date";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $products['sealed_bags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Opened bags
            $sql = "SELECT ob.id as opened_bag_id, ob.serial_number, ob.current_weight_kg,
                           ob.selling_price_per_kg, pb.production_date, pb.expiry_date,
                           p.id as product_id, p.name, p.package_size,
                           'OPENED_BAG' as product_type
                    FROM opened_bags ob
                    JOIN product_bags pb ON ob.bag_id = pb.id
                    JOIN products p ON pb.product_id = p.id
                    WHERE ob.branch_id = ? AND ob.current_weight_kg > 0
                    ORDER BY p.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $products['opened_bags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Raw materials
            $sql = "SELECT id as product_id, name, unit_of_measure, current_stock, selling_price,
                           'RAW_MATERIAL' as product_type
                    FROM raw_materials
                    WHERE branch_id = ? AND status = 'Active' AND current_stock > 0 AND selling_price > 0
                    ORDER BY name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $products['raw_materials'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Third party products
            $sql = "SELECT id as product_id, name, brand, unit_of_measure, current_stock, selling_price,
                           'THIRD_PARTY_PRODUCT' as product_type
                    FROM third_party_products
                    WHERE branch_id = ? AND status = 'Active' AND current_stock > 0
                    ORDER BY name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $products['third_party_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $products;

        } catch (Exception $e) {
            error_log("Error getting available products: " . $e->getMessage());
            return [];
        }
    }

    // Search products by name for quick add
    public function searchProducts($query, $branchId) {
        try {
            $results = [];

            // Search sealed bags
            $sql = "SELECT pb.id as bag_id, pb.serial_number, p.id as product_id, p.name, p.package_size, p.unit_price,
                           'FINISHED_PRODUCT' as product_type, 1 as available_quantity, 'Bag' as unit
                    FROM product_bags pb
                    JOIN products p ON pb.product_id = p.id
                    WHERE pb.branch_id = ? AND pb.status = 'Sealed' AND p.name LIKE ?
                    ORDER BY p.name LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId, "%$query%"]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Search opened bags
            $sql = "SELECT ob.id as opened_bag_id, ob.serial_number, p.id as product_id, p.name, p.package_size,
                           ob.selling_price_per_kg as unit_price, 'OPENED_BAG' as product_type,
                           ob.current_weight_kg as available_quantity, 'KG' as unit
                    FROM opened_bags ob
                    JOIN product_bags pb ON ob.bag_id = pb.id
                    JOIN products p ON pb.product_id = p.id
                    WHERE ob.branch_id = ? AND ob.current_weight_kg > 0 AND p.name LIKE ?
                    ORDER BY p.name LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId, "%$query%"]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Search raw materials
            $sql = "SELECT id as product_id, name, unit_of_measure as unit, current_stock as available_quantity,
                           selling_price as unit_price, 'RAW_MATERIAL' as product_type
                    FROM raw_materials
                    WHERE branch_id = ? AND status = 'Active' AND current_stock > 0
                    AND selling_price > 0 AND name LIKE ?
                    ORDER BY name LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId, "%$query%"]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

            // Search third party products
            $sql = "SELECT id as product_id, CONCAT(name, ' (', brand, ')') as name, unit_of_measure as unit,
                           current_stock as available_quantity, selling_price as unit_price,
                           'THIRD_PARTY_PRODUCT' as product_type
                    FROM third_party_products
                    WHERE branch_id = ? AND status = 'Active' AND current_stock > 0 AND name LIKE ?
                    ORDER BY name LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId, "%$query%"]);
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));

            return $results;

        } catch (Exception $e) {
            error_log("Error searching products: " . $e->getMessage());
            return [];
        }
    }

    // Process sale transaction
    public function processSale($data) {
        try {
            $this->conn->beginTransaction();

            $saleDateTime = null;
            if (!empty($data['sale_datetime'])) {
                try {
                    $saleDateTime = new DateTime($data['sale_datetime']);
                } catch (Exception $e) {
                    throw new Exception('Invalid sale date provided');
                }
            } else {
                $saleDateTime = new DateTime();
            }

            // Generate sale and receipt numbers
            $saleNumber = $this->generateSaleNumber($data['branch_id'], $saleDateTime);
            $receiptNumber = $this->generateReceiptNumber($data['branch_id'], $saleDateTime);

            // Calculate totals (use provided totals from frontend)
            $totalAmount = $data['total_amount'] ?? 0;
            $subtotal = $data['subtotal'] ?? 0;
            $vatAmount = $data['vat_amount'] ?? 0;
            $discountAmount = $data['discount_amount'] ?? 0;
            $finalAmount = $totalAmount;

            // Create sale record
            $sql = "INSERT INTO sales (sale_number, customer_id, branch_id, total_amount, vat_amount,
                                      discount_amount, final_amount, payment_method, payment_reference,
                                      sale_type, receipt_number, notes, sold_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $saleNumber,
                $data['customer_id'],
                $data['branch_id'],
                $totalAmount,
                $vatAmount,
                $discountAmount,
                $finalAmount,
                $data['payment_method'],
                $data['payment_reference'] ?? null,
                $data['sale_type'],
                $receiptNumber,
                $data['notes'] ?? null,
                $data['sold_by']
            ]);

            $saleId = $this->conn->lastInsertId();

            // Process each item and update inventory
            foreach ($data['items'] as $item) {
                // Add sale item
                $sql = "INSERT INTO sale_items (sale_id, product_type, product_id, product_name, serial_number,
                                               quantity, unit, unit_price, total_price)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    $saleId,
                    $item['product_type'],
                    $item['product_id'],
                    $item['product_name'],
                    $item['serial_number'] ?? null,
                    $item['quantity'],
                    $item['unit'],
                    $item['unit_price'],
                    $item['total_price']
                ]);

                // Update inventory based on product type
                $this->updateInventoryAfterSale($item, $data['branch_id']);
            }

            // Handle credit sale
            if ($data['sale_type'] === 'CREDIT') {
                $this->updateCustomerBalance($data['customer_id'], $finalAmount, 'SALE', $saleId, $data['sold_by']);
            }

            // Generate QR code for receipt
            $qrCode = $this->generateReceiptQR($saleId, $receiptNumber, $finalAmount);

            // Update sale with QR code
            $sql = "UPDATE sales SET qr_code = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$qrCode, $saleId]);

            if (!empty($data['sale_datetime'])) {
                $customDate = $saleDateTime->format('Y-m-d H:i:s');
                try {
                    $timestampSql = "UPDATE sales SET created_at = ? WHERE id = ?";
                    $timestampStmt = $this->conn->prepare($timestampSql);
                    $timestampStmt->execute([$customDate, $saleId]);
                } catch (Exception $e) {
                    error_log("Failed to set custom sale timestamp: " . $e->getMessage());
                }
            }

            // Log activity (basic logging)
            $this->logActivity($data['sold_by'], 'SALE_COMPLETED', "Sale $saleNumber completed - TZS " . number_format($finalAmount, 2));

            $this->conn->commit();

            return [
                'success' => true,
                'sale_id' => $saleId,
                'sale_number' => $saleNumber,
                'receipt_number' => $receiptNumber,
                'final_amount' => $finalAmount,
                'qr_code' => $qrCode
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error processing sale: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error processing sale: ' . $e->getMessage()];
        }
    }

    // Update inventory after sale
    private function updateInventoryAfterSale($item, $branchId) {
        error_log("Updating inventory for item: " . print_r($item, true));

        switch ($item['product_type']) {
            case 'FINISHED_PRODUCT':
                // For finished products, we need to find the bag by serial number
                if (!empty($item['serial_number'])) {
                    $sql = "UPDATE product_bags SET status = 'Sold' WHERE serial_number = ? AND branch_id = ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([$item['serial_number'], $branchId]);
                    error_log("Updated bag status for serial: " . $item['serial_number']);
                } else {
                    error_log("No serial number provided for finished product");
                }
                break;

            case 'OPENED_BAG':
                // For opened bags, reduce the weight by product_id + branch (opened bags are unique per product/branch)
                if (!empty($item['product_id'])) {
                    $sql = "UPDATE opened_bags ob
                            JOIN product_bags pb ON ob.bag_id = pb.id
                            SET ob.current_weight_kg = ob.current_weight_kg - ?
                            WHERE pb.product_id = ? AND pb.branch_id = ? AND ob.current_weight_kg >= ?";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([$item['quantity'], $item['product_id'], $branchId, $item['quantity']]);
                    error_log("Reduced opened bag weight for product: " . $item['product_id']);
                }
                break;

            case 'RAW_MATERIAL':
                // Reduce raw material stock
                $sql = "UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND branch_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$item['quantity'], $item['product_id'], $branchId]);
                error_log("Reduced raw material stock for product: " . $item['product_id']);
                break;

            case 'THIRD_PARTY_PRODUCT':
                // Reduce third party product stock
                $sql = "UPDATE third_party_products SET current_stock = current_stock - ? WHERE id = ? AND branch_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$item['quantity'], $item['product_id'], $branchId]);
                error_log("Reduced third party product stock for product: " . $item['product_id']);
                break;

            default:
                error_log("Unknown product type: " . $item['product_type']);
                break;
        }
    }

    // Update customer balance for credit sales
    private function updateCustomerBalance($customerId, $amount, $type, $saleId, $userId) {
        // Get current balance
        $sql = "SELECT current_balance FROM customers WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$customerId]);
        $currentBalance = $stmt->fetchColumn();

        $balanceBefore = $currentBalance;
        $balanceAfter = $currentBalance + $amount;

        // Update customer balance
        $sql = "UPDATE customers SET current_balance = ? WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$balanceAfter, $customerId]);

        // Record in credit history
        $sql = "INSERT INTO customer_credit_history (customer_id, transaction_type, sale_id, amount,
                                                     balance_before, balance_after, description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $customerId,
            $type,
            $saleId,
            $amount,
            $balanceBefore,
            $balanceAfter,
            "Credit sale transaction",
            $userId
        ]);
    }

    // Generate unique customer number
    private function generateCustomerNumber($branchId) {
        $branches = [1 => 'HQ', 2 => 'AR', 3 => 'MW', 4 => 'DD', 5 => 'MB'];
        $branchCode = $branches[$branchId] ?? 'BR';
        $prefix = "CUST-$branchCode-";

        $sql = "SELECT COUNT(*) as count FROM customers WHERE customer_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['count'];
        return $prefix . sprintf('%04d', $count + 1);
    }

    // Generate unique sale number
    private function generateSaleNumber($branchId, $saleDate = null) {
        $date = $saleDate instanceof DateTime ? $saleDate : new DateTime();
        $prefix = 'SALE' . $date->format('Ymd') . sprintf('%02d', $branchId);
        $sql = "SELECT COUNT(*) as count FROM sales WHERE sale_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['count'];
        return $prefix . sprintf('%04d', $count + 1);
    }

    // Generate unique receipt number
    private function generateReceiptNumber($branchId, $saleDate = null) {
        $date = $saleDate instanceof DateTime ? $saleDate : new DateTime();
        $prefix = 'RCP' . $date->format('Ymd') . sprintf('%02d', $branchId);
        $sql = "SELECT COUNT(*) as count FROM sales WHERE receipt_number LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$prefix . '%']);
        $count = $stmt->fetch()['count'];
        return $prefix . sprintf('%04d', $count + 1);
    }

    // Generate receipt QR code
    private function generateReceiptQR($saleId, $receiptNumber, $amount) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return json_encode([
            'sale_id' => $saleId,
            'receipt_number' => $receiptNumber,
            'amount' => $amount,
            'verify_url' => BASE_URL . '/verify_receipt.php?receipt=' . $receiptNumber
        ]);
    }

    // Get sale details for receipt generation
    public function getSaleForReceipt($saleId) {
        try {
            // Get main sale details
            $sql = "SELECT s.*, c.name as customer_name, c.phone as customer_phone,
                           b.name as branch_name, u.full_name as sold_by_name,
                           (s.total_amount - s.vat_amount + s.discount_amount) as subtotal
                    FROM sales s
                    JOIN customers c ON s.customer_id = c.id
                    JOIN branches b ON s.branch_id = b.id
                    JOIN users u ON s.sold_by = u.id
                    WHERE s.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                return null;
            }

            // Get sale items
            $sql = "SELECT si.*,
                           CASE
                               WHEN si.product_type = 'FINISHED_PRODUCT' THEN
                                   JSON_ARRAY(pb.serial_number)
                               WHEN si.product_type = 'OPENED_BAG' THEN
                                   JSON_ARRAY(ob.serial_number)
                               ELSE JSON_ARRAY()
                           END as serial_numbers,
                           CASE
                               WHEN si.product_type = 'OPENED_BAG' THEN
                                   (SELECT pb2.serial_number FROM product_bags pb2 WHERE pb2.id = ob.bag_id)
                               ELSE NULL
                           END as original_serial
                    FROM sale_items si
                    LEFT JOIN product_bags pb ON si.product_type = 'FINISHED_PRODUCT' AND si.product_id = pb.id
                    LEFT JOIN opened_bags ob ON si.product_type = 'OPENED_BAG' AND si.product_id = ob.id
                    WHERE si.sale_id = ?
                    ORDER BY si.id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$saleId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process serial numbers
            foreach ($items as &$item) {
                if ($item['serial_numbers']) {
                    $item['serial_numbers'] = json_decode($item['serial_numbers'], true);
                } else {
                    $item['serial_numbers'] = [];
                }
            }

            $sale['items'] = $items;
            $sale['vat_percentage'] = $sale['vat_amount'] > 0 ? 18 : 0;

            return $sale;

        } catch (Exception $e) {
            error_log("Error getting sale for receipt: " . $e->getMessage());
            return null;
        }
    }

    // Save receipt to file
    public function saveReceiptToFile($saleId, $receiptHtml) {
        try {
            $receiptDir = __DIR__ . '/../receipts';
            if (!is_dir($receiptDir)) {
                mkdir($receiptDir, 0755, true);
            }

            // Get receipt number for filename
            $sql = "SELECT receipt_number FROM sales WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$saleId]);
            $receiptNumber = $stmt->fetchColumn();

            $filename = "receipt_{$receiptNumber}_" . date('Y-m-d_H-i-s') . ".html";
            $filepath = $receiptDir . '/' . $filename;

            file_put_contents($filepath, $receiptHtml);

            return $filename;

        } catch (Exception $e) {
            error_log("Error saving receipt: " . $e->getMessage());
            return false;
        }
    }

    // Get customers with filtering
    public function getCustomersWithFilters($branchId, $search = '', $status = '', $credit = '') {
        try {
            $sql = "SELECT c.*, b.name as branch_name
                    FROM customers c
                    JOIN branches b ON c.branch_id = b.id";
            $params = [];

            // Add branch filter only if branchId is provided (for branch operators)
            if ($branchId !== null) {
                $sql .= " WHERE c.branch_id = ?";
                $params[] = $branchId;
            } else {
                // For administrators, show all customers
                $sql .= " WHERE 1=1";
            }

            // Add search filter
            if (!empty($search)) {
                $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.customer_number LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }

            // Add status filter
            if (!empty($status)) {
                $sql .= " AND c.status = ?";
                $params[] = $status;
            }

            // Add credit filter
            if ($credit === 'has_credit') {
                $sql .= " AND c.credit_limit > 0";
            } elseif ($credit === 'has_balance') {
                $sql .= " AND c.current_balance > 0";
            }

            $sql .= " ORDER BY c.name";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting customers: " . $e->getMessage());
            return [];
        }
    }

    // Record customer payment
    public function recordCustomerPayment($data) {
        try {
            $this->conn->beginTransaction();

            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber($data['branch_id']);

            // Insert payment record
            $sql = "INSERT INTO customer_payments (payment_number, customer_id, amount, payment_method,
                           reference_number, notes, received_by, branch_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $paymentNumber,
                $data['customer_id'],
                $data['amount'],
                $data['payment_method'],
                $data['reference_number'],
                $data['notes'],
                $data['received_by'],
                $data['branch_id']
            ]);

            $paymentId = $this->conn->lastInsertId();

            // Update customer balance
            $this->updateCustomerBalance($data['customer_id'], -$data['amount'], 'PAYMENT', $paymentId, $data['received_by']);

            // Log activity (basic logging)
            $this->logActivity($data['received_by'], 'PAYMENT_RECORDED',
                "Credit payment recorded - Payment #$paymentNumber - TZS " . number_format($data['amount'], 2));

            $this->conn->commit();

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'payment_number' => $paymentNumber
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error recording payment: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error recording payment: ' . $e->getMessage()];
        }
    }

    // Generate payment number
    private function generatePaymentNumber($branchId) {
        $date = date('Ymd');
        $branchCode = str_pad($branchId, 2, '0', STR_PAD_LEFT);

        // Get next sequence number for today
        $sql = "SELECT COUNT(*) + 1 as next_seq FROM customer_payments
                WHERE branch_id = ? AND DATE(created_at) = CURDATE()";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$branchId]);
        $sequence = str_pad($stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);

        return "PAY{$date}{$branchCode}{$sequence}";
    }

    // Get customer by ID
    public function getCustomerById($customerId) {
        try {
            $sql = "SELECT c.*, b.name as branch_name
                    FROM customers c
                    JOIN branches b ON c.branch_id = b.id
                    WHERE c.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customerId]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting customer: " . $e->getMessage());
            return null;
        }
    }


    // Update customer
    public function updateCustomer($data) {
        try {
            $sql = "UPDATE customers SET name = ?, phone = ?, email = ?, address = ?,
                           credit_limit = ?, status = ?, updated_at = NOW()
                    WHERE id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['credit_limit'],
                $data['status'],
                $data['id']
            ]);

            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error updating customer: " . $e->getMessage());
            throw $e;
        }
    }


    // Get payment details for receipt generation
    public function getPaymentForReceipt($paymentId) {
        try {
            $sql = "SELECT p.*, c.name as customer_name, c.phone as customer_phone,
                           b.name as branch_name, u.full_name as received_by_name,
                           ch.balance_before, ch.balance_after
                    FROM customer_payments p
                    JOIN customers c ON p.customer_id = c.id
                    JOIN branches b ON p.branch_id = b.id
                    JOIN users u ON p.received_by = u.id
                    LEFT JOIN customer_credit_history ch ON p.id = ch.payment_id AND ch.transaction_type = 'PAYMENT'
                    WHERE p.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$paymentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting payment for receipt: " . $e->getMessage());
            return null;
        }
    }

    // Save payment receipt to file
    public function savePaymentReceiptToFile($paymentId, $receiptHtml) {
        try {
            // Get payment details
            $payment = $this->getPaymentForReceipt($paymentId);
            if (!$payment) {
                throw new Exception("Payment not found");
            }

            // Create receipts directory if it doesn't exist
            $receiptsDir = __DIR__ . '/../receipts/payments';
            if (!is_dir($receiptsDir)) {
                mkdir($receiptsDir, 0755, true);
            }

            // Save receipt file
            $filename = 'payment_' . $payment['payment_number'] . '_' . date('Y-m-d_H-i-s') . '.html';
            $filePath = $receiptsDir . '/' . $filename;

            file_put_contents($filePath, $receiptHtml);

            return $filename;

        } catch (Exception $e) {
            error_log("Error saving payment receipt: " . $e->getMessage());
            return false;
        }
    }

    // Get customer transaction history
    public function getCustomerTransactionHistory($customerId) {
        try {
            $sql = "SELECT 'SALE' as transaction_type, s.id as transaction_id, s.sale_number as reference,
                           s.total_amount as amount, s.created_at, s.payment_method, s.sale_type,
                           CONCAT('Sale - ', s.sale_number) as description,
                           u.full_name as processed_by, b.name as branch_name
                    FROM sales s
                    JOIN users u ON s.sold_by = u.id
                    JOIN branches b ON s.branch_id = b.id
                    WHERE s.customer_id = ?

                    UNION ALL

                    SELECT 'PAYMENT' as transaction_type, p.id as transaction_id, p.payment_number as reference,
                           -p.amount as amount, p.created_at, p.payment_method, 'PAYMENT' as sale_type,
                           CONCAT('Payment - ', p.payment_number) as description,
                           u.full_name as processed_by, b.name as branch_name
                    FROM customer_payments p
                    JOIN users u ON p.received_by = u.id
                    JOIN branches b ON p.branch_id = b.id
                    WHERE p.customer_id = ?

                    ORDER BY created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customerId, $customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting customer history: " . $e->getMessage());
            return [];
        }
    }

    // Get customer sales history
    public function getCustomerSalesHistory($customerId) {
        try {
            $sql = "SELECT s.*, u.full_name as sold_by_name, b.name as branch_name,
                           COUNT(si.id) as item_count
                    FROM sales s
                    JOIN users u ON s.sold_by = u.id
                    JOIN branches b ON s.branch_id = b.id
                    LEFT JOIN sale_items si ON s.id = si.sale_id
                    WHERE s.customer_id = ?
                    GROUP BY s.id
                    ORDER BY s.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting customer sales history: " . $e->getMessage());
            return [];
        }
    }

    // Get customer payment history
    public function getCustomerPaymentHistory($customerId) {
        try {
            $sql = "SELECT p.*, u.full_name as received_by_name, b.name as branch_name
                    FROM customer_payments p
                    JOIN users u ON p.received_by = u.id
                    JOIN branches b ON p.branch_id = b.id
                    WHERE p.customer_id = ?
                    ORDER BY p.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting customer payment history: " . $e->getMessage());
            return [];
        }
    }

    // Helper method for notification system
    private function getCustomerDetails($customerId) {
        try {
            $sql = "SELECT * FROM customers WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$customerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting customer: " . $e->getMessage());
            return ['name' => 'Unknown Customer'];
        }
    }

    private function getBranchById($branchId) {
        try {
            $sql = "SELECT * FROM branches WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting branch: " . $e->getMessage());
            return ['name' => 'Unknown Branch'];
        }
    }

    // Get branch dashboard statistics
    public function getBranchDashboardStats($branchId) {
        try {
            $stats = [];

            // Today's sales statistics
            $sql = "SELECT
                        COALESCE(SUM(final_amount), 0) as daily_revenue,
                        COALESCE(SUM((SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.id)), 0) as daily_quantity,
                        COUNT(*) as daily_sales_count
                    FROM sales s
                    WHERE s.branch_id = ? AND DATE(s.created_at) = CURDATE()";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // This month's sales statistics
            $sql = "SELECT
                        COALESCE(SUM(final_amount), 0) as monthly_revenue,
                        COALESCE(SUM((SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.id)), 0) as monthly_quantity,
                        COUNT(*) as monthly_sales_count,
                        COUNT(DISTINCT customer_id) as unique_customers
                    FROM sales s
                    WHERE s.branch_id = ? AND MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Total customers for this branch
            $sql = "SELECT COUNT(*) as total_customers FROM customers WHERE branch_id = ? AND status = 'ACTIVE'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            $customerStats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'daily_sales' => round($todayStats['daily_quantity'], 2),
                'daily_revenue' => $todayStats['daily_revenue'],
                'daily_sales_count' => $todayStats['daily_sales_count'],
                'monthly_sales' => round($monthStats['monthly_quantity'], 2),
                'monthly_revenue' => $monthStats['monthly_revenue'],
                'monthly_sales_count' => $monthStats['monthly_sales_count'],
                'customers_served_today' => $monthStats['unique_customers'],
                'total_customers' => $customerStats['total_customers']
            ];

        } catch (Exception $e) {
            error_log("Error getting branch dashboard stats: " . $e->getMessage());
            return [
                'daily_sales' => 0, 'daily_revenue' => 0, 'daily_sales_count' => 0,
                'monthly_sales' => 0, 'monthly_revenue' => 0, 'monthly_sales_count' => 0,
                'customers_served_today' => 0, 'total_customers' => 0
            ];
        }
    }

    // Get recent sales for branch dashboard
    public function getRecentSales($branchId, $limit = 5) {
        try {
            $sql = "SELECT s.*, c.name as customer_name, c.customer_number,
                           TIME(s.created_at) as sale_time,
                           (SELECT SUM(quantity) FROM sale_items WHERE sale_id = s.id) as total_quantity
                    FROM sales s
                    LEFT JOIN customers c ON s.customer_id = c.id
                    WHERE s.branch_id = ?
                    ORDER BY s.created_at DESC
                    LIMIT ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent sales: " . $e->getMessage());
            return [];
        }
    }

    // Get inventory levels for branch dashboard
    public function getBranchInventoryLevels($branchId) {
        try {
            $sql = "SELECT p.name as product_name,
                           COUNT(pb.id) as total_bags,
                           SUM(CASE WHEN pb.status = 'Sealed' THEN 1 ELSE 0 END) as sealed_bags,
                           SUM(CASE WHEN pb.status = 'Opened' THEN 1 ELSE 0 END) as opened_bags,
                           SUM(CASE WHEN pb.status = 'Opened' THEN pb.current_weight ELSE p.package_weight END) as current_stock,
                           SUM(p.package_weight) as max_capacity
                    FROM products p
                    LEFT JOIN product_bags pb ON p.id = pb.product_id AND pb.branch_id = ?
                    WHERE p.type = 'FINISHED_PRODUCT'
                    GROUP BY p.id, p.name
                    HAVING total_bags > 0
                    ORDER BY p.name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$branchId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting inventory levels: " . $e->getMessage());
            return [];
        }
    }

    // Log activity (basic logging)
    private function logActivity($userId, $action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>
