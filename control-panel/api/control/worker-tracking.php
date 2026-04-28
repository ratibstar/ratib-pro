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

    $joins = " LEFT JOIN control_agencies ca ON ca.tenant_id = s.tenant_id ";
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

    if ($action === 'latest') {
        $sql = "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status,
                       s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery, s.last_source AS source,
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
        control_tracking_json(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
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

    control_tracking_json(['success' => false, 'message' => 'Unknown action'], 404);
} catch (Throwable $e) {
    control_tracking_json(['success' => false, 'message' => $e->getMessage()], 500);
}
