<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/dashboard.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/dashboard.php`.
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

try {
    enforceApiPermission('accounts', 'view');
    $response = [];
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    $hasFt = $tableCheck && $tableCheck->num_rows > 0;

    if ($hasFt) {
        $columnCheck = $conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'currency'");
        if ($columnCheck && $columnCheck->num_rows === 0) {
            @$conn->query("ALTER TABLE financial_transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'SAR' AFTER total_amount");
        }
    }

    $revenue = ['total_revenue' => 0];
    $expenses = ['total_expenses' => 0];
    $recent_transactions = [];
    $chart_data = [];

    if ($hasFt) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM financial_transactions WHERE transaction_type = 'Income' AND status = 'Posted' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        if ($stmt) { $stmt->execute(); $revenue = $stmt->get_result()->fetch_assoc() ?: $revenue; $stmt->close(); }
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_expenses FROM financial_transactions WHERE transaction_type = 'Expense' AND status = 'Posted' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        if ($stmt) { $stmt->execute(); $expenses = $stmt->get_result()->fetch_assoc() ?: $expenses; $stmt->close(); }
        $stmt = $conn->prepare("SELECT ft.transaction_date, ft.description, ft.transaction_type, ft.total_amount, ft.status FROM financial_transactions ft ORDER BY ft.transaction_date DESC, ft.created_at DESC LIMIT 10");
        if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) $recent_transactions = $res->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
        $stmt = $conn->prepare("SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(CASE WHEN transaction_type = 'Income' THEN total_amount ELSE 0 END) as income, SUM(CASE WHEN transaction_type = 'Expense' THEN total_amount ELSE 0 END) as expenses FROM financial_transactions WHERE transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) AND status = 'Posted' GROUP BY DATE_FORMAT(transaction_date, '%Y-%m') ORDER BY month");
        if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); if ($res) $chart_data = $res->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
    }

    $netProfit = floatval($revenue['total_revenue'] ?? 0) - floatval($expenses['total_expenses'] ?? 0);
    $cashBalance = 0;
    if (file_exists(__DIR__ . '/core/bank-transaction-gl-helper.php')) {
        $banksCheck = $conn->query("SHOW TABLES LIKE 'accounting_banks'");
        if ($banksCheck && $banksCheck->num_rows > 0) {
            require_once __DIR__ . '/core/bank-transaction-gl-helper.php';
            $banksStmt = $conn->prepare("SELECT id FROM accounting_banks WHERE is_active = 1");
            if ($banksStmt) {
                $banksStmt->execute();
                $banksResult = $banksStmt->get_result();
                if ($banksResult) {
                    while ($bankRow = $banksResult->fetch_assoc()) {
                        $cashBalance += getBankBalanceFromGL($conn, intval($bankRow['id']));
                    }
                    $banksResult->free();
                }
                $banksStmt->close();
            }
        }
    }

    if (empty($chart_data)) {
        $chart_data = [['month' => date('Y-m', strtotime('-5 month')), 'income' => 0, 'expenses' => 0], ['month' => date('Y-m'), 'income' => 0, 'expenses' => 0]];
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_income' => floatval($revenue['total_revenue'] ?? 0),
            'total_expense' => floatval($expenses['total_expenses'] ?? 0),
            'net_balance' => $netProfit,
            'bank_balance' => $cashBalance
        ],
        'recent_transactions' => $recent_transactions,
        'chart_data' => $chart_data
    ]);

} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'stats' => ['total_income' => 0, 'total_expense' => 0, 'net_balance' => 0, 'bank_balance' => 0],
        'recent_transactions' => [],
        'chart_data' => [['month' => date('Y-m'), 'income' => 0, 'expenses' => 0]]
    ]);
}
