<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class AntiSpoofEngine
{
    public static function evaluate(PDO $controlPdo, int $workerId, int $tenantId, ?array $prev, array $current, ?string $networkType = null): array
    {
        $reasons = [];

        $lat = (float) ($current['lat'] ?? 0.0);
        $lng = (float) ($current['lng'] ?? 0.0);
        $speed = isset($current['speed']) ? (float) $current['speed'] : null; // m/s
        $accuracy = isset($current['accuracy']) ? (float) $current['accuracy'] : null;
        $recordedAt = (string) ($current['recorded_at'] ?? date('Y-m-d H:i:s'));

        if ($accuracy !== null && ($accuracy <= 0 || $accuracy > 2000)) {
            $reasons[] = 'suspicious_accuracy';
        }
        if ($speed !== null && $speed > 55.0) {
            $reasons[] = 'impossible_speed';
        }
        if ($speed !== null && $accuracy !== null && $speed > 35.0 && $accuracy > 800.0) {
            $reasons[] = 'speed_accuracy_mismatch';
        }
        if ($networkType !== null && $networkType !== '' && $speed !== null && $speed > 45.0 && strtolower($networkType) === '2g') {
            $reasons[] = 'network_motion_mismatch';
        }

        if ($prev && isset($prev['lat'], $prev['lng'], $prev['recorded_at'])) {
            $distKm = self::haversineKm((float) $prev['lat'], (float) $prev['lng'], $lat, $lng);
            $prevTs = strtotime((string) $prev['recorded_at']) ?: 0;
            $curTs = strtotime($recordedAt) ?: 0;
            $deltaSecs = max(1, $curTs - $prevTs);
            $derivedKmh = $distKm / ($deltaSecs / 3600.0);

            if ($distKm >= 50.0 && $deltaSecs <= 900) {
                $reasons[] = 'cross_city_jump';
            }
            if ($derivedKmh > 240.0) {
                $reasons[] = 'impossible_acceleration';
            }
            if (($speed !== null && $speed > 1.0) && $distKm < 0.001 && $deltaSecs >= 180) {
                $reasons[] = 'repeated_identical_coordinates_with_motion';
            }
        }

        $reasons = array_values(array_unique($reasons));
        if ($reasons !== [] && !self::recentEventExists($controlPdo, 'WORKER_GPS_SPOOF_DETECTED', $workerId, 4)) {
            emitEvent('WORKER_GPS_SPOOF_DETECTED', 'error', 'GPS spoofing suspected', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'accuracy' => $accuracy,
                'reason' => implode(',', $reasons),
                'reasons' => $reasons,
                'status' => 'alert',
                'priority' => 'high',
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $controlPdo);
        }

        return ['is_suspicious' => $reasons !== [], 'reasons' => $reasons];
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

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
