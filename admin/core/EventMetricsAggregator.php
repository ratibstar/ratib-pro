<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventMetricsAggregator.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventMetricsAggregator.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventRepository.php';

/**
 * Lightweight per-minute counters for live observability.
 * Uses APCu when available; otherwise request-local static (accurate within single PHP process only).
 */
final class EventMetricsAggregator
{
    private const APC_PREFIX = 'ratib_evm:';

    /** @var array<string, int> */
    private static array $localMinute = [];

    public static function record(string $eventType, string $level, ?int $tenantId): void
    {
        $minute = (int) floor(time() / 60);
        $bucket = $minute;

        if (function_exists('apcu_inc')) {
            $ttl = 180;
            apcu_inc(self::APC_PREFIX . 'evt:' . $bucket, 1, $success, $ttl);
            if (in_array(strtolower($level), ['error', 'critical'], true)) {
                apcu_inc(self::APC_PREFIX . 'err:' . $bucket, 1, $success2, $ttl);
            }
            if ($eventType === 'QUERY_EXECUTED') {
                apcu_inc(self::APC_PREFIX . 'qry:' . $bucket, 1, $success3, $ttl);
            }
            if ($tenantId !== null && $tenantId > 0) {
                apcu_inc(self::APC_PREFIX . 'ten:' . $tenantId . ':' . $bucket, 1, $success4, $ttl);
            }
            return;
        }

        $k = (string) $bucket;
        self::$localMinute[$k] = (self::$localMinute[$k] ?? 0) + 1;
    }

    /**
     * @return array{
     *   events_per_minute: int,
     *   error_count_minute: int,
     *   query_throughput_minute: int,
     *   active_tenants_estimate: int,
     *   error_rate_percent: float,
     *   backend: string
     * }
     */
    public static function snapshot(PDO $pdo): array
    {
        $minute = (int) floor(time() / 60);
        $events = 0;
        $errors = 0;
        $queries = 0;
        $activeTenants = 0;
        $backend = 'db_fallback';

        if (function_exists('apcu_fetch')) {
            $backend = 'apcu';
            $events = (int) (apcu_fetch(self::APC_PREFIX . 'evt:' . $minute) ?: 0);
            $errors = (int) (apcu_fetch(self::APC_PREFIX . 'err:' . $minute) ?: 0);
            $queries = (int) (apcu_fetch(self::APC_PREFIX . 'qry:' . $minute) ?: 0);
        } else {
            try {
                $events = EventRepository::countEventsSince($pdo, 60);
                $errors = EventRepository::countErrorsSince($pdo, 60);
                $queries = EventRepository::countQueryExecutedSince($pdo, 60);
            } catch (Throwable $e) {
                $events = self::$localMinute[(string) $minute] ?? 0;
            }
        }

        try {
            $activeTenants = EventRepository::countDistinctTenantsSince($pdo, 60);
        } catch (Throwable $e) {
            $activeTenants = 0;
        }

        $errPct = $events > 0 ? round(($errors / $events) * 100, 2) : 0.0;

        return [
            'events_per_minute' => $events,
            'error_count_minute' => $errors,
            'query_throughput_minute' => $queries,
            'active_tenants_estimate' => $activeTenants,
            'error_rate_percent' => $errPct,
            'backend' => $backend,
        ];
    }
}
