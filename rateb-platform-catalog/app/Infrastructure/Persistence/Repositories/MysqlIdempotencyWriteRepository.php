<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;

final class MysqlIdempotencyWriteRepository extends BaseRepository implements IdempotencyWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'idempotency_records';
    }

    public function store(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        int $responseStatus,
        ?array $responseBody,
        \DateTimeImmutable $expiresAt
    ): void {
        $this->writePdo->prepare(
            'INSERT INTO idempotency_records (idempotency_key, scope, request_hash, response_status, response_body, expires_at)
             VALUES (:idempotency_key, :scope, :request_hash, :response_status, :response_body, :expires_at)
             ON DUPLICATE KEY UPDATE
                request_hash = VALUES(request_hash),
                response_status = VALUES(response_status),
                response_body = VALUES(response_body),
                expires_at = VALUES(expires_at)'
        )->execute([
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
            'request_hash' => $requestHash,
            'response_status' => $responseStatus,
            'response_body' => $responseBody === null ? null : (json_encode($responseBody, JSON_UNESCAPED_UNICODE) ?: '{}'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
        ]);
    }
}
