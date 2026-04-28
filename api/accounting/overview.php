<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/overview.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/overview.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0, 'total_receivables' => 0, 'receivables_count' => 0, 'total_payables' => 0, 'payables_count' => 0, 'revenue_change' => 0];
    if (!isset($conn) || !$conn) {
        echo json_encode(array_merge($data, ['success' => true]));
        exit;
    }
    $ftCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if (!$ftCheck || $ftCheck->num_rows === 0) {
        echo json_encode(array_merge($data, ['success' => true]));
        exit;
    }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM financial_transactions WHERE transaction_type = 'Income' AND status IN ('Approved', 'Posted') AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($stmt) { $stmt->execute(); $result = $stmt->get_result()->fetch_assoc(); $data['total_revenue'] = floatval($result['total_revenue'] ?? 0); $stmt->close(); }

    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_expenses FROM financial_transactions WHERE transaction_type = 'Expense' AND status IN ('Approved', 'Posted') AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($stmt) { $stmt->execute(); $result = $stmt->get_result()->fetch_assoc(); $data['total_expenses'] = floatval($result['total_expenses'] ?? 0); $stmt->close(); }

    $data['net_profit'] = $data['total_revenue'] - $data['total_expenses'];

    $bankBalance = 0;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_banks'");
    if ($tableCheck && $tableCheck->num_rows > 0 && file_exists(__DIR__ . '/core/bank-transaction-gl-helper.php')) {
        $tableCheck->free();
        require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
        $banksStmt = $conn->prepare("SELECT id FROM accounting_banks WHERE is_active = 1");
        if ($banksStmt) {
            $banksStmt->execute();
            $banksResult = $banksStmt->get_result();
            
            while ($bankRow = $banksResult->fetch_assoc()) {
                $bankId = intval($bankRow['id']);
                $glBalance = getBankBalanceFromGL($conn, $bankId);
                $bankBalance += $glBalance;
            }
            
            $banksResult->free();
            $banksStmt->close();
        }
    } else {
        if ($tableCheck) $tableCheck->free();
    }

    // Try to get cash account balance from transaction_lines
    $cashAccountBalance = 0;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(tl.debit_amount - tl.credit_amount), 0) as cash_balance
            FROM transaction_lines tl
            INNER JOIN financial_accounts fa ON tl.account_id = fa.id
            WHERE fa.account_code = '1100' AND fa.account_name LIKE '%Cash%'
        ");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $cashAccountBalance = floatval($result['cash_balance'] ?? 0);
        }
    }

    $data['cash_balance'] = $bankBalance + $cashAccountBalance;

    // Total Receivables (from accounts_receivable table if exists, otherwise from old accounting_invoices)
    $receivablesQuery = "
        SELECT 
            COALESCE(SUM(balance_amount), 0) as total_receivables,
            COUNT(*) as receivables_count
        FROM accounts_receivable
        WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')
    ";
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_receivable'");
    $result = ['total_receivables' => 0, 'receivables_count' => 0];
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare($receivablesQuery);
        if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); if ($r) $result = $r; $stmt->close(); }
    } else {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_invoices'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_receivables, COUNT(*) as receivables_count FROM accounting_invoices WHERE status NOT IN ('Paid', 'Cancelled')");
            if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); if ($r) $result = $r; $stmt->close(); }
        }
    }
    $data['total_receivables'] = floatval($result['total_receivables'] ?? 0);
    $data['receivables_count'] = intval($result['receivables_count'] ?? 0);

    $result = ['total_payables' => 0, 'payables_count' => 0];
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounts_payable'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(balance_amount), 0) as total_payables, COUNT(*) as payables_count FROM accounts_payable WHERE status NOT IN ('Paid', 'Cancelled', 'Voided')");
        if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); if ($r) $result = $r; $stmt->close(); }
    } else {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bills'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount - COALESCE(paid_amount, 0)), 0) as total_payables, COUNT(*) as payables_count FROM accounting_bills WHERE status NOT IN ('Paid', 'Cancelled')");
            if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); if ($r) $result = $r; $stmt->close(); }
        }
    }
    $data['total_payables'] = floatval($result['total_payables'] ?? 0);
    $data['payables_count'] = intval($result['payables_count'] ?? 0);

    $previousRevenue = 0;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as previous_revenue FROM financial_transactions WHERE transaction_type = 'Income' AND status IN ('Approved', 'Posted') AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND transaction_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $previousRevenue = floatval($r['previous_revenue'] ?? 0); $stmt->close(); }
    $data['revenue_change'] = $previousRevenue > 0 ? (($data['total_revenue'] - $previousRevenue) / $previousRevenue) * 100 : ($data['total_revenue'] > 0 ? 100 : 0);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Throwable $e) {
    error_log('Overview error: ' . $e->getMessage());
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching overview data: ' . $e->getMessage(),
        'data' => ['total_revenue' => 0, 'total_expenses' => 0, 'net_profit' => 0, 'cash_balance' => 0, 'total_receivables' => 0, 'receivables_count' => 0, 'total_payables' => 0, 'payables_count' => 0, 'revenue_change' => 0]
    ]);
}
?>
