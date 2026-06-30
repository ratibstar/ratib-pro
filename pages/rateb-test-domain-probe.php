<?php
declare(strict_types=1);
/**
 * Step-by-step bootstrap probe for agency / test domains (plain-text diagnostics).
 * Open: https://test.rateb.sa/pages/rateb-test-domain-probe
 * Simulate Control "Open": add ?control=1&agency_id=33
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$lines = [];
$lines[] = 'RATEB domain probe';
$lines[] = 'PHP ' . PHP_VERSION;
$lines[] = 'HTTP_HOST=' . ($_SERVER['HTTP_HOST'] ?? '');
$lines[] = 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '');
$lines[] = 'control=' . ($_GET['control'] ?? '');
$lines[] = 'agency_id=' . ($_GET['agency_id'] ?? '');

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

    $probeAgencyId = isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])
        ? (int) $_GET['agency_id']
        : 0;
    if ($probeAgencyId > 0 && is_file($root . '/config/env/agency_lookup.php')) {
        require_once $root . '/config/env/agency_lookup.php';
        $agencyRow = rateb_lookup_agency_by_id($probeAgencyId);
        if (is_array($agencyRow)) {
            $lines[] = 'agency_row: id=' . ($agencyRow['id'] ?? '') . ' db_name=' . ($agencyRow['db_name'] ?? '');
            $lines[] = 'agency_site_url=' . ($agencyRow['site_url'] ?? '');
            $siteHost = @parse_url(trim((string) ($agencyRow['site_url'] ?? '')), PHP_URL_HOST);
            $reqHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
            $lines[] = 'site_url_host=' . ($siteHost ?: '(none)');
            $lines[] = 'site_url_matches_host=' . ($siteHost && strtolower((string) $siteHost) === $reqHost ? 'yes' : 'no');
        } else {
            $lines[] = 'agency_row: not found for id=' . $probeAgencyId;
        }
    }

    require_once __DIR__ . '/../includes/config.php';
    $lines[] = 'config: loaded';
    $lines[] = 'session_name=' . session_name();
    $conn = $GLOBALS['conn'] ?? null;
    if ($conn instanceof mysqli) {
        $tenantDb = $GLOBALS['agency_db']['db'] ?? (defined('DB_NAME') ? DB_NAME : '');
        $lines[] = 'mysqli: connected (' . $tenantDb . ')';
    } else {
        $lines[] = 'mysqli: missing (check DB credentials / agency db_name)';
    }
    if (function_exists('get_control_lookup_conn')) {
        $lc = get_control_lookup_conn();
        $lines[] = 'control_lookup: ' . ($lc instanceof mysqli ? 'ok' : 'null');
    }
    $lines[] = 'session_agency_id=' . (string) ($_SESSION['agency_id'] ?? '');
    $lines[] = 'session_logged_in=' . (!empty($_SESSION['logged_in']) ? 'yes' : 'no');
    $lines[] = 'control_logged_in=' . (!empty($_SESSION['control_logged_in']) ? 'yes' : 'no');
    $lines[] = 'OK';
} catch (Throwable $e) {
    $lines[] = 'EXCEPTION: ' . $e->getMessage();
    $lines[] = $e->getFile() . ':' . $e->getLine();
}

echo implode("\n", $lines) . "\n";
