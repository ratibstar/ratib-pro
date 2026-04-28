<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/auto-generate-alerts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/auto-generate-alerts.php`.
 */
/**
 * Auto-Generate Alerts (Messages and Follow-ups)
 * Automatically creates messages and follow-ups based on accounting events
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $results = ['messages' => 0, 'followups' => 0];

    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_messages'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'message' => 'Messages table not found.', 'results' => ['messages' => 0, 'followups' => 0]]);
        exit;
    }
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_followups'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'message' => 'Follow-ups table not found.', 'results' => ['messages' => 0, 'followups' => 0]]);
        exit;
    }

    // 1. Check for overdue invoices
    $overdueInvoices = $conn->query("
        SELECT id, invoice_number, total_amount, due_date, customer_name
        FROM accounting_invoices
        WHERE status = 'unpaid'
        AND due_date < CURDATE()
        AND due_date IS NOT NULL
    ");

    while ($invoice = $overdueInvoices->fetch_assoc()) {
        $daysOverdue = (int)((time() - strtotime($invoice['due_date'])) / 86400);

        // Create message
        $stmt = $conn->prepare("
            INSERT INTO accounting_messages
            (type, category, title, message, related_type, related_id, is_important, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $type = $daysOverdue > 30 ? 'error' : 'warning';
        $category = 'overdue_invoice';
        $title = "Overdue Invoice: {$invoice['invoice_number']}";
        $message = "Invoice {$invoice['invoice_number']} for {$invoice['customer_name']} is {$daysOverdue} day(s) overdue. Amount: SAR " . number_format($invoice['total_amount'], 2);
        $relatedType = 'invoice';
        $relatedId = $invoice['id'];
        $stmt->bind_param('sssssi', $type, $category, $title, $message, $relatedType, $relatedId);
        $stmt->execute();
        $results['messages']++;

        // Create follow-up if not exists
        $checkFollowup = $conn->prepare("
            SELECT id FROM accounting_followups
            WHERE related_type = 'invoice' AND related_id = ? AND status IN ('pending', 'in_progress')
        ");
        $checkFollowup->bind_param('i', $invoice['id']);
        $checkFollowup->execute();
        if ($checkFollowup->get_result()->num_rows === 0) {
            $followupStmt = $conn->prepare("
                INSERT INTO accounting_followups
                (title, description, related_type, related_id, due_date, priority, status, created_by, created_at)
                VALUES (?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 3 DAY), ?, 'pending', ?, NOW())
            ");
            $followupTitle = "Follow up on overdue invoice: {$invoice['invoice_number']}";
            $followupDesc = "Invoice is {$daysOverdue} day(s) overdue. Amount: SAR " . number_format($invoice['total_amount'], 2);
            $priority = $daysOverdue > 30 ? 'urgent' : ($daysOverdue > 14 ? 'high' : 'medium');
            $followupStmt->bind_param('sssiss', $followupTitle, $followupDesc, $relatedType, $relatedId, $priority, $userId);
            $followupStmt->execute();
            $results['followups']++;
        }
    }

    // 2. Check for low account balances
    $lowBalanceThreshold = 10000; // SAR 10,000
    $lowBalances = $conn->query("
        SELECT id, account_name, account_code, balance
        FROM financial_accounts
        WHERE UPPER(account_type) IN ('ASSET', 'CASH', 'BANK')
        AND balance < {$lowBalanceThreshold}
        AND balance > 0
        AND is_active = 1
    ");

    while ($account = $lowBalances->fetch_assoc()) {
        // Check if message already exists today
        $checkMsg = $conn->prepare("
            SELECT id FROM accounting_messages
            WHERE category = 'low_balance'
            AND related_type = 'account'
            AND related_id = ?
            AND DATE(created_at) = CURDATE()
        ");
        $checkMsg->bind_param('i', $account['id']);
        $checkMsg->execute();
        if ($checkMsg->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO accounting_messages
                (type, category, title, message, related_type, related_id, is_important, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $type = 'warning';
            $category = 'low_balance';
            $title = "Low Balance Alert: {$account['account_name']}";
            $message = "Account {$account['account_code']} ({$account['account_name']}) has a low balance of SAR " . number_format($account['balance'], 2);
            $relatedType = 'account';
            $relatedId = $account['id'];
            $stmt->bind_param('sssssi', $type, $category, $title, $message, $relatedType, $relatedId);
            $stmt->execute();
            $results['messages']++;
        }
    }

    // 3. Check for bills due soon (within 7 days)
    $billsDueSoon = $conn->query("
        SELECT id, bill_number, total_amount, due_date, vendor_name
        FROM accounting_bills
        WHERE status = 'unpaid'
        AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND due_date IS NOT NULL
    ");

    while ($bill = $billsDueSoon->fetch_assoc()) {
        $daysUntilDue = (int)((strtotime($bill['due_date']) - time()) / 86400);

        // Check if message already exists
        $checkMsg = $conn->prepare("
            SELECT id FROM accounting_messages
            WHERE category = 'bill_due_soon'
            AND related_type = 'bill'
            AND related_id = ?
            AND DATE(created_at) = CURDATE()
        ");
        $checkMsg->bind_param('i', $bill['id']);
        $checkMsg->execute();
        if ($checkMsg->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("
                INSERT INTO accounting_messages
                (type, category, title, message, related_type, related_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $type = 'info';
            $category = 'bill_due_soon';
            $title = "Bill Due Soon: {$bill['bill_number']}";
            $message = "Bill {$bill['bill_number']} for {$bill['vendor_name']} is due in {$daysUntilDue} day(s). Amount: SAR " . number_format($bill['total_amount'], 2);
            $relatedType = 'bill';
            $relatedId = $bill['id'];
            $stmt->bind_param('sssssi', $type, $category, $title, $message, $relatedType, $relatedId);
            $stmt->execute();
            $results['messages']++;

            // Create follow-up
            $followupStmt = $conn->prepare("
                INSERT INTO accounting_followups
                (title, description, related_type, related_id, due_date, priority, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'medium', 'pending', ?, NOW())
            ");
            $followupTitle = "Pay bill: {$bill['bill_number']}";
            $followupDesc = "Bill is due in {$daysUntilDue} day(s). Amount: SAR " . number_format($bill['total_amount'], 2);
            $followupStmt->bind_param('sssiss', $followupTitle, $followupDesc, $relatedType, $relatedId, $bill['due_date'], $userId);
            $followupStmt->execute();
            $results['followups']++;
        }
    }

    // 4. Check for pending transactions older than 7 days
    $pendingTransactions = $conn->query("
        SELECT id, description, transaction_date, amount
        FROM accounting_transactions
        WHERE status = 'Pending'
        AND transaction_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");

    while ($transaction = $pendingTransactions->fetch_assoc()) {
        $daysPending = (int)((time() - strtotime($transaction['transaction_date'])) / 86400);

        // Check if follow-up already exists
        $checkFollowup = $conn->prepare("
            SELECT id FROM accounting_followups
            WHERE related_type = 'transaction' AND related_id = ? AND status IN ('pending', 'in_progress')
        ");
        $checkFollowup->bind_param('i', $transaction['id']);
        $checkFollowup->execute();
        if ($checkFollowup->get_result()->num_rows === 0) {
            $followupStmt = $conn->prepare("
                INSERT INTO accounting_followups
                (title, description, related_type, related_id, due_date, priority, status, created_by, created_at)
                VALUES (?, ?, ?, ?, CURDATE(), 'high', 'pending', ?, NOW())
            ");
            $followupTitle = "Review pending transaction";
            $followupDesc = "Transaction #{$transaction['id']} has been pending for {$daysPending} day(s). Description: {$transaction['description']}. Amount: SAR " . number_format($transaction['amount'], 2);
            $relatedType = 'transaction';
            $relatedId = $transaction['id'];
            $followupStmt->bind_param('sssiss', $followupTitle, $followupDesc, $relatedType, $relatedId, $userId);
            $followupStmt->execute();
            $results['followups']++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Auto-alerts generated successfully',
        'results' => $results
    ]);

} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => 'Error generating alerts: ' . $e->getMessage(),
        'results' => ['messages' => 0, 'followups' => 0]
    ]);
}

