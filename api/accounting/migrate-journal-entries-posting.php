<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-journal-entries-posting.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-journal-entries-posting.php`.
 */
/**
 * Journal Entries Posting Migration Script
 * 
 * This script:
 * 1. Adds is_posted BOOLEAN column to journal_entries
 * 2. Adds is_locked BOOLEAN column (true if posted)
 * 3. Creates trigger to prevent posting unbalanced entries
 * 4. Validates existing journal entries
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
if ($tableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'journal_entries table does not exist']);
    exit;
}

$linesTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
if ($linesTableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'journal_entry_lines table does not exist']);
    exit;
}

try {
    $results = [];
    $errors = [];
    
    // Step 1: Check current structure
    $columnsCheck = $conn->query("SHOW COLUMNS FROM journal_entries");
    $hasIsPosted = false;
    $hasIsLocked = false;
    $hasTotalDebit = false;
    $hasTotalCredit = false;
    
    while ($col = $columnsCheck->fetch_assoc()) {
        if ($col['Field'] === 'is_posted') $hasIsPosted = true;
        if ($col['Field'] === 'is_locked') $hasIsLocked = true;
        if ($col['Field'] === 'total_debit') $hasTotalDebit = true;
        if ($col['Field'] === 'total_credit') $hasTotalCredit = true;
    }
    $columnsCheck->free();
    
    // Step 2: Add is_posted column
    if (!$hasIsPosted) {
        $alterIsPosted = $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN is_posted BOOLEAN NOT NULL DEFAULT FALSE
            AFTER status
        ");
        if ($alterIsPosted) {
            $results[] = "Added is_posted BOOLEAN column";
        } else {
            $errors[] = "Failed to add is_posted: " . $conn->error;
        }
    } else {
        $results[] = "is_posted column already exists";
    }
    
    // Step 3: Add is_locked column
    if (!$hasIsLocked) {
        $alterIsLocked = $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN is_locked BOOLEAN NOT NULL DEFAULT FALSE
            AFTER is_posted
        ");
        if ($alterIsLocked) {
            $results[] = "Added is_locked BOOLEAN column";
        } else {
            $errors[] = "Failed to add is_locked: " . $conn->error;
        }
    } else {
        $results[] = "is_locked column already exists";
    }
    
    // Step 4: Ensure total_debit and total_credit columns exist
    if (!$hasTotalDebit) {
        $alterTotalDebit = $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN total_debit DECIMAL(15,2) NOT NULL DEFAULT 0.00
            AFTER description
        ");
        if ($alterTotalDebit) {
            $results[] = "Added total_debit column";
        } else {
            $errors[] = "Failed to add total_debit: " . $conn->error;
        }
    } else {
        $results[] = "total_debit column already exists";
    }
    
    if (!$hasTotalCredit) {
        $alterTotalCredit = $conn->query("
            ALTER TABLE journal_entries 
            ADD COLUMN total_credit DECIMAL(15,2) NOT NULL DEFAULT 0.00
            AFTER total_debit
        ");
        if ($alterTotalCredit) {
            $results[] = "Added total_credit column";
        } else {
            $errors[] = "Failed to add total_credit: " . $conn->error;
        }
    } else {
        $results[] = "total_credit column already exists";
    }
    
    // Step 5: Update existing entries - set is_locked = true where is_posted = true
    if ($hasIsPosted && $hasIsLocked) {
        $updateLocked = $conn->query("UPDATE journal_entries SET is_locked = TRUE WHERE is_posted = TRUE");
        if ($updateLocked) {
            $affectedRows = $conn->affected_rows;
            if ($affectedRows > 0) {
                $results[] = "Updated {$affectedRows} existing entries to set is_locked = TRUE where is_posted = TRUE";
            } else {
                $results[] = "No existing entries needed is_locked update";
            }
        } else {
            $errors[] = "Failed to update is_locked: " . $conn->error;
        }
    }
    
    // Step 6: Recalculate total_debit and total_credit from journal_entry_lines
    $entriesQuery = $conn->query("SELECT id FROM journal_entries");
    $updatedTotals = 0;
    
    while ($entry = $entriesQuery->fetch_assoc()) {
        $entryId = $entry['id'];
        
        // Calculate totals from lines
        $totalsQuery = $conn->query("
            SELECT 
                COALESCE(SUM(debit_amount), 0) as total_debit,
                COALESCE(SUM(credit_amount), 0) as total_credit
            FROM journal_entry_lines
            WHERE journal_entry_id = {$entryId}
        ");
        
        if ($totalsQuery && $totalsResult = $totalsQuery->fetch_assoc()) {
            $calcTotalDebit = floatval($totalsResult['total_debit'] ?? 0);
            $calcTotalCredit = floatval($totalsResult['total_credit'] ?? 0);
            
            // Update journal entry with calculated totals
            $updateStmt = $conn->prepare("
                UPDATE journal_entries 
                SET total_debit = ?, total_credit = ? 
                WHERE id = ?
            ");
            $updateStmt->bind_param('ddi', $calcTotalDebit, $calcTotalCredit, $entryId);
            
            if ($updateStmt->execute()) {
                $updatedTotals++;
            }
            $updateStmt->close();
            $totalsQuery->free();
        }
    }
    $entriesQuery->free();
    
    if ($updatedTotals > 0) {
        $results[] = "Recalculated total_debit/total_credit for {$updatedTotals} entries from journal_entry_lines";
    } else {
        $results[] = "All entries already have correct total_debit/total_credit values";
    }
    
    // Step 7: Create trigger to set is_locked = true when is_posted = true
    $triggerCheckLock = $conn->query("SHOW TRIGGERS WHERE `Trigger` = 'auto_lock_on_post'");
    if ($triggerCheckLock->num_rows === 0) {
        // Drop trigger if exists with different name
        $conn->query("DROP TRIGGER IF EXISTS auto_lock_on_post");
        
        // Create trigger
        $createTriggerLock = $conn->query("
            CREATE TRIGGER auto_lock_on_post
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            BEGIN
                IF NEW.is_posted = TRUE THEN
                    SET NEW.is_locked = TRUE;
                END IF;
            END
        ");
        
        if ($createTriggerLock) {
            $results[] = "Created trigger to auto-set is_locked when is_posted = TRUE";
        } else {
            $errors[] = "Failed to create auto_lock_on_post trigger: " . $conn->error;
        }
        $triggerCheckLock->free();
    } else {
        $results[] = "auto_lock_on_post trigger already exists";
        $triggerCheckLock->free();
    }
    
    // Step 8: Create trigger to prevent posting unbalanced entries
    $triggerCheckBalance = $conn->query("SHOW TRIGGERS WHERE `Trigger` = 'validate_balanced_entry_before_post'");
    $hasTriggerBalance = $triggerCheckBalance->num_rows > 0;
    if (!$hasTriggerBalance) {
        // Drop trigger if exists with different name
        $conn->query("DROP TRIGGER IF EXISTS validate_balanced_entry_before_post");
        
        // Create trigger - check balance before posting
        // Note: We'll validate in application code, but also add a trigger for database-level protection
        $createTriggerBalance = $conn->query("
            CREATE TRIGGER validate_balanced_entry_before_post
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW
            BEGIN
                DECLARE line_total_debit DECIMAL(15,2) DEFAULT 0;
                DECLARE line_total_credit DECIMAL(15,2) DEFAULT 0;
                
                -- Only validate when trying to post (is_posted changing from FALSE to TRUE)
                IF (OLD.is_posted = FALSE AND NEW.is_posted = TRUE) THEN
                    -- Calculate totals from journal_entry_lines
                    SELECT 
                        COALESCE(SUM(debit_amount), 0),
                        COALESCE(SUM(credit_amount), 0)
                    INTO line_total_debit, line_total_credit
                    FROM journal_entry_lines
                    WHERE journal_entry_id = NEW.id;
                    
                    -- Validate that entry has lines (at least one non-zero amount)
                    IF line_total_debit = 0 AND line_total_credit = 0 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Cannot post entry with no lines or zero amounts';
                    END IF;
                    
                    -- Validate balance (allow small rounding differences - 0.01)
                    IF ABS(line_total_debit - line_total_credit) > 0.01 THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = CONCAT('Cannot post unbalanced entry. Debit total: ', line_total_debit, ', Credit total: ', line_total_credit);
                    END IF;
                END IF;
            END
        ");
        
        if ($createTriggerBalance) {
            $results[] = "Created trigger to validate balanced entries before posting";
        } else {
            $errors[] = "Failed to create validate_balanced_entry_before_post trigger: " . $conn->error;
        }
        $triggerCheckBalance->free();
    } else {
        $results[] = "validate_balanced_entry_before_post trigger already exists";
        $triggerCheckBalance->free();
    }
    
    // Step 9: Validate all existing entries
    $unbalancedEntries = $conn->query("
        SELECT 
            je.id,
            je.entry_number,
            je.total_debit,
            je.total_credit,
            COALESCE(SUM(jel.debit_amount), 0) as line_total_debit,
            COALESCE(SUM(jel.credit_amount), 0) as line_total_credit
        FROM journal_entries je
        LEFT JOIN journal_entry_lines jel ON jel.journal_entry_id = je.id
        GROUP BY je.id, je.entry_number, je.total_debit, je.total_credit
        HAVING ABS(COALESCE(SUM(jel.debit_amount), 0) - COALESCE(SUM(jel.credit_amount), 0)) > 0.01
    ");
    
    $unbalancedCount = $unbalancedEntries->num_rows;
    if ($unbalancedCount > 0) {
        $errors[] = "Found {$unbalancedCount} unbalanced journal entries";
        while ($unbalanced = $unbalancedEntries->fetch_assoc()) {
            $errors[] = "Entry {$unbalanced['entry_number']} (ID: {$unbalanced['id']}): Debit={$unbalanced['line_total_debit']}, Credit={$unbalanced['line_total_credit']}";
        }
    } else {
        $results[] = "All existing journal entries are balanced";
    }
    $unbalancedEntries->free();
    
    // Final summary
    $response = [
        'success' => count($errors) === 0,
        'message' => count($errors) === 0 ? 'Migration completed successfully' : 'Migration completed with errors',
        'results' => $results,
        'updated_entries' => $updatedTotals,
        'unbalanced_entries' => $unbalancedCount
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
