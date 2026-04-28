<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/payment-payments.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/payment-payments.php`.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/core/invoice-payment-automation.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('payment-vouchers', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('payment-vouchers', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('payment-vouchers', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('payment-vouchers', 'delete');
}

try {
    // Check if payment_payments table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payment_payments'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->free();
    }
    if (!$tableExists) {
        // Create table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS payment_payments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                payment_number VARCHAR(50) NOT NULL UNIQUE,
                payment_date DATE NOT NULL,
                payment_method ENUM('Cash', 'Bank Transfer', 'Cheque', 'Credit Card', 'Other') NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'SAR',
                bank_account_id INT NULL,
                cheque_number VARCHAR(50) NULL,
                reference_number VARCHAR(100),
                vendor_id INT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id INT NULL,
                status ENUM('Draft', 'Sent', 'Cleared', 'Cancelled') DEFAULT 'Draft',
                notes TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_payment_date (payment_date),
                INDEX idx_payment_method (payment_method),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $paymentId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
        
        if ($paymentId) {
            // Get single payment
            $stmt = $conn->prepare("SELECT * FROM payment_payments WHERE id = ?");
            $stmt->bind_param('i', $paymentId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'payment' => $row
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Payment not found'
                ]);
            }
            $result->free();
            $stmt->close();
        } else {
            // Get all payments
            $query = "SELECT * FROM payment_payments WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($dateFrom) {
                $query .= " AND payment_date >= ?";
                $params[] = $dateFrom;
                $types .= 's';
            }
            if ($dateTo) {
                $query .= " AND payment_date <= ?";
                $params[] = $dateTo;
                $types .= 's';
            }
            
            $query .= " ORDER BY created_at DESC, payment_date DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $payments = [];
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            $result->free();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'payments' => $payments
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new payment
        $data = json_decode(file_get_contents('php://input'), true);
        
        $paymentNumber = $data['payment_number'] ?? null;
        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        $paymentMethod = $data['payment_method'] ?? 'Cash';
        $amount = floatval($data['amount'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'SAR');
        $bankAccountId = isset($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
        $chequeNumber = $data['cheque_number'] ?? null;
        $referenceNumber = $data['reference_number'] ?? null;
        $vendorId = isset($data['vendor_id']) ? intval($data['vendor_id']) : null;
        $entityType = $data['entity_type'] ?? null;
        $entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
        $status = $data['status'] ?? 'Draft';
        $notes = $data['notes'] ?? null;
        $userId = $_SESSION['user_id'];
        
        if (!$paymentNumber) {
            // Generate payment number
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(payment_number, 5) AS UNSIGNED)) as max_num FROM payment_payments WHERE payment_number LIKE 'PAY-%'");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $nextNum = ($row['max_num'] ?? 0) + 1;
            $paymentNumber = 'PAY-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
            $result->free();
            $stmt->close();
        }
        
        $stmt = $conn->prepare("
            INSERT INTO payment_payments 
            (payment_number, payment_date, payment_method, amount, currency, bank_account_id, cheque_number, reference_number, vendor_id, entity_type, entity_id, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $stmt->bind_param('sssdsssssssssi', $paymentNumber, $paymentDate, $paymentMethod, $amount, $currency, $bankAccountId, $chequeNumber, $referenceNumber, $vendorId, $entityType, $entityId, $status, $notes, $userId);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Execute failed: ' . $stmt->error);
        }
        
        $paymentId = $conn->insert_id;
        $stmt->close();

        // Auto-create journal entry if status is Cleared or Sent (considered posted)
        $journalResult = null;
        if (in_array($status, ['Cleared', 'Sent'])) {
            try {
                $invoiceId = null; // Can be linked via payment_allocations
                $costCenterId = isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
                $journalResult = createPaymentJournalEntry(
                    $conn,
                    $paymentId,
                    $paymentNumber,
                    $paymentDate,
                    $amount,
                    $bankAccountId,
                    $invoiceId,
                    $costCenterId,
                    $notes
                );
                if (!$journalResult['success']) {
                    error_log("WARNING: Failed to create journal entry for payment {$paymentId}: " . $journalResult['message']);
                }
            } catch (Exception $je) {
                error_log("WARNING: Journal entry creation failed for payment {$paymentId}: " . $je->getMessage());
                // Don't fail the payment creation if journal entry fails
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Payment created successfully',
            'payment_id' => $paymentId,
            'journal_entry' => $journalResult
        ]);
    } elseif ($method === 'PUT') {
        // Update existing payment
        $paymentId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$paymentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Payment ID required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Get old data for history (before update)
        $oldStmt = $conn->prepare("SELECT * FROM payment_payments WHERE id = ?");
        $oldStmt->bind_param('i', $paymentId);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();
        $oldPayment = $oldResult->fetch_assoc();
        $oldResult->free();
        $oldStmt->close();
        
        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        $paymentMethod = $data['payment_method'] ?? 'Cash';
        $amount = floatval($data['amount'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'SAR');
        $bankAccountId = isset($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
        $chequeNumber = $data['cheque_number'] ?? null;
        $referenceNumber = $data['reference_number'] ?? null;
        $vendorId = isset($data['vendor_id']) ? intval($data['vendor_id']) : null;
        $entityType = $data['entity_type'] ?? null;
        $entityId = isset($data['entity_id']) ? intval($data['entity_id']) : null;
        $status = $data['status'] ?? 'Draft';
        $notes = $data['notes'] ?? null;
        
        $stmt = $conn->prepare("
            UPDATE payment_payments 
            SET payment_date = ?, payment_method = ?, amount = ?, currency = ?, bank_account_id = ?, 
                cheque_number = ?, reference_number = ?, vendor_id = ?, entity_type = ?, entity_id = ?, 
                status = ?, notes = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param('ssdsssssssssi', $paymentDate, $paymentMethod, $amount, $currency, $bankAccountId, 
            $chequeNumber, $referenceNumber, $vendorId, $entityType, $entityId, $status, $notes, $paymentId);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affectedRows > 0) {
            // Get updated payment for history
            $fetchStmt = $conn->prepare("SELECT * FROM payment_payments WHERE id = ?");
            $fetchStmt->bind_param('i', $paymentId);
            $fetchStmt->execute();
            $fetchResult = $fetchStmt->get_result();
            $updatedPayment = $fetchResult->fetch_assoc();
            $fetchResult->free();
            $fetchStmt->close();
            
            // Auto-create journal entry if status changed to Cleared or Sent
            $oldStatus = $oldPayment['status'] ?? 'Draft';
            $newStatus = $status;
            $journalResult = null;
            if (in_array($newStatus, ['Cleared', 'Sent']) && !in_array($oldStatus, ['Cleared', 'Sent'])) {
                try {
                    $paymentNumber = $oldPayment['payment_number'] ?? $data['payment_number'] ?? '';
                    $invoiceId = null;
                    $costCenterId = isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
                    $journalResult = createPaymentJournalEntry(
                        $conn,
                        $paymentId,
                        $paymentNumber,
                        $paymentDate,
                        $amount,
                        $bankAccountId,
                        $invoiceId,
                        $costCenterId,
                        $notes
                    );
                    if (!$journalResult['success']) {
                        error_log("WARNING: Failed to create journal entry for payment {$paymentId}: " . $journalResult['message']);
                    }
                } catch (Exception $je) {
                    error_log("WARNING: Journal entry creation failed for payment {$paymentId}: " . $je->getMessage());
                    // Don't fail the payment update if journal entry fails
                }
            }
            
            // Log history
            if ($oldPayment && $updatedPayment) {
                $helperPath = __DIR__ . '/../core/global-history-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('logGlobalHistory')) {
                        @logGlobalHistory('payment_payments', $paymentId, 'update', 'accounting', $oldPayment, $updatedPayment);
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment updated successfully',
                'journal_entry' => $journalResult
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Payment not found or no changes made'
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Payment Payments API Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    http_response_code(500);
    error_log('Payment Payments API Fatal Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

