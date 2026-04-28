<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/chart-data.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/chart-data.php`.
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
    $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
    $period = max(7, min(365, $period)); // Clamp between 7 and 365 days

    // Group by day for periods <= 30, by week for 31-90, by month for 90+
    $groupFormat = $period <= 30 ? '%Y-%m-%d' : ($period <= 90 ? '%Y-%u' : '%Y-%m');
    
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, ?) as period_label,
            DATE_FORMAT(transaction_date, ?) as period_key,
            COALESCE(SUM(CASE WHEN transaction_type = 'Income' AND status IN ('Approved', 'Posted') THEN total_amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN transaction_type = 'Expense' AND status IN ('Approved', 'Posted') THEN total_amount ELSE 0 END), 0) as expenses
        FROM financial_transactions
        WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE_FORMAT(transaction_date, ?)
        ORDER BY period_key ASC
    ");
    $stmt->bind_param('ssis', $groupFormat, $groupFormat, $period, $groupFormat);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $chartData = [];
    while ($row = $result->fetch_assoc()) {
        $chartData[] = [
            'period' => $row['period_label'],
            'month' => $row['period_key'],
            'income' => floatval($row['income']),
            'expenses' => floatval($row['expenses'])
        ];
    }

    echo json_encode([
        'success' => true,
        'chart_data' => $chartData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching chart data: ' . $e->getMessage()
    ]);
}
?>
