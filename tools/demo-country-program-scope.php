<?php
declare(strict_types=1);

/**
 * End-to-end walkthrough: country-scoped control panel vs global operator.
 *
 * Usage (from project root):
 *   php tools/demo-country-program-scope.php
 *
 * Requires: config/env.php (or .env) with DB_* and CONTROL_PANEL_DB_NAME when you want live DB output.
 */

$root = dirname(__DIR__);

if (!is_file($root . '/config/env.php')) {
    fwrite(STDERR, "Missing config/env.php — run from project root or configure env.\n");
    exit(1);
}

require_once $root . '/config/env.php';
require_once $root . '/control-panel/includes/control-permissions.php';
require_once $root . '/control-panel/includes/control/country-program-scope.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** PDO to control DB — same fallback as getControlDB() in admin/core/EventBus.php */
function demo_open_control_pdo(): ?PDO
{
    $dbName = defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== ''
        ? CONTROL_PANEL_DB_NAME
        : (defined('DB_NAME') ? DB_NAME : '');
    if ($dbName === '') {
        return null;
    }
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    if ($user === '') {
        return null;
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        return null;
    }
}

function print_header(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo $title . "\n";
    echo str_repeat('=', 60) . "\n";
}

/**
 * Same logic as control_tracking_tenant_ids_for_countries in worker-tracking.php (for demo only).
 *
 * @param list<int> $countryIds
 * @return list<int>
 */
function demo_tenant_ids_for_countries(PDO $pdo, array $countryIds): array
{
    $clean = [];
    foreach ($countryIds as $cid) {
        $id = (int) $cid;
        if ($id > 0) {
            $clean[] = $id;
        }
    }
    $countryIds = array_values(array_unique($clean));
    if ($countryIds === []) {
        return [];
    }
    $marks = implode(',', $countryIds);
    $sql = "SELECT DISTINCT tenant_id FROM control_agencies WHERE tenant_id IS NOT NULL AND tenant_id > 0 AND country_id IN ({$marks})";
    $st = $pdo->query($sql);
    if (!$st) {
        return [];
    }
    $out = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $t = (int) ($r['tenant_id'] ?? 0);
        if ($t > 0) {
            $out[] = $t;
        }
    }

    return array_values(array_unique($out));
}

print_header('RATIB — Country program scope demo');

echo <<<TXT

HOW THIS WORKS (short)
-----------------------
• Operator WITHOUT "control_select_country" but WITH "country_{slug}" only sees/manages those countries.
• Tracking API filters by country → tenant IDs so alerts, history, geofences, health match the same scope.
• Country Profiles API only saves slugs in that intersection.

MANUAL TEST IN THE BROWSER (recommended)
----------------------------------------
1) Create/edit a control admin with JSON permissions like:
   ["view_control_government","manage_control_government","country_indonesia"]
   (omit "control_select_country").
2) Log in as that admin. Open: Country program → Tracking Map / Tracking Health.
3) Confirm another country's sessions do not appear (use a super-admin account to compare).
4) Optional: grant ["control_select_country", ...] — operator can switch countries via Select Country.

TXT;

// --- Scenario A: country-only operator (Indonesia) ---
print_header('Scenario A — Simulated session: Indonesia-only operator');

$_SESSION['control_logged_in'] = true;
$_SESSION['control_username'] = 'demo_country_operator';
$_SESSION['control_permissions'] = [
    'view_control_government',
    'manage_control_government',
    'country_indonesia',
];
$_SESSION['control_country_id'] = 0;
$_SESSION['control_user_id'] = 0;

$dbNameCtrl = defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== ''
    ? CONTROL_PANEL_DB_NAME
    : (defined('DB_NAME') ? DB_NAME : '');
$pdo = null;
$mysqli = null;
if ($dbNameCtrl !== '') {
    $pdo = demo_open_control_pdo();
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbNameCtrl, (int) DB_PORT);
    if ($mysqli && !$mysqli->connect_error) {
        $mysqli->set_charset('utf8mb4');
        $GLOBALS['control_conn'] = $mysqli;
    } else {
        if ($mysqli && $mysqli->connect_error) {
            echo "Note: MySQLi connection failed — " . $mysqli->connect_error . " (permission demo still runs).\n\n";
        }
        $mysqli = null;
        $pdo = null;
    }
} else {
    echo "Note: DB_NAME / CONTROL_PANEL_DB_NAME not set — showing permission logic only (no country ID / tenant resolution).\n\n";
}

if (!$pdo instanceof PDO) {
    $pdo = null;
}

if ($mysqli === null) {
    echo "(Without DB: slug permission \"country_indonesia\" cannot map to numeric country_id — IDs stay empty until mysqli connects.)\n";
}

echo "control_country_program_can_operate_all_countries() → "
    . (control_country_program_can_operate_all_countries() ? 'true (unexpected)' : 'false') . "\n";
echo "control_country_program_allowed_country_ids() → "
    . json_encode(control_country_program_allowed_country_ids($mysqli instanceof mysqli ? $mysqli : null)) . "\n";
echo "control_country_program_allowed_slugs() → "
    . json_encode(control_country_program_allowed_slugs($mysqli instanceof mysqli ? $mysqli : null)) . "\n";
echo "control_country_profiles_can_edit() → "
    . (control_country_profiles_can_edit($mysqli instanceof mysqli ? $mysqli : null) ? 'true' : 'false') . "\n";

$allowedIds = control_country_program_allowed_country_ids($mysqli instanceof mysqli ? $mysqli : null);
if ($pdo instanceof PDO && is_array($allowedIds) && $allowedIds !== []) {
    $tenants = demo_tenant_ids_for_countries($pdo, $allowedIds);
    echo "Tenant IDs linked to those countries (used for alerts/history filter) → "
        . json_encode($tenants) . "\n";
} elseif ($mysqli === null && is_array($allowedIds)) {
    echo "Would resolve tenant IDs from control_agencies once DB credentials work.\n";
}

// --- Scenario B: global country switcher ---
print_header('Scenario B — Simulated session: operator with control_select_country');

$_SESSION['control_permissions'] = [
    'control_select_country',
    'view_control_government',
    'manage_control_government',
];

echo "control_country_program_can_operate_all_countries() → "
    . (control_country_program_can_operate_all_countries() ? 'true' : 'false') . "\n";
echo "control_country_program_allowed_country_ids() → "
    . json_encode(control_country_program_allowed_country_ids($mysqli instanceof mysqli ? $mysqli : null)) . "  (null = no API restriction)\n";
echo "control_country_program_allowed_slugs() → "
    . json_encode(control_country_program_allowed_slugs($mysqli instanceof mysqli ? $mysqli : null)) . "  (null = all registry slugs for profiles)\n";

if ($mysqli instanceof mysqli) {
    $mysqli->close();
}

// --- cURL examples ---
print_header('Optional: call tracking API as a logged-in control user');

$base = getenv('DEMO_CONTROL_BASE_URL');
if ($base === false || trim($base) === '') {
    $base = 'https://your-domain.example/control-panel';
}
echo <<<TXT

Set cookie ratib_control from your browser after login (DevTools → Application → Cookies),
or use browser-only testing instead.

Example (replace SESSION and host):

  curl -s -b "ratib_control=YOUR_SESSION_ID" \\
    "{$base}/api/control/worker-tracking.php?action=health&control=1"

Country-only operators get rows only for tenants in their allowed countries; global operators see all (unless they filter with ?country=).

Environment: set DEMO_CONTROL_BASE_URL in shell to print your real control-panel base in this message.

TXT;

echo "\nDone.\n";
