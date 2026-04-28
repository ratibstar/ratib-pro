<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/dashboard-data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/dashboard-data.php`.
 */
/**
 * Accounting Dashboard Data API
 * 
 * Provides dashboard KPIs and summaries calculated EXCLUSIVELY from general_ledger
 * Following ACCOUNTING_UI_UX_REDESIGN.md specifications
 * 
 * Data Sources:
 * - KPIs: general_ledger aggregated by account_type
 * - Trial Balance: trial_balance view
 * - Pending Approvals: entry_approval WHERE status = 'pending'
 * - Recent Activity: journal_entries ORDER BY created_at DESC
 * - Cash Flow: general_ledger WHERE account_code LIKE '11%' OR '12%'
 * - Period Status: financial_closings
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions
enforceApiPermission('accounts', 'view');

try {
    $asOfDate = isset($_GET['as_of_date']) ? $_GET['as_of_date'] : date('Y-m-d');
    $periodStart = isset($_GET['period_start']) ? $_GET['period_start'] : date('Y-m-01');
    $periodEnd = isset($_GET['period_end']) ? $_GET['period_end'] : date('Y-m-t');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfDate)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD');
    }
    
    $response = [];
    
    // ============================================
    // 1. KPI CARDS - From general_ledger ONLY
    // ============================================
    
    // Check if general_ledger table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        if ($tableCheck) {
            $tableCheck->free();
        }
        // Return empty structure if ledger doesn't exist yet
        $response = [
            'success' => true,
            'kpis' => [
                'assets' => 0,
                'liabilities' => 0,
                'equity' => 0,
                'net_income' => 0
            ],
            'trial_balance' => [
                'total_debit' => 0,
                'total_credit' => 0,
                'is_balanced' => true,
                'difference' => 0
            ],
            'pending_approvals' => [],
            'recent_activity' => [],
            'cash_flow' => [
                'inflow' => 0,
                'outflow' => 0,
                'net' => 0
            ],
            'period_status' => [
                'current_period' => 'Open',
                'last_closed' => null,
                'days_until_end' => null,
                'is_locked' => false,
                'warning' => null
            ]
        ];
        echo json_encode($response);
        exit;
    }
    $tableCheck->free();
    
    // Check if financial_accounts table exists
    $accountsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
    if (!$accountsTableCheck || $accountsTableCheck->num_rows === 0) {
        if ($accountsTableCheck) {
            $accountsTableCheck->free();
        }
        throw new Exception('Financial accounts table not found');
    }
    $accountsTableCheck->free();
    
    // ASSETS - Sum of debit - credit for ASSET accounts up to asOfDate
    $assetsStmt = $conn->prepare("
        SELECT COALESCE(SUM(gl.debit - gl.credit), 0) as total_assets
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE fa.account_type = 'ASSET'
        AND gl.posting_date <= ?
    ");
    $assetsStmt->bind_param('s', $asOfDate);
    $assetsStmt->execute();
    $assetsResult = $assetsStmt->get_result();
    $assetsRow = $assetsResult->fetch_assoc();
    $totalAssets = floatval($assetsRow['total_assets'] ?? 0);
    $assetsResult->free();
    $assetsStmt->close();
    
    // LIABILITIES - Sum of credit - debit for LIABILITY accounts up to asOfDate
    $liabilitiesStmt = $conn->prepare("
        SELECT COALESCE(SUM(gl.credit - gl.debit), 0) as total_liabilities
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE fa.account_type = 'LIABILITY'
        AND gl.posting_date <= ?
    ");
    $liabilitiesStmt->bind_param('s', $asOfDate);
    $liabilitiesStmt->execute();
    $liabilitiesResult = $liabilitiesStmt->get_result();
    $liabilitiesRow = $liabilitiesResult->fetch_assoc();
    $totalLiabilities = floatval($liabilitiesRow['total_liabilities'] ?? 0);
    $liabilitiesResult->free();
    $liabilitiesStmt->close();
    
    // EQUITY - Sum of credit - debit for EQUITY accounts up to asOfDate
    $equityStmt = $conn->prepare("
        SELECT COALESCE(SUM(gl.credit - gl.debit), 0) as total_equity
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE fa.account_type = 'EQUITY'
        AND gl.posting_date <= ?
    ");
    $equityStmt->bind_param('s', $asOfDate);
    $equityStmt->execute();
    $equityResult = $equityStmt->get_result();
    $equityRow = $equityResult->fetch_assoc();
    $totalEquity = floatval($equityRow['total_equity'] ?? 0);
    $equityResult->free();
    $equityStmt->close();
    
    // NET INCOME - Revenue - Expenses for the period
    $netIncomeStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN fa.account_type = 'REVENUE' THEN gl.credit - gl.debit ELSE 0 END), 0) as revenue,
            COALESCE(SUM(CASE WHEN fa.account_type = 'EXPENSE' THEN gl.debit - gl.credit ELSE 0 END), 0) as expenses
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE fa.account_type IN ('REVENUE', 'EXPENSE')
        AND gl.posting_date >= ? AND gl.posting_date <= ?
    ");
    $netIncomeStmt->bind_param('ss', $periodStart, $periodEnd);
    $netIncomeStmt->execute();
    $netIncomeResult = $netIncomeStmt->get_result();
    $netIncomeRow = $netIncomeResult->fetch_assoc();
    $revenue = floatval($netIncomeRow['revenue'] ?? 0);
    $expenses = floatval($netIncomeRow['expenses'] ?? 0);
    $netIncome = $revenue - $expenses;
    $netIncomeResult->free();
    $netIncomeStmt->close();
    
    // ============================================
    // 2. TRIAL BALANCE SUMMARY
    // ============================================
    
    // Check if trial_balance view exists
    $viewCheck = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_" . DB_NAME . " = 'trial_balance'");
    if ($viewCheck && $viewCheck->num_rows > 0) {
        $viewCheck->free();
        
        // Use trial_balance view
        $trialBalanceStmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(debit_balance), 0) as total_debit,
                COALESCE(SUM(credit_balance), 0) as total_credit
            FROM trial_balance
            WHERE account_id IN (
                SELECT id FROM financial_accounts WHERE is_active = 1
            )
        ");
        $trialBalanceStmt->execute();
        $trialBalanceResult = $trialBalanceStmt->get_result();
        $trialBalanceRow = $trialBalanceResult->fetch_assoc();
        $totalDebit = floatval($trialBalanceRow['total_debit'] ?? 0);
        $totalCredit = floatval($trialBalanceRow['total_credit'] ?? 0);
        $trialBalanceResult->free();
        $trialBalanceStmt->close();
    } else {
        if ($viewCheck) {
            $viewCheck->free();
        }
        
        // Fallback: Calculate from general_ledger
        $trialBalanceStmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(gl.debit), 0) as total_debit,
                COALESCE(SUM(gl.credit), 0) as total_credit
            FROM general_ledger gl
            INNER JOIN financial_accounts fa ON gl.account_id = fa.id
            WHERE fa.is_active = 1
            AND gl.posting_date <= ?
        ");
        $trialBalanceStmt->bind_param('s', $asOfDate);
        $trialBalanceStmt->execute();
        $trialBalanceResult = $trialBalanceStmt->get_result();
        $trialBalanceRow = $trialBalanceResult->fetch_assoc();
        $totalDebit = floatval($trialBalanceRow['total_debit'] ?? 0);
        $totalCredit = floatval($trialBalanceRow['total_credit'] ?? 0);
        $trialBalanceResult->free();
        $trialBalanceStmt->close();
    }
    
    $difference = abs($totalDebit - $totalCredit);
    $isBalanced = $difference < 0.01; // Tolerance for floating point
    
    // ============================================
    // 3. PENDING APPROVALS
    // ============================================
    
    $pendingApprovals = [];
    $approvalTableCheck = $conn->query("SHOW TABLES LIKE 'entry_approval'");
    if ($approvalTableCheck && $approvalTableCheck->num_rows > 0) {
        $approvalTableCheck->free();
        
        $pendingStmt = $conn->prepare("
            SELECT 
                ea.id,
                ea.entity_type,
                ea.entity_id,
                ea.status,
                ea.submitted_by,
                ea.submitted_at,
                je.entry_number,
                je.entry_date,
                je.description,
                je.total_debit,
                je.total_credit
            FROM entry_approval ea
            LEFT JOIN journal_entries je ON ea.entity_type = 'JournalEntry' AND ea.entity_id = je.id
            WHERE ea.status = 'pending'
            ORDER BY ea.submitted_at DESC
            LIMIT 10
        ");
        $pendingStmt->execute();
        $pendingResult = $pendingStmt->get_result();
        while ($row = $pendingResult->fetch_assoc()) {
            $pendingApprovals[] = [
                'id' => intval($row['id']),
                'entity_type' => $row['entity_type'],
                'entity_id' => intval($row['entity_id']),
                'entry_number' => $row['entry_number'],
                'entry_date' => $row['entry_date'],
                'description' => $row['description'],
                'amount' => floatval($row['total_debit'] ?? 0) + floatval($row['total_credit'] ?? 0),
                'submitted_by' => $row['submitted_by'],
                'submitted_at' => $row['submitted_at']
            ];
        }
        $pendingResult->free();
        $pendingStmt->close();
    } else {
        if ($approvalTableCheck) {
            $approvalTableCheck->free();
        }
    }
    
    // Count pending approvals by type
    $pendingJournalEntries = 0;
    $pendingExpenses = 0;
    foreach ($pendingApprovals as $approval) {
        if ($approval['entity_type'] === 'JournalEntry') {
            $pendingJournalEntries++;
        } elseif ($approval['entity_type'] === 'Expense') {
            $pendingExpenses++;
        }
    }
    
    // ============================================
    // 4. RECENT ACTIVITY
    // ============================================
    
    $recentActivity = [];
    $journalTableCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
    if ($journalTableCheck && $journalTableCheck->num_rows > 0) {
        $journalTableCheck->free();
        
        $activityStmt = $conn->prepare("
            SELECT 
                je.id,
                je.entry_number,
                je.entry_date,
                je.entry_type,
                je.description,
                je.status,
                je.is_posted,
                je.is_locked,
                je.created_at,
                u.username as created_by
            FROM journal_entries je
            LEFT JOIN users u ON je.created_by = u.id
            ORDER BY je.created_at DESC
            LIMIT 10
        ");
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        while ($row = $activityResult->fetch_assoc()) {
            $recentActivity[] = [
                'id' => intval($row['id']),
                'entry_number' => $row['entry_number'],
                'entry_date' => $row['entry_date'],
                'entry_type' => $row['entry_type'],
                'description' => $row['description'],
                'status' => $row['status'],
                'is_posted' => (bool)$row['is_posted'],
                'is_locked' => (bool)$row['is_locked'],
                'created_at' => $row['created_at'],
                'created_by' => $row['created_by']
            ];
        }
        $activityResult->free();
        $activityStmt->close();
    } else {
        if ($journalTableCheck) {
            $journalTableCheck->free();
        }
    }
    
    // ============================================
    // 5. CASH FLOW (Last 30 Days)
    // ============================================
    
    $cashFlowStartDate = date('Y-m-d', strtotime('-30 days'));
    
    // Cash/Bank accounts typically start with 11xx or 12xx
    $cashFlowStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN gl.debit > 0 THEN gl.debit ELSE 0 END), 0) as inflow,
            COALESCE(SUM(CASE WHEN gl.credit > 0 THEN gl.credit ELSE 0 END), 0) as outflow
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE (fa.account_code LIKE '11%' OR fa.account_code LIKE '12%')
        AND gl.posting_date >= ? AND gl.posting_date <= ?
    ");
    $cashFlowStmt->bind_param('ss', $cashFlowStartDate, $asOfDate);
    $cashFlowStmt->execute();
    $cashFlowResult = $cashFlowStmt->get_result();
    $cashFlowRow = $cashFlowResult->fetch_assoc();
    $cashInflow = floatval($cashFlowRow['inflow'] ?? 0);
    $cashOutflow = floatval($cashFlowRow['outflow'] ?? 0);
    $cashNet = $cashInflow - $cashOutflow;
    $cashFlowResult->free();
    $cashFlowStmt->close();
    
    // ============================================
    // 6. PERIOD STATUS
    // ============================================
    
    $periodStatus = [
        'current_period' => 'Open',
        'last_closed' => null,
        'days_until_end' => null,
        'is_locked' => false,
        'warning' => null
    ];
    
    $closingsTableCheck = $conn->query("SHOW TABLES LIKE 'financial_closings'");
    if ($closingsTableCheck && $closingsTableCheck->num_rows > 0) {
        $closingsTableCheck->free();
        
        // Check if current date falls in a locked period
        $lockCheckStmt = $conn->prepare("
            SELECT COUNT(*) as locked_count
            FROM financial_closings
            WHERE status = 'Completed'
            AND ? >= period_start_date 
            AND ? <= period_end_date
        ");
        $lockCheckStmt->bind_param('ss', $asOfDate, $asOfDate);
        $lockCheckStmt->execute();
        $lockCheckResult = $lockCheckStmt->get_result();
        $lockRow = $lockCheckResult->fetch_assoc();
        $isLocked = ($lockRow['locked_count'] ?? 0) > 0;
        $lockCheckResult->free();
        $lockCheckStmt->close();
        
        $periodStatus['is_locked'] = $isLocked;
        if ($isLocked) {
            $periodStatus['current_period'] = 'Locked';
        }
        
        // Get last closed period
        $lastClosedStmt = $conn->prepare("
            SELECT 
                closing_name,
                period_end_date,
                closed_at
            FROM financial_closings
            WHERE status = 'Completed'
            ORDER BY period_end_date DESC
            LIMIT 1
        ");
        $lastClosedStmt->execute();
        $lastClosedResult = $lastClosedStmt->get_result();
        $lastClosedRow = $lastClosedResult->fetch_assoc();
        if ($lastClosedRow) {
            $periodStatus['last_closed'] = [
                'name' => $lastClosedRow['closing_name'],
                'end_date' => $lastClosedRow['period_end_date'],
                'closed_at' => $lastClosedRow['closed_at']
            ];
        }
        $lastClosedResult->free();
        $lastClosedStmt->close();
        
        // Calculate days until period end (assuming monthly periods)
        $periodEndDate = date('Y-m-t'); // Last day of current month
        $today = new DateTime();
        $endDate = new DateTime($periodEndDate);
        $daysUntilEnd = $today->diff($endDate)->days;
        
        $periodStatus['days_until_end'] = $daysUntilEnd;
        
        if ($daysUntilEnd <= 5 && $daysUntilEnd >= 0) {
            $periodStatus['warning'] = "Period ends in {$daysUntilEnd} days";
        }
    } else {
        if ($closingsTableCheck) {
            $closingsTableCheck->free();
        }
    }
    
    // ============================================
    // BUILD RESPONSE
    // ============================================
    
    $response = [
        'success' => true,
        'as_of_date' => $asOfDate,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'kpis' => [
            'assets' => $totalAssets,
            'liabilities' => $totalLiabilities,
            'equity' => $totalEquity,
            'net_income' => $netIncome
        ],
        'trial_balance' => [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => $isBalanced,
            'difference' => $difference
        ],
        'pending_approvals' => [
            'total' => count($pendingApprovals),
            'journal_entries' => $pendingJournalEntries,
            'expenses' => $pendingExpenses,
            'items' => $pendingApprovals
        ],
        'recent_activity' => $recentActivity,
        'cash_flow' => [
            'inflow' => $cashInflow,
            'outflow' => $cashOutflow,
            'net' => $cashNet,
            'period_start' => $cashFlowStartDate,
            'period_end' => $asOfDate
        ],
        'period_status' => $periodStatus
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Dashboard API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard data: ' . $e->getMessage()
    ]);
}
?>
