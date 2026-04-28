<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class WorkerThreatFusionEngine
{
    public static function evaluate(PDO $pdo, int $workerId, int $tenantId, string $recordedAt, array $escape, array $spoof, array $geo): array
    {
        $cacheKey = 'threat_fusion:' . $tenantId . ':' . $workerId;
        $cached = self::cacheGet($cacheKey);
        if (is_array($cached) && (string) ($cached['recorded_at'] ?? '') === $recordedAt) {
            return $cached;
        }

        $escapeScore = max(0, min(100, (int) ($escape['risk_score'] ?? 0)));
        $spoofScore = max(0, min(100, (int) ($spoof['spoof_score'] ?? 0)));
        $breachPattern = self::geoHasBreachPattern($geo);
        $geoComponentScore = $breachPattern ? 100 : ((string) ($geo['status'] ?? '') === 'outside' ? 70 : 0);

        $final = (int) round(($escapeScore * 0.4) + ($spoofScore * 0.4) + ($geoComponentScore * 0.2));
        if ($breachPattern) {
            $final += 15;
        }
        if (self::recentEventExists($pdo, 'WORKER_GPS_SPOOF_CONFIRMED', $workerId, 10)) {
            $final = max($final, 85);
        }
        if ($escapeScore >= 71 && $spoofScore >= 71) {
            $final = max($final, 90);
        }
        $final = max(0, min(100, $final));

        $level = self::levelFromScore($final);
        $result = [
            'recorded_at' => $recordedAt,
            'final_threat_score' => $final,
            'threat_level' => $level,
            'escape_score' => $escapeScore,
            'spoof_score' => $spoofScore,
            'geofence_status' => (string) ($geo['status'] ?? 'none'),
        ];

        self::emitActionEvents($pdo, $workerId, $tenantId, $result);
        self::cacheSet($cacheKey, $result, 15);
        return $result;
    }

    private static function emitActionEvents(PDO $pdo, int $workerId, int $tenantId, array $result): void
    {
        $score = (int) ($result['final_threat_score'] ?? 0);
        $level = (string) ($result['threat_level'] ?? 'NORMAL');
        if ($score <= 40) {
            return;
        }
        $meta = [
            'worker_id' => $workerId,
            'tenant_id' => $tenantId,
            'final_threat_score' => $score,
            'threat_level' => $level,
            'geofence_status' => $result['geofence_status'] ?? 'none',
            'spoof_score' => (int) ($result['spoof_score'] ?? 0),
            'risk_score' => (int) ($result['escape_score'] ?? 0),
            'priority_broadcast' => $level === 'CRITICAL',
            'highlight_worker' => in_array($level, ['HIGH', 'CRITICAL'], true),
            'focus_worker' => $level === 'CRITICAL',
            'source' => 'worker_tracking',
            'request_id' => getRequestId(),
        ];

        if ($score <= 70 && !self::recentEventExistsSeconds($pdo, 'WORKER_THREAT_ELEVATED', $workerId, 10)) {
            $meta['priority'] = 'medium';
            emitEvent('WORKER_THREAT_ELEVATED', 'warn', 'Worker threat elevated', $meta, $pdo);
            return;
        }
        if ($score <= 89 && !self::recentEventExistsSeconds($pdo, 'WORKER_THREAT_HIGH', $workerId, 10)) {
            $meta['priority'] = 'high';
            emitEvent('WORKER_THREAT_HIGH', 'error', 'Worker threat high', $meta, $pdo);
            return;
        }
        if (!self::recentEventExistsSeconds($pdo, 'WORKER_THREAT_CRITICAL', $workerId, 10)) {
            $meta['priority'] = 'critical';
            emitEvent('WORKER_THREAT_CRITICAL', 'critical', 'Worker threat critical', $meta, $pdo);
        }
    }

    private static function geoHasBreachPattern(array $geo): bool
    {
        $details = isset($geo['details']) && is_array($geo['details']) ? $geo['details'] : [];
        foreach ($details as $d) {
            if (!empty($d['breach_pattern'])) {
                return true;
            }
        }
        return false;
    }

    private static function levelFromScore(int $score): string
    {
        if ($score <= 40) {
            return 'NORMAL';
        }
        if ($score <= 70) {
            return 'ELEVATED';
        }
        if ($score <= 89) {
            return 'HIGH';
        }
        return 'CRITICAL';
    }

    private static function recentEventExists(PDO $pdo, string $eventType, int $workerId, int $minutes): bool
    {
        $st = $pdo->prepare(
            "SELECT id FROM system_events
             WHERE event_type = :et
               AND metadata LIKE :metaLike
               AND created_at >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
             ORDER BY id DESC LIMIT 1"
        );
        $st->bindValue(':et', $eventType);
        $st->bindValue(':metaLike', '%"worker_id":' . $workerId . '%');
        $st->bindValue(':mins', max(1, $minutes), PDO::PARAM_INT);
        $st->execute();
        return (bool) $st->fetchColumn();
    }

    private static function recentEventExistsSeconds(PDO $pdo, string $eventType, int $workerId, int $seconds): bool
    {
        $st = $pdo->prepare(
            "SELECT id FROM system_events
             WHERE event_type = :et
               AND metadata LIKE :metaLike
               AND created_at >= DATE_SUB(NOW(), INTERVAL :secs SECOND)
             ORDER BY id DESC LIMIT 1"
        );
        $st->bindValue(':et', $eventType);
        $st->bindValue(':metaLike', '%"worker_id":' . $workerId . '%');
        $st->bindValue(':secs', max(1, $seconds), PDO::PARAM_INT);
        $st->execute();
        return (bool) $st->fetchColumn();
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
