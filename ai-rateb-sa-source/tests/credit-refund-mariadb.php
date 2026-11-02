<?php
declare(strict_types=1);

/**
 * Two real PDO connections against a local MariaDB/MySQL. Refuses to run unless
 * 127.0.0.1 accepts a connection. It never uses production credentials.
 */
$host = getenv('RATEB_REFUND_TEST_HOST') ?: '127.0.0.1';
$port = getenv('RATEB_REFUND_TEST_PORT') ?: '3306';
$user = getenv('RATEB_REFUND_TEST_USER') ?: 'root';
$pass = getenv('RATEB_REFUND_TEST_PASSWORD') ?: '';
$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

if (($argv[1] ?? '') === 'child') {
    $pdo = new PDO($dsn . ';dbname=' . $argv[2], $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $GLOBALS['refund_pdo'] = $pdo;
    require_once __DIR__ . '/../app/plans.php';
    function db(): PDO
    {
        return $GLOBALS['refund_pdo'];
    }
    while (!is_file($argv[3])) {
        usleep(20000);
    }
    $ok = rateb_refund_credits(7, 'text_campaign', 3);
    file_put_contents($argv[4], $ok ? 'yes' : 'no');
    exit(0);
}

try {
    $admin = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
} catch (Throwable $error) {
    fwrite(STDERR, "MariaDB test NOT RUN: no local server at {$host}:{$port}\n");
    exit(2);
}

$name = 'rateb_refund_' . getmypid();
$admin->exec("CREATE DATABASE `{$name}`");
$admin->exec("USE `{$name}`");
$admin->exec('CREATE TABLE users (id INT PRIMARY KEY, credits_balance INT NOT NULL) ENGINE=InnoDB');
$admin->exec("CREATE TABLE usage_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    campaign_id INT NULL,
    operation VARCHAR(64) NOT NULL,
    units INT NOT NULL DEFAULT 1,
    status VARCHAR(16) NOT NULL,
    event_type VARCHAR(64) NULL,
    credits_deducted INT NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(64) NULL,
    UNIQUE KEY idempotency_key (idempotency_key)
) ENGINE=InnoDB");
$admin->exec('INSERT INTO users (id, credits_balance) VALUES (7, 10)');
$admin->exec("INSERT INTO usage_events (user_id, campaign_id, operation, status, event_type, credits_deducted) VALUES (7, 3, 'text_campaign', 'completed', 'text_campaign', 5)");
$admin->exec("INSERT INTO usage_events (user_id, campaign_id, operation, status, event_type, credits_deducted) VALUES (7, 3, 'text_campaign', 'completed', 'text_campaign', 5)");

$gate = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.gate';
$outA = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.a';
$outB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.b';
@unlink($gate);
@unlink($outA);
@unlink($outB);
$procs = [];
foreach ([$outA, $outB] as $out) {
    $pipes = [];
    $proc = proc_open([PHP_BINARY, __FILE__, 'child', $name, $gate, $out], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $procs[] = [$proc, $pipes];
}
usleep(300000);
file_put_contents($gate, 'go');
foreach ($procs as [$proc, $pipes]) {
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if (is_resource($proc)) {
        proc_close($proc);
    }
}
$yes = 0;
foreach ([$outA, $outB] as $out) {
    if (is_file($out) && trim((string) file_get_contents($out)) === 'yes') {
        $yes++;
    }
}
$balance = (int) $admin->query('SELECT credits_balance FROM users WHERE id = 7')->fetchColumn();
$refunds = (int) $admin->query("SELECT COUNT(*) FROM usage_events WHERE operation = 'text_campaign_refund'")->fetchColumn();
$oldestStillCharged = (int) $admin->query("SELECT COUNT(*) FROM usage_events WHERE operation = 'text_campaign' AND credits_deducted > 0 AND id NOT IN (SELECT CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(idempotency_key, ':', 2), ':', -1) AS UNSIGNED) FROM usage_events WHERE operation = 'text_campaign_refund')")->fetchColumn();
$ok = $yes === 1 && $balance === 15 && $refunds === 1;
echo ($ok ? 'PASS' : 'FAIL') . " concurrent refunds of the newest charge increase the balance once (yes={$yes} balance={$balance} refunds={$refunds})\n";
$admin->exec("DROP DATABASE `{$name}`");
@unlink($gate);
@unlink($outA);
@unlink($outB);
exit($ok ? 0 : 1);
