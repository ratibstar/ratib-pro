<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/unified-calculations.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/unified-calculations.php`.
 */
/**
 * Unified Accounting Calculations API
 * Provides consistent calculations across all accounting modules:
 * - Dashboard
 * - General Ledger
 * - Receivables
 * - Payables
 * - Banking
 * - Entities
 * - Reports
 */

require_once '../../includes/config.php';
if (file_exists(__DIR__ . '/core/erp-guardian.php')) {
    require_once __DIR__ . '/core/erp-guardian.php';
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$roleId = $_SESSION['role_id'] ?? 0;

try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'dashboard' => ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0], 'timestamp' => date('Y-m-d H:i:s')]);
        exit;
    }
    $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
    }
    $response = [];
    $requestType = $_GET['type'] ?? 'all';
    
    // Get base currency (default SAR)
    $baseCurrency = 'SAR';
    
    // ============================================
    // 1. DASHBOARD CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'dashboard') {
        // Total Revenue (Income transactions - Posted status only)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(*) as revenue_count
            FROM financial_transactions 
            WHERE transaction_type = 'Income' 
            AND status = 'Posted'
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc();
        
        // Total Expenses (Expense transactions - Posted status only)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total_expenses,
                COUNT(*) as expense_count
            FROM financial_transactions 
            WHERE transaction_type = 'Expense' 
            AND status = 'Posted'
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $expenses = $stmt->get_result()->fetch_assoc();
        
        // Net Profit
        $netProfit = floatval($revenue['total_revenue']) - floatval($expenses['total_expenses']);
        
        // Cash Balance (ERP COMPLIANCE: Calculate from GL only)
        $cashBalance = 0;
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_banks'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $tableCheck->free();
            
            // ERP PRINCIPLE #1: GL is single source of truth
            // Calculate bank balances from general_ledger, not from current_balance field
            require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
            
            // Get all active banks and calculate balance from GL
            $banksStmt = $conn->prepare("SELECT id FROM accounting_banks WHERE is_active = 1");
            if ($banksStmt) {
                $banksStmt->execute();
                $banksResult = $banksStmt->get_result();
                
                while ($bankRow = $banksResult->fetch_assoc()) {
                    $bankId = intval($bankRow['id']);
                    $glBalance = getBankBalanceFromGL($conn, $bankId);
                    $cashBalance += $glBalance;
                }
                
                $banksResult->free();
                $banksStmt->close();
            }
        } else {
            if ($tableCheck) $tableCheck->free();
        }
        
        // Accounts Receivable (Outstanding invoices)
        $receivables = ['total_receivables' => 0, 'receivables_count' => 0];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_receivable'");
        if ($tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_receivables,
                    COUNT(*) as receivables_count
                FROM accounts_receivable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
            if ($stmt) {
                $stmt->execute();
                $receivables = $stmt->get_result()->fetch_assoc();
            }
        } else {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_invoices'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_receivables,
                        COUNT(*) as receivables_count
                    FROM accounting_invoices
                    WHERE status NOT IN ('Paid', 'Cancelled')
                ");
                if ($stmt) {
                    $stmt->execute();
                    $receivables = $stmt->get_result()->fetch_assoc();
                }
            }
        }
        
        // Accounts Payable (Outstanding bills)
        $payables = ['total_payables' => 0, 'payables_count' => 0];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_payable'");
        if ($tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_payables,
                    COUNT(*) as payables_count
                FROM accounts_payable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
            if ($stmt) {
                $stmt->execute();
                $payables = $stmt->get_result()->fetch_assoc();
            }
        } else {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bills'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_payables,
                        COUNT(*) as payables_count
                    FROM accounting_bills
                    WHERE status NOT IN ('Paid', 'Cancelled')
                ");
                if ($stmt) {
                    $stmt->execute();
                    $payables = $stmt->get_result()->fetch_assoc();
                }
            }
        }
        
        $response['dashboard'] = [
            'total_revenue' => floatval($revenue['total_revenue'] ?? 0),
            'total_expenses' => floatval($expenses['total_expenses'] ?? 0),
            'net_profit' => $netProfit,
            'cash_balance' => $cashBalance,
            'total_receivables' => floatval($receivables['total_receivables'] ?? 0),
            'total_payables' => floatval($payables['total_payables'] ?? 0),
            'revenue_count' => intval($revenue['revenue_count'] ?? 0),
            'expense_count' => intval($expenses['expense_count'] ?? 0),
            'receivables_count' => intval($receivables['receivables_count'] ?? 0),
            'payables_count' => intval($payables['payables_count'] ?? 0),
            'currency' => $baseCurrency
        ];
    }
    
    // ============================================
    // 2. GENERAL LEDGER CALCULATIONS
    // ERP COMPLIANCE: Read ONLY from general_ledger (single source of truth)
    // ============================================
    if ($requestType === 'all' || $requestType === 'ledger') {
        // Check if general_ledger table exists
        $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
        if ($glTableCheck && $glTableCheck->num_rows > 0) {
            $glTableCheck->free();
            
            // ERP PRINCIPLE #1: GL is single source of truth
            // Read totals from general_ledger ONLY (not from journal_entries)
            $glQuery = "
                SELECT 
                    COALESCE(SUM(gl.debit), 0) as total_debits,
                    COALESCE(SUM(gl.credit), 0) as total_credits,
                    COUNT(DISTINCT gl.journal_entry_id) as entry_count
                FROM general_ledger gl
                INNER JOIN journal_entries je ON gl.journal_entry_id = je.id
                WHERE je.status = 'Posted'
                AND (je.posting_status = 'posted' OR je.posting_status IS NULL)
                AND (je.is_posted = 1 OR je.is_posted IS NULL)
                AND je.posting_status != 'reversed'
            ";
            
            // ERP GUARDIAN: Validate report reads from GL
            if (function_exists('erpGuardian')) {
                erpGuardian($conn, 'REPORT', [
                    'query' => $glQuery,
                    'table' => 'general_ledger'
                ]);
            }
            
            $glTotalsStmt = $conn->prepare($glQuery);
            if ($glTotalsStmt) {
                $glTotalsStmt->execute();
                $glTotalsResult = $glTotalsStmt->get_result();
                $glTotals = $glTotalsResult->fetch_assoc();
                $glTotalsResult->free();
                $glTotalsStmt->close();
                
                $totalDebits = floatval($glTotals['total_debits'] ?? 0);
                $totalCredits = floatval($glTotals['total_credits'] ?? 0);
                
                $response['ledger'] = [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'balance' => $totalDebits - $totalCredits,
                    'entry_count' => intval($glTotals['entry_count'] ?? 0),
                    'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
                    'source' => 'general_ledger' // Indicate source for verification
                ];
            } else {
                $response['ledger'] = [
                    'total_debits' => 0,
                    'total_credits' => 0,
                    'balance' => 0,
                    'entry_count' => 0,
                    'is_balanced' => true,
                    'error' => 'Failed to query general_ledger'
                ];
            }
        } else {
            if ($glTableCheck) $glTableCheck->free();
            // Fallback to financial_transactions
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Income' THEN total_amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Expense' THEN total_amount ELSE 0 END), 0) as total_expense
                FROM financial_transactions
                WHERE status = 'Posted'
            ");
            $stmt->execute();
            $transactions = $stmt->get_result()->fetch_assoc();
            
            $response['ledger'] = [
                'transaction_count' => intval($transactions['transaction_count'] ?? 0),
                'total_income' => floatval($transactions['total_income'] ?? 0),
                'total_expense' => floatval($transactions['total_expense'] ?? 0),
                'net_balance' => floatval($transactions['total_income'] ?? 0) - floatval($transactions['total_expense'] ?? 0)
            ];
        }
    }
    
    // ============================================
    // 3. RECEIVABLES CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'receivables') {
        $receivables = ['total_outstanding' => 0, 'overdue' => 0, 'this_month' => 0, 'invoice_count' => 0];
        
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_receivable'");
        if ($tableCheck->num_rows > 0) {
            // Outstanding receivables
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled', 'Voided') THEN balance_amount ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month,
                    COUNT(*) as invoice_count
                FROM accounts_receivable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
            if ($stmt) {
                $stmt->execute();
                $receivables = $stmt->get_result()->fetch_assoc();
            }
        } else {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_invoices'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_outstanding,
                        COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN (total_amount - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as overdue,
                        COALESCE(SUM(CASE WHEN MONTH(invoice_date) = MONTH(CURDATE()) AND YEAR(invoice_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month,
                        COUNT(*) as invoice_count
                    FROM accounting_invoices
                    WHERE status NOT IN ('Paid', 'Cancelled')
                ");
                if ($stmt) {
                    $stmt->execute();
                    $receivables = $stmt->get_result()->fetch_assoc();
                }
            }
        }
        
        $response['receivables'] = [
            'total_outstanding' => floatval($receivables['total_outstanding'] ?? 0),
            'overdue' => floatval($receivables['overdue'] ?? 0),
            'this_month' => floatval($receivables['this_month'] ?? 0),
            'invoice_count' => intval($receivables['invoice_count'] ?? 0),
            'currency' => $baseCurrency
        ];
    }
    
    // ============================================
    // 4. PAYABLES CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'payables') {
        $payables = ['total_outstanding' => 0, 'overdue' => 0, 'this_month' => 0, 'bill_count' => 0];
        
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_payable'");
        if ($tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(balance_amount), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled', 'Voided') THEN balance_amount ELSE 0 END), 0) as overdue,
                    COALESCE(SUM(CASE WHEN MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month,
                    COUNT(*) as bill_count
                FROM accounts_payable
                WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
            ");
            if ($stmt) {
                $stmt->execute();
                $payables = $stmt->get_result()->fetch_assoc();
            }
        } else {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bills'");
            if ($tableCheck->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT 
                        COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_outstanding,
                        COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN (total_amount - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as overdue,
                        COALESCE(SUM(CASE WHEN MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE()) THEN total_amount ELSE 0 END), 0) as this_month,
                        COUNT(*) as bill_count
                    FROM accounting_bills
                    WHERE status NOT IN ('Paid', 'Cancelled')
                ");
                if ($stmt) {
                    $stmt->execute();
                    $payables = $stmt->get_result()->fetch_assoc();
                }
            }
        }
        
        $response['payables'] = [
            'total_outstanding' => floatval($payables['total_outstanding'] ?? 0),
            'overdue' => floatval($payables['overdue'] ?? 0),
            'this_month' => floatval($payables['this_month'] ?? 0),
            'bill_count' => intval($payables['bill_count'] ?? 0),
            'currency' => $baseCurrency
        ];
    }
    
    // ============================================
    // 5. BANKING CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'banking') {
        // Total bank balance
        $banking = ['account_count' => 0, 'total_balance' => 0];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_banks'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $tableCheck->free();
            
            // ERP PRINCIPLE #1: GL is single source of truth
            // Calculate bank balances from general_ledger, not from current_balance field
            require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
            
            // Get account count
            $countStmt = $conn->prepare("SELECT COUNT(*) as account_count FROM accounting_banks WHERE is_active = 1");
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $countData = $countResult->fetch_assoc();
            $accountCount = intval($countData['account_count'] ?? 0);
            $countResult->free();
            $countStmt->close();
            
            // Calculate total balance from GL
            $banksStmt = $conn->prepare("SELECT id FROM accounting_banks WHERE is_active = 1");
            $banksStmt->execute();
            $banksResult = $banksStmt->get_result();
            
            $totalBalance = 0;
            while ($bankRow = $banksResult->fetch_assoc()) {
                $bankId = intval($bankRow['id']);
                $glBalance = getBankBalanceFromGL($conn, $bankId);
                $totalBalance += $glBalance;
            }
            
            $banksResult->free();
            $banksStmt->close();
            
            $banking = [
                'account_count' => $accountCount,
                'total_balance' => $totalBalance
            ];
        }
        
        // Recent bank transactions (last 30 days)
        $bankTransactions = ['transaction_count' => 0, 'total_deposits' => 0, 'total_withdrawals' => 0];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bank_transactions'");
        if ($tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE 0 END), 0) as total_deposits,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals
                FROM accounting_bank_transactions
                WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            if ($stmt) {
                $stmt->execute();
                $bankTransactions = $stmt->get_result()->fetch_assoc();
            }
        }
        
        $response['banking'] = [
            'account_count' => intval($banking['account_count'] ?? 0),
            'total_balance' => floatval($banking['total_balance'] ?? 0),
            'transaction_count' => intval($bankTransactions['transaction_count'] ?? 0),
            'total_deposits' => floatval($bankTransactions['total_deposits'] ?? 0),
            'total_withdrawals' => floatval($bankTransactions['total_withdrawals'] ?? 0),
            'net_flow' => floatval($bankTransactions['total_deposits'] ?? 0) - floatval($bankTransactions['total_withdrawals'] ?? 0),
            'currency' => $baseCurrency
        ];
    }
    
    // ============================================
    // 6. ENTITIES CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'entities') {
        $entities = ['transaction_count' => 0, 'entity_count' => 0, 'total_revenue' => 0, 'total_expenses' => 0];
        $breakdown = [];
        
        $tableCheck = $conn->query("SHOW TABLES LIKE 'entity_transactions'");
        if ($tableCheck->num_rows > 0) {
            // Total entity transactions
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT et.id) as transaction_count,
                    COUNT(DISTINCT CONCAT(et.entity_type, ':', et.entity_id)) as entity_count,
                    COALESCE(SUM(CASE WHEN ft.transaction_type = 'Income' AND ft.status = 'Posted' THEN ft.total_amount ELSE 0 END), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN ft.transaction_type = 'Expense' AND ft.status = 'Posted' THEN ft.total_amount ELSE 0 END), 0) as total_expenses
                FROM entity_transactions et
                INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
            ");
            if ($stmt) {
                $stmt->execute();
                $entities = $stmt->get_result()->fetch_assoc();
            }
            
            // Breakdown by entity type
            $stmt = $conn->prepare("
                SELECT 
                    LOWER(et.entity_type) as entity_type,
                    COUNT(DISTINCT et.entity_id) as entity_count,
                    COUNT(et.id) as transaction_count,
                    COALESCE(SUM(CASE WHEN ft.transaction_type = 'Income' AND ft.status = 'Posted' THEN ft.total_amount ELSE 0 END), 0) as revenue,
                    COALESCE(SUM(CASE WHEN ft.transaction_type = 'Expense' AND ft.status = 'Posted' THEN ft.total_amount ELSE 0 END), 0) as expenses
                FROM entity_transactions et
                INNER JOIN financial_transactions ft ON et.transaction_id = ft.id
                GROUP BY LOWER(et.entity_type)
            ");
            if ($stmt) {
                $stmt->execute();
                $breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
            
            $response['entities'] = [
                'total_transactions' => intval($entities['transaction_count'] ?? 0),
                'total_entities' => intval($entities['entity_count'] ?? 0),
                'total_revenue' => floatval($entities['total_revenue'] ?? 0),
                'total_expenses' => floatval($entities['total_expenses'] ?? 0),
                'net_profit' => floatval($entities['total_revenue'] ?? 0) - floatval($entities['total_expenses'] ?? 0),
                'breakdown' => $breakdown,
                'currency' => $baseCurrency
            ];
        } else {
            $response['entities'] = [
                'total_transactions' => 0,
                'total_entities' => 0,
                'total_revenue' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'breakdown' => [],
                'currency' => $baseCurrency
            ];
        }
    }
    
    // ============================================
    // 7. RECONCILIATION CHECK
    // ============================================
    if ($requestType === 'all') {
        // Check if cash balance matches bank balance
        $cashBalance = $response['dashboard']['cash_balance'] ?? 0;
        $bankBalance = $response['banking']['total_balance'] ?? 0;
        
        // Calculate expected cash from transactions
        $transactions = ['total_income' => 0, 'total_expense' => 0];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if ($tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN transaction_type = 'Income' AND status = 'Posted' THEN total_amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN transaction_type = 'Expense' AND status = 'Posted' THEN total_amount ELSE 0 END), 0) as total_expense
                FROM financial_transactions
            ");
            if ($stmt) {
                $stmt->execute();
                $transactions = $stmt->get_result()->fetch_assoc();
            }
        }
        $netFromTransactions = floatval($transactions['total_income'] ?? 0) - floatval($transactions['total_expense'] ?? 0);
        
        $response['reconciliation'] = [
            'cash_balance' => $cashBalance,
            'bank_balance' => $bankBalance,
            'net_from_transactions' => $netFromTransactions,
            'difference' => abs($cashBalance - $bankBalance),
            'is_reconciled' => abs($cashBalance - $bankBalance) < 0.01
        ];
    }
    
    $response['success'] = true;
    $response['timestamp'] = date('Y-m-d H:i:s');
    
    echo json_encode($response);
    
} catch (Throwable $e) {
    error_log('Unified calculations error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Error in unified calculations: ' . $e->getMessage(),
        'dashboard' => ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>

