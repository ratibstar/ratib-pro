<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/audit-trail-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/audit-trail-helper.php`.
 */
/**
 * Audit Trail Helper Functions
 * 
 * Functions to log all changes to journal entries and general ledger
 */

/**
 * Log an audit trail entry
 * 
 * @param mysqli $conn Database connection
 * @param string $tableName Table name (e.g., 'journal_entries', 'general_ledger')
 * @param int $recordId Record ID
 * @param string $action Action type (CREATE, UPDATE, DELETE, POST, REVERSE, APPROVE, REJECT)
 * @param string|null $fieldName Field name (for UPDATE actions)
 * @param mixed|null $oldValue Old value (for UPDATE actions)
 * @param mixed|null $newValue New value (for UPDATE actions)
 * @param int|null $userId User ID (defaults to session user)
 * @param string|null $notes Additional notes
 * @return bool Success status
 */
function logAuditTrail($conn, $tableName, $recordId, $action, $fieldName = null, $oldValue = null, $newValue = null, $userId = null, $notes = null) {
    // Check if audit_trail table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'audit_trail'");
    $hasAuditTable = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) $tableCheck->free();
    
    if (!$hasAuditTable) {
        // Silently fail if audit table doesn't exist (backward compatibility)
        return false;
    }
    
    // Get user ID from session if not provided
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    
    if (!$userId) {
        return false; // Cannot log without user ID
    }
    
    // Get IP address and user agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert values to strings for storage
    $oldValueStr = $oldValue !== null ? (is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : strval($oldValue)) : null;
    $newValueStr = $newValue !== null ? (is_array($newValue) || is_object($newValue) ? json_encode($newValue) : strval($newValue)) : null;
    
    // Truncate long values (TEXT field limit)
    if ($oldValueStr && strlen($oldValueStr) > 65535) {
        $oldValueStr = substr($oldValueStr, 0, 65530) . '...';
    }
    if ($newValueStr && strlen($newValueStr) > 65535) {
        $newValueStr = substr($newValueStr, 0, 65530) . '...';
    }
    
    $stmt = $conn->prepare("
        INSERT INTO audit_trail (
            table_name,
            record_id,
            action,
            field_name,
            old_value,
            new_value,
            changed_by,
            ip_address,
            user_agent,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare audit trail insert: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param(
        'sississsss',
        $tableName,
        $recordId,
        $action,
        $fieldName,
        $oldValueStr,
        $newValueStr,
        $userId,
        $ipAddress,
        $userAgent,
        $notes
    );
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to insert audit trail: " . $stmt->error);
    }
    
    $stmt->close();
    return $success;
}

/**
 * Log journal entry creation
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param array $entryData Entry data
 * @return bool Success status
 */
function logJournalEntryCreate($conn, $journalEntryId, $entryData) {
    return logAuditTrail(
        $conn,
        'journal_entries',
        $journalEntryId,
        'CREATE',
        null,
        null,
        json_encode($entryData),
        null,
        "Journal entry created: {$entryData['entry_number']}"
    );
}

/**
 * Log journal entry update
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param array $oldData Old entry data
 * @param array $newData New entry data
 * @return bool Success status
 */
function logJournalEntryUpdate($conn, $journalEntryId, $oldData, $newData) {
    $changes = [];
    foreach ($newData as $field => $newValue) {
        $oldValue = $oldData[$field] ?? null;
        if ($oldValue != $newValue) {
            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue
            ];
        }
    }
    
    if (empty($changes)) {
        return true; // No changes to log
    }
    
    // Log each changed field
    foreach ($changes as $field => $change) {
        logAuditTrail(
            $conn,
            'journal_entries',
            $journalEntryId,
            'UPDATE',
            $field,
            $change['old'],
            $change['new'],
            null,
            "Journal entry updated: {$oldData['entry_number']}"
        );
    }
    
    return true;
}

/**
 * Log journal entry posting
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @return bool Success status
 */
function logJournalEntryPost($conn, $journalEntryId) {
    return logAuditTrail(
        $conn,
        'journal_entries',
        $journalEntryId,
        'POST',
        'status',
        'Draft',
        'Posted',
        null,
        "Journal entry posted to general ledger"
    );
}

/**
 * Log journal entry reversal
 * 
 * @param mysqli $conn Database connection
 * @param int $originalEntryId Original journal entry ID
 * @param int $reversalEntryId Reversal journal entry ID
 * @return bool Success status
 */
function logJournalEntryReversal($conn, $originalEntryId, $reversalEntryId) {
    logAuditTrail(
        $conn,
        'journal_entries',
        $originalEntryId,
        'REVERSE',
        'posting_status',
        'posted',
        'reversed',
        null,
        "Journal entry reversed by entry #{$reversalEntryId}"
    );
    
    return logAuditTrail(
        $conn,
        'journal_entries',
        $reversalEntryId,
        'CREATE',
        null,
        null,
        json_encode(['reversal_of' => $originalEntryId]),
        null,
        "Reversal entry created for journal entry #{$originalEntryId}"
    );
}

/**
 * Log journal entry approval
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param int $approvedBy User ID who approved
 * @return bool Success status
 */
function logJournalEntryApproval($conn, $journalEntryId, $approvedBy) {
    return logAuditTrail(
        $conn,
        'journal_entries',
        $journalEntryId,
        'APPROVE',
        'approved_by',
        null,
        $approvedBy,
        $approvedBy,
        "Journal entry approved"
    );
}

/**
 * Log journal entry deletion
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param array $deletedData Deleted entry data
 * @return bool Success status
 */
function logJournalEntryDelete($conn, $journalEntryId, $deletedData) {
    return logAuditTrail(
        $conn,
        'journal_entries',
        $journalEntryId,
        'DELETE',
        null,
        json_encode($deletedData),
        null,
        null,
        "Journal entry deleted: {$deletedData['entry_number']}"
    );
}
