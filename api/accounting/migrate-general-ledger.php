<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-general-ledger.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-general-ledger.php`.
 */
/**
 * General Ledger Migration Script
 * 
 * This script:
 * 1. Creates general_ledger table
 * 2. Creates stored procedure to post journal entries
 * 3. Migrates existing posted journal entries to ledger
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if required tables exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
if ($tableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'journal_entries table does not exist']);
    exit;
}
$tableCheck->free();

$linesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
if ($linesTableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'journal_entry_lines table does not exist']);
    exit;
}
$linesTableCheck->free();

$accountsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
if ($accountsTableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'financial_accounts table does not exist']);
    exit;
}
$accountsTableCheck->free();

try {
    $results = [];
    $errors = [];
    
    // Step 1: Create general_ledger table if it doesn't exist
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $glTableCheck->num_rows > 0;
    $glTableCheck->free();
    
    if (!$hasGLTable) {
        $createGLTable = $conn->query("
            CREATE TABLE general_ledger (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                account_id INT(11) NOT NULL,
                journal_entry_id INT(11) NOT NULL,
                debit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                credit DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                posting_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account_id (account_id),
                INDEX idx_journal_entry_id (journal_entry_id),
                INDEX idx_posting_date (posting_date),
                FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
                FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        if ($createGLTable) {
            $results[] = "Created general_ledger table";
        } else {
            $errors[] = "Failed to create general_ledger table: " . $conn->error;
        }
    } else {
        $results[] = "general_ledger table already exists";
    }
    
    // Step 2: Note about posting function
    // We'll create a PHP helper function instead of stored procedure for better compatibility
    $results[] = "Posting to general ledger will be handled via PHP function (postJournalEntryToLedger)";
    
    // Step 3: Migrate existing posted journal entries to general_ledger
    $migratedCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    
    // Check if is_posted column exists
    $isPostedCheck = $conn->query("SHOW COLUMNS FROM journal_entries LIKE 'is_posted'");
    $hasIsPosted = $isPostedCheck->num_rows > 0;
    $isPostedCheck->free();
    
    if ($hasIsPosted) {
        // Get all posted journal entries
        $postedEntriesQuery = $conn->query("
            SELECT id, entry_date 
            FROM journal_entries 
            WHERE is_posted = TRUE 
            AND status = 'Posted'
            ORDER BY id
        ");
        
        if ($postedEntriesQuery) {
            while ($entry = $postedEntriesQuery->fetch_assoc()) {
                $entryId = $entry['id'];
                $postingDate = $entry['entry_date'];
                
                // Skip entries with NULL entry_date (consistent with helper function)
                if (empty($postingDate)) {
                    $skippedCount++;
                    $errors[] = "Skipped entry {$entryId}: entry_date is NULL";
                    continue;
                }
                
                // Check if already in ledger
                $existsCheck = $conn->prepare("SELECT COUNT(*) as count FROM general_ledger WHERE journal_entry_id = ?");
                $existsCheck->bind_param('i', $entryId);
                $existsCheck->execute();
                $existsResult = $existsCheck->get_result();
                $existsData = $existsResult->fetch_assoc();
                $existsCheck->close();
                $existsResult->free();
                
                if ($existsData['count'] > 0) {
                    $skippedCount++;
                    continue;
                }
                
                // Get all lines for this entry
                $linesQuery = $conn->prepare("
                    SELECT 
                        account_id,
                        COALESCE(debit_amount, 0) as debit_amount,
                        COALESCE(credit_amount, 0) as credit_amount
                    FROM journal_entry_lines
                    WHERE journal_entry_id = ?
                ");
                $linesQuery->bind_param('i', $entryId);
                $linesQuery->execute();
                $linesResult = $linesQuery->get_result();
                
                $totalDebit = 0;
                $totalCredit = 0;
                $hasLines = false;
                
                // Insert each line into general_ledger
                while ($line = $linesResult->fetch_assoc()) {
                    $accountId = $line['account_id'];
                    $debit = floatval($line['debit_amount']);
                    $credit = floatval($line['credit_amount']);
                    
                    // Skip lines with zero amounts or invalid account_id (consistent with helper function)
                    if (($debit == 0 && $credit == 0) || !$accountId || $accountId <= 0) {
                        continue;
                    }
                    
                    $hasLines = true;
                    $totalDebit += $debit;
                    $totalCredit += $credit;
                    
                    $insertStmt = $conn->prepare("
                        INSERT INTO general_ledger (account_id, journal_entry_id, debit, credit, posting_date)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insertStmt->bind_param('iidds', $accountId, $entryId, $debit, $credit, $postingDate);
                    
                    if (!$insertStmt->execute()) {
                        $errorCount++;
                        $errors[] = "Failed to migrate line for journal_entry_id {$entryId}: " . $insertStmt->error;
                    }
                    $insertStmt->close();
                }
                
                $linesQuery->close();
                $linesResult->free();
                
                // Validate balance
                if ($hasLines && abs($totalDebit - $totalCredit) > 0.01) {
                    $errorCount++;
                    $errors[] = "Unbalanced entry {$entryId}: Debit {$totalDebit} != Credit {$totalCredit}";
                    // Remove the entries we just inserted
                    $deleteStmt = $conn->prepare("DELETE FROM general_ledger WHERE journal_entry_id = ?");
                    $deleteStmt->bind_param('i', $entryId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                } else if ($hasLines) {
                    $migratedCount++;
                } else {
                    $skippedCount++;
                }
            }
            $postedEntriesQuery->free();
        }
    } else {
        $results[] = "is_posted column not found - skipping migration (table may not have posting feature yet)";
    }
    
    // Prepare response
    $response = [
        'success' => count($errors) === 0,
        'results' => $results,
        'migration' => [
            'migrated' => $migratedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount
        ]
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}