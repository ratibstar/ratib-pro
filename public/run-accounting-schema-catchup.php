<?php
/**
 * Idempotent column catch-up for enterprise accounting tables (admin_rateb).
 *
 * Auth: header X-Rateb-Migrate-Token
 * Usage: curl -H "X-Rateb-Migrate-Token: TOKEN" https://rateb.sa/public/run-accounting-schema-catchup.php
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
require_once dirname(__DIR__) . '/app/Accounting/Infrastructure/AccountingConnectionFactory.php';
require_once dirname(__DIR__) . '/app/Accounting/Infrastructure/AccountingSchemaCatchup.php';

use App\Accounting\Infrastructure\AccountingConnectionFactory;
use App\Accounting\Infrastructure\AccountingSchemaCatchup;

$pdo = AccountingConnectionFactory::pdo();
if ($pdo === null) {
    http_response_code(500);
    exit("Could not connect to enterprise accounting database\n");
}

AccountingSchemaCatchup::ensure($pdo);

$host = defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1';
$name = defined('DB_NAME') ? (string) DB_NAME : '';

echo "OK — schema catch-up on `{$name}` @ {$host}\n\n";

$check = [
    'accounting_drift_reports' => ['payload', 'period_from'],
    'accounting_reconciliation_reports' => ['payload', 'risk_level'],
    'accounting_events' => ['payload'],
    'accounting_trial_balance_snapshots' => ['payload'],
];

foreach ($check as $table => $cols) {
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    if (!$st || !$st->fetchColumn()) {
        echo "[MISSING TABLE] {$table}\n";
        continue;
    }
    foreach ($cols as $col) {
        $cst = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE :c');
        $cst->execute(['c' => $col]);
        echo ($cst->fetch() ? '[ok] ' : '[MISSING] ') . "{$table}.{$col}\n";
    }
}
