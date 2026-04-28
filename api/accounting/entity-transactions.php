<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entity-transactions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entity-transactions.php`.
 */
/**
 * Entity Transactions API
 * Comprehensive CRUD operations for entity-specific financial transactions
 * Supports: agents, workers, subagents, hr
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) { require_once __DIR__ . '/../core/date-helper.php'; }
elseif (file_exists(__DIR__ . '/core/date-helper.php')) { require_once __DIR__ . '/core/date-helper.php'; }
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) { if (empty($s) || $s === '0000-00-00') return ''; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]); $t = strtotime($s); return $t ? date('m/d/Y', $t) : $s; }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) { if (!is_array($data)) return $data; $fields = $fields ?? ['date','entry_date','invoice_date','bill_date','due_date','transaction_date','created_at','updated_at']; foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields); elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v); } return $data; }
}

header('Content-Type: application/json');

$entityTransactionsLogFile = __DIR__ . '/../../logs/entity-transactions.log';
$entityTransactionsLogDir = dirname($entityTransactionsLogFile);
if (!is_dir($entityTransactionsLogDir)) {
    mkdir($entityTransactionsLogDir, 0755, true);
}

if (!function_exists('log_entity_transactions')) {
    function log_entity_transactions($message) {
        global $entityTransactionsLogFile;
        error_log(date('c') . ' | ' . $message . PHP_EOL, 3, $entityTransactionsLogFile);
    }
}

if (!function_exists('send_json_response')) {
    function send_json_response($payload, $status = 200) {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        log_entity_transactions('response(' . $status . '): ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('entity_transactions_shutdown')) {
    function entity_transactions_shutdown() {
        $error = error_get_last();
        if ($error !== null) {
            log_entity_transactions('FATAL: ' . json_encode($error));
        }
    }
}
register_shutdown_function('entity_transactions_shutdown');

if (!function_exists('tableExists')) {
    function tableExists($conn, $tableName) {
        // Whitelist allowed table names to prevent SQL injection
        $allowedTables = [
            'financial_accounts', 'journal_entries', 'journal_entry_lines',
            'accounts_receivable', 'accounts_payable', 'financial_transactions',
            'transaction_lines', 'payment_receipts', 'payment_payments',
            'accounting_banks', 'accounting_bank_transactions', 'entity_transactions',
            'entry_approval', 'cost_centers', 'bank_guarantees'
        ];
        
        // Only check if table name is in whitelist
        if (!in_array($tableName, $allowedTables)) {
            return false;
        }
        
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}

if (!function_exists('generateReferenceNumber')) {
    function generateReferenceNumber($conn, $table, $column, $prefix) {
        // Whitelist allowed table names
        $allowedTables = [
            'financial_accounts', 'journal_entries', 'journal_entry_lines',
            'accounts_receivable', 'accounts_payable', 'financial_transactions',
            'transaction_lines', 'payment_receipts', 'payment_payments',
            'accounting_banks', 'accounting_bank_transactions', 'entity_transactions',
            'entry_approval', 'cost_centers', 'bank_guarantees'
        ];
        
        if (!in_array($table, $allowedTables)) {
            return $prefix . '1';
        }
        
        // Validate column name (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return $prefix . '1';
        }
        
        // Use backticks for table and column names (they're whitelisted, so safe)
        $stmt = $conn->prepare("SELECT `{$column}` FROM `{$table}` WHERE `{$column}` LIKE CONCAT(?, '%') ORDER BY CAST(SUBSTRING(`{$column}`, ?) AS UNSIGNED) DESC LIMIT 1");
        if ($stmt) {
            $prefixLength = strlen($prefix) + 1;
            $stmt->bind_param('si', $prefix, $prefixLength);
            $stmt->execute();
            $result = $stmt->get_result();
            $nextNumber = 1;
            if ($result && $row = $result->fetch_assoc()) {
                $columnValue = $row[$column];
                if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $columnValue, $matches)) {
                    $nextNumber = intval($matches[1]) + 1;
                }
            }
            $stmt->close();
        } else {
            $nextNumber = 1;
        }
        return $prefix . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('createAccountsReceivableFromEntity')) {
    function createAccountsReceivableFromEntity($conn, $data) {
        global $entityTransactionsLogFile;
        $log = function($msg) use ($entityTransactionsLogFile) {
            error_log(date('c') . ' | ' . $msg . PHP_EOL, 3, $entityTransactionsLogFile);
        };
        
        $log('createAccountsReceivableFromEntity called with data: ' . json_encode($data));
        
        // Create table if it doesn't exist
        if (!tableExists($conn, 'accounts_receivable')) {
            $log('Creating accounts_receivable table...');
            $conn->query("
                CREATE TABLE IF NOT EXISTS accounts_receivable (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    invoice_number VARCHAR(50) UNIQUE,
                    invoice_date DATE NOT NULL,
                    due_date DATE NOT NULL,
                    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(20) DEFAULT 'Draft',
                    currency VARCHAR(3) DEFAULT 'SAR',
                    entity_type VARCHAR(50),
                    entity_id INT,
                    description TEXT,
                    customer_id INT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_entity (entity_type, entity_id),
                    INDEX idx_status (status),
                    INDEX idx_invoice_date (invoice_date),
                    INDEX idx_customer (customer_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $invoiceNumber = $data['reference'] ?: generateReferenceNumber($conn, 'accounts_receivable', 'invoice_number', 'INV-');
        
        // Check if customer_id column exists, adjust query accordingly
        $colCheck = $conn->query("SHOW COLUMNS FROM accounts_receivable LIKE 'customer_id'");
        $hasCustomerId = $colCheck->num_rows > 0;
        
        if ($hasCustomerId) {
            $stmt = $conn->prepare("
                INSERT INTO accounts_receivable (
                    invoice_number, invoice_date, due_date,
                    total_amount, paid_amount, balance_amount,
                    debit_amount, credit_amount,
                    status, currency, entity_type, entity_id, description, created_by
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
            ");
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            if ($stmt) {
                $debitAmount = 0; // debit_amount (payment received) - must be variable for bind_param
                $stmt->bind_param(
                    'sssddddssssi', // 12 types for 12 placeholders
                    $invoiceNumber,
                    $data['invoice_date'],
                    $data['due_date'],
                    $data['amount'],
                    $data['amount'],
                    $debitAmount, // debit_amount (payment received)
                    $data['amount'], // credit_amount (invoice amount)
                    $data['currency'],
                    $data['entity_type'],
                    $data['entity_id'],
                    $data['description'],
                    $userId
                );
                if (!$stmt->execute()) {
                    $error = 'Failed to insert into accounts_receivable: ' . $stmt->error;
                    error_log($error);
                    $log($error);
                } else {
                    $success = 'Successfully inserted invoice into accounts_receivable: ' . $invoiceNumber . ' (ID: ' . $conn->insert_id . ')';
                    error_log($success);
                    $log($success);
                }
                $stmt->close();
            } else {
                $error = 'Failed to prepare statement for accounts_receivable: ' . $conn->error;
                error_log($error);
                $log($error);
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO accounts_receivable (
                    invoice_number, invoice_date, due_date,
                    total_amount, paid_amount, balance_amount,
                    debit_amount, credit_amount,
                    status, currency, entity_type, entity_id, description, created_by
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
            ");
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            if ($stmt) {
                // VALUES: (?, ?, ?, ?, 0, ?, 0, ?, 'Posted', ?, ?, ?, ?, ?)
                // 11 placeholders: invoice_number, invoice_date, due_date, total_amount, balance_amount, debit_amount, credit_amount, currency, entity_type, entity_id, description, created_by
                $debitAmount = 0; // debit_amount (payment received) - must be variable for bind_param
                $stmt->bind_param(
                    'sssddddssssi', // 11 types: s-s-s-d-d-d-d-s-s-s-s-i
                    $invoiceNumber,         // 1: s
                    $data['invoice_date'],  // 2: s
                    $data['due_date'],      // 3: s
                    $data['amount'],        // 4: d (total_amount)
                    $data['amount'],        // 5: d (balance_amount)
                    $debitAmount,           // 6: d (debit_amount)
                    $data['amount'],        // 7: d (credit_amount)
                    $data['currency'],      // 8: s
                    $data['entity_type'],   // 9: s
                    $data['entity_id'],     // 10: s
                    $data['description'],   // 11: s
                    $userId                 // 12: i
                );
                if (!$stmt->execute()) {
                    $error = 'Failed to insert into accounts_receivable (no customer_id): ' . $stmt->error;
                    error_log($error);
                    $log($error);
                } else {
                    $success = 'Successfully inserted invoice into accounts_receivable (no customer_id): ' . $invoiceNumber . ' (ID: ' . $conn->insert_id . ')';
                    error_log($success);
                    $log($success);
                }
                $stmt->close();
            } else {
                $error = 'Failed to prepare statement for accounts_receivable (no customer_id): ' . $conn->error;
                error_log($error);
                $log($error);
            }
        }
    }
}

if (!function_exists('createAccountsPayableFromEntity')) {
    function createAccountsPayableFromEntity($conn, $data) {
        global $entityTransactionsLogFile;
        $log = function($msg) use ($entityTransactionsLogFile) {
            error_log(date('c') . ' | ' . $msg . PHP_EOL, 3, $entityTransactionsLogFile);
        };
        
        $log('createAccountsPayableFromEntity called with data: ' . json_encode($data));
        
        // Create table if it doesn't exist
        if (!tableExists($conn, 'accounts_payable')) {
            $log('Creating accounts_payable table...');
            $conn->query("
                CREATE TABLE IF NOT EXISTS accounts_payable (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    bill_number VARCHAR(50) UNIQUE,
                    bill_date DATE NOT NULL,
                    due_date DATE NOT NULL,
                    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    status VARCHAR(20) DEFAULT 'Draft',
                    currency VARCHAR(3) DEFAULT 'SAR',
                    entity_type VARCHAR(50),
                    entity_id INT,
                    description TEXT,
                    vendor_id INT,
                    created_by INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_entity (entity_type, entity_id),
                    INDEX idx_status (status),
                    INDEX idx_bill_date (bill_date),
                    INDEX idx_vendor (vendor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        $billNumber = $data['reference'] ?: generateReferenceNumber($conn, 'accounts_payable', 'bill_number', 'BILL-');
        
        // Check if vendor_id column exists, adjust query accordingly
        $colCheck = $conn->query("SHOW COLUMNS FROM accounts_payable LIKE 'vendor_id'");
        $hasVendorId = $colCheck->num_rows > 0;
        
        if ($hasVendorId) {
            $stmt = $conn->prepare("
                INSERT INTO accounts_payable (
                    bill_number, bill_date, due_date,
                    total_amount, paid_amount, balance_amount,
                    debit_amount, credit_amount,
                    status, currency, entity_type, entity_id, description, created_by
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
            ");
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            if ($stmt) {
                // VALUES: (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
                // 12 placeholders: bill_number, bill_date, due_date, total_amount, balance_amount, debit_amount, credit_amount, currency, entity_type, entity_id, description, created_by
                $creditAmount = 0; // credit_amount (payment made) - must be variable for bind_param
                $stmt->bind_param(
                    'sssddddssssi', // 12 types for 12 parameters - correct match
                    $billNumber,           // 1: bill_number
                    $data['bill_date'],    // 2: bill_date
                    $data['due_date'],     // 3: due_date
                    $data['amount'],       // 4: total_amount
                    $data['amount'],       // 5: balance_amount
                    $data['amount'],       // 6: debit_amount
                    $creditAmount,         // 7: credit_amount
                    $data['currency'],     // 8: currency
                    $data['entity_type'],  // 9: entity_type
                    $data['entity_id'],    // 10: entity_id
                    $data['description'],  // 11: description
                    $userId                // 12: created_by
                );
                if (!$stmt->execute()) {
                    $error = 'Failed to insert into accounts_payable: ' . $stmt->error;
                    error_log($error);
                    $log($error);
                } else {
                    $success = 'Successfully inserted bill into accounts_payable: ' . $billNumber . ' (ID: ' . $conn->insert_id . ')';
                    error_log($success);
                    $log($success);
                }
                $stmt->close();
            } else {
                $error = 'Failed to prepare statement for accounts_payable: ' . $conn->error;
                error_log($error);
                $log($error);
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO accounts_payable (
                    bill_number, bill_date, due_date,
                    total_amount, paid_amount, balance_amount,
                    debit_amount, credit_amount,
                    status, currency, entity_type, entity_id, description, created_by
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
            ");
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            if ($stmt) {
                // VALUES: (?, ?, ?, ?, 0, ?, ?, ?, 'Posted', ?, ?, ?, ?, ?)
                // 12 placeholders: bill_number, bill_date, due_date, total_amount, balance_amount, debit_amount, credit_amount, currency, entity_type, entity_id, description, created_by
                $creditAmount = 0; // credit_amount (payment made) - must be variable for bind_param
                $stmt->bind_param(
                    'sssddddssssi', // 12 types for 12 parameters - correct match
                    $billNumber,           // 1: bill_number
                    $data['bill_date'],    // 2: bill_date
                    $data['due_date'],     // 3: due_date
                    $data['amount'],       // 4: total_amount
                    $data['amount'],       // 5: balance_amount
                    $data['amount'],       // 6: debit_amount
                    $creditAmount,         // 7: credit_amount
                    $data['currency'],     // 8: currency
                    $data['entity_type'],  // 9: entity_type
                    $data['entity_id'],    // 10: entity_id
                    $data['description'],  // 11: description
                    $userId                // 12: created_by
                );
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('createBankTransactionFromEntity')) {
    function createBankTransactionFromEntity($conn, $data) {
        // Create table if it doesn't exist
        if (!tableExists($conn, 'accounting_bank_transactions')) {
            $conn->query("
                CREATE TABLE IF NOT EXISTS accounting_bank_transactions (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    transaction_date DATE NOT NULL,
                    description TEXT,
                    transaction_type VARCHAR(50),
                    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    reference_number VARCHAR(50),
                    bank_account_name VARCHAR(100),
                    status VARCHAR(20) DEFAULT 'Posted',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_transaction_date (transaction_date),
                    INDEX idx_status (status),
                    INDEX idx_reference (reference_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        $type = 'Deposit';
        $debit = 0;
        $credit = $data['amount'];
        if (strcasecmp($data['transaction_type'], 'Expense') === 0) {
            $type = 'Withdrawal';
            $debit = $data['amount'];
            $credit = 0;
        }
        $stmt = $conn->prepare("
            INSERT INTO accounting_bank_transactions (
                transaction_date, description, transaction_type,
                amount, debit_amount, credit_amount,
                reference_number, bank_account_name, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Default', 'Posted')
        ");
        if ($stmt) {
            $stmt->bind_param(
                'sssddds',
                $data['transaction_date'],
                $data['description'],
                $type,
                $data['amount'],
                $debit,
                $credit,
                $data['reference']
            );
            $stmt->execute();
            $stmt->close();
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure currency and linking columns exist in financial_transactions table
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if ($tableCheck->num_rows > 0) {
        $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
        if ($columnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
        }
        // Add cost_center_id column
        $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'cost_center_id'");
        if ($columnCheck->num_rows === 0) {
            try {
                $conn->query("ALTER TABLE financial_transactions ADD COLUMN cost_center_id INT NULL AFTER currency, ADD INDEX idx_cost_center (cost_center_id)");
            } catch (Exception $e) {
                // Column might already exist, continue
            }
        }
        // Add bank_guarantee_id column
        $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'bank_guarantee_id'");
        if ($columnCheck->num_rows === 0) {
            try {
                $conn->query("ALTER TABLE financial_transactions ADD COLUMN bank_guarantee_id INT NULL AFTER cost_center_id, ADD INDEX idx_bank_guarantee (bank_guarantee_id)");
            } catch (Exception $e) {
                // Column might already exist, continue
            }
        }
        // Entity linking is already handled via entity_transactions table, but ensure direct linking columns exist too
        require_once __DIR__ . '/unified-entity-linking.php';
        ensureEntityLinkingColumns($conn, 'financial_transactions');
    }
    
    $logFile = __DIR__ . '/../../logs/entity-transactions.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $log = function($msg) use ($logFile) {
        error_log(date('c') . ' | ' . $msg . PHP_EOL, 3, $logFile);
    };

    $log("METHOD: {$method}");
    switch ($method) {
        case 'GET':
            // Get transactions and summary for specific entity
            $entityType = $_GET['entity_type'] ?? '';
            $entityId = intval($_GET['entity_id'] ?? 0);
            $transactionId = intval($_GET['id'] ?? 0);
            
            if ($transactionId > 0) {
                // Get single transaction
                enforceApiPermission('journal-entries', 'view');
                
                // Check if transaction_lines table exists and has account_id
                $hasTransactionLines = tableExists($conn, 'transaction_lines');
                $accountIdField = '';
                $accountJoin = '';
                if ($hasTransactionLines) {
                    // Check if account_id column exists in transaction_lines
                    $colCheck = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'account_id'");
                    if ($colCheck && $colCheck->num_rows > 0) {
                        // Check if line_type column exists
                        $lineTypeCheck = $conn->query("SHOW COLUMNS FROM transaction_lines LIKE 'line_type'");
                        if ($lineTypeCheck && $lineTypeCheck->num_rows > 0) {
                            $accountIdField = ', tl.account_id';
                            $accountJoin = 'LEFT JOIN transaction_lines tl ON ft.id = tl.transaction_id AND (tl.line_type = "main" OR tl.line_type IS NULL)';
                        } else {
                            $accountIdField = ', tl.account_id';
                            $accountJoin = 'LEFT JOIN transaction_lines tl ON ft.id = tl.transaction_id';
                        }
                    }
                }
                
                // Check if entity_transactions has entry_type column
                $etEntryTypeCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'entry_type'");
                $hasEntryType = $etEntryTypeCheck && $etEntryTypeCheck->num_rows > 0;
                
                $entryTypeField = $hasEntryType ? 'COALESCE(et.entry_type, ft.transaction_type, "Manual")' : 'COALESCE(ft.transaction_type, "Manual")';
                
                $query = "
                    SELECT 
                        et.*,
                        COALESCE(et.debit_amount, ft.debit_amount, 0) as debit_amount,
                        COALESCE(et.credit_amount, ft.credit_amount, 0) as credit_amount,
                        COALESCE(et.debit_amount, ft.debit_amount, 0) as debit,
                        COALESCE(et.credit_amount, ft.credit_amount, 0) as credit,
                        ft.transaction_date,
                        ft.description,
                        ft.reference_number,
                        ft.total_amount,
                        CASE 
                            WHEN ft.currency IS NULL OR ft.currency = '' OR ft.currency = '0' THEN 'SAR'
                            ELSE ft.currency
                        END as currency,
                        ft.transaction_type,
                        ({$entryTypeField}) as entry_type,
                        ft.status,
                        ft.created_at,
                        ft.updated_at,
                        u.username as created_by_name" . 
                        ($accountIdField ? $accountIdField : '') . ",
                        CASE 
                            WHEN et.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = et.entity_id LIMIT 1)
                            WHEN et.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = et.entity_id LIMIT 1)
                            WHEN et.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = et.entity_id LIMIT 1)
                            WHEN et.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = et.entity_id LIMIT 1)
                            WHEN et.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = et.entity_id LIMIT 1)
                            ELSE NULL
                        END as entity_name
                    FROM entity_transactions et
                    INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
                    LEFT JOIN users u ON ft.created_by = u.user_id" . 
                        ($accountJoin ? ' ' . $accountJoin : '') . "
                    WHERE et.id = ?
                ";
                
                $log('Query: ' . $query);
                $log('Transaction ID: ' . $transactionId);
                
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    $error = 'Failed to prepare query: ' . $conn->error . ' | Query: ' . $query;
                    $log($error);
                    error_log($error);
                    send_json_response([
                        'success' => false,
                        'error' => $error,
                        'message' => 'Database query preparation failed'
                    ], 500);
                    exit;
                }
                
                $stmt->bind_param('i', $transactionId);
                if (!$stmt->execute()) {
                    $error = 'Failed to execute query: ' . $stmt->error;
                    $log($error);
                    error_log($error);
                    $stmt->close();
                    send_json_response([
                        'success' => false,
                        'error' => $error,
                        'message' => 'Database query execution failed'
                    ], 500);
                    exit;
                }
                
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    $stmt->close();
                    send_json_response([
                        'success' => false,
                        'message' => 'Transaction not found'
                    ], 404);
                    exit;
                }
                
                $transaction = $result->fetch_assoc();
                $stmt->close();
                
                // Ensure entry_type is valid (Manual, Automatic, Recurring, Adjustment, Reversal)
                // If entry_type is empty or is a transaction_type value (Expense, Income, Transfer), default to Manual
                $validEntryTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                $entryType = $transaction['entry_type'] ?? 'Manual';
                if (empty($entryType) || !in_array($entryType, $validEntryTypes)) {
                    // If entry_type is empty or is a transaction_type, default to Manual
                    $entryType = 'Manual';
                }
                $transaction['entry_type'] = $entryType;
                
                // Log the actual values being returned
                $log('get single success. Debit: ' . ($transaction['debit_amount'] ?? 'NULL') . ', Credit: ' . ($transaction['credit_amount'] ?? 'NULL') . ', Debit (alias): ' . ($transaction['debit'] ?? 'NULL') . ', Credit (alias): ' . ($transaction['credit'] ?? 'NULL') . ', Entry Type: ' . $entryType . ', Currency: ' . ($transaction['currency'] ?? 'NULL'));
                
                // Format dates for display
                $transaction = formatDatesInArray($transaction);
                send_json_response([
                    'success' => true,
                    'transaction' => $transaction
                ]);
            } else {
                // Get all transactions for entity
                if (empty($entityType) || $entityId <= 0) {
                throw new Exception('Entity type and ID are required');
            }
            
                enforceApiPermission('journal-entries', 'view');
                
                // Normalize entity_type to lowercase for consistency
                $entityType = strtolower(trim($entityType));
                
                // Get transactions (case-insensitive entity_type matching)
            $stmt = $conn->prepare("
                SELECT 
                        et.id,
                        et.transaction_id,
                        et.entity_type,
                        et.entity_id,
                        et.category,
                        COALESCE(et.debit_amount, 0) as debit_amount,
                        COALESCE(et.credit_amount, 0) as credit_amount,
                        ft.transaction_date,
                        ft.description,
                        ft.reference_number,
                        ft.total_amount,
                        COALESCE(ft.currency, 'SAR') as currency,
                        ft.transaction_type,
                        ft.status,
                        ft.created_at,
                    u.username as created_by_name
                    FROM entity_transactions et
                    INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
                    LEFT JOIN users u ON ft.created_by = u.user_id
                    WHERE LOWER(et.entity_type) = ? AND et.entity_id = ?
                ORDER BY ft.transaction_date DESC, ft.created_at DESC, et.id DESC
                    LIMIT 1000
            ");
            $stmt->bind_param('si', $entityType, $entityId);
            $stmt->execute();
            $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Format dates for display
            foreach ($transactions as &$trans) {
                $trans = formatDatesInArray($trans);
            }
            unset($trans);
            
            // Get summary data
                $summary = getEntitySummary($conn, $entityType, $entityId);
            
            $log('list fetched count=' . count($transactions));
            send_json_response([
                'success' => true,
                'transactions' => $transactions,
                'summary' => $summary
            ]);
            }
            break;
            
        case 'POST':
            // Create new transaction for entity
            enforceApiPermission('journal-entries', 'create');
            
            // Check and create tables if they don't exist
            $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
            if ($tableCheck->num_rows === 0) {
                // Auto-create financial_transactions table
                $sql = "
                    CREATE TABLE IF NOT EXISTS financial_transactions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        transaction_date DATE NOT NULL,
                        description VARCHAR(255) NOT NULL,
                        reference_number VARCHAR(50),
                        total_amount DECIMAL(15,2) NOT NULL,
                        currency VARCHAR(3) DEFAULT 'SAR',
                        debit_amount DECIMAL(15,2) DEFAULT 0.00,
                        credit_amount DECIMAL(15,2) DEFAULT 0.00,
                        transaction_type ENUM('Income', 'Expense', 'Transfer', 'Adjustment') NOT NULL,
                        status ENUM('Draft', 'Approved', 'Posted') DEFAULT 'Posted',
                        created_by INT NOT NULL DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_transaction_date (transaction_date),
                        INDEX idx_transaction_type (transaction_type),
                        INDEX idx_status (status),
                        INDEX idx_created_by (created_by)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ";
                if (!$conn->query($sql)) {
                    throw new Exception('Failed to create financial_transactions table: ' . $conn->error);
                }
            } else {
                // Check if currency column exists, add it if not
                $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
                if ($columnCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
                }
                // Ensure debit_amount / credit_amount columns exist
                $debitCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'debit_amount'");
                if ($debitCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE financial_transactions ADD COLUMN debit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER total_amount");
                }
                $creditCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'credit_amount'");
                if ($creditCheck->num_rows === 0) {
                    $conn->query("ALTER TABLE financial_transactions ADD COLUMN credit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER debit_amount");
                }
            }
            
            $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
            if ($tableCheck->num_rows === 0) {
                // Auto-create entity_transactions table
                $sql = "
                    CREATE TABLE IF NOT EXISTS entity_transactions (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        transaction_id INT NOT NULL,
                        entity_type VARCHAR(50) NOT NULL,
                        entity_id INT NOT NULL,
                        category VARCHAR(50) DEFAULT 'other',
                        debit_amount DECIMAL(15,2) DEFAULT 0.00,
                        credit_amount DECIMAL(15,2) DEFAULT 0.00,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
                        INDEX idx_entity (entity_type, entity_id),
                        INDEX idx_transaction (transaction_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ";
                if (!$conn->query($sql)) {
                    throw new Exception('Failed to create entity_transactions table: ' . $conn->error);
                }
            }

            // Ensure entity_transactions has debit/credit columns
            $etDebitCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'debit_amount'");
            if ($etDebitCheck->num_rows === 0) {
                $conn->query("ALTER TABLE entity_transactions ADD COLUMN debit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER category");
            }
            $etCreditCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'credit_amount'");
            if ($etCreditCheck->num_rows === 0) {
                $conn->query("ALTER TABLE entity_transactions ADD COLUMN credit_amount DECIMAL(15,2) DEFAULT 0.00 AFTER debit_amount");
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $log('POST payload: ' . json_encode($data));
            $log('POST - entry_type in request: ' . ($data['entry_type'] ?? 'NOT PROVIDED'));
            $log('POST - transaction_type in request: ' . ($data['transaction_type'] ?? 'NOT PROVIDED'));
            $log('POST - type in request: ' . ($data['type'] ?? 'NOT PROVIDED'));
            
            // Validate required fields
            if (empty($data['entity_type']) || empty($data['entity_id'])) {
                throw new Exception('Entity type and ID are required');
            }
            // Validate required fields - amount can come from 'amount' or calculated from debit/credit
            $hasAmount = !empty($data['amount']) || !empty($data['total_amount']);
            $hasDebit = !empty($data['debit']) || !empty($data['debit_amount']);
            $hasCredit = !empty($data['credit']) || !empty($data['credit_amount']);
            $hasDebitCredit = $hasDebit || $hasCredit; // Either debit OR credit is acceptable
            
            if (empty($data['transaction_date']) || empty($data['description'])) {
                throw new Exception('Transaction date and description are required');
            }
            
            // Convert transaction_date from MM/DD/YYYY to YYYY-MM-DD for database
            $data['transaction_date'] = formatDateForDatabase($data['transaction_date']);
            
            if (!$hasAmount && !$hasDebitCredit) {
                throw new Exception('Transaction amount is required. Please provide amount, total_amount, or debit/credit values.');
            }
            
            $conn->begin_transaction();
            
            try {
                // Get entry_type (Manual, Automatic, Recurring, etc.) - separate from transaction_type
                $entryType = $data['entry_type'] ?? null;
                $log('POST - Raw entry_type from data: ' . ($entryType ?? 'NULL') . ' (type: ' . gettype($entryType) . ')');
                
                if ($entryType) {
                    $entryType = ucfirst(trim($entryType));
                    $log('POST - Trimmed entry_type: ' . $entryType);
                    // Validate entry_type
                    $validEntryTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                    if (!in_array($entryType, $validEntryTypes)) {
                        $log('POST - WARNING: entry_type "' . $entryType . '" is not valid, defaulting to Manual');
                        $entryType = 'Manual';
                    } else {
                        $log('POST - entry_type "' . $entryType . '" is valid');
                    }
                } else {
                    $log('POST - entry_type not provided or empty, defaulting to Manual');
                    $entryType = 'Manual'; // Default
                }
                
                // Get transaction_type (Expense, Income, Transfer) - for backward compatibility
                $transactionType = null;
                if (isset($data['transaction_type']) && !empty($data['transaction_type'])) {
                    $transactionType = ucfirst(trim($data['transaction_type']));
                } else {
                    // Default based on debit/credit if provided, otherwise default to Expense
                    $transactionType = 'Expense';
                }
                
                // All new transactions must go through approval - ALWAYS set status to Draft
                // Ignore any status sent from frontend - workflow requires approval first
                $status = 'Draft';
                $userId = $_SESSION['user_id'] ?? 1;
                $referenceNumber = $data['reference_number'] ?? null;
                
                // Ensure currency is valid (not empty, not '0', default to 'SAR')
                $currencyInput = $data['currency'] ?? 'SAR';
                $currency = (!empty($currencyInput) && $currencyInput !== '0' && $currencyInput !== '') 
                    ? strtoupper($currencyInput) 
                    : 'SAR';
                
                    // Auto-generate reference number if not provided or empty
                if (empty($referenceNumber)) {
                    // Get the highest reference number (by numeric value, not just latest by ID)
                    // Format: ETX + 5 digits (e.g., ETX00001)
                    $refStmt = $conn->prepare("SELECT reference_number FROM financial_transactions WHERE reference_number IS NOT NULL AND reference_number LIKE 'ETX%' ORDER BY CAST(SUBSTRING(reference_number, 4) AS UNSIGNED) DESC LIMIT 1");
                    $refStmt->execute();
                    $refResult = $refStmt->get_result();
                    
                    if ($refResult->num_rows > 0) {
                        $lastRef = $refResult->fetch_assoc()['reference_number'];
                        // Extract number from ETX##### format
                        if (preg_match('/ETX(\d+)/', $lastRef, $matches)) {
                            $nextNum = intval($matches[1]) + 1;
                            $referenceNumber = 'ETX' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
                        } else {
                            $referenceNumber = 'ETX00001';
                        }
                    } else {
                        $referenceNumber = 'ETX00001';
                    }
                }
                
                // Get debit and credit from request data (preferred) or calculate from amount
                $debitAmount = isset($data['debit']) && $data['debit'] !== '' ? floatval($data['debit']) : (isset($data['debit_amount']) && $data['debit_amount'] !== '' ? floatval($data['debit_amount']) : 0);
                $creditAmount = isset($data['credit']) && $data['credit'] !== '' ? floatval($data['credit']) : (isset($data['credit_amount']) && $data['credit_amount'] !== '' ? floatval($data['credit_amount']) : 0);
                
                // If both debit and credit are 0, calculate from amount and transaction_type
                if ($debitAmount == 0 && $creditAmount == 0) {
                    $amount = floatval($data['amount'] ?? 0);
                    if ($amount > 0) {
                        if ($transactionType === 'Expense') {
                            $debitAmount = $amount;
                            $creditAmount = 0;
                        } else if ($transactionType === 'Income') {
                            $debitAmount = 0;
                            $creditAmount = $amount;
                        } else {
                            // For Transfer or other types, use amount as the larger value
                            $debitAmount = $amount;
                            $creditAmount = $amount;
                        }
                    }
                }
                
                // Calculate total amount - ensure it's never null or zero if required
                $totalAmount = 0;
                if (isset($data['total_amount']) && $data['total_amount'] !== '' && $data['total_amount'] !== null) {
                    $totalAmount = floatval($data['total_amount']);
                } elseif (isset($data['amount']) && $data['amount'] !== '' && $data['amount'] !== null) {
                    $totalAmount = floatval($data['amount']);
                } else {
                    $totalAmount = max($debitAmount, $creditAmount);
                }
                
                // Ensure total_amount is never null or zero - if all are zero/null, throw error
                if ($totalAmount <= 0) {
                    throw new Exception('Transaction amount is required. Please provide amount, total_amount, or debit/credit values.');
                }
                
                $log('POST - entry_type: ' . $entryType . ', transaction_type: ' . $transactionType . ', currency: ' . $currency . ', debit: ' . $debitAmount . ', credit: ' . $creditAmount);
                
                // Check if financial_transactions has entry_type column
                $ftEntryTypeCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'entry_type'");
                $ftHasEntryType = $ftEntryTypeCheck && $ftEntryTypeCheck->num_rows > 0;
                $log('POST - financial_transactions has entry_type column: ' . ($ftHasEntryType ? 'YES' : 'NO'));
                
                // Build INSERT query dynamically to include entry_type if column exists
                $ftInsertFields = [
                    'transaction_date',
                    'description',
                    'reference_number',
                    'total_amount',
                    'debit_amount',
                    'credit_amount',
                    'currency',
                    'transaction_type',
                    'status',
                    'created_by'
                ];
                $ftInsertValues = [
                    $data['transaction_date'],
                    $data['description'],
                    $referenceNumber,
                    $totalAmount,
                    $debitAmount,
                    $creditAmount,
                    $currency,
                    $transactionType,
                    $status,
                    $userId
                ];
                $ftInsertTypes = 'sssddddssi';
                
                // Add entry_type if column exists
                if ($ftHasEntryType) {
                    $ftInsertFields[] = 'entry_type';
                    $ftInsertValues[] = $entryType;
                    $ftInsertTypes .= 's';
                    $log('POST - Adding entry_type to financial_transactions INSERT: ' . $entryType);
                }
                
                $ftInsertQuery = "INSERT INTO financial_transactions (" . implode(', ', $ftInsertFields) . ") VALUES (" . implode(', ', array_fill(0, count($ftInsertFields), '?')) . ")";
                $log('POST - financial_transactions INSERT query: ' . $ftInsertQuery);
                $log('POST - entry_type: ' . $entryType . ', transaction_type: ' . $transactionType . ', currency: ' . $currency . ', debit: ' . $debitAmount . ', credit: ' . $creditAmount);
                
                $stmt = $conn->prepare($ftInsertQuery);
                if (!$stmt) {
                    $log('ERROR: Failed to prepare financial_transactions INSERT: ' . $conn->error);
                    throw new Exception('Failed to prepare financial_transactions insert: ' . $conn->error);
                }
                
                $stmt->bind_param($ftInsertTypes, ...$ftInsertValues);
                $stmt->execute();
                $transactionId = $conn->insert_id;
                
                // Check if entity_transactions has entry_type column
                $etEntryTypeCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'entry_type'");
                $etHasEntryType = $etEntryTypeCheck && $etEntryTypeCheck->num_rows > 0;
                $log('POST - entity_transactions has entry_type column: ' . ($etHasEntryType ? 'YES' : 'NO'));
                
                // Build INSERT query dynamically to include entry_type if column exists
                $etInsertFields = [
                    'transaction_id',
                    'entity_type',
                    'entity_id',
                    'category',
                    'debit_amount',
                    'credit_amount'
                ];
                $category = $data['category'] ?? 'other';
                // Normalize entity_type to lowercase for consistency
                $entityType = strtolower(trim($data['entity_type']));
                $entityId = intval($data['entity_id']);
                
                $etInsertValues = [
                    $transactionId,
                    $entityType,
                    $entityId,
                    $category,
                    $debitAmount,
                    $creditAmount
                ];
                $etInsertTypes = 'isisdd';
                
                // Add entry_type if column exists
                if ($etHasEntryType) {
                    $etInsertFields[] = 'entry_type';
                    $etInsertValues[] = $entryType;
                    $etInsertTypes .= 's';
                    $log('POST - Adding entry_type to entity_transactions INSERT: ' . $entryType);
                }
                
                $etInsertQuery = "INSERT INTO entity_transactions (" . implode(', ', $etInsertFields) . ") VALUES (" . implode(', ', array_fill(0, count($etInsertFields), '?')) . ")";
                $log('POST - entity_transactions INSERT query: ' . $etInsertQuery);
                
                $stmt = $conn->prepare($etInsertQuery);
                $stmt->bind_param($etInsertTypes, ...$etInsertValues);
                $stmt->execute();
                $entityTransactionId = $conn->insert_id;
                
                // AUTO-LINK: Automatically link transaction to appropriate account
                $autoLinkSuccess = false;
                $tlCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
                if ($tlCheck->num_rows > 0) {
                    // Get account mappings
                    $accountMap = [];
                    $accountQuery = $conn->query("SELECT id, account_code FROM financial_accounts WHERE is_active = 1");
                    while ($accRow = $accountQuery->fetch_assoc()) {
                        $accountMap[$accRow['account_code']] = $accRow['id'];
                    }
                    
                    if (!empty($accountMap)) {
                        $accountId = null;
                        
                        // Auto-determine account based on entity type and transaction type
                        if ($transactionType === 'Income') {
                            if ($entityType === 'agent') {
                                $accountId = $accountMap['4300'] ?? $accountMap['4000'] ?? null;
                            } elseif ($entityType === 'subagent') {
                                $accountId = $accountMap['4400'] ?? $accountMap['4000'] ?? null;
                            } elseif ($entityType === 'worker') {
                                $accountId = $accountMap['4500'] ?? $accountMap['4000'] ?? null;
                            } elseif ($entityType === 'hr') {
                                $accountId = $accountMap['4600'] ?? $accountMap['4000'] ?? null;
                            } elseif ($entityType === 'accounting') {
                                $accountId = $accountMap['4700'] ?? $accountMap['4000'] ?? null;
                            } else {
                                $accountId = $accountMap['4100'] ?? $accountMap['4000'] ?? null;
                            }
                        } elseif ($transactionType === 'Expense') {
                            if ($entityType === 'agent') {
                                $accountId = $accountMap['5500'] ?? $accountMap['5000'] ?? null;
                            } elseif ($entityType === 'subagent') {
                                $accountId = $accountMap['5600'] ?? $accountMap['5000'] ?? null;
                            } elseif ($entityType === 'worker') {
                                $accountId = $accountMap['5700'] ?? $accountMap['5000'] ?? null;
                            } elseif ($entityType === 'hr') {
                                $accountId = $accountMap['5800'] ?? $accountMap['5000'] ?? null;
                            } elseif ($entityType === 'accounting') {
                                $accountId = $accountMap['5900'] ?? $accountMap['5000'] ?? null;
                            } else {
                                $accountId = $accountMap['5100'] ?? $accountMap['5000'] ?? null;
                            }
                        }
                        
                        if ($accountId) {
                            $debitAmount = $transactionType === 'Expense' ? $amount : 0;
                            $creditAmount = $transactionType === 'Income' ? $amount : 0;
                            
                            $linkStmt = $conn->prepare("
                                INSERT INTO transaction_lines (transaction_id, account_id, debit_amount, credit_amount, description)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $linkDesc = "Auto-linked ({$entityType}): " . substr($data['description'], 0, 200);
                            $linkStmt->bind_param('iidds', $transactionId, $accountId, $debitAmount, $creditAmount, $linkDesc);
                            if ($linkStmt->execute()) {
                                $autoLinkSuccess = true;
                            }
                            $linkStmt->close();
                        }
                    }
                }
                
                // AUTO JOURNAL ENTRY: Create double-entry bookkeeping journal entry
                $journalResult = ['success' => false, 'message' => 'Journal entry creation skipped'];
                try {
                    if (file_exists(__DIR__ . '/auto-journal-entry.php')) {
                        require_once __DIR__ . '/auto-journal-entry.php';
                        if (function_exists('createAutomaticJournalEntry')) {
                            $journalResult = createAutomaticJournalEntry(
                                $conn, 
                                $transactionId, 
                                $entityType, 
                                $entityId, 
                                $transactionType, 
                                $amount, 
                                $data['description'], 
                                $data['transaction_date']
                            );
                        }
                    }
                } catch (Exception $je) {
                    $log('Journal entry creation failed: ' . $je->getMessage());
                    // Don't fail the transaction if journal entry fails
                }
                
                // Update financial_transactions debit/credit columns
                $updateFT = $conn->prepare("
                    UPDATE financial_transactions 
                    SET debit_amount = ?, credit_amount = ?
                    WHERE id = ?
                ");
                if ($updateFT) {
                    $ftDebit = $transactionType === 'Expense' ? $amount : 0;
                    $ftCredit = $transactionType === 'Income' ? $amount : 0;
                    $updateFT->bind_param('ddi', $ftDebit, $ftCredit, $transactionId);
                    if (!$updateFT->execute()) {
                        $log('Warning: Failed to update financial_transactions debit/credit: ' . $updateFT->error);
                        error_log('Failed to update financial_transactions debit/credit: ' . $updateFT->error);
                    }
                    $updateFT->close();
                } else {
                    $log('Warning: Failed to prepare financial_transactions update: ' . $conn->error);
                    error_log('Failed to prepare financial_transactions update: ' . $conn->error);
                }
                
                // AUTO TOTALS: Update entity totals
                $entityTotals = null;
                try {
                    if (file_exists(__DIR__ . '/auto-journal-entry.php')) {
                        require_once __DIR__ . '/auto-journal-entry.php';
                        if (function_exists('updateEntityTotals')) {
                            $entityTotals = updateEntityTotals($conn, $entityType, $entityId);
                        }
                    }
                } catch (Exception $te) {
                    $log('Entity totals update failed: ' . $te->getMessage());
                    // Don't fail the transaction if totals update fails
                }

                // Mirror transaction into Accounts Receivable / Payable / Banking tables
                try {
                    if ($transactionType === 'Income') {
                        $log('Mirroring Income transaction to Accounts Receivable...');
                        createAccountsReceivableFromEntity($conn, [
                            'invoice_date' => $data['transaction_date'],
                            'due_date' => $data['transaction_date'],
                            'amount' => $amount,
                            'currency' => $currency,
                            'reference' => $referenceNumber,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'description' => $data['description']
                        ]);
                        $log('Successfully mirrored to Accounts Receivable');
                    } elseif ($transactionType === 'Expense') {
                        $log('Mirroring Expense transaction to Accounts Payable...');
                        createAccountsPayableFromEntity($conn, [
                            'bill_date' => $data['transaction_date'],
                            'due_date' => $data['transaction_date'],
                            'amount' => $amount,
                            'currency' => $currency,
                            'reference' => $referenceNumber,
                            'entity_type' => $entityType,
                            'entity_id' => $entityId,
                            'description' => $data['description']
                        ]);
                        $log('Successfully mirrored to Accounts Payable');
                    }

                    $log('Mirroring transaction to Banking...');
                    createBankTransactionFromEntity($conn, [
                        'transaction_date' => $data['transaction_date'],
                        'description' => $data['description'],
                        'reference' => $referenceNumber,
                        'transaction_type' => $transactionType,
                        'amount' => $amount
                    ]);
                    $log('Successfully mirrored to Banking');
                } catch (Exception $mirrorError) {
                    $log('Mirroring failed (non-fatal): ' . $mirrorError->getMessage());
                    // Don't fail the transaction if mirroring fails - it's not critical
                }
                
                // Create entry approval record for this transaction (Draft transactions require approval)
                $approvalTableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
                if ($approvalTableCheck && $approvalTableCheck->num_rows > 0) {
                    // Check if entry_approval has the necessary columns
                    $entityTypeCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_type'");
                    $hasEntityType = $entityTypeCheck && $entityTypeCheck->num_rows > 0;
                    $entityIdCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'entity_id'");
                    $hasEntityId = $entityIdCheck && $entityIdCheck->num_rows > 0;
                    
                    $approvalEntryNumber = 'APP-' . ($referenceNumber ?: ('TXN-' . str_pad($transactionId, 8, '0', STR_PAD_LEFT)));
                    $approvalAmount = $totalAmount; // Use totalAmount calculated earlier, not undefined $amount
                    $approvalDate = $data['transaction_date']; // Already converted to YYYY-MM-DD above // Already converted to YYYY-MM-DD above
                    $approvalDescription = $data['description'] ?? '';
                    
                    if ($hasEntityType && $hasEntityId) {
                        // Use entity linking
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, entity_type, entity_id, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                        ");
                        $approvalStmt->bind_param('sssdssii', $approvalEntryNumber, $approvalDate, $approvalDescription, $approvalAmount, $currency, $entityType, $entityId, $userId);
                    } else {
                        // Basic approval record without entity linking
                        $approvalStmt = $conn->prepare("
                            INSERT INTO entry_approval (entry_number, entry_date, description, amount, currency, status, created_by)
                            VALUES (?, ?, ?, ?, ?, 'pending', ?)
                        ");
                        $approvalStmt->bind_param('sssddsi', $approvalEntryNumber, $approvalDate, $approvalDescription, $approvalAmount, $currency, $userId);
                    }
                    
                    if (isset($approvalStmt) && !$approvalStmt->execute()) {
                        error_log("Failed to create entry approval for transaction: " . ($approvalStmt->error ?? 'Unknown error'));
                        // Don't fail the transaction, but log the error
                    } else if (isset($approvalStmt)) {
                        $approvalId = $conn->insert_id;
                        error_log("Entry approval created successfully for transaction - Approval ID: $approvalId, Transaction ID: $transactionId");
                    }
                    if (isset($approvalStmt)) {
                        $approvalStmt->close();
                    }
                }
                
                $conn->commit();
                
                $log('POST success transId=' . $transactionId);
                send_json_response([
                    'success' => true,
                    'message' => 'Transaction added successfully' . 
                        ($autoLinkSuccess ? ' and auto-linked to account' : '') . 
                        ($journalResult['success'] ? ' with journal entry created' : ''),
                    'transaction_id' => $transactionId,
                    'entity_transaction_id' => $entityTransactionId,
                    'auto_linked' => $autoLinkSuccess,
                    'journal_entry' => $journalResult,
                    'entity_totals' => $entityTotals
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                $log('POST exception: ' . $e->getMessage());
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update transaction
            enforceApiPermission('journal-entries', 'update');
            
            $data = json_decode(file_get_contents('php://input'), true);
            // Try to get ID from URL parameter first, then from request body
            $transactionId = isset($_GET['id']) ? intval($_GET['id']) : (isset($data['id']) ? intval($data['id']) : 0);
            
            if ($transactionId <= 0) {
                throw new Exception('Transaction ID is required');
            }
            
            $conn->begin_transaction();
            
            try {
                // Get entity transaction to verify ownership
                $stmt = $conn->prepare("
                    SELECT transaction_id, entity_type, entity_id 
                    FROM entity_transactions 
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                $entityTrans = $stmt->get_result()->fetch_assoc();
                
                if (!$entityTrans) {
                    throw new Exception('Transaction not found');
                }
                
                // Get debit and credit from request data
                $debitAmount = isset($data['debit']) && $data['debit'] !== '' ? floatval($data['debit']) : (isset($data['debit_amount']) && $data['debit_amount'] !== '' ? floatval($data['debit_amount']) : 0);
                $creditAmount = isset($data['credit']) && $data['credit'] !== '' ? floatval($data['credit']) : (isset($data['credit_amount']) && $data['credit_amount'] !== '' ? floatval($data['credit_amount']) : 0);
                
                // Get total_amount
                $totalAmount = isset($data['total_amount']) && $data['total_amount'] !== '' ? floatval($data['total_amount']) : (isset($data['amount']) && $data['amount'] !== '' ? floatval($data['amount']) : 0);
                
                // Get entry_type (Manual, Automatic, Recurring, etc.) - separate from transaction_type
                $entryType = $data['entry_type'] ?? null;
                if ($entryType) {
                    $entryType = ucfirst(trim($entryType));
                    // Validate entry_type
                    $validEntryTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                    if (!in_array($entryType, $validEntryTypes)) {
                        $entryType = 'Manual';
                    }
                }
                
                // Get transaction_type (Expense, Income, Transfer) - for backward compatibility
                // If entry_type is provided, use it to determine transaction_type if needed
                $transactionType = null;
                if (isset($data['transaction_type']) && !empty($data['transaction_type'])) {
                    $transactionType = ucfirst(trim($data['transaction_type']));
                } else if ($entryType) {
                    // If only entry_type is provided, default transaction_type based on debit/credit
                    $transactionType = ($debitAmount > 0) ? 'Expense' : (($creditAmount > 0) ? 'Income' : 'Expense');
                } else {
                    $transactionType = 'Expense'; // Default fallback
                }
                
                $log('Transaction type - entry_type: ' . ($entryType ?? 'not provided') . ', transaction_type: ' . $transactionType);
                
                // ONLY calculate debit/credit from transaction_type if BOTH are 0 AND total_amount > 0
                // This prevents overwriting user-entered values
                if ($debitAmount == 0 && $creditAmount == 0 && $totalAmount > 0) {
                    // Calculate from transaction_type if not provided
                    if ($transactionType === 'Expense') {
                        $debitAmount = $totalAmount;
                        $creditAmount = 0;
                    } else if ($transactionType === 'Income') {
                        $debitAmount = 0;
                        $creditAmount = $totalAmount;
                    } else {
                        // For Transfer or other types, use total_amount as the larger value
                        $debitAmount = $totalAmount;
                        $creditAmount = $totalAmount;
                    }
                    $log('Calculated debit/credit from transaction_type. Debit: ' . $debitAmount . ', Credit: ' . $creditAmount);
                } else if ($totalAmount == 0 && ($debitAmount > 0 || $creditAmount > 0)) {
                    // Calculate total_amount from debit/credit if not provided
                    $totalAmount = max($debitAmount, $creditAmount);
                    $log('Calculated total_amount from debit/credit. Total: ' . $totalAmount);
                } else {
                    $log('Using provided values. Debit: ' . $debitAmount . ', Credit: ' . $creditAmount . ', Total: ' . $totalAmount);
                }
                
                $referenceNumber = $data['reference_number'] ?? null;
                // Ensure currency is valid (not empty, not '0', default to 'SAR')
                $currencyInput = $data['currency'] ?? 'SAR';
                $currency = (!empty($currencyInput) && $currencyInput !== '0' && $currencyInput !== '') 
                    ? strtoupper($currencyInput) 
                    : 'SAR';
                $status = $data['status'] ?? 'Posted';
                $transactionId = $entityTrans['transaction_id'];
                
                // Check if financial_transactions has entry_type column
                $ftEntryTypeCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'entry_type'");
                $ftHasEntryType = $ftEntryTypeCheck && $ftEntryTypeCheck->num_rows > 0;
                $log('financial_transactions has entry_type column: ' . ($ftHasEntryType ? 'YES' : 'NO'));
                
                // Update financial_transactions with debit/credit, currency, and entry_type
                $log('Updating financial_transactions. ID: ' . $transactionId . ', Debit: ' . $debitAmount . ', Credit: ' . $creditAmount . ', Total: ' . $totalAmount . ', Currency: ' . $currency . ', Entry Type: ' . ($entryType ?? 'not provided'));
                
                // Build UPDATE query dynamically to include entry_type if column exists
                $ftUpdateFields = [
                    'transaction_date = ?',
                    'description = ?',
                    'reference_number = ?',
                    'total_amount = ?',
                    'debit_amount = ?',
                    'credit_amount = ?',
                    'currency = ?',
                    'transaction_type = ?',
                    'status = ?'
                ];
                // Convert transaction_date from MM/DD/YYYY to YYYY-MM-DD for database if provided
                $transactionDate = isset($data['transaction_date']) ? formatDateForDatabase($data['transaction_date']) : $entityTrans['transaction_date'];
                
                $ftUpdateValues = [
                $transactionDate,
                $data['description'],
                    $referenceNumber,
                    $totalAmount,
                    $debitAmount,
                    $creditAmount,
                    $currency,
                    $transactionType,
                    $status
                ];
                $ftUpdateTypes = 'sssddddss';
                
                // Add entry_type if column exists and value is provided
                if ($ftHasEntryType && $entryType) {
                    $ftUpdateFields[] = 'entry_type = ?';
                    $ftUpdateValues[] = $entryType;
                    $ftUpdateTypes .= 's';
                    $log('Adding entry_type to financial_transactions UPDATE: ' . $entryType);
                }
                
                // Add WHERE clause
                $ftUpdateTypes .= 'i';
                $ftUpdateValues[] = $transactionId;
                
                $ftUpdateQuery = "UPDATE financial_transactions SET " . implode(', ', $ftUpdateFields) . " WHERE id = ?";
                $log('financial_transactions UPDATE query: ' . $ftUpdateQuery);
                
                $stmt = $conn->prepare($ftUpdateQuery);
                if (!$stmt) {
                    $log('ERROR: Failed to prepare financial_transactions UPDATE: ' . $conn->error);
                    throw new Exception('Failed to prepare financial_transactions update: ' . $conn->error);
                }
                
                $stmt->bind_param($ftUpdateTypes, ...$ftUpdateValues);
                
                if (!$stmt->execute()) {
                    $log('ERROR: Failed to execute financial_transactions UPDATE: ' . $stmt->error);
                    throw new Exception('Failed to execute financial_transactions update: ' . $stmt->error);
                }
                
                $affectedRows = $stmt->affected_rows;
                $log('financial_transactions UPDATE affected rows: ' . $affectedRows);
                $stmt->close();
                
                // Check if entity_transactions has entry_type column
                $etEntryTypeCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'entry_type'");
                $etHasEntryType = $etEntryTypeCheck && $etEntryTypeCheck->num_rows > 0;
                $log('entity_transactions has entry_type column: ' . ($etHasEntryType ? 'YES' : 'NO'));
                
                // Update entity_transactions with debit/credit, entity_type, entity_id, and entry_type
                $log('Updating entity_transactions. ID: ' . $transactionId . ', Debit: ' . $debitAmount . ', Credit: ' . $creditAmount);
                
                // Get entity_type and entity_id from request data
                $entityType = null;
                $entityId = null;
                if (isset($data['entity_type']) && !empty($data['entity_type'])) {
                    $entityType = strtolower(trim($data['entity_type']));
                }
                if (isset($data['entity_id']) && !empty($data['entity_id'])) {
                    $entityId = intval($data['entity_id']);
                }
                
                $log('Entity update - entity_type: ' . ($entityType ?? 'not provided') . ', entity_id: ' . ($entityId ?? 'not provided') . ', entry_type: ' . ($entryType ?? 'not provided'));
                
                // Build UPDATE query dynamically based on what fields are provided
                $updateFields = [];
                $updateValues = [];
                $updateTypes = '';
                
                // Always update debit_amount and credit_amount
                $updateFields[] = 'debit_amount = ?';
                $updateValues[] = $debitAmount;
                $updateTypes .= 'd';
                
                $updateFields[] = 'credit_amount = ?';
                $updateValues[] = $creditAmount;
                $updateTypes .= 'd';
                
                // Add category if provided
                if (isset($data['category'])) {
                    $updateFields[] = 'category = ?';
                    $updateValues[] = $data['category'];
                    $updateTypes .= 's';
                }
                
                // Add entity_type if provided
                if ($entityType !== null) {
                    $updateFields[] = 'entity_type = ?';
                    $updateValues[] = $entityType;
                    $updateTypes .= 's';
                    $log('Adding entity_type to UPDATE: ' . $entityType);
                }
                
                // Add entity_id if provided
                if ($entityId !== null) {
                    $updateFields[] = 'entity_id = ?';
                    $updateValues[] = $entityId;
                    $updateTypes .= 'i';
                    $log('Adding entity_id to UPDATE: ' . $entityId);
                }
                
                // Check if entity_transactions has entry_type column and add it if provided
                $etEntryTypeCheck = $conn->query("SHOW COLUMNS FROM entity_transactions LIKE 'entry_type'");
                $etHasEntryType = $etEntryTypeCheck && $etEntryTypeCheck->num_rows > 0;
                if ($etHasEntryType && $entryType) {
                    $updateFields[] = 'entry_type = ?';
                    $updateValues[] = $entryType;
                    $updateTypes .= 's';
                    $log('Adding entry_type to entity_transactions UPDATE: ' . $entryType);
                }
                
                // Add WHERE clause
                $updateTypes .= 'i'; // for transactionId
                $updateValues[] = $transactionId;
                
                $updateQuery = "UPDATE entity_transactions SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $log('UPDATE query: ' . $updateQuery);
                $log('UPDATE values: ' . json_encode($updateValues));
                $log('UPDATE types: ' . $updateTypes);
                
                $stmt = $conn->prepare($updateQuery);
                if (!$stmt) {
                    $log('ERROR: Failed to prepare entity_transactions UPDATE: ' . $conn->error);
                    throw new Exception('Failed to prepare entity_transactions update: ' . $conn->error);
                }
                
                $stmt->bind_param($updateTypes, ...$updateValues);
                if (!$stmt->execute()) {
                    $log('ERROR: Failed to execute entity_transactions UPDATE: ' . $stmt->error);
                    throw new Exception('Failed to execute entity_transactions update: ' . $stmt->error);
                }
                $affectedRows = $stmt->affected_rows;
                $log('entity_transactions UPDATE affected rows: ' . $affectedRows);
                $stmt->close();
                
                $conn->commit();
                
                // Add a small delay to ensure database commit is complete
                usleep(100000); // 100ms
                
                // Return the updated transaction data
                $log('Transaction updated successfully. Debit: ' . $debitAmount . ', Credit: ' . $creditAmount . ', Currency: ' . $currency . ', Entry Type: ' . ($entryType ?? $transactionType));
                
                // Fetch updated transaction to return complete data with formatted dates
                $fetchStmt = $conn->prepare("SELECT * FROM entity_transactions et 
                    LEFT JOIN financial_transactions ft ON et.transaction_id = ft.id 
                    WHERE et.id = ?");
                $fetchStmt->bind_param('i', $transactionId);
                $fetchStmt->execute();
                $fetchResult = $fetchStmt->get_result();
                $updatedTransaction = $fetchResult->fetch_assoc();
                $fetchStmt->close();
                
                // Format dates in response
                if ($updatedTransaction) {
                    $updatedTransaction = formatDatesInArray($updatedTransaction);
                }
                
                send_json_response([
                    'success' => true,
                    'message' => 'Transaction updated successfully',
                    'transaction' => $updatedTransaction ?: [
                        'id' => $transactionId,
                        'debit_amount' => $debitAmount,
                        'credit_amount' => $creditAmount,
                        'total_amount' => $totalAmount,
                        'currency' => $currency,
                        'transaction_type' => $transactionType,
                        'entry_type' => $entryType ?? $transactionType,
                        'transaction_date' => formatDateForDisplay($transactionDate)
                    ]
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete transaction
            enforceApiPermission('journal-entries', 'delete');
            
            $transactionId = intval($_GET['id'] ?? 0);
            $entityType = $_GET['entity_type'] ?? '';
            $entityId = intval($_GET['entity_id'] ?? 0);
            
            if ($transactionId <= 0) {
                throw new Exception('Transaction ID is required');
            }
            
            $conn->begin_transaction();
            
            try {
                // Get transaction to verify
            $stmt = $conn->prepare("
                    SELECT transaction_id, entity_type, entity_id 
                    FROM entity_transactions 
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                $entityTrans = $stmt->get_result()->fetch_assoc();
                
                if (!$entityTrans) {
                    throw new Exception('Transaction not found');
                }
                
                // Verify entity match if provided
                if (!empty($entityType) && $entityId > 0) {
                    if ($entityTrans['entity_type'] !== $entityType || $entityTrans['entity_id'] != $entityId) {
                        throw new Exception('Transaction does not belong to this entity');
                    }
                }
                
                // Delete from entity_transactions (cascade will delete from financial_transactions)
                $stmt = $conn->prepare("DELETE FROM entity_transactions WHERE id = ?");
                $stmt->bind_param('i', $transactionId);
                $stmt->execute();
                
                // Also delete from financial_transactions if not cascade
                if ($entityTrans['transaction_id']) {
                    $stmt = $conn->prepare("DELETE FROM financial_transactions WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param('i', $entityTrans['transaction_id']);
                        if (!$stmt->execute()) {
                            $log('Warning: Failed to delete from financial_transactions: ' . $stmt->error);
                            error_log('Failed to delete from financial_transactions: ' . $stmt->error);
                        }
                        $stmt->close();
                    } else {
                        $log('Warning: Failed to prepare DELETE statement for financial_transactions: ' . $conn->error);
                        error_log('Failed to prepare DELETE statement for financial_transactions: ' . $conn->error);
                    }
                }
                
                $conn->commit();
                
                send_json_response([
                    'success' => true,
                    'message' => 'Transaction deleted successfully'
                ]);
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
            
        default:
            send_json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $errorTrace = $e->getTraceAsString();
    
    // Log detailed error
    if (function_exists('log_entity_transactions')) {
        log_entity_transactions("FATAL EXCEPTION: {$errorMsg} | File: {$errorFile} | Line: {$errorLine} | Trace: {$errorTrace}");
    }
    
    send_json_response([
        'success' => false,
        'message' => 'Error: ' . $errorMsg,
        'error_file' => basename($errorFile),
        'error_line' => $errorLine
    ], 500);
} catch (Error $e) {
    $errorMsg = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $errorTrace = $e->getTraceAsString();
    
    // Log detailed error
    if (function_exists('log_entity_transactions')) {
        log_entity_transactions("FATAL ERROR: {$errorMsg} | File: {$errorFile} | Line: {$errorLine} | Trace: {$errorTrace}");
    }
    
    send_json_response([
        'success' => false,
        'message' => 'Error: ' . $errorMsg,
        'error_file' => basename($errorFile),
        'error_line' => $errorLine
    ], 500);
}

/**
 * Get financial summary for an entity
 */
function getEntitySummary($conn, $entityType, $entityId) {
    // Normalize entity_type to lowercase for consistency
    $entityType = strtolower(trim($entityType));
    
    // Get total revenue (Income transactions)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ft.total_amount), 0) as total_revenue 
        FROM entity_transactions et
        INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        WHERE LOWER(et.entity_type) = ? AND et.entity_id = ? 
        AND ft.transaction_type = 'Income' AND ft.status = 'Posted'
    ");
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $revenue = floatval($stmt->get_result()->fetch_assoc()['total_revenue']);
    
    // Get total expenses (Expense transactions)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ft.total_amount), 0) as total_expenses 
        FROM entity_transactions et
        INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        WHERE LOWER(et.entity_type) = ? AND et.entity_id = ? 
        AND ft.transaction_type = 'Expense' AND ft.status = 'Posted'
    ");
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $expenses = floatval($stmt->get_result()->fetch_assoc()['total_expenses']);
    
    // Get this month's total
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(ft.total_amount), 0) as this_month 
        FROM entity_transactions et
        INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        WHERE LOWER(et.entity_type) = ? AND et.entity_id = ? 
        AND ft.status = 'Posted'
        AND MONTH(ft.transaction_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(ft.transaction_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $thisMonth = floatval($stmt->get_result()->fetch_assoc()['this_month']);
    
    // Get transaction count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM entity_transactions et
        INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        WHERE LOWER(et.entity_type) = ? AND et.entity_id = ?
    ");
    $stmt->bind_param('si', $entityType, $entityId);
    $stmt->execute();
    $count = intval($stmt->get_result()->fetch_assoc()['count']);
    
    return [
        'total_revenue' => $revenue,
        'total_expenses' => $expenses,
        'net_profit' => $revenue - $expenses,
        'this_month' => $thisMonth,
        'transaction_count' => $count
    ];
}

