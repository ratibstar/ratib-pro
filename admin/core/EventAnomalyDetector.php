<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventAnomalyDetector.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventAnomalyDetector.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventRepository.php';

/**
 * Throttled anomaly checks after inserts. Avoids per-event heavy queries via APCu / sampling.
 */
final class EventAnomalyDetector
{
    private const APC_THROTTLE_PREFIX = 'ratib_ano_th:';

    private static function allowDbProbe(string $probeKey, int $ttlSeconds = 4): bool
    {
        if (function_exists('apcu_fetch')) {
            $k = self::APC_THROTTLE_PREFIX . $probeKey;
            if (apcu_fetch($k) !== false) {
                return false;
            }
            apcu_store($k, time(), max(2, min(30, $ttlSeconds)));
            return true;
        }

        return random_int(1, 10) <= 2;
    }

    /**
     * @param array<string, mixed> $payloadMeta Already masked/truncated metadata persisted with the row.
     */
    public static function evaluateAfterInsert(
        PDO $pdo,
        string $typeNorm,
        string $normLevel,
        string $message,
        array $payloadMeta
    ): void {
        if (str_starts_with($typeNorm, 'ANOMALY_')) {
            return;
        }

        $tenantId = isset($payloadMeta['tenant_id']) && (int) $payloadMeta['tenant_id'] > 0
            ? (int) $payloadMeta['tenant_id']
            : null;

        if (in_array($normLevel, ['error', 'critical'], true)) {
            if (self::allowDbProbe('err_' . $typeNorm, 5)) {
                $cnt = EventRepository::countByTypeAndLevelsSince($pdo, $typeNorm, ['error', 'critical'], 60);
                if ($cnt > 10) {
                    emitEvent('ANOMALY_DETECTED', 'critical', 'Error spike detected for ' . $typeNorm, [
                        'anomaly' => 'error_spike',
                        'event_type' => $typeNorm,
                        'count' => $cnt,
                        'window_seconds' => 60,
                        'tenant_id' => $tenantId,
                        'source' => 'anomaly_detector',
                        'endpoint' => (string) ($payloadMeta['endpoint'] ?? ''),
                        'query' => null,
                        'mode' => null,
                        'duration_ms' => null,
                    ], $pdo);
                }
            }
        }

        if ($typeNorm === 'QUERY_EXECUTED' && self::allowDbProbe('qry_spike', 5)) {
            $q = EventRepository::countQueryExecutedSince($pdo, 60);
            if ($q > 500) {
                emitEvent('ANOMALY_DETECTED', 'warn', 'Query throughput spike', [
                    'anomaly' => 'query_spike',
                    'count' => $q,
                    'window_seconds' => 60,
                    'tenant_id' => $tenantId,
                    'source' => 'anomaly_detector',
                    'endpoint' => (string) ($payloadMeta['endpoint'] ?? ''),
                    'query' => null,
                    'mode' => null,
                    'duration_ms' => null,
                ], $pdo);
            }
        }

        if ($tenantId !== null && $tenantId > 0 && self::allowDbProbe('ten_' . $tenantId, 5)) {
            $tc = EventRepository::countByTenantSince($pdo, $tenantId, 60);
            if ($tc > 200) {
                emitEvent('ANOMALY_DETECTED', 'warn', 'Suspicious tenant event volume', [
                    'anomaly' => 'tenant_volume',
                    'tenant_id' => $tenantId,
                    'count' => $tc,
                    'window_seconds' => 60,
                    'source' => 'anomaly_detector',
                    'endpoint' => (string) ($payloadMeta['endpoint'] ?? ''),
                    'query' => null,
                    'mode' => null,
                    'duration_ms' => null,
                ], $pdo);
            }
        }

        $msgKey = substr($message, 0, 200);
        if ($msgKey !== '' && in_array($normLevel, ['error', 'warn', 'critical'], true)) {
            $probe = 'dup_' . hash('sha256', $typeNorm . '|' . $msgKey);
            if (self::allowDbProbe($probe, 6)) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) AS c FROM system_events
                     WHERE event_type = :t AND message = :m
                       AND created_at > (NOW() - INTERVAL 120 SECOND)'
                );
                $stmt->execute([':t' => $typeNorm, ':m' => $message]);
                $dup = (int) (($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0));
                if ($dup > 15) {
                    emitEvent('ANOMALY_DETECTED', 'critical', 'Repeated identical event pattern', [
                        'anomaly' => 'repeated_event',
                        'event_type' => $typeNorm,
                        'repeat_count' => $dup,
                        'window_seconds' => 120,
                        'tenant_id' => $tenantId,
                        'source' => 'anomaly_detector',
                        'endpoint' => (string) ($payloadMeta['endpoint'] ?? ''),
                        'query' => null,
                        'mode' => null,
                        'duration_ms' => null,
                    ], $pdo);
                }
            }
        }
    }
}
