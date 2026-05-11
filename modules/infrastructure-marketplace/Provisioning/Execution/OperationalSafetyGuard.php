<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Execution;

final class OperationalSafetyGuard
{
    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function assertNoQueueStorm(int $maxQueued): void
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) c FROM ratib_infra_provisioning_jobs WHERE status IN ('QUEUED','RETRYING','RUNNING')");
        $row = $stmt instanceof \PDOStatement ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $count = is_array($row) ? (int) ($row['c'] ?? 0) : 0;
        if ($count > $maxQueued) {
            throw new \RuntimeException('Queue pressure protection triggered.');
        }
    }

    public function assertIdempotencyUnused(string $idempotencyKey): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ratib_infra_orders WHERE idempotency_key = :idempotency_key LIMIT 1');
        $stmt->execute(['idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (is_array($row)) {
            throw new \RuntimeException('Duplicate order prevention triggered.');
        }
    }
}

