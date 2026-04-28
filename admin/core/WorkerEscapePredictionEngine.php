<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class WorkerEscapePredictionEngine
{
    public static function evaluate(PDO $pdo, int $workerId, int $tenantId, array $latest): array
    {
        $cacheKey = 'escape:' . $tenantId . ':' . $workerId;
        $cached = self::cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = self::loadRecentPoints($pdo, $workerId, $tenantId, 30);
        if (count($rows) < 4) {
            $result = [
                'risk_score' => 0,
                'escape_risk_level' => 'normal',
                'top_reasons' => [],
                'last_coordinates' => ['lat' => $latest['lat'] ?? null, 'lng' => $latest['lng'] ?? null],
            ];
            self::cacheSet($cacheKey, $result, 15);
            return $result;
        }

        $reasons = [];
        $score = 0.0;
        $score += self::directionChangeScore($rows, $reasons);
        $score += self::boundaryApproachScore($rows, $reasons);
        $score += self::speedBurstAfterIdleScore($rows, $reasons);
        $score += self::longStopThenSpikeScore($rows, $reasons);
        $score += self::movingAwayFromCenterScore($rows, $reasons);
        $score += self::nightMovementScore($rows, $reasons);

        $riskScore = max(0, min(100, (int) round($score)));
        $riskLevel = $riskScore >= 71 ? 'high' : ($riskScore >= 41 ? 'warning' : 'normal');
        $topReasons = array_slice(array_values(array_unique($reasons)), 0, 4);

        $result = [
            'risk_score' => $riskScore,
            'escape_risk_level' => $riskLevel,
            'top_reasons' => $topReasons,
            'last_coordinates' => ['lat' => $latest['lat'] ?? null, 'lng' => $latest['lng'] ?? null],
        ];

        if ($riskScore >= 41 && !self::recentEventSeconds($pdo, 'WORKER_ESCAPE_RISK', $workerId, 10)) {
            emitEvent('WORKER_ESCAPE_RISK', 'warn', 'Possible escape behavior detected', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'risk_score' => $riskScore,
                'last_coordinates' => $result['last_coordinates'],
                'top_reasons' => $topReasons,
                'priority' => 'medium',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }
        if ($riskScore >= 71 && !self::recentEventSeconds($pdo, 'WORKER_ESCAPE_HIGH_RISK', $workerId, 10)) {
            emitEvent('WORKER_ESCAPE_HIGH_RISK', 'critical', 'High escape probability', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'risk_score' => $riskScore,
                'last_coordinates' => $result['last_coordinates'],
                'top_reasons' => $topReasons,
                'priority' => 'critical',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }

        self::cacheSet($cacheKey, $result, 15);
        return $result;
    }

    private static function loadRecentPoints(PDO $pdo, int $workerId, int $tenantId, int $limit): array
    {
        $st = $pdo->prepare(
            "SELECT lat, lng, speed, status, recorded_at
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT ?"
        );
        $st->bindValue(1, $workerId, PDO::PARAM_INT);
        $st->bindValue(2, $tenantId, PDO::PARAM_INT);
        $st->bindValue(3, max(10, min(30, $limit)), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows);
    }

    private static function directionChangeScore(array $rows, array &$reasons): float
    {
        if (count($rows) < 5) {
            return 0.0;
        }
        $n = count($rows);
        $a1 = self::bearing($rows[$n - 5], $rows[$n - 4]);
        $a2 = self::bearing($rows[$n - 4], $rows[$n - 3]);
        $a3 = self::bearing($rows[$n - 3], $rows[$n - 2]);
        $a4 = self::bearing($rows[$n - 2], $rows[$n - 1]);
        $turns = 0;
        foreach ([abs($a2 - $a1), abs($a3 - $a2), abs($a4 - $a3)] as $delta) {
            if ($delta > 110.0) {
                $turns++;
            }
        }
        if ($turns >= 2) {
            $reasons[] = 'sudden_direction_changes';
            return 18.0;
        }
        return 0.0;
    }

    private static function boundaryApproachScore(array $rows, array &$reasons): float
    {
        $nearBoundary = 0;
        foreach ($rows as $r) {
            $d = isset($r['distance_from_center']) ? (float) $r['distance_from_center'] : null;
            $rad = isset($r['radius_m']) ? (float) $r['radius_m'] : null;
            if ($d !== null && $rad !== null && $rad > 0 && $d >= $rad * 0.85 && $d <= $rad * 1.05) {
                $nearBoundary++;
            }
        }
        if ($nearBoundary >= 3) {
            $reasons[] = 'repeated_boundary_approach';
            return 14.0;
        }
        return 0.0;
    }

    private static function speedBurstAfterIdleScore(array $rows, array &$reasons): float
    {
        if (count($rows) < 6) {
            return 0.0;
        }
        $tail = array_slice($rows, -6);
        $idleCount = 0;
        foreach (array_slice($tail, 0, 4) as $r) {
            if (strtolower((string) ($r['status'] ?? '')) === 'idle') {
                $idleCount++;
            }
        }
        $lastSpeed = isset($tail[5]['speed']) ? (float) $tail[5]['speed'] : 0.0;
        if ($idleCount >= 3 && $lastSpeed > 10.0) {
            $reasons[] = 'abnormal_speed_burst_after_idle';
            return 20.0;
        }
        return 0.0;
    }

    private static function longStopThenSpikeScore(array $rows, array &$reasons): float
    {
        if (count($rows) < 3) {
            return 0.0;
        }
        $recent = array_slice($rows, -3);
        $firstTs = strtotime((string) $recent[0]['recorded_at']) ?: 0;
        $secondTs = strtotime((string) $recent[1]['recorded_at']) ?: 0;
        $idleGap = max(0, $secondTs - $firstTs);
        $lastSpeed = isset($recent[2]['speed']) ? (float) $recent[2]['speed'] : 0.0;
        if ($idleGap >= 600 && $lastSpeed > 12.0) {
            $reasons[] = 'long_stop_then_spike';
            return 14.0;
        }
        return 0.0;
    }

    private static function movingAwayFromCenterScore(array $rows, array &$reasons): float
    {
        $last = array_slice($rows, -4);
        if (count($last) < 4) {
            return 0.0;
        }
        $centerLat = 0.0;
        $centerLng = 0.0;
        $base = array_slice($rows, 0, max(1, count($rows) - 4));
        foreach ($base as $r) {
            $centerLat += (float) $r['lat'];
            $centerLng += (float) $r['lng'];
        }
        $centerLat /= count($base);
        $centerLng /= count($base);

        $d1 = self::distanceKm($centerLat, $centerLng, (float) $last[0]['lat'], (float) $last[0]['lng']);
        $d2 = self::distanceKm($centerLat, $centerLng, (float) $last[1]['lat'], (float) $last[1]['lng']);
        $d3 = self::distanceKm($centerLat, $centerLng, (float) $last[2]['lat'], (float) $last[2]['lng']);
        $d4 = self::distanceKm($centerLat, $centerLng, (float) $last[3]['lat'], (float) $last[3]['lng']);
        if ($d1 < $d2 && $d2 < $d3 && $d3 < $d4 && ($d4 - $d1) > 0.8) {
            $reasons[] = 'moving_away_from_geofence_center';
            return 20.0;
        }
        return 0.0;
    }

    private static function nightMovementScore(array $rows, array &$reasons): float
    {
        $nightMoves = 0;
        foreach (array_slice($rows, -8) as $r) {
            $ts = strtotime((string) ($r['recorded_at'] ?? '')) ?: 0;
            if ($ts <= 0) {
                continue;
            }
            $hour = (int) date('G', $ts);
            $speed = isset($r['speed']) ? (float) $r['speed'] : 0.0;
            if (($hour >= 0 && $hour <= 4) && $speed > 6.0) {
                $nightMoves++;
            }
        }
        if ($nightMoves >= 2) {
            $reasons[] = 'night_time_abnormal_movement';
            return 12.0;
        }
        return 0.0;
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

    private static function bearing(array $a, array $b): float
    {
        $lat1 = deg2rad((float) $a['lat']);
        $lng1 = deg2rad((float) $a['lng']);
        $lat2 = deg2rad((float) $b['lat']);
        $lng2 = deg2rad((float) $b['lng']);
        $dl = $lng2 - $lng1;
        $y = sin($dl) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dl);
        $brng = rad2deg(atan2($y, $x));
        return fmod(($brng + 360.0), 360.0);
    }

    private static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private static function cacheGet(string $key): ?array
    {
        if (function_exists('apcu_fetch')) {
            $ok = false;
            $v = apcu_fetch($key, $ok);
            if ($ok && is_array($v)) {
                return $v;
            }
        }
        return null;
    }

    private static function cacheSet(string $key, array $value, int $ttl): void
    {
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, max(1, $ttl));
        }
    }
}
