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
    $stubDashboardCurrency = '';
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'dashboard' => [
                'total_revenue' => 0,
                'total_expenses' => 0,
                'net_profit' => 0,
                'cash_balance' => 0,
                'total_receivables' => 0,
                'total_payables' => 0,
                'receivables_count' => 0,
                'payables_count' => 0,
                'revenue_change' => 0,
                'expense_change' => 0,
                'profit_change' => 0,
                'balance_change' => 0,
                'currency' => $stubDashboardCurrency,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
    $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
    }
    $curResEarly = @$conn->query("SELECT setting_value FROM accounting_settings WHERE setting_key = 'default_currency' LIMIT 1");
    if ($curResEarly && ($curEarlyRow = $curResEarly->fetch_assoc())) {
        $cvEarly = strtoupper(trim((string) ($curEarlyRow['setting_value'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $cvEarly)) {
            $stubDashboardCurrency = $cvEarly;
        }
    }
    if ($curResEarly instanceof mysqli_result) {
        $curResEarly->free();
    }
    $response = [];
    $requestType = $_GET['type'] ?? 'all';
    
    $baseCurrency = '';
    $curRes = @$conn->query("SELECT setting_value FROM accounting_settings WHERE setting_key = 'default_currency' LIMIT 1");
    if ($curRes && ($curRow = $curRes->fetch_assoc())) {
        $cv = strtoupper(trim((string) ($curRow['setting_value'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $cv)) {
            $baseCurrency = $cv;
        }
    }
    if ($curRes instanceof mysqli_result) {
        $curRes->free();
    }
    // Enforce active currencies for dashboard currency field.
    $tblC = @$conn->query("SHOW TABLES LIKE 'currencies'");
    if ($tblC && $tblC->num_rows > 0) {
        $codeEsc = $conn->real_escape_string($baseCurrency);
        $isActive = false;
        $r2 = @$conn->query("SELECT 1 AS ok FROM currencies WHERE (is_active = 1 OR is_active = '1') AND UPPER(TRIM(code)) = '{$codeEsc}' LIMIT 1");
        if ($r2 && $r2->num_rows > 0) {
            $isActive = true;
        }
        if ($r2 instanceof mysqli_result) {
            $r2->free();
        }
        if (!$isActive) {
            $rC = @$conn->query("SELECT UPPER(TRIM(code)) AS c FROM currencies WHERE (is_active = 1 OR is_active = '1') ORDER BY display_order ASC, code ASC LIMIT 1");
            if ($rC && ($rowC = $rC->fetch_assoc())) {
                $vC = strtoupper(trim((string) ($rowC['c'] ?? '')));
                if (preg_match('/^[A-Z]{3}$/', $vC)) {
                    $baseCurrency = $vC;
                }
            } else {
                // No active currencies: show no currency code.
                $baseCurrency = '';
            }
            if ($rC instanceof mysqli_result) {
                $rC->free();
            }
        }
    }
    if ($tblC instanceof mysqli_result) {
        $tblC->free();
    }
    
    // ============================================
    // 1. DASHBOARD CALCULATIONS
    // ============================================
    if ($requestType === 'all' || $requestType === 'dashboard') {
        // Total Revenue (Income transactions - Posted/Approved status, full history)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COUNT(*) as revenue_count
            FROM financial_transactions 
            WHERE transaction_type = 'Income' 
            AND status IN ('Approved', 'Posted')
        ");
        $stmt->execute();
        $revenue = $stmt->get_result()->fetch_assoc();
        
        // Total Expenses (Expense transactions - Posted/Approved status, full history)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) as total_expenses,
                COUNT(*) as expense_count
            FROM financial_transactions 
            WHERE transaction_type = 'Expense' 
            AND status IN ('Approved', 'Posted')
        ");
        $stmt->execute();
        $expenses = $stmt->get_result()->fetch_assoc();

        // Server-side fallback: if financial_transactions are empty/zero, derive from
        // voucher tables in the current tenant DB (receipt_vouchers/payment_vouchers).
        $currentRevenue = floatval($revenue['total_revenue'] ?? 0);
        $currentExpenses = floatval($expenses['total_expenses'] ?? 0);
        if ($currentRevenue == 0.0 && $currentExpenses == 0.0) {
            $sumVoucherTable = function(string $tableName, string $alias) use ($conn): float {
                $tbl = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
                if (!$tbl || $tbl->num_rows <= 0) {
                    if ($tbl instanceof mysqli_result) $tbl->free();
                    return 0.0;
                }
                if ($tbl instanceof mysqli_result) $tbl->free();

                $colRes = $conn->query("SHOW COLUMNS FROM `{$tableName}`");
                if (!$colRes) {
                    return 0.0;
                }
                $cols = [];
                while ($c = $colRes->fetch_assoc()) {
                    $cols[] = strtolower((string)($c['Field'] ?? ''));
                }
                $colRes->free();

                $amountCol = null;
                foreach (['amount', 'total_amount', 'paid_amount', 'payment_amount'] as $cand) {
                    if (in_array($cand, $cols, true)) {
                        $amountCol = $cand;
                        break;
                    }
                }
                if ($amountCol === null) {
                    return 0.0;
                }

                $where = ["COALESCE(`{$amountCol}`, 0) > 0"];
                if (in_array('status', $cols, true)) {
                    $where[] = "(`status` IS NULL OR `status` = '' OR `status` NOT IN ('Cancelled', 'Voided', 'Reversed'))";
                }
                $sql = "SELECT COALESCE(SUM(`{$amountCol}`), 0) AS {$alias} FROM `{$tableName}` WHERE " . implode(' AND ', $where);
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    return 0.0;
                }
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return floatval($row[$alias] ?? 0);
            };

            // Read both modern and legacy voucher table families per-agency.
            $voucherRevenue =
                $sumVoucherTable('receipt_vouchers', 'total_revenue')
                + $sumVoucherTable('payment_receipts', 'total_revenue');
            $voucherExpenses =
                $sumVoucherTable('payment_vouchers', 'total_expenses')
                + $sumVoucherTable('payment_payments', 'total_expenses');

            if ($voucherRevenue > 0 || $voucherExpenses > 0) {
                $revenue['total_revenue'] = $voucherRevenue;
                $expenses['total_expenses'] = $voucherExpenses;
                $revenue['revenue_count'] = intval($voucherRevenue > 0 ? 1 : 0);
                $expenses['expense_count'] = intval($voucherExpenses > 0 ? 1 : 0);
            }
        }

        // Final fallback: derive revenue/expense directly from General Ledger account classes
        // (REVENUE/EXPENSE) when financial_transactions and voucher totals are both zero.
        $currentRevenue = floatval($revenue['total_revenue'] ?? 0);
        $currentExpenses = floatval($expenses['total_expenses'] ?? 0);
        if ($currentRevenue == 0.0 && $currentExpenses == 0.0) {
            $glRevenue = 0.0;
            $glExpenses = 0.0;

            $glTable = $conn->query("SHOW TABLES LIKE 'general_ledger'");
            $faTable = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
            if ($glTable && $glTable->num_rows > 0 && $faTable && $faTable->num_rows > 0) {
                $revSql = "
                    SELECT COALESCE(SUM(gl.credit - gl.debit), 0) AS total_revenue
                    FROM general_ledger gl
                    INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                    WHERE fa.account_type = 'REVENUE'
                      AND fa.is_active = 1
                ";
                $revStmt = $conn->prepare($revSql);
                if ($revStmt) {
                    $revStmt->execute();
                    $revRow = $revStmt->get_result()->fetch_assoc();
                    $glRevenue = floatval($revRow['total_revenue'] ?? 0);
                    $revStmt->close();
                }

                $expSql = "
                    SELECT COALESCE(SUM(gl.debit - gl.credit), 0) AS total_expenses
                    FROM general_ledger gl
                    INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                    WHERE fa.account_type = 'EXPENSE'
                      AND fa.is_active = 1
                ";
                $expStmt = $conn->prepare($expSql);
                if ($expStmt) {
                    $expStmt->execute();
                    $expRow = $expStmt->get_result()->fetch_assoc();
                    $glExpenses = floatval($expRow['total_expenses'] ?? 0);
                    $expStmt->close();
                }
            }
            if ($glTable instanceof mysqli_result) {
                $glTable->free();
            }
            if ($faTable instanceof mysqli_result) {
                $faTable->free();
            }

            if ($glRevenue > 0 || $glExpenses > 0) {
                $revenue['total_revenue'] = $glRevenue;
                $expenses['total_expenses'] = $glExpenses;
                $revenue['revenue_count'] = intval($glRevenue > 0 ? 1 : 0);
                $expenses['expense_count'] = intval($glExpenses > 0 ? 1 : 0);
            }
        }

        // Legacy schema fallback: some agency DBs don't maintain account_type reliably.
        // Use account_code classes (4* revenue, 5* expense) from GL as a final source.
        $currentRevenue = floatval($revenue['total_revenue'] ?? 0);
        $currentExpenses = floatval($expenses['total_expenses'] ?? 0);
        if ($currentRevenue == 0.0 && $currentExpenses == 0.0) {
            $codeRevenue = 0.0;
            $codeExpenses = 0.0;

            $glTable = $conn->query("SHOW TABLES LIKE 'general_ledger'");
            $faTable = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
            if ($glTable && $glTable->num_rows > 0 && $faTable && $faTable->num_rows > 0) {
                $revSql = "
                    SELECT COALESCE(SUM(gl.credit - gl.debit), 0) AS total_revenue
                    FROM general_ledger gl
                    INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                    WHERE fa.account_code LIKE '4%'
                ";
                $revStmt = $conn->prepare($revSql);
                if ($revStmt) {
                    $revStmt->execute();
                    $revRow = $revStmt->get_result()->fetch_assoc();
                    $codeRevenue = floatval($revRow['total_revenue'] ?? 0);
                    $revStmt->close();
                }

                $expSql = "
                    SELECT COALESCE(SUM(gl.debit - gl.credit), 0) AS total_expenses
                    FROM general_ledger gl
                    INNER JOIN financial_accounts fa ON gl.account_id = fa.id
                    WHERE fa.account_code LIKE '5%'
                ";
                $expStmt = $conn->prepare($expSql);
                if ($expStmt) {
                    $expStmt->execute();
                    $expRow = $expStmt->get_result()->fetch_assoc();
                    $codeExpenses = floatval($expRow['total_expenses'] ?? 0);
                    $expStmt->close();
                }
            }
            if ($glTable instanceof mysqli_result) {
                $glTable->free();
            }
            if ($faTable instanceof mysqli_result) {
                $faTable->free();
            }

            if ($codeRevenue > 0 || $codeExpenses > 0) {
                $revenue['total_revenue'] = $codeRevenue;
                $expenses['total_expenses'] = $codeExpenses;
                $revenue['revenue_count'] = intval($codeRevenue > 0 ? 1 : 0);
                $expenses['expense_count'] = intval($codeExpenses > 0 ? 1 : 0);
            }
        }

        // Final ledger-line fallback: derive from posted journal_entry_lines when
        // financial_transactions/general_ledger summaries are unavailable.
        $currentRevenue = floatval($revenue['total_revenue'] ?? 0);
        $currentExpenses = floatval($expenses['total_expenses'] ?? 0);
        if ($currentRevenue == 0.0 && $currentExpenses == 0.0) {
            $jelRevenue = 0.0;
            $jelExpenses = 0.0;

            $jeTable = $conn->query("SHOW TABLES LIKE 'journal_entries'");
            $jelTable = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
            $faTable = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
            if ($jeTable && $jeTable->num_rows > 0 && $jelTable && $jelTable->num_rows > 0 && $faTable && $faTable->num_rows > 0) {
                $jelColsRes = $conn->query("SHOW COLUMNS FROM journal_entry_lines");
                $jelCols = [];
                if ($jelColsRes) {
                    while ($c = $jelColsRes->fetch_assoc()) {
                        $jelCols[] = strtolower((string)($c['Field'] ?? ''));
                    }
                    $jelColsRes->free();
                }
                $debitCol = in_array('debit_amount', $jelCols, true) ? 'debit_amount' : (in_array('debit', $jelCols, true) ? 'debit' : null);
                $creditCol = in_array('credit_amount', $jelCols, true) ? 'credit_amount' : (in_array('credit', $jelCols, true) ? 'credit' : null);

                if ($debitCol !== null && $creditCol !== null) {
                    $postedWhere = "(je.status = 'Posted' OR je.status = 'Approved')";

                    $revSql = "
                        SELECT COALESCE(SUM(jel.`{$creditCol}` - jel.`{$debitCol}`), 0) AS total_revenue
                        FROM journal_entry_lines jel
                        INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
                        INNER JOIN financial_accounts fa ON fa.id = jel.account_id
                        WHERE {$postedWhere}
                          AND (
                            fa.account_type = 'REVENUE'
                            OR fa.account_code LIKE '4%'
                          )
                    ";
                    $revStmt = $conn->prepare($revSql);
                    if ($revStmt) {
                        $revStmt->execute();
                        $revRow = $revStmt->get_result()->fetch_assoc();
                        $jelRevenue = floatval($revRow['total_revenue'] ?? 0);
                        $revStmt->close();
                    }

                    $expSql = "
                        SELECT COALESCE(SUM(jel.`{$debitCol}` - jel.`{$creditCol}`), 0) AS total_expenses
                        FROM journal_entry_lines jel
                        INNER JOIN journal_entries je ON je.id = jel.journal_entry_id
                        INNER JOIN financial_accounts fa ON fa.id = jel.account_id
                        WHERE {$postedWhere}
                          AND (
                            fa.account_type = 'EXPENSE'
                            OR fa.account_code LIKE '5%'
                          )
                    ";
                    $expStmt = $conn->prepare($expSql);
                    if ($expStmt) {
                        $expStmt->execute();
                        $expRow = $expStmt->get_result()->fetch_assoc();
                        $jelExpenses = floatval($expRow['total_expenses'] ?? 0);
                        $expStmt->close();
                    }
                }
            }
            if ($jeTable instanceof mysqli_result) $jeTable->free();
            if ($jelTable instanceof mysqli_result) $jelTable->free();
            if ($faTable instanceof mysqli_result) $faTable->free();

            if ($jelRevenue > 0 || $jelExpenses > 0) {
                $revenue['total_revenue'] = $jelRevenue;
                $expenses['total_expenses'] = $jelExpenses;
                $revenue['revenue_count'] = intval($jelRevenue > 0 ? 1 : 0);
                $expenses['expense_count'] = intval($jelExpenses > 0 ? 1 : 0);
            }
        }

        // Prior rolling 30-day window (days 31–60 ago) for % change vs current 30 days
        $prevRevenue = 0.0;
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) AS total_revenue
            FROM financial_transactions
            WHERE transaction_type = 'Income'
            AND status IN ('Approved', 'Posted')
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND transaction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        if ($stmt) {
            $stmt->execute();
            $pr = $stmt->get_result()->fetch_assoc();
            $prevRevenue = floatval($pr['total_revenue'] ?? 0);
            $stmt->close();
        }
        $prevExpenses = 0.0;
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) AS total_expenses
            FROM financial_transactions
            WHERE transaction_type = 'Expense'
            AND status IN ('Approved', 'Posted')
            AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            AND transaction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        if ($stmt) {
            $stmt->execute();
            $pe = $stmt->get_result()->fetch_assoc();
            $prevExpenses = floatval($pe['total_expenses'] ?? 0);
            $stmt->close();
        }
        
        // When there is no P&L (Income/Expense) data but the ledger still has posted
        // movement (e.g. AR, AP, cash transfers), surface GL debit/credit totals so
        // dashboard cards are not stuck at zero while GL clearly has activity.
        $currRevBeforeGlActivity = floatval($revenue['total_revenue'] ?? 0);
        $currExpBeforeGlActivity = floatval($expenses['total_expenses'] ?? 0);
        if ($currRevBeforeGlActivity == 0.0 && $currExpBeforeGlActivity == 0.0) {
            $glTbl = $conn->query("SHOW TABLES LIKE 'general_ledger'");
            if ($glTbl && $glTbl->num_rows > 0) {
                $glDebitCol = null;
                $glCreditCol = null;
                $cd = $conn->query("SHOW COLUMNS FROM general_ledger");
                if ($cd) {
                    $have = [];
                    while ($r = $cd->fetch_assoc()) {
                        $have[strtolower((string)($r['Field'] ?? ''))] = true;
                    }
                    $cd->free();
                    if (!empty($have['debit']) && !empty($have['credit'])) {
                        $glDebitCol = 'debit';
                        $glCreditCol = 'credit';
                    } elseif (!empty($have['debit_amount']) && !empty($have['credit_amount'])) {
                        $glDebitCol = 'debit_amount';
                        $glCreditCol = 'credit_amount';
                    }
                }
                if ($glDebitCol !== null && $glCreditCol !== null) {
                    $glActSql = "
                        SELECT
                            COALESCE(SUM(gl.`{$glDebitCol}`), 0) AS total_debits,
                            COALESCE(SUM(gl.`{$glCreditCol}`), 0) AS total_credits,
                            COUNT(*) AS line_count
                        FROM general_ledger gl
                    ";
                    $glActStmt = $conn->prepare($glActSql);
                    if ($glActStmt) {
                        $glActStmt->execute();
                        $glActRow = $glActStmt->get_result()->fetch_assoc();
                        $glActStmt->close();
                        $td = floatval($glActRow['total_debits'] ?? 0);
                        $tc = floatval($glActRow['total_credits'] ?? 0);
                        $lc = intval($glActRow['line_count'] ?? 0);
                        if ($td > 0 || $tc > 0) {
                            // Map credits → "revenue side" and debits → "expense side" for summary cards only.
                            $revenue['total_revenue'] = $tc;
                            $expenses['total_expenses'] = $td;
                            $revenue['revenue_count'] = max(1, $lc);
                            $expenses['expense_count'] = max(1, $lc);
                        }
                    }
                }
            }
            if ($glTbl instanceof mysqli_result) {
                $glTbl->free();
            }
        }

        // Net Profit
        $netProfit = floatval($revenue['total_revenue']) - floatval($expenses['total_expenses']);
        
        // Cash Balance (ERP COMPLIANCE: Calculate from GL only)
        $cashBalance = 0;
        $cashBalancePrev = 0.0;
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

                $asOfPrev = date('Y-m-d', strtotime('-30 days'));
                $banksStmtPrev = $conn->prepare("SELECT id FROM accounting_banks WHERE is_active = 1");
                if ($banksStmtPrev) {
                    $banksStmtPrev->execute();
                    $banksPrevRes = $banksStmtPrev->get_result();
                    while ($bankRow = $banksPrevRes->fetch_assoc()) {
                        $bankId = intval($bankRow['id']);
                        $cashBalancePrev += getBankBalanceFromGL($conn, $bankId, $asOfPrev);
                    }
                    $banksPrevRes->free();
                    $banksStmtPrev->close();
                }
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

        $currRev = floatval($revenue['total_revenue'] ?? 0);
        $currExp = floatval($expenses['total_expenses'] ?? 0);
        $revenue_change = $prevRevenue > 0
            ? (($currRev - $prevRevenue) / $prevRevenue) * 100.0
            : ($currRev > 0 ? 100.0 : 0.0);
        $expense_change = $prevExpenses > 0
            ? (($currExp - $prevExpenses) / $prevExpenses) * 100.0
            : ($currExp > 0 ? 100.0 : 0.0);
        $prevProfit = $prevRevenue - $prevExpenses;
        if (abs($prevProfit) >= 0.0000001) {
            $profit_change = (($netProfit - $prevProfit) / abs($prevProfit)) * 100.0;
        } else {
            $profit_change = abs($netProfit) < 0.0000001 ? 0.0 : ($netProfit > 0 ? 100.0 : -100.0);
        }
        $balance_change = $cashBalancePrev > 0
            ? (($cashBalance - $cashBalancePrev) / $cashBalancePrev) * 100.0
            : ($cashBalance > 0 ? 100.0 : 0.0);
        
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
            'revenue_change' => round($revenue_change, 1),
            'expense_change' => round($expense_change, 1),
            'profit_change' => round($profit_change, 1),
            'balance_change' => round($balance_change, 1),
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
    
    $response['tenant_debug'] = [
        'db' => (string)($GLOBALS['agency_db']['db'] ?? DB_NAME),
        'agency_id_get' => isset($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0,
        'agency_id_session' => isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0,
        'control' => !empty($_GET['control']) && (string)$_GET['control'] === '1' ? 1 : 0
    ];
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

