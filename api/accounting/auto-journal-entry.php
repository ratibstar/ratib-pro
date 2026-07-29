<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/auto-journal-entry.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/auto-journal-entry.php`.
 */
/**
 * Automatic Double-Entry Bookkeeping System
 * When transactions are created for agents/subagents/workers/HR,
 * this automatically creates proper journal entries with debit/credit
 * and calculates totals for entries and individuals
 */

require_once '../../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

/**
 * Create automatic journal entry with double-entry bookkeeping
 * @param mysqli $conn Database connection
 * @param int $transactionId Financial transaction ID
 * @param string $entityType agent/subagent/worker/hr
 * @param int $entityId Entity ID
 * @param string $transactionType Income/Expense
 * @param float $amount Transaction amount
 * @param string $description Transaction description
 * @param string $transactionDate Transaction date
 * @return array Result with journal_entry_id and success status
 */
function ensureDefaultAccount($conn, $accountCode, $accountName, $accountType, $normalBalance) {
    $stmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param('s', $accountCode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $stmt->close();
        return (int) $row['id'];
    }
    $stmt->close();
    $ins = $conn->prepare("INSERT INTO financial_accounts (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active) VALUES (?, ?, ?, ?, 0, 0, 1)");
    $ins->bind_param('ssss', $accountCode, $accountName, $accountType, $normalBalance);
    if ($ins->execute()) {
        $id = (int) $conn->insert_id;
        $ins->close();
        error_log("ensureDefaultAccount: Created $accountCode - $accountName");
        return $id;
    }
    $ins->close();
    return 0;
}

function createAutomaticJournalEntry($conn, $transactionId, $entityType, $entityId, $transactionType, $amount, $description, $transactionDate, $category = null) {
    try {
        $category = $category ? trim((string) $category) : null;
        // Ensure cash account exists
        $cashAccountId = ensureDefaultAccount($conn, '1100', 'Cash', 'Asset', 'Debit');
        if (!$cashAccountId) {
            return ['success' => false, 'message' => 'Cash account (1100) could not be created'];
        }
        
        // Category to expense account mapping (6xxx expense group)
        $categoryExpenseMap = [
            'office supplies' => ['6110', 'Office Supplies'],
            'travel' => ['6120', 'Travel & Accommodation'],
            'travel & accommodation' => ['6120', 'Travel & Accommodation'],
            'utilities' => ['6130', 'Utilities'],
            'rent' => ['6140', 'Rent'],
            'payroll' => ['6150', 'Payroll'],
            'marketing' => ['6160', 'Marketing & Advertising'],
            'marketing & advertising' => ['6160', 'Marketing & Advertising'],
            'maintenance' => ['6170', 'Maintenance & Repairs'],
            'maintenance & repairs' => ['6170', 'Maintenance & Repairs'],
            'communication' => ['6180', 'Communication'],
            'insurance' => ['6190', 'Insurance'],
            'professional fees' => ['6200', 'Professional Fees'],
            'commission' => ['6210', 'Commission Expenses'],
            'other' => ['6990', 'Other Expenses'],
        ];

        // Ensure default expense/income accounts exist
        if ($transactionType === 'Expense') {
            ensureDefaultAccount($conn, '5500', 'General Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5600', 'Subagent Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5700', 'Worker Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5800', 'HR Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5900', 'Partner Agency Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5100', 'Operating Expenses', 'Expense', 'Debit');
            ensureDefaultAccount($conn, '5000', 'Other Expenses', 'Expense', 'Debit');
            // Ensure category-specific accounts exist
            foreach ($categoryExpenseMap as $cat => $acct) {
                ensureDefaultAccount($conn, $acct[0], $acct[1], 'Expense', 'Debit');
            }
        } else {
            ensureDefaultAccount($conn, '4300', 'Agent Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4400', 'Subagent Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4500', 'Worker Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4600', 'HR Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4700', 'Accounting Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4100', 'Sales Revenue', 'Revenue', 'Credit');
            ensureDefaultAccount($conn, '4000', 'Other Revenue', 'Revenue', 'Credit');
        }
        
        // Get account mappings
        $accountMap = [];
        $accountQuery = $conn->query("SELECT id, account_code, account_name, account_type, normal_balance FROM financial_accounts WHERE is_active = 1");
        while ($row = $accountQuery->fetch_assoc()) {
            $accountMap[$row['account_code']] = $row;
        }
        
        if (empty($accountMap)) {
            return ['success' => false, 'message' => 'No accounts found'];
        }
        
        // Determine accounts - prefer entity-specific GL account (entity_type+entity_id in financial_accounts)
        $incomeAccountId = null;
        $expenseAccountId = null;
        
        if ($entityType && $entityId && in_array(strtolower($entityType), ['agent','subagent','worker','hr','accounting','partner_agency'])) {
            $escEt = $conn->real_escape_string($entityType);
            $eid = (int)$entityId;
            $entityAcc = $conn->query("SELECT id FROM financial_accounts WHERE entity_type='$escEt' AND entity_id=$eid AND is_active=1 LIMIT 1");
            if ($entityAcc && $entityAcc->num_rows > 0) {
                $ea = $entityAcc->fetch_assoc();
                if ($transactionType === 'Income') $incomeAccountId = (int)$ea['id'];
                elseif ($transactionType === 'Expense') $expenseAccountId = (int)$ea['id'];
            }
        }
        
        if (!$incomeAccountId && $transactionType === 'Income') {
            $incomeAccountId = $accountMap['4300']['id'] ?? $accountMap['4400']['id'] ?? $accountMap['4500']['id'] ?? $accountMap['4600']['id'] ?? $accountMap['4700']['id'] ?? $accountMap['4100']['id'] ?? $accountMap['4000']['id'] ?? null;
        }
        if (!$expenseAccountId && $transactionType === 'Expense') {
            // Prefer category-specific account when provided
            if ($category && isset($categoryExpenseMap[strtolower($category)])) {
                $catCode = $categoryExpenseMap[strtolower($category)][0];
                if (isset($accountMap[$catCode])) {
                    $expenseAccountId = (int) $accountMap[$catCode]['id'];
                }
            }
            // Fallback to entity-specific or default expense accounts
            if (!$expenseAccountId) {
                $expenseAccountId = $accountMap['5500']['id'] ?? $accountMap['5600']['id'] ?? $accountMap['5700']['id'] ?? $accountMap['5800']['id'] ?? $accountMap['5900']['id'] ?? $accountMap['5100']['id'] ?? $accountMap['5000']['id'] ?? null;
            }
        }
        
        if (!$cashAccountId) {
            return ['success' => false, 'message' => 'Cash account (1100) not found'];
        }
        
        // Generate journal entry number
        $entryNumber = 'JE-' . date('Ymd') . '-' . str_pad($transactionId, 6, '0', STR_PAD_LEFT);
        
        // Check if journal entry already exists for this transaction
        $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE entry_number = ?");
        $checkStmt->bind_param('s', $entryNumber);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            $checkStmt->close();
            return ['success' => true, 'message' => 'Journal entry already exists', 'journal_entry_id' => null];
        }
        $checkStmt->close();
        
        // Create journal entry
        $userId = $_SESSION['user_id'] ?? 1;
        // Check if is_posted and is_locked columns exist
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck->num_rows > 0;
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck->num_rows > 0;
        
        // Build INSERT query dynamically based on available columns
        $insertFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'created_by'];
        $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
        $bindParams = [];
        $bindTypes = '';
        
        // Add is_posted if column exists (set to TRUE for Posted entries)
        if ($hasIsPosted) {
            $insertFields[] = 'is_posted';
            $insertValues[] = '?';
            $bindParams[] = 1; // TRUE - already posted
            $bindTypes .= 'i';
        }
        
        // Add is_locked if column exists (set to TRUE for Posted entries)
        if ($hasIsLocked) {
            $insertFields[] = 'is_locked';
            $insertValues[] = '?';
            $bindParams[] = 1; // TRUE - locked when posted
            $bindTypes .= 'i';
        }
        
        $entryType = 'Entity Transaction';
        $totalDebit = $amount;
        $totalCredit = $amount;
        
        // Prepare bind parameters in correct order
        $allBindParams = [
            $entryNumber, 
            $transactionDate, 
            $description, 
            $entryType,
            $totalDebit, 
            $totalCredit, 
            'Posted',
            $userId
        ];
        $allBindParams = array_merge($allBindParams, $bindParams);
        $allBindTypes = 'ssssddsi' . $bindTypes;

        require_once __DIR__ . '/core/accounting-ledger-lock.php';
        try {
            accounting_main_site_enforce_ledger_mutable(0, $transactionDate, null, 'create');
        } catch (\Throwable $lockEx) {
            $lock = accounting_main_site_ledger_lock_response($lockEx);

            return ['success' => false, 'message' => $lock['message']];
        }
        
        $sql = "INSERT INTO journal_entries (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $insertJE = $conn->prepare($sql);
        
        if (!$insertJE) {
            return ['success' => false, 'message' => 'Failed to prepare journal entry insert: ' . $conn->error];
        }
        
        $insertJE->bind_param($allBindTypes, ...$allBindParams);
        
        if (!$insertJE->execute()) {
            $insertJE->close();
            return ['success' => false, 'message' => 'Failed to create journal entry: ' . $insertJE->error];
        }
        
        $journalEntryId = $conn->insert_id;
        $insertJE->close();
        
        // Create journal entry lines (double-entry)
        $insertJEL = $conn->prepare("
            INSERT INTO journal_entry_lines (
                journal_entry_id, account_id, account_code, account_name,
                description, debit_amount, credit_amount, line_order,
                entity_type, entity_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($transactionType === 'Income') {
            // Line 1: Debit Cash
            $cashAccountData = $accountMap['1100'] ?? null;
            $cashCode = $cashAccountData['account_code'] ?? '1100';
            $cashName = $cashAccountData['account_name'] ?? 'Cash';
            $insertJEL->bind_param('iissddiiss', 
                $journalEntryId, $cashAccountId, $cashCode, $cashName,
                "Cash received from {$entityType}", $amount, 0, 1,
                $entityType, $entityId
            );
            $insertJEL->execute();
            
            // Line 2: Credit Revenue
            $incomeCode = '4300';
            $incomeName = 'Agent Revenue';
            if ($entityType === 'subagent') {
                $incomeCode = '4400';
                $incomeName = 'Subagent Revenue';
            } elseif ($entityType === 'worker') {
                $incomeCode = '4500';
                $incomeName = 'Worker Revenue';
            } elseif ($entityType === 'hr') {
                $incomeCode = '4600';
                $incomeName = 'HR Revenue';
            } elseif ($entityType === 'accounting') {
                $incomeCode = '4700';
                $incomeName = 'Accounting Revenue';
            }
            $incomeAccountData = $accountMap[$incomeCode] ?? null;
            if ($incomeAccountData) {
                $incomeName = $incomeAccountData['account_name'];
            }
            $insertJEL->bind_param('iissddiiss', 
                $journalEntryId, $incomeAccountId, $incomeCode, $incomeName,
                "Revenue from {$entityType}", 0, $amount, 2,
                $entityType, $entityId
            );
            $insertJEL->execute();
            
        } elseif ($transactionType === 'Expense') {
            // Line 1: Debit Expense
            // Determine code/name from the selected expense account (category, entity, or default)
            $expenseAccountData = null;
            foreach ($accountMap as $code => $acct) {
                if ((int) $acct['id'] === (int) $expenseAccountId) {
                    $expenseAccountData = $acct;
                    break;
                }
            }
            $expenseCode = $expenseAccountData ? $expenseAccountData['account_code'] : '5500';
            $expenseName = $expenseAccountData ? $expenseAccountData['account_name'] : 'General Expenses';
            $insertJEL->bind_param('iissddiiss', 
                $journalEntryId, $expenseAccountId, $expenseCode, $expenseName,
                "Expense for {$entityType}" . ($category ? " - {$category}" : ''), $amount, 0, 1,
                $entityType, $entityId
            );
            $insertJEL->execute();
            
            // Line 2: Credit Cash
            $cashAccountData = $accountMap['1100'] ?? null;
            $cashCode = $cashAccountData['account_code'] ?? '1100';
            $cashName = $cashAccountData['account_name'] ?? 'Cash';
            $insertJEL->bind_param('iissddiiss', 
                $journalEntryId, $cashAccountId, $cashCode, $cashName,
                "Cash paid to {$entityType}", 0, $amount, 2,
                $entityType, $entityId
            );
            $insertJEL->execute();
        }
        
        $insertJEL->close();
        
        // ERP PRINCIPLE: Do NOT update account balances directly
        // Balances must be calculated from general_ledger only
        // Removed: updateAccountBalance() calls - violates ERP Principle #2
        
        // Post to general ledger (since this entry is created with 'Posted' status)
        $ledgerHelperPath = __DIR__ . '/core/general-ledger-helper.php';
        if (file_exists($ledgerHelperPath)) {
            require_once $ledgerHelperPath;
            if (function_exists('postJournalEntryToLedger')) {
                try {
                    $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
                    error_log("Auto Journal Entry - General ledger posting for entry {$journalEntryId}: " . $ledgerResult['message']);
                } catch (Exception $e) {
                    error_log("Auto Journal Entry - WARNING: Failed to post entry {$journalEntryId} to general ledger: " . $e->getMessage());
                    // Don't fail the transaction, but log the error
                }
            }
        }
        
        return [
            'success' => true, 
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Journal entry created with double-entry bookkeeping'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Update account balance
 * 
 * @deprecated ERP Principle Violation - Do NOT use this function
 * Balances must be calculated from general_ledger only
 * This function is kept for backward compatibility but should never be called
 * 
 * @param mysqli $conn Database connection
 * @param int $accountId Account ID
 * @param float $amountChange Amount change
 */
function updateAccountBalance($conn, $accountId, $amountChange) {
    // ERP PRINCIPLE VIOLATION: This function directly updates account balances
    // Balances MUST be calculated from general_ledger only
    // This function is DEPRECATED and should NOT be used
    error_log("WARNING: updateAccountBalance() called - violates ERP Principle #2. Balances must be calculated from general_ledger only.");
    
    // DO NOT EXECUTE - Return without updating
    return;
    
    /* DEPRECATED CODE - DO NOT USE
    $colCheck = $conn->query("SHOW COLUMNS FROM financial_accounts LIKE 'current_balance'");
    if ($colCheck->num_rows > 0) {
        $updateStmt = $conn->prepare("
            UPDATE financial_accounts 
            SET current_balance = COALESCE(current_balance, 0) + ?
            WHERE id = ?
        ");
        $updateStmt->bind_param('di', $amountChange, $accountId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    */
}

/**
 * Calculate and store totals for individual entity
 */
function updateEntityTotals($conn, $entityType, $entityId) {
    // Create entity_totals table if it doesn't exist
    $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_totals'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS entity_totals (
                id INT PRIMARY KEY AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT NOT NULL,
                total_debit DECIMAL(15,2) DEFAULT 0.00,
                total_credit DECIMAL(15,2) DEFAULT 0.00,
                total_income DECIMAL(15,2) DEFAULT 0.00,
                total_expenses DECIMAL(15,2) DEFAULT 0.00,
                net_balance DECIMAL(15,2) DEFAULT 0.00,
                transaction_count INT DEFAULT 0,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_entity (entity_type, entity_id),
                INDEX idx_entity_type (entity_type),
                INDEX idx_entity_id (entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Calculate totals from entity_transactions (now has debit/credit)
    $calcQuery = "
        SELECT 
            SUM(et.debit_amount) as total_debit,
            SUM(et.credit_amount) as total_credit,
            SUM(CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END) as total_income,
            SUM(CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END) as total_expenses,
            COUNT(*) as transaction_count
        FROM entity_transactions et
        INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
        WHERE et.entity_type = ? AND et.entity_id = ?
    ";
    
    $calcStmt = $conn->prepare($calcQuery);
    $calcStmt->bind_param('si', $entityType, $entityId);
    $calcStmt->execute();
    $result = $calcStmt->get_result();
    $totals = $result->fetch_assoc();
    $calcStmt->close();
    
    $totalDebit = $totals['total_debit'] ?? 0;
    $totalCredit = $totals['total_credit'] ?? 0;
    $totalIncome = $totals['total_income'] ?? 0;
    $totalExpenses = $totals['total_expenses'] ?? 0;
    $netBalance = $totalIncome - $totalExpenses;
    $transactionCount = $totals['transaction_count'] ?? 0;
    
    // Insert or update entity totals
    $upsertStmt = $conn->prepare("
        INSERT INTO entity_totals (entity_type, entity_id, total_debit, total_credit, total_income, total_expenses, net_balance, transaction_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            total_debit = VALUES(total_debit),
            total_credit = VALUES(total_credit),
            total_income = VALUES(total_income),
            total_expenses = VALUES(total_expenses),
            net_balance = VALUES(net_balance),
            transaction_count = VALUES(transaction_count)
    ");
    $upsertStmt->bind_param('sidddddi', $entityType, $entityId, $totalDebit, $totalCredit, $totalIncome, $totalExpenses, $netBalance, $transactionCount);
    $upsertStmt->execute();
    $upsertStmt->close();
    
    return [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'total_income' => $totalIncome,
        'total_expenses' => $totalExpenses,
        'net_balance' => $netBalance,
        'transaction_count' => $transactionCount
    ];
}

// Export functions for use in other files
if (!function_exists('createAutomaticJournalEntry')) {
    // Functions are already defined above
}
?>

