<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/erp-guardian.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/erp-guardian.php`.
 */
/**
 * ERP GUARDIAN SYSTEM
 * 
 * FINAL AUTHORITY: Prevents ANY accounting violations
 * 
 * NON-NEGOTIABLE LAWS:
 * 1. GL is the SINGLE SOURCE OF TRUTH
 * 2. No balances may be calculated outside journals
 * 3. Every financial action MUST generate journal entries
 * 4. Posted journals are IMMUTABLE
 * 5. Reversals must be separate journal entries
 * 6. Reports read ONLY from posted GL data
 * 7. No accounting logic in frontend JS
 * 8. No direct balance updates EVER
 */

/**
 * BLOCK direct balance updates
 * 
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $query SQL query to check
 * @throws Exception If violation detected
 */
function guardAgainstBalanceUpdate($conn, $table, $query) {
    // Allow UPDATE ... SET field = (calculated_from_GL) - this is display-only sync
    // Block UPDATE ... SET field = field + amount - this is direct modification
    
    $forbiddenPatterns = [
        // Direct modification patterns (FORBIDDEN)
        '/UPDATE\s+.*\s+SET\s+.*current_balance\s*=\s*.*current_balance\s*[+\-]/i', // field = field + amount
        '/UPDATE\s+.*\s+SET\s+.*balance\s*=\s*.*balance\s*[+\-]/i', // field = field + amount
        '/UPDATE\s+financial_accounts\s+SET\s+.*current_balance\s*=\s*.*current_balance/i', // Direct account balance update
        '/UPDATE\s+accounting_banks\s+SET\s+.*current_balance\s*=\s*.*current_balance\s*[+\-]/i' // Direct bank balance modification
    ];
    
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $query)) {
            error_log("ERP GUARDIAN BLOCKED: Direct balance modification detected");
            error_log("Table: $table");
            error_log("Query: " . substr($query, 0, 200));
            throw new Exception("ERP VIOLATION: Direct balance modifications are FORBIDDEN. Balances must be calculated from general_ledger only. Use display-only sync: UPDATE ... SET field = (calculated_from_GL).");
        }
    }
    
    // Note: UPDATE ... SET current_balance = ? (where ? comes from GL calculation) is ALLOWED
    // This is display-only synchronization, not direct modification
}

/**
 * BLOCK calls to deprecated functions
 * 
 * @param string $functionName Function name
 * @throws Exception If deprecated function called
 */
function guardAgainstDeprecatedFunction($functionName) {
    $deprecatedFunctions = [
        'updateAccountBalance',
        'updateBalance',
        'setBalance',
        'calculateBalanceDirect'
    ];
    
    if (in_array($functionName, $deprecatedFunctions)) {
        error_log("ERP GUARDIAN BLOCKED: Deprecated function call detected");
        error_log("Function: $functionName");
        throw new Exception("ERP VIOLATION: Function '$functionName' is DEPRECATED. Use general_ledger calculations instead.");
    }
}

/**
 * VALIDATE that journal entry is Posted before GL posting
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @throws Exception If entry is not Posted
 */
function guardJournalEntryPosting($conn, $journalEntryId) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            status,
            posting_status,
            is_posted
        FROM journal_entries
        WHERE id = ?
    ");
    $stmt->bind_param('i', $journalEntryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $result->free();
        $stmt->close();
        throw new Exception("ERP GUARDIAN: Journal entry not found");
    }
    
    $entry = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    
    $isPosted = ($entry['status'] === 'Posted' || 
                $entry['posting_status'] === 'posted' || 
                $entry['is_posted'] == 1);
    
    if (!$isPosted) {
        throw new Exception("ERP GUARDIAN: Only Posted journal entries can be posted to general ledger. Entry #{$journalEntryId} is not Posted.");
    }
}

/**
 * VALIDATE that balance calculations come from GL only
 * 
 * @param mysqli $conn Database connection
 * @param string $source Source of balance calculation
 * @param int|null $accountId Account ID
 * @throws Exception If balance not from GL
 */
function guardBalanceSource($conn, $source, $accountId = null) {
    $allowedSources = [
        'general_ledger',
        'gl',
        'posted_journals'
    ];
    
    $forbiddenSources = [
        'financial_accounts.current_balance',
        'accounting_banks.current_balance',
        'direct_update',
        'calculated_field'
    ];
    
    $sourceLower = strtolower($source);
    
    foreach ($forbiddenSources as $forbidden) {
        if (strpos($sourceLower, $forbidden) !== false) {
            error_log("ERP GUARDIAN BLOCKED: Balance from forbidden source");
            error_log("Source: $source");
            error_log("Account ID: " . ($accountId ?? 'N/A'));
            throw new Exception("ERP VIOLATION: Balance must be calculated from general_ledger only. Source '$source' is FORBIDDEN.");
        }
    }
}

/**
 * VALIDATE that reports read from GL only
 * 
 * @param mysqli $conn Database connection
 * @param string $query SQL query for report
 * @throws Exception If report doesn't read from GL
 */
function guardReportSource($conn, $query) {
    // Reports MUST read from general_ledger
    $hasGLSource = (
        stripos($query, 'FROM general_ledger') !== false ||
        stripos($query, 'JOIN general_ledger') !== false ||
        stripos($query, 'general_ledger gl') !== false
    );
    
    // Reports MUST NOT read balances from financial_accounts.current_balance directly
    $hasDirectBalance = (
        stripos($query, 'financial_accounts.current_balance') !== false &&
        stripos($query, 'FROM general_ledger') === false
    );
    
    if ($hasDirectBalance && !$hasGLSource) {
        error_log("ERP GUARDIAN BLOCKED: Report reading balance from non-GL source");
        error_log("Query: " . substr($query, 0, 300));
        throw new Exception("ERP VIOLATION: Reports must read balances from general_ledger only. Direct balance reads are FORBIDDEN.");
    }
}

/**
 * VALIDATE that posted entries cannot be modified
 * 
 * @param mysqli $conn Database connection
 * @param int $journalEntryId Journal entry ID
 * @param string $operation Operation attempted (UPDATE, DELETE)
 * @throws Exception If posted entry modification attempted
 */
function guardPostedEntryModification($conn, $journalEntryId, $operation) {
    $stmt = $conn->prepare("
        SELECT 
            id,
            status,
            posting_status,
            is_posted,
            is_locked
        FROM journal_entries
        WHERE id = ?
    ");
    $stmt->bind_param('i', $journalEntryId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $result->free();
        $stmt->close();
        return; // Entry doesn't exist, let normal flow handle it
    }
    
    $entry = $result->fetch_assoc();
    $result->free();
    $stmt->close();
    
    $isPosted = ($entry['status'] === 'Posted' || 
                $entry['posting_status'] === 'posted' || 
                $entry['is_posted'] == 1);
    
    $isLocked = ($entry['is_locked'] == 1);
    
    if ($isPosted || $isLocked) {
        error_log("ERP GUARDIAN BLOCKED: Attempted $operation on posted entry");
        error_log("Entry ID: $journalEntryId");
        error_log("Status: " . $entry['status']);
        error_log("Posting Status: " . ($entry['posting_status'] ?? 'N/A'));
        throw new Exception("ERP VIOLATION: Posted journal entries are IMMUTABLE. Cannot $operation entry #{$journalEntryId}. Use reversal entry instead.");
    }
}

/**
 * VALIDATE that all financial actions post to GL
 * 
 * @param string $module Module name
 * @param string $action Action performed
 * @param int|null $journalEntryId Journal entry ID created
 * @throws Exception If action doesn't create journal entry
 */
function guardFinancialAction($module, $action, $journalEntryId = null) {
    $financialActions = [
        'invoice' => ['create', 'update', 'payment'],
        'payment' => ['create', 'process'],
        'receipt' => ['create', 'process'],
        'expense' => ['create', 'approve'],
        'payroll' => ['process', 'approve'],
        'commission' => ['calculate', 'approve'],
        'bank-transactions' => ['create', 'update', 'delete'] // Bank transactions must create journal entries
    ];
    
    if (isset($financialActions[$module]) && in_array($action, $financialActions[$module])) {
        if ($journalEntryId === null || $journalEntryId <= 0) {
            error_log("ERP GUARDIAN BLOCKED: Financial action without journal entry");
            error_log("Module: $module");
            error_log("Action: $action");
            throw new Exception("ERP VIOLATION: Financial action '$action' in module '$module' MUST create a journal entry. No journal entry found.");
        }
    }
}

/**
 * SCAN for ERP violations in codebase
 * 
 * @param mysqli $conn Database connection
 * @return array Violations found
 */
function scanForViolations($conn) {
    $violations = [];
    
    // Check for direct balance updates in database
    $checkTables = ['financial_accounts', 'accounting_banks'];
    foreach ($checkTables as $table) {
        $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $tableCheck->free();
            // Check if current_balance column exists and has non-zero values
            // This would indicate direct updates (should be calculated from GL)
            $balanceCheck = $conn->query("
                SELECT COUNT(*) as count 
                FROM $table 
                WHERE current_balance != 0 
                AND current_balance IS NOT NULL
            ");
            if ($balanceCheck) {
                $balanceData = $balanceCheck->fetch_assoc();
                if ($balanceData['count'] > 0) {
                    $violations[] = [
                        'type' => 'direct_balance_field',
                        'table' => $table,
                        'severity' => 'warning',
                        'message' => "Table $table has current_balance values. Ensure balances are calculated from GL only."
                    ];
                }
                $balanceCheck->free();
            }
        } else {
            if ($tableCheck) $tableCheck->free();
        }
    }
    
    return $violations;
}

/**
 * ERP GUARDIAN: Main validation function
 * Call this before any accounting operation
 * 
 * @param mysqli $conn Database connection
 * @param string $operation Operation type (CREATE, UPDATE, DELETE, POST, REPORT)
 * @param array $context Operation context
 * @throws Exception If violation detected
 */
function erpGuardian($conn, $operation, $context = []) {
    // Guard against direct balance updates
    if (isset($context['query'])) {
        guardAgainstBalanceUpdate($conn, $context['table'] ?? 'unknown', $context['query']);
    }
    
    // Guard against deprecated functions
    if (isset($context['function'])) {
        guardAgainstDeprecatedFunction($context['function']);
    }
    
    // Guard journal entry posting
    if ($operation === 'POST' && isset($context['journal_entry_id'])) {
        guardJournalEntryPosting($conn, $context['journal_entry_id']);
    }
    
    // Guard posted entry modification
    if (in_array($operation, ['UPDATE', 'DELETE']) && isset($context['journal_entry_id'])) {
        guardPostedEntryModification($conn, $context['journal_entry_id'], $operation);
    }
    
    // Guard report source
    if ($operation === 'REPORT' && isset($context['query'])) {
        guardReportSource($conn, $context['query']);
    }
    
    // Guard financial actions
    if (isset($context['module']) && isset($context['action'])) {
        guardFinancialAction($context['module'], $context['action'], $context['journal_entry_id'] ?? null);
    }
    
    return true; // All checks passed
}
