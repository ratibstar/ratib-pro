<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class WorkerBehaviorAnalyzer
{
    public static function evaluate(PDO $pdo, int $workerId, int $tenantId, array $latest): array
    {
        $rows = self::loadRecent($pdo, $workerId, $tenantId, 40);
        if (count($rows) < 3) {
            return ['risk_score' => 0, 'signals' => []];
        }

        $signals = [];
        $speedAnomaly = self::speedAnomaly($rows, $signals);
        $distanceAnomaly = self::distanceAnomaly($rows, $signals);
        $boundaryProximity = self::boundaryProximity($pdo, $workerId, $tenantId, $latest, $signals);
        $historicalDeviation = self::historicalDeviation($rows, $signals);
        $directionAnomaly = self::directionChangeAnomaly($rows, $signals);
        $idleBurst = self::idleThenBurst($rows, $signals);

        $score = min(100, (int) round(
            $speedAnomaly
            + $distanceAnomaly
            + $boundaryProximity
            + $historicalDeviation
            + $directionAnomaly
            + $idleBurst
        ));

        if ($score >= 60 && !self::recentEventExists($pdo, 'WORKER_ESCAPE_RISK', $workerId, 5)) {
            emitEvent('WORKER_ESCAPE_RISK', 'warn', 'Possible escape behavior detected', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'risk_score' => $score,
                'signals' => $signals,
                'last_location' => ['lat' => $latest['lat'] ?? null, 'lng' => $latest['lng'] ?? null],
                'priority' => 'medium',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }
        if ($score >= 85 && !self::recentEventExists($pdo, 'WORKER_ESCAPE_HIGH_RISK', $workerId, 4)) {
            emitEvent('WORKER_ESCAPE_HIGH_RISK', 'critical', 'High escape probability', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'risk_score' => $score,
                'signals' => $signals,
                'priority' => 'critical',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }

        return ['risk_score' => $score, 'signals' => $signals];
    }

    private static function loadRecent(PDO $pdo, int $workerId, int $tenantId, int $limit): array
    {
        $st = $pdo->prepare(
            "SELECT lat, lng, speed, accuracy, status, recorded_at
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             ORDER BY recorded_at DESC, id DESC
             LIMIT ?"
        );
        $st->bindValue(1, $workerId, PDO::PARAM_INT);
        $st->bindValue(2, $tenantId, PDO::PARAM_INT);
        $st->bindValue(3, max(5, $limit), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows);
    }

    private static function speedAnomaly(array $rows, array &$signals): float
    {
        $speeds = [];
        foreach ($rows as $r) {
            if (isset($r['speed']) && $r['speed'] !== null) {
                $speeds[] = (float) $r['speed'];
            }
        }
        if ($speeds === []) {
            return 0.0;
        }
        $avg = array_sum($speeds) / count($speeds);
        $last = (float) end($speeds);
        if ($last > max(14.0, $avg * 2.8)) {
            $signals[] = 'abnormal_speed_increase';
            return 24.0;
        }
        return 0.0;
    }

    private static function distanceAnomaly(array $rows, array &$signals): float
    {
        if (count($rows) < 2) {
            return 0.0;
        }
        $a = $rows[count($rows) - 2];
        $b = $rows[count($rows) - 1];
        $d = self::haversineKm((float) $a['lat'], (float) $a['lng'], (float) $b['lat'], (float) $b['lng']);
        $dt = max(1, (strtotime((string) $b['recorded_at']) ?: 0) - (strtotime((string) $a['recorded_at']) ?: 0));
        if ($dt <= 180 && $d >= 2.5) {
            $signals[] = 'distance_jump';
            return 20.0;
        }
        return 0.0;
    }

    private static function boundaryProximity(PDO $pdo, int $workerId, int $tenantId, array $latest, array &$signals): float
    {
        $lat = isset($latest['lat']) ? (float) $latest['lat'] : 0.0;
        $lng = isset($latest['lng']) ? (float) $latest['lng'] : 0.0;
        $st = $pdo->prepare(
            "SELECT wgs.last_distance_m, wg.radius_m
             FROM worker_geofence_states wgs
             INNER JOIN worker_geofences wg ON wg.id = wgs.geofence_id
             WHERE wgs.worker_id = ? AND wgs.tenant_id = ?
             ORDER BY wgs.updated_at DESC
             LIMIT 3"
        );
        $st->execute([$workerId, $tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $r) {
            $dist = (float) ($r['last_distance_m'] ?? 0.0);
            $radius = (float) max(1, (int) ($r['radius_m'] ?? 1));
            if ($dist >= ($radius * 0.85)) {
                $signals[] = 'boundary_approach_pattern';
                return 14.0;
            }
        }
        return 0.0;
    }

    private static function historicalDeviation(array $rows, array &$signals): float
    {
        $sample = array_slice($rows, 0, max(1, count($rows) - 1));
        $last = $rows[count($rows) - 1];
        $centerLat = 0.0;
        $centerLng = 0.0;
        foreach ($sample as $r) {
            $centerLat += (float) $r['lat'];
            $centerLng += (float) $r['lng'];
        }
        $centerLat /= count($sample);
        $centerLng /= count($sample);
        $distLast = self::haversineKm($centerLat, $centerLng, (float) $last['lat'], (float) $last['lng']);
        if ($distLast > 3.0) {
            $signals[] = 'outside_usual_work_area';
            return 18.0;
        }
        return 0.0;
    }

    private static function directionChangeAnomaly(array $rows, array &$signals): float
    {
        if (count($rows) < 4) {
            return 0.0;
        }
        $n = count($rows);
        $b1 = self::bearing((float) $rows[$n - 4]['lat'], (float) $rows[$n - 4]['lng'], (float) $rows[$n - 3]['lat'], (float) $rows[$n - 3]['lng']);
        $b2 = self::bearing((float) $rows[$n - 3]['lat'], (float) $rows[$n - 3]['lng'], (float) $rows[$n - 2]['lat'], (float) $rows[$n - 2]['lng']);
        $b3 = self::bearing((float) $rows[$n - 2]['lat'], (float) $rows[$n - 2]['lng'], (float) $rows[$n - 1]['lat'], (float) $rows[$n - 1]['lng']);
        $d1 = abs($b2 - $b1);
        $d2 = abs($b3 - $b2);
        if ($d1 > 120.0 && $d2 > 120.0) {
            $signals[] = 'sudden_direction_change';
            return 12.0;
        }
        return 0.0;
    }

    private static function idleThenBurst(array $rows, array &$signals): float
    {
        if (count($rows) < 6) {
            return 0.0;
        }
        $tail = array_slice($rows, -6);
        $idleCount = 0;
        foreach (array_slice($tail, 0, 4) as $r) {
            if (((string) ($r['status'] ?? '')) === 'idle') {
                $idleCount++;
            }
        }
        $lastSpeed = isset($tail[5]['speed']) ? (float) $tail[5]['speed'] : 0.0;
        if ($idleCount >= 3 && $lastSpeed > 12.0) {
            $signals[] = 'long_idle_then_fast_movement';
            return 15.0;
        }
        return 0.0;
    }

    private static function recentEventExists(PDO $pdo, string $eventType, int $workerId, int $minutes): bool
    {
        $st = $pdo->prepare(
            "SELECT id
             FROM system_events
             WHERE event_type = :et
               AND metadata LIKE :metaLike
               AND created_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->bindValue(':et', $eventType);
        $st->bindValue(':metaLike', '%"worker_id":' . $workerId . '%');
        $st->bindValue(':mins', max(1, $minutes), PDO::PARAM_INT);
        $st->execute();
        return (bool) $st->fetchColumn();
    }

    private static function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dl = deg2rad($lng2 - $lng1);
        $y = sin($dl) * cos($phi2);
        $x = cos($phi1) * sin($phi2) - sin($phi1) * cos($phi2) * cos($dl);
        $brng = rad2deg(atan2($y, $x));
        return fmod(($brng + 360.0), 360.0);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
