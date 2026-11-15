<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/payment/commerce.php';

admin_require_permission('finance.export');

$export = (string) ($_GET['export'] ?? '');
$range = admin_finance_range();
$start = $range['start'];
$end = $range['end'];
$q = trim((string) ($_GET['q'] ?? ''));
$hasOrders = admin_table_has($pdo, 'payment_orders', 'status');
$disabledCol = admin_table_has($pdo, 'users', 'disabled_at');

$allowed = ['receipts', 'pending', 'failed', 'customers', 'summary'];
if (!in_array($export, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo admin_t('Unknown export type.', 'نوع التصدير غير معروف.');
    exit;
}

if (($export === 'pending' || $export === 'failed') && !$hasOrders) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo admin_t('Payment orders are not available in this database.', 'جدول الطلبات غير متاح في قاعدة البيانات.');
    exit;
}

$stamp = date('Y-m-d');
$filename = 'rateb-finance-' . $export . '-' . $stamp . '.csv';

if ($export === 'summary') {
    $receiptStmt = $pdo->prepare('SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_receipts WHERE created_at >= ? AND created_at < ?');
    $receiptStmt->execute([$start, $end]);
    $receiptAgg = $receiptStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
    $pendingCount = 0;
    $pendingHalalas = 0;
    $failedCount = 0;
    $failedHalalas = 0;
    if ($hasOrders) {
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_orders WHERE status = 'pending' AND created_at >= ? AND created_at < ?");
        $pendingStmt->execute([$start, $end]);
        $pendingAgg = $pendingStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
        $pendingCount = (int) $pendingAgg['rows_count'];
        $pendingHalalas = (int) $pendingAgg['halalas'];
        $failedStmt = $pdo->prepare("SELECT COUNT(*) AS rows_count, COALESCE(SUM(amount),0) AS halalas FROM payment_orders WHERE status = 'failed' AND created_at >= ? AND created_at < ?");
        $failedStmt->execute([$start, $end]);
        $failedAgg = $failedStmt->fetch() ?: ['rows_count' => 0, 'halalas' => 0];
        $failedCount = (int) $failedAgg['rows_count'];
        $failedHalalas = (int) $failedAgg['halalas'];
    }
    admin_finance_send_csv($filename, static function () use ($range, $start, $end, $receiptAgg, $pendingCount, $pendingHalalas, $failedCount, $failedHalalas): void {
        echo admin_csv_row(['metric', 'value', 'range_start', 'range_end', 'currency_note']);
        echo admin_csv_row(['collected_sar', admin_halalas_to_sar((int) $receiptAgg['halalas']), $start, $end, 'SAR; amounts stored as halalas in DB']);
        echo admin_csv_row(['receipt_count', (string) (int) $receiptAgg['rows_count'], $start, $end, '']);
        echo admin_csv_row(['pending_orders', (string) $pendingCount, $start, $end, 'not revenue']);
        echo admin_csv_row(['pending_sar', admin_halalas_to_sar($pendingHalalas), $start, $end, '']);
        echo admin_csv_row(['failed_orders', (string) $failedCount, $start, $end, 'not revenue']);
        echo admin_csv_row(['failed_sar', admin_halalas_to_sar($failedHalalas), $start, $end, '']);
        $refunded = rateb_refunds_sum_halalas($pdo, $start, $end);
        $net = max(0, (int) $receiptAgg['halalas'] - $refunded);
        echo admin_csv_row(['refunded_sar', admin_halalas_to_sar($refunded), $start, $end, 'payment_refunds']);
        echo admin_csv_row(['net_sar', admin_halalas_to_sar($net), $start, $end, 'collected minus refunds']);
        echo admin_csv_row(['range_key', $range['key'], $range['from'], $range['to'], '']);
    });
}

if ($export === 'receipts') {
    $sql = 'SELECT r.id, r.created_at, r.moyasar_id, r.package_type, r.amount, u.id AS user_id, u.name, u.email
        FROM payment_receipts r INNER JOIN users u ON u.id = r.user_id
        WHERE r.created_at >= ? AND r.created_at < ? ORDER BY r.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start, $end]);
    admin_finance_send_csv($filename, static function () use ($stmt): void {
        echo admin_csv_row(['receipt_id', 'date', 'customer', 'email', 'package', 'amount_sar', 'status', 'moyasar_id', 'currency']);
        foreach ($stmt->fetchAll() as $row) {
            echo admin_csv_row([
                'R' . (int) $row['id'],
                (string) $row['created_at'],
                (string) $row['name'],
                (string) $row['email'],
                (string) $row['package_type'],
                admin_halalas_to_sar((int) $row['amount']),
                'paid',
                (string) $row['moyasar_id'],
                'SAR',
            ]);
        }
    });
}

if ($export === 'pending' || $export === 'failed') {
    $status = $export === 'pending' ? 'pending' : 'failed';
    $stmt = $pdo->prepare("SELECT o.id, o.created_at, o.package_code, o.amount, o.status, COALESCE(o.moyasar_invoice_id,'') AS moyasar_ref, u.name, u.email
        FROM payment_orders o INNER JOIN users u ON u.id = o.user_id
        WHERE o.status = ? AND o.created_at >= ? AND o.created_at < ? ORDER BY o.id DESC");
    $stmt->execute([$status, $start, $end]);
    admin_finance_send_csv($filename, static function () use ($stmt, $status): void {
        echo admin_csv_row(['order_id', 'date', 'customer', 'email', 'package', 'amount_sar', 'status', 'moyasar_invoice', 'currency']);
        foreach ($stmt->fetchAll() as $row) {
            echo admin_csv_row([
                'O' . (int) $row['id'],
                (string) $row['created_at'],
                (string) $row['name'],
                (string) $row['email'],
                (string) $row['package_code'],
                admin_halalas_to_sar((int) $row['amount']),
                $status,
                (string) $row['moyasar_ref'],
                'SAR',
            ]);
        }
    });
}

if ($export === 'customers') {
    $where = '';
    $params = [];
    if ($q !== '') {
        $where = "WHERE (u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\')";
        $like = admin_like(mb_substr($q, 0, 120));
        $params = [$like, $like];
    }
    $disabledExpr = $disabledCol ? 'u.disabled_at' : 'NULL AS disabled_at';
    $sql = "SELECT u.id, u.name, u.email, u.credits_balance, $disabledExpr,
        COALESCE(pay.paid_halalas, 0) AS paid_halalas,
        COALESCE(pay.paid_count, 0) AS paid_count,
        pay.last_payment,
        pl.code AS plan_code
        FROM users u
        LEFT JOIN (
            SELECT user_id, SUM(amount) AS paid_halalas, COUNT(*) AS paid_count, MAX(created_at) AS last_payment
            FROM payment_receipts GROUP BY user_id
        ) pay ON pay.user_id = u.id
        LEFT JOIN subscriptions s ON s.id = (
            SELECT s2.id FROM subscriptions s2
            WHERE s2.user_id = u.id AND s2.status = 'active' AND s2.period_end >= NOW()
            ORDER BY s2.id DESC LIMIT 1
        )
        LEFT JOIN plans pl ON pl.id = s.plan_id
        $where
        ORDER BY pay.paid_halalas DESC, u.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    admin_finance_send_csv($filename, static function () use ($stmt): void {
        echo admin_csv_row(['user_id', 'name', 'email', 'paid_sar', 'paid_count', 'subscription_code', 'credits_balance', 'account_status', 'last_payment']);
        foreach ($stmt->fetchAll() as $row) {
            $disabled = ($row['disabled_at'] ?? null) !== null && (string) $row['disabled_at'] !== '';
            echo admin_csv_row([
                (string) (int) $row['id'],
                (string) $row['name'],
                (string) $row['email'],
                admin_halalas_to_sar((int) $row['paid_halalas']),
                (string) (int) $row['paid_count'],
                (string) ($row['plan_code'] ?? ''),
                (string) (int) $row['credits_balance'],
                $disabled ? 'disabled' : 'active',
                (string) ($row['last_payment'] ?? ''),
            ]);
        }
    });
}
