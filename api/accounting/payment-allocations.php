<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/payment-allocations.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/payment-allocations.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('accounts', 'view');
} elseif ($method === 'POST' || $method === 'PUT') {
    enforceApiPermission('accounts', 'edit');
} elseif ($method === 'DELETE') {
    enforceApiPermission('accounts', 'delete');
}

try {
    // Check if payment_allocations table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payment_allocations'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS payment_allocations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                payment_id INT NULL,
                receipt_id INT NULL,
                invoice_id INT NULL,
                bill_id INT NULL,
                allocated_amount DECIMAL(15,2) NOT NULL,
                allocation_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (payment_id) REFERENCES payment_payments(id) ON DELETE CASCADE,
                FOREIGN KEY (receipt_id) REFERENCES payment_receipts(id) ON DELETE CASCADE,
                FOREIGN KEY (invoice_id) REFERENCES accounts_receivable(id) ON DELETE CASCADE,
                FOREIGN KEY (bill_id) REFERENCES accounts_payable(id) ON DELETE CASCADE,
                INDEX idx_payment (payment_id),
                INDEX idx_receipt (receipt_id),
                INDEX idx_invoice (invoice_id),
                INDEX idx_bill (bill_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $allocationId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $paymentId = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : null;
        $receiptId = isset($_GET['receipt_id']) ? intval($_GET['receipt_id']) : null;
        $invoiceId = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : null;
        $billId = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : null;
        
        if ($allocationId) {
            // Get single allocation
            $stmt = $conn->prepare("SELECT * FROM payment_allocations WHERE id = ?");
            $stmt->bind_param('i', $allocationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $allocation = $result->fetch_assoc();
            
            if ($allocation) {
                echo json_encode([
                    'success' => true,
                    'allocation' => $allocation
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Allocation not found'
                ]);
            }
        } else {
            // Get allocations with filters
            $query = "SELECT * FROM payment_allocations WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($paymentId) {
                $query .= " AND payment_id = ?";
                $params[] = $paymentId;
                $types .= 'i';
            }
            if ($receiptId) {
                $query .= " AND receipt_id = ?";
                $params[] = $receiptId;
                $types .= 'i';
            }
            if ($invoiceId) {
                $query .= " AND invoice_id = ?";
                $params[] = $invoiceId;
                $types .= 'i';
            }
            if ($billId) {
                $query .= " AND bill_id = ?";
                $params[] = $billId;
                $types .= 'i';
            }
            
            $query .= " ORDER BY allocation_date DESC, created_at DESC";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $allocations = [];
            while ($row = $result->fetch_assoc()) {
                $allocations[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'allocations' => $allocations
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new allocation
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['allocated_amount']) || !isset($data['allocation_date'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Allocated amount and allocation date are required'
            ]);
            exit;
        }
        
        // Must have at least one of: payment_id, receipt_id
        // And at least one of: invoice_id, bill_id
        if ((!isset($data['payment_id']) && !isset($data['receipt_id'])) ||
            (!isset($data['invoice_id']) && !isset($data['bill_id']))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Must specify payment/receipt and invoice/bill'
            ]);
            exit;
        }
        
        $paymentId = isset($data['payment_id']) ? intval($data['payment_id']) : null;
        $receiptId = isset($data['receipt_id']) ? intval($data['receipt_id']) : null;
        $invoiceId = isset($data['invoice_id']) ? intval($data['invoice_id']) : null;
        $billId = isset($data['bill_id']) ? intval($data['bill_id']) : null;
        $allocatedAmount = floatval($data['allocated_amount']);
        $allocationDate = $data['allocation_date'];
        
        $conn->begin_transaction();
        
        try {
            // Insert allocation
            $stmt = $conn->prepare("
                INSERT INTO payment_allocations 
                (payment_id, receipt_id, invoice_id, bill_id, allocated_amount, allocation_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iiidds', $paymentId, $receiptId, $invoiceId, $billId, $allocatedAmount, $allocationDate);
            $stmt->execute();
            $allocationId = $conn->insert_id;
            
            // Update invoice/bill paid amount
            if ($invoiceId) {
                $stmt = $conn->prepare("
                    UPDATE accounts_receivable 
                    SET paid_amount = COALESCE(paid_amount, 0) + ?, 
                        balance_amount = total_amount - (COALESCE(paid_amount, 0) + ?),
                        status = CASE 
                            WHEN (COALESCE(paid_amount, 0) + ?) >= total_amount THEN 'Paid'
                            WHEN (COALESCE(paid_amount, 0) + ?) > 0 THEN 'Partially Paid'
                            ELSE status
                        END
                    WHERE id = ?
                ");
                $stmt->bind_param('ddddi', $allocatedAmount, $allocatedAmount, $allocatedAmount, $allocatedAmount, $invoiceId);
                $stmt->execute();
            }
            
            if ($billId) {
                $stmt = $conn->prepare("
                    UPDATE accounts_payable 
                    SET paid_amount = COALESCE(paid_amount, 0) + ?, 
                        balance_amount = total_amount - (COALESCE(paid_amount, 0) + ?),
                        status = CASE 
                            WHEN (COALESCE(paid_amount, 0) + ?) >= total_amount THEN 'Paid'
                            WHEN (COALESCE(paid_amount, 0) + ?) > 0 THEN 'Partially Paid'
                            ELSE status
                        END
                    WHERE id = ?
                ");
                $stmt->bind_param('ddddi', $allocatedAmount, $allocatedAmount, $allocatedAmount, $allocatedAmount, $billId);
                $stmt->execute();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment allocation created successfully',
                'allocation_id' => $allocationId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        // Update allocation
        $allocationId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$allocationId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Allocation ID is required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = '';
        
        if (isset($data['allocated_amount'])) {
            $updateFields[] = "allocated_amount = ?";
            $params[] = floatval($data['allocated_amount']);
            $types .= 'd';
        }
        if (isset($data['allocation_date'])) {
            $updateFields[] = "allocation_date = ?";
            $params[] = $data['allocation_date'];
            $types .= 's';
        }
        
        if (!empty($updateFields)) {
            $params[] = $allocationId;
            $types .= 'i';
            $query = "UPDATE payment_allocations SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Allocation updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
        }
    } elseif ($method === 'DELETE') {
        $allocationId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$allocationId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Allocation ID is required']);
            exit;
        }
        
        // Get allocation details before deleting
        $stmt = $conn->prepare("SELECT * FROM payment_allocations WHERE id = ?");
        $stmt->bind_param('i', $allocationId);
        $stmt->execute();
        $allocation = $stmt->get_result()->fetch_assoc();
        
        if (!$allocation) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Allocation not found']);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            // Reverse invoice/bill paid amount
            if ($allocation['invoice_id']) {
                $stmt = $conn->prepare("
                    UPDATE accounts_receivable 
                    SET paid_amount = GREATEST(0, COALESCE(paid_amount, 0) - ?),
                        balance_amount = total_amount - GREATEST(0, COALESCE(paid_amount, 0) - ?),
                        status = CASE 
                            WHEN GREATEST(0, COALESCE(paid_amount, 0) - ?) <= 0 THEN 'Sent'
                            WHEN GREATEST(0, COALESCE(paid_amount, 0) - ?) < total_amount THEN 'Partially Paid'
                            ELSE 'Paid'
                        END
                    WHERE id = ?
                ");
                $amount = floatval($allocation['allocated_amount']);
                $stmt->bind_param('ddddi', $amount, $amount, $amount, $amount, $allocation['invoice_id']);
                $stmt->execute();
            }
            
            if ($allocation['bill_id']) {
                $stmt = $conn->prepare("
                    UPDATE accounts_payable 
                    SET paid_amount = GREATEST(0, COALESCE(paid_amount, 0) - ?),
                        balance_amount = total_amount - GREATEST(0, COALESCE(paid_amount, 0) - ?),
                        status = CASE 
                            WHEN GREATEST(0, COALESCE(paid_amount, 0) - ?) <= 0 THEN 'Sent'
                            WHEN GREATEST(0, COALESCE(paid_amount, 0) - ?) < total_amount THEN 'Partially Paid'
                            ELSE 'Paid'
                        END
                    WHERE id = ?
                ");
                $amount = floatval($allocation['allocated_amount']);
                $stmt->bind_param('ddddi', $amount, $amount, $amount, $amount, $allocation['bill_id']);
                $stmt->execute();
            }
            
            // Delete allocation
            $stmt = $conn->prepare("DELETE FROM payment_allocations WHERE id = ?");
            $stmt->bind_param('i', $allocationId);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Allocation deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    error_log('Payment allocations API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

