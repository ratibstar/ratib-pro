<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/bank-transaction-gl-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/bank-transaction-gl-helper.php`.
 */
/**
 * Bank Transaction to General Ledger Helper
 * 
 * ERP COMPLIANCE: Bank transactions MUST create journal entries and post to GL
 * 
 * This helper creates journal entries for bank transactions instead of
 * directly updating accounting_banks.current_balance
 */

require_once __DIR__ . '/general-ledger-helper.php';
require_once __DIR__ . '/fiscal-period-helper.php';
require_once __DIR__ . '/erp-posting-controls.php';

/**
 * Create journal entry for bank transaction
 * 
 * @param mysqli $conn Database connection
 * @param int $bankTransactionId Bank transaction ID
 * @param int $bankId Bank ID
 * @param string $transactionDate Transaction date (YYYY-MM-DD)
 * @param string $description Description
 * @param float $amount Amount
 * @param string $transactionType Transaction type (Deposit, Withdrawal, Fee, Interest)
 * @param string|null $referenceNumber Reference number
 * @return array ['success' => bool, 'journal_entry_id' => int|null, 'message' => string]
 */
function createBankTransactionJournalEntry($conn, $bankTransactionId, $bankId, $transactionDate, $description, $amount, $transactionType, $referenceNumber = null) {
    try {
        // Validate posting date
        validatePostingDate($conn, $transactionDate);
        
        // Get bank account mapping
        $bankStmt = $conn->prepare("
            SELECT 
                ab.id,
                ab.bank_name,
                ab.account_name,
                ab.account_number,
                ab.account_type,
                fa.id as account_id,
                fa.account_code,
                fa.account_name as gl_account_name
            FROM accounting_banks ab
            LEFT JOIN financial_accounts fa ON ab.account_id = fa.id
            WHERE ab.id = ?
        ");
        $bankStmt->bind_param('i', $bankId);
        $bankStmt->execute();
        $bankResult = $bankStmt->get_result();
        
        if ($bankResult->num_rows === 0) {
            $bankResult->free();
            $bankStmt->close();
            return ['success' => false, 'journal_entry_id' => null, 'message' => 'Bank account not found'];
        }
        
        $bank = $bankResult->fetch_assoc();
        $bankResult->free();
        $bankStmt->close();
        
        // Get or create bank GL account
        $bankAccountId = $bank['account_id'];
        if (!$bankAccountId) {
            // Try to find cash account (1100) as default
            $cashStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code = '1100' AND is_active = 1 LIMIT 1");
            $cashStmt->execute();
            $cashResult = $cashStmt->get_result();
            if ($cashResult->num_rows > 0) {
                $cashRow = $cashResult->fetch_assoc();
                $bankAccountId = $cashRow['id'];
            }
            $cashResult->free();
            $cashStmt->close();
            
            if (!$bankAccountId) {
                return ['success' => false, 'journal_entry_id' => null, 'message' => 'Bank account not linked to GL account and no cash account found'];
            }
        }
        
        // Determine accounts based on transaction type
        $debitAccountId = null;
        $creditAccountId = null;
        $debitAmount = 0;
        $creditAmount = 0;
        
        if ($transactionType === 'Deposit' || $transactionType === 'Interest') {
            // Deposit: Debit Bank, Credit Income/Other
            $debitAccountId = $bankAccountId;
            $debitAmount = $amount;
            
            // Get income account for interest, or use bank account for deposits
            if ($transactionType === 'Interest') {
                $incomeStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code LIKE '4%' AND account_type = 'REVENUE' AND is_active = 1 LIMIT 1");
                $incomeStmt->execute();
                $incomeResult = $incomeStmt->get_result();
                if ($incomeResult->num_rows > 0) {
                    $incomeRow = $incomeResult->fetch_assoc();
                    $creditAccountId = $incomeRow['id'];
                }
                $incomeResult->free();
                $incomeStmt->close();
            }
            
            // If no income account, use bank account (internal transfer)
            if (!$creditAccountId) {
                $creditAccountId = $bankAccountId;
            }
            $creditAmount = $amount;
            
        } elseif ($transactionType === 'Withdrawal' || $transactionType === 'Fee') {
            // Withdrawal: Debit Expense/Other, Credit Bank
            $creditAccountId = $bankAccountId;
            $creditAmount = $amount;
            
            // Get expense account for fees
            if ($transactionType === 'Fee') {
                $expenseStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE account_code LIKE '5%' AND account_type = 'EXPENSE' AND is_active = 1 LIMIT 1");
                $expenseStmt->execute();
                $expenseResult = $expenseStmt->get_result();
                if ($expenseResult->num_rows > 0) {
                    $expenseRow = $expenseResult->fetch_assoc();
                    $debitAccountId = $expenseRow['id'];
                }
                $expenseResult->free();
                $expenseStmt->close();
            }
            
            // If no expense account, use bank account (internal transfer)
            if (!$debitAccountId) {
                $debitAccountId = $bankAccountId;
            }
            $debitAmount = $amount;
        }
        
        if (!$debitAccountId || !$creditAccountId) {
            return ['success' => false, 'journal_entry_id' => null, 'message' => 'Could not determine GL accounts for transaction'];
        }
        
        // Generate journal entry number
        $entryNumStmt = $conn->query("SELECT MAX(CAST(SUBSTRING(entry_number, 4) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
        $entryNumRow = $entryNumStmt->fetch_assoc();
        $nextNum = ($entryNumRow['max_num'] ?? 0) + 1;
        $entryNumStmt->free();
        $entryNumber = 'JE-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
        
        // Get fiscal period
        $fiscalPeriodId = getFiscalPeriodId($conn, $transactionDate);
        
        // Create journal entry header
        $jeFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'posting_status', 'is_auto', 'source_table', 'source_id', 'created_by'];
        $jeValues = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
        $jeParams = [
            $entryNumber,
            $transactionDate,
            "Bank Transaction: {$description}",
            'Automatic',
            $debitAmount,
            $creditAmount,
            'Posted',
            'posted',
            1, // is_auto
            'accounting_bank_transactions',
            $bankTransactionId,
            $_SESSION['user_id'] ?? 1
        ];
        $jeTypes = 'ssssddsssisi';
        
        // Add optional fields
        $currencyCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
        $hasCurrency = $currencyCheck && $currencyCheck->num_rows > 0;
        if ($currencyCheck) $currencyCheck->free();
        if ($hasCurrency) {
            $jeFields[] = 'currency';
            $jeValues[] = '?';
            $jeParams[] = 'SAR';
            $jeTypes .= 's';
        }
        
        $fiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'fiscal_period_id'");
        $hasFiscalPeriod = $fiscalPeriodCheck && $fiscalPeriodCheck->num_rows > 0;
        if ($fiscalPeriodCheck) $fiscalPeriodCheck->free();
        if ($hasFiscalPeriod && $fiscalPeriodId) {
            $jeFields[] = 'fiscal_period_id';
            $jeValues[] = '?';
            $jeParams[] = $fiscalPeriodId;
            $jeTypes .= 'i';
        }
        
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck && $isPostedCheck->num_rows > 0;
        if ($isPostedCheck) $isPostedCheck->free();
        if ($hasIsPosted) {
            $jeFields[] = 'is_posted';
            $jeValues[] = '?';
            $jeParams[] = 1;
            $jeTypes .= 'i';
        }
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck && $isLockedCheck->num_rows > 0;
        if ($isLockedCheck) $isLockedCheck->free();
        if ($hasIsLocked) {
            $jeFields[] = 'is_locked';
            $jeValues[] = '?';
            $jeParams[] = 1;
            $jeTypes .= 'i';
        }
        
        $lockedAtCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'locked_at'");
        $hasLockedAt = $lockedAtCheck && $lockedAtCheck->num_rows > 0;
        if ($lockedAtCheck) $lockedAtCheck->free();
        if ($hasLockedAt) {
            $jeFields[] = 'locked_at';
            $jeValues[] = 'NOW()';
        }
        
        $insertJE = "INSERT INTO journal_entries (" . implode(', ', $jeFields) . ") VALUES (" . implode(', ', $jeValues) . ")";
        $jeStmt = $conn->prepare($insertJE);
        
        if (!$jeStmt) {
            return ['success' => false, 'journal_entry_id' => null, 'message' => 'Failed to prepare journal entry: ' . $conn->error];
        }
        
        // Bind parameters (excluding NOW() which is SQL function)
        $bindParams = [];
        $bindTypes = '';
        foreach ($jeParams as $param) {
            if ($param !== 'NOW()') {
                $bindParams[] = $param;
                if (is_int($param)) {
                    $bindTypes .= 'i';
                } elseif (is_float($param)) {
                    $bindTypes .= 'd';
                } else {
                    $bindTypes .= 's';
                }
            }
        }
        
        $jeStmt->bind_param($bindTypes, ...$bindParams);
        if (!$jeStmt->execute()) {
            $jeStmt->close();
            return ['success' => false, 'journal_entry_id' => null, 'message' => 'Failed to create journal entry: ' . $jeStmt->error];
        }
        
        $journalEntryId = $conn->insert_id;
        $jeStmt->close();
        
        // Create journal entry lines
        $lineFields = ['journal_entry_id', 'account_id', 'debit_amount', 'credit_amount', 'description'];
        $lineValues = ['?', '?', '?', '?', '?'];
        
        // Line 1: Debit
        $line1Stmt = $conn->prepare("INSERT INTO journal_entry_lines (" . implode(', ', $lineFields) . ") VALUES (" . implode(', ', $lineValues) . ")");
        $line1Stmt->bind_param('iidds', $journalEntryId, $debitAccountId, $debitAmount, 0, $description);
        $line1Stmt->execute();
        $line1Stmt->close();
        
        // Line 2: Credit
        $line2Stmt = $conn->prepare("INSERT INTO journal_entry_lines (" . implode(', ', $lineFields) . ") VALUES (" . implode(', ', $lineValues) . ")");
        $line2Stmt->bind_param('iidds', $journalEntryId, $creditAccountId, 0, $creditAmount, $description);
        $line2Stmt->execute();
        $line2Stmt->close();
        
        // Post to general ledger
        $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
        
        return [
            'success' => true,
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Bank transaction journal entry created and posted to GL',
            'ledger_result' => $ledgerResult
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'journal_entry_id' => null, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Calculate bank balance from general ledger
 * 
 * @param mysqli $conn Database connection
 * @param int $bankId Bank ID
 * @param string|null $asOfDate As of date (YYYY-MM-DD), null for current
 * @return float Bank balance from GL
 */
function getBankBalanceFromGL($conn, $bankId, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    
    // Get bank's GL account ID
    $bankStmt = $conn->prepare("SELECT account_id FROM accounting_banks WHERE id = ?");
    $bankStmt->bind_param('i', $bankId);
    $bankStmt->execute();
    $bankResult = $bankStmt->get_result();
    
    if ($bankResult->num_rows === 0) {
        $bankResult->free();
        $bankStmt->close();
        return 0.0;
    }
    
    $bank = $bankResult->fetch_assoc();
    $bankResult->free();
    $bankStmt->close();
    
    $accountId = $bank['account_id'];
    if (!$accountId) {
        return 0.0;
    }
    
    // Calculate balance from general_ledger (ONLY posted entries)
    $glStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(gl.debit), 0) as total_debit,
            COALESCE(SUM(gl.credit), 0) as total_credit
        FROM general_ledger gl
        INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
        WHERE gl.account_id = ?
        AND gl.posting_date <= ?
        AND je.status = 'Posted'
        AND (je.posting_status = 'posted' OR je.posting_status IS NULL)
        AND (je.is_posted = 1 OR je.is_posted IS NULL)
        AND (je.posting_status IS NULL OR je.posting_status != 'reversed')
    ");
    $glStmt->bind_param('is', $accountId, $asOfDate);
    $glStmt->execute();
    $glResult = $glStmt->get_result();
    $glData = $glResult->fetch_assoc();
    $glResult->free();
    $glStmt->close();
    
    $totalDebit = floatval($glData['total_debit'] ?? 0);
    $totalCredit = floatval($glData['total_credit'] ?? 0);
    
    // Bank accounts are asset accounts (debit normal balance)
    return $totalDebit - $totalCredit;
}
