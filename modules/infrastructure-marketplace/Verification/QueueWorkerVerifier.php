<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Verification;

use Ratib\InfrastructureMarketplace\Config\ModuleConfig;

final class QueueWorkerVerifier
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $depth = $this->countByStatuses(['QUEUED', 'RUNNING', 'RETRYING']);
        $dead = $this->countByStatuses(['DEAD_LETTER']);
        $recon = $this->countByStatuses(['RECONCILING']);
        $stuck = $this->countStuckRunning(20);
        $staleWorkers = $this->countStaleWorkers(120);

        $checks = [
            ['name' => 'queue_depth', 'status' => $depth <= ModuleConfig::queuePressureThreshold() ? 'PASS' : 'WARN', 'value' => $depth],
            ['name' => 'dead_letter_pressure', 'status' => $dead === 0 ? 'PASS' : 'WARN', 'value' => $dead],
            ['name' => 'reconciliation_backlog', 'status' => $recon === 0 ? 'PASS' : 'WARN', 'value' => $recon],
            ['name' => 'stuck_jobs', 'status' => $stuck === 0 ? 'PASS' : 'WARN', 'value' => $stuck],
            ['name' => 'stale_worker_heartbeats', 'status' => $staleWorkers === 0 ? 'PASS' : 'FAIL', 'value' => $staleWorkers],
        ];

        return [
            'checks' => $checks,
            'summary' => [
                'depth' => $depth,
                'dead_letter' => $dead,
                'reconciling' => $recon,
                'stuck_jobs' => $stuck,
                'stale_workers' => $staleWorkers,
            ],
        ];
    }

    /**
     * @param list<string> $statuses
     */
    private function countByStatuses(array $statuses): int
    {
        $in = implode(',', array_map(static fn(string $s): string => "'" . addslashes($s) . "'", $statuses));
        $sql = 'SELECT COUNT(*) c FROM ratib_infra_provisioning_jobs WHERE status IN (' . $in . ')';
        $stmt = $this->pdo->query($sql);
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
    }

    private function countStuckRunning(int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) c
             FROM ratib_infra_provisioning_jobs
             WHERE status = 'RUNNING'
               AND updated_at < DATE_SUB(NOW(), INTERVAL :mins MINUTE)"
        );
        $stmt->execute(['mins' => $minutes]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
    }

    private function countStaleWorkers(int $seconds): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) c
             FROM ratib_infra_worker_heartbeats
             WHERE heartbeat_at < DATE_SUB(NOW(), INTERVAL :sec SECOND)'
        );
        $stmt->execute(['sec' => $seconds]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? (int) ($row['c'] ?? 0) : 0;
    }
}

