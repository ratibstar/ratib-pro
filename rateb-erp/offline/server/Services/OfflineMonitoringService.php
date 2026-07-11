<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineDevice;
use Rateb\App\Offline\Models\OfflineEntityCursor;
use Rateb\App\Offline\Models\OfflineSyncConflict;
use Rateb\App\Offline\Models\OfflineSyncQueueItem;
use Rateb\App\Offline\Services\OfflineSchema;

/**
 * Read-only enterprise offline operations metrics (Phase 6).
 * Aggregates existing offline tables — no writes, no business logic changes.
 */
final class OfflineMonitoringService
{
    private ?OfflineFeatureFlagService $flags = null;
    private ?OfflineQueueService $queue = null;

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    private function queue(): OfflineQueueService
    {
        return $this->queue ??= new OfflineQueueService();
    }

    public function isMonitoringEnabled(): bool
    {
        return $this->flags()->enabled('offline.monitoring');
    }

    /**
     * Full ops snapshot for dashboard + API.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $available = $this->queue()->isAvailable();

        $queueHealth = $this->queueHealth($companyId);
        $devices = $this->deviceStatus($companyId);
        $syncMetrics = $this->synchronizationMetrics($companyId);
        $conflicts = $this->conflictDashboard($companyId);
        $retries = $this->retryDashboard($companyId);
        $replay = $this->replayHistory($companyId);
        $audit = $this->auditLogs($companyId);
        $worker = $this->backgroundWorkerMetrics($companyId);
        $alerts = $this->alerts($companyId, $queueHealth, $devices, $conflicts, $retries);
        $performance = $this->performanceMetrics($companyId, $syncMetrics);
        $readiness = $this->productionReadiness($companyId, $queueHealth, $devices, $conflicts);

        return [
            'monitoring_enabled' => $this->isMonitoringEnabled(),
            'master_enabled' => $this->flags()->isMasterEnabled(),
            'flags' => $this->flags()->snapshot(),
            'company_id' => $companyId,
            'migration_required' => !$available,
            'generated_at' => date('c'),
            'queue_health' => $queueHealth,
            'devices' => $devices,
            'sync_metrics' => $syncMetrics,
            'conflicts' => $conflicts,
            'retries' => $retries,
            'replay_history' => $replay,
            'audit_logs' => $audit,
            'background_worker' => $worker,
            'alerts' => $alerts,
            'performance' => $performance,
            'production_readiness' => $readiness,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueHealth(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if (!$this->queue()->isAvailable() || $companyId < 1) {
            return [
                'pending' => 0,
                'synced' => 0,
                'conflict' => 0,
                'failed' => 0,
                'depth' => 0,
                'by_module' => [],
                'migration_required' => !$this->queue()->isAvailable(),
                'healthy' => false,
            ];
        }

        $summary = $this->queue()->statusSummary($companyId);
        $byModule = $this->countGroup(
            'SELECT module, status, COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid GROUP BY module, status',
            ['cid' => $companyId]
        );

        $pending = (int) ($summary['pending'] ?? 0);
        $failed = (int) ($summary['failed'] ?? 0);
        $conflict = (int) ($summary['conflict'] ?? 0);
        $depth = $pending + $failed;

        return [
            'pending' => $pending,
            'synced' => (int) ($summary['synced'] ?? 0),
            'conflict' => $conflict,
            'failed' => $failed,
            'depth' => $depth,
            'last_sync' => $summary['last_sync'] ?? null,
            'by_module' => $byModule,
            'migration_required' => false,
            'healthy' => $failed < 25 && $depth < 500 && $conflict < 50,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deviceStatus(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1 || !OfflineSchema::hasColumn('rateb_offline_devices', 'id')) {
            return [
                'total' => 0,
                'by_status' => [],
                'recent' => [],
                'stale' => 0,
                'migration_required' => !OfflineSchema::hasColumn('rateb_offline_devices', 'id'),
            ];
        }

        $byStatus = $this->countSimple(
            'SELECT status, COUNT(*) AS c FROM rateb_offline_devices WHERE company_id = :cid GROUP BY status',
            ['cid' => $companyId]
        );
        $total = array_sum(array_map('intval', $byStatus));
        $stale = (int) (new OfflineDevice())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_devices
             WHERE company_id = :cid AND status = 'active'
               AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL 7 DAY))",
            ['cid' => $companyId]
        )['c'] ?? 0;

        $recent = (new OfflineDevice())->query(
            'SELECT id, device_id, label, branch_id, status, last_seen_at, activated_at, created_at
             FROM rateb_offline_devices WHERE company_id = :cid
             ORDER BY COALESCE(last_seen_at, created_at) DESC LIMIT 25',
            ['cid' => $companyId]
        );

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'stale_active' => $stale,
            'recent' => $recent,
            'migration_required' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function synchronizationMetrics(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1 || !$this->queue()->isAvailable()) {
            return [
                'synced_24h' => 0,
                'synced_7d' => 0,
                'failed_24h' => 0,
                'avg_retry' => 0,
                'by_action' => [],
            ];
        }

        $synced24 = (int) ((new OfflineSyncQueueItem())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status = 'synced'
               AND synced_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            ['cid' => $companyId]
        )['c'] ?? 0);
        $synced7 = (int) ((new OfflineSyncQueueItem())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status = 'synced'
               AND synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            ['cid' => $companyId]
        )['c'] ?? 0);
        $failed24 = (int) ((new OfflineSyncQueueItem())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status = 'failed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            ['cid' => $companyId]
        )['c'] ?? 0);
        $avgRetry = (float) ((new OfflineSyncQueueItem())->queryOne(
            'SELECT AVG(retry_count) AS a FROM rateb_offline_sync_queue WHERE company_id = :cid',
            ['cid' => $companyId]
        )['a'] ?? 0);

        $byAction = $this->countSimple(
            "SELECT action, COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status = 'synced'
               AND synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY action ORDER BY c DESC LIMIT 20",
            ['cid' => $companyId]
        );

        $cursors = [];
        if (OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id')) {
            $cursors = (new OfflineEntityCursor())->query(
                'SELECT entity_type, branch_id, cursor_token, updated_at
                 FROM rateb_offline_entity_cursors WHERE company_id = :cid
                 ORDER BY updated_at DESC LIMIT 20',
                ['cid' => $companyId]
            );
        }

        return [
            'synced_24h' => $synced24,
            'synced_7d' => $synced7,
            'failed_24h' => $failed24,
            'avg_retry' => round($avgRetry, 2),
            'by_action' => $byAction,
            'cursors' => $cursors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function conflictDashboard(?int $companyId = null, int $limit = 50): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1 || !OfflineSchema::hasColumn('rateb_offline_sync_conflicts', 'id')) {
            return [
                'open' => 0,
                'resolved' => 0,
                'by_reason' => [],
                'items' => [],
                'migration_required' => !OfflineSchema::hasColumn('rateb_offline_sync_conflicts', 'id'),
            ];
        }

        $open = (int) ((new OfflineSyncConflict())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_sync_conflicts
             WHERE company_id = :cid AND status = 'open'",
            ['cid' => $companyId]
        )['c'] ?? 0);
        $resolved = (int) ((new OfflineSyncConflict())->queryOne(
            "SELECT COUNT(*) AS c FROM rateb_offline_sync_conflicts
             WHERE company_id = :cid AND status <> 'open'",
            ['cid' => $companyId]
        )['c'] ?? 0);
        $byReason = $this->countSimple(
            "SELECT reason, COUNT(*) AS c FROM rateb_offline_sync_conflicts
             WHERE company_id = :cid AND status = 'open' GROUP BY reason",
            ['cid' => $companyId]
        );
        $safeLimit = max(1, min(100, $limit));
        $items = (new OfflineSyncConflict())->query(
            "SELECT id, queue_id, idempotency_key, reason, status, created_at, resolved_at, resolved_by
             FROM rateb_offline_sync_conflicts WHERE company_id = :cid
             ORDER BY FIELD(status,'open') DESC, id DESC LIMIT {$safeLimit}",
            ['cid' => $companyId]
        );

        return [
            'open' => $open,
            'resolved' => $resolved,
            'by_reason' => $byReason,
            'items' => $items,
            'migration_required' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retryDashboard(?int $companyId = null, int $limit = 50): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1 || !$this->queue()->isAvailable()) {
            return ['hotspots' => [], 'items' => [], 'high_retry_count' => 0];
        }

        $high = (int) ((new OfflineSyncQueueItem())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND retry_count >= 3',
            ['cid' => $companyId]
        )['c'] ?? 0);
        $safeLimit = max(1, min(100, $limit));
        $items = (new OfflineSyncQueueItem())->query(
            "SELECT id, module, action, status, retry_count, last_error, device_id, created_at, synced_at
             FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND (status IN ('failed','pending') OR retry_count > 0)
             ORDER BY retry_count DESC, id DESC LIMIT {$safeLimit}",
            ['cid' => $companyId]
        );
        $hotspots = $this->countSimple(
            "SELECT CONCAT(module, '.', action) AS k, COUNT(*) AS c
             FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND retry_count >= 1
             GROUP BY module, action ORDER BY c DESC LIMIT 15",
            ['cid' => $companyId]
        );

        return [
            'high_retry_count' => $high,
            'hotspots' => $hotspots,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function replayHistory(?int $companyId = null, int $limit = 50): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1 || !$this->queue()->isAvailable()) {
            return ['items' => []];
        }
        $safeLimit = max(1, min(100, $limit));
        $items = (new OfflineSyncQueueItem())->query(
            "SELECT id, module, action, status, idempotency_key, retry_count, device_id, branch_id,
                    created_at, synced_at, last_error
             FROM rateb_offline_sync_queue
             WHERE company_id = :cid AND status IN ('synced','conflict','failed')
             ORDER BY COALESCE(synced_at, created_at) DESC, id DESC LIMIT {$safeLimit}",
            ['cid' => $companyId]
        );

        return ['items' => $items];
    }

    /**
     * Derived audit trail from queue errors, conflicts, and device activation (read-only).
     *
     * @return array<string, mixed>
     */
    public function auditLogs(?int $companyId = null, int $limit = 40): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $events = [];

        if ($companyId > 0 && $this->queue()->isAvailable()) {
            $failed = (new OfflineSyncQueueItem())->query(
                "SELECT id, module, action, last_error, created_at, status
                 FROM rateb_offline_sync_queue
                 WHERE company_id = :cid AND last_error IS NOT NULL AND last_error <> ''
                 ORDER BY id DESC LIMIT 20",
                ['cid' => $companyId]
            );
            foreach ($failed as $row) {
                $events[] = [
                    'at' => $row['created_at'] ?? null,
                    'type' => 'queue_error',
                    'module' => $row['module'] ?? '',
                    'action' => $row['action'] ?? '',
                    'detail' => $row['last_error'] ?? '',
                    'ref_id' => (int) ($row['id'] ?? 0),
                ];
            }
        }

        if ($companyId > 0 && OfflineSchema::hasColumn('rateb_offline_sync_conflicts', 'id')) {
            $conflicts = (new OfflineSyncConflict())->query(
                'SELECT id, reason, status, created_at, resolved_at, resolved_by
                 FROM rateb_offline_sync_conflicts WHERE company_id = :cid
                 ORDER BY id DESC LIMIT 15',
                ['cid' => $companyId]
            );
            foreach ($conflicts as $row) {
                $events[] = [
                    'at' => $row['resolved_at'] ?? $row['created_at'] ?? null,
                    'type' => 'conflict',
                    'module' => 'offline',
                    'action' => (string) ($row['reason'] ?? ''),
                    'detail' => 'status=' . ($row['status'] ?? ''),
                    'ref_id' => (int) ($row['id'] ?? 0),
                ];
            }
        }

        if ($companyId > 0 && OfflineSchema::hasColumn('rateb_offline_devices', 'activated_at')) {
            $devs = (new OfflineDevice())->query(
                "SELECT id, device_id, status, activated_at, activated_by, created_at
                 FROM rateb_offline_devices WHERE company_id = :cid
                   AND (activated_at IS NOT NULL OR status IN ('revoked','pending'))
                 ORDER BY COALESCE(activated_at, created_at) DESC LIMIT 15",
                ['cid' => $companyId]
            );
            foreach ($devs as $row) {
                $events[] = [
                    'at' => $row['activated_at'] ?? $row['created_at'] ?? null,
                    'type' => 'device',
                    'module' => 'device',
                    'action' => (string) ($row['status'] ?? ''),
                    'detail' => (string) ($row['device_id'] ?? ''),
                    'ref_id' => (int) ($row['id'] ?? 0),
                ];
            }
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return ['items' => array_slice($events, 0, max(1, min(80, $limit)))];
    }

    /**
     * @return array<string, mixed>
     */
    public function backgroundWorkerMetrics(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $flags = $this->flags()->snapshot();
        $pending = 0;
        $processedHint = 0;
        if ($companyId > 0 && $this->queue()->isAvailable()) {
            $pending = (int) ((new OfflineSyncQueueItem())->queryOne(
                "SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
                 WHERE company_id = :cid AND status IN ('pending','failed')",
                ['cid' => $companyId]
            )['c'] ?? 0);
            $processedHint = (int) ((new OfflineSyncQueueItem())->queryOne(
                "SELECT COUNT(*) AS c FROM rateb_offline_sync_queue
                 WHERE company_id = :cid AND status = 'synced'
                   AND synced_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                ['cid' => $companyId]
            )['c'] ?? 0);
        }

        return [
            'master_enabled' => !empty($flags['offline.enabled']),
            'inventory_enabled' => !empty($flags['offline.inventory.movements']),
            'hr_enabled' => !empty($flags['offline.hr.attendance']),
            'procurement_enabled' => !empty($flags['offline.procurement']),
            'pending_backlog' => $pending,
            'synced_last_hour' => $processedHint,
            'batch_limit' => 50,
            'idle' => $pending === 0,
        ];
    }

    /**
     * @param array<string, mixed> $queueHealth
     * @param array<string, mixed> $devices
     * @param array<string, mixed> $conflicts
     * @param array<string, mixed> $retries
     * @return array{items: list<array<string, mixed>>, count: int}
     */
    public function alerts(
        ?int $companyId,
        array $queueHealth,
        array $devices,
        array $conflicts,
        array $retries
    ): array {
        $items = [];
        if (!empty($queueHealth['migration_required'])) {
            $items[] = ['severity' => 'critical', 'code' => 'MIGRATION_REQUIRED', 'message' => 'Offline sync tables missing'];
        }
        if ((int) ($queueHealth['failed'] ?? 0) >= 25) {
            $items[] = ['severity' => 'high', 'code' => 'QUEUE_FAILED_HIGH', 'message' => 'Failed queue items ≥ 25'];
        }
        if ((int) ($queueHealth['depth'] ?? 0) >= 500) {
            $items[] = ['severity' => 'high', 'code' => 'QUEUE_DEPTH_HIGH', 'message' => 'Queue depth ≥ 500'];
        }
        if ((int) ($conflicts['open'] ?? 0) >= 20) {
            $items[] = ['severity' => 'medium', 'code' => 'CONFLICTS_OPEN', 'message' => 'Open conflicts ≥ 20'];
        }
        if ((int) ($retries['high_retry_count'] ?? 0) >= 10) {
            $items[] = ['severity' => 'medium', 'code' => 'RETRY_HOTSPOTS', 'message' => 'Items with retry_count ≥ 3'];
        }
        if ((int) ($devices['stale_active'] ?? 0) >= 5) {
            $items[] = ['severity' => 'low', 'code' => 'STALE_DEVICES', 'message' => 'Active devices unseen ≥ 7 days'];
        }
        if (!$this->flags()->isMasterEnabled() && (int) ($queueHealth['pending'] ?? 0) > 0) {
            $items[] = ['severity' => 'medium', 'code' => 'MASTER_OFF_WITH_PENDING', 'message' => 'Master offline flag OFF but pending items exist'];
        }

        return ['items' => $items, 'count' => count($items)];
    }

    /**
     * @param array<string, mixed> $syncMetrics
     * @return array<string, mixed>
     */
    public function performanceMetrics(?int $companyId, array $syncMetrics): array
    {
        $synced24 = (int) ($syncMetrics['synced_24h'] ?? 0);
        $failed24 = (int) ($syncMetrics['failed_24h'] ?? 0);
        $denom = max(1, $synced24 + $failed24);
        $successRate = round(100 * $synced24 / $denom, 2);

        return [
            'synced_24h' => $synced24,
            'failed_24h' => $failed24,
            'success_rate_24h_pct' => $successRate,
            'avg_retry' => $syncMetrics['avg_retry'] ?? 0,
            'throughput_per_hour_est' => round($synced24 / 24, 2),
        ];
    }

    /**
     * @param array<string, mixed> $queueHealth
     * @param array<string, mixed> $devices
     * @param array<string, mixed> $conflicts
     * @return array<string, mixed>
     */
    public function productionReadiness(?int $companyId, array $queueHealth, array $devices, array $conflicts): array
    {
        $checks = [
            ['id' => 'tables', 'ok' => empty($queueHealth['migration_required']), 'label' => 'Offline tables present'],
            ['id' => 'monitoring_flag', 'ok' => $this->isMonitoringEnabled(), 'label' => 'offline.monitoring enabled'],
            ['id' => 'queue_healthy', 'ok' => !empty($queueHealth['healthy']), 'label' => 'Queue health within thresholds'],
            ['id' => 'conflicts_ok', 'ok' => (int) ($conflicts['open'] ?? 0) < 50, 'label' => 'Open conflicts under control'],
            ['id' => 'devices_registry', 'ok' => empty($devices['migration_required']), 'label' => 'Device registry available'],
            ['id' => 'flags_snapshot', 'ok' => true, 'label' => 'Feature flags readable'],
        ];
        $pass = count(array_filter($checks, static fn ($c) => !empty($c['ok'])));
        $total = count($checks);
        $score = $total > 0 ? round(10 * $pass / $total, 1) : 0.0;

        return [
            'score' => $score,
            'passed' => $pass,
            'total' => $total,
            'checks' => $checks,
            'verdict' => $score >= 8.0 ? 'READY' : ($score >= 6.0 ? 'CONDITIONAL' : 'NOT_READY'),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, int>
     */
    private function countSimple(string $sql, array $params): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $out = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $key = (string) ($row['status'] ?? $row['reason'] ?? $row['action'] ?? $row['k'] ?? $row['module'] ?? 'unknown');
                $out[$key] = (int) ($row['c'] ?? 0);
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function countGroup(string $sql, array $params): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return array_map(static function (array $row): array {
                return [
                    'module' => (string) ($row['module'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'count' => (int) ($row['c'] ?? 0),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
