<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/invoice-payment-automation.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/invoice-payment-automation.php`.
 */
/**
 * Invoice and Payment Automation Helper
 * Automatically creates journal entries for:
 * - Sales Invoices: Dr Accounts Receivable, Dr VAT Receivable, Cr Revenue, Cr VAT Payable
 * - Payments: Dr Cash/Bank, Cr Accounts Receivable
 * - Expenses: Dr Expense Account, Cr Cash/Bank
 * - Receipts/Disbursement Vouchers: Auto journal entries
 * Supports cost centers per line
 */

require_once __DIR__ . '/general-ledger-helper.php';

/**
 * Create automatic journal entry for Sales Invoice
 * Dr Accounts Receivable, Dr VAT Receivable, Cr Revenue, Cr VAT Payable
 * 
 * @param mysqli $conn Database connection
 * @param int $invoiceId Invoice ID
 * @param string $invoiceNumber Invoice number
 * @param string $invoiceDate Invoice date
 * @param float $totalAmount Total invoice amount
 * @param float|null $vatAmount VAT amount (calculated if not provided)
 * @param float|null $vatRate VAT rate (default 15%)
 * @param int|null $costCenterId Cost center ID (optional)
 * @param string $description Description
 * @return array Result with journal_entry_id and success status
 */
function createInvoiceJournalEntry($conn, $invoiceId, $invoiceNumber, $invoiceDate, $totalAmount, $vatAmount = null, $vatRate = 15, $costCenterId = null, $description = '') {
    try {
        // Validate amount
        if ($totalAmount <= 0) {
            return ['success' => false, 'message' => 'Total amount must be greater than 0'];
        }
        
        // Check if period is locked
        $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
        if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
            $closingsTableCheck->free();
            $periodCheck = $conn->prepare("
                SELECT COUNT(*) as locked_count
                FROM financial_closings
                WHERE status = 'Completed'
                AND ? >= period_start_date 
                AND ? <= period_end_date
            ");
            $periodCheck->bind_param('ss', $invoiceDate, $invoiceDate);
            $periodCheck->execute();
            $periodResult = $periodCheck->get_result();
            $periodRow = $periodResult->fetch_assoc();
            $isPeriodLocked = ($periodRow['locked_count'] ?? 0) > 0;
            $periodResult->free();
            $periodCheck->close();
            
            if ($isPeriodLocked) {
                return ['success' => false, 'message' => 'Cannot create journal entry - period is locked (completed closing). Invoice date falls within a closed period.'];
            }
        } else {
            if ($closingsTableCheck) {
                $closingsTableCheck->free();
            }
        }
        
        // Get account mappings
        $accountMap = [];
        $accountQuery = $conn->query("SELECT id, account_code, account_name, account_type, normal_balance FROM financial_accounts WHERE is_active = 1");
        while ($row = $accountQuery->fetch_assoc()) {
            $accountMap[$row['account_code']] = $row;
        }
        $accountQuery->free();
        
        if (empty($accountMap)) {
            return ['success' => false, 'message' => 'No accounts found'];
        }
        
        // Default account codes (with fallbacks)
        $arAccountCode = '1200'; // Accounts Receivable
        $vatReceivableCode = '1300'; // VAT Receivable
        $revenueAccountCode = '4100'; // Revenue
        $vatPayableCode = '2200'; // VAT Payable
        
        $arAccountId = $accountMap[$arAccountCode]['id'] ?? null;
        $vatReceivableId = $accountMap[$vatReceivableCode]['id'] ?? null;
        $revenueAccountId = $accountMap[$revenueAccountCode]['id'] ?? $accountMap['4000']['id'] ?? null;
        $vatPayableId = $accountMap[$vatPayableCode]['id'] ?? null;
        
        // If VAT accounts not found, calculate without VAT
        if (!$arAccountId || !$revenueAccountId) {
            return ['success' => false, 'message' => 'Required accounts (AR or Revenue) not found'];
        }
        
        // Calculate VAT if not provided
        if ($vatAmount === null && $vatRate > 0) {
            // VAT inclusive: total = base + vat, so base = total / (1 + vatRate/100)
            $baseAmount = $totalAmount / (1 + ($vatRate / 100));
            $vatAmount = $totalAmount - $baseAmount;
        } else if ($vatAmount === null) {
            $baseAmount = $totalAmount;
            $vatAmount = 0;
        } else {
            $baseAmount = $totalAmount - $vatAmount;
        }
        
        // Generate journal entry number
        $entryNumber = 'JE-INV-' . $invoiceNumber;
        
        // Check if journal entry already exists
        $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE entry_number = ?");
        $checkStmt->bind_param('s', $entryNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkResult->free();
            $checkStmt->close();
            return ['success' => true, 'message' => 'Journal entry already exists', 'journal_entry_id' => null];
        }
        $checkResult->free();
        $checkStmt->close();
        
        // Check if is_posted and is_locked columns exist
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck->num_rows > 0;
        $isPostedCheck->free();
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck->num_rows > 0;
        $isLockedCheck->free();
        
        // Build INSERT query dynamically
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
        
        // Add is_locked if column exists
        if ($hasIsLocked) {
            $insertFields[] = 'is_locked';
            $insertValues[] = '?';
            $bindParams[] = 1; // TRUE
            $bindTypes .= 'i';
        }
        
        $entryType = 'Sales Invoice';
        $totalDebit = $totalAmount;
        $totalCredit = $totalAmount;
        
        // Prepare bind parameters in correct order
        $userId = $_SESSION['user_id'] ?? 1;
        $allBindParams = [
            $entryNumber,
            $invoiceDate,
            $description ?: "Sales Invoice {$invoiceNumber}",
            $entryType,
            $totalDebit,
            $totalCredit,
            'Posted',
            $userId
        ];
        $allBindParams = array_merge($allBindParams, $bindParams);
        $allBindTypes = 'ssssddsi' . $bindTypes;
        
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
        
        // Check if cost_center_id column exists in journal_entry_lines
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        $hasCostCenter = $costCenterCheck->num_rows > 0;
        $costCenterCheck->free();
        
        // Create journal entry lines (double-entry)
        if ($hasCostCenter && $costCenterId) {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order, cost_center_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        $lineOrder = 1;
        
        // Line 1: Debit Accounts Receivable
        $arCode = $accountMap[$arAccountCode]['account_code'] ?? $arAccountCode;
        $arName = $accountMap[$arAccountCode]['account_name'] ?? 'Accounts Receivable';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $arAccountId, $arCode, $arName, "Invoice {$invoiceNumber} - Receivable", $totalAmount, 0, $lineOrder, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $arAccountId, $arCode, $arName, "Invoice {$invoiceNumber} - Receivable", $totalAmount, 0, $lineOrder);
        }
        $insertJEL->execute();
        $lineOrder++;
        
        // Line 2: Credit Revenue
        $revenueCode = $accountMap[$revenueAccountCode]['account_code'] ?? $revenueAccountCode;
        $revenueName = $accountMap[$revenueAccountCode]['account_name'] ?? 'Revenue';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $revenueAccountId, $revenueCode, $revenueName, "Invoice {$invoiceNumber} - Revenue", 0, $baseAmount, $lineOrder, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $revenueAccountId, $revenueCode, $revenueName, "Invoice {$invoiceNumber} - Revenue", 0, $baseAmount, $lineOrder);
        }
        $insertJEL->execute();
        $lineOrder++;
        
        // Line 3 & 4: VAT entries (if VAT > 0)
        if ($vatAmount > 0.01) {
            // Line 3: Debit VAT Receivable (if account exists)
            if ($vatReceivableId) {
                $vatRecCode = $accountMap[$vatReceivableCode]['account_code'] ?? $vatReceivableCode;
                $vatRecName = $accountMap[$vatReceivableCode]['account_name'] ?? 'VAT Receivable';
                if ($hasCostCenter && $costCenterId) {
                    $insertJEL->bind_param('iissddii', $journalEntryId, $vatReceivableId, $vatRecCode, $vatRecName, "Invoice {$invoiceNumber} - VAT Receivable", $vatAmount, 0, $lineOrder, $costCenterId);
                } else {
                    $insertJEL->bind_param('iissddi', $journalEntryId, $vatReceivableId, $vatRecCode, $vatRecName, "Invoice {$invoiceNumber} - VAT Receivable", $vatAmount, 0, $lineOrder);
                }
                $insertJEL->execute();
                $lineOrder++;
            }
            
            // Line 4: Credit VAT Payable (if account exists)
            if ($vatPayableId) {
                $vatPayCode = $accountMap[$vatPayableCode]['account_code'] ?? $vatPayableCode;
                $vatPayName = $accountMap[$vatPayableCode]['account_name'] ?? 'VAT Payable';
                if ($hasCostCenter && $costCenterId) {
                    $insertJEL->bind_param('iissddii', $journalEntryId, $vatPayableId, $vatPayCode, $vatPayName, "Invoice {$invoiceNumber} - VAT Payable", 0, $vatAmount, $lineOrder, $costCenterId);
                } else {
                    $insertJEL->bind_param('iissddi', $journalEntryId, $vatPayableId, $vatPayCode, $vatPayName, "Invoice {$invoiceNumber} - VAT Payable", 0, $vatAmount, $lineOrder);
                }
                $insertJEL->execute();
            } else {
                // If VAT Payable account doesn't exist, adjust Revenue credit to balance
                // Increase revenue credit by vatAmount to balance the entry
                $revenueCode = $accountMap[$revenueAccountCode]['account_code'] ?? $revenueAccountCode;
                $revenueName = $accountMap[$revenueAccountCode]['account_name'] ?? 'Revenue';
                $adjustedRevenueCredit = $baseAmount + $vatAmount;
                
                // Update the revenue line that was already inserted
                $updateRevenueStmt = $conn->prepare("
                    UPDATE journal_entry_lines 
                    SET credit_amount = ?
                    WHERE journal_entry_id = ? AND account_id = ? AND line_order = 2
                ");
                $updateRevenueStmt->bind_param('dii', $adjustedRevenueCredit, $journalEntryId, $revenueAccountId);
                $updateRevenueStmt->execute();
                $updateRevenueStmt->close();
                
                // Update journal_entries.total_debit and total_credit to match actual lines
                // If VAT Receivable exists, total_debit = totalAmount + vatAmount
                // Otherwise, total_debit = totalAmount
                $adjustedTotalDebit = $vatReceivableId ? ($totalAmount + $vatAmount) : $totalAmount;
                $adjustedTotalCredit = $adjustedRevenueCredit;
                
                $updateJEStmt = $conn->prepare("UPDATE journal_entries SET total_debit = ?, total_credit = ? WHERE id = ?");
                $updateJEStmt->bind_param('ddi', $adjustedTotalDebit, $adjustedTotalCredit, $journalEntryId);
                $updateJEStmt->execute();
                $updateJEStmt->close();
                
                error_log("WARNING: VAT Payable account not found. Adjusted revenue credit from {$baseAmount} to {$adjustedRevenueCredit}, total_debit to {$adjustedTotalDebit}");
            }
        }
        
        $insertJEL->close();
        
        // Final validation: Recalculate totals from lines to ensure consistency
        // This ensures journal_entries.total_debit/total_credit match actual line totals
        $finalRecalcStmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(debit_amount), 0) as total_debit,
                COALESCE(SUM(credit_amount), 0) as total_credit
            FROM journal_entry_lines
            WHERE journal_entry_id = ?
        ");
        $finalRecalcStmt->bind_param('i', $journalEntryId);
        $finalRecalcStmt->execute();
        $finalRecalcResult = $finalRecalcStmt->get_result();
        $finalRecalcData = $finalRecalcResult->fetch_assoc();
        $finalRecalcResult->free();
        $finalRecalcStmt->close();
        
        $finalDebit = floatval($finalRecalcData['total_debit'] ?? 0);
        $finalCredit = floatval($finalRecalcData['total_credit'] ?? 0);
        
        // Update journal_entries with correct totals from lines
        $updateTotalsStmt = $conn->prepare("UPDATE journal_entries SET total_debit = ?, total_credit = ? WHERE id = ?");
        $updateTotalsStmt->bind_param('ddi', $finalDebit, $finalCredit, $journalEntryId);
        $updateTotalsStmt->execute();
        $updateTotalsStmt->close();
        
        // Validate balance
        $balanceDiff = abs($finalDebit - $finalCredit);
        if ($balanceDiff > 0.01) {
            error_log("ERROR: Invoice journal entry {$journalEntryId} is unbalanced. Debit: {$finalDebit}, Credit: {$finalCredit}, Diff: {$balanceDiff}");
            return ['success' => false, 'message' => "Journal entry is unbalanced. Debit: {$finalDebit}, Credit: {$finalCredit}"];
        }
        
        // Post to general ledger (since entry is Posted)
        $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
        
        return [
            'success' => true,
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Invoice journal entry created successfully',
            'ledger_result' => $ledgerResult
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create automatic journal entry for Payment
 * Dr Cash/Bank, Cr Accounts Receivable
 * 
 * @param mysqli $conn Database connection
 * @param int $paymentId Payment ID
 * @param string $paymentNumber Payment number
 * @param string $paymentDate Payment date
 * @param float $amount Payment amount
 * @param int|null $bankAccountId Bank account ID (if null, uses Cash account)
 * @param int|null $invoiceId Invoice ID being paid (optional)
 * @param int|null $costCenterId Cost center ID (optional)
 * @param string $description Description
 * @return array Result with journal_entry_id and success status
 */
function createPaymentJournalEntry($conn, $paymentId, $paymentNumber, $paymentDate, $amount, $bankAccountId = null, $invoiceId = null, $costCenterId = null, $description = '') {
    try {
        // Validate amount
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Payment amount must be greater than 0'];
        }
        
        // Check if period is locked
        $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
        if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
            $closingsTableCheck->free();
            $periodCheck = $conn->prepare("
                SELECT COUNT(*) as locked_count
                FROM financial_closings
                WHERE status = 'Completed'
                AND ? >= period_start_date 
                AND ? <= period_end_date
            ");
            $periodCheck->bind_param('ss', $paymentDate, $paymentDate);
            $periodCheck->execute();
            $periodResult = $periodCheck->get_result();
            $periodRow = $periodResult->fetch_assoc();
            $isPeriodLocked = ($periodRow['locked_count'] ?? 0) > 0;
            $periodResult->free();
            $periodCheck->close();
            
            if ($isPeriodLocked) {
                return ['success' => false, 'message' => 'Cannot create journal entry - period is locked (completed closing). Payment date falls within a closed period.'];
            }
        } else {
            if ($closingsTableCheck) {
                $closingsTableCheck->free();
            }
        }
        
        // Get account mappings
        $accountMap = [];
        $accountQuery = $conn->query("SELECT id, account_code, account_name, account_type, normal_balance FROM financial_accounts WHERE is_active = 1");
        while ($row = $accountQuery->fetch_assoc()) {
            $accountMap[$row['account_code']] = $row;
        }
        $accountQuery->free();
        
        if (empty($accountMap)) {
            return ['success' => false, 'message' => 'No accounts found'];
        }
        
        // Determine cash/bank account
        $cashAccountCode = '1100'; // Cash
        $cashAccountId = $accountMap[$cashAccountCode]['id'] ?? null;
        
        // If bank_account_id provided, try to find bank account
        if ($bankAccountId) {
            // Check if accounting_banks table exists and has account_id mapping
            $bankCheck = $conn->prepare("SELECT account_id FROM accounting_banks WHERE id = ? AND is_active = 1");
            if ($bankCheck) {
                $bankCheck->bind_param('i', $bankAccountId);
                $bankCheck->execute();
                $bankResult = $bankCheck->get_result();
                if ($bankRow = $bankResult->fetch_assoc()) {
                    $mappedAccountId = $bankRow['account_id'] ?? null;
                    if ($mappedAccountId) {
                        $cashAccountId = $mappedAccountId;
                        // Find account code
                        $accountCheck = $conn->prepare("SELECT account_code FROM financial_accounts WHERE id = ?");
                        $accountCheck->bind_param('i', $mappedAccountId);
                        $accountCheck->execute();
                        $accResult = $accountCheck->get_result();
                        if ($accRow = $accResult->fetch_assoc()) {
                            $cashAccountCode = $accRow['account_code'];
                        }
                        $accResult->free();
                        $accountCheck->close();
                    }
                }
                $bankResult->free();
                $bankCheck->close();
            }
        }
        
        if (!$cashAccountId) {
            return ['success' => false, 'message' => 'Cash/Bank account not found'];
        }
        
        // Accounts Receivable
        $arAccountCode = '1200';
        $arAccountId = $accountMap[$arAccountCode]['id'] ?? null;
        
        if (!$arAccountId) {
            return ['success' => false, 'message' => 'Accounts Receivable account not found'];
        }
        
        // Generate journal entry number
        $entryNumber = 'JE-PAY-' . $paymentNumber;
        
        // Check if journal entry already exists
        $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE entry_number = ?");
        $checkStmt->bind_param('s', $entryNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkResult->free();
            $checkStmt->close();
            return ['success' => true, 'message' => 'Journal entry already exists', 'journal_entry_id' => null];
        }
        $checkResult->free();
        $checkStmt->close();
        
        // Check if is_posted and is_locked columns exist
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck->num_rows > 0;
        $isPostedCheck->free();
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck->num_rows > 0;
        $isLockedCheck->free();
        
        // Build INSERT query dynamically
        $insertFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'created_by'];
        $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
        $bindParams = [];
        $bindTypes = '';
        
        if ($hasIsPosted) {
            $insertFields[] = 'is_posted';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        if ($hasIsLocked) {
            $insertFields[] = 'is_locked';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        $entryType = 'Payment';
        $totalDebit = $amount;
        $totalCredit = $amount;
        
        $userId = $_SESSION['user_id'] ?? 1;
        $allBindParams = [
            $entryNumber,
            $paymentDate,
            $description ?: "Payment {$paymentNumber}",
            $entryType,
            $totalDebit,
            $totalCredit,
            'Posted',
            $userId
        ];
        $allBindParams = array_merge($allBindParams, $bindParams);
        $allBindTypes = 'ssssddsi' . $bindTypes;
        
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
        
        // Check if cost_center_id column exists
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        $hasCostCenter = $costCenterCheck->num_rows > 0;
        $costCenterCheck->free();
        
        // Create journal entry lines
        if ($hasCostCenter && $costCenterId) {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order, cost_center_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        // Line 1: Debit Cash/Bank
        $cashCode = $cashAccountCode;
        $cashName = $accountMap[$cashAccountCode]['account_name'] ?? 'Cash';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Payment {$paymentNumber}", $amount, 0, 1, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Payment {$paymentNumber}", $amount, 0, 1);
        }
        $insertJEL->execute();
        
        // Line 2: Credit Accounts Receivable
        $arCode = $accountMap[$arAccountCode]['account_code'] ?? $arAccountCode;
        $arName = $accountMap[$arAccountCode]['account_name'] ?? 'Accounts Receivable';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $arAccountId, $arCode, $arName, "Payment {$paymentNumber} - Invoice", 0, $amount, 2, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $arAccountId, $arCode, $arName, "Payment {$paymentNumber} - Invoice", 0, $amount, 2);
        }
        $insertJEL->execute();
        
        $insertJEL->close();
        
        // Post to general ledger
        $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
        
        return [
            'success' => true,
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Payment journal entry created successfully',
            'ledger_result' => $ledgerResult
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create automatic journal entry for Expense
 * Dr Expense Account, Cr Cash/Bank
 * 
 * @param mysqli $conn Database connection
 * @param int $expenseId Expense ID (or payment ID if from payment_payments)
 * @param string $referenceNumber Reference number
 * @param string $expenseDate Expense date
 * @param float $amount Expense amount
 * @param int|null $expenseAccountId Expense account ID (if null, uses default 5000)
 * @param int|null $bankAccountId Bank account ID (if null, uses Cash account)
 * @param int|null $costCenterId Cost center ID (optional)
 * @param string $description Description
 * @return array Result with journal_entry_id and success status
 */
function createExpenseJournalEntry($conn, $expenseId, $referenceNumber, $expenseDate, $amount, $expenseAccountId = null, $bankAccountId = null, $costCenterId = null, $description = '') {
    try {
        // Validate amount
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Expense amount must be greater than 0'];
        }
        
        // Check if period is locked
        $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
        if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
            $closingsTableCheck->free();
            $periodCheck = $conn->prepare("
                SELECT COUNT(*) as locked_count
                FROM financial_closings
                WHERE status = 'Completed'
                AND ? >= period_start_date 
                AND ? <= period_end_date
            ");
            $periodCheck->bind_param('ss', $expenseDate, $expenseDate);
            $periodCheck->execute();
            $periodResult = $periodCheck->get_result();
            $periodRow = $periodResult->fetch_assoc();
            $isPeriodLocked = ($periodRow['locked_count'] ?? 0) > 0;
            $periodResult->free();
            $periodCheck->close();
            
            if ($isPeriodLocked) {
                return ['success' => false, 'message' => 'Cannot create journal entry - period is locked (completed closing). Expense date falls within a closed period.'];
            }
        } else {
            if ($closingsTableCheck) {
                $closingsTableCheck->free();
            }
        }
        
        // Get account mappings
        $accountMap = [];
        $accountQuery = $conn->query("SELECT id, account_code, account_name, account_type, normal_balance FROM financial_accounts WHERE is_active = 1");
        while ($row = $accountQuery->fetch_assoc()) {
            $accountMap[$row['account_code']] = $row;
        }
        $accountQuery->free();
        
        if (empty($accountMap)) {
            return ['success' => false, 'message' => 'No accounts found'];
        }
        
        // Determine expense account
        $expenseAccountCode = '5000'; // Default expense
        if ($expenseAccountId) {
            $expenseCheck = $conn->prepare("SELECT account_code FROM financial_accounts WHERE id = ?");
            $expenseCheck->bind_param('i', $expenseAccountId);
            $expenseCheck->execute();
            $expenseResult = $expenseCheck->get_result();
            if ($expenseRow = $expenseResult->fetch_assoc()) {
                $expenseAccountCode = $expenseRow['account_code'];
            }
            $expenseResult->free();
            $expenseCheck->close();
        } else {
            $expenseAccountId = $accountMap[$expenseAccountCode]['id'] ?? $accountMap['5100']['id'] ?? null;
        }
        
        // Determine cash/bank account (same logic as payment)
        $cashAccountCode = '1100';
        $cashAccountId = $accountMap[$cashAccountCode]['id'] ?? null;
        
        if ($bankAccountId) {
            $bankCheck = $conn->prepare("SELECT account_id FROM accounting_banks WHERE id = ? AND is_active = 1");
            if ($bankCheck) {
                $bankCheck->bind_param('i', $bankAccountId);
                $bankCheck->execute();
                $bankResult = $bankCheck->get_result();
                if ($bankRow = $bankResult->fetch_assoc()) {
                    $mappedAccountId = $bankRow['account_id'] ?? null;
                    if ($mappedAccountId) {
                        $cashAccountId = $mappedAccountId;
                        $accountCheck = $conn->prepare("SELECT account_code FROM financial_accounts WHERE id = ?");
                        $accountCheck->bind_param('i', $mappedAccountId);
                        $accountCheck->execute();
                        $accResult = $accountCheck->get_result();
                        if ($accRow = $accResult->fetch_assoc()) {
                            $cashAccountCode = $accRow['account_code'];
                        }
                        $accResult->free();
                        $accountCheck->close();
                    }
                }
                $bankResult->free();
                $bankCheck->close();
            }
        }
        
        if (!$expenseAccountId || !$cashAccountId) {
            return ['success' => false, 'message' => 'Expense or Cash/Bank account not found'];
        }
        
        // Generate journal entry number
        $entryNumber = 'JE-EXP-' . $referenceNumber;
        
        // Check if journal entry already exists
        $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE entry_number = ?");
        $checkStmt->bind_param('s', $entryNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkResult->free();
            $checkStmt->close();
            return ['success' => true, 'message' => 'Journal entry already exists', 'journal_entry_id' => null];
        }
        $checkResult->free();
        $checkStmt->close();
        
        // Check if is_posted and is_locked columns exist
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck->num_rows > 0;
        $isPostedCheck->free();
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck->num_rows > 0;
        $isLockedCheck->free();
        
        // Build INSERT query dynamically
        $insertFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'created_by'];
        $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
        $bindParams = [];
        $bindTypes = '';
        
        if ($hasIsPosted) {
            $insertFields[] = 'is_posted';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        if ($hasIsLocked) {
            $insertFields[] = 'is_locked';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        $entryType = 'Expense';
        $totalDebit = $amount;
        $totalCredit = $amount;
        
        $userId = $_SESSION['user_id'] ?? 1;
        $allBindParams = [
            $entryNumber,
            $expenseDate,
            $description ?: "Expense {$referenceNumber}",
            $entryType,
            $totalDebit,
            $totalCredit,
            'Posted',
            $userId
        ];
        $allBindParams = array_merge($allBindParams, $bindParams);
        $allBindTypes = 'ssssddsi' . $bindTypes;
        
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
        
        // Check if cost_center_id column exists
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        $hasCostCenter = $costCenterCheck->num_rows > 0;
        $costCenterCheck->free();
        
        // Create journal entry lines
        if ($hasCostCenter && $costCenterId) {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order, cost_center_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        // Line 1: Debit Expense
        $expenseCode = $expenseAccountCode;
        $expenseName = $accountMap[$expenseAccountCode]['account_name'] ?? 'Expense';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $expenseAccountId, $expenseCode, $expenseName, "Expense {$referenceNumber}", $amount, 0, 1, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $expenseAccountId, $expenseCode, $expenseName, "Expense {$referenceNumber}", $amount, 0, 1);
        }
        $insertJEL->execute();
        
        // Line 2: Credit Cash/Bank
        $cashCode = $cashAccountCode;
        $cashName = $accountMap[$cashAccountCode]['account_name'] ?? 'Cash';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Expense {$referenceNumber} - Payment", 0, $amount, 2, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Expense {$referenceNumber} - Payment", 0, $amount, 2);
        }
        $insertJEL->execute();
        
        $insertJEL->close();
        
        // Post to general ledger
        $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
        
        return [
            'success' => true,
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Expense journal entry created successfully',
            'ledger_result' => $ledgerResult
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create automatic journal entry for Receipt Voucher
 * Dr Cash/Bank, Cr Revenue
 * 
 * @param mysqli $conn Database connection
 * @param int $receiptId Receipt ID
 * @param string $receiptNumber Receipt number
 * @param string $receiptDate Receipt date
 * @param float $amount Receipt amount
 * @param int|null $bankAccountId Bank account ID (if null, uses Cash account)
 * @param int|null $costCenterId Cost center ID (optional)
 * @param string $description Description
 * @return array Result with journal_entry_id and success status
 */
function createReceiptJournalEntry($conn, $receiptId, $receiptNumber, $receiptDate, $amount, $bankAccountId = null, $costCenterId = null, $description = '') {
    try {
        // Validate amount
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Receipt amount must be greater than 0'];
        }
        
        // Check if period is locked
        $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
        if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
            $closingsTableCheck->free();
            $periodCheck = $conn->prepare("
                SELECT COUNT(*) as locked_count
                FROM financial_closings
                WHERE status = 'Completed'
                AND ? >= period_start_date 
                AND ? <= period_end_date
            ");
            $periodCheck->bind_param('ss', $receiptDate, $receiptDate);
            $periodCheck->execute();
            $periodResult = $periodCheck->get_result();
            $periodRow = $periodResult->fetch_assoc();
            $isPeriodLocked = ($periodRow['locked_count'] ?? 0) > 0;
            $periodResult->free();
            $periodCheck->close();
            
            if ($isPeriodLocked) {
                return ['success' => false, 'message' => 'Cannot create journal entry - period is locked (completed closing). Receipt date falls within a closed period.'];
            }
        } else {
            if ($closingsTableCheck) {
                $closingsTableCheck->free();
            }
        }
        
        // Get account mappings
        $accountMap = [];
        $accountQuery = $conn->query("SELECT id, account_code, account_name, account_type, normal_balance FROM financial_accounts WHERE is_active = 1");
        while ($row = $accountQuery->fetch_assoc()) {
            $accountMap[$row['account_code']] = $row;
        }
        $accountQuery->free();
        
        if (empty($accountMap)) {
            return ['success' => false, 'message' => 'No accounts found'];
        }
        
        // Determine cash/bank account (same logic as payment)
        $cashAccountCode = '1100';
        $cashAccountId = $accountMap[$cashAccountCode]['id'] ?? null;
        
        if ($bankAccountId) {
            $bankCheck = $conn->prepare("SELECT account_id FROM accounting_banks WHERE id = ? AND is_active = 1");
            if ($bankCheck) {
                $bankCheck->bind_param('i', $bankAccountId);
                $bankCheck->execute();
                $bankResult = $bankCheck->get_result();
                if ($bankRow = $bankResult->fetch_assoc()) {
                    $mappedAccountId = $bankRow['account_id'] ?? null;
                    if ($mappedAccountId) {
                        $cashAccountId = $mappedAccountId;
                        $accountCheck = $conn->prepare("SELECT account_code FROM financial_accounts WHERE id = ?");
                        $accountCheck->bind_param('i', $mappedAccountId);
                        $accountCheck->execute();
                        $accResult = $accountCheck->get_result();
                        if ($accRow = $accResult->fetch_assoc()) {
                            $cashAccountCode = $accRow['account_code'];
                        }
                        $accResult->free();
                        $accountCheck->close();
                    }
                }
                $bankResult->free();
                $bankCheck->close();
            }
        }
        
        if (!$cashAccountId) {
            return ['success' => false, 'message' => 'Cash/Bank account not found'];
        }
        
        // Revenue account
        $revenueAccountCode = '4100';
        $revenueAccountId = $accountMap[$revenueAccountCode]['id'] ?? $accountMap['4000']['id'] ?? null;
        
        if (!$revenueAccountId) {
            return ['success' => false, 'message' => 'Revenue account not found'];
        }
        
        // Generate journal entry number
        $entryNumber = 'JE-REC-' . $receiptNumber;
        
        // Check if journal entry already exists
        $checkStmt = $conn->prepare("SELECT id FROM journal_entries WHERE entry_number = ?");
        $checkStmt->bind_param('s', $entryNumber);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $checkResult->free();
            $checkStmt->close();
            return ['success' => true, 'message' => 'Journal entry already exists', 'journal_entry_id' => null];
        }
        $checkResult->free();
        $checkStmt->close();
        
        // Check if is_posted and is_locked columns exist
        $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
        $hasIsPosted = $isPostedCheck->num_rows > 0;
        $isPostedCheck->free();
        
        $isLockedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_locked'");
        $hasIsLocked = $isLockedCheck->num_rows > 0;
        $isLockedCheck->free();
        
        // Build INSERT query dynamically
        $insertFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'created_by'];
        $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?'];
        $bindParams = [];
        $bindTypes = '';
        
        if ($hasIsPosted) {
            $insertFields[] = 'is_posted';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        if ($hasIsLocked) {
            $insertFields[] = 'is_locked';
            $insertValues[] = '?';
            $bindParams[] = 1;
            $bindTypes .= 'i';
        }
        
        $entryType = 'Receipt Voucher';
        $totalDebit = $amount;
        $totalCredit = $amount;
        
        $userId = $_SESSION['user_id'] ?? 1;
        $allBindParams = [
            $entryNumber,
            $receiptDate,
            $description ?: "Receipt {$receiptNumber}",
            $entryType,
            $totalDebit,
            $totalCredit,
            'Posted',
            $userId
        ];
        $allBindParams = array_merge($allBindParams, $bindParams);
        $allBindTypes = 'ssssddsi' . $bindTypes;
        
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
        
        // Check if cost_center_id column exists
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
        $hasCostCenter = $costCenterCheck->num_rows > 0;
        $costCenterCheck->free();
        
        // Create journal entry lines
        if ($hasCostCenter && $costCenterId) {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order, cost_center_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $insertJEL = $conn->prepare("
                INSERT INTO journal_entry_lines (
                    journal_entry_id, account_id, account_code, account_name,
                    description, debit_amount, credit_amount, line_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }
        
        // Line 1: Debit Cash/Bank
        $cashCode = $cashAccountCode;
        $cashName = $accountMap[$cashAccountCode]['account_name'] ?? 'Cash';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Receipt {$receiptNumber}", $amount, 0, 1, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $cashAccountId, $cashCode, $cashName, "Receipt {$receiptNumber}", $amount, 0, 1);
        }
        $insertJEL->execute();
        
        // Line 2: Credit Revenue
        $revenueCode = $accountMap[$revenueAccountCode]['account_code'] ?? $revenueAccountCode;
        $revenueName = $accountMap[$revenueAccountCode]['account_name'] ?? 'Revenue';
        if ($hasCostCenter && $costCenterId) {
            $insertJEL->bind_param('iissddii', $journalEntryId, $revenueAccountId, $revenueCode, $revenueName, "Receipt {$receiptNumber} - Revenue", 0, $amount, 2, $costCenterId);
        } else {
            $insertJEL->bind_param('iissddi', $journalEntryId, $revenueAccountId, $revenueCode, $revenueName, "Receipt {$receiptNumber} - Revenue", 0, $amount, 2);
        }
        $insertJEL->execute();
        
        $insertJEL->close();
        
        // Post to general ledger
        $ledgerResult = postJournalEntryToLedger($conn, $journalEntryId);
        
        return [
            'success' => true,
            'journal_entry_id' => $journalEntryId,
            'entry_number' => $entryNumber,
            'message' => 'Receipt journal entry created successfully',
            'ledger_result' => $ledgerResult
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

?>