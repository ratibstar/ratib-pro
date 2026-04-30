<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/get-countries-with-login.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/get-countries-with-login.php`.
 */
/**
 * Control Panel API: Get all countries with login URLs and user counts.
 * For Super Admin - quick access to each country's login page.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
    && !hasControlPermission('view_control_country_users')
    && !hasControlPermission(CONTROL_PERM_AGENCIES)
    && !hasControlPermission('view_control_agencies')
    && !hasControlPermission('open_control_agency')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    echo json_encode(['success' => false, 'message' => 'Control database unavailable']);
    exit;
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);
$countryWhere = "c.is_active = 1";
if ($allowedCountryIds === []) {
    $countryWhere .= " AND 1=0";
} elseif ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $countryWhere .= " AND c.id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
}

$chkCountries = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
$chkAgencies = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
if (!$chkCountries || $chkCountries->num_rows === 0 || !$chkAgencies || $chkAgencies->num_rows === 0) {
    echo json_encode(['success' => true, 'countries' => []]);
    exit;
}

$sql = "SELECT c.id, c.name, c.slug FROM control_countries c WHERE $countryWhere ORDER BY c.sort_order ASC, c.name ASC";
$countriesRes = $ctrl->query($sql);
if (!$countriesRes) {
    echo json_encode(['success' => true, 'countries' => []]);
    exit;
}

$result = [];

while ($countryRow = $countriesRes->fetch_assoc()) {
    $countryId = (int)$countryRow['id'];
    $countryName = $countryRow['name'];
    $countrySlug = $countryRow['slug'];
    $loginUrl = '';
    $userCount = 0;
    $seenDbThisCountry = [];

    $agenciesRes = $ctrl->query("SELECT id, db_host, db_port, db_user, db_pass, db_name, site_url FROM control_agencies WHERE country_id = $countryId AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 10");
    if ($agenciesRes) {
        while ($agency = $agenciesRes->fetch_assoc()) {
            if (empty($loginUrl) && !empty($agency['site_url'])) {
                $loginUrl = rtrim($agency['site_url'], '/') . '/pages/login.php';
            }
            $dbKey = $agency['db_host'] . '|' . ($agency['db_name'] ?? '');
            if (isset($seenDbThisCountry[$dbKey])) continue;
            $seenDbThisCountry[$dbKey] = true;
            try {
                $port = (int)($agency['db_port'] ?? 3306);
                $conn = @new mysqli($agency['db_host'], $agency['db_user'], $agency['db_pass'], $agency['db_name'], $port);
                if ($conn && !$conn->connect_error) {
                    $conn->set_charset('utf8mb4');
                    $chk = $conn->query("SHOW TABLES LIKE 'users'");
                    if ($chk && $chk->num_rows > 0) {
                        $r = $conn->query("SELECT COUNT(*) as c FROM users");
                        if ($r) $userCount += (int)($r->fetch_assoc()['c'] ?? 0);
                    }
                    $conn->close();
                }
            } catch (Throwable $e) { /* skip */ }
        }
        $agenciesRes->free();
    }

    $result[] = [
        'id' => $countryId,
        'name' => $countryName,
        'slug' => $countrySlug,
        'users_count' => $userCount,
        'login_url' => $loginUrl,
    ];
}

if ($countriesRes) $countriesRes->free();

echo json_encode(['success' => true, 'countries' => $result]);
