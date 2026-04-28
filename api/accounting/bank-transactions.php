<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/bank-transactions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/bank-transactions.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/core/erp-guardian.php';

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
    enforceApiPermission('bank-transactions', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('bank-transactions', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('bank-transactions', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('bank-transactions', 'delete');
}

try {
    // Check if accounting_bank_transactions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bank_transactions'");
    if ($tableCheck->num_rows === 0) {
        // Create table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS accounting_bank_transactions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                bank_id INT NOT NULL,
                transaction_date DATE NOT NULL,
                description VARCHAR(255) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                transaction_type ENUM('Deposit', 'Withdrawal', 'Transfer', 'Fee', 'Interest') NOT NULL DEFAULT 'Deposit',
                reference_number VARCHAR(100),
                is_reconciled TINYINT(1) DEFAULT 0,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (bank_id) REFERENCES accounting_banks(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
                INDEX idx_bank_id (bank_id),
                INDEX idx_transaction_date (transaction_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $bankId = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : null;
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
        
        if ($transactionId) {
            // Get single transaction
            $stmt = $conn->prepare("SELECT * FROM accounting_bank_transactions WHERE id = ?");
            $stmt->bind_param('i', $transactionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'transaction' => $row
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction not found'
                ]);
            }
        } else {
            // Get all transactions
            // First check if is_reconciled column exists, if not add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM accounting_bank_transactions LIKE 'is_reconciled'");
            if ($columnCheck->num_rows === 0) {
                $conn->query("ALTER TABLE accounting_bank_transactions ADD COLUMN is_reconciled TINYINT(1) DEFAULT 0");
            }
            
            $query = "SELECT * FROM accounting_bank_transactions WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($bankId) {
                $query .= " AND bank_id = ?";
                $params[] = $bankId;
                $types .= 'i';
            }
            if ($dateFrom) {
                $query .= " AND transaction_date >= ?";
                $params[] = $dateFrom;
                $types .= 's';
            }
            if ($dateTo) {
                $query .= " AND transaction_date <= ?";
                $params[] = $dateTo;
                $types .= 's';
            }
            
            $query .= " ORDER BY transaction_date DESC, created_at DESC LIMIT 100";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $transactions = [];
            while ($row = $result->fetch_assoc()) {
                // Ensure is_reconciled is set (default to 0 if null)
                if (!isset($row['is_reconciled'])) {
                    $row['is_reconciled'] = 0;
                }
                $transactions[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new transaction
        $data = json_decode(file_get_contents('php://input'), true);
        
        $bankId = isset($data['bank_id']) ? intval($data['bank_id']) : null;
        $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
        $description = $data['description'] ?? '';
        $amount = floatval($data['amount'] ?? 0);
        $transactionType = $data['transaction_type'] ?? 'Deposit';
        $referenceNumber = $data['reference_number'] ?? null;
        $userId = $_SESSION['user_id'];
        
        if (!$bankId || $amount <= 0) {
            throw new Exception('Bank ID and amount are required');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO accounting_bank_transactions 
            (bank_id, transaction_date, description, amount, transaction_type, reference_number, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param('issdssi', $bankId, $transactionDate, $description, $amount, $transactionType, $referenceNumber, $userId);
        $stmt->execute();
        $transactionId = $conn->insert_id;
        
        // ERP GUARDIAN: Validate financial action creates journal entry
        if (function_exists('erpGuardian')) {
            erpGuardian($conn, 'CREATE', [
                'module' => 'bank-transactions',
                'action' => 'create',
                'journal_entry_id' => null // Will be created below
            ]);
        }
        
        // ERP COMPLIANCE: Create journal entry and post to GL instead of direct balance update
        require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
        $jeResult = createBankTransactionJournalEntry($conn, $transactionId, $bankId, $transactionDate, $description, $amount, $transactionType, $referenceNumber);
        
        // ERP GUARDIAN: Validate journal entry was created
        if (function_exists('erpGuardian')) {
            erpGuardian($conn, 'CREATE', [
                'module' => 'bank-transactions',
                'action' => 'create',
                'journal_entry_id' => $jeResult['journal_entry_id'] ?? null
            ]);
        }
        
        if (!$jeResult['success']) {
            // Rollback transaction if journal entry creation fails
            $conn->query("DELETE FROM accounting_bank_transactions WHERE id = $transactionId");
            throw new Exception('Failed to create journal entry: ' . $jeResult['message']);
        }
        
        // ERP COMPLIANCE: Update bank balance from GL (DISPLAY-ONLY SYNC)
        // GL is the source of truth - this is only for UI display convenience
        // The balance is CALCULATED from GL, not directly modified
        $glBalance = getBankBalanceFromGL($conn, $bankId);
        
        // ERP GUARDIAN: This UPDATE is acceptable because:
        // 1. Value comes FROM GL (calculated, not direct modification)
        // 2. GL remains the source of truth
        // 3. This is display-only synchronization
        // Guardian allows this pattern: UPDATE ... SET field = (calculated_from_GL)
        $updateBalanceStmt = $conn->prepare("UPDATE accounting_banks SET current_balance = ? WHERE id = ?");
        $updateBalanceStmt->bind_param('di', $glBalance, $bankId);
        $updateBalanceStmt->execute();
        $updateBalanceStmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank transaction created successfully and posted to GL',
            'transaction_id' => $transactionId,
            'journal_entry_id' => $jeResult['journal_entry_id'],
            'entry_number' => $jeResult['entry_number'] ?? null
        ]);
    } elseif ($method === 'PUT') {
        // Update transaction
        $transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$transactionId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Get old transaction for balance adjustment
        $oldStmt = $conn->prepare("SELECT * FROM accounting_bank_transactions WHERE id = ?");
        $oldStmt->bind_param('i', $transactionId);
        $oldStmt->execute();
        $oldTransaction = $oldStmt->get_result()->fetch_assoc();
        
        $transactionDate = $data['transaction_date'] ?? $oldTransaction['transaction_date'];
        $description = $data['description'] ?? $oldTransaction['description'];
        $amount = isset($data['amount']) ? floatval($data['amount']) : $oldTransaction['amount'];
        $transactionType = $data['transaction_type'] ?? $oldTransaction['transaction_type'];
        $referenceNumber = $data['reference_number'] ?? $oldTransaction['reference_number'];
        
        // ERP COMPLIANCE: Bank transactions with journal entries cannot be edited
        // Must create reversal entry and new entry instead
        require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
        require_once __DIR__ . '/core/erp-posting-controls.php';
        
        // Check if old transaction has journal entry
        $oldBankId = intval($oldTransaction['bank_id']);
        $oldJeCheck = $conn->prepare("
            SELECT id FROM journal_entries 
            WHERE source_table = 'accounting_bank_transactions' 
            AND source_id = ?
            LIMIT 1
        ");
        $oldJeCheck->bind_param('i', $transactionId);
        $oldJeCheck->execute();
        $oldJeResult = $oldJeCheck->get_result();
        $hasOldJE = $oldJeResult->num_rows > 0;
        $oldJeResult->free();
        $oldJeCheck->close();
        
        if ($hasOldJE) {
            // Transaction has been posted - cannot edit directly
            // Must create reversal entry
            throw new Exception('ERP VIOLATION: Bank transactions with journal entries cannot be edited. Delete and recreate, or use reversal entry.');
        }
        
        // Update transaction (no journal entry exists yet)
        $stmt = $conn->prepare("
            UPDATE accounting_bank_transactions 
            SET transaction_date = ?, description = ?, amount = ?, transaction_type = ?, reference_number = ?
            WHERE id = ?
        ");
        
        $stmt->bind_param('ssdssi', $transactionDate, $description, $amount, $transactionType, $referenceNumber, $transactionId);
        $stmt->execute();
        
        // Create journal entry for updated transaction
        $jeResult = createBankTransactionJournalEntry($conn, $transactionId, $oldBankId, $transactionDate, $description, $amount, $transactionType, $referenceNumber);
        
        if (!$jeResult['success']) {
            throw new Exception('Failed to create journal entry: ' . $jeResult['message']);
        }
        
        // ERP COMPLIANCE: Update bank balance from GL (DISPLAY-ONLY SYNC)
        // GL is the source of truth - this is only for UI display convenience
        $glBalance = getBankBalanceFromGL($conn, $oldBankId);
        $updateBalanceStmt = $conn->prepare("UPDATE accounting_banks SET current_balance = ? WHERE id = ?");
        $updateBalanceStmt->bind_param('di', $glBalance, $oldBankId);
        $updateBalanceStmt->execute();
        $updateBalanceStmt->close();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Transaction updated successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Transaction not found or no changes made'
            ]);
        }
    } elseif ($method === 'DELETE') {
        // Delete transaction
        $transactionId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$transactionId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
            exit;
        }
        
        // Get transaction to reverse balance
        $stmt = $conn->prepare("SELECT * FROM accounting_bank_transactions WHERE id = ?");
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            exit;
        }
        
        // ERP COMPLIANCE: Check if transaction has journal entry
        require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
        require_once __DIR__ . '/core/erp-posting-controls.php';
        
        $bankId = intval($transaction['bank_id']);
        
        // Check if transaction has journal entry
        $jeCheck = $conn->prepare("
            SELECT id FROM journal_entries 
            WHERE source_table = 'accounting_bank_transactions' 
            AND source_id = ?
            LIMIT 1
        ");
        $jeCheck->bind_param('i', $transactionId);
        $jeCheck->execute();
        $jeResult = $jeCheck->get_result();
        $hasJE = $jeResult->num_rows > 0;
        $jeRow = null;
        if ($hasJE) {
            $jeRow = $jeResult->fetch_assoc();
        }
        $jeResult->free();
        $jeCheck->close();
        
        if ($hasJE && $jeRow) {
            // Transaction has been posted - must create reversal entry
            $jeId = $jeRow['id'];
            
            // Check if entry is posted
            $jeStatusCheck = $conn->prepare("SELECT status, posting_status, is_posted FROM journal_entries WHERE id = ?");
            $jeStatusCheck->bind_param('i', $jeId);
            $jeStatusCheck->execute();
            $jeStatusResult = $jeStatusCheck->get_result();
            $jeStatus = $jeStatusResult->fetch_assoc();
            $jeStatusResult->free();
            $jeStatusCheck->close();
            
            $isPosted = ($jeStatus['status'] === 'Posted' || 
                        $jeStatus['posting_status'] === 'posted' || 
                        $jeStatus['is_posted'] == 1);
            
            if ($isPosted) {
                // Create reversal entry
                $reversalDate = date('Y-m-d');
                $reversalDesc = "Reversal of bank transaction #{$transactionId}";
                $reversalResult = createReversalEntry($conn, $jeId, $reversalDate, $reversalDesc, $_SESSION['user_id'] ?? 1);
                
                if (!$reversalResult['success']) {
                    throw new Exception('Failed to create reversal entry: ' . $reversalResult['message']);
                }
            } else {
                // Entry not posted - can delete journal entry
                $conn->query("DELETE FROM journal_entry_lines WHERE journal_entry_id = $jeId");
                $conn->query("DELETE FROM journal_entries WHERE id = $jeId");
            }
        }
        
        // Delete transaction
        $stmt = $conn->prepare("DELETE FROM accounting_bank_transactions WHERE id = ?");
        $stmt->bind_param('i', $transactionId);
        $stmt->execute();
        
        // ERP COMPLIANCE: Update bank balance from GL (DISPLAY-ONLY SYNC)
        // GL is the source of truth - this is only for UI display convenience
        $glBalance = getBankBalanceFromGL($conn, $bankId);
        $updateBalanceStmt = $conn->prepare("UPDATE accounting_banks SET current_balance = ? WHERE id = ?");
        $updateBalanceStmt->bind_param('di', $glBalance, $bankId);
        $updateBalanceStmt->execute();
        $updateBalanceStmt->close();
        
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Bank transaction deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Transaction not found'
            ]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

