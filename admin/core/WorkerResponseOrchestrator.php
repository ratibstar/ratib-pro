<?php
declare(strict_types=1);

require_once __DIR__ . '/EventBus.php';

final class WorkerResponseOrchestrator
{
    public static function evaluate(
        PDO $pdo,
        int $workerId,
        int $tenantId,
        string $recordedAt,
        int $finalThreatScore,
        string $threatLevel,
        array $context = []
    ): array {
        $cacheKey = 'response_orch:' . $tenantId . ':' . $workerId;
        $cached = self::cacheGet($cacheKey);
        if (
            is_array($cached)
            && (string) ($cached['recorded_at'] ?? '') === $recordedAt
            && ((int) ($cached['final_threat_score'] ?? -1) === $finalThreatScore)
            && isset($cached['response'])
            && is_array($cached['response'])
        ) {
            return $cached['response'];
        }

        $level = strtoupper(trim($threatLevel));
        $action = self::baseAction($finalThreatScore, $level);

        $hasSos = self::recentEventSeconds($pdo, 'WORKER_SOS', $workerId, 20);
        $hasSpoofConfirmed = self::recentEventSeconds($pdo, 'WORKER_GPS_SPOOF_CONFIRMED', $workerId, 20);
        $hasBreachPattern = self::recentEventSeconds($pdo, 'WORKER_GEOFENCE_BREACH_PATTERN', $workerId, 20)
            || !empty($context['geofence_breach_pattern']);
        $incomingEvents = self::normalizeEvents($context['event_type'] ?? []);
        if (in_array('WORKER_SOS', $incomingEvents, true)) {
            $hasSos = true;
        }
        if (in_array('WORKER_GPS_SPOOF_CONFIRMED', $incomingEvents, true)) {
            $hasSpoofConfirmed = true;
        }
        if (in_array('WORKER_GEOFENCE_BREACH_PATTERN', $incomingEvents, true)) {
            $hasBreachPattern = true;
        }

        if ($hasSos) {
            $action = [
                'action_type' => 'EMERGENCY',
                'priority' => 'MAX',
                'auto_focus_map' => true,
                'notification_level' => 'urgent',
                'suggested_response' => 'Immediate intervention required',
            ];
        } elseif ($hasSpoofConfirmed && in_array($action['action_type'], ['NONE', 'MONITOR'], true)) {
            $action = [
                'action_type' => 'ALERT_CONTROL',
                'priority' => 'HIGH',
                'auto_focus_map' => true,
                'notification_level' => 'alert',
                'suggested_response' => 'Review worker movement immediately',
            ];
        }
        if ($hasBreachPattern && in_array($action['action_type'], ['ALERT_CONTROL', 'EMERGENCY'], true)) {
            $action = self::escalateOneLevel($action);
        }

        $result = [
            'action_type' => $action['action_type'],
            'priority' => $action['priority'],
            'auto_focus_map' => (bool) $action['auto_focus_map'],
            'notification_level' => $action['notification_level'],
            'suggested_response' => $action['suggested_response'],
        ];

        if (!self::recentEventSeconds($pdo, 'WORKER_RESPONSE_ACTION', $workerId, 10)) {
            emitEvent('WORKER_RESPONSE_ACTION', $result['action_type'] === 'EMERGENCY' ? 'critical' : 'info', 'Worker response action updated', [
                'worker_id' => $workerId,
                'tenant_id' => $tenantId,
                'response_action' => $result['action_type'],
                'response_priority' => $result['priority'],
                'auto_focus_map' => $result['auto_focus_map'],
                'notification_level' => $result['notification_level'],
                'suggested_response' => $result['suggested_response'],
                'priority' => $result['priority'],
                'source' => 'worker_tracking',
                'request_id' => getRequestId(),
            ], $pdo);
        }

        self::cacheSet($cacheKey, [
            'recorded_at' => $recordedAt,
            'final_threat_score' => $finalThreatScore,
            'response' => $result,
        ], 12);
        return $result;
    }

    private static function baseAction(int $score, string $level): array
    {
        if ($score <= 40 || $level === 'NORMAL') {
            return [
                'action_type' => 'NONE',
                'priority' => 'LOW',
                'auto_focus_map' => false,
                'notification_level' => 'none',
                'suggested_response' => 'Continue passive monitoring',
            ];
        }
        if ($score <= 70 || $level === 'ELEVATED') {
            return [
                'action_type' => 'MONITOR',
                'priority' => 'MEDIUM',
                'auto_focus_map' => false,
                'notification_level' => 'soft',
                'suggested_response' => 'Continue passive monitoring',
            ];
        }
        if ($score <= 89 || $level === 'HIGH') {
            return [
                'action_type' => 'ALERT_CONTROL',
                'priority' => 'HIGH',
                'auto_focus_map' => true,
                'notification_level' => 'alert',
                'suggested_response' => 'Review worker movement immediately',
            ];
        }
        return [
            'action_type' => 'EMERGENCY',
            'priority' => 'CRITICAL',
            'auto_focus_map' => true,
            'notification_level' => 'urgent',
            'suggested_response' => 'Immediate intervention required',
        ];
    }

    private static function escalateOneLevel(array $action): array
    {
        if (($action['action_type'] ?? '') === 'ALERT_CONTROL') {
            return [
                'action_type' => 'EMERGENCY',
                'priority' => 'CRITICAL',
                'auto_focus_map' => true,
                'notification_level' => 'urgent',
                'suggested_response' => 'Immediate intervention required',
            ];
        }
        if (($action['action_type'] ?? '') === 'EMERGENCY' && ($action['priority'] ?? '') !== 'MAX') {
            $action['priority'] = 'MAX';
            $action['notification_level'] = 'urgent';
            $action['auto_focus_map'] = true;
        }
        return $action;
    }

    private static function normalizeEvents($eventType): array
    {
        $events = [];
        if (is_string($eventType) && trim($eventType) !== '') {
            $events[] = strtoupper(trim($eventType));
        } elseif (is_array($eventType)) {
            foreach ($eventType as $v) {
                if (!is_string($v)) {
                    continue;
                }
                $n = strtoupper(trim($v));
                if ($n !== '') {
                    $events[] = $n;
                }
            }
        }
        return array_values(array_unique($events));
    }

    private static function recentEventSeconds(PDO $pdo, string $eventType, int $workerId, int $seconds): bool
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
