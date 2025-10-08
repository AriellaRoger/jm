<?php
// File: controllers/OrderController.php
// Complete customer order management controller
// Handles order creation, approval workflow, and automatic sales conversion

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationManager.php';

class OrderController {
    private $conn;
    private $notificationManager;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
        $this->notificationManager = new NotificationManager();
    }

    // Create new customer order
    public function createOrder($data) {
        try {
            $this->conn->beginTransaction();

            // Generate order number
            $orderNumber = $this->generateOrderNumber($data['requesting_branch_id']);

            // Create main order record
            $sql = "INSERT INTO customer_orders
                   (order_number, customer_id, requesting_branch_id, fulfilling_branch_id,
                    requested_by, order_notes, payment_method, payment_reference,
                    payment_notes, custom_total_amount, status, created_at)
                   VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 'PENDING', NOW())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $orderNumber,
                $data['customer_id'],
                $data['requesting_branch_id'],
                $data['requested_by'],
                $data['order_notes'],
                $data['payment_method'] ?? 'CASH',
                $data['payment_reference'] ?? null,
                $data['payment_notes'] ?? null,
                $data['custom_total_amount'] ?? 0
            ]);

            $orderId = $this->conn->lastInsertId();

            // Add order items
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addOrderItem($orderId, $item);
                }
            }

            // Add order bags (specific product bags requested)
            if (!empty($data['bags'])) {
                foreach ($data['bags'] as $bag) {
                    $this->addOrderBag($orderId, $bag);
                }
            }

            // Log activity
            $this->logActivity(
                $data['requested_by'],
                'ORDER_CREATED',
                "Customer order $orderNumber created"
            );

            // Get order details for notification
            $customerSql = "SELECT name FROM customers WHERE id = ?";
            $customerStmt = $this->conn->prepare($customerSql);
            $customerStmt->execute([$data['customer_id']]);
            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
            $customerName = $customer ? $customer['name'] : 'Unknown Customer';

            $branchSql = "SELECT name FROM branches WHERE id = ?";
            $branchStmt = $this->conn->prepare($branchSql);
            $branchStmt->execute([$data['requesting_branch_id']]);
            $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);
            $branchName = $branch ? $branch['name'] : 'Unknown Branch';

            // Notify administrators and supervisors about new order
            $itemCount = count($data['items'] ?? []) + count($data['bags'] ?? []);
            $this->notificationManager->createForRole(
                ['Administrator', 'Supervisor'],
                'New Customer Order Received',
                "Order {$orderNumber} from {$customerName} at {$branchName} with {$itemCount} items requires approval",
                'APPROVAL_REQUIRED',
                'ORDERS',
                [
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'action_url' => '/orders/index.php?view=' . $orderId,
                    'is_urgent' => true
                ]
            );

            $this->conn->commit();
            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creating order: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Add individual item to order
    private function addOrderItem($orderId, $item) {
        $sql = "INSERT INTO order_items
               (order_id, product_type, product_id, quantity, unit, notes,
                custom_unit_price, custom_total_price)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $orderId,
            $item['product_type'],
            $item['product_id'],
            $item['quantity'],
            $item['unit'],
            $item['notes'] ?? null,
            $item['custom_unit_price'] ?? 0,
            $item['custom_total_price'] ?? 0
        ]);
    }

    // Add specific product bag to order
    private function addOrderBag($orderId, $bag) {
        $sql = "INSERT INTO order_bags
               (order_id, product_bag_id, notes)
               VALUES (?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $orderId,
            $bag['product_bag_id'],
            $bag['notes'] ?? null
        ]);
    }

    // Approve or reject order (HQ only)
    public function approveOrder($orderId, $approvedBy, $approvalNotes = '', $rejectionReason = '', $approve = true) {
        try {
            $this->conn->beginTransaction();

            $newStatus = $approve ? 'APPROVED' : 'REJECTED';

            // Update order status
            $sql = "UPDATE customer_orders
                   SET status = ?, approved_by = ?, approval_date = NOW(),
                       approval_notes = ?, rejection_reason = ?
                   WHERE id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $newStatus,
                $approvedBy,
                $approvalNotes,
                $rejectionReason,
                $orderId
            ]);

            // Get order details for logging
            $order = $this->getOrderById($orderId);
            $action = $approve ? 'ORDER_APPROVED' : 'ORDER_REJECTED';

            $this->logActivity(
                $approvedBy,
                $action,
                "Order {$order['order_number']} " . ($approve ? 'approved' : 'rejected')
            );

            // Notify the order requester
            $statusText = $approve ? 'approved' : 'rejected';
            $notificationType = $approve ? 'SUCCESS' : 'ERROR';
            $this->notificationManager->create(
                $order['requested_by'],
                "Order {$statusText}",
                "Your order {$order['order_number']} has been {$statusText}",
                $notificationType,
                'ORDERS',
                [
                    'entity_type' => 'order',
                    'entity_id' => $orderId,
                    'action_url' => '/orders/index.php?view=' . $orderId
                ]
            );

            $this->conn->commit();
            return ['success' => true];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error approving order: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Update order status (for shipping and delivery)
    public function updateOrderStatus($orderId, $status, $updatedBy, $notes = '') {
        try {
            $this->conn->beginTransaction();

            // If marking as DELIVERED, convert to sales
            if ($status === 'DELIVERED') {
                $result = $this->convertOrderToSales($orderId, $updatedBy);
                if (!$result['success']) {
                    throw new Exception("Failed to convert order to sales: " . $result['message']);
                }
            }

            // Update order status
            $sql = "UPDATE customer_orders
                   SET status = ?, updated_by = ?, updated_at = NOW()";

            $params = [$status, $updatedBy];

            // Add specific date fields based on status
            if ($status === 'IN_TRANSIT') {
                $sql .= ", shipped_date = NOW()";
            } elseif ($status === 'DELIVERED') {
                $sql .= ", delivered_date = NOW(), sale_id = ?";
                $params[] = $result['sale_id'] ?? null;
            }

            $sql .= " WHERE id = ?";
            $params[] = $orderId;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            // Log activity
            $order = $this->getOrderById($orderId);
            $this->logActivity(
                $updatedBy,
                'ORDER_STATUS_UPDATED',
                "Order {$order['order_number']} status changed to $status"
            );

            $this->conn->commit();
            return ['success' => true, 'sale_id' => $result['sale_id'] ?? null];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error updating order status: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Convert approved order to sales record when delivered
    private function convertOrderToSales($orderId, $processedBy) {
        try {
            // Get order details
            $order = $this->getOrderWithItems($orderId);

            if (!$order) {
                throw new Exception("Order not found");
            }

            if ($order['status'] !== 'IN_TRANSIT') {
                throw new Exception("Only in-transit orders can be delivered");
            }

            // Generate sale number
            $saleNumber = $this->generateSaleNumber($order['requesting_branch_id']);

            // Calculate total amount and quantity
            $totalAmount = 0;
            $totalQuantity = 0;

            foreach ($order['items'] as $item) {
                $productPrice = $this->getProductPrice($item['product_type'], $item['product_id']);
                $itemTotal = $item['quantity'] * $productPrice;
                $totalAmount += $itemTotal;
                $totalQuantity += $item['quantity'];
            }

            // Create sales record
            $sql = "INSERT INTO sales
                   (sale_number, customer_id, branch_id, sold_by, total_amount,
                    final_amount, status, notes, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, 'COMPLETED', ?, NOW())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $saleNumber,
                $order['customer_id'],
                $order['requesting_branch_id'],
                $processedBy,
                $totalAmount,
                $totalAmount, // final_amount same as total_amount
                "Converted from order {$order['order_number']}"
            ]);

            $saleId = $this->conn->lastInsertId();

            // Add sale items
            foreach ($order['items'] as $item) {
                $productPrice = $this->getProductPrice($item['product_type'], $item['product_id']);
                $itemTotal = $item['quantity'] * $productPrice;

                $sql = "INSERT INTO sale_items
                       (sale_id, product_type, product_id, quantity, unit_price, total_price, unit)
                       VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    $saleId,
                    $item['product_type'],
                    $item['product_id'],
                    $item['quantity'],
                    $productPrice,
                    $itemTotal,
                    $item['unit']
                ]);
            }

            // Update inventory movements
            foreach ($order['items'] as $item) {
                $this->recordInventoryMovement(
                    $order['requesting_branch_id'],
                    $item['product_type'],
                    $item['product_id'],
                    'SALE',
                    $item['quantity'],
                    $item['unit'],
                    $saleId
                );
            }

            return ['success' => true, 'sale_id' => $saleId, 'sale_number' => $saleNumber];

        } catch (Exception $e) {
            error_log("Error converting order to sales: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get orders with filtering
    public function getOrders($branchId = null, $status = '', $limit = 100) {
        try {
            $sql = "SELECT co.*,
                           c.name as customer_name, c.customer_number, c.phone as customer_phone, c.address as customer_address,
                           rb.name as requesting_branch_name,
                           fb.name as fulfilling_branch_name,
                           ru.full_name as requested_by_name,
                           au.full_name as approved_by_name,
                           COUNT(oi.id) + COUNT(ob.id) as total_items
                    FROM customer_orders co
                    LEFT JOIN customers c ON co.customer_id = c.id
                    LEFT JOIN branches rb ON co.requesting_branch_id = rb.id
                    LEFT JOIN branches fb ON co.fulfilling_branch_id = fb.id
                    LEFT JOIN users ru ON co.requested_by = ru.id
                    LEFT JOIN users au ON co.approved_by = au.id
                    LEFT JOIN order_items oi ON co.id = oi.order_id
                    LEFT JOIN order_bags ob ON co.id = ob.order_id
                    WHERE 1=1";

            $params = [];

            if ($branchId) {
                $sql .= " AND co.requesting_branch_id = ?";
                $params[] = $branchId;
            }

            if ($status) {
                $sql .= " AND co.status = ?";
                $params[] = $status;
            }

            $sql .= " GROUP BY co.id ORDER BY co.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error getting orders: " . $e->getMessage());
            return [];
        }
    }

    // Get order statistics
    public function getOrderStatistics($branchId = null) {
        try {
            $sql = "SELECT
                       status,
                       COUNT(*) as count,
                       COALESCE(SUM(total_estimated_value), 0) as total_value
                    FROM customer_orders co";

            $params = [];
            if ($branchId) {
                $sql .= " WHERE requesting_branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " GROUP BY status";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format results
            $stats = [
                'PENDING' => ['count' => 0, 'total_value' => 0],
                'APPROVED' => ['count' => 0, 'total_value' => 0],
                'IN_TRANSIT' => ['count' => 0, 'total_value' => 0],
                'DELIVERED' => ['count' => 0, 'total_value' => 0],
                'REJECTED' => ['count' => 0, 'total_value' => 0]
            ];

            foreach ($results as $result) {
                $stats[$result['status']] = [
                    'count' => (int)$result['count'],
                    'total_value' => (float)$result['total_value']
                ];
            }

            return $stats;

        } catch (Exception $e) {
            error_log("Error getting order statistics: " . $e->getMessage());
            return [];
        }
    }

    // Get single order with full details
    public function getOrderWithItems($orderId) {
        try {
            // Get order details
            $sql = "SELECT co.*,
                           c.name as customer_name, c.customer_number, c.phone as customer_phone,
                           rb.name as requesting_branch_name,
                           fb.name as fulfilling_branch_name,
                           ru.full_name as requested_by_name,
                           au.full_name as approved_by_name
                    FROM customer_orders co
                    LEFT JOIN customers c ON co.customer_id = c.id
                    LEFT JOIN branches rb ON co.requesting_branch_id = rb.id
                    LEFT JOIN branches fb ON co.fulfilling_branch_id = fb.id
                    LEFT JOIN users ru ON co.requested_by = ru.id
                    LEFT JOIN users au ON co.approved_by = au.id
                    WHERE co.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return null;
            }

            // Get order items with product details
            $sql = "SELECT oi.*,
                           CASE
                               WHEN oi.product_type = 'FINISHED_PRODUCT' THEN p.name
                               WHEN oi.product_type = 'RAW_MATERIAL' THEN rm.name
                               WHEN oi.product_type = 'THIRD_PARTY_PRODUCT' THEN tp.name
                               WHEN oi.product_type = 'PACKAGING_MATERIAL' THEN pm.name
                           END as product_name,
                           CASE
                               WHEN oi.product_type = 'FINISHED_PRODUCT' THEN p.package_size
                               WHEN oi.product_type = 'RAW_MATERIAL' THEN rm.unit_of_measure
                               WHEN oi.product_type = 'THIRD_PARTY_PRODUCT' THEN tp.package_size
                               WHEN oi.product_type = 'PACKAGING_MATERIAL' THEN pm.unit
                           END as package_size,
                           CASE
                               WHEN oi.product_type = 'FINISHED_PRODUCT' THEN p.unit_price
                               WHEN oi.product_type = 'RAW_MATERIAL' THEN rm.selling_price
                               WHEN oi.product_type = 'THIRD_PARTY_PRODUCT' THEN tp.selling_price
                               WHEN oi.product_type = 'PACKAGING_MATERIAL' THEN pm.unit_cost
                           END as default_unit_price,
                           CASE
                               WHEN oi.product_type = 'FINISHED_PRODUCT' THEN p.description
                               WHEN oi.product_type = 'RAW_MATERIAL' THEN rm.description
                               WHEN oi.product_type = 'THIRD_PARTY_PRODUCT' THEN tp.description
                               WHEN oi.product_type = 'PACKAGING_MATERIAL' THEN pm.description
                           END as product_description
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_type = 'FINISHED_PRODUCT' AND oi.product_id = p.id
                    LEFT JOIN raw_materials rm ON oi.product_type = 'RAW_MATERIAL' AND oi.product_id = rm.id
                    LEFT JOIN third_party_products tp ON oi.product_type = 'THIRD_PARTY_PRODUCT' AND oi.product_id = tp.id
                    LEFT JOIN packaging_materials pm ON oi.product_type = 'PACKAGING_MATERIAL' AND oi.product_id = pm.id
                    WHERE oi.order_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get order bags
            $sql = "SELECT ob.*, pb.serial_number, p.name as product_name
                    FROM order_bags ob
                    LEFT JOIN product_bags pb ON ob.product_bag_id = pb.id
                    LEFT JOIN products p ON pb.product_id = p.id
                    WHERE ob.order_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            $order['bags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $order;

        } catch (Exception $e) {
            error_log("Error getting order details: " . $e->getMessage());
            return null;
        }
    }

    // Get single order basic info
    public function getOrderById($orderId) {
        try {
            $sql = "SELECT * FROM customer_orders WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting order by ID: " . $e->getMessage());
            return null;
        }
    }

    // Assign specific bags to order and mark as in transit
    public function assignBagsAndSend($orderId, $assignments, $driverId, $deliveryNotes, $updatedBy) {
        try {
            $this->conn->beginTransaction();

            // Get order details
            $order = $this->getOrderById($orderId);
            if (!$order || $order['status'] !== 'APPROVED') {
                throw new Exception('Order not found or not approved');
            }

            // Clear existing bag assignments
            $sql = "DELETE FROM order_bags WHERE order_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId]);

            // Assign specific bags to order
            foreach ($assignments as $assignment) {
                if (!empty($assignment['selected_bags'])) {
                    foreach ($assignment['selected_bags'] as $bagId) {
                        // Add bag to order_bags table
                        $sql = "INSERT INTO order_bags (order_id, product_bag_id, notes)
                                VALUES (?, ?, ?)";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([
                            $orderId,
                            $bagId,
                            $deliveryNotes
                        ]);

                        // Update bag status to allocated
                        $sql = "UPDATE product_bags SET status = 'Allocated' WHERE id = ?";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->execute([$bagId]);
                    }
                }
            }

            // Update order status to IN_TRANSIT
            $sql = "UPDATE customer_orders
                   SET status = 'IN_TRANSIT', updated_by = ?, shipped_date = NOW(),
                       delivery_notes = ?
                   WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$updatedBy, $deliveryNotes, $orderId]);

            // Generate QR code for delivery verification
            $qrCode = $this->generateDeliveryQRCode($orderId);

            // Log activity
            $this->logActivity(
                $updatedBy,
                'ORDER_BAGS_ASSIGNED',
                "Bags assigned and order {$order['order_number']} sent for delivery"
            );

            $this->conn->commit();
            return [
                'success' => true,
                'qr_code' => $qrCode
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error assigning bags and sending order: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Generate QR code for delivery verification
    private function generateDeliveryQRCode($orderId) {
        // Simple QR code data - in real implementation, use proper QR library
        return base64_encode(json_encode([
            'order_id' => $orderId,
            'verification_code' => md5($orderId . date('Y-m-d H:i:s')),
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    // Helper methods
    private function generateOrderNumber($branchId) {
        $prefix = "ORD-" . date('Ymd') . "-B{$branchId}-";

        $sql = "SELECT COUNT(*) + 1 as next_number
                FROM customer_orders
                WHERE requesting_branch_id = ?
                AND DATE(created_at) = CURDATE()";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$branchId]);
        $result = $stmt->fetch();

        return $prefix . str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
    }

    private function generateSaleNumber($branchId) {
        $prefix = "SALE-" . date('Ymd') . "-B{$branchId}-";

        $sql = "SELECT COUNT(*) + 1 as next_number
                FROM sales
                WHERE branch_id = ?
                AND DATE(created_at) = CURDATE()";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$branchId]);
        $result = $stmt->fetch();

        return $prefix . str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
    }

    private function getProductPrice($productType, $productId) {
        try {
            $table = '';
            switch ($productType) {
                case 'FINISHED_PRODUCT':
                    $table = 'products';
                    break;
                case 'RAW_MATERIAL':
                    $table = 'raw_materials';
                    break;
                case 'THIRD_PARTY_PRODUCT':
                    $table = 'third_party_products';
                    break;
                case 'PACKAGING_MATERIAL':
                    $table = 'packaging_materials';
                    break;
                default:
                    return 0;
            }

            $sql = "SELECT selling_price FROM {$table} WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$productId]);
            $result = $stmt->fetch();

            return $result ? (float)$result['selling_price'] : 0;

        } catch (Exception $e) {
            error_log("Error getting product price: " . $e->getMessage());
            return 0;
        }
    }

    private function recordInventoryMovement($branchId, $productType, $productId, $movementType, $quantity, $unit, $referenceId) {
        try {
            $sql = "INSERT INTO inventory_movements
                   (branch_id, product_type, product_id, movement_type, quantity, unit, reference_type, reference_id, notes, created_by, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, 'SALE', ?, 'Order delivery conversion', ?, NOW())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $branchId,
                $productType,
                $productId,
                $movementType,
                $quantity,
                $unit,
                $referenceId,
                $_SESSION['user_id'] ?? 1
            ]);

        } catch (Exception $e) {
            error_log("Error recording inventory movement: " . $e->getMessage());
        }
    }

    private function logActivity($userId, $action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, module, description, created_at)
                   VALUES (?, ?, 'ORDERS', ?, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
?>