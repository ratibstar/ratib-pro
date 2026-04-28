<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounts/stats.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounts/stats.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

// Check if user is logged in
// Temporarily disabled for testing
/*
if (!is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
*/

header('Content-Type: application/json');

try {
    $stats = [];
    
    // Get today's date
    $today = date('Y-m-d');
    $currentMonth = date('Y-m');
    $currentYear = date('Y');
    
    // 1. Daily Journal Stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as today_entries,
            COALESCE(SUM(total_debit), 0) as total_amount
        FROM journal_entries 
        WHERE DATE(entry_date) = ? AND status = 'posted'
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $journalStats = $stmt->get_result()->fetch_assoc();
    
    $stats['journal_today_entries'] = [
        'value' => $journalStats['today_entries'] ?? 0,
        'isCurrency' => false
    ];
    $stats['journal_total_amount'] = [
        'value' => $journalStats['total_amount'] ?? 0,
        'isCurrency' => true
    ];
    
    // 2. Expenses Stats
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as month_total,
            COALESCE(SUM(CASE WHEN YEAR(expense_date) = ? THEN total_amount ELSE 0 END), 0) as ytd_total
        FROM expenses 
        WHERE DATE_FORMAT(expense_date, '%Y-%m') = ? AND status IN ('paid', 'approved')
    ");
    $stmt->bind_param("ss", $currentYear, $currentMonth);
    $stmt->execute();
    $expenseStats = $stmt->get_result()->fetch_assoc();
    
    $stats['expenses_month'] = [
        'value' => $expenseStats['month_total'] ?? 0,
        'isCurrency' => true
    ];
    $stats['expenses_ytd'] = [
        'value' => $expenseStats['ytd_total'] ?? 0,
        'isCurrency' => true
    ];
    
    // 3. Receipts Stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as today_receipts,
            COALESCE(SUM(total_amount), 0) as total_amount
        FROM receipts 
        WHERE DATE(receipt_date) = ? AND status = 'received'
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $receiptStats = $stmt->get_result()->fetch_assoc();
    
    $stats['receipts_today'] = [
        'value' => $receiptStats['today_receipts'] ?? 0,
        'isCurrency' => false
    ];
    $stats['receipts_total_amount'] = [
        'value' => $receiptStats['total_amount'] ?? 0,
        'isCurrency' => true
    ];
    
    // 4. Payments Stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'processed' THEN 1 END) as processed_count
        FROM payments 
        WHERE DATE(payment_date) = ?
    ");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $paymentStats = $stmt->get_result()->fetch_assoc();
    
    $stats['payments_pending'] = [
        'value' => $paymentStats['pending_count'] ?? 0,
        'isCurrency' => false
    ];
    $stats['payments_processed'] = [
        'value' => $paymentStats['processed_count'] ?? 0,
        'isCurrency' => false
    ];
    
    // 5. Receivables Stats
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount - paid_amount), 0) as outstanding,
            COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status = 'outstanding' THEN amount - paid_amount ELSE 0 END), 0) as overdue
        FROM receivables 
        WHERE status IN ('outstanding', 'overdue')
    ");
    $stmt->execute();
    $receivableStats = $stmt->get_result()->fetch_assoc();
    
    $stats['receivables_outstanding'] = [
        'value' => $receivableStats['outstanding'] ?? 0,
        'isCurrency' => true
    ];
    $stats['receivables_overdue'] = [
        'value' => $receivableStats['overdue'] ?? 0,
        'isCurrency' => true
    ];
    
    // 6. Payables Stats
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount - paid_amount), 0) as outstanding,
            COALESCE(SUM(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'outstanding' THEN amount - paid_amount ELSE 0 END), 0) as due_this_week
        FROM payables 
        WHERE status IN ('outstanding', 'overdue')
    ");
    $stmt->execute();
    $payableStats = $stmt->get_result()->fetch_assoc();
    
    $stats['payables_outstanding'] = [
        'value' => $payableStats['outstanding'] ?? 0,
        'isCurrency' => true
    ];
    $stats['payables_due_this_week'] = [
        'value' => $payableStats['due_this_week'] ?? 0,
        'isCurrency' => true
    ];
    
    // 7. Bank Accounts Stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_accounts,
            COALESCE(SUM(current_balance), 0) as total_balance
        FROM bank_accounts 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $bankStats = $stmt->get_result()->fetch_assoc();
    
    $stats['bank_total_balance'] = [
        'value' => $bankStats['total_balance'] ?? 0,
        'isCurrency' => true
    ];
    $stats['bank_accounts'] = [
        'value' => $bankStats['total_accounts'] ?? 0,
        'isCurrency' => false
    ];
    
    // 8. Chart of Accounts Stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_accounts,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_accounts
        FROM chart_of_accounts
    ");
    $stmt->execute();
    $chartStats = $stmt->get_result()->fetch_assoc();
    
    $stats['chart_total_accounts'] = [
        'value' => $chartStats['total_accounts'] ?? 0,
        'isCurrency' => false
    ];
    $stats['chart_active_accounts'] = [
        'value' => $chartStats['active_accounts'] ?? 0,
        'isCurrency' => false
    ];
    
    // 9. Financial Reports Stats (YTD)
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN r.status = 'received' THEN r.total_amount ELSE 0 END), 0) as revenue_ytd,
            COALESCE(SUM(CASE WHEN e.status IN ('paid', 'approved') THEN e.total_amount ELSE 0 END), 0) as expenses_ytd
        FROM receipts r
        LEFT JOIN expenses e ON YEAR(e.expense_date) = YEAR(r.receipt_date)
        WHERE YEAR(r.receipt_date) = ?
    ");
    $stmt->bind_param("s", $currentYear);
    $stmt->execute();
    $reportStats = $stmt->get_result()->fetch_assoc();
    
    $revenueYTD = $reportStats['revenue_ytd'] ?? 0;
    $expensesYTD = $reportStats['expenses_ytd'] ?? 0;
    $profitYTD = $revenueYTD - $expensesYTD;
    
    $stats['reports_revenue_ytd'] = [
        'value' => $revenueYTD,
        'isCurrency' => true
    ];
    $stats['reports_profit_ytd'] = [
        'value' => $profitYTD,
        'isCurrency' => true
    ];
    
    // 10. Settings Stats
    $stmt = $conn->prepare("
        SELECT 
            setting_value as fiscal_year,
            updated_at
        FROM accounting_settings 
        WHERE setting_key = 'fiscal_year_start'
    ");
    $stmt->execute();
    $settingStats = $stmt->get_result()->fetch_assoc();
    
    $stats['settings_fiscal_year'] = [
        'value' => date('Y', strtotime($settingStats['fiscal_year'] ?? '2024-01-01')),
        'isCurrency' => false
    ];
    $stats['settings_last_updated'] = [
        'value' => $settingStats['updated_at'] ? date('Y-m-d H:i', strtotime($settingStats['updated_at'])) : 'Never',
        'isCurrency' => false
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Accounting Stats Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving accounting statistics'
    ]);
}
?> 