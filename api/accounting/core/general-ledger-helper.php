<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/general-ledger-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/general-ledger-helper.php`.
 */
/**
 * General Ledger Helper Functions
 * 
 * Functions to handle posting journal entries to general ledger
 */

/**
 * Post a journal entry to the general ledger
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID to post
 * @return array ['success' => bool, 'message' => string, 'entries_created' => int]
 * @throws Exception On validation or insertion failure
 */
function postJournalEntryToLedger($conn, $journalEntryId) {
    // Validate journal entry ID
    if (!$journalEntryId || $journalEntryId <= 0) {
        throw new Exception('Invalid journal entry ID');
    }
    
    // Check if general_ledger table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $tableCheck->num_rows > 0;
    $tableCheck->free();
    
    if (!$hasGLTable) {
        throw new Exception('general_ledger table does not exist. Please run migration first.');
    }
    
    // Check if journal entry exists and is Posted
    $entryCheck = $conn->prepare("
        SELECT 
            id, 
            entry_date, 
            status, 
            posting_status, 
            is_posted 
        FROM journal_entries 
        WHERE id = ?
    ");
    $entryCheck->bind_param('i', $journalEntryId);
    $entryCheck->execute();
    $entryResult = $entryCheck->get_result();
    
    if ($entryResult->num_rows === 0) {
        $entryResult->free();
        $entryCheck->close();
        throw new Exception("Journal entry {$journalEntryId} does not exist");
    }
    
    $entryData = $entryResult->fetch_assoc();
    
    // ERP VALIDATION: Only posted entries can be posted to GL
    $isPosted = ($entryData['status'] === 'Posted' || 
                $entryData['posting_status'] === 'posted' || 
                $entryData['is_posted'] == 1);
    
    if (!$isPosted) {
        $entryResult->free();
        $entryCheck->close();
        throw new Exception("Journal entry {$journalEntryId} is not Posted. Only Posted entries can be posted to general ledger.");
    }
    
    $postingDate = $entryData['entry_date'];
    
    // Validate posting date is not NULL
    if (empty($postingDate)) {
        $entryResult->free();
        $entryCheck->close();
        throw new Exception("Journal entry {$journalEntryId} has no entry_date. Cannot post to general ledger.");
    }
    
    $entryResult->free();
    $entryCheck->close();
    
    // Check if entry already posted to ledger
    $existsCheck = $conn->prepare("SELECT COUNT(*) as count FROM general_ledger WHERE journal_entry_id = ?");
    $existsCheck->bind_param('i', $journalEntryId);
    $existsCheck->execute();
    $existsResult = $existsCheck->get_result();
    $existsData = $existsResult->fetch_assoc();
    $existsCheck->close();
    $existsResult->free();
    
    if ($existsData['count'] > 0) {
        return [
            'success' => true,
            'message' => 'Journal entry already posted to general ledger',
            'entries_created' => 0
        ];
    }
    
    // Get journal entry header data for ERP fields
    $entryHeaderStmt = $conn->prepare("
        SELECT 
            branch_id,
            fiscal_period_id,
            approved_by
        FROM journal_entries
        WHERE id = ?
    ");
    $entryHeaderStmt->bind_param('i', $journalEntryId);
    $entryHeaderStmt->execute();
    $entryHeaderResult = $entryHeaderStmt->get_result();
    $entryHeader = $entryHeaderResult->fetch_assoc();
    $entryHeaderStmt->close();
    $entryHeaderResult->free();
    
    $branchId = $entryHeader['branch_id'] ?? null;
    $fiscalPeriodId = $entryHeader['fiscal_period_id'] ?? null;
    $approvedBy = $entryHeader['approved_by'] ?? null;
    
    // Get all lines for this journal entry (with ERP fields)
    $linesQuery = $conn->prepare("
        SELECT 
            account_id,
            COALESCE(debit_amount, 0) as debit_amount,
            COALESCE(credit_amount, 0) as credit_amount,
            cost_center_id
        FROM journal_entry_lines
        WHERE journal_entry_id = ?
    ");
    $linesQuery->bind_param('i', $journalEntryId);
    $linesQuery->execute();
    $linesResult = $linesQuery->get_result();
    
    $totalDebit = 0;
    $totalCredit = 0;
    $entriesCreated = 0;
    $hasLines = false;
    
    // Check which ERP columns exist in general_ledger
    $glBranchCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'branch_id'");
    $hasGLBranch = $glBranchCheck && $glBranchCheck->num_rows > 0;
    if ($glBranchCheck) $glBranchCheck->free();
    
    $glCostCenterCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'cost_center_id'");
    $hasGLCostCenter = $glCostCenterCheck && $glCostCenterCheck->num_rows > 0;
    if ($glCostCenterCheck) $glCostCenterCheck->free();
    
    $glFiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'fiscal_period_id'");
    $hasGLFiscalPeriod = $glFiscalPeriodCheck && $glFiscalPeriodCheck->num_rows > 0;
    if ($glFiscalPeriodCheck) $glFiscalPeriodCheck->free();
    
    $glApprovedByCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'approved_by'");
    $hasGLApprovedBy = $glApprovedByCheck && $glApprovedByCheck->num_rows > 0;
    if ($glApprovedByCheck) $glApprovedByCheck->free();
    
    // Build dynamic INSERT statement based on available columns
    $insertFields = ['account_id', 'journal_entry_id', 'debit', 'credit', 'posting_date'];
    $insertValues = ['?', '?', '?', '?', '?'];
    $bindTypes = 'iidds';
    $bindParamsBase = [];
    
    if ($hasGLBranch) {
        $insertFields[] = 'branch_id';
        $insertValues[] = '?';
        $bindTypes .= 'i';
    }
    
    if ($hasGLCostCenter) {
        $insertFields[] = 'cost_center_id';
        $insertValues[] = '?';
        $bindTypes .= 'i';
    }
    
    if ($hasGLFiscalPeriod) {
        $insertFields[] = 'fiscal_period_id';
        $insertValues[] = '?';
        $bindTypes .= 'i';
    }
    
    if ($hasGLApprovedBy) {
        $insertFields[] = 'approved_by';
        $insertValues[] = '?';
        $bindTypes .= 'i';
    }
    
    $insertSql = "INSERT INTO general_ledger (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
    $insertStmt = $conn->prepare($insertSql);
    
    // Insert each line into general_ledger
    while ($line = $linesResult->fetch_assoc()) {
        $accountId = $line['account_id'];
        $debit = floatval($line['debit_amount']);
        $credit = floatval($line['credit_amount']);
        $costCenterId = isset($line['cost_center_id']) && $line['cost_center_id'] > 0 ? intval($line['cost_center_id']) : null;
        
        // Skip lines with zero amounts or invalid account_id
        if (($debit == 0 && $credit == 0) || !$accountId || $accountId <= 0) {
            continue;
        }
        
        $hasLines = true;
        $totalDebit += $debit;
        $totalCredit += $credit;
        
        // Build bind parameters dynamically
        $bindParams = [$accountId, $journalEntryId, $debit, $credit, $postingDate];
        
        if ($hasGLBranch) {
            $bindParams[] = $branchId;
        }
        
        if ($hasGLCostCenter) {
            $bindParams[] = $costCenterId;
        }
        
        if ($hasGLFiscalPeriod) {
            $bindParams[] = $fiscalPeriodId;
        }
        
        if ($hasGLApprovedBy) {
            $bindParams[] = $approvedBy;
        }
        
        $insertStmt->bind_param($bindTypes, ...$bindParams);
        
        if (!$insertStmt->execute()) {
            $insertStmt->close();
            $linesQuery->close();
            $linesResult->free();
            
            // Rollback by deleting any entries we've already inserted
            $deleteStmt = $conn->prepare("DELETE FROM general_ledger WHERE journal_entry_id = ?");
            $deleteStmt->bind_param('i', $journalEntryId);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            throw new Exception("Failed to insert into general_ledger: " . $insertStmt->error);
        }
        
        $entriesCreated++;
    }
    
    $insertStmt->close();
    $linesQuery->close();
    $linesResult->free();
    
    // Validate double-entry compliance: total debit must equal total credit
    if ($hasLines) {
        $balanceDiff = abs($totalDebit - $totalCredit);
        
        if ($balanceDiff > 0.01) {
            // Rollback by deleting entries we just inserted
            $deleteStmt = $conn->prepare("DELETE FROM general_ledger WHERE journal_entry_id = ?");
            $deleteStmt->bind_param('i', $journalEntryId);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            throw new Exception("Double-entry violation: Debit total ({$totalDebit}) does not equal Credit total ({$totalCredit}). Difference: {$balanceDiff}");
        }
    } else {
        // No lines found - don't create ledger entries
        return [
            'success' => true,
            'message' => 'Journal entry has no lines to post to general ledger',
            'entries_created' => 0
        ];
    }
    
    return [
        'success' => true,
        'message' => "Journal entry posted to general ledger successfully. {$entriesCreated} entries created.",
        'entries_created' => $entriesCreated,
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit
    ];
}
