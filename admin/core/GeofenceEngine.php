<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class GeofenceEngine
{
    public static function evaluateLocation(PDO $pdo, int $workerId, int $tenantId, ?int $agencyId, float $lat, float $lng): array
    {
        $fences = self::loadApplicableGeofences($pdo, $tenantId, $agencyId);
        if ($fences === []) {
            return ['inside' => [], 'outside' => [], 'status' => 'none', 'details' => []];
        }

        $outside = [];
        $inside = [];
        $details = [];
        foreach ($fences as $fence) {
            $breachPattern = false;
            $fenceId = (int) ($fence['id'] ?? 0);
            if ($fenceId <= 0) {
                continue;
            }
            $distanceM = self::haversineKm((float) $fence['center_lat'], (float) $fence['center_lng'], $lat, $lng) * 1000.0;
            $isInside = $distanceM <= (float) max(1, (int) ($fence['radius_m'] ?? 1));

            $prevState = self::readPrevState($pdo, $workerId, $tenantId, $fenceId);
            $prevInside = $prevState['is_inside'];
            $prevDistance = $prevState['last_distance_m'];
            $timeOutside = 0;
            if ($prevInside === false && isset($prevState['last_seen_at']) && $prevState['last_seen_at'] !== null) {
                $timeOutside = max(0, time() - (strtotime((string) $prevState['last_seen_at']) ?: time()));
            }
            $directionVector = self::directionVector(
                $prevState['last_lat'] ?? null,
                $prevState['last_lng'] ?? null,
                $lat,
                $lng
            );
            self::upsertState($pdo, $workerId, $tenantId, $fenceId, $isInside, $distanceM, $lat, $lng);

            if ($isInside) {
                $inside[] = $fenceId;
                if ($prevInside === false && !self::recentEventSeconds($pdo, 'WORKER_GEOFENCE_ENTER', $workerId, 10)) {
                    emitEvent('WORKER_GEOFENCE_ENTER', 'info', 'Worker returned to zone', [
                        'worker_id' => $workerId,
                        'tenant_id' => $tenantId,
                        'geofence_id' => $fenceId,
                        'distance_from_center' => round($distanceM, 2),
                        'time_outside' => $timeOutside,
                        'direction_vector' => $directionVector,
                        'priority' => 'low',
                        'source' => 'worker_tracking',
                        'request_id' => getRequestId(),
                    ], $pdo);
                }
            } else {
                $outside[] = $fenceId;
                if (($prevInside === null || $prevInside === true) && !self::recentEventSeconds($pdo, 'WORKER_GEOFENCE_EXIT', $workerId, 10)) {
                    emitEvent('WORKER_GEOFENCE_EXIT', 'warn', 'Worker left allowed zone', [
                        'worker_id' => $workerId,
                        'tenant_id' => $tenantId,
                        'geofence_id' => $fenceId,
                        'distance_from_center' => round($distanceM, 2),
                        'time_outside' => $timeOutside,
                        'direction_vector' => $directionVector,
                        'priority' => 'high',
                        'source' => 'worker_tracking',
                        'request_id' => getRequestId(),
                    ], $pdo);
                }
                if (self::isBreachPattern($pdo, $workerId, $tenantId, $fence, $distanceM) && !self::recentEventSeconds($pdo, 'WORKER_GEOFENCE_BREACH_PATTERN', $workerId, 10)) {
                    emitEvent('WORKER_GEOFENCE_BREACH_PATTERN', 'warn', 'Geofence breach pattern detected', [
                        'worker_id' => $workerId,
                        'tenant_id' => $tenantId,
                        'geofence_id' => $fenceId,
                        'distance_from_center' => round($distanceM, 2),
                        'time_outside' => $timeOutside,
                        'direction_vector' => $directionVector,
                        'priority' => 'high',
                        'source' => 'worker_tracking',
                        'request_id' => getRequestId(),
                    ], $pdo);
                }
                $breachPattern = self::isBreachPattern($pdo, $workerId, $tenantId, $fence, $distanceM);
            }

            $details[] = [
                'geofence_id' => $fenceId,
                'distance_from_center' => round($distanceM, 2),
                'is_inside' => $isInside,
                'time_outside' => $timeOutside,
                'direction_vector' => $directionVector,
                'boundary_hovering' => self::isBoundaryHovering($pdo, $workerId, $tenantId, $fence),
                'breach_pattern' => isset($breachPattern) ? (bool) $breachPattern : false,
            ];
        }

        return [
            'inside' => $inside,
            'outside' => $outside,
            'status' => $outside !== [] ? 'outside' : 'inside',
            'details' => $details,
        ];
    }

    private static function loadApplicableGeofences(PDO $pdo, int $tenantId, ?int $agencyId): array
    {
        $sql = "SELECT id, tenant_id, agency_id, name, center_lat, center_lng, radius_m
                FROM worker_geofences
                WHERE is_active = 1
                  AND (tenant_id IS NULL OR tenant_id = :tenant_id)
                  AND (:agency_id IS NULL OR agency_id IS NULL OR agency_id = :agency_id)";
        $st = $pdo->prepare($sql);
        $st->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        if ($agencyId === null || $agencyId <= 0) {
            $st->bindValue(':agency_id', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':agency_id', $agencyId, PDO::PARAM_INT);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static function readPrevState(PDO $pdo, int $workerId, int $tenantId, int $geofenceId): array
    {
        $st = $pdo->prepare(
            "SELECT is_inside, last_distance_m, last_lat, last_lng, last_seen_at
             FROM worker_geofence_states
             WHERE worker_id = ? AND tenant_id = ? AND geofence_id = ?
             LIMIT 1"
        );
        $st->execute([$workerId, $tenantId, $geofenceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['is_inside' => null, 'last_distance_m' => null, 'last_lat' => null, 'last_lng' => null, 'last_seen_at' => null];
        }
        return [
            'is_inside' => ((int) ($row['is_inside'] ?? 0)) === 1,
            'last_distance_m' => isset($row['last_distance_m']) ? (float) $row['last_distance_m'] : null,
            'last_lat' => isset($row['last_lat']) ? (float) $row['last_lat'] : null,
            'last_lng' => isset($row['last_lng']) ? (float) $row['last_lng'] : null,
            'last_seen_at' => $row['last_seen_at'] ?? null,
        ];
    }

    private static function upsertState(
        PDO $pdo,
        int $workerId,
        int $tenantId,
        int $geofenceId,
        bool $isInside,
        float $distanceM,
        float $lat,
        float $lng
    ): void {
        $st = $pdo->prepare(
            "INSERT INTO worker_geofence_states
             (worker_id, tenant_id, geofence_id, is_inside, last_distance_m, last_lat, last_lng, last_seen_at, updated_at)
             VALUES (:worker_id, :tenant_id, :geofence_id, :is_inside, :last_distance_m, :last_lat, :last_lng, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                is_inside = VALUES(is_inside),
                last_distance_m = VALUES(last_distance_m),
                last_lat = VALUES(last_lat),
                last_lng = VALUES(last_lng),
                last_seen_at = NOW(),
                updated_at = NOW()"
        );
        $st->execute([
            ':worker_id' => $workerId,
            ':tenant_id' => $tenantId,
            ':geofence_id' => $geofenceId,
            ':is_inside' => $isInside ? 1 : 0,
            ':last_distance_m' => $distanceM,
            ':last_lat' => $lat,
            ':last_lng' => $lng,
        ]);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private static function isBoundaryHovering(PDO $pdo, int $workerId, int $tenantId, array $fence): bool
    {
        $radius = (float) max(1, (int) ($fence['radius_m'] ?? 1));
        $st = $pdo->prepare(
            "SELECT lat, lng
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 6"
        );
        $st->execute([$workerId, $tenantId]);
        $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        if (count($rows) < 4) {
            return false;
        }
        $switches = 0;
        $prevInside = null;
        foreach ($rows as $r) {
            $d = self::haversineKm((float) $fence['center_lat'], (float) $fence['center_lng'], (float) $r['lat'], (float) $r['lng']) * 1000.0;
            $inside = $d <= $radius;
            if ($prevInside !== null && $inside !== $prevInside) {
                $switches++;
            }
            $prevInside = $inside;
        }
        return $switches >= 2;
    }

    private static function isBreachPattern(PDO $pdo, int $workerId, int $tenantId, array $fence, float $currentDistanceM): bool
    {
        $radius = (float) max(1, (int) ($fence['radius_m'] ?? 1));
        if ($currentDistanceM <= $radius) {
            return false;
        }
        $st = $pdo->prepare(
            "SELECT lat, lng
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT 3"
        );
        $st->execute([$workerId, $tenantId]);
        $rows = array_reverse($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
        if (count($rows) < 3) {
            return false;
        }
        $d1 = self::haversineKm((float) $fence['center_lat'], (float) $fence['center_lng'], (float) $rows[0]['lat'], (float) $rows[0]['lng']) * 1000.0;
        $d2 = self::haversineKm((float) $fence['center_lat'], (float) $fence['center_lng'], (float) $rows[1]['lat'], (float) $rows[1]['lng']) * 1000.0;
        $d3 = self::haversineKm((float) $fence['center_lat'], (float) $fence['center_lng'], (float) $rows[2]['lat'], (float) $rows[2]['lng']) * 1000.0;
        return $d1 > $radius && $d2 > $radius && $d3 > $radius && $d1 < $d2 && $d2 < $d3;
    }

    private static function directionVector(?float $fromLat, ?float $fromLng, float $toLat, float $toLng): ?array
    {
        if ($fromLat === null || $fromLng === null) {
            return null;
        }
        return [
            'd_lat' => round($toLat - $fromLat, 7),
            'd_lng' => round($toLng - $fromLng, 7),
        ];
    }

    private static function recentEventSeconds(PDO $pdo, string $eventType, int $workerId, int $seconds): bool
    {
        $st = $pdo->prepare(
            "SELECT id FROM system_events
             WHERE event_type = :et
               AND metadata LIKE :metaLike
               AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->bindValue(':et', $eventType);
        $st->bindValue(':metaLike', '%"worker_id":' . $workerId . '%');
        $st->bindValue(':secs', max(1, $seconds), PDO::PARAM_INT);
        $st->execute();
        return (bool) $st->fetchColumn();
    }
}
