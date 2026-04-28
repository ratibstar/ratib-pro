<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/erp-posting-controls.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/erp-posting-controls.php`.
 */
/**
 * ERP-Grade Posting Controls
 * 
 * PHASE 2: Strict posting rules enforcement
 * 
 * Rules:
 * - Draft journals can be edited
 * - Posted journals cannot be edited or deleted
 * - Reversals must be separate entries
 * - Prevent backdated posting in closed periods
 */

/**
 * Check if a journal entry can be edited
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @return array ['can_edit' => bool, 'reason' => string]
 */
function canEditJournalEntry($conn, $journalEntryId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            status,
            posting_status,
            is_posted,
            is_locked,
            entry_date,
            fiscal_period_id
        FROM journal_entries
        WHERE id = ?
    ");
    $stmt->bind_param('i', $journalEntryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $result->free();
        $stmt->close();
        return ['can_edit' => false, 'reason' => 'Journal entry not found'];
    }
    
    $entry = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    
    // Rule 1: Posted entries cannot be edited
    if ($entry['is_posted'] == 1 || $entry['posting_status'] === 'posted' || $entry['status'] === 'Posted') {
        return ['can_edit' => false, 'reason' => 'Posted journal entries cannot be edited'];
    }
    
    // Rule 2: Locked entries cannot be edited
    if ($entry['is_locked'] == 1) {
        return ['can_edit' => false, 'reason' => 'Locked journal entries cannot be edited'];
    }
    
    // Rule 3: Reversed entries cannot be edited
    if ($entry['posting_status'] === 'reversed') {
        return ['can_edit' => false, 'reason' => 'Reversed journal entries cannot be edited'];
    }
    
    // Rule 4: Check if period is closed
    if ($entry['fiscal_period_id']) {
        $periodCheck = $conn->prepare("
            SELECT is_closed 
            FROM fiscal_periods 
            WHERE id = ?
        ");
        $periodCheck->bind_param('i', $entry['fiscal_period_id']);
        $periodCheck->execute();
        $periodResult = $periodCheck->get_result();
        if ($periodResult->num_rows > 0) {
            $period = $periodResult->fetch_assoc();
            if ($period['is_closed'] == 1) {
                $periodResult->free();
                $periodCheck->close();
                return ['can_edit' => false, 'reason' => 'Cannot edit entries in closed fiscal period'];
            }
        }
        $periodResult->free();
        $periodCheck->close();
    }
    
    return ['can_edit' => true, 'reason' => ''];
}

/**
 * Check if a journal entry can be deleted
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @return array ['can_delete' => bool, 'reason' => string]
 */
function canDeleteJournalEntry($conn, $journalEntryId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            status,
            posting_status,
            is_posted,
            is_locked,
            fiscal_period_id
        FROM journal_entries
        WHERE id = ?
    ");
    $stmt->bind_param('i', $journalEntryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $result->free();
        $stmt->close();
        return ['can_delete' => false, 'reason' => 'Journal entry not found'];
    }
    
    $entry = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    
    // Rule 1: Posted entries cannot be deleted
    if ($entry['is_posted'] == 1 || $entry['posting_status'] === 'posted' || $entry['status'] === 'Posted') {
        return ['can_delete' => false, 'reason' => 'Posted journal entries cannot be deleted. Use reversal entry instead.'];
    }
    
    // Rule 2: Locked entries cannot be deleted
    if ($entry['is_locked'] == 1) {
        return ['can_delete' => false, 'reason' => 'Locked journal entries cannot be deleted'];
    }
    
    // Rule 3: Reversed entries cannot be deleted
    if ($entry['posting_status'] === 'reversed') {
        return ['can_delete' => false, 'reason' => 'Reversed journal entries cannot be deleted'];
    }
    
    // Rule 4: Check if period is closed
    if ($entry['fiscal_period_id']) {
        $periodCheck = $conn->prepare("
            SELECT is_closed 
            FROM fiscal_periods 
            WHERE id = ?
        ");
        $periodCheck->bind_param('i', $entry['fiscal_period_id']);
        $periodCheck->execute();
        $periodResult = $periodCheck->get_result();
        if ($periodResult->num_rows > 0) {
            $period = $periodResult->fetch_assoc();
            if ($period['is_closed'] == 1) {
                $periodResult->free();
                $periodCheck->close();
                return ['can_delete' => false, 'reason' => 'Cannot delete entries in closed fiscal period'];
            }
        }
        $periodResult->free();
        $periodCheck->close();
    }
    
    // Rule 5: Check if entry has been posted to general ledger
    $glCheck = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM general_ledger 
        WHERE journal_entry_id = ?
    ");
    $glCheck->bind_param('i', $journalEntryId);
    $glCheck->execute();
    $glResult = $glCheck->get_result();
    $glData = $glResult->fetch_assoc();
    $glCheck->close();
    $glResult->free();
    
    if ($glData['count'] > 0) {
        return ['can_delete' => false, 'reason' => 'Journal entry has been posted to general ledger and cannot be deleted'];
    }
    
    return ['can_delete' => true, 'reason' => ''];
}

/**
 * Check if a date falls within a closed period
 * 
 * @param mysqli $conn Database connection
 * @param string $entryDate Date to check (YYYY-MM-DD)
 * @return array ['is_locked' => bool, 'period' => array|null, 'reason' => string]
 */
function isPeriodClosed($conn, $entryDate) {
    // Check fiscal_periods table
    $periodCheck = $conn->prepare("
        SELECT 
            id,
            period_name,
            period_code,
            start_date,
            end_date,
            is_closed,
            closed_at,
            closed_by
        FROM fiscal_periods
        WHERE ? >= start_date 
        AND ? <= end_date
        AND is_closed = 1
        LIMIT 1
    ");
    $periodCheck->bind_param('ss', $entryDate, $entryDate);
    $periodCheck->execute();
    $periodResult = $periodCheck->get_result();
    
    if ($periodResult->num_rows > 0) {
        $period = $periodResult->fetch_assoc();
        $periodResult->free();
        $periodCheck->close();
        return [
            'is_locked' => true,
            'period' => $period,
            'reason' => "Date falls within closed period: {$period['period_name']}"
        ];
    }
    $periodResult->free();
    $periodCheck->close();
    
    // Also check financial_closings table (backward compatibility)
    $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
    if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
        $closingsTableCheck->free();
        $closingCheck = $conn->prepare("
            SELECT 
                id,
                closing_name,
                period_start_date,
                period_end_date,
                status
            FROM financial_closings
            WHERE status = 'Completed'
            AND ? >= period_start_date 
            AND ? <= period_end_date
            LIMIT 1
        ");
        $closingCheck->bind_param('ss', $entryDate, $entryDate);
        $closingCheck->execute();
        $closingResult = $closingCheck->get_result();
        
        if ($closingResult->num_rows > 0) {
            $closing = $closingResult->fetch_assoc();
            $closingResult->free();
            $closingCheck->close();
            return [
                'is_locked' => true,
                'period' => [
                    'period_name' => $closing['closing_name'],
                    'start_date' => $closing['period_start_date'],
                    'end_date' => $closing['period_end_date']
                ],
                'reason' => "Date falls within completed closing: {$closing['closing_name']}"
            ];
        }
        $closingResult->free();
        $closingCheck->close();
    } else {
        if ($closingsTableCheck) {
            $closingsTableCheck->free();
        }
    }
    
    return ['is_locked' => false, 'period' => null, 'reason' => ''];
}

/**
 * Validate posting date is not in closed period
 * 
 * @param mysqli $conn Database connection
 * @param string $entryDate Date to validate (YYYY-MM-DD)
 * @throws Exception If date is in closed period
 */
function validatePostingDate($conn, $entryDate) {
    $periodCheck = isPeriodClosed($conn, $entryDate);
    if ($periodCheck['is_locked']) {
        throw new Exception($periodCheck['reason']);
    }
}

/**
 * Create a reversal journal entry
 * 
 * @param mysqli $conn Database connection
 * @param int $originalEntryId Original journal entry ID to reverse
 * @param string $reversalDate Date for reversal entry (YYYY-MM-DD)
 * @param string $description Description for reversal entry
 * @param int $userId User ID creating the reversal
 * @return array ['success' => bool, 'reversal_entry_id' => int|null, 'message' => string]
 */
function createReversalEntry($conn, $originalEntryId, $reversalDate, $description, $userId) {
    // Validate original entry exists and is posted
    $originalStmt = $conn->prepare("
        SELECT 
            id,
            entry_number,
            entry_date,
            description,
            total_debit,
            total_credit,
            currency,
            branch_id,
            fiscal_period_id,
            status,
            posting_status,
            is_posted
        FROM journal_entries
        WHERE id = ?
    ");
    $originalStmt->bind_param('i', $originalEntryId);
    $originalStmt->execute();
    $originalResult = $originalStmt->get_result();
    
    if ($originalResult->num_rows === 0) {
        $originalResult->free();
        $originalStmt->close();
        return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Original journal entry not found'];
    }
    
    $original = $originalResult->fetch_assoc();
    $originalResult->free();
    $originalStmt->close();
    
    // Validate original entry is posted
    if ($original['is_posted'] != 1 && $original['posting_status'] !== 'posted' && $original['status'] !== 'Posted') {
        return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Can only reverse posted journal entries'];
    }
    
    // Validate reversal date is not in closed period
    try {
        validatePostingDate($conn, $reversalDate);
    } catch (Exception $e) {
        return ['success' => false, 'reversal_entry_id' => null, 'message' => $e->getMessage()];
    }
    
    // Get original entry lines
    $linesStmt = $conn->prepare("
        SELECT 
            account_id,
            debit_amount,
            credit_amount,
            cost_center_id,
            description,
            entity_type,
            entity_id,
            vat_report
        FROM journal_entry_lines
        WHERE journal_entry_id = ?
    ");
    $linesStmt->bind_param('i', $originalEntryId);
    $linesStmt->execute();
    $linesResult = $linesStmt->get_result();
    
    if ($linesResult->num_rows === 0) {
        $linesResult->free();
        $linesStmt->close();
        return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Original journal entry has no lines'];
    }
    
    // Generate reversal entry number
    $nextNumStmt = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(entry_number, 4) AS UNSIGNED)), 0) + 1 as next_num FROM journal_entries WHERE entry_number LIKE 'JE-%'");
    $nextNumRow = $nextNumStmt->fetch_assoc();
    $nextNum = $nextNumRow['next_num'];
    $nextNumStmt->free();
    $reversalEntryNumber = 'JE-' . str_pad($nextNum, 8, '0', STR_PAD_LEFT);
    
    // Create reversal entry header (swap debits and credits)
    $reversalDescription = $description ?: "Reversal of {$original['entry_number']}: {$original['description']}";
    
    $insertFields = ['entry_number', 'entry_date', 'description', 'entry_type', 'total_debit', 'total_credit', 'status', 'posting_status', 'is_auto', 'source_table', 'source_id', 'created_by'];
    $insertValues = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
    $bindParams = [
        $reversalEntryNumber,
        $reversalDate,
        $reversalDescription,
        'Reversal',
        $original['total_credit'], // Swap: original credit becomes debit
        $original['total_debit'],   // Swap: original debit becomes credit
        'Draft',
        'draft',
        0, // Manual reversal
        'journal_entries',
        $originalEntryId,
        $userId
    ];
    $bindTypes = 'ssssddsssisi';
    
    // Add optional fields if they exist
    $currencyCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'currency'");
    $hasCurrency = $currencyCheck && $currencyCheck->num_rows > 0;
    if ($currencyCheck) $currencyCheck->free();
    if ($hasCurrency && !empty($original['currency'])) {
        $insertFields[] = 'currency';
        $insertValues[] = '?';
        $bindParams[] = $original['currency'];
        $bindTypes .= 's';
    }
    
    $branchCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'branch_id'");
    $hasBranch = $branchCheck && $branchCheck->num_rows > 0;
    if ($branchCheck) $branchCheck->free();
    if ($hasBranch && !empty($original['branch_id'])) {
        $insertFields[] = 'branch_id';
        $insertValues[] = '?';
        $bindParams[] = $original['branch_id'];
        $bindTypes .= 'i';
    }
    
    $fiscalPeriodCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'fiscal_period_id'");
    $hasFiscalPeriod = $fiscalPeriodCheck && $fiscalPeriodCheck->num_rows > 0;
    if ($fiscalPeriodCheck) $fiscalPeriodCheck->free();
    if ($hasFiscalPeriod && !empty($original['fiscal_period_id'])) {
        // Determine fiscal period for reversal date
        $revPeriodStmt = $conn->prepare("
            SELECT id FROM fiscal_periods 
            WHERE ? >= start_date AND ? <= end_date 
            LIMIT 1
        ");
        $revPeriodStmt->bind_param('ss', $reversalDate, $reversalDate);
        $revPeriodStmt->execute();
        $revPeriodResult = $revPeriodStmt->get_result();
        if ($revPeriodResult->num_rows > 0) {
            $revPeriod = $revPeriodResult->fetch_assoc();
            $insertFields[] = 'fiscal_period_id';
            $insertValues[] = '?';
            $bindParams[] = $revPeriod['id'];
            $bindTypes .= 'i';
        }
        $revPeriodResult->free();
        $revPeriodStmt->close();
    }
    
    $insertSql = "INSERT INTO journal_entries (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Failed to prepare reversal entry: ' . $conn->error];
    }
    
    $insertStmt->bind_param($bindTypes, ...$bindParams);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Failed to create reversal entry: ' . $insertStmt->error];
    }
    
    $reversalEntryId = $conn->insert_id;
    $insertStmt->close();
    
    // Create reversal lines (swap debits and credits)
    $lineInsertFields = ['journal_entry_id', 'account_id', 'debit_amount', 'credit_amount'];
    $lineInsertValues = ['?', '?', '?', '?'];
    $lineBindTypes = 'iidd';
    
    $hasCostCenter = false;
    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
    if ($costCenterCheck && $costCenterCheck->num_rows > 0) {
        $hasCostCenter = true;
        $lineInsertFields[] = 'cost_center_id';
        $lineInsertValues[] = '?';
        $lineBindTypes .= 'i';
    }
    if ($costCenterCheck) $costCenterCheck->free();
    
    $hasDescription = false;
    $descCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'description'");
    if ($descCheck && $descCheck->num_rows > 0) {
        $hasDescription = true;
        $lineInsertFields[] = 'description';
        $lineInsertValues[] = '?';
        $lineBindTypes .= 's';
    }
    if ($descCheck) $descCheck->free();
    
    $hasEntityType = false;
    $hasEntityId = false;
    $entityTypeCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_type'");
    if ($entityTypeCheck && $entityTypeCheck->num_rows > 0) {
        $hasEntityType = true;
        $lineInsertFields[] = 'entity_type';
        $lineInsertValues[] = '?';
        $lineBindTypes .= 's';
    }
    if ($entityTypeCheck) $entityTypeCheck->free();
    
    $entityIdCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'entity_id'");
    if ($entityIdCheck && $entityIdCheck->num_rows > 0) {
        $hasEntityId = true;
        $lineInsertFields[] = 'entity_id';
        $lineInsertValues[] = '?';
        $lineBindTypes .= 'i';
    }
    if ($entityIdCheck) $entityIdCheck->free();
    
    $lineInsertSql = "INSERT INTO journal_entry_lines (" . implode(', ', $lineInsertFields) . ") VALUES (" . implode(', ', $lineInsertValues) . ")";
    $lineInsertStmt = $conn->prepare($lineInsertSql);
    
    while ($line = $linesResult->fetch_assoc()) {
        $lineBindParams = [
            $reversalEntryId,
            $line['account_id'],
            $line['credit_amount'], // Swap: original credit becomes debit
            $line['debit_amount']   // Swap: original debit becomes credit
        ];
        
        if ($hasCostCenter) {
            $lineBindParams[] = $line['cost_center_id'] ?? null;
        }
        if ($hasDescription) {
            $lineBindParams[] = $line['description'] ?? null;
        }
        if ($hasEntityType) {
            $lineBindParams[] = $line['entity_type'] ?? null;
        }
        if ($hasEntityId) {
            $lineBindParams[] = $line['entity_id'] ?? null;
        }
        
        $lineInsertStmt->bind_param($lineBindTypes, ...$lineBindParams);
        if (!$lineInsertStmt->execute()) {
            $lineInsertStmt->close();
            $linesResult->free();
            $linesStmt->close();
            // Rollback reversal entry
            $conn->query("DELETE FROM journal_entries WHERE id = $reversalEntryId");
            return ['success' => false, 'reversal_entry_id' => null, 'message' => 'Failed to create reversal line: ' . $lineInsertStmt->error];
        }
    }
    
    $lineInsertStmt->close();
    $linesResult->free();
    $linesStmt->close();
    
    // Mark original entry as reversed
    $updateOriginalStmt = $conn->prepare("
        UPDATE journal_entries 
        SET posting_status = 'reversed',
            source_id = ?
        WHERE id = ?
    ");
    $updateOriginalStmt->bind_param('ii', $reversalEntryId, $originalEntryId);
    $updateOriginalStmt->execute();
    $updateOriginalStmt->close();
    
    return [
        'success' => true,
        'reversal_entry_id' => $reversalEntryId,
        'message' => "Reversal entry {$reversalEntryNumber} created successfully"
    ];
}
