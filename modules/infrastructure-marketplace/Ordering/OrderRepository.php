<?php
declare(strict_types=1);

namespace RATEB\InfrastructureMarketplace\Ordering;

final class OrderRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }


    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_infra_orders
            (public_id, tenant_id, agency_id, sku, status, idempotency_key, currency, amount, payload_json, created_at, updated_at)
            VALUES
            (:public_id, :tenant_id, :agency_id, :sku, :status, :idempotency_key, :currency, :amount, :payload_json, NOW(), NOW())'
        );
        $stmt->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function findByIdempotency(string $idempotencyKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rateb_infra_orders WHERE idempotency_key = :idempotency_key LIMIT 1');
        $stmt->execute(['idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rateb_infra_orders WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function markQueued(int $id, string $jobPublicId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_infra_orders
             SET status = :status, provisioning_job_public_id = :job_public_id, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'status' => 'QUEUED',
            'job_public_id' => $jobPublicId,
            'id' => $id,
        ]);
    }

    /**
     * Additive status updates for commerce execution paths (caller supplies allowed values).
     */
    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE rateb_infra_orders SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'status' => strtoupper(trim($status)),
            'id' => $id,
        ]);
    }
}

