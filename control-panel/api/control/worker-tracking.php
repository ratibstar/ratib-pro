<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../includes/control/country-program-scope.php';
require_once dirname(__DIR__, 3) . '/api/core/ensure-worker-tracking-schema.php';
require_once dirname(__DIR__, 3) . '/admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function control_tracking_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function control_tracking_query_row(PDO $pdo, string $sql): array
{
    $st = $pdo->query($sql);
    if (!$st) {
        return [];
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : [];
}

function control_tracking_query_count(PDO $pdo, string $sql): int
{
    $st = $pdo->query($sql);
    if (!$st) {
        return 0;
    }
    $val = $st->fetchColumn();
    return (int) ($val ?: 0);
}

/**
 * Distinct tenant IDs for control_agencies rows in the given countries (control DB).
 *
 * @param list<int> $countryIds
 * @return list<int>
 */
function control_tracking_tenant_ids_for_countries(PDO $controlPdo, array $countryIds): array
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
    $st = $controlPdo->query($sql);
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

function control_tracking_workers_table_exists(?mysqli $m): bool
{
    if (!($m instanceof mysqli)) {
        return false;
    }
    try {
        $r = $m->query("SHOW TABLES LIKE 'workers'");
        return $r && $r->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Open mysqli for one control_agencies row (tenant program DB with `workers`).
 */
function control_tracking_open_program_mysqli_from_agency_row(?array $row): ?mysqli
{
    if (!is_array($row) || ($row['db_name'] ?? '') === '') {
        return null;
    }
    $port = (int) ($row['db_port'] ?? 3306);
    $m = @new mysqli(
        (string) $row['db_host'],
        (string) $row['db_user'],
        (string) $row['db_pass'],
        (string) $row['db_name'],
        $port
    );
    if ($m->connect_errno !== 0) {
        return null;
    }
    $m->set_charset('utf8mb4');
    if (!control_tracking_workers_table_exists($m)) {
        $m->close();
        return null;
    }
    register_shutdown_function(static function () use ($m): void {
        try {
            $m->close();
        } catch (Throwable $e) {
            // ignore
        }
    });

    return $m;
}

/**
 * Cached mysqli for tenant's program DB (correct DB when several agencies share a country).
 */
function control_tracking_agency_mysqli_for_tenant(mysqli $control, int $tenantId): ?mysqli
{
    static $cache = [];
    if ($tenantId <= 0) {
        return null;
    }
    if (array_key_exists($tenantId, $cache)) {
        return $cache[$tenantId];
    }
    $cache[$tenantId] = null;
    try {
        $st = $control->prepare(
            "SELECT db_host, db_port, db_user, db_pass, db_name
             FROM control_agencies
             WHERE tenant_id = ? AND tenant_id IS NOT NULL AND tenant_id > 0
               AND is_active = 1 AND COALESCE(is_suspended, 0) = 0
             LIMIT 1"
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $tenantId);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
        $m = control_tracking_open_program_mysqli_from_agency_row(is_array($row) ? $row : null);
        $cache[$tenantId] = $m;

        return $m;
    } catch (Throwable $e) {
        error_log('control_tracking_agency_mysqli_for_tenant: ' . $e->getMessage());

        return null;
    }
}

/**
 * First active agency program DB for a country (fallback when tenant_id is unknown).
 */
function control_tracking_agency_mysqli_for_country(mysqli $control, int $countryId): ?mysqli
{
    static $cache = [];
    if ($countryId <= 0) {
        return null;
    }
    if (array_key_exists($countryId, $cache)) {
        return $cache[$countryId];
    }
    $cache[$countryId] = null;
    try {
        $st = $control->prepare(
            "SELECT db_host, db_port, db_user, db_pass, db_name
             FROM control_agencies
             WHERE country_id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0
             ORDER BY id ASC
             LIMIT 1"
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $countryId);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
        $m = control_tracking_open_program_mysqli_from_agency_row(is_array($row) ? $row : null);
        $cache[$countryId] = $m;

        return $m;
    } catch (Throwable $e) {
        error_log('control_tracking_agency_mysqli_for_country: ' . $e->getMessage());

        return null;
    }
}

/**
 * mysqli that has the Ratib Pro `workers` table (agency program DB).
 * Control panel often sets $GLOBALS['conn'] to control_panel DB only — no `workers` there.
 */
function control_tracking_workers_mysqli(): ?mysqli
{
    static $computed = false;
    static $mysqli = null;
    if ($computed) {
        return $mysqli;
    }
    $computed = true;
    $mysqli = null;

    $conn = $GLOBALS['conn'] ?? null;
    if ($conn instanceof mysqli && control_tracking_workers_table_exists($conn)) {
        $mysqli = $conn;

        return $mysqli;
    }

    $control = $GLOBALS['control_conn'] ?? null;
    $agencyId = isset($_SESSION['control_agency_id']) ? (int) $_SESSION['control_agency_id'] : 0;
    if ($control instanceof mysqli && $agencyId > 0) {
        try {
            $st = $control->prepare(
                "SELECT db_host, db_port, db_user, db_pass, db_name
                 FROM control_agencies
                 WHERE id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0
                 LIMIT 1"
            );
            if ($st) {
                $st->bind_param('i', $agencyId);
                $st->execute();
                $res = $st->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $st->close();
                $m = control_tracking_open_program_mysqli_from_agency_row(is_array($row) ? $row : null);
                if ($m instanceof mysqli) {
                    $mysqli = $m;

                    return $mysqli;
                }
            }
        } catch (Throwable $e) {
            error_log('control_tracking_workers_mysqli: ' . $e->getMessage());
        }
    }

    if ($control instanceof mysqli) {
        $allowed = control_country_program_allowed_country_ids($control);
        if ($allowed !== null && count($allowed) === 1) {
            $m = control_tracking_agency_mysqli_for_country($control, (int) $allowed[0]);
            if ($m instanceof mysqli) {
                $mysqli = $m;

                return $mysqli;
            }
        }
        $sessC = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
        if ($sessC > 0 && ($allowed === null || in_array($sessC, $allowed, true))) {
            $m = control_tracking_agency_mysqli_for_country($control, $sessC);
            if ($m instanceof mysqli) {
                $mysqli = $m;

                return $mysqli;
            }
        }
    }

    return $mysqli;
}

function control_tracking_run_worker_name_search_on_mysqli(mysqli $conn, string $search, int $limit): array
{
    try {
        $stmt = $conn->prepare(
            "SELECT id
             FROM workers
             WHERE status != 'deleted'
               AND (
                 worker_name LIKE ?
                 OR formatted_id LIKE ?
                 OR CAST(id AS CHAR) LIKE ?
                 OR COALESCE(identity_number, '') LIKE ?
               )
             ORDER BY id DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }
        $like = '%' . $search . '%';
        $safeLimit = max(1, min(1000, $limit));
        $stmt->bind_param('ssssi', $like, $like, $like, $like, $safeLimit);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($row = $res ? $res->fetch_assoc() : null) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $stmt->close();

        return $ids;
    } catch (Throwable $e) {
        error_log('control_tracking_run_worker_name_search_on_mysqli: ' . $e->getMessage());

        return [];
    }
}

/**
 * Which tenant program DBs to search for name/ID matches. null = single DB via control_tracking_workers_mysqli()
 * resolved to one tenant using DATABASE() vs control_agencies.db_name.
 *
 * @return list<int>|null
 */
function control_tracking_worker_search_tenant_ids(
    PDO $controlPdo,
    ?array $scopeCountryIds,
    ?array $scopeTenantIds,
    int $countryId,
    int $tenantId
): ?array {
    if ($tenantId > 0) {
        if (is_array($scopeTenantIds) && $scopeTenantIds !== [] && !in_array($tenantId, $scopeTenantIds, true)) {
            return [];
        }

        return [$tenantId];
    }
    if ($countryId > 0) {
        $ids = control_tracking_tenant_ids_for_countries($controlPdo, [$countryId]);
        if (is_array($scopeTenantIds) && $scopeTenantIds !== []) {
            return array_values(array_intersect($ids, $scopeTenantIds));
        }

        return $ids;
    }
    if (is_array($scopeTenantIds) && $scopeTenantIds !== []) {
        return $scopeTenantIds;
    }
    if ($scopeCountryIds === null) {
        return null;
    }

    return [];
}

/**
 * Per-tenant worker id lists for search OR — avoids merging numeric ids across different program DBs.
 *
 * @return list<array{tenant_id: int, worker_ids: list<int>}>
 */
function control_tracking_resolve_worker_search_groups(
    string $search,
    int $limit,
    PDO $controlPdo,
    ?mysqli $controlMysqli,
    ?array $scopeCountryIds,
    ?array $scopeTenantIds,
    int $countryId,
    int $tenantId
): array {
    $search = trim($search);
    if ($search === '' || !($controlMysqli instanceof mysqli)) {
        return [];
    }
    $tenantIdsToScan = control_tracking_worker_search_tenant_ids(
        $controlPdo,
        $scopeCountryIds,
        $scopeTenantIds,
        $countryId,
        $tenantId
    );
    if ($tenantIdsToScan === []) {
        return [];
    }
    $safeLimit = max(1, min(1000, $limit));

    if ($tenantIdsToScan === null) {
        $conn = control_tracking_workers_mysqli();
        if (!($conn instanceof mysqli)) {
            return [];
        }
        $ids = control_tracking_run_worker_name_search_on_mysqli($conn, $search, $safeLimit);
        if ($ids === []) {
            return [];
        }
        $dbName = '';
        $dbRow = $conn->query('SELECT DATABASE() AS dbn');
        if ($dbRow) {
            $dr = $dbRow->fetch_assoc();
            $dbName = (string) ($dr['dbn'] ?? '');
        }
        $tid = 0;
        if ($dbName !== '') {
            $st = $controlMysqli->prepare(
                'SELECT tenant_id FROM control_agencies WHERE db_name = ? AND tenant_id IS NOT NULL AND tenant_id > 0 LIMIT 1'
            );
            if ($st) {
                $st->bind_param('s', $dbName);
                $st->execute();
                $res = $st->get_result();
                $tr = $res ? $res->fetch_assoc() : null;
                $st->close();
                $tid = (int) ($tr['tenant_id'] ?? 0);
            }
        }
        if ($tid <= 0) {
            return [];
        }

        return [['tenant_id' => $tid, 'worker_ids' => $ids]];
    }

    $n = max(1, count($tenantIdsToScan));
    $perTenant = max(1, (int) ceil($safeLimit / $n));
    $out = [];
    foreach ($tenantIdsToScan as $scanTid) {
        $scanTid = (int) $scanTid;
        if ($scanTid <= 0) {
            continue;
        }
        $prog = control_tracking_agency_mysqli_for_tenant($controlMysqli, $scanTid);
        if (!($prog instanceof mysqli)) {
            continue;
        }
        $ids = control_tracking_run_worker_name_search_on_mysqli($prog, $search, $perTenant);
        if ($ids !== []) {
            $out[] = ['tenant_id' => $scanTid, 'worker_ids' => $ids];
        }
    }

    return $out;
}

function control_tracking_enrich_rows_with_worker_info(array $rows): array
{
    if ($rows === []) {
        return $rows;
    }
    $control = $GLOBALS['control_conn'] ?? null;

    $groups = [];
    foreach ($rows as $idx => $row) {
        $tid = (int) ($row['tenant_id'] ?? 0);
        $groups[$tid][] = $idx;
    }

    foreach ($groups as $tenantKey => $indices) {
        $conn = null;
        if ($control instanceof mysqli && $tenantKey > 0) {
            $conn = control_tracking_agency_mysqli_for_tenant($control, $tenantKey);
        }
        if (!($conn instanceof mysqli)) {
            $conn = control_tracking_workers_mysqli();
        }
        if (!($conn instanceof mysqli)) {
            foreach ($indices as $idx) {
                $rows[$idx]['worker_name'] = $rows[$idx]['worker_name'] ?? '';
                $rows[$idx]['formatted_id'] = $rows[$idx]['formatted_id'] ?? '';
                $rows[$idx]['worker_country'] = $rows[$idx]['worker_country'] ?? '';
            }

            continue;
        }
        $ids = [];
        foreach ($indices as $idx) {
            $wid = (int) ($rows[$idx]['worker_id'] ?? 0);
            if ($wid > 0) {
                $ids[$wid] = true;
            }
        }
        if ($ids === []) {
            continue;
        }
        $idSql = implode(',', array_map('intval', array_keys($ids)));
        if ($idSql === '') {
            continue;
        }
        try {
            $meta = [];
            $q = $conn->query(
                "SELECT id, worker_name, formatted_id, country, identity_number
                 FROM workers
                 WHERE status != 'deleted' AND id IN ({$idSql})"
            );
            if ($q) {
                while ($r = $q->fetch_assoc()) {
                    $wid = (int) ($r['id'] ?? 0);
                    if ($wid > 0) {
                        $meta[$wid] = [
                            'worker_name' => (string) ($r['worker_name'] ?? ''),
                            'formatted_id' => (string) ($r['formatted_id'] ?? ''),
                            'worker_country' => (string) ($r['country'] ?? ''),
                            'identity_number' => trim((string) ($r['identity_number'] ?? '')),
                        ];
                    }
                }
            }
            foreach ($indices as $idx) {
                $wid = (int) ($rows[$idx]['worker_id'] ?? 0);
                if ($wid > 0 && isset($meta[$wid])) {
                    $rows[$idx]['worker_name'] = $meta[$wid]['worker_name'];
                    $rows[$idx]['formatted_id'] = $meta[$wid]['formatted_id'];
                    $rows[$idx]['worker_country'] = $meta[$wid]['worker_country'];
                } else {
                    $rows[$idx]['worker_name'] = $rows[$idx]['worker_name'] ?? '';
                    $rows[$idx]['formatted_id'] = $rows[$idx]['formatted_id'] ?? '';
                    $rows[$idx]['worker_country'] = $rows[$idx]['worker_country'] ?? '';
                }
                $devIdent = trim((string) ($rows[$idx]['worker_identity'] ?? ''));
                if ($devIdent === '' && $wid > 0 && isset($meta[$wid]['identity_number']) && $meta[$wid]['identity_number'] !== '') {
                    $rows[$idx]['worker_identity'] = $meta[$wid]['identity_number'];
                }
            }
        } catch (Throwable $e) {
            error_log('control_tracking_enrich_rows_with_worker_info: ' . $e->getMessage());
        }
    }

    return $rows;
}

if (empty($_SESSION['control_logged_in'])) {
    control_tracking_json(['success' => false, 'message' => 'Unauthorized'], 401);
}

$canView = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('view_control_government')
    || hasControlPermission('gov_admin')
    || hasControlPermission(CONTROL_PERM_ADMINS);
if (!$canView) {
    control_tracking_json(['success' => false, 'message' => 'Access denied'], 403);
}
$canManage = hasControlPermission(CONTROL_PERM_GOVERNMENT)
    || hasControlPermission('manage_control_government')
    || hasControlPermission('gov_admin')
    || hasControlPermission(CONTROL_PERM_ADMINS);

try {
    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);

    $action = trim((string) ($_GET['action'] ?? 'latest'));
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 400;
    $limit = max(1, min(500, $limit));
    $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
    $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;
    $countryId = isset($_GET['country']) ? (int) $_GET['country'] : 0;
    $status = trim((string) ($_GET['status'] ?? ''));
    $search = trim((string) ($_GET['q'] ?? ''));

    $ctrlMysqli = $GLOBALS['control_conn'] ?? null;
    $scopeCountryIds = control_country_program_effective_scope_country_ids($ctrlMysqli instanceof mysqli ? $ctrlMysqli : null);
    /** @var list<int>|null $countryScopeIn */
    $countryScopeIn = null;
    if ($scopeCountryIds !== null) {
        if ($scopeCountryIds === []) {
            control_tracking_json(['success' => false, 'message' => 'No country access for this account'], 403);
        }
        if (count($scopeCountryIds) === 1) {
            $countryId = (int) $scopeCountryIds[0];
        } else {
            $pick = isset($_GET['country']) ? (int) $_GET['country'] : 0;
            if ($pick > 0 && in_array($pick, $scopeCountryIds, true)) {
                $countryId = $pick;
            } else {
                $sessC = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
                if ($sessC > 0 && in_array($sessC, $scopeCountryIds, true)) {
                    $countryId = $sessC;
                }
            }
            if ($countryId <= 0) {
                $countryScopeIn = $scopeCountryIds;
            }
        }
    }

    $scopeCountryIdList = null;
    if ($scopeCountryIds !== null) {
        if ($countryId > 0) {
            $scopeCountryIdList = [$countryId];
        } elseif (is_array($countryScopeIn) && $countryScopeIn !== []) {
            $scopeCountryIdList = $countryScopeIn;
        } else {
            $scopeCountryIdList = [];
        }
    }
    $scopeTenantIds = null;
    if (is_array($scopeCountryIdList)) {
        $scopeTenantIds = control_tracking_tenant_ids_for_countries($controlPdo, $scopeCountryIdList);
    }

    $joins = " LEFT JOIN control_agencies ca ON ca.tenant_id = s.tenant_id
               LEFT JOIN (
                   SELECT d.worker_id, d.tenant_id, MAX(d.id) AS latest_device_id
                   FROM worker_tracking_devices d
                   WHERE d.is_active = 1
                   GROUP BY d.worker_id, d.tenant_id
               ) dlast ON dlast.worker_id = s.worker_id AND dlast.tenant_id = s.tenant_id
               LEFT JOIN worker_tracking_devices d ON d.id = dlast.latest_device_id ";
    $where = ["1=1"];
    $params = [];
    if ($tenantId > 0) {
        $where[] = 's.tenant_id = ?';
        $params[] = $tenantId;
    }
    if ($agencyId > 0) {
        $where[] = 'ca.id = ?';
        $params[] = $agencyId;
    }
    if ($countryId > 0) {
        $where[] = 'ca.country_id = ?';
        $params[] = $countryId;
    } elseif (is_array($countryScopeIn) && $countryScopeIn !== []) {
        $marks = implode(',', array_fill(0, count($countryScopeIn), '?'));
        $where[] = 'ca.country_id IN (' . $marks . ')';
        foreach ($countryScopeIn as $cid) {
            $params[] = (int) $cid;
        }
    }
    if ($status !== '' && in_array($status, ['active', 'inactive', 'lost'], true)) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $searchGroups = control_tracking_resolve_worker_search_groups(
            $search,
            300,
            $controlPdo,
            $ctrlMysqli instanceof mysqli ? $ctrlMysqli : null,
            $scopeCountryIds,
            $scopeTenantIds,
            $countryId,
            $tenantId
        );
        $searchWhere = "(CAST(s.worker_id AS CHAR) LIKE ? OR CAST(s.tenant_id AS CHAR) LIKE ? OR ca.name LIKE ? OR COALESCE(d.worker_identity, '') LIKE ? OR COALESCE(d.device_id, '') LIKE ?)";
        if ($searchGroups !== []) {
            $parts = [];
            foreach ($searchGroups as $sg) {
                $wids = array_map('intval', $sg['worker_ids'] ?? []);
                $wids = array_values(array_filter($wids, static fn (int $x): bool => $x > 0));
                $tid = (int) ($sg['tenant_id'] ?? 0);
                if ($tid <= 0 || $wids === []) {
                    continue;
                }
                $inMarks = implode(',', array_fill(0, count($wids), '?'));
                $parts[] = "(s.tenant_id = ? AND s.worker_id IN ({$inMarks}))";
            }
            if ($parts !== []) {
                $searchWhere .= ' OR (' . implode(' OR ', $parts) . ')';
            }
        }
        $where[] = '(' . $searchWhere . ')';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        foreach ($searchGroups as $sg) {
            $wids = array_map('intval', $sg['worker_ids'] ?? []);
            $wids = array_values(array_filter($wids, static fn (int $x): bool => $x > 0));
            $tid = (int) ($sg['tenant_id'] ?? 0);
            if ($tid <= 0 || $wids === []) {
                continue;
            }
            $params[] = $tid;
            foreach ($wids as $wid) {
                $params[] = $wid;
            }
        }
    }

    if ($action === 'latest') {
        $sql = "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status,
                       s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery, s.last_source AS source,
                       d.worker_identity, d.device_id,
                       (
                           SELECT wl.status
                           FROM worker_locations wl
                           WHERE wl.worker_id = s.worker_id AND wl.tenant_id = s.tenant_id
                           ORDER BY wl.recorded_at DESC, wl.id DESC
                           LIMIT 1
                       ) AS location_status,
                       ca.id AS agency_id, ca.name AS agency_name, ca.country_id
                FROM worker_tracking_sessions s
                {$joins}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.last_seen DESC
                LIMIT {$limit}";
        $st = $controlPdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $rows = control_tracking_enrich_rows_with_worker_info($rows);
        control_tracking_json(['success' => true, 'data' => $rows]);
    }

    if ($action === 'alerts') {
        if (is_array($scopeTenantIds)) {
            if ($scopeTenantIds === []) {
                control_tracking_json(['success' => true, 'data' => []]);
            }
            $tMarks = implode(',', array_map('intval', $scopeTenantIds));
            $tenantFilter = " AND tenant_id IS NOT NULL AND tenant_id IN ({$tMarks})";
        } else {
            $tenantFilter = '';
        }
        $sql = "SELECT id, event_type, level, tenant_id, message, metadata, created_at
                FROM system_events
                WHERE (event_type IN ('WORKER_OFFLINE','WORKER_IDLE_ALERT','WORKER_ANOMALY','WORKER_SOS','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_SPOOF_SUSPECTED','WORKER_GPS_SPOOF_CONFIRMED','WORKER_ESCAPE_RISK','WORKER_ESCAPE_HIGH_RISK','WORKER_GEOFENCE_EXIT','WORKER_GEOFENCE_ENTER','WORKER_GEOFENCE_BREACH_PATTERN','WORKER_THREAT_ELEVATED','WORKER_THREAT_HIGH','WORKER_THREAT_CRITICAL','WORKER_RESPONSE_ACTION')
                       OR event_type LIKE 'WORKER_%'){$tenantFilter}
                ORDER BY id DESC
                LIMIT {$limit}";
        $st = $controlPdo->query($sql);
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as &$r) {
            $et = (string) ($r['event_type'] ?? '');
            $priority = 'low';
            if ($et === 'WORKER_SOS' || $et === 'WORKER_ESCAPE_HIGH_RISK' || $et === 'WORKER_GPS_SPOOF_CONFIRMED' || $et === 'WORKER_THREAT_CRITICAL') {
                $priority = 'critical';
            } elseif ($et === 'WORKER_ANOMALY' || $et === 'WORKER_FAKE_GPS' || $et === 'WORKER_GPS_SPOOF_DETECTED' || $et === 'WORKER_SPOOF_SUSPECTED' || $et === 'WORKER_GEOFENCE_EXIT' || $et === 'WORKER_GEOFENCE_BREACH_PATTERN' || $et === 'WORKER_THREAT_HIGH' || $et === 'WORKER_RESPONSE_ACTION') {
                $priority = 'high';
            } elseif ($et === 'WORKER_OFFLINE' || $et === 'WORKER_ESCAPE_RISK' || $et === 'WORKER_THREAT_ELEVATED') {
                $priority = 'medium';
            }
            $r['priority'] = $priority;
        }
        unset($r);
        usort($rows, static function (array $a, array $b): int {
            $rank = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $ra = $rank[$a['priority'] ?? 'low'] ?? 1;
            $rb = $rank[$b['priority'] ?? 'low'] ?? 1;
            if ($ra === $rb) {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            }
            return $rb <=> $ra;
        });
        control_tracking_json(['success' => true, 'data' => $rows]);
    }

    if ($action === 'history') {
        $workerId = isset($_GET['worker_id']) ? (int) $_GET['worker_id'] : 0;
        if ($workerId <= 0) {
            control_tracking_json(['success' => false, 'message' => 'worker_id required'], 422);
        }
        if (is_array($scopeTenantIds)) {
            if ($scopeTenantIds === []) {
                control_tracking_json(['success' => false, 'message' => 'No tenants in your country scope'], 403);
            }
            if ($tenantId > 0 && !in_array($tenantId, $scopeTenantIds, true)) {
                control_tracking_json(['success' => false, 'message' => 'tenant_id outside your country scope'], 403);
            }
            $ph = implode(',', array_fill(0, count($scopeTenantIds), '?'));
            $verifySql = "SELECT 1 FROM worker_tracking_sessions WHERE worker_id = ? AND tenant_id IN ({$ph}) LIMIT 1";
            $vSt = $controlPdo->prepare($verifySql);
            $vSt->execute(array_merge([$workerId], $scopeTenantIds));
            if (!$vSt->fetchColumn()) {
                control_tracking_json(['success' => false, 'message' => 'Worker not in your country scope'], 403);
            }
        }
        $sql = "SELECT id, worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at
                FROM worker_locations
                WHERE worker_id = ?" . ($tenantId > 0 ? " AND tenant_id = ?" : '') . "
                ORDER BY recorded_at DESC, id DESC
                LIMIT {$limit}";
        $st = $controlPdo->prepare($sql);
        if ($tenantId > 0) {
            $st->execute([$workerId, $tenantId]);
        } else {
            $st->execute([$workerId]);
        }
        $histRows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($scopeTenantIds) && $scopeTenantIds !== []) {
            $allowedSet = array_fill_keys($scopeTenantIds, true);
            $histRows = array_values(array_filter($histRows, static function (array $r) use ($allowedSet): bool {
                $t = (int) ($r['tenant_id'] ?? 0);

                return $t > 0 && isset($allowedSet[$t]);
            }));
        }
        control_tracking_json(['success' => true, 'data' => $histRows]);
    }

    if ($action === 'geofences') {
        $scopeGeoSql = '';
        if (is_array($scopeCountryIdList) && $scopeCountryIdList !== []) {
            $cm = implode(',', array_map('intval', $scopeCountryIdList));
            $scopeGeoSql = " AND (
                EXISTS (SELECT 1 FROM control_agencies ca WHERE ca.id = g.agency_id AND ca.country_id IN ({$cm}))
                OR EXISTS (SELECT 1 FROM control_agencies ca WHERE ca.tenant_id = g.tenant_id AND COALESCE(g.tenant_id, 0) > 0 AND ca.country_id IN ({$cm}))
            )";
        }
        $sql = "SELECT g.id, g.tenant_id, g.agency_id, g.name, g.center_lat, g.center_lng, g.radius_m, g.is_active, g.created_at,
                       COALESCE(SUM(CASE WHEN s.is_inside = 0 THEN 1 ELSE 0 END), 0) AS outside_count,
                       COALESCE(SUM(CASE WHEN s.is_inside = 1 THEN 1 ELSE 0 END), 0) AS inside_count
                FROM worker_geofences g
                LEFT JOIN worker_geofence_states s ON s.geofence_id = g.id
                WHERE g.is_active = 1
                  AND (:tenant_id_filter = 0 OR g.tenant_id = :tenant_id_value OR g.tenant_id IS NULL)
                  AND (:agency_id_filter = 0 OR g.agency_id = :agency_id_value OR g.agency_id IS NULL)
                  {$scopeGeoSql}
                GROUP BY g.id, g.tenant_id, g.agency_id, g.name, g.center_lat, g.center_lng, g.radius_m, g.is_active, g.created_at
                ORDER BY g.id DESC
                LIMIT {$limit}";
        $st = $controlPdo->prepare($sql);
        $st->bindValue(':tenant_id_filter', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':tenant_id_value', $tenantId, PDO::PARAM_INT);
        $st->bindValue(':agency_id_filter', $agencyId, PDO::PARAM_INT);
        $st->bindValue(':agency_id_value', $agencyId, PDO::PARAM_INT);
        $st->execute();
        control_tracking_json(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'create_geofence') {
        if (!$canManage || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            control_tracking_json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            control_tracking_json(['success' => false, 'message' => 'Invalid JSON'], 422);
        }
        $name = trim((string) ($payload['name'] ?? ''));
        $gTenant = isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : 0;
        $gAgency = isset($payload['agency_id']) ? (int) $payload['agency_id'] : 0;
        $lat = isset($payload['center_lat']) ? (float) $payload['center_lat'] : 0.0;
        $lng = isset($payload['center_lng']) ? (float) $payload['center_lng'] : 0.0;
        $radius = isset($payload['radius_m']) ? (int) $payload['radius_m'] : 0;
        if ($name === '' || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $radius < 20 || $radius > 500000) {
            control_tracking_json(['success' => false, 'message' => 'Invalid geofence payload'], 422);
        }
        if (is_array($scopeCountryIdList) && $scopeCountryIdList !== []) {
            $resolvedCid = 0;
            if ($gAgency > 0) {
                $ar = control_tracking_query_row(
                    $controlPdo,
                    'SELECT country_id FROM control_agencies WHERE id = ' . (int) $gAgency . ' LIMIT 1'
                );
                $resolvedCid = (int) ($ar['country_id'] ?? 0);
            } elseif ($gTenant > 0) {
                $ar = control_tracking_query_row(
                    $controlPdo,
                    'SELECT country_id FROM control_agencies WHERE tenant_id = ' . (int) $gTenant . ' LIMIT 1'
                );
                $resolvedCid = (int) ($ar['country_id'] ?? 0);
            }
            if ($resolvedCid <= 0 || !in_array($resolvedCid, $scopeCountryIdList, true)) {
                control_tracking_json(['success' => false, 'message' => 'Geofence must target an agency/tenant in your allowed countries'], 403);
            }
        }
        $ins = $controlPdo->prepare(
            "INSERT INTO worker_geofences
             (tenant_id, agency_id, name, center_lat, center_lng, radius_m, is_active, created_by, created_at, updated_at)
             VALUES (:tenant_id, :agency_id, :name, :center_lat, :center_lng, :radius_m, 1, :created_by, NOW(), NOW())"
        );
        $ins->bindValue(':tenant_id', $gTenant > 0 ? $gTenant : null, $gTenant > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ins->bindValue(':agency_id', $gAgency > 0 ? $gAgency : null, $gAgency > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ins->bindValue(':name', $name);
        $ins->bindValue(':center_lat', $lat);
        $ins->bindValue(':center_lng', $lng);
        $ins->bindValue(':radius_m', $radius, PDO::PARAM_INT);
        $ins->bindValue(':created_by', isset($_SESSION['control_user_id']) ? (int) $_SESSION['control_user_id'] : null, isset($_SESSION['control_user_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $ins->execute();
        control_tracking_json(['success' => true, 'message' => 'Geofence created', 'data' => ['id' => (int) $controlPdo->lastInsertId()]]);
    }

    if ($action === 'health') {
        $summary = [
            'sessions_total' => 0,
            'sessions_active' => 0,
            'sessions_inactive' => 0,
            'sessions_lost' => 0,
            'devices_total' => 0,
            'devices_active' => 0,
            'devices_seen_24h' => 0,
            'locations_24h' => 0,
            'sos_24h' => 0,
            'anomalies_24h' => 0,
        ];
        $scopedHealth = is_array($scopeCountryIdList) && $scopeCountryIdList !== [];
        $cmHealth = $scopedHealth ? implode(',', array_map('intval', $scopeCountryIdList)) : '';
        if (!$scopedHealth) {
            $row = control_tracking_query_row($controlPdo,
                "SELECT
                    COUNT(*) AS sessions_total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS sessions_active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS sessions_inactive,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) AS sessions_lost
                 FROM worker_tracking_sessions"
            );
            if (is_array($row)) {
                foreach ($summary as $k => $v) {
                    if (isset($row[$k])) {
                        $summary[$k] = (int) $row[$k];
                    }
                }
            }
            $rowDev = control_tracking_query_row($controlPdo,
                "SELECT
                    COUNT(*) AS devices_total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS devices_active,
                    SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS devices_seen_24h
                 FROM worker_tracking_devices"
            );
            if (is_array($rowDev)) {
                $summary['devices_total'] = (int) ($rowDev['devices_total'] ?? 0);
                $summary['devices_active'] = (int) ($rowDev['devices_active'] ?? 0);
                $summary['devices_seen_24h'] = (int) ($rowDev['devices_seen_24h'] ?? 0);
            }
            $summary['locations_24h'] = control_tracking_query_count(
                $controlPdo,
                "SELECT COUNT(*) FROM worker_locations WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $summary['sos_24h'] = control_tracking_query_count(
                $controlPdo,
                "SELECT COUNT(*) FROM system_events WHERE event_type = 'WORKER_SOS' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
            $summary['anomalies_24h'] = control_tracking_query_count(
                $controlPdo,
                "SELECT COUNT(*) FROM system_events
                 WHERE event_type IN ('WORKER_ANOMALY','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_GPS_SPOOF_CONFIRMED')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
        } else {
            $row = control_tracking_query_row($controlPdo,
                "SELECT
                    COUNT(*) AS sessions_total,
                    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS sessions_active,
                    SUM(CASE WHEN s.status = 'inactive' THEN 1 ELSE 0 END) AS sessions_inactive,
                    SUM(CASE WHEN s.status = 'lost' THEN 1 ELSE 0 END) AS sessions_lost
                 FROM worker_tracking_sessions s
                 INNER JOIN control_agencies ca ON ca.tenant_id = s.tenant_id
                 WHERE ca.country_id IN ({$cmHealth})"
            );
            if (is_array($row)) {
                foreach (['sessions_total', 'sessions_active', 'sessions_inactive', 'sessions_lost'] as $sk) {
                    if (isset($row[$sk])) {
                        $summary[$sk] = (int) $row[$sk];
                    }
                }
            }
            $rowDev = control_tracking_query_row($controlPdo,
                "SELECT
                    COUNT(*) AS devices_total,
                    SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) AS devices_active,
                    SUM(CASE WHEN d.last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS devices_seen_24h
                 FROM worker_tracking_devices d
                 INNER JOIN control_agencies ca ON ca.tenant_id = d.tenant_id
                 WHERE ca.country_id IN ({$cmHealth})"
            );
            if (is_array($rowDev)) {
                $summary['devices_total'] = (int) ($rowDev['devices_total'] ?? 0);
                $summary['devices_active'] = (int) ($rowDev['devices_active'] ?? 0);
                $summary['devices_seen_24h'] = (int) ($rowDev['devices_seen_24h'] ?? 0);
            }
            $summary['locations_24h'] = control_tracking_query_count(
                $controlPdo,
                "SELECT COUNT(*) FROM worker_locations wl
                 INNER JOIN control_agencies ca ON ca.tenant_id = wl.tenant_id
                 WHERE wl.recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                   AND ca.country_id IN ({$cmHealth})"
            );
            if (is_array($scopeTenantIds) && $scopeTenantIds !== []) {
                $tm = implode(',', array_map('intval', $scopeTenantIds));
                $summary['sos_24h'] = control_tracking_query_count(
                    $controlPdo,
                    "SELECT COUNT(*) FROM system_events WHERE event_type = 'WORKER_SOS'
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND tenant_id IS NOT NULL AND tenant_id IN ({$tm})"
                );
                $summary['anomalies_24h'] = control_tracking_query_count(
                    $controlPdo,
                    "SELECT COUNT(*) FROM system_events
                     WHERE event_type IN ('WORKER_ANOMALY','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_GPS_SPOOF_CONFIRMED')
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       AND tenant_id IS NOT NULL AND tenant_id IN ({$tm})"
                );
            }
        }

        $scopeCaSql = '';
        if ($countryId > 0) {
            $scopeCaSql = ' AND ca.country_id = ' . (int) $countryId;
        } elseif (is_array($countryScopeIn) && $countryScopeIn !== []) {
            $scopeCaSql = ' AND ca.country_id IN (' . implode(',', array_map('intval', $countryScopeIn)) . ')';
        }

        $latestSql = "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status,
                             s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery,
                             d.worker_identity, d.device_id,
                             ca.id AS agency_id, ca.name AS agency_name, ca.country_id
                      FROM worker_tracking_sessions s
                      LEFT JOIN control_agencies ca ON ca.tenant_id = s.tenant_id
                      LEFT JOIN (
                          SELECT worker_id, tenant_id, MAX(id) AS latest_device_id
                          FROM worker_tracking_devices
                          WHERE is_active = 1
                          GROUP BY worker_id, tenant_id
                      ) dlast ON dlast.worker_id = s.worker_id AND dlast.tenant_id = s.tenant_id
                      LEFT JOIN worker_tracking_devices d ON d.id = dlast.latest_device_id
                      WHERE 1=1{$scopeCaSql}
                      ORDER BY s.last_seen DESC
                      LIMIT 120";
        $stLatest = $controlPdo->query($latestSql);
        $latestRows = $stLatest ? $stLatest->fetchAll(PDO::FETCH_ASSOC) : [];
        $latestRows = control_tracking_enrich_rows_with_worker_info($latestRows);

        control_tracking_json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'latest' => $latestRows,
            ],
        ]);
    }

    control_tracking_json(['success' => false, 'message' => 'Unknown action'], 404);
} catch (Throwable $e) {
    control_tracking_json(['success' => false, 'message' => $e->getMessage()], 500);
}
