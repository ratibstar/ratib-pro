<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/fiscal-period-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/fiscal-period-helper.php`.
 */
/**
 * Fiscal Period Helper Functions
 * 
 * Functions to determine and manage fiscal periods
 */

/**
 * Get fiscal period ID for a given date
 * 
 * @param mysqli $conn Database connection
 * @param string $entryDate Date (YYYY-MM-DD)
 * @return int|null Fiscal period ID or null if not found
 */
function getFiscalPeriodId($conn, $entryDate) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'fiscal_periods'");
    $hasPeriodsTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) $tableCheck->free();
    
    if (!$hasPeriodsTable) {
        return null; // Table doesn't exist yet
    }
    
    $stmt = $conn->prepare("
        SELECT id 
        FROM fiscal_periods 
        WHERE ? >= start_date 
        AND ? <= end_date
        AND is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param('ss', $entryDate, $entryDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $periodId = intval($row['id']);
        $result->free();
        $stmt->close();
        return $periodId;
    }
    
    $result->free();
    $stmt->close();
    return null;
}

/**
 * Auto-populate fiscal_period_id for a journal entry
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param string|null $entryDate Entry date (YYYY-MM-DD), if null will fetch from DB
 * @return bool Success status
 */
function autoPopulateFiscalPeriod($conn, $journalEntryId, $entryDate = null) {
    // Check if fiscal_period_id column exists
    $colCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'fiscal_period_id'");
    $hasFiscalPeriodCol = $colCheck && $colCheck->num_rows > 0;
    if ($colCheck) $colCheck->free();
    
    if (!$hasFiscalPeriodCol) {
        return false; // Column doesn't exist yet
    }
    
    // Get entry_date if not provided
    if ($entryDate === null) {
        $dateStmt = $conn->prepare("SELECT entry_date FROM journal_entries WHERE id = ?");
        $dateStmt->bind_param('i', $journalEntryId);
        $dateStmt->execute();
        $dateResult = $dateStmt->get_result();
        if ($dateResult->num_rows === 0) {
            $dateResult->free();
            $dateStmt->close();
            return false;
        }
        $dateRow = $dateResult->fetch_assoc();
        $entryDate = $dateRow['entry_date'];
        $dateResult->free();
        $dateStmt->close();
    }
    
    if (empty($entryDate)) {
        return false; // No date to work with
    }
    
    // Get fiscal period ID
    $fiscalPeriodId = getFiscalPeriodId($conn, $entryDate);
    
    if ($fiscalPeriodId === null) {
        return false; // No matching period found
    }
    
    // Update journal entry
    $updateStmt = $conn->prepare("UPDATE journal_entries SET fiscal_period_id = ? WHERE id = ? AND (fiscal_period_id IS NULL OR fiscal_period_id = 0)");
    $updateStmt->bind_param('ii', $fiscalPeriodId, $journalEntryId);
    $success = $updateStmt->execute();
    $updateStmt->close();
    
    return $success;
}
