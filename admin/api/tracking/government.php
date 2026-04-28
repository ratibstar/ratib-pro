<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}
require_once __DIR__ . '/../../core/ControlCenterAccess.php';
require_once __DIR__ . '/../../core/EventBus.php';
require_once __DIR__ . '/../../../api/core/ensure-worker-tracking-schema.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function gov_tracking_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!ControlCenterAccess::canAccessControlCenter()) {
    gov_tracking_json(['success' => false, 'message' => 'Forbidden'], 403);
}

$isSuper = ControlCenterAccess::role() === ControlCenterAccess::SUPER_ADMIN;
$isGov = false;
if (!empty($_SESSION['control_logged_in'])) {
    $perms = $_SESSION['control_permissions'] ?? [];
    $isGov = $perms === '*'
        || (is_array($perms) && (in_array('gov_admin', $perms, true)
            || in_array('control_government', $perms, true)
            || in_array('view_control_government', $perms, true)));
}
if (!$isSuper && !$isGov) {
    gov_tracking_json(['success' => false, 'message' => 'Requires SUPER_ADMIN or government role'], 403);
}

try {
    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);

    $action = trim((string) ($_GET['action'] ?? 'latest'));
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 400;
    $limit = max(1, min(500, $limit));

    if ($action === 'latest') {
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;
        $agencyId = isset($_GET['agency_id']) ? (int) $_GET['agency_id'] : 0;
        $countryId = isset($_GET['country']) ? (int) $_GET['country'] : 0;

        $where = ['1=1'];
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

        $sql = "SELECT s.worker_id, s.tenant_id, s.last_seen, s.status,
                       s.last_lat AS lat, s.last_lng AS lng, s.last_speed AS speed, s.last_battery AS battery,
                       (
                           SELECT wl.status
                           FROM worker_locations wl
                           WHERE wl.worker_id = s.worker_id AND wl.tenant_id = s.tenant_id
                           ORDER BY wl.recorded_at DESC, wl.id DESC
                           LIMIT 1
                       ) AS location_status,
                       ca.id AS agency_id, ca.name AS agency_name, ca.country_id
                FROM worker_tracking_sessions s
                LEFT JOIN control_agencies ca ON ca.tenant_id = s.tenant_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.last_seen DESC
                LIMIT {$limit}";
        $st = $controlPdo->prepare($sql);
        $st->execute($params);
        gov_tracking_json(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'alerts') {
        $sql = "SELECT id, event_type, level, tenant_id, message, metadata, created_at
                FROM system_events
                WHERE event_type IN ('WORKER_OFFLINE','WORKER_IDLE_ALERT','WORKER_ANOMALY','WORKER_SOS','WORKER_FAKE_GPS','WORKER_GPS_SPOOF_DETECTED','WORKER_SPOOF_SUSPECTED','WORKER_GPS_SPOOF_CONFIRMED','WORKER_ESCAPE_RISK','WORKER_ESCAPE_HIGH_RISK','WORKER_GEOFENCE_EXIT','WORKER_GEOFENCE_ENTER','WORKER_GEOFENCE_BREACH_PATTERN','WORKER_THREAT_ELEVATED','WORKER_THREAT_HIGH','WORKER_THREAT_CRITICAL','WORKER_RESPONSE_ACTION','WORKER_LOCATION_UPDATE')
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
        gov_tracking_json(['success' => true, 'data' => $rows]);
    }

    gov_tracking_json(['success' => false, 'message' => 'Unknown action'], 404);
} catch (Throwable $e) {
    gov_tracking_json(['success' => false, 'message' => $e->getMessage()], 500);
}
