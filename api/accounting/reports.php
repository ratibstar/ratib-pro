<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/reports.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/reports.php`.
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

// Helper function to safely escape dates for SQL queries (defense in depth)
// Dates should already be validated, but escaping provides additional security
function escapeDate($conn, $date) {
    if ($date === null) {
        return null;
    }
    // Dates should already be in Y-m-d format from validation
    // But escape them anyway for defense in depth
    return $conn->real_escape_string($date);
}

// Helper function to safely execute queries - prevents unbound placeholder errors
function safeQuery($conn, $query, $params = []) {
    if (strpos($query, '?') !== false) {
        // Query has placeholders - must use prepared statement
        if (empty($params)) {
            error_log('ERROR: Query has ? but no params provided: ' . substr($query, 0, 200));
            throw new Exception('Query has unbound placeholders: ' . substr($query, 0, 100));
        }
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log('ERROR: Failed to prepare query: ' . $conn->error . ' Query: ' . substr($query, 0, 200));
            throw new Exception('Failed to prepare query: ' . $conn->error);
        }
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) $types .= 'i';
            elseif (is_float($param)) $types .= 'd';
            else $types .= 's';
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            error_log('ERROR: Execute failed: ' . $stmt->error);
            $stmt->close();
            throw new Exception('Query execute failed: ' . $stmt->error);
        }
        return $stmt->get_result();
    } else {
        // No placeholders - safe to use query()
        return $conn->query($query);
    }
}

try {
    $reportType = isset($_GET['type']) ? $_GET['type'] : '';
    
    if (empty($reportType)) {
        echo json_encode([
            'success' => false,
            'message' => 'Report type is required'
        ]);
        exit;
    }
    
    // Get optional date range parameters
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $asOfDate = isset($_GET['as_of']) ? $_GET['as_of'] : null;
    $accountId = isset($_GET['account_id']) && $_GET['account_id'] !== '' ? intval($_GET['account_id']) : null;
    $costCenterId = isset($_GET['cost_center_id']) && $_GET['cost_center_id'] !== '' ? intval($_GET['cost_center_id']) : null;
    // Validate cost_center_id must be a positive integer
    if ($costCenterId !== null && $costCenterId <= 0) {
        $costCenterId = null;
    }
    $searchTerm = isset($_GET['search_term']) && $_GET['search_term'] !== '' ? trim($_GET['search_term']) : null;
    
    // Validate dates if provided (dates are validated and will be used in prepared statements)
    if ($startDate && !strtotime($startDate)) {
        $startDate = null;
    }
    if ($endDate && !strtotime($endDate)) {
        $endDate = null;
    }
    if ($asOfDate && !strtotime($asOfDate)) {
        $asOfDate = null;
    }
    
    // Escape dates for use in SQL queries (defense in depth - dates are already validated)
    // Note: Dates are validated above with strtotime(), but escaping provides additional security
    $escapedStartDate = $startDate ? escapeDate($conn, $startDate) : null;
    $escapedEndDate = $endDate ? escapeDate($conn, $endDate) : null;
    $escapedAsOfDate = $asOfDate ? escapeDate($conn, $asOfDate) : null;
    
    // Validate date range (startDate must be <= endDate)
    if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
        echo json_encode([
            'success' => false,
            'message' => 'Start date must be before or equal to end date'
        ]);
        exit;
    }
    
    // Validate account_id if provided (accountId can be 0, so check for !== null)
    if ($accountId !== null && $accountId !== '') {
        $accountId = intval($accountId);
        if ($accountId > 0) {
            // Always check if financial_accounts exists (it should)
            $accountCheckStmt = $conn->prepare("SELECT id FROM financial_accounts WHERE id = ? LIMIT 1");
            if ($accountCheckStmt) {
                $accountCheckStmt->bind_param('i', $accountId);
                if ($accountCheckStmt->execute()) {
                    $accountCheck = $accountCheckStmt->get_result();
                    if (!$accountCheck || $accountCheck->num_rows === 0) {
                        $accountCheckStmt->close();
                        echo json_encode([
                            'success' => false,
                            'message' => 'Invalid account ID provided'
                        ]);
                        exit;
                    }
                }
                $accountCheckStmt->close();
            }
        } elseif ($accountId < 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Account ID must be a positive number'
            ]);
            exit;
        }
    }
    
    // Check if financial_transactions table exists (only for reports that need it)
    // Some reports like trial-balance and general-ledger work with financial_accounts only
    $reportsNeedingTransactions = [
        'income-statement', 'profit-loss', 'cash-flow', 'cash-book', 'bank-book',
        'expense-statement', 'value-added', 'entries-by-year', 'changes-equity',
        'financial-performance', 'comparative-report'
    ];
    
    if (in_array($reportType, $reportsNeedingTransactions)) {
        if (!tableExists($conn, 'financial_transactions')) {
            echo json_encode([
                'success' => false,
                'message' => 'Financial transactions table not found'
            ]);
            exit;
        }
    }
    
    $report = [];
    
    switch ($reportType) {
        case 'trial-balance':
            // Trial Balance Report
            $report = generateTrialBalance($conn, $asOfDate);
            break;
            
        case 'income-statement':
        case 'profit-loss':
            // Income Statement / Profit & Loss (from general_ledger only)
            $report = generateIncomeStatement($conn, $startDate, $endDate, $costCenterId);
            break;
            
        case 'balance-sheet':
            // Balance Sheet (from general_ledger only)
            $report = generateBalanceSheet($conn, $asOfDate, $costCenterId);
            break;
            
        case 'cash-flow':
            // Cash Flow Statement (from general_ledger only)
            $report = generateCashFlow($conn, $startDate, $endDate, $costCenterId);
            break;
            
        case 'vat-report':
        case 'vat-reports':
            // VAT Reports (Saudi compliant, from general_ledger only)
            $report = generateVATReport($conn, $startDate, $endDate, $costCenterId);
            break;
            
        case 'aged-receivables':
        case 'ages-debt-receivable':
            // Aged Receivables / Ages of Debt Receivable
            $report = generateAgedReceivables($conn, $asOfDate);
            break;
            
        case 'ages-credit-receivable':
            // Ages of Credit Receivable
            $report = generateAgedCreditReceivable($conn, $asOfDate);
            break;
            
        case 'aged-payables':
            // Aged Payables
            $report = generateAgedPayables($conn, $asOfDate);
            break;
            
        case 'cash-book':
            // Cash Book Report
            $report = generateCashBook($conn, $startDate, $endDate);
            break;
            
        case 'bank-book':
            // Bank Book Report
            $report = generateBankBook($conn, $startDate, $endDate);
            break;
            
        case 'general-ledger':
        case 'general-ledger-report':
            // General Ledger Report
            $report = generateGeneralLedgerReport($conn, $startDate, $endDate, $accountId, $searchTerm);
            break;
            
        case 'account-statement':
            // Account Statement (similar to General Ledger but for specific account)
            $report = generateAccountStatement($conn, $accountId, $startDate, $endDate);
            break;
            
        case 'expense-statement':
            // Expense Statement
            $report = generateExpenseStatement($conn, $startDate, $endDate);
            break;
            
        case 'chart-of-accounts-report':
            // Chart of Accounts Report
            $report = generateChartOfAccounts($conn, $asOfDate);
            break;
            
        case 'value-added':
            // Value Added Report
            $report = generateValueAdded($conn, $startDate, $endDate);
            break;
            
        case 'fixed-assets':
            // Fixed Assets Report
            $report = generateFixedAssets($conn, $asOfDate);
            break;
            
        case 'entries-by-year':
            // Entries by Year Report
            $report = generateEntriesByYear($conn, $startDate, $endDate);
            break;
            
        case 'customer-debits':
            // Customer Debits Report
            $report = generateCustomerDebits($conn, $asOfDate);
            break;
            
        case 'statistical-position':
            // Statistical Position Report
            $report = generateStatisticalPosition($conn, $asOfDate);
            break;
            
        case 'changes-equity':
            // Changes in Equity Report
            $report = generateChangesInEquity($conn, $startDate, $endDate);
            break;
            
        case 'financial-performance':
            // Financial Performance Report
            $report = generateFinancialPerformance($conn, $startDate, $endDate);
            break;
            
        case 'comparative-report':
            // Comparative Report
            $report = generateComparativeReport($conn, $startDate, $endDate);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown report type: ' . $reportType
            ]);
            exit;
    }
    
    // Add debug info to help diagnose empty reports
    $debugInfo = [];
    if (isset($report['accounts']) && is_array($report['accounts'])) {
        $debugInfo['accounts_count'] = count($report['accounts']);
    }
    if (isset($report['debug'])) {
        $debugInfo['debug'] = $report['debug'];
    }
    
    // Check if financial_accounts table has data
    try {
        // Make sure this query has no placeholders
        $countQuery = "SELECT COUNT(*) as total FROM financial_accounts";
        if (strpos($countQuery, '?') === false) {
            $countResult = $conn->query($countQuery);
            if ($countResult) {
                $countRow = $countResult->fetch_assoc();
                $debugInfo['total_accounts_in_db'] = intval($countRow['total']);
                $countResult->free();
            } else {
                $debugInfo['count_error'] = $conn->error;
            }
        }
        $debugInfo['table_exists_check'] = tableExists($conn, 'financial_accounts');
    } catch (Exception $e) {
        $debugInfo['count_error'] = $e->getMessage();
        error_log('Debug count query error: ' . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report,
        'type' => $reportType,
        'generated_at' => date('Y-m-d H:i:s'),
        'debug' => $debugInfo
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $trace = $e->getTraceAsString();
    
    error_log('Report Generation Error: ' . $errorMsg);
    error_log('Error File: ' . $errorFile);
    error_log('Error Line: ' . $errorLine);
    error_log('Stack Trace: ' . $trace);
    error_log('Report Type: ' . (isset($reportType) ? $reportType : 'unknown'));
    error_log('MySQL Error: ' . (isset($conn) && $conn->error ? $conn->error : 'none'));
    
    // If it's a SQL syntax error with '?', provide more details
    if (strpos($errorMsg, "near '?'") !== false) {
        error_log('SQL Error suggests unbound placeholder. Check for queries with ? executed without prepare()');
        // Try to get more context from the trace
        $traceLines = explode("\n", $trace);
        foreach ($traceLines as $traceLine) {
            if (strpos($traceLine, 'reports.php') !== false) {
                error_log('Trace line in reports.php: ' . $traceLine);
            }
        }
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $errorMsg,
        'error_details' => [
            'file' => $errorFile,
            'line' => $errorLine,
            'trace' => $trace,
            'mysql_error' => (isset($conn) && $conn->error ? $conn->error : null),
            'report_type' => (isset($reportType) ? $reportType : 'unknown')
        ]
    ]);
}

function tableExists($conn, $tableName) {
    // Always return true for financial_accounts to avoid blocking reports
    if ($tableName === 'financial_accounts') {
        return true;
    }
    
    try {
        // Whitelist allowed table names to prevent SQL injection
        $allowedTables = [
            'financial_accounts', 'journal_entries', 'journal_entry_lines',
            'accounts_receivable', 'accounts_payable', 'financial_transactions',
            'transaction_lines', 'payment_receipts', 'payment_payments',
            'accounting_banks', 'accounting_bank_transactions', 'entity_transactions'
        ];
        
        // Only check if table name is in whitelist
        if (!in_array($tableName, $allowedTables)) {
            return false;
        }
        
        // Try multiple methods to check table existence
        // Method 1: SHOW TABLES LIKE (safest - uses prepared statement)
        try {
            $stmt = $conn->prepare("SHOW TABLES LIKE ?");
            if ($stmt) {
                $stmt->bind_param('s', $tableName);
                if ($stmt->execute()) {
                    $result = $stmt->get_result();
                    $exists = $result && $result->num_rows > 0;
                    $stmt->close();
                    if ($exists) {
                        return true;
                    }
                } else {
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log('tableExists method 1 error: ' . $e->getMessage());
            // Fall through to method 2
        }
        
        // Method 2: Query INFORMATION_SCHEMA (more reliable)
        try {
            $dbNameQuery = $conn->query("SELECT DATABASE()");
            if ($dbNameQuery) {
                $dbNameRow = $dbNameQuery->fetch_row();
                if ($dbNameRow && isset($dbNameRow[0])) {
                    $dbName = $dbNameRow[0];
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
                    if ($checkStmt) {
                        $checkStmt->bind_param('ss', $dbName, $tableName);
                        if ($checkStmt->execute()) {
                            $result = $checkStmt->get_result();
                            $row = $result->fetch_row();
                            $checkStmt->close();
                            if ($row && $row[0] > 0) {
                                return true;
                            }
                        }
                        $checkStmt->close();
                    }
                }
            }
        } catch (Exception $e) {
            error_log('tableExists method 2 error: ' . $e->getMessage());
            // Fall through to method 3
        }
        
        // Method 3: Try to query the table directly (last resort)
        try {
            $testQuery = $conn->query("SELECT 1 FROM `{$tableName}` LIMIT 1");
            if ($testQuery !== false) {
                return true;
            }
        } catch (Exception $e) {
            // Table doesn't exist or query failed - this is expected
            error_log('tableExists method 3: Table may not exist: ' . $tableName);
        }
        
        return false;
    } catch (Exception $e) {
        error_log('tableExists error: ' . $e->getMessage());
        return false;
    }
}

function columnExists($conn, $tableName, $columnName) {
    try {
        // Whitelist allowed table names to prevent SQL injection
        $allowedTables = [
            'financial_accounts', 'journal_entries', 'journal_entry_lines',
            'accounts_receivable', 'accounts_payable', 'financial_transactions',
            'transaction_lines', 'payment_receipts', 'payment_payments',
            'accounting_banks', 'accounting_bank_transactions', 'entity_transactions'
        ];
        
        // Only check if table name is in whitelist
        if (!in_array($tableName, $allowedTables)) {
            return false;
        }
        
        // Whitelist allowed column names (common columns)
        $allowedColumns = [
            'id', 'account_code', 'account_name', 'account_type', 'debit_amount',
            'credit_amount', 'entry_date', 'description', 'status', 'currency',
            'amount', 'total_amount', 'created_at', 'updated_at'
        ];
        
        // For column names, we'll validate the pattern (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
            return false;
        }
        
        try {
            $stmt = $conn->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
            if (!$stmt) {
                error_log('columnExists: prepare failed for ' . $tableName . '.' . $columnName . ': ' . $conn->error);
                return false;
            }
            $stmt->bind_param('s', $columnName);
            if (!$stmt->execute()) {
                error_log('columnExists: execute failed for ' . $tableName . '.' . $columnName . ': ' . $stmt->error);
                $stmt->close();
                return false;
            }
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
        } catch (Exception $e) {
            error_log('columnExists exception: ' . $e->getMessage());
            return false;
        }
    } catch (Exception $e) {
        error_log('columnExists error: ' . $e->getMessage());
        return false;
    }
}

function generateTrialBalance($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Trial Balance',
        'period' => $asOfDate,
        'accounts' => []
    ];
    
    // Check if financial_accounts table exists
    // Always try to query, even if tableExists check fails
    $tableExistsCheck = tableExists($conn, 'financial_accounts');
    if ($tableExistsCheck || true) { // Always try
        // Check which columns exist
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasTotalDebit = false;
        $hasTotalCredit = false;
        $hasCurrentBalance = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'total_debit') $hasTotalDebit = true;
                if ($col['Field'] === 'total_credit') $hasTotalCredit = true;
                if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
            }
        }
        
        // Check if transaction_lines and journal_entry_lines exist for calculation
        $hasTransactionLines = tableExists($conn, 'transaction_lines');
        $hasJournalEntryLines = tableExists($conn, 'journal_entry_lines');
        
        // Build SELECT with calculated totals if columns are missing or zero
        $selectFields = ['fa.account_code', 'fa.account_name'];
        
        if ($hasTotalDebit && $hasTotalCredit) {
            // Use stored values, but also calculate from transaction_lines and journal_entry_lines as fallback
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlDebitSubquery = $hasTransactionLines ? "(
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                )" : "0";
                $jelDebitSubquery = $hasJournalEntryLines ? "(
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                )" : "0";
                $tlCreditSubquery = $hasTransactionLines ? "(
                    SELECT COALESCE(SUM(COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                )" : "0";
                $jelCreditSubquery = $hasJournalEntryLines ? "(
                    SELECT COALESCE(SUM(COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                )" : "0";
                
                $selectFields[] = "COALESCE(
                    NULLIF(fa.total_debit, 0),
                    ({$tlDebitSubquery}) + ({$jelDebitSubquery})
                ) as debit";
                
                $selectFields[] = "COALESCE(
                    NULLIF(fa.total_credit, 0),
                    ({$tlCreditSubquery}) + ({$jelCreditSubquery})
                ) as credit";
            } else {
                // No transaction lines tables, use stored values only
                $selectFields[] = "COALESCE(fa.total_debit, 0) as debit";
                $selectFields[] = "COALESCE(fa.total_credit, 0) as credit";
            }
        } else {
            // Calculate from transaction_lines and journal_entry_lines
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlDebitSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelDebitSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                $tlCreditSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelCreditSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                
                $selectFields[] = "({$tlDebitSubquery} + {$jelDebitSubquery}) as debit";
                $selectFields[] = "({$tlCreditSubquery} + {$jelCreditSubquery}) as credit";
            } else {
                $selectFields[] = '0 as debit';
                $selectFields[] = '0 as credit';
            }
        }
        
        if ($hasCurrentBalance) {
            $selectFields[] = 'COALESCE(fa.current_balance, 0) as balance';
        } else {
            // Calculate balance from debit - credit (will be calculated in PHP if needed)
            $selectFields[] = '0 as balance';
        }
        
        $query = "
            SELECT " . implode(', ', $selectFields) . "
            FROM financial_accounts fa
            ORDER BY fa.account_code
        ";
        
        // Check if query contains unbound placeholders
        if (strpos($query, '?') !== false) {
            error_log('Trial Balance Query contains unbound placeholder: ' . $query);
            // Don't execute query with unbound placeholders
            $stmt = false;
        } else {
            $stmt = $conn->query($query);
        }
        
        if ($stmt) {
            $rowCount = 0;
            while ($row = $stmt->fetch_assoc()) {
                $rowCount++;
                // Calculate balance if not already calculated
                if (!isset($row['balance']) || $row['balance'] == 0) {
                    $row['balance'] = floatval($row['debit']) - floatval($row['credit']);
                }
                $report['accounts'][] = $row;
            }
            if ($rowCount == 0) {
                error_log('Trial Balance: Query executed successfully but returned 0 rows.');
                error_log('Trial Balance Query: ' . substr($query, 0, 300));
                error_log('Trial Balance: tableExists check returned: ' . ($tableExistsCheck ? 'true' : 'false'));
                
                // Try a simple count query to verify table has data
                $countQuery = "SELECT COUNT(*) as total FROM financial_accounts";
                $countResult = $conn->query($countQuery);
                if ($countResult) {
                    $countRow = $countResult->fetch_assoc();
                    error_log('Trial Balance: Total accounts in table: ' . $countRow['total']);
                    $countResult->free();
                } else {
                    error_log('Trial Balance: Count query failed: ' . $conn->error);
                }
            } else {
                error_log('Trial Balance: Successfully retrieved ' . $rowCount . ' accounts');
            }
        } else {
            // Log query error for debugging
            $errorMsg = $conn->error ? $conn->error : 'Unknown error';
            error_log('Trial Balance Query Error: ' . $errorMsg);
            error_log('Query: ' . substr($query, 0, 500)); // Log first 500 chars
        }
    }
    
    // Calculate totals
    $totalDebit = !empty($report['accounts']) ? array_sum(array_column($report['accounts'], 'debit')) : 0;
    $totalCredit = !empty($report['accounts']) ? array_sum(array_column($report['accounts'], 'credit')) : 0;
    
    $report['totals'] = [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'difference' => abs($totalDebit - $totalCredit)
    ];
    
    return $report;
}

function generateIncomeStatement($conn, $startDate = null, $endDate = null, $costCenterId = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    $report = [
        'title' => 'Income Statement',
        'period' => $startDate . ' to ' . $endDate,
        'revenue' => [],
        'expenses' => [],
        'cost_center_id' => $costCenterId
    ];
    
    // Check if general_ledger table exists
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
    $glTableCheck->free();
    
    if (!$hasGLTable) {
        $report['error'] = 'general_ledger table does not exist';
        return $report;
    }
    
    // Check if journal_entry_lines has cost_center_id column
    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
    $hasCostCenter = $costCenterCheck && $costCenterCheck->num_rows > 0;
    $costCenterCheck->free();
    
    // Build JOIN clause for cost center filtering
    $costCenterJoin = '';
    $costCenterWhere = '';
    if ($hasCostCenter && $costCenterId) {
        // Use INNER JOIN when filtering by cost center to ensure we only get matching rows
        $costCenterJoin = "INNER JOIN journal_entry_lines jel ON gl.journal_entry_id = jel.journal_entry_id AND gl.account_id = jel.account_id";
        $costCenterWhere = "AND jel.cost_center_id = " . intval($costCenterId);
    }
    
    // Build date filter
    $dateWhere = "gl.posting_date >= ? AND gl.posting_date <= ?";
    
    // Initialize totals
    $totalRevenue = 0;
    $totalExpenses = 0;
    
    // Get Revenue accounts (account_type = 'REVENUE', credit amounts)
    $revenueQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as revenue_amount
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'REVENUE'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING revenue_amount > 0
        ORDER BY fa.account_code
    ";
    
    $revenueStmt = $conn->prepare($revenueQuery);
    if ($revenueStmt) {
        $revenueStmt->bind_param('ss', $startDate, $endDate);
        if ($revenueStmt->execute()) {
            $revenueResult = $revenueStmt->get_result();
            if ($revenueResult) {
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
            } else {
                error_log('Income Statement: get_result() failed for revenue query: ' . $revenueStmt->error);
            }
        } else {
            error_log('Income Statement: execute() failed for revenue query: ' . $revenueStmt->error);
        }
        $revenueStmt->close();
    } else {
        error_log('Income Statement: prepare() failed for revenue query: ' . $conn->error);
    }
    
    // Get Expense accounts (account_type = 'EXPENSE', debit amounts)
    $expenseQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.debit - gl.credit), 0) as expense_amount
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'EXPENSE'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING expense_amount > 0
        ORDER BY fa.account_code
    ";
    
    $expenseStmt = $conn->prepare($expenseQuery);
    if ($expenseStmt) {
        $expenseStmt->bind_param('ss', $startDate, $endDate);
        if ($expenseStmt->execute()) {
            $expenseResult = $expenseStmt->get_result();
            if ($expenseResult) {
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
            } else {
                error_log('Income Statement: get_result() failed for expense query: ' . $expenseStmt->error);
            }
        } else {
            error_log('Income Statement: execute() failed for expense query: ' . $expenseStmt->error);
        }
        $expenseStmt->close();
    } else {
        error_log('Income Statement: prepare() failed for expense query: ' . $conn->error);
    }
    
    $report['totals'] = [
        'total_revenue' => $totalRevenue,
        'total_expenses' => $totalExpenses,
        'net_income' => $totalRevenue - $totalExpenses
    ];
    
    return $report;
}

function generateBalanceSheet($conn, $asOfDate = null, $costCenterId = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Balance Sheet',
        'as_of' => $asOfDate,
        'assets' => [],
        'liabilities' => [],
        'equity' => [],
        'cost_center_id' => $costCenterId
    ];
    
    // Check if general_ledger table exists
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
    $glTableCheck->free();
    
    if (!$hasGLTable) {
        $report['error'] = 'general_ledger table does not exist';
        return $report;
    }
    
    // Check if journal_entry_lines has cost_center_id column
    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
    $hasCostCenter = $costCenterCheck && $costCenterCheck->num_rows > 0;
    $costCenterCheck->free();
    
    // Build JOIN clause for cost center filtering
    $costCenterJoin = '';
    $costCenterWhere = '';
    if ($hasCostCenter && $costCenterId) {
        // Use INNER JOIN when filtering by cost center to ensure we only get matching rows
        $costCenterJoin = "INNER JOIN journal_entry_lines jel ON gl.journal_entry_id = jel.journal_entry_id AND gl.account_id = jel.account_id";
        $costCenterWhere = "AND jel.cost_center_id = " . intval($costCenterId);
    }
    
    // Build date filter (cumulative up to asOfDate)
    $dateWhere = "gl.posting_date <= ?";
    
    // Get Assets (account_type = 'ASSET', balance = debit - credit)
    $assetsQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.debit - gl.credit), 0) as balance
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'ASSET'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $assetsStmt = $conn->prepare($assetsQuery);
    if ($assetsStmt) {
        $assetsStmt->bind_param('s', $asOfDate);
        if ($assetsStmt->execute()) {
            $assetsResult = $assetsStmt->get_result();
            if ($assetsResult) {
                while ($row = $assetsResult->fetch_assoc()) {
                    $report['assets'][] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => floatval($row['balance'])
                    ];
                }
                $assetsResult->free();
            } else {
                error_log('Balance Sheet: get_result() failed for assets query: ' . $assetsStmt->error);
            }
        } else {
            error_log('Balance Sheet: execute() failed for assets query: ' . $assetsStmt->error);
        }
        $assetsStmt->close();
    } else {
        error_log('Balance Sheet: prepare() failed for assets query: ' . $conn->error);
    }
    
    // Get Liabilities (account_type = 'LIABILITY', balance = credit - debit)
    $liabilitiesQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as balance
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'LIABILITY'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $liabilitiesStmt = $conn->prepare($liabilitiesQuery);
    if ($liabilitiesStmt) {
        $liabilitiesStmt->bind_param('s', $asOfDate);
        if ($liabilitiesStmt->execute()) {
            $liabilitiesResult = $liabilitiesStmt->get_result();
            if ($liabilitiesResult) {
                while ($row = $liabilitiesResult->fetch_assoc()) {
                    $report['liabilities'][] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => floatval($row['balance'])
                    ];
                }
                $liabilitiesResult->free();
            } else {
                error_log('Balance Sheet: get_result() failed for liabilities query: ' . $liabilitiesStmt->error);
            }
        } else {
            error_log('Balance Sheet: execute() failed for liabilities query: ' . $liabilitiesStmt->error);
        }
        $liabilitiesStmt->close();
    } else {
        error_log('Balance Sheet: prepare() failed for liabilities query: ' . $conn->error);
    }
    
    // Get Equity (account_type = 'EQUITY', balance = credit - debit)
    $equityQuery = "
        SELECT 
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as balance
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'EQUITY'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY fa.id, fa.account_code, fa.account_name
        HAVING balance != 0
        ORDER BY fa.account_code
    ";
    
    $equityStmt = $conn->prepare($equityQuery);
    if ($equityStmt) {
        $equityStmt->bind_param('s', $asOfDate);
        if ($equityStmt->execute()) {
            $equityResult = $equityStmt->get_result();
            if ($equityResult) {
                while ($row = $equityResult->fetch_assoc()) {
                    $report['equity'][] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'balance' => floatval($row['balance'])
                    ];
                }
                $equityResult->free();
            } else {
                error_log('Balance Sheet: get_result() failed for equity query: ' . $equityStmt->error);
            }
        } else {
            error_log('Balance Sheet: execute() failed for equity query: ' . $equityStmt->error);
        }
        $equityStmt->close();
    } else {
        error_log('Balance Sheet: prepare() failed for equity query: ' . $conn->error);
    }
    
    $totalAssets = !empty($report['assets']) ? array_sum(array_column($report['assets'], 'balance')) : 0;
    $totalLiabilities = !empty($report['liabilities']) ? array_sum(array_column($report['liabilities'], 'balance')) : 0;
    $totalEquity = !empty($report['equity']) ? array_sum(array_column($report['equity'], 'balance')) : 0;
    
    $report['totals'] = [
        'total_assets' => $totalAssets,
        'total_liabilities' => $totalLiabilities,
        'total_equity' => $totalEquity,
        'total_liabilities_equity' => $totalLiabilities + $totalEquity
    ];
    
    return $report;
}

function generateCashFlow($conn, $startDate = null, $endDate = null, $costCenterId = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    $report = [
        'title' => 'Cash Flow Statement',
        'period' => $startDate . ' to ' . $endDate,
        'operating' => [],
        'investing' => [],
        'financing' => [],
        'cost_center_id' => $costCenterId
    ];
    
    // Check if general_ledger table exists
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
    $glTableCheck->free();
    
    if (!$hasGLTable) {
        $report['error'] = 'general_ledger table does not exist';
        return $report;
    }
    
    // Check if journal_entry_lines has cost_center_id column
    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
    $hasCostCenter = $costCenterCheck && $costCenterCheck->num_rows > 0;
    $costCenterCheck->free();
    
    // Build JOIN clause for cost center filtering
    $costCenterJoin = '';
    $costCenterWhere = '';
    if ($hasCostCenter && $costCenterId) {
        // Use INNER JOIN when filtering by cost center to ensure we only get matching rows
        $costCenterJoin = "INNER JOIN journal_entry_lines jel ON gl.journal_entry_id = jel.journal_entry_id AND gl.account_id = jel.account_id";
        $costCenterWhere = "AND jel.cost_center_id = " . intval($costCenterId);
    }
    
    // Build date filter
    $dateWhere = "gl.posting_date >= ? AND gl.posting_date <= ?";
    
    // Operating activities: Cash accounts (typically account_code like '1100' for Cash, '1200' for Bank)
    // Cash in = credit to cash/bank accounts, Cash out = debit to cash/bank accounts
    $operatingQuery = "
        SELECT 
            DATE_FORMAT(gl.posting_date, '%Y-%m') as month,
            COALESCE(SUM(CASE WHEN fa.account_type = 'ASSET' AND (fa.account_code LIKE '1100%' OR fa.account_code LIKE '1200%') THEN gl.credit ELSE 0 END), 0) as cash_in,
            COALESCE(SUM(CASE WHEN fa.account_type = 'ASSET' AND (fa.account_code LIKE '1100%' OR fa.account_code LIKE '1200%') THEN gl.debit ELSE 0 END), 0) as cash_out
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_type = 'ASSET'
        AND fa.is_active = 1
        AND (fa.account_code LIKE '1100%' OR fa.account_code LIKE '1200%')
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY DATE_FORMAT(gl.posting_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ";
    
    $operatingStmt = $conn->prepare($operatingQuery);
    if ($operatingStmt) {
        $operatingStmt->bind_param('ss', $startDate, $endDate);
        if ($operatingStmt->execute()) {
            $operatingResult = $operatingStmt->get_result();
            if ($operatingResult) {
                while ($row = $operatingResult->fetch_assoc()) {
                    $report['operating'][] = [
                        'month' => $row['month'],
                        'cash_in' => floatval($row['cash_in']),
                        'cash_out' => floatval($row['cash_out'])
                    ];
                }
                $operatingResult->free();
            } else {
                error_log('Cash Flow: get_result() failed for operating query: ' . $operatingStmt->error);
            }
        } else {
            error_log('Cash Flow: execute() failed for operating query: ' . $operatingStmt->error);
        }
        $operatingStmt->close();
    } else {
        error_log('Cash Flow: prepare() failed for operating query: ' . $conn->error);
    }
    
    $totalOperatingIn = !empty($report['operating']) ? array_sum(array_column($report['operating'], 'cash_in')) : 0;
    $totalOperatingOut = !empty($report['operating']) ? array_sum(array_column($report['operating'], 'cash_out')) : 0;
    
    $report['totals'] = [
        'operating_cash_flow' => $totalOperatingIn - $totalOperatingOut,
        'investing_cash_flow' => 0,
        'financing_cash_flow' => 0,
        'net_cash_flow' => $totalOperatingIn - $totalOperatingOut
    ];
    
    return $report;
}

function generateVATReport($conn, $startDate = null, $endDate = null, $costCenterId = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    $report = [
        'title' => 'VAT Report (Saudi Arabia)',
        'period' => $startDate . ' to ' . $endDate,
        'vat_receivable' => [],
        'vat_payable' => [],
        'summary' => [],
        'cost_center_id' => $costCenterId
    ];
    
    // Check if general_ledger table exists
    $glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
    $hasGLTable = $glTableCheck && $glTableCheck->num_rows > 0;
    $glTableCheck->free();
    
    if (!$hasGLTable) {
        $report['error'] = 'general_ledger table does not exist';
        return $report;
    }
    
    // Check if journal_entry_lines has cost_center_id column
    $costCenterCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
    $hasCostCenter = $costCenterCheck && $costCenterCheck->num_rows > 0;
    $costCenterCheck->free();
    
    // Build JOIN clause for cost center filtering
    $costCenterJoin = '';
    $costCenterWhere = '';
    if ($hasCostCenter && $costCenterId) {
        // Use INNER JOIN when filtering by cost center to ensure we only get matching rows
        $costCenterJoin = "INNER JOIN journal_entry_lines jel ON gl.journal_entry_id = jel.journal_entry_id AND gl.account_id = jel.account_id";
        $costCenterWhere = "AND jel.cost_center_id = " . intval($costCenterId);
    }
    
    // Build date filter
    $dateWhere = "gl.posting_date >= ? AND gl.posting_date <= ?";
    
    // Initialize totals
    $totalVATReceivable = 0;
    $totalVATPayable = 0;
    
    // Get VAT Receivable (typically account_code = '1300', debit amounts)
    $vatReceivableQuery = "
        SELECT 
            DATE_FORMAT(gl.posting_date, '%Y-%m') as month,
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.debit - gl.credit), 0) as vat_amount
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_code LIKE '1300%'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY DATE_FORMAT(gl.posting_date, '%Y-%m'), fa.id, fa.account_code, fa.account_name
        HAVING vat_amount != 0
        ORDER BY month DESC, fa.account_code
    ";
    
    $vatReceivableStmt = $conn->prepare($vatReceivableQuery);
    if ($vatReceivableStmt) {
        $vatReceivableStmt->bind_param('ss', $startDate, $endDate);
        if ($vatReceivableStmt->execute()) {
            $vatReceivableResult = $vatReceivableStmt->get_result();
            if ($vatReceivableResult) {
                while ($row = $vatReceivableResult->fetch_assoc()) {
                    $amount = floatval($row['vat_amount']);
                    $report['vat_receivable'][] = [
                        'month' => $row['month'],
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'vat_amount' => $amount
                    ];
                    $totalVATReceivable += $amount;
                }
                $vatReceivableResult->free();
            } else {
                error_log('VAT Report: get_result() failed for VAT receivable query: ' . $vatReceivableStmt->error);
            }
        } else {
            error_log('VAT Report: execute() failed for VAT receivable query: ' . $vatReceivableStmt->error);
        }
        $vatReceivableStmt->close();
    } else {
        error_log('VAT Report: prepare() failed for VAT receivable query: ' . $conn->error);
    }
    
    // Get VAT Payable (typically account_code = '2200', credit amounts)
    $vatPayableQuery = "
        SELECT 
            DATE_FORMAT(gl.posting_date, '%Y-%m') as month,
            fa.account_code,
            fa.account_name,
            COALESCE(SUM(gl.credit - gl.debit), 0) as vat_amount
        FROM general_ledger gl
        INNER JOIN financial_accounts fa ON gl.account_id = fa.id
        {$costCenterJoin}
        WHERE fa.account_code LIKE '2200%'
        AND fa.is_active = 1
        AND {$dateWhere}
        {$costCenterWhere}
        GROUP BY DATE_FORMAT(gl.posting_date, '%Y-%m'), fa.id, fa.account_code, fa.account_name
        HAVING vat_amount != 0
        ORDER BY month DESC, fa.account_code
    ";
    
    $vatPayableStmt = $conn->prepare($vatPayableQuery);
    if ($vatPayableStmt) {
        $vatPayableStmt->bind_param('ss', $startDate, $endDate);
        if ($vatPayableStmt->execute()) {
            $vatPayableResult = $vatPayableStmt->get_result();
            if ($vatPayableResult) {
                while ($row = $vatPayableResult->fetch_assoc()) {
                    $amount = floatval($row['vat_amount']);
                    $report['vat_payable'][] = [
                        'month' => $row['month'],
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'vat_amount' => $amount
                    ];
                    $totalVATPayable += $amount;
                }
                $vatPayableResult->free();
            } else {
                error_log('VAT Report: get_result() failed for VAT payable query: ' . $vatPayableStmt->error);
            }
        } else {
            error_log('VAT Report: execute() failed for VAT payable query: ' . $vatPayableStmt->error);
        }
        $vatPayableStmt->close();
    } else {
        error_log('VAT Report: prepare() failed for VAT payable query: ' . $conn->error);
    }
    
    // Summary for Saudi VAT compliance
    $report['summary'] = [
        'total_vat_receivable' => $totalVATReceivable,
        'total_vat_payable' => $totalVATPayable,
        'vat_due_from_government' => max(0, $totalVATReceivable - $totalVATPayable),
        'vat_due_to_government' => max(0, $totalVATPayable - $totalVATReceivable),
        'net_vat' => $totalVATReceivable - $totalVATPayable
    ];
    
    return $report;
}

function generateAgedReceivables($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    // Escape date for SQL queries to prevent SQL injection
    $escapedAsOfDate = escapeDate($conn, $asOfDate);
    $report = [
        'title' => 'Aged Receivables',
        'as_of' => $asOfDate,
        'receivables' => []
    ];
    
    // Check if accounts_receivable table exists
    if (tableExists($conn, 'accounts_receivable')) {
        // Check if accounting_customers table exists for JOIN
        $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
        $hasCustomersTable = $customersTableCheck && $customersTableCheck->num_rows > 0;
        if ($customersTableCheck) {
            $customersTableCheck->free();
        }
        
        if ($hasCustomersTable) {
            $stmt = $conn->query("
                SELECT 
                    ar.invoice_number,
                    COALESCE(
                        ac.customer_name,
                        CASE 
                            WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                            WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                            ELSE 'N/A'
                        END,
                        'N/A'
                    ) as customer_name,
                    ar.invoice_date,
                    ar.due_date,
                    ar.total_amount,
                    ar.paid_amount,
                    (ar.total_amount - COALESCE(ar.paid_amount, 0)) as balance,
                    DATEDIFF('{$escapedAsOfDate}', ar.due_date) as days_overdue
                FROM accounts_receivable ar
                LEFT JOIN accounting_customers ac ON ar.customer_id = ac.id
                WHERE (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0
                    AND ar.due_date <= '{$escapedAsOfDate}'
                ORDER BY ar.due_date ASC
            ");
        } else {
            // Check if entity_type and entity_id columns exist
            $hasEntityType = columnExists($conn, 'accounts_receivable', 'entity_type');
            $hasEntityId = columnExists($conn, 'accounts_receivable', 'entity_id');
            
            if ($hasEntityType && $hasEntityId) {
                $stmt = $conn->query("
                    SELECT 
                        ar.invoice_number,
                        COALESCE(
                            CASE 
                                WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                                ELSE 'N/A'
                            END,
                            'N/A'
                        ) as customer_name,
                        ar.invoice_date,
                        ar.due_date,
                        ar.total_amount,
                        ar.paid_amount,
                        (ar.total_amount - COALESCE(ar.paid_amount, 0)) as balance,
                        DATEDIFF('{$escapedAsOfDate}', ar.due_date) as days_overdue
                    FROM accounts_receivable ar
                    WHERE (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0
                        AND ar.due_date <= '{$escapedAsOfDate}'
                    ORDER BY ar.due_date ASC
                ");
            } else {
                $stmt = $conn->query("
                    SELECT 
                        invoice_number,
                        'N/A' as customer_name,
                        invoice_date,
                        due_date,
                        total_amount,
                        paid_amount,
                        (total_amount - COALESCE(paid_amount, 0)) as balance,
                        DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                    FROM accounts_receivable
                    WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                        AND due_date <= '{$escapedAsOfDate}'
                    ORDER BY due_date ASC
                ");
            }
        }
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['receivables'][] = $row;
            }
            $stmt->free();
        }
    } else {
        // Fallback to old accounting_invoices table
        if (tableExists($conn, 'accounting_invoices')) {
            // Check if paid_amount column exists
            $paidCheck = $conn->query("SHOW COLUMNS FROM accounting_invoices LIKE 'paid_amount'");
            $hasPaidAmount = $paidCheck && $paidCheck->num_rows > 0;
            if ($paidCheck) {
                $paidCheck->free();
            }
            
            // Check if accounting_customers table exists
            $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
            $hasCustomersTable = $customersTableCheck && $customersTableCheck->num_rows > 0;
            if ($customersTableCheck) {
                $customersTableCheck->free();
            }
            
            if ($hasPaidAmount) {
                if ($hasCustomersTable) {
                    $stmt = $conn->query("
                        SELECT 
                            invoice_number,
                            COALESCE((SELECT customer_name FROM accounting_customers WHERE id = accounting_invoices.customer_id), 'N/A') as customer_name,
                            invoice_date,
                            due_date,
                            total_amount,
                            COALESCE(paid_amount, 0) as paid_amount,
                            (total_amount - COALESCE(paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_invoices
                        WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                } else {
                    $stmt = $conn->query("
                        SELECT 
                            invoice_number,
                            'N/A' as customer_name,
                            invoice_date,
                            due_date,
                            total_amount,
                            COALESCE(paid_amount, 0) as paid_amount,
                            (total_amount - COALESCE(paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_invoices
                        WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                }
            } else {
                if ($hasCustomersTable) {
                    $stmt = $conn->query("
                        SELECT 
                            invoice_number,
                            COALESCE((SELECT customer_name FROM accounting_customers WHERE id = accounting_invoices.customer_id), 'N/A') as customer_name,
                            invoice_date,
                            due_date,
                            total_amount,
                            0 as paid_amount,
                            total_amount as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_invoices
                        WHERE status != 'Paid'
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                } else {
                    $stmt = $conn->query("
                        SELECT 
                            invoice_number,
                            'N/A' as customer_name,
                            invoice_date,
                            due_date,
                            total_amount,
                            0 as paid_amount,
                            total_amount as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_invoices
                        WHERE status != 'Paid'
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                }
            }
            
            if ($stmt) {
                while ($row = $stmt->fetch_assoc()) {
                    $report['receivables'][] = $row;
                }
                $stmt->free();
            }
        }
    }
    
    $totalOutstanding = !empty($report['receivables']) ? array_sum(array_column($report['receivables'], 'balance')) : 0;
    $report['total_outstanding'] = $totalOutstanding;
    
    return $report;
}

function generateAgedPayables($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    // Escape date for SQL queries to prevent SQL injection
    $escapedAsOfDate = escapeDate($conn, $asOfDate);
    $report = [
        'title' => 'Aged Payables',
        'as_of' => $asOfDate,
        'payables' => []
    ];
    
    // Check if accounts_payable table exists
    if (tableExists($conn, 'accounts_payable')) {
        // Check if accounting_vendors table exists for JOIN
        $vendorsTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
        $hasVendorsTable = $vendorsTableCheck && $vendorsTableCheck->num_rows > 0;
        if ($vendorsTableCheck) {
            $vendorsTableCheck->free();
        }
        
        if ($hasVendorsTable) {
            $stmt = $conn->query("
                SELECT 
                    ap.bill_number,
                    COALESCE(av.vendor_name, 'N/A') as vendor_name,
                    ap.bill_date,
                    ap.due_date,
                    ap.total_amount,
                    ap.paid_amount,
                    (ap.total_amount - COALESCE(ap.paid_amount, 0)) as balance,
                    DATEDIFF('{$escapedAsOfDate}', ap.due_date) as days_overdue
                FROM accounts_payable ap
                LEFT JOIN accounting_vendors av ON ap.vendor_id = av.id
                WHERE (ap.total_amount - COALESCE(ap.paid_amount, 0)) > 0
                    AND ap.due_date <= '{$escapedAsOfDate}'
                ORDER BY ap.due_date ASC
            ");
        } else {
            $stmt = $conn->query("
                SELECT 
                    bill_number,
                    'N/A' as vendor_name,
                    bill_date,
                    due_date,
                    total_amount,
                    paid_amount,
                    (total_amount - COALESCE(paid_amount, 0)) as balance,
                    DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                FROM accounts_payable
                WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                    AND due_date <= '{$escapedAsOfDate}'
                ORDER BY due_date ASC
            ");
        }
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['payables'][] = $row;
            }
            $stmt->free();
        }
    } else {
        // Fallback to old accounting_bills table
        if (tableExists($conn, 'accounting_bills')) {
            // Check if paid_amount column exists
            $paidCheck = $conn->query("SHOW COLUMNS FROM accounting_bills LIKE 'paid_amount'");
            $hasPaidAmount = $paidCheck && $paidCheck->num_rows > 0;
            if ($paidCheck) {
                $paidCheck->free();
            }
            
            // Check if accounting_vendors table exists
            $vendorsTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
            $hasVendorsTable = $vendorsTableCheck && $vendorsTableCheck->num_rows > 0;
            if ($vendorsTableCheck) {
                $vendorsTableCheck->free();
            }
            
            if ($hasPaidAmount) {
                if ($hasVendorsTable) {
                    $stmt = $conn->query("
                        SELECT 
                            bill_number,
                            COALESCE((SELECT vendor_name FROM accounting_vendors WHERE id = accounting_bills.vendor_id), 'N/A') as vendor_name,
                            bill_date,
                            due_date,
                            total_amount,
                            COALESCE(paid_amount, 0) as paid_amount,
                            (total_amount - COALESCE(paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_bills
                        WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                } else {
                    $stmt = $conn->query("
                        SELECT 
                            bill_number,
                            'N/A' as vendor_name,
                            bill_date,
                            due_date,
                            total_amount,
                            COALESCE(paid_amount, 0) as paid_amount,
                            (total_amount - COALESCE(paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounting_bills
                        WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                            AND due_date <= '{$escapedAsOfDate}'
                        ORDER BY due_date ASC
                    ");
                }
            } else {
                if ($hasVendorsTable) {
                    $stmt = $conn->query("
                        SELECT 
                            bill_number,
                            COALESCE((SELECT vendor_name FROM accounting_vendors WHERE id = accounting_bills.vendor_id), 'N/A') as vendor_name,
                            bill_date,
                            due_date,
                            total_amount,
                            0 as paid_amount,
                            total_amount as balance,
                            DATEDIFF('{$asOfDate}', due_date) as days_overdue
                        FROM accounting_bills
                        WHERE status != 'Paid'
                            AND due_date <= '{$asOfDate}'
                        ORDER BY due_date ASC
                    ");
                } else {
                    $stmt = $conn->query("
                        SELECT 
                            bill_number,
                            'N/A' as vendor_name,
                            bill_date,
                            due_date,
                            total_amount,
                            0 as paid_amount,
                            total_amount as balance,
                            DATEDIFF('{$asOfDate}', due_date) as days_overdue
                        FROM accounting_bills
                        WHERE status != 'Paid'
                            AND due_date <= '{$asOfDate}'
                        ORDER BY due_date ASC
                    ");
                }
            }
            
            if ($stmt) {
                while ($row = $stmt->fetch_assoc()) {
                    $report['payables'][] = $row;
                }
                $stmt->free();
            }
        }
    }
    
    $totalOutstanding = !empty($report['payables']) ? array_sum(array_column($report['payables'], 'balance')) : 0;
    $report['total_outstanding'] = $totalOutstanding;
    
    return $report;
}

function generateCashBook($conn, $startDate = null, $endDate = null) {
    // Default to last 30 days if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d');
    }
    
    $report = [
        'title' => 'Cash Book',
        'period' => $startDate . ' to ' . $endDate,
        'transactions' => []
    ];

    if (!tableExists($conn, 'financial_transactions')) {
        return $report;
    }

    $hasDebit = columnExists($conn, 'financial_transactions', 'debit_amount');
    $hasCredit = columnExists($conn, 'financial_transactions', 'credit_amount');
    $hasTotal = columnExists($conn, 'financial_transactions', 'total_amount');
    $hasType = columnExists($conn, 'financial_transactions', 'transaction_type');
    $hasReference = columnExists($conn, 'financial_transactions', 'reference_number');
    $hasStatus = columnExists($conn, 'financial_transactions', 'status');
    $hasCurrency = columnExists($conn, 'financial_transactions', 'currency');
    $hasPaymentMethod = columnExists($conn, 'financial_transactions', 'payment_method');
    $hasCreatedAt = columnExists($conn, 'financial_transactions', 'created_at');
    $hasDescription = columnExists($conn, 'financial_transactions', 'description');

    $selectParts = [
        "ft.id",
        $hasType ? "ft.transaction_type" : "'Transaction' AS transaction_type"
    ];

    $dateField = columnExists($conn, 'financial_transactions', 'transaction_date')
        ? 'ft.transaction_date'
        : ($hasCreatedAt ? 'ft.created_at' : 'NULL');
    $selectParts[] = "{$dateField} AS transaction_date";

    $selectParts[] = ($hasDescription ? "ft.description" : "'N/A'") . " AS description";

    if ($hasTotal) {
        $selectParts[] = "ft.total_amount";
    } else {
        $selectParts[] = "0 AS total_amount";
    }

    if ($hasDebit) {
        $selectParts[] = "COALESCE(ft.debit_amount, 0) AS debit_amount";
    } elseif ($hasTotal && $hasType) {
        $selectParts[] = "CASE WHEN ft.transaction_type = 'Expense' THEN ft.total_amount ELSE 0 END AS debit_amount";
    } else {
        $selectParts[] = "0 AS debit_amount";
    }

    if ($hasCredit) {
        $selectParts[] = "COALESCE(ft.credit_amount, 0) AS credit_amount";
    } elseif ($hasTotal && $hasType) {
        $selectParts[] = "CASE WHEN ft.transaction_type = 'Income' THEN ft.total_amount ELSE 0 END AS credit_amount";
    } else {
        $selectParts[] = "0 AS credit_amount";
    }

    $selectParts[] = ($hasReference ? "ft.reference_number" : "NULL") . " AS reference_number";
    $selectParts[] = ($hasStatus ? "ft.status" : "'Posted'") . " AS status";

    if ($hasCurrency) {
        $selectParts[] = "ft.currency";
    }

    $joins = '';
    $where = '';

    $hasTransactionLines = tableExists($conn, 'transaction_lines');
    $hasAccounts = tableExists($conn, 'financial_accounts');

    if ($hasTransactionLines && $hasAccounts) {
        $joins = "
            LEFT JOIN transaction_lines tl ON tl.transaction_id = ft.id
            LEFT JOIN financial_accounts fa ON fa.id = tl.account_id
        ";
        $where = "WHERE fa.account_code = '1100' OR fa.account_name LIKE '%Cash%'";
    } elseif ($hasPaymentMethod) {
        $where = "WHERE LOWER(ft.payment_method) LIKE '%cash%'";
    }

    // Escape dates to prevent SQL injection (dates are validated but still need escaping)
    $escapedStartDate = $conn->real_escape_string($startDate);
    $escapedEndDate = $conn->real_escape_string($endDate);
    $dateFilter = "AND ft.transaction_date >= '{$escapedStartDate}' AND ft.transaction_date <= '{$escapedEndDate}'";
    if ($where) {
        $where .= " " . $dateFilter;
    } else {
        $where = "WHERE 1=1 " . $dateFilter;
    }

    $query = "
        SELECT " . implode(",\n            ", $selectParts) . "
        FROM financial_transactions ft
        {$joins}
        {$where}
        ORDER BY transaction_date DESC, ft.id DESC
        LIMIT 500
    ";

    $stmt = $conn->query($query);

    $runningBalance = 0;
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $debit = floatval($row['debit_amount']);
            $credit = floatval($row['credit_amount']);
            $runningBalance += ($credit - $debit);
            $row['balance'] = $runningBalance;
            $report['transactions'][] = $row;
        }
        $stmt->free(); // Free result set
    }
    
    $totalDebit = !empty($report['transactions']) ? array_sum(array_column($report['transactions'], 'debit_amount')) : 0;
    $totalCredit = !empty($report['transactions']) ? array_sum(array_column($report['transactions'], 'credit_amount')) : 0;
    
    $report['totals'] = [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'closing_balance' => $runningBalance
    ];
    
    return $report;
}

function generateBankBook($conn, $startDate = null, $endDate = null) {
    // Default to last 30 days if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d');
    }
    
    $report = [
        'title' => 'Bank Book',
        'period' => $startDate . ' to ' . $endDate,
        'transactions' => []
    ];

    if (!tableExists($conn, 'financial_transactions')) {
        return $report;
    }

    $hasBankTransactionsTable = tableExists($conn, 'accounting_bank_transactions');
    $hasPaymentMethod = columnExists($conn, 'financial_transactions', 'payment_method');
    $hasReference = columnExists($conn, 'financial_transactions', 'reference_number');
    $hasStatus = columnExists($conn, 'financial_transactions', 'status');
    $hasBankName = columnExists($conn, 'financial_transactions', 'bank_account_name');
    $hasTotal = columnExists($conn, 'financial_transactions', 'total_amount');
    $hasDebit = columnExists($conn, 'financial_transactions', 'debit_amount');
    $hasCredit = columnExists($conn, 'financial_transactions', 'credit_amount');
    $hasType = columnExists($conn, 'financial_transactions', 'transaction_type');

    if ($hasBankTransactionsTable) {
        $bankHasDebit = columnExists($conn, 'accounting_bank_transactions', 'debit_amount');
        $bankHasCredit = columnExists($conn, 'accounting_bank_transactions', 'credit_amount');
        $bankHasAmount = columnExists($conn, 'accounting_bank_transactions', 'amount');
        $bankHasReference = columnExists($conn, 'accounting_bank_transactions', 'reference_number');
        $bankHasBankName = columnExists($conn, 'accounting_bank_transactions', 'bank_account_name');
        $bankHasStatus = columnExists($conn, 'accounting_bank_transactions', 'status');
        $bankHasType = columnExists($conn, 'accounting_bank_transactions', 'transaction_type');

        $stmt = $conn->query("
            SELECT 
                id,
                transaction_date,
                description,
                " . ($bankHasType ? "transaction_type" : "'Transaction'") . " as transaction_type,
                " . ($bankHasAmount ? "amount" : "0") . " AS amount,
                COALESCE(" . ($bankHasDebit ? "debit_amount" : "0") . ", CASE WHEN " . ($bankHasType ? "transaction_type" : "'Transaction'") . " = 'Withdrawal' THEN " . ($bankHasAmount ? "amount" : "0") . " ELSE 0 END) as debit_amount,
                COALESCE(" . ($bankHasCredit ? "credit_amount" : "0") . ", CASE WHEN " . ($bankHasType ? "transaction_type" : "'Transaction'") . " = 'Deposit' THEN " . ($bankHasAmount ? "amount" : "0") . " ELSE 0 END) as credit_amount,
                " . ($bankHasReference ? "reference_number" : "NULL") . " as reference_number,
                " . ($bankHasBankName ? "bank_account_name" : "NULL") . " as bank_account_name,
                " . ($bankHasStatus ? "status" : "NULL") . " as status
            FROM accounting_bank_transactions
            WHERE transaction_date >= '{$escapedStartDate}' AND transaction_date <= '{$escapedEndDate}'
            ORDER BY transaction_date DESC, id DESC
            LIMIT 500
        ");
    } else {
        $hasTransactionLines = tableExists($conn, 'transaction_lines');
        $hasAccounts = tableExists($conn, 'financial_accounts');

        if ($hasTransactionLines && $hasAccounts) {
            $stmt = $conn->query("
                SELECT 
                    ft.id,
                    ft.transaction_date,
                    ft.description,
                    " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " as transaction_type,
                    " . ($hasTotal ? "ft.total_amount" : "0") . " as total_amount,
                    COALESCE(" . ($hasDebit ? "ft.debit_amount" : "0") . ", CASE WHEN " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " = 'Expense' THEN " . ($hasTotal ? "ft.total_amount" : "0") . " ELSE 0 END) as debit_amount,
                    COALESCE(" . ($hasCredit ? "ft.credit_amount" : "0") . ", CASE WHEN " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " = 'Income' THEN " . ($hasTotal ? "ft.total_amount" : "0") . " ELSE 0 END) as credit_amount,
                    " . ($hasReference ? "ft.reference_number" : "NULL") . " as reference_number,
                    " . ($hasBankName ? "ft.bank_account_name" : "NULL") . " as bank_account_name,
                    " . ($hasStatus ? "ft.status" : "NULL") . " as status
                FROM financial_transactions ft
                LEFT JOIN transaction_lines tl ON tl.transaction_id = ft.id
                LEFT JOIN financial_accounts fa ON fa.id = tl.account_id
                WHERE (fa.account_code LIKE '12%' OR fa.account_name LIKE '%Bank%')
                    AND ft.transaction_date >= '{$escapedStartDate}' AND ft.transaction_date <= '{$escapedEndDate}'
                ORDER BY ft.transaction_date DESC, ft.id DESC
                LIMIT 500
            ");
        }

        if (!$stmt) {
            $paymentWhere = $hasPaymentMethod ? "WHERE LOWER(ft.payment_method) LIKE '%bank%' OR LOWER(ft.payment_method) LIKE '%transfer%'" : '';
            $dateFilter = "AND ft.transaction_date >= '{$escapedStartDate}' AND ft.transaction_date <= '{$escapedEndDate}'";
            if ($paymentWhere) {
                $paymentWhere .= " " . $dateFilter;
            } else {
                $paymentWhere = "WHERE 1=1 " . $dateFilter;
            }
            $stmt = $conn->query("
                SELECT 
                    ft.id,
                    ft.transaction_date,
                    ft.description,
                    " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " as transaction_type,
                    " . ($hasTotal ? "ft.total_amount" : "0") . " as total_amount,
                    CASE WHEN " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " IN ('Withdrawal', 'Expense') THEN " . ($hasTotal ? "ft.total_amount" : "0") . " ELSE 0 END as debit_amount,
                    CASE WHEN " . ($hasType ? "ft.transaction_type" : "'Transaction'") . " IN ('Deposit', 'Income') THEN " . ($hasTotal ? "ft.total_amount" : "0") . " ELSE 0 END as credit_amount,
                    " . ($hasReference ? "ft.reference_number" : "NULL") . " as reference_number,
                    " . ($hasBankName ? "ft.bank_account_name" : "NULL") . " as bank_account_name,
                    " . ($hasStatus ? "ft.status" : "NULL") . " as status
                FROM financial_transactions ft
                {$paymentWhere}
                ORDER BY ft.transaction_date DESC, ft.id DESC
                LIMIT 500
            ");
        }
    }

    $runningBalance = 0;
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $debit = floatval($row['debit_amount']);
            $credit = floatval($row['credit_amount']);
            $runningBalance += ($credit - $debit);
            $row['balance'] = $runningBalance;
            $report['transactions'][] = $row;
        }
        $stmt->free(); // Free result set
    }

    $totalDebit = !empty($report['transactions']) ? array_sum(array_column($report['transactions'], 'debit_amount')) : 0;
    $totalCredit = !empty($report['transactions']) ? array_sum(array_column($report['transactions'], 'credit_amount')) : 0;
    
    $report['totals'] = [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'closing_balance' => $runningBalance
    ];
    
    return $report;
}

function generateGeneralLedgerReport($conn, $startDate = null, $endDate = null, $accountId = null, $searchTerm = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    // Validate dates (already validated, but ensure format)
    if (!strtotime($startDate) || !strtotime($endDate)) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
        $endDate = date('Y-m-t');
    }
    
    $report = [
        'title' => 'General Ledger',
        'period' => $startDate . ' to ' . $endDate,
        'accounts' => []
    ];
    
    // Get all accounts with their transactions using prepared statements
    // Try tableExists first, but also try direct query as fallback
    $tableExistsCheck = tableExists($conn, 'financial_accounts');
    
    if ($tableExistsCheck || true) { // Always try, even if check fails
        // Build query with prepared statement
        $query = "SELECT account_code, account_name, id FROM financial_accounts fa WHERE 1=1";
        $params = [];
        $types = '';
        
        if ($accountId) {
            $query .= " AND fa.id = ?";
            $params[] = intval($accountId);
            $types .= 'i';
        }
        
        if ($searchTerm) {
            $query .= " AND (fa.account_code LIKE ? OR fa.account_name LIKE ?)";
            $searchPattern = '%' . $searchTerm . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $types .= 'ss';
        }
        
        $query .= " ORDER BY account_code";
        
        if (!empty($params)) {
            // Use prepared statement when we have parameters
            try {
                $accountsStmt = $conn->prepare($query);
                if ($accountsStmt) {
                    $accountsStmt->bind_param($types, ...$params);
                    if ($accountsStmt->execute()) {
                        $accountsStmt = $accountsStmt->get_result();
                    } else {
                        error_log('General Ledger: Execute failed. Error: ' . $accountsStmt->error);
                        error_log('General Ledger: Query: ' . $query);
                        error_log('General Ledger: Params: ' . print_r($params, true));
                        $accountsStmt->close();
                        $accountsStmt = null;
                    }
                } else {
                    $accountsStmt = null;
                    error_log('General Ledger: Failed to prepare query with params. Error: ' . $conn->error);
                    error_log('General Ledger: Query: ' . $query);
                }
            } catch (Exception $e) {
                error_log('General Ledger: Exception in prepared statement: ' . $e->getMessage());
                $accountsStmt = null;
            }
        } else {
            // Use regular query when no parameters
            // Double-check query doesn't have unbound placeholders
            if (strpos($query, '?') !== false) {
                error_log('General Ledger: ERROR - Query contains ? but no params! Query: ' . $query);
                $accountsStmt = null;
            } else {
                $accountsStmt = $conn->query($query);
                if (!$accountsStmt) {
                    error_log('General Ledger: Query failed. Error: ' . $conn->error);
                    error_log('General Ledger: Query was: ' . $query);
                }
            }
        }
        
        if ($accountsStmt) {
            $accountCount = 0;
            while ($account = $accountsStmt->fetch_assoc()) {
                $accountCount++;
                $currentAccountId = intval($account['id']);
                
                // Debug: Log first account found
                if ($accountCount == 1) {
                    error_log('General Ledger: Found first account - ID: ' . $currentAccountId . ', Code: ' . $account['account_code']);
                }
                
                // Get transactions for this account using prepared statements
                $transactions = [];
                
                // Wrap transaction fetching in try-catch to prevent one failure from stopping the report
                try {
                // From transaction_lines (only if both tables exist)
                if (tableExists($conn, 'transaction_lines') && tableExists($conn, 'financial_transactions')) {
                    $tlQuery = "SELECT 
                        ft.transaction_date,
                        ft.description,
                        ft.reference_number,
                        tl.debit_amount,
                        tl.credit_amount,
                        ft.transaction_type,
                        ft.status
                    FROM transaction_lines tl
                    JOIN financial_transactions ft ON ft.id = tl.transaction_id
                    WHERE tl.account_id = ? AND ft.transaction_date >= ? AND ft.transaction_date <= ?";
                    
                    $tlParams = [$currentAccountId, $startDate, $endDate];
                    $tlTypes = 'iss';
                    
                    if ($searchTerm) {
                        $tlQuery .= " AND (ft.description LIKE ? OR ft.reference_number LIKE ?)";
                        $searchPattern = '%' . $searchTerm . '%';
                        $tlParams[] = $searchPattern;
                        $tlParams[] = $searchPattern;
                        $tlTypes .= 'ss';
                    }
                    
                    $tlQuery .= " ORDER BY ft.transaction_date DESC LIMIT 100";
                    
                    $tlStmt = $conn->prepare($tlQuery);
                    if ($tlStmt) {
                        $tlStmt->bind_param($tlTypes, ...$tlParams);
                        if ($tlStmt->execute()) {
                            $tlStmt = $tlStmt->get_result();
                        } else {
                            error_log('General Ledger: Transaction lines query execute failed: ' . $tlStmt->error);
                            $tlStmt->close();
                            $tlStmt = null;
                        }
                    } else {
                        error_log('General Ledger: Failed to prepare transaction lines query: ' . $conn->error);
                        $tlStmt = null;
                    }
                
                    if ($tlStmt) {
                        while ($row = $tlStmt->fetch_assoc()) {
                            $transactions[] = $row;
                        }
                    }
                }
                
                // From journal_entry_lines (only if both tables exist)
                if (tableExists($conn, 'journal_entry_lines') && tableExists($conn, 'journal_entries')) {
                    // Check if reference_number column exists
                    $hasReferenceNumber = columnExists($conn, 'journal_entries', 'reference_number');
                    $referenceField = $hasReferenceNumber ? 'je.reference_number' : "CONCAT('JE-', je.id) as reference_number";
                    $statusField = columnExists($conn, 'journal_entries', 'status') ? 'je.status' : "'Posted' as status";
                    
                    $jelQuery = "SELECT 
                        je.entry_date as transaction_date,
                        je.description,
                        {$referenceField},
                        jel.debit_amount,
                        jel.credit_amount,
                        'Journal Entry' as transaction_type,
                        {$statusField}
                    FROM journal_entry_lines jel
                    JOIN journal_entries je ON je.id = jel.journal_entry_id
                    WHERE jel.account_id = ? AND je.entry_date >= ? AND je.entry_date <= ?";
                    
                    $jelParams = [$currentAccountId, $startDate, $endDate];
                    $jelTypes = 'iss';
                    
                    if ($searchTerm) {
                        if ($hasReferenceNumber) {
                            $jelQuery .= " AND (je.description LIKE ? OR je.reference_number LIKE ?)";
                            $searchPattern = '%' . $searchTerm . '%';
                            $jelParams[] = $searchPattern;
                            $jelParams[] = $searchPattern;
                            $jelTypes .= 'ss';
                        } else {
                            $jelQuery .= " AND je.description LIKE ?";
                            $searchPattern = '%' . $searchTerm . '%';
                            $jelParams[] = $searchPattern;
                            $jelTypes .= 's';
                        }
                    }
                    
                    $jelQuery .= " ORDER BY je.entry_date DESC LIMIT 100";
                    
                    $jelStmt = $conn->prepare($jelQuery);
                    if ($jelStmt) {
                        $jelStmt->bind_param($jelTypes, ...$jelParams);
                        if ($jelStmt->execute()) {
                            $jelStmt = $jelStmt->get_result();
                        } else {
                            error_log('General Ledger: Journal entry lines query execute failed: ' . $jelStmt->error);
                            $jelStmt->close();
                            $jelStmt = null;
                        }
                    } else {
                        error_log('General Ledger: Failed to prepare journal entry lines query: ' . $conn->error);
                        $jelStmt = null;
                    }
                
                    if ($jelStmt) {
                        while ($row = $jelStmt->fetch_assoc()) {
                            $transactions[] = $row;
                        }
                    }
                }
                } catch (Exception $transError) {
                    // Log transaction fetch error but continue with account
                    error_log('General Ledger: Error fetching transactions for account ' . $currentAccountId . ': ' . $transError->getMessage());
                    $transactions = []; // Use empty transactions array
                }
                
                // Include all accounts, even if they have no transactions
                $account['transactions'] = $transactions;
                $account['total_debit'] = !empty($transactions) ? array_sum(array_column($transactions, 'debit_amount')) : 0;
                $account['total_credit'] = !empty($transactions) ? array_sum(array_column($transactions, 'credit_amount')) : 0;
                    $account['balance'] = $account['total_debit'] - $account['total_credit'];
                    $report['accounts'][] = $account;
                }
            
            // If query succeeded but returned 0 accounts, add debug info
            if ($accountCount === 0) {
                error_log('General Ledger: Query executed successfully but returned 0 accounts');
                error_log('General Ledger: Query was: ' . substr($query, 0, 200));
                error_log('General Ledger: tableExists check returned: ' . ($tableExistsCheck ? 'true' : 'false'));
                
                // Try a simple count query to verify table has data
                $countQuery = "SELECT COUNT(*) as total FROM financial_accounts";
                $countResult = $conn->query($countQuery);
                if ($countResult) {
                    $countRow = $countResult->fetch_assoc();
                    error_log('General Ledger: Total accounts in table: ' . $countRow['total']);
                    $countResult->free();
                }
                
                $report['debug'] = [
                    'message' => 'Query executed successfully but returned 0 accounts',
                    'table_exists' => $tableExistsCheck,
                    'account_id' => $accountId,
                    'search_term' => $searchTerm
                ];
            } else {
                error_log('General Ledger: Successfully retrieved ' . $accountCount . ' accounts');
            }
        } else {
            // Query failed - add error info
            $report['debug'] = [
                'message' => 'Failed to query financial_accounts table',
                'error' => $conn->error,
                'table_exists' => true
            ];
        }
    } else {
        // Table doesn't exist
        $report['debug'] = [
            'message' => 'financial_accounts table does not exist'
        ];
    }
    
    // Calculate totals for all accounts
    $totalDebit = 0;
    $totalCredit = 0;
    foreach ($report['accounts'] as $account) {
        $totalDebit += floatval($account['total_debit'] ?? 0);
        $totalCredit += floatval($account['total_credit'] ?? 0);
    }
    
    $report['totals'] = [
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'difference' => abs($totalDebit - $totalCredit)
    ];
    
    return $report;
}

function generateAccountStatement($conn, $accountId = null, $startDate = null, $endDate = null) {
    // If no account_id provided or accountId is 0 or empty, return empty report
    if ($accountId === null || $accountId === '' || intval($accountId) <= 0) {
        return [
            'title' => 'Account Statement',
            'period' => ($startDate && $endDate) ? ($startDate . ' to ' . $endDate) : date('Y-m-d'),
            'accounts' => [],
            'message' => 'Please select an account to generate the statement'
        ];
    }
    
    // Ensure accountId is an integer
    $accountId = intval($accountId);
    
    // Use General Ledger Report but filter by account
    return generateGeneralLedgerReport($conn, $startDate, $endDate, $accountId);
}

function generateAgedCreditReceivable($conn, $asOfDate = null) {
    // Similar to aged receivables but for credit notes/returns
    $asOfDate = $asOfDate ?: date('Y-m-d');
    // Escape date for SQL queries to prevent SQL injection
    $escapedAsOfDate = escapeDate($conn, $asOfDate);
    $report = [
        'title' => 'Ages of Credit Receivable',
        'as_of' => $asOfDate,
        'receivables' => []
    ];
    
    // Check if accounts_receivable table exists with credit_amount or credit notes
    if (tableExists($conn, 'accounts_receivable')) {
        // Check if there's a credit_amount or credit_note field
        $hasCreditAmount = columnExists($conn, 'accounts_receivable', 'credit_amount');
        $hasCreditNote = columnExists($conn, 'accounts_receivable', 'is_credit_note');
        
        if ($hasCreditNote || $hasCreditAmount) {
            $creditFilter = $hasCreditNote ? "AND is_credit_note = 1" : "AND credit_amount > 0";
            $customersTableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
            $hasCustomersTable = $customersTableCheck && $customersTableCheck->num_rows > 0;
            if ($customersTableCheck) {
                $customersTableCheck->free();
            }
            
            if ($hasCustomersTable) {
                $stmt = $conn->query("
                    SELECT 
                        ar.invoice_number,
                        COALESCE(
                            ac.customer_name,
                            CASE 
                                WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                                ELSE 'N/A'
                            END,
                            'N/A'
                        ) as customer_name,
                        ar.invoice_date,
                        ar.due_date,
                        " . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " as total_amount,
                        COALESCE(ar.paid_amount, 0) as paid_amount,
                        (" . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " - COALESCE(ar.paid_amount, 0)) as balance,
                        DATEDIFF('{$escapedAsOfDate}', ar.due_date) as days_overdue
                    FROM accounts_receivable ar
                    LEFT JOIN accounting_customers ac ON ar.customer_id = ac.id
                    WHERE (" . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " - COALESCE(ar.paid_amount, 0)) > 0
                        AND ar.due_date <= '{$escapedAsOfDate}'
                        {$creditFilter}
                    ORDER BY ar.due_date ASC
                ");
            } else {
                // Check if entity_type and entity_id columns exist
                $hasEntityType = columnExists($conn, 'accounts_receivable', 'entity_type');
                $hasEntityId = columnExists($conn, 'accounts_receivable', 'entity_id');
                
                if ($hasEntityType && $hasEntityId) {
                    $stmt = $conn->query("
                        SELECT 
                            ar.invoice_number,
                            COALESCE(
                                CASE 
                                    WHEN ar.entity_type = 'agent' THEN (SELECT agent_name FROM agents WHERE id = ar.entity_id LIMIT 1)
                                    WHEN ar.entity_type = 'subagent' THEN (SELECT subagent_name FROM subagents WHERE id = ar.entity_id LIMIT 1)
                                    WHEN ar.entity_type = 'worker' THEN (SELECT worker_name FROM workers WHERE id = ar.entity_id LIMIT 1)
WHEN ar.entity_type = 'hr' THEN (SELECT name FROM employees WHERE id = ar.entity_id LIMIT 1)
                                WHEN ar.entity_type = 'accounting' THEN (SELECT username FROM users WHERE user_id = ar.entity_id LIMIT 1)
                                ELSE 'N/A'
                            END,
                            'N/A'
                        ) as customer_name,
                        ar.invoice_date,
                        ar.due_date,
                            " . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " as total_amount,
                            COALESCE(ar.paid_amount, 0) as paid_amount,
                            (" . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " - COALESCE(ar.paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', ar.due_date) as days_overdue
                        FROM accounts_receivable ar
                        WHERE (" . ($hasCreditAmount ? "ar.credit_amount" : "ar.total_amount") . " - COALESCE(ar.paid_amount, 0)) > 0
                            AND ar.due_date <= '{$escapedAsOfDate}'
                            {$creditFilter}
                        ORDER BY ar.due_date ASC
                    ");
                } else {
                    $stmt = $conn->query("
                        SELECT 
                            invoice_number,
                            'N/A' as customer_name,
                            invoice_date,
                            due_date,
                            " . ($hasCreditAmount ? "credit_amount" : "total_amount") . " as total_amount,
                            COALESCE(paid_amount, 0) as paid_amount,
                            (" . ($hasCreditAmount ? "credit_amount" : "total_amount") . " - COALESCE(paid_amount, 0)) as balance,
                            DATEDIFF('{$escapedAsOfDate}', due_date) as days_overdue
                        FROM accounts_receivable
                        WHERE (" . ($hasCreditAmount ? "credit_amount" : "total_amount") . " - COALESCE(paid_amount, 0)) > 0
                            AND due_date <= '{$escapedAsOfDate}'
                            {$creditFilter}
                        ORDER BY due_date ASC
                    ");
                }
            }
            
            if ($stmt) {
                while ($row = $stmt->fetch_assoc()) {
                    $report['receivables'][] = $row;
                }
                $stmt->free();
            }
        } else {
            // If no credit note field, return empty (or use same as debt receivable)
            // For now, return empty report
        }
    }
    
    $totalOutstanding = !empty($report['receivables']) ? array_sum(array_column($report['receivables'], 'balance')) : 0;
    $report['total_outstanding'] = $totalOutstanding;
    
    return $report;
}

function generateExpenseStatement($conn, $startDate = null, $endDate = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedStartDate = escapeDate($conn, $startDate);
    $escapedEndDate = escapeDate($conn, $endDate);
    
    $report = [
        'title' => 'Expense Statement',
        'period' => $startDate . ' to ' . $endDate,
        'expenses' => []
    ];

    if (!tableExists($conn, 'financial_transactions')) {
        return $report;
    }

    // Check if status column exists
    $hasStatus = columnExists($conn, 'financial_transactions', 'status');
    $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
    
    $categoryField = columnExists($conn, 'financial_transactions', 'category') ? "COALESCE(category, 'Uncategorized')" : "'Uncategorized'";
    $descriptionField = columnExists($conn, 'financial_transactions', 'description') ? "COALESCE(description, '')" : "CONCAT('Transaction #', ft.id)";
    $dateField = columnExists($conn, 'financial_transactions', 'transaction_date')
        ? 'transaction_date'
        : (columnExists($conn, 'financial_transactions', 'created_at') ? 'created_at' : 'NULL');
    $amountField = columnExists($conn, 'financial_transactions', 'total_amount')
        ? 'COALESCE(total_amount, 0)'
        : (columnExists($conn, 'financial_transactions', 'amount') ? 'COALESCE(amount, 0)' : '0');
    $typeCondition = columnExists($conn, 'financial_transactions', 'transaction_type') ? "transaction_type = 'Expense'" : "1 = 1";
    $dateFilter = "AND {$dateField} >= '{$escapedStartDate}' AND {$dateField} <= '{$escapedEndDate}'";

    // Get expenses grouped by category or description
    $stmt = $conn->query("
        SELECT 
            {$categoryField} as category,
            {$descriptionField} as description,
            {$dateField} as transaction_date,
            SUM({$amountField}) as total_amount,
            COUNT(*) as transaction_count
        FROM financial_transactions ft
        WHERE {$typeCondition} {$statusFilter} {$dateFilter}
        GROUP BY {$categoryField}, {$descriptionField}, {$dateField}
        ORDER BY total_amount DESC
        LIMIT 200
    ");
    
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $row['total_amount'] = floatval($row['total_amount']);
            $report['expenses'][] = $row;
        }
        $stmt->free(); // Free result set
    }
    
    // Get expenses from bills
    if (tableExists($conn, 'accounts_payable')) {
        $billDateFilter = "AND bill_date >= '{$startDate}' AND bill_date <= '{$endDate}'";
        $billStmt = $conn->query("
            SELECT 
                'Bills' as category,
                CONCAT('Bill: ', bill_number) as description,
                bill_date as transaction_date,
                total_amount,
                1 as transaction_count
            FROM accounts_payable
            WHERE status IN ('Received', 'Paid', 'Partially Paid') {$billDateFilter}
            ORDER BY bill_date DESC
            LIMIT 100
        ");
        
        if ($billStmt) {
            while ($row = $billStmt->fetch_assoc()) {
                $row['total_amount'] = floatval($row['total_amount']);
                $report['expenses'][] = $row;
            }
        }
    }
    
    $totalExpenses = !empty($report['expenses']) ? array_sum(array_column($report['expenses'], 'total_amount')) : 0;
    $report['totals'] = [
        'total_expenses' => $totalExpenses,
        'transaction_count' => count($report['expenses'])
    ];
    
    return $report;
}

function generateChartOfAccounts($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Chart of Accounts',
        'as_of' => $asOfDate,
        'accounts' => []
    ];
    
    if (tableExists($conn, 'financial_accounts')) {
        // Check which columns exist
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        $hasAccountType = false;
        $hasCurrentBalance = false;
        $hasParentId = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
                if ($col['Field'] === 'account_type') $hasAccountType = true;
                if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
                if ($col['Field'] === 'parent_account_id' || $col['Field'] === 'parent_id') $hasParentId = true;
            }
        }
        
        $selectFields = ['account_code', 'account_name'];
        if ($hasCategory) $selectFields[] = 'category';
        if ($hasAccountType && !$hasCategory) $selectFields[] = 'account_type as category';
        if ($hasCurrentBalance) $selectFields[] = 'COALESCE(current_balance, 0) as balance';
        if ($hasParentId) {
            $parentFieldCheck = $conn->query("SHOW COLUMNS FROM financial_accounts LIKE 'parent_account_id'");
            $parentField = ($parentFieldCheck && $parentFieldCheck->num_rows > 0) ? 'parent_account_id' : 'parent_id';
            $selectFields[] = $parentField . ' as parent_id';
        }
        
        $stmt = $conn->query("
            SELECT " . implode(', ', $selectFields) . "
            FROM financial_accounts
            ORDER BY account_code
        ");
        
        if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            if (!isset($row['balance'])) $row['balance'] = 0;
            if (!isset($row['category'])) $row['category'] = 'Other';
            $report['accounts'][] = $row;
            }
        }
    }
    
    // Group by category
    $grouped = [];
    foreach ($report['accounts'] as $account) {
        $category = $account['category'] ?? 'Other';
        if (!isset($grouped[$category])) {
            $grouped[$category] = [];
        }
        $grouped[$category][] = $account;
    }
    
    $report['grouped'] = $grouped;
    $report['total_accounts'] = count($report['accounts']);
    
    return $report;
}

function generateValueAdded($conn, $startDate = null, $endDate = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedStartDate = escapeDate($conn, $startDate);
    $escapedEndDate = escapeDate($conn, $endDate);
    
    $report = [
        'title' => 'Value Added',
        'period' => $startDate . ' to ' . $endDate,
        'data' => [],
        'totals' => []
    ];
    
    // Value Added = Revenue - Cost of Goods Sold - Operating Expenses
    // Or: Value Added = Sales - Purchases - Services
    
    $revenue = 0;
    $costOfGoodsSold = 0;
    $operatingExpenses = 0;
    
    // Get Revenue
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        $dateFilter = "AND transaction_date >= '{$escapedStartDate}' AND transaction_date <= '{$escapedEndDate}'";
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as total_revenue
            FROM financial_transactions
            WHERE transaction_type = 'Income' {$statusFilter} {$dateFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $revenue = floatval($row['total_revenue'] || 0);
            $stmt->free();
        }
    }
    
    // Get from invoices
    if (tableExists($conn, 'accounts_receivable')) {
        $invoiceDateFilter = "AND invoice_date >= '{$escapedStartDate}' AND invoice_date <= '{$escapedEndDate}'";
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as invoice_revenue
            FROM accounts_receivable
            WHERE 1=1 {$invoiceDateFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $revenue += floatval($row['invoice_revenue'] || 0);
        }
    }
    
    // Get Cost of Goods Sold (COGS) - typically expense accounts related to production
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
            }
        }
        
        $categoryField = $hasCategory ? 'category' : 'account_type';
        
        // COGS accounts (typically Expense accounts with names like Cost, COGS, etc.)
        $stmt = $conn->query("
            SELECT 
                fa.account_code,
                fa.account_name,
                COALESCE(fa.current_balance, 0) as balance
            FROM financial_accounts fa
            WHERE fa.{$categoryField} = 'Expense'
                AND (
                    fa.account_name LIKE '%Cost%'
                    OR fa.account_name LIKE '%COGS%'
                    OR fa.account_name LIKE '%Goods%'
                    OR fa.account_name LIKE '%Material%'
                    OR fa.account_code LIKE '5%'
                )
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $costAmount = abs(floatval($row['balance'] || 0));
                $costOfGoodsSold += $costAmount;
                
                $report['data'][] = [
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'type' => 'Cost of Goods Sold',
                    'amount' => $costAmount
                ];
            }
            $stmt->free();
        }
    }
    
    // Get Operating Expenses
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as total_expenses
            FROM financial_transactions
            WHERE transaction_type = 'Expense' {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $operatingExpenses = floatval($row['total_expenses'] || 0);
            $stmt->free();
        }
    }
    
    // Calculate Value Added
    $valueAdded = $revenue - $costOfGoodsSold - $operatingExpenses;
    $valueAddedPercentage = $revenue > 0 ? ($valueAdded / $revenue) * 100 : 0;
    
    $report['data'][] = [
        'account_code' => '',
        'account_name' => 'Total Revenue',
        'type' => 'Revenue',
        'amount' => $revenue
    ];
    
    $report['data'][] = [
        'account_code' => '',
        'account_name' => 'Total Operating Expenses',
        'type' => 'Expenses',
        'amount' => $operatingExpenses
    ];
    
    $report['totals'] = [
        'total_revenue' => $revenue,
        'cost_of_goods_sold' => $costOfGoodsSold,
        'operating_expenses' => $operatingExpenses,
        'value_added' => $valueAdded,
        'value_added_percentage' => $valueAddedPercentage
    ];
    
    return $report;
}

function generateFixedAssets($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Fixed Assets Report',
        'as_of' => $asOfDate,
        'assets' => [],
        'totals' => []
    ];
    
    // Get fixed assets from financial_accounts
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        $hasAccountType = false;
        $hasCurrentBalance = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
                if ($col['Field'] === 'account_type') $hasAccountType = true;
                if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
            }
        }
        
        $categoryField = $hasCategory ? 'category' : ($hasAccountType ? 'account_type' : 'account_type');
        
        // Check if transaction_lines and journal_entry_lines exist for calculation
        $hasTransactionLines = tableExists($conn, 'transaction_lines');
        $hasJournalEntryLines = tableExists($conn, 'journal_entry_lines');
        
        // Build balance field
        if ($hasCurrentBalance) {
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlBalanceSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0) - COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelBalanceSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0) - COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                $balanceField = "COALESCE(
                    NULLIF(fa.current_balance, 0),
                    ({$tlBalanceSubquery}) + ({$jelBalanceSubquery})
                )";
            } else {
                $balanceField = 'COALESCE(fa.current_balance, 0)';
            }
        } else {
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlBalanceSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0) - COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelBalanceSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0) - COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                $balanceField = "({$tlBalanceSubquery}) + ({$jelBalanceSubquery})";
            } else {
                $balanceField = '0';
            }
        }
        
        // Get fixed assets (Asset category/type, typically non-current assets)
        $stmt = $conn->query("
            SELECT 
                fa.account_code,
                fa.account_name,
                {$balanceField} as balance,
                fa.description
            FROM financial_accounts fa
            WHERE fa.{$categoryField} = 'Asset'
                AND (
                    fa.account_name LIKE '%Fixed%' 
                    OR fa.account_name LIKE '%Asset%'
                    OR fa.account_name LIKE '%Property%'
                    OR fa.account_name LIKE '%Equipment%'
                    OR fa.account_name LIKE '%Plant%'
                    OR fa.account_code LIKE '15%'
                    OR fa.account_code LIKE '16%'
                )
            ORDER BY fa.account_code
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['assets'][] = [
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'balance' => floatval($row['balance'] || 0),
                    'description' => $row['description'] ?? null
                ];
            }
        }
    }
    
    $totalAssets = !empty($report['assets']) ? array_sum(array_column($report['assets'], 'balance')) : 0;
    $report['totals'] = [
        'total_assets' => $totalAssets,
        'asset_count' => count($report['assets'])
    ];
    
    return $report;
}

function generateEntriesByYear($conn, $startDate = null, $endDate = null) {
    // Default to last 5 years if no dates provided
    if (!$startDate) {
        $startDate = date('Y-01-01', strtotime('-4 years'));
    }
    if (!$endDate) {
        $endDate = date('Y-12-31');
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedStartDate = escapeDate($conn, $startDate);
    $escapedEndDate = escapeDate($conn, $endDate);
    
    $report = [
        'title' => 'Entries by Year Report',
        'period' => $startDate . ' to ' . $endDate,
        'data' => [],
        'years' => []
    ];
    
    // Get entries grouped by year from journal_entries
    if (tableExists($conn, 'journal_entries')) {
        $hasStatus = columnExists($conn, 'journal_entries', 'status');
        $statusFilter = $hasStatus ? "WHERE status = 'Posted'" : "";
        
        $dateFilter = "AND entry_date >= '{$escapedStartDate}' AND entry_date <= '{$escapedEndDate}'";
        $stmt = $conn->query("
            SELECT 
                YEAR(entry_date) as year,
                COUNT(*) as entry_count,
                SUM(
                    COALESCE((
                        SELECT SUM(COALESCE(jel.debit_amount, 0) + COALESCE(jel.credit_amount, 0))
                        FROM journal_entry_lines jel
                        WHERE jel.journal_entry_id = je.id
                    ), 0)
                ) as total_amount
            FROM journal_entries je
            WHERE 1=1 {$statusFilter} {$dateFilter}
            GROUP BY YEAR(entry_date)
            ORDER BY year DESC
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['data'][] = [
                    'year' => intval($row['year']),
                    'entry_count' => intval($row['entry_count']),
                    'total_amount' => floatval($row['total_amount'] || 0)
                ];
                $report['years'][] = intval($row['year']);
            }
            $stmt->free(); // Free result set
        }
    }
    
    // Also get from financial_transactions if available
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "WHERE status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT 
                YEAR(transaction_date) as year,
                COUNT(*) as transaction_count,
                SUM(COALESCE(total_amount, 0)) as total_amount
            FROM financial_transactions
            WHERE 1=1 {$statusFilter} AND transaction_date >= '{$startDate}' AND transaction_date <= '{$endDate}'
            GROUP BY YEAR(transaction_date)
            ORDER BY year DESC
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $year = intval($row['year']);
                $existingIndex = array_search($year, array_column($report['data'], 'year'));
                
                if ($existingIndex !== false) {
                    // Merge with existing year data
                    $report['data'][$existingIndex]['entry_count'] += intval($row['transaction_count']);
                    $report['data'][$existingIndex]['total_amount'] += floatval($row['total_amount'] || 0);
                } else {
                    // Add new year
                    $report['data'][] = [
                        'year' => $year,
                        'entry_count' => intval($row['transaction_count']),
                        'total_amount' => floatval($row['total_amount'] || 0)
                    ];
                    $report['years'][] = $year;
                }
            }
        }
    }
    
    // Sort by year descending
    usort($report['data'], function($a, $b) {
        return $b['year'] - $a['year'];
    });
    
    $report['totals'] = [
        'total_entries' => !empty($report['data']) ? array_sum(array_column($report['data'], 'entry_count')) : 0,
        'total_amount' => !empty($report['data']) ? array_sum(array_column($report['data'], 'total_amount')) : 0
    ];
    
    return $report;
}

function generateCustomerDebits($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Customer Debits Report',
        'as_of' => $asOfDate,
        'customers' => [],
        'totals' => []
    ];
    
    // Get customer debits from accounts_receivable
    if (tableExists($conn, 'accounts_receivable')) {
        $hasCustomersTable = tableExists($conn, 'accounting_customers');
        
        if ($hasCustomersTable) {
            $stmt = $conn->query("
                SELECT 
                    ac.id as customer_id,
                    ac.customer_name,
                    COUNT(ar.id) as invoice_count,
                    SUM(ar.total_amount) as total_invoiced,
                    SUM(COALESCE(ar.paid_amount, 0)) as total_paid,
                    SUM(ar.total_amount - COALESCE(ar.paid_amount, 0)) as total_debit,
                    MAX(ar.due_date) as latest_due_date,
                    SUM(CASE WHEN ar.due_date < '{$asOfDate}' AND (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0 THEN 1 ELSE 0 END) as overdue_count
                FROM accounts_receivable ar
                LEFT JOIN accounting_customers ac ON ar.customer_id = ac.id
                WHERE (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0
                GROUP BY ac.id, ac.customer_name
                HAVING total_debit > 0
                ORDER BY total_debit DESC
            ");
        } else {
            $stmt = $conn->query("
                SELECT 
                    COALESCE(ar.customer_id, 0) as customer_id,
                    CONCAT('Customer #', COALESCE(ar.customer_id, 'N/A')) as customer_name,
                    COUNT(ar.id) as invoice_count,
                    SUM(ar.total_amount) as total_invoiced,
                    SUM(COALESCE(ar.paid_amount, 0)) as total_paid,
                    SUM(ar.total_amount - COALESCE(ar.paid_amount, 0)) as total_debit,
                    MAX(ar.due_date) as latest_due_date,
                    SUM(CASE WHEN ar.due_date < '{$asOfDate}' AND (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0 THEN 1 ELSE 0 END) as overdue_count
                FROM accounts_receivable ar
                WHERE (ar.total_amount - COALESCE(ar.paid_amount, 0)) > 0
                    AND ar.due_date <= '{$asOfDate}'
                GROUP BY ar.customer_id
                HAVING total_debit > 0
                ORDER BY total_debit DESC
            ");
        }
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['customers'][] = [
                    'customer_id' => intval($row['customer_id']),
                    'customer_name' => $row['customer_name'],
                    'invoice_count' => intval($row['invoice_count']),
                    'total_invoiced' => floatval($row['total_invoiced'] || 0),
                    'total_paid' => floatval($row['total_paid'] || 0),
                    'total_debit' => floatval($row['total_debit'] || 0),
                    'latest_due_date' => $row['latest_due_date'],
                    'overdue_count' => intval($row['overdue_count'] || 0)
                ];
            }
        }
    }
    
    // Also check transaction_lines for customer-related debits
    if (tableExists($conn, 'transaction_lines') && tableExists($conn, 'financial_accounts')) {
        $stmt = $conn->query("
            SELECT 
                fa.account_name,
                SUM(tl.debit_amount) as total_debit,
                COUNT(DISTINCT tl.transaction_id) as transaction_count
            FROM transaction_lines tl
            JOIN financial_accounts fa ON tl.account_id = fa.id
            WHERE UPPER(fa.account_type) = 'ASSET' 
                AND fa.account_name LIKE '%Receivable%'
                AND tl.debit_amount > 0
            GROUP BY fa.id, fa.account_name
            ORDER BY total_debit DESC
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $report['customers'][] = [
                    'customer_id' => 0,
                    'customer_name' => $row['account_name'],
                    'invoice_count' => intval($row['transaction_count']),
                    'total_invoiced' => floatval($row['total_debit'] || 0),
                    'total_paid' => 0,
                    'total_debit' => floatval($row['total_debit'] || 0),
                    'latest_due_date' => null,
                    'overdue_count' => 0
                ];
            }
        }
    }
    
    $totalDebit = !empty($report['customers']) ? array_sum(array_column($report['customers'], 'total_debit')) : 0;
    $totalInvoiced = !empty($report['customers']) ? array_sum(array_column($report['customers'], 'total_invoiced')) : 0;
    $totalPaid = !empty($report['customers']) ? array_sum(array_column($report['customers'], 'total_paid')) : 0;
    
    $report['totals'] = [
        'total_customers' => count($report['customers']),
        'total_invoiced' => $totalInvoiced,
        'total_paid' => $totalPaid,
        'total_debit' => $totalDebit
    ];
    
    return $report;
}

function generateStatisticalPosition($conn, $asOfDate = null) {
    $asOfDate = $asOfDate ?: date('Y-m-d');
    $report = [
        'title' => 'Statistical Position Report',
        'as_of' => $asOfDate,
        'data' => [],
        'statistics' => []
    ];
    
    $statistics = [];
    
    // 1. Account Statistics
    if (tableExists($conn, 'financial_accounts')) {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_accounts,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_accounts,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_accounts
            FROM financial_accounts
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $statistics['accounts'] = [
                'total' => intval($row['total_accounts']),
                'active' => intval($row['active_accounts']),
                'inactive' => intval($row['inactive_accounts'])
            ];
        }
    }
    
    // 2. Transaction Statistics
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "WHERE status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(COALESCE(total_amount, 0)) as total_amount,
                AVG(COALESCE(total_amount, 0)) as avg_amount,
                MIN(COALESCE(total_amount, 0)) as min_amount,
                MAX(COALESCE(total_amount, 0)) as max_amount
            FROM financial_transactions
            {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $statistics['transactions'] = [
                'total' => intval($row['total_transactions']),
                'total_amount' => floatval($row['total_amount'] || 0),
                'avg_amount' => floatval($row['avg_amount'] || 0),
                'min_amount' => floatval($row['min_amount'] || 0),
                'max_amount' => floatval($row['max_amount'] || 0)
            ];
        }
    }
    
    // 3. Journal Entry Statistics
    if (tableExists($conn, 'journal_entries')) {
        $hasStatus = columnExists($conn, 'journal_entries', 'status');
        $statusFilter = $hasStatus ? "WHERE status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_entries,
                COUNT(DISTINCT DATE(entry_date)) as days_with_entries
            FROM journal_entries
            {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $statistics['journal_entries'] = [
                'total' => intval($row['total_entries']),
                'days_with_entries' => intval($row['days_with_entries'])
            ];
        }
    }
    
    // 4. Receivables Statistics
    if (tableExists($conn, 'accounts_receivable')) {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(COALESCE(total_amount, 0)) as total_invoiced,
                SUM(COALESCE(paid_amount, 0)) as total_paid,
                SUM(COALESCE(total_amount, 0) - COALESCE(paid_amount, 0)) as total_outstanding,
                AVG(DATEDIFF('{$asOfDate}', due_date)) as avg_days_overdue
            FROM accounts_receivable
            WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
                AND due_date <= '{$asOfDate}'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $statistics['receivables'] = [
                'total_invoices' => intval($row['total_invoices']),
                'total_invoiced' => floatval($row['total_invoiced'] || 0),
                'total_paid' => floatval($row['total_paid'] || 0),
                'total_outstanding' => floatval($row['total_outstanding'] || 0),
                'avg_days_overdue' => floatval($row['avg_days_overdue'] || 0)
            ];
        }
    }
    
    // 5. Payables Statistics
    if (tableExists($conn, 'accounts_payable')) {
        $stmt = $conn->query("
            SELECT 
                COUNT(*) as total_bills,
                SUM(COALESCE(total_amount, 0)) as total_billed,
                SUM(COALESCE(paid_amount, 0)) as total_paid,
                SUM(COALESCE(total_amount, 0) - COALESCE(paid_amount, 0)) as total_outstanding,
                AVG(DATEDIFF('{$asOfDate}', due_date)) as avg_days_overdue
            FROM accounts_payable
            WHERE (total_amount - COALESCE(paid_amount, 0)) > 0
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $statistics['payables'] = [
                'total_bills' => intval($row['total_bills']),
                'total_billed' => floatval($row['total_billed'] || 0),
                'total_paid' => floatval($row['total_paid'] || 0),
                'total_outstanding' => floatval($row['total_outstanding'] || 0),
                'avg_days_overdue' => floatval($row['avg_days_overdue'] || 0)
            ];
        }
    }
    
    // Convert statistics to data array for table display
    foreach ($statistics as $category => $stats) {
        foreach ($stats as $key => $value) {
            $report['data'][] = [
                'category' => ucfirst(str_replace('_', ' ', $category)),
                'metric' => ucfirst(str_replace('_', ' ', $key)),
                'value' => is_numeric($value) ? $value : $value
            ];
        }
    }
    
    $report['statistics'] = $statistics;
    
    return $report;
}

function generateChangesInEquity($conn, $startDate = null, $endDate = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedStartDate = escapeDate($conn, $startDate);
    $escapedEndDate = escapeDate($conn, $endDate);
    
    $report = [
        'title' => 'Changes in Equity',
        'period' => $startDate . ' to ' . $endDate,
        'data' => [],
        'equity_changes' => []
    ];
    
    // Get equity accounts
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        $hasAccountType = false;
        $hasCurrentBalance = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
                if ($col['Field'] === 'account_type') $hasAccountType = true;
                if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
            }
        }
        
        $categoryField = $hasCategory ? 'category' : ($hasAccountType ? 'account_type' : 'account_type');
        
        // Check if transaction_lines and journal_entry_lines exist for calculation
        $hasTransactionLines = tableExists($conn, 'transaction_lines');
        $hasJournalEntryLines = tableExists($conn, 'journal_entry_lines');
        
        // Build balance field
        if ($hasCurrentBalance) {
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlBalanceSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0) - COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelBalanceSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0) - COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                $balanceField = "COALESCE(
                    NULLIF(fa.current_balance, 0),
                    ({$tlBalanceSubquery}) + ({$jelBalanceSubquery})
                )";
            } else {
                $balanceField = 'COALESCE(fa.current_balance, 0)';
            }
        } else {
            if ($hasTransactionLines || $hasJournalEntryLines) {
                $tlBalanceSubquery = $hasTransactionLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(tl.debit_amount, 0) - COALESCE(tl.credit_amount, 0)), 0)
                    FROM transaction_lines tl
                    WHERE tl.account_id = fa.id
                ), 0)" : "0";
                $jelBalanceSubquery = $hasJournalEntryLines ? "COALESCE((
                    SELECT COALESCE(SUM(COALESCE(jel.debit_amount, 0) - COALESCE(jel.credit_amount, 0)), 0)
                    FROM journal_entry_lines jel
                    WHERE jel.account_id = fa.id
                ), 0)" : "0";
                $balanceField = "({$tlBalanceSubquery}) + ({$jelBalanceSubquery})";
            } else {
                $balanceField = '0';
            }
        }
        
        // Get equity accounts with monthly changes
        $stmt = $conn->query("
            SELECT 
                fa.account_code,
                fa.account_name,
                {$balanceField} as current_balance,
                DATE_FORMAT('{$escapedEndDate}', '%Y-%m') as current_period
            FROM financial_accounts fa
            WHERE fa.{$categoryField} = 'Equity'
            ORDER BY fa.account_code
        ");
        
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $accountCode = $row['account_code'];
                
                // Get monthly changes for this equity account using prepared statement
                $monthlyChanges = [];
                if ($hasJournalEntryLines) {
                    $changeQuery = "SELECT 
                            DATE_FORMAT(je.entry_date, '%Y-%m') as period,
                            SUM(COALESCE(jel.credit_amount, 0) - COALESCE(jel.debit_amount, 0)) as change_amount
                        FROM journal_entry_lines jel
                        JOIN journal_entries je ON je.id = jel.journal_entry_id
                        JOIN financial_accounts fa2 ON jel.account_id = fa2.id
                        WHERE fa2.account_code = ? AND je.entry_date >= ? AND je.entry_date <= ?
                        GROUP BY DATE_FORMAT(je.entry_date, '%Y-%m')
                        ORDER BY period DESC";
                    
                    $changeStmt = $conn->prepare($changeQuery);
                    if ($changeStmt) {
                        $changeStmt->bind_param('sss', $accountCode, $escapedStartDate, $escapedEndDate);
                        if ($changeStmt->execute()) {
                            $changeResult = $changeStmt->get_result();
                            if ($changeResult) {
                                while ($change = $changeResult->fetch_assoc()) {
                                    $monthlyChanges[] = [
                                        'period' => $change['period'],
                                        'change_amount' => floatval($change['change_amount'] || 0)
                                    ];
                                }
                                $changeResult->free();
                            }
                            $changeStmt->close();
                        } else {
                            error_log('Changes in Equity: Query execute failed: ' . $changeStmt->error);
                            $changeStmt->close();
                        }
                    } else {
                        error_log('Changes in Equity: Failed to prepare query: ' . $conn->error);
                    }
                }
                
                $report['equity_changes'][] = [
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'current_balance' => floatval($row['current_balance'] || 0),
                    'monthly_changes' => $monthlyChanges
                ];
                
                // Add to data array for table display
                foreach ($monthlyChanges as $change) {
                    $report['data'][] = [
                        'account_code' => $row['account_code'],
                        'account_name' => $row['account_name'],
                        'period' => $change['period'],
                        'change_amount' => $change['change_amount'],
                        'current_balance' => floatval($row['current_balance'] || 0)
                    ];
                }
            }
            $stmt->free();
        }
        if ($columnsCheck) {
            $columnsCheck->free();
        }
    }
    
    $totalEquity = !empty($report['equity_changes']) ? array_sum(array_column($report['equity_changes'], 'current_balance')) : 0;
    $report['totals'] = [
        'total_equity' => $totalEquity,
        'equity_accounts' => count($report['equity_changes'])
    ];
    
    return $report;
}

function generateFinancialPerformance($conn, $startDate = null, $endDate = null) {
    // Default to last 12 months if no dates provided
    if (!$startDate) {
        $startDate = date('Y-m-01', strtotime('-11 months'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-t');
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedStartDate = escapeDate($conn, $startDate);
    $escapedEndDate = escapeDate($conn, $endDate);
    
    $report = [
        'title' => 'Financial Performance',
        'period' => $startDate . ' to ' . $endDate,
        'metrics' => [],
        'performance_data' => []
    ];
    
    // Calculate key financial metrics
    $metrics = [];
    
    // 1. Revenue metrics
    $revenue = 0;
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as total_revenue
            FROM financial_transactions
            WHERE transaction_type = 'Income' {$statusFilter}
                AND transaction_date >= '{$escapedStartDate}' AND transaction_date <= '{$escapedEndDate}'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $revenue = floatval($row['total_revenue'] || 0);
            $stmt->free();
        }
    }
    
    // Also from invoices
    if (tableExists($conn, 'accounts_receivable')) {
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as invoice_revenue
            FROM accounts_receivable
            WHERE invoice_date >= '{$escapedStartDate}' AND invoice_date <= '{$escapedEndDate}'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $revenue += floatval($row['invoice_revenue'] || 0);
            $stmt->free();
        }
    }
    
    // 2. Expense metrics
    $expenses = 0;
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as total_expenses
            FROM financial_transactions
            WHERE transaction_type = 'Expense' {$statusFilter}
                AND transaction_date >= '{$escapedStartDate}' AND transaction_date <= '{$escapedEndDate}'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $expenses = floatval($row['total_expenses'] || 0);
            $stmt->free();
        }
    }
    
    // Also from bills
    if (tableExists($conn, 'accounts_payable')) {
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as bill_expenses
            FROM accounts_payable
            WHERE bill_date >= '{$escapedStartDate}' AND bill_date <= '{$escapedEndDate}'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $expenses += floatval($row['bill_expenses'] || 0);
            $stmt->free();
        }
    }
    
    // 3. Net Income
    $netIncome = $revenue - $expenses;
    
    // 4. Total Assets
    $totalAssets = 0;
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        $hasCurrentBalance = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
                if ($col['Field'] === 'current_balance') $hasCurrentBalance = true;
            }
            $columnsCheck->free();
        }
        
        $categoryField = $hasCategory ? 'category' : 'account_type';
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(current_balance, 0)) as total_assets
            FROM financial_accounts
            WHERE {$categoryField} = 'Asset'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $totalAssets = floatval($row['total_assets'] || 0);
            $stmt->free();
        }
    }
    
    // 5. Total Liabilities
    $totalLiabilities = 0;
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
            }
            $columnsCheck->free();
        }
        
        $categoryField = $hasCategory ? 'category' : 'account_type';
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(current_balance, 0)) as total_liabilities
            FROM financial_accounts
            WHERE {$categoryField} = 'Liability'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $totalLiabilities = floatval($row['total_liabilities'] || 0);
            $stmt->free();
        }
    }
    
    // 6. Total Equity
    $totalEquity = 0;
    if (tableExists($conn, 'financial_accounts')) {
        $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $hasCategory = false;
        
        if ($columnsCheck) {
            while ($col = $columnsCheck->fetch_assoc()) {
                if ($col['Field'] === 'category') $hasCategory = true;
            }
            $columnsCheck->free();
        }
        
        $categoryField = $hasCategory ? 'category' : 'account_type';
        
        $stmt = $conn->query("
            SELECT SUM(COALESCE(current_balance, 0)) as total_equity
            FROM financial_accounts
            WHERE {$categoryField} = 'Equity'
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $totalEquity = floatval($row['total_equity'] || 0);
            $stmt->free();
        }
    }
    
    // Calculate performance ratios
    $profitMargin = $revenue > 0 ? ($netIncome / $revenue) * 100 : 0;
    $returnOnAssets = $totalAssets > 0 ? ($netIncome / $totalAssets) * 100 : 0;
    $debtToEquity = $totalEquity > 0 ? ($totalLiabilities / $totalEquity) : 0;
    
    $report['metrics'] = [
        'revenue' => $revenue,
        'expenses' => $expenses,
        'net_income' => $netIncome,
        'total_assets' => $totalAssets,
        'total_liabilities' => $totalLiabilities,
        'total_equity' => $totalEquity,
        'profit_margin' => $profitMargin,
        'return_on_assets' => $returnOnAssets,
        'debt_to_equity' => $debtToEquity
    ];
    
    $report['performance_data'] = [
        ['metric' => 'Total Revenue', 'value' => $revenue, 'type' => 'currency'],
        ['metric' => 'Total Expenses', 'value' => $expenses, 'type' => 'currency'],
        ['metric' => 'Net Income', 'value' => $netIncome, 'type' => 'currency'],
        ['metric' => 'Total Assets', 'value' => $totalAssets, 'type' => 'currency'],
        ['metric' => 'Total Liabilities', 'value' => $totalLiabilities, 'type' => 'currency'],
        ['metric' => 'Total Equity', 'value' => $totalEquity, 'type' => 'currency'],
        ['metric' => 'Profit Margin (%)', 'value' => $profitMargin, 'type' => 'percentage'],
        ['metric' => 'Return on Assets (%)', 'value' => $returnOnAssets, 'type' => 'percentage'],
        ['metric' => 'Debt to Equity Ratio', 'value' => $debtToEquity, 'type' => 'ratio']
    ];
    
    return $report;
}

function generateComparativeReport($conn, $startDate = null, $endDate = null) {
    // Default to last 3 months vs previous 3 months if no dates provided
    if (!$startDate || !$endDate) {
        $currentPeriodStart = date('Y-m-01', strtotime('-2 months'));
        $currentPeriodEnd = date('Y-m-t');
        $previousPeriodStart = date('Y-m-01', strtotime('-5 months'));
        $previousPeriodEnd = date('Y-m-t', strtotime('-3 months'));
    } else {
        // Use provided dates for current period, calculate previous period
        $currentPeriodStart = $startDate;
        $currentPeriodEnd = $endDate;
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
        $previousPeriodEnd = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $previousPeriodStart = date('Y-m-d', strtotime($previousPeriodEnd . ' -' . $daysDiff . ' days'));
    }
    
    // Escape dates for SQL queries to prevent SQL injection
    $escapedCurrentPeriodStart = escapeDate($conn, $currentPeriodStart);
    $escapedCurrentPeriodEnd = escapeDate($conn, $currentPeriodEnd);
    $escapedPreviousPeriodStart = escapeDate($conn, $previousPeriodStart);
    $escapedPreviousPeriodEnd = escapeDate($conn, $previousPeriodEnd);
    
    $report = [
        'title' => 'Comparative Report',
        'periods' => [],
        'data' => [],
        'comparisons' => []
    ];
    
    $currentPeriod = [
        'start' => $currentPeriodStart,
        'end' => $currentPeriodEnd,
        'label' => date('M Y', strtotime($currentPeriodStart)) . ' - ' . date('M Y', strtotime($currentPeriodEnd))
    ];
    
    $previousPeriod = [
        'start' => $previousPeriodStart,
        'end' => $previousPeriodEnd,
        'label' => date('M Y', strtotime($previousPeriodStart)) . ' - ' . date('M Y', strtotime($previousPeriodEnd))
    ];
    
    $report['periods'] = [$previousPeriod, $currentPeriod];
    
    // Compare Revenue
    $currentRevenue = 0;
    $previousRevenue = 0;
    
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        // Current period revenue
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as revenue
            FROM financial_transactions
            WHERE transaction_type = 'Income' 
                AND transaction_date >= '{$escapedCurrentPeriodStart}'
                AND transaction_date <= '{$escapedCurrentPeriodEnd}'
                {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $currentRevenue = floatval($row['revenue'] || 0);
            $stmt->free();
        }
        
        // Previous period revenue
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as revenue
            FROM financial_transactions
            WHERE transaction_type = 'Income' 
                AND transaction_date >= '{$escapedPreviousPeriodStart}'
                AND transaction_date <= '{$escapedPreviousPeriodEnd}'
                {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $previousRevenue = floatval($row['revenue'] || 0);
            $stmt->free();
        }
    }
    
    // Compare Expenses
    $currentExpenses = 0;
    $previousExpenses = 0;
    
    if (tableExists($conn, 'financial_transactions')) {
        $hasStatus = columnExists($conn, 'financial_transactions', 'status');
        $statusFilter = $hasStatus ? "AND status = 'Posted'" : "";
        
        // Current period expenses
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as expenses
            FROM financial_transactions
            WHERE transaction_type = 'Expense' 
                AND transaction_date >= '{$escapedCurrentPeriodStart}'
                AND transaction_date <= '{$escapedCurrentPeriodEnd}'
                {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $currentExpenses = floatval($row['expenses'] || 0);
            $stmt->free();
        }
        
        // Previous period expenses
        $stmt = $conn->query("
            SELECT SUM(COALESCE(total_amount, 0)) as expenses
            FROM financial_transactions
            WHERE transaction_type = 'Expense' 
                AND transaction_date >= '{$escapedPreviousPeriodStart}'
                AND transaction_date <= '{$escapedPreviousPeriodEnd}'
                {$statusFilter}
        ");
        if ($stmt && $row = $stmt->fetch_assoc()) {
            $previousExpenses = floatval($row['expenses'] || 0);
            $stmt->free();
        }
    }
    
    // Calculate Net Income
    $currentNetIncome = $currentRevenue - $currentExpenses;
    $previousNetIncome = $previousRevenue - $previousExpenses;
    
    // Calculate changes
    $revenueChange = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
    $expensesChange = $previousExpenses > 0 ? (($currentExpenses - $previousExpenses) / $previousExpenses) * 100 : 0;
    $netIncomeChange = $previousNetIncome != 0 ? (($currentNetIncome - $previousNetIncome) / abs($previousNetIncome)) * 100 : 0;
    
    $report['comparisons'] = [
        'revenue' => [
            'current' => $currentRevenue,
            'previous' => $previousRevenue,
            'change' => $currentRevenue - $previousRevenue,
            'change_percentage' => $revenueChange
        ],
        'expenses' => [
            'current' => $currentExpenses,
            'previous' => $previousExpenses,
            'change' => $currentExpenses - $previousExpenses,
            'change_percentage' => $expensesChange
        ],
        'net_income' => [
            'current' => $currentNetIncome,
            'previous' => $previousNetIncome,
            'change' => $currentNetIncome - $previousNetIncome,
            'change_percentage' => $netIncomeChange
        ]
    ];
    
    // Format for table display
    $report['data'] = [
        [
            'item' => 'Revenue',
            'previous_period' => $previousRevenue,
            'current_period' => $currentRevenue,
            'change' => $currentRevenue - $previousRevenue,
            'change_percentage' => $revenueChange
        ],
        [
            'item' => 'Expenses',
            'previous_period' => $previousExpenses,
            'current_period' => $currentExpenses,
            'change' => $currentExpenses - $previousExpenses,
            'change_percentage' => $expensesChange
        ],
        [
            'item' => 'Net Income',
            'previous_period' => $previousNetIncome,
            'current_period' => $currentNetIncome,
            'change' => $currentNetIncome - $previousNetIncome,
            'change_percentage' => $netIncomeChange
        ]
    ];
    
    return $report;
}
?>

