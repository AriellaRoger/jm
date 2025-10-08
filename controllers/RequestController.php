<?php
// File: controllers/RequestController.php
// Minimal request management controller

require_once __DIR__ . '/../config/database.php';

class RequestController {
    private $conn;

    public function __construct() {
        $this->conn = getDbConnection();
    }

    // Create new request with multiple items
    public function createRequest($data) {
        try {
            $this->conn->beginTransaction();

            // Generate request number
            $requestNumber = 'REQ-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Insert main request
            $sql = "INSERT INTO requests (request_number, branch_id, requested_by, total_items, notes)
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $requestNumber,
                $data['branch_id'],
                $data['requested_by'],
                count($data['items']),
                $data['notes'] ?? ''
            ]);

            $requestId = $this->conn->lastInsertId();

            // Insert request items
            $itemSql = "INSERT INTO request_items (request_id, product_type, product_id, product_name, quantity, unit, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            $itemStmt = $this->conn->prepare($itemSql);

            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    $requestId,
                    $item['product_type'],
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit'],
                    $item['notes'] ?? ''
                ]);
            }

            $this->conn->commit();
            return ['success' => true, 'request_number' => $requestNumber];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get requests based on user role
    public function getRequests($userRole, $branchId = null) {
        try {
            if ($userRole === 'Branch Operator') {
                // Branch operators see only their branch requests
                $sql = "SELECT r.*, b.name as branch_name, u.full_name as requested_by_name
                        FROM requests r
                        JOIN branches b ON r.branch_id = b.id
                        JOIN users u ON r.requested_by = u.id
                        WHERE r.branch_id = ?
                        ORDER BY r.created_at DESC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$branchId]);
            } else {
                // Admin/Supervisor see all requests
                $sql = "SELECT r.*, b.name as branch_name, u.full_name as requested_by_name
                        FROM requests r
                        JOIN branches b ON r.branch_id = b.id
                        JOIN users u ON r.requested_by = u.id
                        ORDER BY r.created_at DESC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute();
            }

            $requests = $stmt->fetchAll();

            // Get items for each request and format for display
            foreach ($requests as &$request) {
                $itemSql = "SELECT * FROM request_items WHERE request_id = ? ORDER BY id";
                $itemStmt = $this->conn->prepare($itemSql);
                $itemStmt->execute([$request['id']]);
                $items = $itemStmt->fetchAll();

                $request['items'] = $items;

                // Create display summary for table view
                if (count($items) == 1) {
                    $request['product_name'] = $items[0]['product_name'];
                    $request['quantity'] = $items[0]['quantity'];
                    $request['unit'] = $items[0]['unit'];
                } else {
                    $request['product_name'] = count($items) . ' different items';
                    $request['quantity'] = array_sum(array_column($items, 'quantity'));
                    $request['unit'] = 'Total items';
                }
            }

            return ['success' => true, 'requests' => $requests];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Process request (confirm/reject)
    public function processRequest($requestId, $action, $userId, $notes = '') {
        try {
            $status = ($action === 'confirm') ? 'CONFIRMED' : 'REJECTED';

            $sql = "UPDATE requests
                    SET status = ?, processed_by = ?, processed_at = NOW(), response_notes = ?
                    WHERE id = ? AND status = 'PENDING'";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$status, $userId, $notes, $requestId]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => "Request {$status} successfully"];
            } else {
                return ['success' => false, 'message' => 'Request not found or already processed'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get detailed information for a specific request
    public function getRequestDetails($requestId) {
        try {
            // Get request information with user and branch details
            $sql = "SELECT r.*,
                           b.name as branch_name,
                           u.full_name as requested_by_name,
                           p.full_name as processed_by_name
                    FROM requests r
                    JOIN branches b ON r.branch_id = b.id
                    JOIN users u ON r.requested_by = u.id
                    LEFT JOIN users p ON r.processed_by = p.id
                    WHERE r.id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                return ['success' => false, 'message' => 'Request not found'];
            }

            // Get request items
            $itemSql = "SELECT * FROM request_items WHERE request_id = ? ORDER BY id";
            $itemStmt = $this->conn->prepare($itemSql);
            $itemStmt->execute([$requestId]);
            $request['items'] = $itemStmt->fetchAll();

            return ['success' => true, 'request' => $request];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get products for selection
    public function getProducts($productType) {
        try {
            switch ($productType) {
                case 'RAW_MATERIAL':
                    $sql = "SELECT id, name, unit_of_measure as unit FROM raw_materials WHERE status = 'ACTIVE'";
                    break;
                case 'FINISHED_PRODUCT':
                    $sql = "SELECT id, name, 'Bags' as unit FROM products WHERE status = 'ACTIVE'";
                    break;
                case 'THIRD_PARTY_PRODUCT':
                    $sql = "SELECT id, name, unit_of_measure as unit FROM third_party_products WHERE status = 'ACTIVE'";
                    break;
                default:
                    return ['success' => false, 'message' => 'Invalid product type'];
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return ['success' => true, 'products' => $stmt->fetchAll()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>