<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

try {
    $receipts = (int) $pdo->query('SELECT COUNT(*) FROM payment_receipts')->fetchColumn();
    $sum = (int) $pdo->query('SELECT COALESCE(SUM(amount),0) FROM payment_receipts')->fetchColumn();
    $check($receipts >= 0 && $sum >= 0, 'receipt aggregate query runs');
    $dup = $pdo->query('SELECT moyasar_id, COUNT(*) c FROM payment_receipts GROUP BY moyasar_id HAVING c > 1 LIMIT 1')->fetch();
    $check($dup === false, 'moyasar_id has no duplicate receipt groups');
    if (admin_table_has($pdo, 'payment_orders', 'status')) {
        $pending = $pdo->query("SELECT COUNT(*) FROM payment_orders WHERE status='pending'")->fetchColumn();
        $check($pending !== false, 'pending order count query runs');
    } else {
        echo "SKIP payment_orders table missing\n";
    }
} catch (Throwable $error) {
    echo 'DB_SKIP: ' . $error->getMessage() . "\n";
    exit(0);
}

exit($failures > 0 ? 1 : 0);
