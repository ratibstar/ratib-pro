<?php
declare(strict_types=1);

define('TENANT_REQUIRED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-worker-tracking-schema.php';
require_once __DIR__ . '/../../admin/core/EventBus.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function sos_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sos_token_required(): bool
{
    $v = getenv('TRACKING_REQUIRE_TOKEN');
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}

function sos_extract_token(): string
{
    $h1 = trim((string) ($_SERVER['HTTP_X_TRACKING_TOKEN'] ?? ''));
    if ($h1 !== '') return $h1;
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
    return '';
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        sos_json(['success' => false, 'message' => 'POST required'], 405);
    }
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        sos_json(['success' => false, 'message' => 'Invalid JSON body'], 422);
    }

    $tenantFromContext = (int) (TenantExecutionContext::getTenantId() ?? 0);
    $tenantFromHeader = (int) ($_SERVER['HTTP_X_TENANT_ID'] ?? 0);
    $tenantFromPost = (int) ($_POST['tenant_id'] ?? 0);
    $tenantFromGet = (int) ($_GET['tenant_id'] ?? 0);
    $tenantFromPayload = (int) ($payload['tenant_id'] ?? 0);
    $tenantId = $tenantFromContext > 0
        ? $tenantFromContext
        : ($tenantFromHeader > 0
            ? $tenantFromHeader
            : ($tenantFromPost > 0
                ? $tenantFromPost
                : ($tenantFromGet > 0 ? $tenantFromGet : $tenantFromPayload)));

    $workerId = (int) ($payload['worker_id'] ?? 0);
    $lat = isset($payload['lat']) ? (float) $payload['lat'] : null;
    $lng = isset($payload['lng']) ? (float) $payload['lng'] : null;
    if ($workerId <= 0 || $lat === null || $lng === null) {
        sos_json(['success' => false, 'message' => 'worker_id, lat, lng required'], 422);
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        sos_json(['success' => false, 'message' => 'Invalid lat/lng range'], 422);
    }
    $battery = isset($payload['battery']) ? max(0, min(100, (int) $payload['battery'])) : null;
    $message = trim((string) ($payload['message'] ?? ''));
    $deviceId = trim((string) ($payload['device_id'] ?? ''));
    $token = sos_extract_token();
    if (sos_token_required() && ($deviceId === '' || $token === '')) {
        sos_json(['success' => false, 'message' => 'device_id and token are required'], 401);
    }
    $recordedAt = date('Y-m-d H:i:s');

    $appPdo = Database::getInstance()->getConnection();
    if ($tenantFromContext > 0) {
        $workerSt = $appPdo->prepare("SELECT id FROM workers WHERE id = ? AND status != 'deleted' LIMIT 1");
        $workerSt->execute([$workerId]);
        if (!$workerSt->fetch(PDO::FETCH_ASSOC)) {
            sos_json(['success' => false, 'message' => 'Worker not found in current tenant context'], 404);
        }
    }

    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);
    if ($tenantId <= 0) {
        sos_json(['success' => false, 'error' => 'Tenant context missing', 'message' => 'Tenant context missing'], 400);
    }
    if ($deviceId !== '' || $token !== '') {
        $dev = $controlPdo->prepare(
            "SELECT id, api_token, is_active
             FROM worker_tracking_devices
             WHERE tenant_id = ? AND worker_id = ? AND device_id = ?
             LIMIT 1"
        );
        $dev->execute([$tenantId, $workerId, $deviceId]);
        $devRow = $dev->fetch(PDO::FETCH_ASSOC);
        if (!$devRow) {
            sos_json(['success' => false, 'message' => 'Unregistered device'], 401);
        }
        if ((int) ($devRow['is_active'] ?? 0) !== 1) {
            sos_json(['success' => false, 'message' => 'Device disabled'], 403);
        }
        $storedToken = (string) ($devRow['api_token'] ?? '');
        if (sos_token_required() && ($storedToken === '' || !hash_equals($storedToken, $token))) {
            sos_json(['success' => false, 'message' => 'Invalid token for device'], 401);
        }
    }
    $controlPdo->beginTransaction();
    try {
        $ins = $controlPdo->prepare(
            "INSERT INTO worker_locations
             (worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
        );
        $ins->execute([$workerId, $tenantId, $lat, $lng, null, null, 'alert', $battery, 'gps', $recordedAt]);

        $sess = $controlPdo->prepare(
            "INSERT INTO worker_tracking_sessions
             (worker_id, tenant_id, started_at, last_seen, status, last_lat, last_lng, last_speed, last_battery, last_source, updated_at)
             VALUES (?, ?, NOW(), ?, 'lost', ?, ?, NULL, ?, 'gps', NOW())
             ON DUPLICATE KEY UPDATE
                last_seen = VALUES(last_seen),
                status = 'lost',
                last_lat = VALUES(last_lat),
                last_lng = VALUES(last_lng),
                last_battery = VALUES(last_battery),
                last_source = 'gps',
                updated_at = NOW()"
        );
        $sess->execute([$workerId, $tenantId, $recordedAt, $lat, $lng, $battery]);

        emitEvent('WORKER_SOS', 'critical', 'Worker emergency triggered', [
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'lat' => $lat,
            'lng' => $lng,
            'battery' => $battery,
            'status' => 'alert',
            'source' => 'worker_tracking',
            'message' => $message,
            'duration_ms' => 0,
            'request_id' => getRequestId(),
        ]);
        $controlPdo->commit();
    } catch (Throwable $e) {
        if ($controlPdo->inTransaction()) {
            $controlPdo->rollBack();
        }
        throw $e;
    }

    sos_json([
        'success' => true,
        'message' => 'SOS received',
        'data' => ['worker_id' => $workerId, 'tenant_id' => $tenantId, 'status' => 'alert'],
    ]);
} catch (Throwable $e) {
    sos_json(['success' => false, 'message' => $e->getMessage()], 500);
}
