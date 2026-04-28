<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/get-users-per-country.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/get-users-per-country.php`.
 */
/**
 * Control Panel API: Get users count per country.
 * Connects to each agency's database and counts users, grouped by country.
 */
header('Content-Type: application/json; charset=UTF-8');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/control-permissions.php';
    require_once __DIR__ . '/agency-db-helper.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Load error: ' . $e->getMessage()]);
    exit;
}

if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_COUNTRY_USERS)
    && !hasControlPermission('view_control_country_users')
    && !hasControlPermission(CONTROL_PERM_AGENCIES)
    && !hasControlPermission('view_control_agencies')
    && !hasControlPermission('open_control_agency')
    && !hasControlPermission('manage_control_users')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    echo json_encode(['success' => false, 'message' => 'Control database unavailable']);
    exit;
}

try {
$chkCountries = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if (!$chkCountries || $chkCountries->num_rows === 0) {
    echo json_encode(['success' => true, 'countries' => []]);
    exit;
}
$chkAgencies = $ctrl->query("SHOW TABLES LIKE 'control_agencies'");
// Still return countries even if control_agencies is missing (show "Add agencies" in UI)

$allowedCountryIds = getAllowedCountryIds($ctrl);
$countryWhere = "1=1";
$chkActive = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
if ($chkActive && $chkActive->num_rows > 0) {
    $countryWhere = "c.is_active = 1";
}
if ($allowedCountryIds === []) {
    $countryWhere .= " AND 1=0";
} elseif ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
    $countryWhere .= " AND c.id IN (" . implode(',', array_map('intval', $allowedCountryIds)) . ")";
}

$orderBy = 'c.name ASC';
$chkSort = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'sort_order'");
if ($chkSort && $chkSort->num_rows > 0) {
    $orderBy = 'c.sort_order ASC, c.name ASC';
}
$agencyHasActive = false;
$chkAgencyActive = $ctrl->query("SHOW COLUMNS FROM control_agencies LIKE 'is_active'");
if ($chkAgencyActive && $chkAgencyActive->num_rows > 0) {
    $agencyHasActive = true;
}
$sql = "SELECT c.id, c.name, c.slug FROM control_countries c WHERE $countryWhere ORDER BY $orderBy";
$countriesRes = $ctrl->query($sql);
if (!$countriesRes) {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $ctrl->error]);
    exit;
}

$result = [];
$seenSlugs = []; // Deduplicate countries (e.g. Sri Lanka with sri_lanka and sri-lanka) - value = result index

while ($countryRow = $countriesRes->fetch_assoc()) {
    $countryId = (int)$countryRow['id'];
    $countryName = $countryRow['name'];
    $countrySlug = $countryRow['slug'];
    $userCount = 0;
    $agencyId = null;
    $seenDbThisCountry = []; // Avoid double-count when one country has multiple agencies sharing same DB

    $agencyWhere = "country_id = $countryId";
    if ($agencyHasActive) $agencyWhere .= " AND is_active = 1";
    $agenciesRes = ($chkAgencies && $chkAgencies->num_rows > 0)
        ? $ctrl->query("SELECT id, db_host, db_port, db_user, db_pass, db_name FROM control_agencies WHERE $agencyWhere")
        : false;
    if ($agenciesRes) {
        while ($agency = $agenciesRes->fetch_assoc()) {
            if ($agencyId === null) $agencyId = (int)$agency['id'];
            $dbName = trim($agency['db_name'] ?? '');
            if (empty($dbName)) continue;
            $agency['country_slug'] = $countrySlug;
            try {
                $connResult = getAgencyDbConnection($agency, $countryId);
                if (!$connResult) continue;
                $conn = $connResult['conn'];
                $dbName = $connResult['db_name'];
                $useCountryFilter = $connResult['use_country_filter'];
                $dbKey = ($dbName === (defined('DB_NAME') ? DB_NAME : '')) ? '__main__|' . $dbName : (($agency['db_host'] ?? '') . '|' . $dbName);
                if (isset($seenDbThisCountry[$dbKey])) { $conn->close(); continue; }
                $seenDbThisCountry[$dbKey] = true;
                $chk = $conn->query("SHOW TABLES LIKE 'users'");
                if ($chk && $chk->num_rows > 0) {
                    $countSql = "SELECT COUNT(*) as c FROM users";
                    if ($useCountryFilter) {
                        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'country_id'");
                        if ($col && $col->num_rows > 0) $countSql .= " WHERE country_id = $countryId";
                    }
                    $r = $conn->query($countSql);
                    if ($r) {
                        $count = (int)($r->fetch_assoc()['c'] ?? 0);
                        $userCount += $count;
                    }
                }
                $conn->close();
            } catch (Throwable $e) {
                // Skip country DB if connection fails - continue with others
            }
        }
        $agenciesRes->free();
    }

    // Normalize slug for dedup (sri_lanka, sri-lanka, Sri Lanka -> srilanka)
    $slugNorm = preg_replace('/[-_\s]+/', '', strtolower($countrySlug));
    $nameNorm = preg_replace('/[-_\s]+/', '', strtolower($countryName));
    $dedupKey = $slugNorm . '|' . $nameNorm;
    if (isset($seenSlugs[$dedupKey])) {
        $idx = $seenSlugs[$dedupKey];
        if ($userCount > $result[$idx]['users_count']) {
            $result[$idx]['users_count'] = $userCount;
            $result[$idx]['agency_id'] = $agencyId;
        }
        continue;
    }
    $seenSlugs[$dedupKey] = count($result);

    $result[] = [
        'id' => $countryId,
        'name' => $countryName,
        'slug' => $countrySlug,
        'users_count' => $userCount,
        'agency_id' => $agencyId,
    ];
}

if ($countriesRes) $countriesRes->free();

echo json_encode(['success' => true, 'countries' => $result]);

} catch (Throwable $e) {
    $msg = $e->getMessage();
    echo json_encode(['success' => false, 'message' => $msg]);
}
