<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/bank-reconciliation.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/bank-reconciliation.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

/**
 * Log reconciliation audit trail
 */
function logReconciliationAudit($conn, $reconciliationId, $action, $fieldName = null, $oldValue = null, $newValue = null, $description = null, $performedBy = null) {
    if (!$performedBy) {
        $performedBy = $_SESSION['user_id'] ?? 0;
    }
    
    $auditTableCheck = $conn->query("SHOW TABLES LIKE 'reconciliation_audit_log'");
    if ($auditTableCheck && $auditTableCheck->num_rows > 0) {
        $auditTableCheck->free();
        $stmt = $conn->prepare("
            INSERT INTO reconciliation_audit_log 
            (reconciliation_id, action, field_name, old_value, new_value, description, performed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssssi', $reconciliationId, $action, $fieldName, $oldValue, $newValue, $description, $performedBy);
        $stmt->execute();
        $stmt->close();
    } else {
        if ($auditTableCheck) {
            $auditTableCheck->free();
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('bank-accounts', 'reconcile');
} elseif ($method === 'POST') {
    enforceApiPermission('bank-accounts', 'reconcile');
} elseif ($method === 'PUT') {
    enforceApiPermission('bank-accounts', 'reconcile');
} elseif ($method === 'DELETE') {
    enforceApiPermission('bank-accounts', 'reconcile');
}

try {
    // Check if bank_reconciliations table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'bank_reconciliations'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->free();
    }
    
    if (!$tableExists) {
        // Create table if it doesn't exist
        $conn->query("
            CREATE TABLE IF NOT EXISTS bank_reconciliations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                bank_account_id INT NOT NULL,
                reconciliation_date DATE NOT NULL,
                statement_balance DECIMAL(15,2) NOT NULL,
                book_balance DECIMAL(15,2) NOT NULL,
                reconciled_balance DECIMAL(15,2) NOT NULL,
                difference_amount DECIMAL(15,2) DEFAULT 0.00,
                status ENUM('In Progress', 'Reconciled', 'Finalized') DEFAULT 'In Progress',
                notes TEXT,
                created_by INT NOT NULL,
                reconciled_by INT NULL,
                reconciled_at TIMESTAMP NULL,
                updated_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bank_account (bank_account_id),
                INDEX idx_reconciliation_date (reconciliation_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } else {
        // Ensure audit trail columns exist
        $columnsCheck = $conn->query("SHOW COLUMNS FROM bank_reconciliations LIKE 'reconciled_by'");
        if (!$columnsCheck || $columnsCheck->num_rows === 0) {
            $conn->query("ALTER TABLE bank_reconciliations ADD COLUMN reconciled_by INT NULL AFTER notes");
        }
        if ($columnsCheck) {
            $columnsCheck->free();
        }
        
        $columnsCheck = $conn->query("SHOW COLUMNS FROM bank_reconciliations LIKE 'reconciled_at'");
        if (!$columnsCheck || $columnsCheck->num_rows === 0) {
            $conn->query("ALTER TABLE bank_reconciliations ADD COLUMN reconciled_at TIMESTAMP NULL AFTER reconciled_by");
        }
        if ($columnsCheck) {
            $columnsCheck->free();
        }
        
        $columnsCheck = $conn->query("SHOW COLUMNS FROM bank_reconciliations LIKE 'updated_by'");
        if (!$columnsCheck || $columnsCheck->num_rows === 0) {
            $conn->query("ALTER TABLE bank_reconciliations ADD COLUMN updated_by INT NULL AFTER reconciled_at");
        }
        if ($columnsCheck) {
            $columnsCheck->free();
        }
    }
    
    // Create bank_reconciliation_items table for transaction matching
    $itemsTableCheck = $conn->query("SHOW TABLES LIKE 'bank_reconciliation_items'");
    $itemsTableExists = $itemsTableCheck && $itemsTableCheck->num_rows > 0;
    if ($itemsTableCheck) {
        $itemsTableCheck->free();
    }
    
    if (!$itemsTableExists) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS bank_reconciliation_items (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reconciliation_id INT NOT NULL,
                ledger_entry_id INT NULL,
                statement_date DATE NULL,
                statement_amount DECIMAL(15,2) NOT NULL,
                statement_description VARCHAR(255) NULL,
                ledger_posting_date DATE NULL,
                ledger_amount DECIMAL(15,2) NULL,
                ledger_description TEXT NULL,
                match_status ENUM('unmatched', 'matched', 'discrepancy', 'manual') DEFAULT 'unmatched',
                discrepancy_amount DECIMAL(15,2) DEFAULT 0.00,
                discrepancy_reason VARCHAR(255) NULL,
                is_reconciled BOOLEAN DEFAULT FALSE,
                reconciled_by INT NULL,
                reconciled_at TIMESTAMP NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_reconciliation (reconciliation_id),
                INDEX idx_match_status (match_status),
                INDEX idx_ledger_entry (ledger_entry_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Create reconciliation_audit_log table for comprehensive audit trail
    $auditTableCheck = $conn->query("SHOW TABLES LIKE 'reconciliation_audit_log'");
    $auditTableExists = $auditTableCheck && $auditTableCheck->num_rows > 0;
    if ($auditTableCheck) {
        $auditTableCheck->free();
    }
    
    if (!$auditTableExists) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS reconciliation_audit_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                reconciliation_id INT NOT NULL,
                action ENUM('created', 'updated', 'matched', 'unmatched', 'discrepancy_flagged', 'finalized', 'status_changed') NOT NULL,
                field_name VARCHAR(100) NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                description TEXT NULL,
                performed_by INT NOT NULL,
                performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reconciliation (reconciliation_id),
                INDEX idx_action (action),
                INDEX idx_performed_by (performed_by),
                INDEX idx_performed_at (performed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $reconciliationId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $bankId = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : null;
        
        if ($reconciliationId) {
            // Get single reconciliation
            $stmt = $conn->prepare("SELECT * FROM bank_reconciliations WHERE id = ?");
            $stmt->bind_param('i', $reconciliationId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $reconciliation = $row;
                
                // Get reconciliation items if table exists
                $itemsTableCheck = $conn->query("SHOW TABLES LIKE 'bank_reconciliation_items'");
                if ($itemsTableCheck && $itemsTableCheck->num_rows > 0) {
                    $itemsTableCheck->free();
                    
                    // Get all items for this reconciliation
                    $itemsStmt = $conn->prepare("SELECT * FROM bank_reconciliation_items WHERE reconciliation_id = ? ORDER BY statement_date DESC, created_at DESC");
                    $itemsStmt->bind_param('i', $reconciliationId);
                    $itemsStmt->execute();
                    $itemsResult = $itemsStmt->get_result();
                    
                    $items = [];
                    while ($item = $itemsResult->fetch_assoc()) {
                        $items[] = $item;
                    }
                    $itemsResult->free();
                    $itemsStmt->close();
                    
                    $reconciliation['items'] = $items;
                    $reconciliation['items_count'] = count($items);
                    
                    // Count discrepancies
                    $discrepancies = array_filter($items, function($item) {
                        return $item['match_status'] === 'discrepancy';
                    });
                    $reconciliation['discrepancy_count'] = count($discrepancies);
                }
                
                $result->free();
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'reconciliation' => $reconciliation
                ]);
            } else {
                $result->free();
                $stmt->close();
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Reconciliation not found'
                ]);
            }
        } else {
            // Check if requesting ledger entries for matching
            $getLedgerEntries = isset($_GET['ledger_entries']) && $_GET['ledger_entries'] === 'true';
            $forBankId = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : null;
            $asOfDate = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
                $asOfDate = date('Y-m-d');
            }
            
            if ($getLedgerEntries && $forBankId) {
                // Get ledger entries for bank account matching
                // First, get the account_id mapped to this bank_account_id
                $bankStmt = $conn->prepare("SELECT account_id FROM accounting_banks WHERE id = ?");
                $bankStmt->bind_param('i', $forBankId);
                $bankStmt->execute();
                $bankResult = $bankStmt->get_result();
                $bankData = $bankResult->fetch_assoc();
                $mappedAccountId = isset($bankData['account_id']) ? intval($bankData['account_id']) : null;
                $bankResult->free();
                $bankStmt->close();
                
                if (!$mappedAccountId) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Bank account not found or not mapped to financial account',
                        'ledger_entries' => []
                    ]);
                    exit;
                }
                
                // Get ledger entries from general_ledger
                $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
                $ledgerEntries = [];
                
                if ($glTableCheck && $glTableCheck->num_rows > 0) {
                    $glTableCheck->free();
                    
                    $glStmt = $conn->prepare("
                        SELECT 
                            gl.id as ledger_entry_id,
                            gl.posting_date,
                            gl.debit,
                            gl.credit,
                            ABS(gl.debit - gl.credit) as amount,
                            je.entry_number,
                            je.description,
                            je.id as journal_entry_id
                        FROM general_ledger gl
                        LEFT JOIN journal_entries je ON gl.journal_entry_id = je.id
                        WHERE gl.account_id = ? AND gl.posting_date <= ?
                        ORDER BY gl.posting_date DESC, gl.id DESC
                    ");
                    $glStmt->bind_param('is', $mappedAccountId, $asOfDate);
                    $glStmt->execute();
                    $glResult = $glStmt->get_result();
                    
                    while ($entry = $glResult->fetch_assoc()) {
                        $ledgerEntries[] = [
                            'ledger_entry_id' => intval($entry['ledger_entry_id']),
                            'posting_date' => $entry['posting_date'],
                            'debit' => floatval($entry['debit']),
                            'credit' => floatval($entry['credit']),
                            'amount' => floatval($entry['amount']),
                            'entry_number' => $entry['entry_number'] ?? '',
                            'description' => $entry['description'] ?? '',
                            'journal_entry_id' => isset($entry['journal_entry_id']) ? intval($entry['journal_entry_id']) : null
                        ];
                    }
                    $glResult->free();
                    $glStmt->close();
                } else {
                    if ($glTableCheck) {
                        $glTableCheck->free();
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'ledger_entries' => $ledgerEntries,
                    'account_id' => $mappedAccountId,
                    'as_of_date' => $asOfDate
                ]);
                exit;
            }
            
            // Get all reconciliations
            $query = "SELECT * FROM bank_reconciliations WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($bankId) {
                $query .= " AND bank_account_id = ?";
                $params[] = $bankId;
                $types .= 'i';
            }
            
            $query .= " ORDER BY reconciliation_date DESC, created_at DESC LIMIT 50";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $reconciliations = [];
            while ($row = $result->fetch_assoc()) {
                $reconciliations[] = $row;
            }
            $result->free();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'reconciliations' => $reconciliations
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new reconciliation
        $data = json_decode(file_get_contents('php://input'), true);
        
        $bankAccountId = isset($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
        $reconciliationDate = $data['reconciliation_date'] ?? date('Y-m-d');
        $statementBalance = floatval($data['statement_balance'] ?? 0);
        $notes = $data['notes'] ?? null;
        $userId = $_SESSION['user_id'];
        
        if (!$bankAccountId) {
            throw new Exception('Bank account ID is required');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconciliationDate)) {
            throw new Exception('Invalid reconciliation_date format. Use YYYY-MM-DD');
        }
        
        // Get bank account info and map to financial_accounts account_id
        // ERP COMPLIANCE: current_balance is read for display/reconciliation comparison only
        // Actual book balance is calculated from GL below
        $bankStmt = $conn->prepare("SELECT account_id, current_balance FROM accounting_banks WHERE id = ?");
        $bankStmt->bind_param('i', $bankAccountId);
        $bankStmt->execute();
        $bankResult = $bankStmt->get_result();
        $bankData = $bankResult->fetch_assoc();
        
        if (!$bankData) {
            $bankResult->free();
            $bankStmt->close();
            throw new Exception('Bank account not found');
        }
        
        $mappedAccountId = isset($bankData['account_id']) ? intval($bankData['account_id']) : null;
        
        // Calculate book balance from general_ledger if available, otherwise use current_balance
        $bookBalance = floatval($bankData['current_balance'] ?? 0);
        
        if ($mappedAccountId) {
            // Check if general_ledger table exists
            $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
            if ($glTableCheck && $glTableCheck->num_rows > 0) {
                $glTableCheck->free();
                
                // Calculate balance from general_ledger up to reconciliation date
                $glStmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(debit), 0) as total_debit,
                        COALESCE(SUM(credit), 0) as total_credit
                    FROM general_ledger
                    WHERE account_id = ? AND posting_date <= ?
                ");
                $glStmt->bind_param('is', $mappedAccountId, $reconciliationDate);
                $glStmt->execute();
                $glResult = $glStmt->get_result();
                $glData = $glResult->fetch_assoc();
                $totalDebit = floatval($glData['total_debit'] ?? 0);
                $totalCredit = floatval($glData['total_credit'] ?? 0);
                
                // For asset accounts (debit normal balance), book balance = debit - credit
                // For liability/equity accounts (credit normal balance), book balance = credit - debit
                // Default to debit - credit (asset assumption)
                $bookBalance = $totalDebit - $totalCredit;
                
                $glResult->free();
                $glStmt->close();
            } else {
                if ($glTableCheck) {
                    $glTableCheck->free();
                }
            }
        }
        
        // Calculate difference
        $difference = $statementBalance - $bookBalance;
        $reconciledBalance = $statementBalance;
        
        $stmt = $conn->prepare("
            INSERT INTO bank_reconciliations 
            (bank_account_id, reconciliation_date, statement_balance, book_balance, reconciled_balance, difference_amount, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'In Progress', ?, ?)
        ");
        
        $stmt->bind_param('isddddssi', $bankAccountId, $reconciliationDate, $statementBalance, $bookBalance, $reconciledBalance, $difference, $notes, $userId);
        $stmt->execute();
        $reconciliationId = $conn->insert_id;
        $stmt->close();
        $bankResult->free();
        $bankStmt->close();
        
        // Log audit trail
        logReconciliationAudit($conn, $reconciliationId, 'created', null, null, null, 'Bank reconciliation created', $userId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bank reconciliation started successfully',
            'reconciliation_id' => $reconciliationId,
            'book_balance' => $bookBalance,
            'difference' => $difference
        ]);
    } elseif ($method === 'PUT') {
        // Update reconciliation
        $reconciliationId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$reconciliationId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reconciliation ID required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $reconciliationDate = $data['reconciliation_date'] ?? null;
        $statementBalance = isset($data['statement_balance']) ? floatval($data['statement_balance']) : null;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? null;
        
        // Get old values for audit trail and check status in single query
        $oldStmt = $conn->prepare("SELECT status, statement_balance FROM bank_reconciliations WHERE id = ?");
        $oldStmt->bind_param('i', $reconciliationId);
        $oldStmt->execute();
        $oldResult = $oldStmt->get_result();
        $oldData = $oldResult->fetch_assoc();
        
        // Check if reconciliation exists
        if (!$oldData) {
            $oldResult->free();
            $oldStmt->close();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Reconciliation not found']);
            exit;
        }
        
        // Check if reconciliation is Finalized - prevent editing
        $currentStatus = $oldData['status'] ?? null;
        if ($currentStatus === 'Finalized') {
            $oldResult->free();
            $oldStmt->close();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot edit finalized reconciliation']);
            exit;
        }
        
        $oldResult->free();
        $oldStmt->close();
        
        // Validate status transition (cannot go from Finalized back to lower status)
        if ($status && $currentStatus === 'Finalized' && $status !== 'Finalized') {
            throw new Exception('Cannot change status from Finalized to another status');
        }
        
        $updateFields = [];
        $params = [];
        $types = '';
        
        if ($reconciliationDate) {
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reconciliationDate)) {
                throw new Exception('Invalid reconciliation_date format. Use YYYY-MM-DD');
            }
            $updateFields[] = "reconciliation_date = ?";
            $params[] = $reconciliationDate;
            $types .= 's';
        }
        if ($statementBalance !== null) {
            $updateFields[] = "statement_balance = ?";
            $params[] = $statementBalance;
            $types .= 'd';
        }
        if ($status) {
            $updateFields[] = "status = ?";
            $params[] = $status;
            $types .= 's';
        }
        if ($notes !== null) {
            $updateFields[] = "notes = ?";
            $params[] = $notes;
            $types .= 's';
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }
        
        // Add updated_by to update fields
        $updateFields[] = "updated_by = ?";
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
        
        // Update reconciled_by and reconciled_at if status is changing to Reconciled
        if ($status === 'Reconciled' && ($oldData['status'] ?? '') !== 'Reconciled') {
            $updateFields[] = "reconciled_by = ?";
            $updateFields[] = "reconciled_at = NOW()";
            $params[] = $_SESSION['user_id'];
            $types .= 'i';
        }
        
        $params[] = $reconciliationId;
        $types .= 'i';
        
        // Include status check in WHERE clause to prevent race condition (cannot update if Finalized)
        $query = "UPDATE bank_reconciliations SET " . implode(', ', $updateFields) . " WHERE id = ? AND status != 'Finalized'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if ($affectedRows === 0 && $currentStatus === 'Finalized') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot edit finalized reconciliation']);
            exit;
        }
        
        if ($affectedRows > 0) {
            // Log audit trail
            if ($status && ($oldData['status'] ?? '') !== $status) {
                logReconciliationAudit($conn, $reconciliationId, 'status_changed', 'status', $oldData['status'] ?? null, $status, "Status changed from '{$oldData['status']}' to '{$status}'", $_SESSION['user_id']);
            }
            if ($statementBalance !== null && abs(($oldData['statement_balance'] ?? 0) - $statementBalance) > 0.01) {
                logReconciliationAudit($conn, $reconciliationId, 'updated', 'statement_balance', $oldData['statement_balance'] ?? null, $statementBalance, null, $_SESSION['user_id']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Reconciliation updated successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Reconciliation not found or no changes made'
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

