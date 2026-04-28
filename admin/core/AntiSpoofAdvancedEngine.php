<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class AntiSpoofAdvancedEngine
{
    public static function evaluate(PDO $pdo, int $workerId, int $tenantId, array $latest, ?string $networkType = null): array
    {
        $cacheKey = 'spoof:' . $tenantId . ':' . $workerId;
        $cached = self::cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $rows = self::loadRecentPoints($pdo, $workerId, $tenantId, 30);
        if (count($rows) < 4) {
            $result = ['spoof_score' => 0, 'top_reasons' => []];
            self::cacheSet($cacheKey, $result, 12);
            return $result;
        }

        $reasons = [];
        $score = 0.0;
        $score += self::impossiblePhysicsScore($rows, $reasons);
        $score += self::gpsManipulationScore($rows, $reasons);
        $score += self::patternSpoofScore($rows, $reasons);
        $score += self::networkMismatchScore($rows, $networkType, $reasons);

        $spoofScore = max(0, min(100, (int) round($score)));
        $topReasons = array_slice(array_values(array_unique($reasons)), 0, 5);
        $result = ['spoof_score' => $spoofScore, 'top_reasons' => $topReasons];

        if ($spoofScore >= 55 && !self::recentEventSeconds($pdo, 'WORKER_SPOOF_SUSPECTED', $workerId, 10)) {
            emitEvent('WORKER_SPOOF_SUSPECTED', 'error', 'Advanced spoofing suspicion', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'spoof_score' => $spoofScore,
                'last_coordinates' => ['lat' => $latest['lat'] ?? null, 'lng' => $latest['lng'] ?? null],
                'top_reasons' => $topReasons,
                'priority' => 'high',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }
        if ($spoofScore >= 80 && !self::recentEventSeconds($pdo, 'WORKER_GPS_SPOOF_CONFIRMED', $workerId, 10)) {
            emitEvent('WORKER_GPS_SPOOF_CONFIRMED', 'critical', 'GPS spoofing confirmed', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'spoof_score' => $spoofScore,
                'last_coordinates' => ['lat' => $latest['lat'] ?? null, 'lng' => $latest['lng'] ?? null],
                'top_reasons' => $topReasons,
                'priority' => 'critical',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }

        self::cacheSet($cacheKey, $result, 12);
        return $result;
    }

    private static function loadRecentPoints(PDO $pdo, int $workerId, int $tenantId, int $limit): array
    {
        $st = $pdo->prepare(
            "SELECT lat, lng, speed, accuracy, status, recorded_at
             FROM worker_locations
             WHERE worker_id = ? AND tenant_id = ?
             ORDER BY recorded_at DESC, id DESC
             LIMIT ?"
        );
        $st->bindValue(1, $workerId, PDO::PARAM_INT);
        $st->bindValue(2, $tenantId, PDO::PARAM_INT);
        $st->bindValue(3, max(12, min(30, $limit)), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows);
    }

    private static function impossiblePhysicsScore(array $rows, array &$reasons): float
    {
        $score = 0.0;
        $n = count($rows);
        for ($i = 1; $i < $n; $i++) {
            $a = $rows[$i - 1];
            $b = $rows[$i];
            $distKm = self::haversineKm((float) $a['lat'], (float) $a['lng'], (float) $b['lat'], (float) $b['lng']);
            $dt = max(1, (strtotime((string) $b['recorded_at']) ?: 0) - (strtotime((string) $a['recorded_at']) ?: 0));
            $kmh = $distKm / ($dt / 3600.0);
            if ($kmh > 220.0) {
                $score += 20.0;
                $reasons[] = 'teleport_jump';
            } elseif ($kmh > 130.0) {
                $score += 10.0;
                $reasons[] = 'impossible_speed';
            }
            $prevSpeed = isset($a['speed']) ? (float) $a['speed'] : 0.0;
            $curSpeed = isset($b['speed']) ? (float) $b['speed'] : 0.0;
            if (($curSpeed - $prevSpeed) > 25.0) {
                $score += 8.0;
                $reasons[] = 'acceleration_spike';
            }
        }
        return min(35.0, $score);
    }

    private static function gpsManipulationScore(array $rows, array &$reasons): float
    {
        $score = 0.0;
        $identicalMoving = 0;
        $perfectAccuracy = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $a = $rows[$i - 1];
            $b = $rows[$i];
            $same = (abs((float) $a['lat'] - (float) $b['lat']) < 0.0000001) && (abs((float) $a['lng'] - (float) $b['lng']) < 0.0000001);
            if ($same && (((float) ($b['speed'] ?? 0.0)) > 1.0 || strtolower((string) ($b['status'] ?? '')) === 'moving')) {
                $identicalMoving++;
            }
            $acc = (float) ($b['accuracy'] ?? 0.0);
            if ($acc > 0 && $acc <= 3.0) {
                $perfectAccuracy++;
            }
            $jumpKm = self::haversineKm((float) $a['lat'], (float) $a['lng'], (float) $b['lat'], (float) $b['lng']);
            $dt = max(1, (strtotime((string) $b['recorded_at']) ?: 0) - (strtotime((string) $a['recorded_at']) ?: 0));
            if ($dt <= 600 && $jumpKm >= 100.0) {
                $score += 20.0;
                $reasons[] = 'country_city_jump';
            }
        }
        if ($identicalMoving >= 2) {
            $score += 16.0;
            $reasons[] = 'identical_coordinates_while_moving';
        }
        if ($perfectAccuracy >= 8) {
            $score += 10.0;
            $reasons[] = 'accuracy_too_perfect';
        }
        return min(35.0, $score);
    }

    private static function patternSpoofScore(array $rows, array &$reasons): float
    {
        $score = 0.0;
        if (count($rows) < 6) {
            return $score;
        }
        $bearings = [];
        for ($i = 1; $i < count($rows); $i++) {
            $bearings[] = self::bearing((float) $rows[$i - 1]['lat'], (float) $rows[$i - 1]['lng'], (float) $rows[$i]['lat'], (float) $rows[$i]['lng']);
        }
        $zigzag = 0;
        for ($i = 1; $i < count($bearings); $i++) {
            $d = abs($bearings[$i] - $bearings[$i - 1]);
            if ($d > 140.0 && $d < 220.0) {
                $zigzag++;
            }
        }
        if ($zigzag >= 3) {
            $score += 14.0;
            $reasons[] = 'zigzag_micro_movements';
        }

        $diffs = [];
        for ($i = 1; $i < count($bearings); $i++) {
            $diffs[] = abs($bearings[$i] - $bearings[$i - 1]);
        }
        if ($diffs !== []) {
            $avg = array_sum($diffs) / count($diffs);
            if ($avg < 3.0 && count($diffs) >= 6) {
                $score += 12.0;
                $reasons[] = 'robotic_straight_line_pattern';
            }
        }
        return min(30.0, $score);
    }

    private static function networkMismatchScore(array $rows, ?string $networkType, array &$reasons): float
    {
        if ($networkType === null || $networkType === '') {
            return 0.0;
        }
        $last = end($rows);
        $speed = isset($last['speed']) ? (float) $last['speed'] : 0.0;
        if (strtolower($networkType) === '2g' && $speed > 35.0) {
            $reasons[] = 'network_motion_mismatch';
            return 8.0;
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

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
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
