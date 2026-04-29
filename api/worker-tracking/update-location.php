<?php
declare(strict_types=1);

define('TENANT_REQUIRED', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-worker-tracking-schema.php';
require_once __DIR__ . '/../../admin/core/EventBus.php';
require_once __DIR__ . '/../../admin/core/AntiSpoofAdvancedEngine.php';
require_once __DIR__ . '/../../admin/core/GeofenceEngine.php';
require_once __DIR__ . '/../../admin/core/WorkerEscapePredictionEngine.php';
require_once __DIR__ . '/../../admin/core/WorkerThreatFusionEngine.php';
require_once __DIR__ . '/../../admin/core/WorkerResponseOrchestrator.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

function tracking_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tracking_haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function tracking_parse_ts(?string $raw): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return date('Y-m-d H:i:s');
    }
    $t = strtotime($raw);
    if ($t === false) {
        return date('Y-m-d H:i:s');
    }
    return date('Y-m-d H:i:s', $t);
}

function tracking_mobile_token_required(): bool
{
    $v = getenv('TRACKING_REQUIRE_TOKEN');
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}

function tracking_single_device_lock(): bool
{
    $v = getenv('TRACKING_SINGLE_DEVICE_ENFORCED');
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}

function tracking_extract_token(): string
{
    $h1 = trim((string) ($_SERVER['HTTP_X_TRACKING_TOKEN'] ?? ''));
    if ($h1 !== '') {
        return $h1;
    }
    $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return '';
}

function tracking_event_recent(PDO $controlPdo, string $eventType, int $workerId, int $minutes): bool
{
    $minutes = max(1, (int) $minutes);
    $sql = "SELECT id FROM system_events
            WHERE event_type = :et
              AND metadata LIKE :metaLike
              AND created_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)
            ORDER BY id DESC
            LIMIT 1";
    $st = $controlPdo->prepare($sql);
    $st->bindValue(':et', $eventType);
    $st->bindValue(':metaLike', '%"worker_id":' . $workerId . '%');
    $st->execute();
    return (bool) $st->fetchColumn();
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        tracking_json(['success' => false, 'message' => 'POST required'], 405);
    }

    $raw = (string) file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        tracking_json(['success' => false, 'message' => 'Invalid JSON body'], 422);
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
    if ($workerId <= 0) {
        tracking_json(['success' => false, 'message' => 'worker_id is required'], 422);
    }

    $appPdo = Database::getInstance()->getConnection();

    if ($tenantFromContext > 0) {
        $workerSt = $appPdo->prepare("SELECT id FROM workers WHERE id = ? AND status != 'deleted' LIMIT 1");
        $workerSt->execute([$workerId]);
        if (!$workerSt->fetch(PDO::FETCH_ASSOC)) {
            tracking_json(['success' => false, 'message' => 'Worker not found in current tenant context'], 404);
        }
    }

    $controlPdo = getControlDB();
    ratibEnsureWorkerTrackingSchema($controlPdo);

    $deviceId = trim((string) ($payload['device_id'] ?? ''));
    $token = tracking_extract_token();
    if ($tenantId <= 0) {
        tracking_json(['success' => false, 'error' => 'Tenant context missing', 'message' => 'Tenant context missing'], 400);
    }
    if (tracking_mobile_token_required()) {
        if ($deviceId === '' || $token === '') {
            tracking_json(['success' => false, 'message' => 'device_id and token are required'], 401);
        }
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
            if (tracking_mobile_token_required()) {
                tracking_json(['success' => false, 'message' => 'Unregistered device'], 401);
            }
            $insDev = $controlPdo->prepare(
                "INSERT INTO worker_tracking_devices (worker_id, tenant_id, device_id, api_token, is_active, last_seen, created_at, updated_at)
                 VALUES (?,?,?,?,1,NOW(),NOW(),NOW())"
            );
            $insDev->execute([$workerId, $tenantId, $deviceId === '' ? 'unknown-device' : $deviceId, $token === '' ? null : $token]);
        } else {
            if ((int) ($devRow['is_active'] ?? 0) !== 1) {
                tracking_json(['success' => false, 'message' => 'Device disabled'], 403);
            }
            $storedToken = (string) ($devRow['api_token'] ?? '');
            if (tracking_mobile_token_required() && ($storedToken === '' || !hash_equals($storedToken, $token))) {
                tracking_json(['success' => false, 'message' => 'Invalid token for device'], 401);
            }
            $touchDev = $controlPdo->prepare(
                "UPDATE worker_tracking_devices SET last_seen = NOW(), updated_at = NOW(), api_token = COALESCE(?, api_token)
                 WHERE id = ?"
            );
            $touchDev->execute([$token === '' ? null : $token, (int) $devRow['id']]);
        }
        if (tracking_single_device_lock()) {
            $lock = $controlPdo->prepare(
                "SELECT COUNT(*) FROM worker_tracking_devices
                 WHERE tenant_id = ? AND worker_id = ? AND is_active = 1 AND device_id <> ?"
            );
            $lock->execute([$tenantId, $workerId, $deviceId]);
            if ((int) $lock->fetchColumn() > 0) {
                tracking_json(['success' => false, 'message' => 'Multiple devices blocked for this worker'], 409);
            }
        }
    }

    $isBatch = !empty($payload['is_offline_batch']) && is_array($payload['locations'] ?? null);
    $networkType = trim((string) ($payload['network_type'] ?? ''));
    $source = strtolower(trim((string) ($payload['source'] ?? 'gps')));
    if (!in_array($source, ['gps', 'network', 'cached'], true)) {
        $source = 'gps';
    }
    $battery = isset($payload['battery']) ? (int) $payload['battery'] : null;
    if ($battery !== null) {
        $battery = max(0, min(100, $battery));
    }

    $points = [];
    if ($isBatch) {
        foreach ((array) $payload['locations'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lng = isset($row['lng']) ? (float) $row['lng'] : null;
            if ($lat === null || $lng === null) {
                continue;
            }
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }
            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => isset($row['accuracy']) ? (float) $row['accuracy'] : (isset($payload['accuracy']) ? (float) $payload['accuracy'] : null),
                'speed' => isset($row['speed']) ? (float) $row['speed'] : null,
                'battery' => isset($row['battery']) ? (int) $row['battery'] : $battery,
                'recorded_at' => tracking_parse_ts(isset($row['timestamp']) ? (string) $row['timestamp'] : null),
                'source' => isset($row['source']) ? strtolower(trim((string) $row['source'])) : $source,
            ];
        }
    } else {
        $lat = isset($payload['lat']) ? (float) $payload['lat'] : null;
        $lng = isset($payload['lng']) ? (float) $payload['lng'] : null;
        if ($lat === null || $lng === null) {
            tracking_json(['success' => false, 'message' => 'lat/lng are required'], 422);
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            tracking_json(['success' => false, 'message' => 'Invalid lat/lng range'], 422);
        }
        $points[] = [
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => isset($payload['accuracy']) ? (float) $payload['accuracy'] : null,
            'speed' => isset($payload['speed']) ? (float) $payload['speed'] : null,
            'battery' => $battery,
            'recorded_at' => tracking_parse_ts(isset($payload['timestamp']) ? (string) $payload['timestamp'] : null),
            'source' => $source,
        ];
    }

    if ($points === []) {
        tracking_json(['success' => false, 'message' => 'No valid location points provided'], 422);
    }

    $controlPdo->beginTransaction();
    try {
        $lastKnownStmt = $controlPdo->prepare(
            "SELECT lat, lng, recorded_at, status
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 1"
        );
        $lastKnownStmt->execute([$workerId, $tenantId]);
        $prev = $lastKnownStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $ins = $controlPdo->prepare(
            "INSERT INTO worker_locations
             (worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at)
             VALUES (:worker_id, :tenant_id, :lat, :lng, :accuracy, :speed, :status, :battery, :source, :recorded_at, NOW())"
        );

        $latest = null;
        foreach ($points as $point) {
            $lat = (float) $point['lat'];
            $lng = (float) $point['lng'];
            $speed = isset($point['speed']) ? (float) $point['speed'] : null;
            $recordedAt = (string) $point['recorded_at'];
            $status = 'idle';
            if ($speed !== null && $speed > 1.2) {
                $status = 'moving';
            }
            $accuracy = isset($point['accuracy']) ? (float) $point['accuracy'] : null;
            if ($prev && isset($prev['lat'], $prev['lng'], $prev['recorded_at'])) {
                $dist = tracking_haversine_km((float) $prev['lat'], (float) $prev['lng'], $lat, $lng);
                $prevTs = strtotime((string) $prev['recorded_at']) ?: 0;
                $curTs = strtotime($recordedAt) ?: 0;
                $deltaSecs = max(1, $curTs - $prevTs);
                if (($speed === null || $speed <= 0.1) && $dist < 0.02 && $deltaSecs >= 300) {
                    $status = 'idle';
                } elseif ($dist >= 0.02) {
                    $status = 'moving';
                }
            }

            $src = in_array((string) $point['source'], ['gps', 'network', 'cached'], true) ? (string) $point['source'] : 'gps';
            $pointBattery = isset($point['battery']) ? max(0, min(100, (int) $point['battery'])) : null;

            $ins->execute([
                ':worker_id' => $workerId,
                ':tenant_id' => $tenantId,
                ':lat' => $lat,
                ':lng' => $lng,
                ':accuracy' => $accuracy,
                ':speed' => $speed,
                ':status' => $status,
                ':battery' => $pointBattery,
                ':source' => $src,
                ':recorded_at' => $recordedAt,
            ]);

            $prev = ['lat' => $lat, 'lng' => $lng, 'recorded_at' => $recordedAt, 'status' => $status];
            $latest = [
                'lat' => $lat,
                'lng' => $lng,
                'status' => $status,
                'battery' => $pointBattery,
                'accuracy' => $point['accuracy'] ?? null,
                'speed' => $speed,
                'recorded_at' => $recordedAt,
                'source' => $src,
            ];
        }

        if ($latest === null) {
            throw new RuntimeException('No location stored');
        }

        $sessionStatus = 'active';
        if ($latest['status'] === 'idle') {
            $sessionStatus = 'inactive';
        } elseif ($latest['status'] === 'alert') {
            $sessionStatus = 'lost';
        }

        $sess = $controlPdo->prepare(
            "INSERT INTO worker_tracking_sessions
             (worker_id, tenant_id, started_at, last_seen, status, last_lat, last_lng, last_speed, last_battery, last_source, updated_at)
             VALUES (:worker_id, :tenant_id, NOW(), :last_seen, :status, :last_lat, :last_lng, :last_speed, :last_battery, :last_source, NOW())
             ON DUPLICATE KEY UPDATE
                last_seen = VALUES(last_seen),
                status = VALUES(status),
                last_lat = VALUES(last_lat),
                last_lng = VALUES(last_lng),
                last_speed = VALUES(last_speed),
                last_battery = VALUES(last_battery),
                last_source = VALUES(last_source),
                updated_at = NOW()"
        );
        $sess->execute([
            ':worker_id' => $workerId,
            ':tenant_id' => $tenantId,
            ':last_seen' => $latest['recorded_at'],
            ':status' => $sessionStatus,
            ':last_lat' => $latest['lat'],
            ':last_lng' => $latest['lng'],
            ':last_speed' => $latest['speed'],
            ':last_battery' => $latest['battery'],
            ':last_source' => $latest['source'],
        ]);

        $agencyId = null;
        $agencySt = $controlPdo->prepare("SELECT id FROM control_agencies WHERE tenant_id = ? ORDER BY id ASC LIMIT 1");
        $agencySt->execute([$tenantId]);
        $agencyIdRaw = $agencySt->fetchColumn();
        if ($agencyIdRaw !== false) {
            $agencyId = (int) $agencyIdRaw;
        }
        // Intelligence execution order:
        // 1) Advanced anti-spoofing
        // 2) Geofence intelligence
        // 3) Escape prediction
        // 4) Threat fusion
        //
        // Fail-safe: intelligence errors must not block core location sync.
        $spoofAdv = ['spoof_score' => 0];
        $geo = ['status' => 'none', 'outside' => [], 'details' => []];
        $escape = ['risk_score' => 0, 'escape_risk_level' => 'normal'];
        $threatFusion = ['final_threat_score' => 0, 'threat_level' => 'NORMAL'];
        $responseAction = ['action_type' => 'NONE', 'priority' => 'LOW', 'auto_focus_map' => false];
        try {
            $spoofAdv = AntiSpoofAdvancedEngine::evaluate($controlPdo, $workerId, $tenantId, $latest, $networkType);
            if (((int) ($spoofAdv['spoof_score'] ?? 0)) >= 80) {
                $latest['status'] = 'alert';
                $controlPdo->prepare(
                    "UPDATE worker_locations
                     SET status = 'alert'
                     WHERE tenant_id = ? AND worker_id = ? AND recorded_at = ?"
                )->execute([$tenantId, $workerId, $latest['recorded_at']]);
                $controlPdo->prepare(
                    "UPDATE worker_tracking_sessions
                     SET status = 'lost', updated_at = NOW()
                     WHERE worker_id = ? AND tenant_id = ?"
                )->execute([$workerId, $tenantId]);
            }

            $geo = GeofenceEngine::evaluateLocation($controlPdo, $workerId, $tenantId, $agencyId, (float) $latest['lat'], (float) $latest['lng']);
            if (($geo['status'] ?? '') === 'outside') {
                $latest['status'] = 'alert';
                $controlPdo->prepare(
                    "UPDATE worker_locations
                     SET status = 'alert'
                     WHERE tenant_id = ? AND worker_id = ? AND recorded_at = ?"
                )->execute([$tenantId, $workerId, $latest['recorded_at']]);
                $controlPdo->prepare(
                    "UPDATE worker_tracking_sessions
                     SET status = 'lost', updated_at = NOW()
                     WHERE worker_id = ? AND tenant_id = ?"
                )->execute([$workerId, $tenantId]);
            }

            $escape = WorkerEscapePredictionEngine::evaluate($controlPdo, $workerId, $tenantId, $latest);
            $threatFusion = WorkerThreatFusionEngine::evaluate(
                $controlPdo,
                $workerId,
                $tenantId,
                (string) $latest['recorded_at'],
                $escape,
                $spoofAdv,
                $geo
            );
        } catch (Throwable $intelEx) {
            emitEvent('WORKER_INTELLIGENCE_ERROR', 'warn', 'Location stored but intelligence engine failed', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'source' => 'worker_tracking',
                'error' => $intelEx->getMessage(),
                'request_id' => getRequestId(),
            ]);
        }
        $geoHasBreachPattern = false;
        if (isset($geo['details']) && is_array($geo['details'])) {
            foreach ($geo['details'] as $d) {
                if (!empty($d['breach_pattern'])) {
                    $geoHasBreachPattern = true;
                    break;
                }
            }
        }
        try {
            $responseAction = WorkerResponseOrchestrator::evaluate(
                $controlPdo,
                $workerId,
                $tenantId,
                (string) $latest['recorded_at'],
                (int) ($threatFusion['final_threat_score'] ?? 0),
                (string) ($threatFusion['threat_level'] ?? 'NORMAL'),
                ['geofence_breach_pattern' => $geoHasBreachPattern]
            );
        } catch (Throwable $orchestratorEx) {
            emitEvent('WORKER_RESPONSE_ORCHESTRATOR_ERROR', 'warn', 'Response orchestrator failed', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'source' => 'worker_tracking',
                'error' => $orchestratorEx->getMessage(),
                'request_id' => getRequestId(),
            ]);
        }

        // Offline / idle / anomaly checks (single-source events in system_events).
        $offlineRows = $controlPdo->prepare(
            "SELECT worker_id, tenant_id, UNIX_TIMESTAMP(last_seen) AS last_seen_ts
             FROM worker_tracking_sessions
             WHERE tenant_id = ? AND last_seen < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $offlineRows->execute([$tenantId]);
        foreach ($offlineRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $w = (int) ($row['worker_id'] ?? 0);
            if ($w <= 0 || tracking_event_recent($controlPdo, 'WORKER_OFFLINE', $w, 10)) {
                continue;
            }
            emitEvent('WORKER_OFFLINE', 'warn', 'Worker tracking offline (>15m)', [
                'worker_id' => $w,
                'tenant_id' => (int) ($row['tenant_id'] ?? $tenantId),
                'status' => 'offline',
                'source' => 'worker_tracking',
                'duration_ms' => max(0, (time() - (int) ($row['last_seen_ts'] ?? 0))) * 1000,
                'request_id' => getRequestId(),
            ]);
        }

        $idleRows = $controlPdo->prepare(
            "SELECT worker_id, tenant_id, UNIX_TIMESTAMP(MAX(recorded_at)) AS last_move_ts
             FROM worker_locations
             WHERE tenant_id = ? AND status = 'moving'
             GROUP BY worker_id, tenant_id
             HAVING last_move_ts < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 2 HOUR))"
        );
        $idleRows->execute([$tenantId]);
        foreach ($idleRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $w = (int) ($row['worker_id'] ?? 0);
            if ($w <= 0 || tracking_event_recent($controlPdo, 'WORKER_IDLE_ALERT', $w, 60)) {
                continue;
            }
            emitEvent('WORKER_IDLE_ALERT', 'warn', 'Worker idle for over 2 hours', [
                'worker_id' => $w,
                'tenant_id' => (int) ($row['tenant_id'] ?? $tenantId),
                'status' => 'idle',
                'source' => 'worker_tracking',
                'duration_ms' => max(0, (time() - (int) ($row['last_move_ts'] ?? 0))) * 1000,
                'request_id' => getRequestId(),
            ]);
        }

        // sudden jump anomaly for latest point vs previous point
        $prevTwo = $controlPdo->prepare(
            "SELECT lat, lng, recorded_at
             FROM worker_locations
             WHERE tenant_id = ? AND worker_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 2"
        );
        $prevTwo->execute([$tenantId, $workerId]);
        $rows2 = $prevTwo->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows2) >= 2) {
            $new = $rows2[0];
            $old = $rows2[1];
            $jumpKm = tracking_haversine_km((float) $old['lat'], (float) $old['lng'], (float) $new['lat'], (float) $new['lng']);
            $dt = max(1, (strtotime((string) $new['recorded_at']) ?: 0) - (strtotime((string) $old['recorded_at']) ?: 0));
            if ($dt <= 120 && $jumpKm >= 2.0 && !tracking_event_recent($controlPdo, 'WORKER_ANOMALY', $workerId, 15)) {
                $controlPdo->prepare(
                    "UPDATE worker_locations
                     SET status = 'alert'
                     WHERE tenant_id = ? AND worker_id = ? AND recorded_at = ?"
                )->execute([$tenantId, $workerId, $new['recorded_at']]);
                emitEvent('WORKER_ANOMALY', 'error', 'Sudden location jump detected', [
                    'worker_id' => $workerId,
                    'tenant_id' => $tenantId,
                    'lat' => (float) $new['lat'],
                    'lng' => (float) $new['lng'],
                    'status' => 'alert',
                    'source' => 'worker_tracking',
                    'jump_km' => round($jumpKm, 4),
                    'duration_ms' => $dt * 1000,
                    'request_id' => getRequestId(),
                ]);
            }
        }

        emitEvent('WORKER_LOCATION_UPDATE', 'info', 'Location updated', [
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'lat' => $latest['lat'],
            'lng' => $latest['lng'],
            'accuracy' => $latest['accuracy'],
            'speed' => $latest['speed'],
            'status' => $latest['status'],
            'battery' => $latest['battery'],
            'source' => 'worker_tracking',
            'duration_ms' => 0,
            'request_id' => getRequestId(),
            'is_offline_batch' => $isBatch,
            'batch_size' => count($points),
            'risk_score' => (int) ($escape['risk_score'] ?? 0),
            'spoof_score' => (int) ($spoofAdv['spoof_score'] ?? 0),
            'geofence_status' => (string) ($geo['status'] ?? 'none'),
            'escape_risk_level' => (string) ($escape['escape_risk_level'] ?? 'normal'),
            'final_threat_score' => (int) ($threatFusion['final_threat_score'] ?? 0),
            'threat_level' => (string) ($threatFusion['threat_level'] ?? 'NORMAL'),
            'response_action' => (string) ($responseAction['action_type'] ?? 'NONE'),
            'response_priority' => (string) ($responseAction['priority'] ?? 'LOW'),
            'auto_focus_map' => !empty($responseAction['auto_focus_map']),
            'outside_geofences' => $geo['outside'] ?? [],
        ]);

        // Optional archive rotation: move old hot rows to archive table.
        $archiveDays = (int) (getenv('TRACKING_ARCHIVE_AFTER_DAYS') ?: 7);
        $archiveDays = max(1, min(365, $archiveDays));
        if (random_int(1, 30) === 1) {
            $copy = $controlPdo->prepare(
                "INSERT INTO worker_locations_archive
                 (worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at)
                 SELECT worker_id, tenant_id, lat, lng, accuracy, speed, status, battery, source, recorded_at, created_at
                 FROM worker_locations
                 WHERE recorded_at < DATE_SUB(NOW(), INTERVAL {$archiveDays} DAY)
                 ORDER BY id ASC
                 LIMIT 3000"
            );
            $copy->execute();
            $del = $controlPdo->prepare(
                "DELETE FROM worker_locations
                 WHERE recorded_at < DATE_SUB(NOW(), INTERVAL {$archiveDays} DAY)
                 LIMIT 3000"
            );
            $del->execute();
        }

        $controlPdo->commit();
    } catch (Throwable $e) {
        if ($controlPdo->inTransaction()) {
            $controlPdo->rollBack();
        }
        throw $e;
    }

    tracking_json([
        'success' => true,
        'message' => 'Location processed',
        'data' => [
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'accepted_points' => count($points),
            'offline_batch' => $isBatch,
        ],
    ]);
} catch (Throwable $e) {
    tracking_json(['success' => false, 'message' => $e->getMessage()], 500);
}
