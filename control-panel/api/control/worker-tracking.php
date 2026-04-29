<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
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

    $workersTableExists = static function (?mysqli $m): bool {
        if (!($m instanceof mysqli)) {
            return false;
        }
        try {
            $r = $m->query("SHOW TABLES LIKE 'workers'");
            return $r && $r->num_rows > 0;
        } catch (Throwable $e) {
            return false;
        }
    };

    $conn = $GLOBALS['conn'] ?? null;
    if ($conn instanceof mysqli && $workersTableExists($conn)) {
        $mysqli = $conn;
        return $mysqli;
    }

    $control = $GLOBALS['control_conn'] ?? null;
    $agencyId = isset($_SESSION['control_agency_id']) ? (int) $_SESSION['control_agency_id'] : 0;
    if (!($control instanceof mysqli) || $agencyId <= 0) {
        return null;
    }
    try {
        $st = $control->prepare(
            "SELECT db_host, db_port, db_user, db_pass, db_name
             FROM control_agencies
             WHERE id = ? AND is_active = 1 AND COALESCE(is_suspended, 0) = 0
             LIMIT 1"
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $agencyId);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
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
        if (!$workersTableExists($m)) {
            $m->close();
            return null;
        }
        $mysqli = $m;
        register_shutdown_function(static function () use ($m): void {
            try {
                $m->close();
            } catch (Throwable $e) {
                // ignore
            }
        });
    } catch (Throwable $e) {
        error_log('control_tracking_workers_mysqli: ' . $e->getMessage());
    }

    return $mysqli;
}

function control_tracking_resolve_worker_ids_by_search(string $search, int $limit = 300): array
{
    $search = trim($search);
    if ($search === '') {
        return [];
    }
    $conn = control_tracking_workers_mysqli();
    if (!($conn instanceof mysqli)) {
        return [];
    }
    try {
        $stmt = $conn->prepare(
            "SELECT id
             FROM workers
             WHERE status != 'deleted'
               AND (
                 worker_name LIKE ?
                 OR formatted_id LIKE ?
                 OR CAST(id AS CHAR) LIKE ?
               )
             ORDER BY id DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }
        $like = '%' . $search . '%';
        $safeLimit = max(1, min(1000, $limit));
        $stmt->bind_param('sssi', $like, $like, $like, $safeLimit);
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
        error_log('control_tracking_resolve_worker_ids_by_search: ' . $e->getMessage());
        return [];
    }
}

function control_tracking_enrich_rows_with_worker_info(array $rows): array
{
    if ($rows === []) {
        return $rows;
    }
    $conn = control_tracking_workers_mysqli();
    if (!($conn instanceof mysqli)) {
        return $rows;
    }
    $ids = [];
    foreach ($rows as $row) {
        $wid = (int) ($row['worker_id'] ?? 0);
        if ($wid > 0) {
            $ids[$wid] = true;
        }
    }
    if ($ids === []) {
        return $rows;
    }
    $idList = array_keys($ids);
    $idSql = implode(',', array_map('intval', $idList));
    if ($idSql === '') {
        return $rows;
    }
    try {
        $meta = [];
        $q = $conn->query(
            "SELECT id, worker_name, formatted_id, country
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
                    ];
                }
            }
        }
        foreach ($rows as &$row) {
            $wid = (int) ($row['worker_id'] ?? 0);
            if ($wid > 0 && isset($meta[$wid])) {
                $row['worker_name'] = $meta[$wid]['worker_name'];
                $row['formatted_id'] = $meta[$wid]['formatted_id'];
                $row['worker_country'] = $meta[$wid]['worker_country'];
            } else {
                $row['worker_name'] = $row['worker_name'] ?? '';
                $row['formatted_id'] = $row['formatted_id'] ?? '';
                $row['worker_country'] = $row['worker_country'] ?? '';
            }
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) {
        error_log('control_tracking_enrich_rows_with_worker_info: ' . $e->getMessage());
        return $rows;
    }
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
    }
    if ($status !== '' && in_array($status, ['active', 'inactive', 'lost'], true)) {
        $where[] = 's.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $workerIdsByName = control_tracking_resolve_worker_ids_by_search($search);
        $searchWhere = "(CAST(s.worker_id AS CHAR) LIKE ? OR CAST(s.tenant_id AS CHAR) LIKE ? OR ca.name LIKE ? OR COALESCE(d.worker_identity, '') LIKE ? OR COALESCE(d.device_id, '') LIKE ?)";
        if ($workerIdsByName !== []) {
            $inMarks = implode(',', array_fill(0, count($workerIdsByName), '?'));
            $searchWhere .= " OR s.worker_id IN ({$inMarks})";
        }
        $where[] = '(' . $searchWhere . ')';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        foreach ($workerIdsByName as $wid) {
            $params[] = (int) $wid;
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
        $sql = "SELECT id, event_type, level, tenant_id, message, metadata, created_at
                FROM system_events
                WHERE (event_type IN ('WORKER_OFFLINE','WORKER_IDLE_ALERT','WORKER_ANOMALY','WORKER_SOS','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_SPOOF_SUSPECTED','WORKER_GPS_SPOOF_CONFIRMED','WORKER_ESCAPE_RISK','WORKER_ESCAPE_HIGH_RISK','WORKER_GEOFENCE_EXIT','WORKER_GEOFENCE_ENTER','WORKER_GEOFENCE_BREACH_PATTERN','WORKER_THREAT_ELEVATED','WORKER_THREAT_HIGH','WORKER_THREAT_CRITICAL','WORKER_RESPONSE_ACTION')
                       OR event_type LIKE 'WORKER_%')
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
        $sql = "SELECT id, worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at
                FROM worker_locations
                WHERE worker_id = ?" . ($tenantId > 0 ? " AND tenant_id = ?" : "") . "
                ORDER BY recorded_at DESC, id DESC
                LIMIT {$limit}";
        $st = $controlPdo->prepare($sql);
        if ($tenantId > 0) {
            $st->execute([$workerId, $tenantId]);
        } else {
            $st->execute([$workerId]);
        }
        control_tracking_json(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'geofences') {
        $sql = "SELECT g.id, g.tenant_id, g.agency_id, g.name, g.center_lat, g.center_lng, g.radius_m, g.is_active, g.created_at,
                       COALESCE(SUM(CASE WHEN s.is_inside = 0 THEN 1 ELSE 0 END), 0) AS outside_count,
                       COALESCE(SUM(CASE WHEN s.is_inside = 1 THEN 1 ELSE 0 END), 0) AS inside_count
                FROM worker_geofences g
                LEFT JOIN worker_geofence_states s ON s.geofence_id = g.id
                WHERE g.is_active = 1
                  AND (:tenant_id_filter = 0 OR g.tenant_id = :tenant_id_value OR g.tenant_id IS NULL)
                  AND (:agency_id_filter = 0 OR g.agency_id = :agency_id_value OR g.agency_id IS NULL)
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
                if (isset($row[$k])) $summary[$k] = (int) $row[$k];
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

        $latestSql = "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status,
                             s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery,
                             d.worker_identity, d.device_id,
                             ca.id AS agency_id, ca.name AS agency_name
                      FROM worker_tracking_sessions s
                      LEFT JOIN control_agencies ca ON ca.tenant_id = s.tenant_id
                      LEFT JOIN (
                          SELECT worker_id, tenant_id, MAX(id) AS latest_device_id
                          FROM worker_tracking_devices
                          WHERE is_active = 1
                          GROUP BY worker_id, tenant_id
                      ) dlast ON dlast.worker_id = s.worker_id AND dlast.tenant_id = s.tenant_id
                      LEFT JOIN worker_tracking_devices d ON d.id = dlast.latest_device_id
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
