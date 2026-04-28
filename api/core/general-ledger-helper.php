<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/general-ledger-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/general-ledger-helper.php`.
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
    
    // Check if journal entry exists
    $entryCheck = $conn->prepare("SELECT id, entry_date FROM journal_entries WHERE id = ?");
    $entryCheck->bind_param('i', $journalEntryId);
    $entryCheck->execute();
    $entryResult = $entryCheck->get_result();
    
    if ($entryResult->num_rows === 0) {
        $entryResult->free();
        $entryCheck->close();
        throw new Exception("Journal entry {$journalEntryId} does not exist");
    }
    
    $entryData = $entryResult->fetch_assoc();
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
    
    // Get all lines for this journal entry
    $linesQuery = $conn->prepare("
        SELECT 
            account_id,
            COALESCE(debit_amount, 0) as debit_amount,
            COALESCE(credit_amount, 0) as credit_amount
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
    
    // Prepare insert statement
    $insertStmt = $conn->prepare("
        INSERT INTO general_ledger (account_id, journal_entry_id, debit, credit, posting_date)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Insert each line into general_ledger
    while ($line = $linesResult->fetch_assoc()) {
        $accountId = $line['account_id'];
        $debit = floatval($line['debit_amount']);
        $credit = floatval($line['credit_amount']);
        
        // Skip lines with zero amounts or invalid account_id
        if (($debit == 0 && $credit == 0) || !$accountId || $accountId <= 0) {
            continue;
        }
        
        $hasLines = true;
        $totalDebit += $debit;
        $totalCredit += $credit;
        
        $insertStmt->bind_param('iidds', $accountId, $journalEntryId, $debit, $credit, $postingDate);
        
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
