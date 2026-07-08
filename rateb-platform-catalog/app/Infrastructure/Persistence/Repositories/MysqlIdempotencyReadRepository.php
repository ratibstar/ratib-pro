<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;

final class MysqlIdempotencyReadRepository extends BaseRepository implements IdempotencyReadRepositoryInterface
{
    protected function table(): string
    {
        return 'idempotency_records';
    }

    public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array
    {
        return $this->fetchOne(
            'SELECT idempotency_key, scope, request_hash, response_status, response_body, expires_at
             FROM idempotency_records
             WHERE idempotency_key = :idempotency_key AND scope = :scope
             LIMIT 1',
            ['idempotency_key' => $idempotencyKey, 'scope' => $scope]
        );
    }

    public function deleteExpired(): int
    {
        $stmt = $this->writePdo->prepare(
            'DELETE FROM idempotency_records WHERE expires_at < CURRENT_TIMESTAMP(6)'
        );
        $stmt->execute();

        return $stmt->rowCount();
    }
}
