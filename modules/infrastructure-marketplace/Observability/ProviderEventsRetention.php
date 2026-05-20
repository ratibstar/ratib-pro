<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Observability;

/**
 * Prunes ratib_infra_provider_events only — never touches ratib_infra_audit_entries.
 */
final class ProviderEventsRetention
{
    /** @var list<string> */
    private const CRITICAL_EVENTS = [
        'create',
        'suspend',
        'unsuspend',
        'terminate',
        'failures',
        'retries',
        'dns_updates',
        'ssl_renewals',
        'renewals',
    ];

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, int>
     */
    public function run(bool $dryRun = false): array
    {
        if (!$this->tableExists('ratib_infra_provider_events')) {
            return ['health_check' => 0, 'success_non_critical' => 0, 'success_critical_old' => 0, 'total' => 0, 'skipped' => 1];
        }

        $healthDays = $this->envInt('RATIB_INFRA_EVENTS_RETAIN_HEALTH_DAYS', 7);
        $successDays = $this->envInt('RATIB_INFRA_EVENTS_RETAIN_SUCCESS_DAYS', 14);
        $criticalDays = $this->envInt('RATIB_INFRA_EVENTS_RETAIN_CRITICAL_DAYS', 90);

        // Never delete failure/retry/degraded rows — operational incidents must remain auditable.
        $deleted = [
            'health_check' => $this->purge(
                'event_name = :event AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
                ['event' => 'health_check', 'days' => $healthDays],
                $dryRun
            ),
            'success_non_critical' => $this->purge(
                'status = :status
                 AND event_name NOT IN (' . $this->criticalInList() . ')
                 AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
                ['status' => 'success', 'days' => $successDays],
                $dryRun
            ),
            'success_critical_old' => $this->purge(
                'status = :status
                 AND event_name IN (' . $this->criticalInList() . ')
                 AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
                ['status' => 'success', 'days' => $criticalDays],
                $dryRun
            ),
        ];
        $deleted['total'] = $deleted['health_check'] + $deleted['success_non_critical'] + $deleted['success_critical_old'];

        return $deleted;
    }

    private function purge(string $where, array $params, bool $dryRun): int
    {
        $countSql = 'SELECT COUNT(*) FROM ratib_infra_provider_events WHERE ' . $where;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $count = (int) ($countStmt->fetchColumn() ?: 0);
        if ($dryRun || $count === 0) {
            return $count;
        }
        $deleteSql = 'DELETE FROM ratib_infra_provider_events WHERE ' . $where . ' LIMIT 5000';
        $deleted = 0;
        while (true) {
            $stmt = $this->pdo->prepare($deleteSql);
            $stmt->execute($params);
            $batch = $stmt->rowCount();
            $deleted += $batch;
            if ($batch < 5000) {
                break;
            }
        }

        return $deleted;
    }

    private function criticalInList(): string
    {
        $quoted = [];
        foreach (self::CRITICAL_EVENTS as $event) {
            $quoted[] = $this->pdo->quote($event);
        }

        return implode(',', $quoted);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));

        return $stmt instanceof \PDOStatement && $stmt->fetchColumn() !== false;
    }

    private function envInt(string $key, int $default): int
    {
        $raw = getenv($key);
        if ($raw === false || trim((string) $raw) === '') {
            return $default;
        }
        $n = (int) $raw;

        return $n > 0 ? $n : $default;
    }
}
