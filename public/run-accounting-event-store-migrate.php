<?php
/**
 * One-shot production migration: accounting_events / accounting_processed_events / accounting_audit_logs
 *
 * Auth: header X-Rateb-Migrate-Token (same token as rateb-erp/public/run-migrations.php)
 * Usage: curl -H "X-Rateb-Migrate-Token: YOUR_TOKEN" https://rateb.sa/public/run-accounting-event-store-migrate.php
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? $_GET['token'] ?? ''));
if ($provided === '') {
    http_response_code(403);
    exit("Forbidden — missing X-Rateb-Migrate-Token\n");
}

$expected = '';
$erpRoot = realpath(dirname(__DIR__) . '/rateb-erp');
if ($erpRoot !== false) {
    foreach (['/storage/deploy-migrate-token', '/storage/.deploy-migrate-token'] as $rel) {
        $tokenFile = $erpRoot . $rel;
        if (is_file($tokenFile)) {
            $expected = trim((string) file_get_contents($tokenFile));
            break;
        }
    }
}
if ($expected === '') {
    $fromEnv = getenv('RATEB_ERP_MIGRATE_TOKEN');
    if ($fromEnv !== false && $fromEnv !== '') {
        $expected = (string) $fromEnv;
    }
}
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once dirname(__DIR__) . '/config/env/load.php';

$host = defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1';
$user = defined('DB_USER') ? (string) DB_USER : '';
$pass = defined('DB_PASS') ? (string) DB_PASS : '';
$name = defined('DB_NAME') ? (string) DB_NAME : '';
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;

$sqlFile = dirname(__DIR__) . '/config/migrations/20260704_accounting_event_store.sql';
if (!is_file($sqlFile)) {
    http_response_code(500);
    exit("SQL file not found on server\n");
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Connection failed: ' . $e->getMessage() . "\n");
}

$sql = file_get_contents($sqlFile);
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', (string) $sql)));
$ok = 0;

foreach ($statements as $stmt) {
    if ($stmt === '' || strncmp($stmt, '--', 2) === 0) {
        continue;
    }
    $pdo->exec($stmt);
    $ok++;
}

echo "OK — applied {$ok} statement(s) on `{$name}` @ {$host}\n";

foreach (['accounting_events', 'accounting_processed_events', 'accounting_audit_logs'] as $t) {
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    echo ($st && $st->fetchColumn() ? '[exists] ' : '[MISSING] ') . $t . "\n";
}
