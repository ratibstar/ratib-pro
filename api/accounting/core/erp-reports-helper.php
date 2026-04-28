<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/core/erp-reports-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/core/erp-reports-helper.php`.
 */
/**
 * ERP-Grade Financial Reports Helper
 * 
 * PHASE 4: Financial Reports that read ONLY from posted journals
 * 
 * Rules:
 * - Reports read ONLY from posted journals (status='Posted', posting_status='posted', is_posted=1)
 * - Must be branch & period aware
 * - Totals must reconcile
 */

/**
 * Get base query for general ledger with ERP filters
 * 
 * @param mysqli $conn Database connection
 * @param string|null $startDate Start date (YYYY-MM-DD)
 * @param string|null $endDate End date (YYYY-MM-DD)
 * @param int|null $branchId Branch ID filter
 * @param int|null $fiscalPeriodId Fiscal period ID filter
 * @param int|null $costCenterId Cost center ID filter
 * @return array ['query' => string, 'params' => array, 'types' => string]
 */
function getERPGeneralLedgerBaseQuery($conn, $startDate = null, $endDate = null, $branchId = null, $fiscalPeriodId = null, $costCenterId = null) {
    // Base query - ONLY from posted journals
    $query = "
        FROM general_ledger gl
        INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        WHERE je.status = 'Posted'
        AND (je.posting_status = 'posted' OR je.posting_status IS NULL)
        AND (je.is_posted = 1 OR je.is_posted IS NULL)
        AND je.posting_status != 'reversed'
    ";
    
    $params = [];
    $types = '';
    
    // Date filter
    if ($startDate) {
        $query .= " AND gl.posting_date >= ?";
        $params[] = $startDate;
        $types .= 's';
    }
    
    if ($endDate) {
        $query .= " AND gl.posting_date <= ?";
        $params[] = $endDate;
        $types .= 's';
    }
    
    // Branch filter
    if ($branchId) {
        $branchCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'branch_id'");
        $hasBranch = $branchCheck && $branchCheck->num_rows > 0;
        if ($branchCheck) $branchCheck->free();
        
        if ($hasBranch) {
            $query .= " AND gl.branch_id = ?";
            $params[] = intval($branchId);
            $types .= 'i';
        } else {
            // Fallback to journal_entries branch_id
            $query .= " AND je.branch_id = ?";
            $params[] = intval($branchId);
            $types .= 'i';
        }
    }
    
    // Fiscal period filter
    if ($fiscalPeriodId) {
        $periodCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'fiscal_period_id'");
        $hasPeriod = $periodCheck && $periodCheck->num_rows > 0;
        if ($periodCheck) $periodCheck->free();
        
        if ($hasPeriod) {
            $query .= " AND gl.fiscal_period_id = ?";
            $params[] = intval($fiscalPeriodId);
            $types .= 'i';
        } else {
            // Fallback to journal_entries fiscal_period_id
            $query .= " AND je.fiscal_period_id = ?";
            $params[] = intval($fiscalPeriodId);
            $types .= 'i';
        }
    }
    
    // Cost center filter
    if ($costCenterId) {
        $costCenterCheck = $conn->query("SHOW COLUMNS FROM general_ledger LIKE 'cost_center_id'");
        $hasCostCenter = $costCenterCheck && $costCenterCheck->num_rows > 0;
        if ($costCenterCheck) $costCenterCheck->free();
        
        if ($hasCostCenter) {
            $query .= " AND gl.cost_center_id = ?";
            $params[] = intval($costCenterId);
            $types .= 'i';
        } else {
            // Fallback to journal_entry_lines cost_center_id
            $query .= " 
                AND EXISTS (
                    SELECT 1 FROM journal_entry_lines jel 
                    WHERE jel.journal_entry_id = je.id 
                    AND jel.account_id = gl.account_id 
                    AND jel.cost_center_id = ?
                )";
            $params[] = intval($costCenterId);
            $types .= 'i';
        }
    }
    
    return [
        'query' => $query,
        'params' => $params,
        'types' => $types
    ];
}

/**
 * Generate ERP-Grade Trial Balance
 * Reads ONLY from posted journals
 * 
 * @param mysqli $conn Database connection
 * @param string|null $asOfDate As of date (YYYY-MM-DD)
 * @param int|null $branchId Branch ID filter
 * @param int|null $fiscalPeriodId Fiscal period ID filter
 * @return array Trial balance report
 */
function generateERPTrialBalance($conn, $asOfDate = null, $branchId = null, $fiscalPeriodId = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    
    $report = [
        'title' => 'Trial Balance',
        'as_of' => $asOfDate,
        'branch_id' => $branchId,
        'fiscal_period_id' => $fiscalPeriodId,
        'accounts' => [],
        'totals' => []
    ];
    
    // Get base query with ERP filters
    $baseQuery = getERPGeneralLedgerBaseQuery($conn, null, $asOfDate, $branchId, $fiscalPeriodId, null);
    
    // Get account balances from general ledger (ONLY posted entries)
    $query = "
        SELECT 
            fa.id,
            fa.account_code,
            fa.account_name,
            fa.account_type,
            fa.normal_balance,
            COALESCE(SUM(gl.debit), 0) as total_debit,
            COALESCE(SUM(gl.credit), 0) as total_credit,
            COALESCE(SUM(gl.debit), 0) - COALESCE(SUM(gl.credit), 0) as balance
        " . $baseQuery['query'] . "
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name, fa.account_type, fa.normal_balance
        HAVING total_debit > 0 OR total_credit > 0
        ORDER BY fa.account_code
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($baseQuery['params'])) {
            $stmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $totalDebit = 0;
        $totalCredit = 0;
        
        while ($row = $result->fetch_assoc()) {
            $debit = floatval($row['total_debit']);
            $credit = floatval($row['total_credit']);
            $balance = floatval($row['balance']);
            
            // Adjust balance based on normal balance
            if ($row['normal_balance'] === 'CREDIT' && $balance < 0) {
                $balance = abs($balance);
            }
            
            $report['accounts'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'account_type' => $row['account_type'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance
            ];
            
            $totalDebit += $debit;
            $totalCredit += $credit;
        }
        
        $result->free();
        $stmt->close();
        
        $report['totals'] = [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'difference' => abs($totalDebit - $totalCredit),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01
        ];
    }
    
    return $report;
}

/**
 * Generate ERP-Grade Income Statement
 * Reads ONLY from posted journals
 * 
 * @param mysqli $conn Database connection
 * @param string|null $startDate Start date (YYYY-MM-DD)
 * @param string|null $endDate End date (YYYY-MM-DD)
 * @param int|null $branchId Branch ID filter
 * @param int|null $fiscalPeriodId Fiscal period ID filter
 * @param int|null $costCenterId Cost center ID filter
 * @return array Income statement report
 */
function generateERPIncomeStatement($conn, $startDate = null, $endDate = null, $branchId = null, $fiscalPeriodId = null, $costCenterId = null) {
    $startDate = $startDate ?: date('Y-m-01', strtotime('-11 months'));
    $endDate = $endDate ?: date('Y-m-t');
    
    $report = [
        'title' => 'Income Statement',
        'period' => $startDate . ' to ' . $endDate,
        'branch_id' => $branchId,
        'fiscal_period_id' => $fiscalPeriodId,
        'cost_center_id' => $costCenterId,
        'revenue' => [],
        'expenses' => [],
        'totals' => []
    ];
    
    // Get base query with ERP filters
    $baseQuery = getERPGeneralLedgerBaseQuery($conn, $startDate, $endDate, $branchId, $fiscalPeriodId, $costCenterId);
    
    // Get Revenue accounts (REVENUE type, credit - debit)
    $revenueQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as revenue_amount
        " . $baseQuery['query'] . "
        AND fa.account_type = 'REVENUE'
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING revenue_amount > 0
        ORDER BY fa.account_code
    ";
    
    $revenueStmt = $conn->prepare($revenueQuery);
    if ($revenueStmt) {
        if (!empty($baseQuery['params'])) {
            $revenueStmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $revenueStmt->execute();
        $revenueResult = $revenueStmt->get_result();
        
        $totalRevenue = 0;
        while ($row = $revenueResult->fetch_assoc()) {
            $amount = floatval($row['revenue_amount']);
            $report['revenue'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'amount' => $amount
            ];
            $totalRevenue += $amount;
        }
        $revenueResult->free();
        $revenueStmt->close();
    }
    
    // Get Expense accounts (EXPENSE type, debit - credit)
    $expenseQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.debit - gl.credit), 0) as expense_amount
        " . $baseQuery['query'] . "
        AND fa.account_type = 'EXPENSE'
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING expense_amount > 0
        ORDER BY fa.account_code
    ";
    
    $expenseStmt = $conn->prepare($expenseQuery);
    if ($expenseStmt) {
        if (!empty($baseQuery['params'])) {
            $expenseStmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $expenseStmt->execute();
        $expenseResult = $expenseStmt->get_result();
        
        $totalExpenses = 0;
        while ($row = $expenseResult->fetch_assoc()) {
            $amount = floatval($row['expense_amount']);
            $report['expenses'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'amount' => $amount
            ];
            $totalExpenses += $amount;
        }
        $expenseResult->free();
        $expenseStmt->close();
    }
    
    $netIncome = $totalRevenue - $totalExpenses;
    
    $report['totals'] = [
        'total_revenue' => $totalRevenue,
        'total_expenses' => $totalExpenses,
        'net_income' => $netIncome
    ];
    
    return $report;
}

/**
 * Generate ERP-Grade Balance Sheet
 * Reads ONLY from posted journals
 * 
 * @param mysqli $conn Database connection
 * @param string|null $asOfDate As of date (YYYY-MM-DD)
 * @param int|null $branchId Branch ID filter
 * @param int|null $fiscalPeriodId Fiscal period ID filter
 * @param int|null $costCenterId Cost center ID filter
 * @return array Balance sheet report
 */
function generateERPBalanceSheet($conn, $asOfDate = null, $branchId = null, $fiscalPeriodId = null, $costCenterId = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    
    $report = [
        'title' => 'Balance Sheet',
        'as_of' => $asOfDate,
        'branch_id' => $branchId,
        'fiscal_period_id' => $fiscalPeriodId,
        'cost_center_id' => $costCenterId,
        'assets' => [],
        'liabilities' => [],
        'equity' => [],
        'totals' => []
    ];
    
    // Get base query with ERP filters
    $baseQuery = getERPGeneralLedgerBaseQuery($conn, null, $asOfDate, $branchId, $fiscalPeriodId, $costCenterId);
    
    // Get Assets (ASSET type)
    $assetQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.debit - gl.credit), 0) as balance
        " . $baseQuery['query'] . "
        AND fa.account_type = 'ASSET'
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $assetStmt = $conn->prepare($assetQuery);
    $totalAssets = 0;
    if ($assetStmt) {
        if (!empty($baseQuery['params'])) {
            $assetStmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $assetStmt->execute();
        $assetResult = $assetStmt->get_result();
        
        while ($row = $assetResult->fetch_assoc()) {
            $balance = floatval($row['balance']);
            if ($balance < 0) $balance = abs($balance); // Assets should be positive
            $report['assets'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'balance' => $balance
            ];
            $totalAssets += $balance;
        }
        $assetResult->free();
        $assetStmt->close();
    }
    
    // Get Liabilities (LIABILITY type)
    $liabilityQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as balance
        " . $baseQuery['query'] . "
        AND fa.account_type = 'LIABILITY'
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $liabilityStmt = $conn->prepare($liabilityQuery);
    $totalLiabilities = 0;
    if ($liabilityStmt) {
        if (!empty($baseQuery['params'])) {
            $liabilityStmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $liabilityStmt->execute();
        $liabilityResult = $liabilityStmt->get_result();
        
        while ($row = $liabilityResult->fetch_assoc()) {
            $balance = floatval($row['balance']);
            if ($balance < 0) $balance = abs($balance); // Liabilities should be positive
            $report['liabilities'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'balance' => $balance
            ];
            $totalLiabilities += $balance;
        }
        $liabilityResult->free();
        $liabilityStmt->close();
    }
    
    // Get Equity (EQUITY type)
    $equityQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as balance
        " . $baseQuery['query'] . "
        AND fa.account_type = 'EQUITY'
        AND fa.is_active = 1
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $equityStmt = $conn->prepare($equityQuery);
    $totalEquity = 0;
    if ($equityStmt) {
        if (!empty($baseQuery['params'])) {
            $equityStmt->bind_param($baseQuery['types'], ...$baseQuery['params']);
        }
        $equityStmt->execute();
        $equityResult = $equityStmt->get_result();
        
        while ($row = $equityResult->fetch_assoc()) {
            $balance = floatval($row['balance']);
            if ($balance < 0) $balance = abs($balance); // Equity should be positive
            $report['equity'][] = [
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'balance' => $balance
            ];
            $totalEquity += $balance;
        }
        $equityResult->free();
        $equityStmt->close();
    }
    
    $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;
    
    $report['totals'] = [
        'total_assets' => $totalAssets,
        'total_liabilities' => $totalLiabilities,
        'total_equity' => $totalEquity,
        'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
        'is_balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
        'difference' => abs($totalAssets - $totalLiabilitiesAndEquity)
    ];
    
    return $report;
}
