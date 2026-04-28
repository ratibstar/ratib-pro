<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/financial-closings.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/financial-closings.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/core/general-ledger-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('accounts', 'view');
} elseif ($method === 'POST' || $method === 'PUT') {
    enforceApiPermission('accounts', 'edit');
} elseif ($method === 'DELETE') {
    enforceApiPermission('accounts', 'delete');
}

/**
 * Check if a period is locked (has a completed closing)
 * @param mysqli $conn Database connection
 * @param string $entryDate Date to check (YYYY-MM-DD)
 * @return bool True if period is locked, false otherwise
 */
function isPeriodLocked($conn, $entryDate) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        if ($tableCheck) {
            $tableCheck->free();
        }
        return false; // No closings table means no locks
    }
    $tableCheck->free();
    
    // Check if date falls within any completed closing period
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as locked_count
        FROM financial_closings
        WHERE status = 'Completed'
        AND ? >= period_start_date 
        AND ? <= period_end_date
    ");
    $checkStmt->bind_param('ss', $entryDate, $entryDate);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $locked = ($row['locked_count'] ?? 0) > 0;
    $result->free();
    $checkStmt->close();
    
    return $locked;
}

try {
    // Check if financial_closings table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
    $tableExists = $tableCheck && $tableCheck->num_rows > 0;
    if ($tableCheck) {
        $tableCheck->free();
    }
    if (!$tableExists) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS financial_closings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                closing_name VARCHAR(255) NOT NULL,
                closing_date DATE NOT NULL,
                period_start_date DATE NOT NULL,
                period_end_date DATE NOT NULL,
                status ENUM('In Progress', 'Completed', 'Reopened') DEFAULT 'In Progress',
                net_income DECIMAL(15,2) DEFAULT 0.00,
                retained_earnings_before DECIMAL(15,2) DEFAULT 0.00,
                retained_earnings_after DECIMAL(15,2) DEFAULT 0.00,
                closing_journal_entry_id INT NULL,
                notes TEXT,
                closed_by INT NULL,
                closed_at TIMESTAMP NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (closing_journal_entry_id) REFERENCES journal_entries(id) ON DELETE SET NULL,
                FOREIGN KEY (closed_by) REFERENCES users(user_id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $closingId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($closingId) {
            // Get single closing
            $stmt = $conn->prepare("SELECT * FROM financial_closings WHERE id = ?");
            $stmt->bind_param('i', $closingId);
            $stmt->execute();
            $result = $stmt->get_result();
            $closing = $result->fetch_assoc();
            
            if ($closing) {
                $result->free();
                $stmt->close();
                echo json_encode([
                    'success' => true,
                    'closing' => $closing
                ]);
            } else {
                $result->free();
                $stmt->close();
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Financial closing not found'
                ]);
            }
        } else {
            // Get all closings
            $stmt = $conn->prepare("SELECT * FROM financial_closings ORDER BY closing_date DESC, created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $closings = [];
            while ($row = $result->fetch_assoc()) {
                $closings[] = $row;
            }
            $result->free();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'closings' => $closings
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new financial closing
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['closing_name']) || !isset($data['closing_date']) || 
            !isset($data['period_start_date']) || !isset($data['period_end_date'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Closing name, date, and period dates are required'
            ]);
            exit;
        }
        
        $closingName = $data['closing_name'];
        $closingDate = $data['closing_date'];
        $periodStartDate = $data['period_start_date'];
        $periodEndDate = $data['period_end_date'];
        $status = $data['status'] ?? 'In Progress';
        $notes = $data['notes'] ?? null;
        $userId = $_SESSION['user_id'];
        
        // Validate dates
        if (!strtotime($closingDate) || !strtotime($periodStartDate) || !strtotime($periodEndDate)) {
            throw new Exception('Invalid date format. Dates must be in YYYY-MM-DD format.');
        }
        
        // Validate period dates
        if ($periodStartDate > $periodEndDate) {
            throw new Exception('Period start date must be less than or equal to period end date.');
        }
        
        // Validate closing_date is within or after the period
        if ($closingDate < $periodStartDate) {
            throw new Exception('Closing date must be on or after the period start date.');
        }
        
        // Check for overlapping completed closings
        $overlapCheck = $conn->prepare("
            SELECT COUNT(*) as overlap_count
            FROM financial_closings
            WHERE status = 'Completed'
            AND (
                (? >= period_start_date AND ? <= period_end_date) OR
                (? >= period_start_date AND ? <= period_end_date) OR
                (? <= period_start_date AND ? >= period_end_date)
            )
        ");
        $overlapCheck->bind_param('ssssss', $periodStartDate, $periodStartDate, $periodEndDate, $periodEndDate, $periodStartDate, $periodEndDate);
        $overlapCheck->execute();
        $overlapResult = $overlapCheck->get_result();
        $overlapRow = $overlapResult->fetch_assoc();
        $hasOverlap = ($overlapRow['overlap_count'] ?? 0) > 0;
        $overlapResult->free();
        $overlapCheck->close();
        
        if ($hasOverlap) {
            throw new Exception('Period overlaps with an existing completed closing. Cannot create overlapping closed periods.');
        }
        
        // Calculate net income for the period from general_ledger (if available) or journal_entry_lines
        $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
        $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
        if ($glTableCheck) {
            $glTableCheck->free();
        }
        
        if ($hasGLTable) {
            // Use general_ledger for accurate calculations
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN fa.account_type = 'REVENUE' THEN gl.credit - gl.debit ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN fa.account_type = 'EXPENSE' THEN gl.debit - gl.credit ELSE 0 END), 0) as expenses
                FROM general_ledger gl
                INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                WHERE gl.posting_date >= ? AND gl.posting_date <= ?
            ");
            $stmt->bind_param('ss', $periodStartDate, $periodEndDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $resultRow = $result->fetch_assoc();
            $netIncome = floatval($resultRow['income'] ?? 0) - floatval($resultRow['expenses'] ?? 0);
            $result->free();
            $stmt->close();
        } else {
            // Fallback to journal_entry_lines
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN UPPER(fa.account_type) = 'REVENUE' THEN tl.credit_amount - tl.debit_amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN UPPER(fa.account_type) = 'EXPENSE' THEN tl.debit_amount - tl.credit_amount ELSE 0 END), 0) as expenses
                FROM journal_entry_lines tl
                INNER JOIN journal_entries je ON tl.journal_entry_id = je.id
                INNER JOIN financial_accounts fa ON tl.account_id = fa.id
                WHERE je.entry_date >= ? AND je.entry_date <= ? AND je.status = 'Posted'
            ");
            $stmt->bind_param('ss', $periodStartDate, $periodEndDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $resultRow = $result->fetch_assoc();
            $netIncome = floatval($resultRow['income'] ?? 0) - floatval($resultRow['expenses'] ?? 0);
            $result->free();
            $stmt->close();
        }
        
        // Get retained earnings account ID
        $retainedStmt = $conn->prepare("
            SELECT id FROM financial_accounts
            WHERE (account_code = '3200' OR account_code LIKE '32%') 
            AND (account_name LIKE '%Retained Earnings%' OR account_type = 'EQUITY')
            ORDER BY CASE WHEN account_code = '3200' THEN 0 ELSE 1 END, id
            LIMIT 1
        ");
        $retainedStmt->execute();
        $retainedResult = $retainedStmt->get_result();
        $retainedAccount = $retainedResult->fetch_assoc();
        $retainedAccountId = $retainedAccount ? intval($retainedAccount['id']) : null;
        $retainedResult->free();
        $retainedStmt->close();
        
        if (!$retainedAccountId) {
            throw new Exception('Retained Earnings account not found. Please create account code 3200 (Retained Earnings) first.');
        }
        
        // Calculate retained earnings before from general_ledger (if available)
        if ($hasGLTable) {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(gl.credit - gl.debit), 0) as retained_earnings
                FROM general_ledger gl
                WHERE gl.account_id = ?
                AND gl.posting_date < ?
            ");
            $stmt->bind_param('is', $retainedAccountId, $periodStartDate);
            $stmt->execute();
            $retainedResult = $stmt->get_result();
            $retainedRow = $retainedResult->fetch_assoc();
            $retainedEarningsBefore = floatval($retainedRow['retained_earnings'] ?? 0);
            $retainedResult->free();
            $stmt->close();
        } else {
            // Fallback: try to get from current_balance
            $stmt = $conn->prepare("
                SELECT COALESCE(current_balance, 0) as retained_earnings
                FROM financial_accounts
                WHERE id = ?
            ");
            $stmt->bind_param('i', $retainedAccountId);
            $stmt->execute();
            $retainedResult = $stmt->get_result();
            $retainedRow = $retainedResult->fetch_assoc();
            $retainedEarningsBefore = floatval($retainedRow['retained_earnings'] ?? 0);
            $retainedResult->free();
            $stmt->close();
        }
        
        $retainedEarningsAfter = $retainedEarningsBefore + $netIncome;
        
        $stmt = $conn->prepare("
            INSERT INTO financial_closings 
            (closing_name, closing_date, period_start_date, period_end_date, status, net_income, 
             retained_earnings_before, retained_earnings_after, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssdddsi', $closingName, $closingDate, $periodStartDate, $periodEndDate, 
                          $status, $netIncome, $retainedEarningsBefore, $retainedEarningsAfter, $notes, $userId);
        $stmt->execute();
        $closingId = $conn->insert_id;
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Financial closing created successfully',
            'closing_id' => $closingId,
            'net_income' => $netIncome,
            'retained_earnings_before' => $retainedEarningsBefore,
            'retained_earnings_after' => $retainedEarningsAfter
        ]);
    } elseif ($method === 'PUT') {
        // Update financial closing
        $closingId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$closingId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Closing ID is required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = '';
        
        if (isset($data['closing_name'])) {
            $updateFields[] = "closing_name = ?";
            $params[] = $data['closing_name'];
            $types .= 's';
        }
        if (isset($data['status'])) {
            $newStatus = $data['status'];
            $updateFields[] = "status = ?";
            $params[] = $newStatus;
            $types .= 's';
            
            // If completing, create closing entries and set closed_by and closed_at
            if ($newStatus === 'Completed') {
                // Get current closing status
                $currentStatusStmt = $conn->prepare("SELECT status FROM financial_closings WHERE id = ?");
                $currentStatusStmt->bind_param('i', $closingId);
                $currentStatusStmt->execute();
                $currentStatusResult = $currentStatusStmt->get_result();
                $currentStatusRow = $currentStatusResult->fetch_assoc();
                $currentStatus = $currentStatusRow['status'] ?? null;
                $currentStatusResult->free();
                $currentStatusStmt->close();
                
                // Prevent completing if already completed
                if ($currentStatus === 'Completed') {
                    throw new Exception('Closing is already completed. Cannot complete again.');
                }
                // Get closing data
                $getClosingStmt = $conn->prepare("SELECT * FROM financial_closings WHERE id = ?");
                $getClosingStmt->bind_param('i', $closingId);
                $getClosingStmt->execute();
                $closingResult = $getClosingStmt->get_result();
                $closing = $closingResult->fetch_assoc();
                $closingResult->free();
                $getClosingStmt->close();
                
                if (!$closing) {
                    throw new Exception('Closing not found');
                }
                
                // Check if closing entry already exists
                if ($closing['closing_journal_entry_id']) {
                    throw new Exception('Closing entries already created for this period. Cannot complete again.');
                }
                
                $netIncome = floatval($closing['net_income'] ?? 0);
                
                // Only create closing entries if there's net income/loss
                if (abs($netIncome) > 0.01) {
                    // Get revenue and expense account IDs for closing entries
                    $periodStartDate = $closing['period_start_date'];
                    $periodEndDate = $closing['period_end_date'];
                    $closingDate = $closing['closing_date'];
                    
                    // Check if we need to create temporary income summary accounts or close directly
                    // For simplicity, we'll close Revenue and Expense accounts directly to Retained Earnings
                    // Get all Revenue accounts with balances
                    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
                    $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
                    if ($glTableCheck) {
                        $glTableCheck->free();
                    }
                    
                    // Get Retained Earnings account
                    $retainedStmt = $conn->prepare("
                        SELECT id FROM financial_accounts
                        WHERE (account_code = '3200' OR account_code LIKE '32%') 
                        AND (account_name LIKE '%Retained Earnings%' OR account_type = 'EQUITY')
                        ORDER BY CASE WHEN account_code = '3200' THEN 0 ELSE 1 END, id
                        LIMIT 1
                    ");
                    $retainedStmt->execute();
                    $retainedResult = $retainedStmt->get_result();
                    $retainedAccount = $retainedResult->fetch_assoc();
                    $retainedAccountId = $retainedAccount ? intval($retainedAccount['id']) : null;
                    $retainedResult->free();
                    $retainedStmt->close();
                    
                    if (!$retainedAccountId) {
                        throw new Exception('Retained Earnings account not found. Cannot create closing entries.');
                    }
                    
                    // Create closing journal entry
                    $conn->begin_transaction();
                    try {
                        // Generate entry number
                        $entryNumberStmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(entry_number, '-', -1) AS UNSIGNED)) as max_num FROM journal_entries WHERE entry_number LIKE 'CLOSE-%'");
                        $entryNumberStmt->execute();
                        $entryNumberResult = $entryNumberStmt->get_result();
                        $entryNumberRow = $entryNumberResult->fetch_assoc();
                        $nextNum = ($entryNumberRow['max_num'] ?? 0) + 1;
                        $entryNumber = 'CLOSE-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                        $entryNumberResult->free();
                        $entryNumberStmt->close();
                        
                        // Insert journal entry (totals will be recalculated after lines are added)
                        $insertJeStmt = $conn->prepare("
                            INSERT INTO journal_entries 
                            (entry_number, entry_date, description, status, total_debit, total_credit, is_posted, is_locked, entry_type)
                            VALUES (?, ?, ?, 'Posted', 0, 0, TRUE, TRUE, 'Closing')
                        ");
                        $description = "Period Closing - {$closing['closing_name']}";
                        $insertJeStmt->bind_param('sss', $entryNumber, $closingDate, $description);
                        $insertJeStmt->execute();
                        $closingJournalEntryId = $conn->insert_id;
                        $insertJeStmt->close();
                        
                        // Create closing entries: Close Revenue and Expense accounts, then net to Retained Earnings
                        // Check if general_ledger exists before querying
                        if (!$hasGLTable) {
                            throw new Exception('General ledger table not found. Cannot create closing entries. Please run general ledger migration first.');
                        }
                        
                        // Get Revenue accounts with balances in period
                        $revenueStmt = $conn->prepare("
                            SELECT 
                                gl.account_id,
                                SUM(gl.credit - gl.debit) as balance
                            FROM general_ledger gl
                            INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                            WHERE fa.account_type = 'REVENUE'
                            AND gl.posting_date >= ? AND gl.posting_date <= ?
                            GROUP BY gl.account_id
                            HAVING ABS(balance) > 0.01
                        ");
                        $revenueStmt->bind_param('ss', $periodStartDate, $periodEndDate);
                        $revenueStmt->execute();
                        $revenueResult = $revenueStmt->get_result();
                        
                        $totalRevenueClose = 0;
                        
                        // Close Revenue accounts: Dr Revenue (to zero out), Cr Retained Earnings
                        while ($revenueRow = $revenueResult->fetch_assoc()) {
                            $balance = floatval($revenueRow['balance']);
                            if (abs($balance) > 0.01) {
                                $accountId = intval($revenueRow['account_id']);
                                if ($balance > 0) {
                                    // Positive balance: Dr Revenue (to close to zero)
                                    $lineStmt = $conn->prepare("
                                        INSERT INTO journal_entry_lines 
                                        (journal_entry_id, account_id, debit_amount, credit_amount)
                                        VALUES (?, ?, ?, 0)
                                    ");
                                    $lineStmt->bind_param('iid', $closingJournalEntryId, $accountId, $balance);
                                    $lineStmt->execute();
                                    $lineStmt->close();
                                    $totalRevenueClose += $balance;
                                } else {
                                    // Negative balance (returns/refunds): Cr Revenue (to close to zero)
                                    $absBalance = abs($balance);
                                    $lineStmt = $conn->prepare("
                                        INSERT INTO journal_entry_lines 
                                        (journal_entry_id, account_id, debit_amount, credit_amount)
                                        VALUES (?, ?, 0, ?)
                                    ");
                                    $lineStmt->bind_param('iid', $closingJournalEntryId, $accountId, $absBalance);
                                    $lineStmt->execute();
                                    $lineStmt->close();
                                    $totalRevenueClose += $balance; // Negative value
                                }
                            }
                        }
                        $revenueResult->free();
                        $revenueStmt->close();
                        
                        // Get Expense accounts with balances in period
                        $expenseStmt = $conn->prepare("
                            SELECT 
                                gl.account_id,
                                SUM(gl.debit - gl.credit) as balance
                            FROM general_ledger gl
                            INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                            WHERE fa.account_type = 'EXPENSE'
                            AND gl.posting_date >= ? AND gl.posting_date <= ?
                            GROUP BY gl.account_id
                            HAVING ABS(balance) > 0.01
                        ");
                        $expenseStmt->bind_param('ss', $periodStartDate, $periodEndDate);
                        $expenseStmt->execute();
                        $expenseResult = $expenseStmt->get_result();
                        
                        $totalExpenseClose = 0;
                        
                        // Close Expense accounts: Cr Expense (to zero out)
                        while ($expenseRow = $expenseResult->fetch_assoc()) {
                            $balance = floatval($expenseRow['balance']);
                            if (abs($balance) > 0.01) {
                                $accountId = intval($expenseRow['account_id']);
                                if ($balance > 0) {
                                    // Positive balance: Cr Expense (to close to zero)
                                    $lineStmt = $conn->prepare("
                                        INSERT INTO journal_entry_lines 
                                        (journal_entry_id, account_id, debit_amount, credit_amount)
                                        VALUES (?, ?, 0, ?)
                                    ");
                                    $lineStmt->bind_param('iid', $closingJournalEntryId, $accountId, $balance);
                                    $lineStmt->execute();
                                    $lineStmt->close();
                                    $totalExpenseClose += $balance;
                                } else {
                                    // Negative balance (reversals): Dr Expense (to close to zero)
                                    $absBalance = abs($balance);
                                    $lineStmt = $conn->prepare("
                                        INSERT INTO journal_entry_lines 
                                        (journal_entry_id, account_id, debit_amount, credit_amount)
                                        VALUES (?, ?, ?, 0)
                                    ");
                                    $lineStmt->bind_param('iid', $closingJournalEntryId, $accountId, $absBalance);
                                    $lineStmt->execute();
                                    $lineStmt->close();
                                    $totalExpenseClose += $balance; // Negative value
                                }
                            }
                        }
                        $expenseResult->free();
                        $expenseStmt->close();
                        
                        // Net to Retained Earnings (Revenue - Expenses)
                        $netToRetained = $totalRevenueClose - $totalExpenseClose;
                        if (abs($netToRetained) > 0.01) {
                            if ($netToRetained > 0) {
                                // Profit: Cr Retained Earnings (opposite of Revenue debit)
                                $retainedStmt = $conn->prepare("
                                    INSERT INTO journal_entry_lines 
                                    (journal_entry_id, account_id, debit_amount, credit_amount)
                                    VALUES (?, ?, 0, ?)
                                ");
                                $retainedStmt->bind_param('iid', $closingJournalEntryId, $retainedAccountId, $netToRetained);
                                $retainedStmt->execute();
                                $retainedStmt->close();
                            } else {
                                // Loss: Dr Retained Earnings (opposite of Expense credit)
                                $absNetToRetained = abs($netToRetained);
                                $retainedStmt = $conn->prepare("
                                    INSERT INTO journal_entry_lines 
                                    (journal_entry_id, account_id, debit_amount, credit_amount)
                                    VALUES (?, ?, ?, 0)
                                ");
                                $retainedStmt->bind_param('iid', $closingJournalEntryId, $retainedAccountId, $absNetToRetained);
                                $retainedStmt->execute();
                                $retainedStmt->close();
                            }
                        }
                        
                        // Recalculate totals
                        $recalcStmt = $conn->prepare("
                            SELECT 
                                COALESCE(SUM(debit_amount), 0) as total_debit,
                                COALESCE(SUM(credit_amount), 0) as total_credit
                            FROM journal_entry_lines
                            WHERE journal_entry_id = ?
                        ");
                        $recalcStmt->bind_param('i', $closingJournalEntryId);
                        $recalcStmt->execute();
                        $recalcResult = $recalcStmt->get_result();
                        $recalcRow = $recalcResult->fetch_assoc();
                        $totalDebit = floatval($recalcRow['total_debit'] ?? 0);
                        $totalCredit = floatval($recalcRow['total_credit'] ?? 0);
                        $recalcResult->free();
                        $recalcStmt->close();
                        
                        // Validate double-entry balance
                        $balanceDiff = abs($totalDebit - $totalCredit);
                        if ($balanceDiff > 0.01) {
                            throw new Exception("Closing entry is unbalanced. Debit total ({$totalDebit}) does not equal Credit total ({$totalCredit}). Difference: {$balanceDiff}");
                        }
                        
                        // Validate that we have at least one line
                        if ($totalDebit == 0 && $totalCredit == 0) {
                            throw new Exception('Closing entry has no lines. Cannot create empty closing entry.');
                        }
                        
                        // Update journal entry totals
                        $updateJeStmt = $conn->prepare("
                            UPDATE journal_entries 
                            SET total_debit = ?, total_credit = ?
                            WHERE id = ?
                        ");
                        $updateJeStmt->bind_param('ddi', $totalDebit, $totalCredit, $closingJournalEntryId);
                        $updateJeStmt->execute();
                        $updateJeStmt->close();
                        
                        // Post to general ledger
                        if (function_exists('postJournalEntryToLedger')) {
                            $ledgerResult = postJournalEntryToLedger($conn, $closingJournalEntryId);
                            if (!$ledgerResult['success']) {
                                throw new Exception('Failed to post closing entry to general ledger: ' . ($ledgerResult['message'] ?? 'Unknown error'));
                            }
                        }
                        
                        // Update closing record with journal entry ID
                        $updateClosingStmt = $conn->prepare("
                            UPDATE financial_closings 
                            SET closing_journal_entry_id = ?
                            WHERE id = ?
                        ");
                        $updateClosingStmt->bind_param('ii', $closingJournalEntryId, $closingId);
                        $updateClosingStmt->execute();
                        $updateClosingStmt->close();
                        
                        $updateFields[] = "closed_by = ?";
                        $updateFields[] = "closed_at = NOW()";
                        $updateFields[] = "closing_journal_entry_id = ?";
                        $params[] = $_SESSION['user_id'];
                        $params[] = $closingJournalEntryId;
                        $types .= 'ii';
                        
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                } else {
                    // No net income/loss, just mark as completed
                    $updateFields[] = "closed_by = ?";
                    $updateFields[] = "closed_at = NOW()";
                    $params[] = $_SESSION['user_id'];
                    $types .= 'i';
                }
            } elseif ($newStatus === 'Reopened') {
                // Reopening a closed period - unlock it
                // Get current closing status
                $currentStatusStmt = $conn->prepare("SELECT status, closing_journal_entry_id FROM financial_closings WHERE id = ?");
                $currentStatusStmt->bind_param('i', $closingId);
                $currentStatusStmt->execute();
                $currentStatusResult = $currentStatusStmt->get_result();
                $currentStatusRow = $currentStatusResult->fetch_assoc();
                $currentStatus = $currentStatusRow['status'] ?? null;
                $closingJournalEntryId = $currentStatusRow['closing_journal_entry_id'] ?? null;
                $currentStatusResult->free();
                $currentStatusStmt->close();
                
                // Only allow reopening if currently Completed
                if ($currentStatus !== 'Completed') {
                    throw new Exception('Can only reopen completed closings. Current status: ' . ($currentStatus ?? 'Unknown'));
                }
                
                // Note: We don't delete the closing journal entry, just mark as Reopened
                // This maintains audit trail while allowing edits
            }
        }
        if (isset($data['notes'])) {
            $updateFields[] = "notes = ?";
            $params[] = $data['notes'];
            $types .= 's';
        }
        
        if (!empty($updateFields)) {
            $params[] = $closingId;
            $types .= 'i';
            $query = "UPDATE financial_closings SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Financial closing updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
        }
    } elseif ($method === 'DELETE') {
        $closingId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$closingId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Closing ID is required']);
            exit;
        }
        
        // Check if closing exists and get its status
        $checkStmt = $conn->prepare("SELECT status, closing_journal_entry_id FROM financial_closings WHERE id = ?");
        $checkStmt->bind_param('i', $closingId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $closing = $checkResult->fetch_assoc();
        $checkResult->free();
        $checkStmt->close();
        
        if (!$closing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Financial closing not found']);
            exit;
        }
        
        // Prevent deletion of completed closings that have journal entries
        if ($closing['status'] === 'Completed' && $closing['closing_journal_entry_id']) {
            throw new Exception('Cannot delete completed closing with journal entries. Reopen the closing first if you need to modify it.');
        }
        
        $stmt = $conn->prepare("DELETE FROM financial_closings WHERE id = ?");
        $stmt->bind_param('i', $closingId);
        $stmt->execute();
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Financial closing deleted successfully'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Financial closings API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

