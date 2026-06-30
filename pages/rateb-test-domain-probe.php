<?php
declare(strict_types=1);
/**
 * Step-by-step bootstrap probe for agency / test domains (plain-text diagnostics).
 * Open: https://test.rateb.sa/pages/rateb-test-domain-probe
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$lines = [];
$lines[] = 'RATEB domain probe';
$lines[] = 'PHP ' . PHP_VERSION;
$lines[] = 'HTTP_HOST=' . ($_SERVER['HTTP_HOST'] ?? '');
$lines[] = 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '');

$root = dirname(__DIR__);
$mustExist = [
    'core/TenantExecutionContext.php',
    'core/bootstrap.php',
    'app/Core/ErrorTracker.php',
    'config/env/test_rateb_sa.php',
];
foreach ($mustExist as $rel) {
    $p = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $lines[] = (is_file($p) ? 'file OK: ' : 'file MISSING: ') . $rel;
}

try {
    require_once __DIR__ . '/../config/env/load.php';
    $lines[] = 'env: loaded';
    $lines[] = 'DB_NAME=' . (defined('DB_NAME') ? DB_NAME : '(undefined)');
    $lines[] = 'SINGLE_URL_MODE=' . (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE ? 'yes' : 'no');
    $lines[] = 'SITE_URL=' . (defined('SITE_URL') ? SITE_URL : '(undefined)');
    $lines[] = 'RATEB_ERP_AGENCY_RESOLVED=' . (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED ? 'yes' : 'no');

    require_once __DIR__ . '/../includes/config.php';
    $lines[] = 'config: loaded';
    $conn = $GLOBALS['conn'] ?? null;
    if ($conn instanceof mysqli) {
        $lines[] = 'mysqli: connected (' . (defined('DB_NAME') ? DB_NAME : '') . ')';
    } else {
        $lines[] = 'mysqli: missing (check DB credentials / agency db_name)';
    }
    if (function_exists('get_control_lookup_conn')) {
        $lc = get_control_lookup_conn();
        $lines[] = 'control_lookup: ' . ($lc instanceof mysqli ? 'ok' : 'null');
    }
    $lines[] = 'OK';
} catch (Throwable $e) {
    $lines[] = 'EXCEPTION: ' . $e->getMessage();
    $lines[] = $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $lines) . "\n";
