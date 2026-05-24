<?php
declare(strict_types=1);

/**
 * Provision QR / barcode login schema on every active agency DB (all countries).
 *
 * CLI:  php scripts/provision-qr-login-all-agencies.php
 * Web:  /scripts/provision-qr-login-all-agencies.php?key=YOUR_CRON_KEY (if CRON_KEY defined)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (php_sapi_name() === 'cli') {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
}

require_once dirname(__DIR__) . '/config/env/load.php';

if (php_sapi_name() !== 'cli') {
    $key = defined('CRON_KEY') ? (string) CRON_KEY : '';
    if ($key === '' || !isset($_GET['key']) || !hash_equals($key, (string) $_GET['key'])) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once dirname(__DIR__) . '/includes/control_lookup_conn.php';
require_once dirname(__DIR__) . '/includes/ratib-qr-login.php';
require_once dirname(__DIR__) . '/includes/ratib-qr-workforce-identity.php';
require_once dirname(__DIR__) . '/includes/ratib-barcode-login-pair.php';

$lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
if (!($lookup instanceof mysqli)) {
    $lookup = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) (DB_PORT ?? 3306));
    if ($lookup->connect_error) {
        fwrite(STDERR, 'Control DB connection failed: ' . $lookup->connect_error . PHP_EOL);
        exit(1);
    }
    $lookup->set_charset('utf8mb4');
}

$helper = dirname(__DIR__) . '/control-panel/api/control/agency-db-helper.php';
if (is_readable($helper)) {
    require_once $helper;
}

echo "=== RATEB QR login provision (all agencies) ===\n\n";

if (function_exists('ratib_barcode_pair_ensure_table')) {
    if (ratib_barcode_pair_ensure_table($lookup)) {
        echo "[OK] control DB: login_barcode_pairs table ready\n";
    } else {
        echo "[WARN] control DB: could not ensure login_barcode_pairs\n";
    }
}

$sql = "SELECT a.id AS agency_id, a.name AS agency_name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name,
               c.name AS country_name, c.slug AS country_slug
        FROM control_agencies a
        LEFT JOIN control_countries c ON c.id = a.country_id
        WHERE a.is_active = 1
        ORDER BY c.name ASC, a.id ASC";
$res = $lookup->query($sql);
if (!$res) {
    fwrite(STDERR, "control_agencies query failed\n");
    exit(1);
}

$seenDb = [];
$ok = 0;
$skip = 0;
$fail = 0;

while ($row = $res->fetch_assoc()) {
    $agencyId = (int) ($row['agency_id'] ?? 0);
    $dbName = trim((string) ($row['db_name'] ?? ''));
    $countryName = trim((string) ($row['country_name'] ?? ''));
    $countrySlug = trim((string) ($row['country_slug'] ?? ''));
    $agencyLabel = trim((string) ($row['agency_name'] ?? '')) ?: ('Agency #' . $agencyId);
    $label = ($countryName !== '' ? $countryName : $countrySlug) . ' / ' . $agencyLabel;

    if ($dbName === '') {
        echo "[SKIP] $label — no db_name\n";
        $skip++;
        continue;
    }
    if (isset($seenDb[$dbName])) {
        echo "[SKIP] $label — same DB as earlier ($dbName)\n";
        $skip++;
        continue;
    }
    $seenDb[$dbName] = true;

    $tenantConn = null;
    if (function_exists('getAgencyDbConnection')) {
        $acct = getAgencyDbConnection($row, (int) ($row['country_id'] ?? 0));
        if ($acct && isset($acct['conn']) && $acct['conn'] instanceof mysqli) {
            $tenantConn = $acct['conn'];
        }
    }
    if (!$tenantConn) {
        $host = trim((string) ($row['db_host'] ?? '')) ?: DB_HOST;
        $port = (int) ($row['db_port'] ?? 0) ?: (int) (DB_PORT ?? 3306);
        $user = trim((string) ($row['db_user'] ?? '')) ?: DB_USER;
        $pass = (string) ($row['db_pass'] ?? DB_PASS);
        try {
            $tenantConn = new mysqli($host, $user, $pass, $dbName, $port);
            if ($tenantConn->connect_error) {
                throw new RuntimeException($tenantConn->connect_error);
            }
            $tenantConn->set_charset('utf8mb4');
            if (function_exists('ratib_ensure_minimal_ratib_pro_schema')) {
                ratib_ensure_minimal_ratib_pro_schema($tenantConn);
            }
        } catch (Throwable $e) {
            echo "[FAIL] $label ($dbName): " . $e->getMessage() . "\n";
            $fail++;
            continue;
        }
    }

    try {
        ratib_qr_login_ensure_schema($tenantConn);
        ratib_qr_workforce_ensure_schema($tenantConn);
        $loginUrl = $countrySlug !== ''
            ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'out.ratib.sa') . '/' . rawurlencode($countrySlug) . '/login'
            : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'out.ratib.sa') . '/pages/login.php?agency_id=' . $agencyId;
        echo "[OK] $label ($dbName)\n";
        echo "     Login: $loginUrl\n";
        $ok++;
    } catch (Throwable $e) {
        echo "[FAIL] $label ($dbName): schema — " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\nDone: $ok OK, $skip skipped, $fail failed\n";
echo "Next: In each country admin → Users → Access → Generate QR for each user.\n";
exit($fail > 0 ? 1 : 0);
