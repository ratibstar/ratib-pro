<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Application\Support\IdempotencyAcquireResult;
use Rateb\PlatformCatalog\Application\Support\IdempotencyResponseCachePolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;

final class MysqlIdempotencyWriteRepository extends BaseRepository implements IdempotencyWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'idempotency_records';
    }

    public function acquire(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        \DateTimeImmutable $expiresAt
    ): IdempotencyAcquireResult {
        return $this->transaction(function () use ($idempotencyKey, $scope, $requestHash, $expiresAt): IdempotencyAcquireResult {
            $row = $this->fetchRowForUpdate($idempotencyKey, $scope);

            if ($row === null) {
                if ($this->insertPendingIfAbsent($idempotencyKey, $scope, $requestHash, $expiresAt)) {
                    return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
                }

                $row = $this->fetchRowForUpdate($idempotencyKey, $scope);
                if ($row === null) {
                    throw new \RuntimeException('Idempotency acquisition failed after concurrent insert');
                }
            }

            return $this->evaluateActiveRow($row, $idempotencyKey, $scope, $requestHash, $expiresAt);
        });
    }

    public function finalize(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        int $responseStatus,
        ?array $responseBody,
        \DateTimeImmutable $expiresAt
    ): void {
        $this->writePdo->prepare(
            'UPDATE idempotency_records
             SET request_hash = :request_hash,
                 response_status = :response_status,
                 response_body = :response_body,
                 expires_at = :expires_at
             WHERE idempotency_key = :idempotency_key AND scope = :scope'
        )->execute([
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
            'request_hash' => $requestHash,
            'response_status' => $responseStatus,
            'response_body' => $responseBody === null ? null : (json_encode($responseBody, JSON_UNESCAPED_UNICODE) ?: '{}'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function abandon(string $idempotencyKey, string $scope): void
    {
        $this->writePdo->prepare(
            'DELETE FROM idempotency_records
             WHERE idempotency_key = :idempotency_key AND scope = :scope'
        )->execute([
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
        ]);
    }

    public function store(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        int $responseStatus,
        ?array $responseBody,
        \DateTimeImmutable $expiresAt
    ): void {
        $this->finalize($idempotencyKey, $scope, $requestHash, $responseStatus, $responseBody, $expiresAt);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function evaluateActiveRow(
        array $row,
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        \DateTimeImmutable $expiresAt
    ): IdempotencyAcquireResult {
        if (!$this->isActiveRow($row)) {
            $this->deleteRow($idempotencyKey, $scope);
            if ($this->insertPendingIfAbsent($idempotencyKey, $scope, $requestHash, $expiresAt)) {
                return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
            }

            $row = $this->fetchRowForUpdate($idempotencyKey, $scope);
            if ($row === null) {
                throw new \RuntimeException('Idempotency acquisition failed after concurrent insert');
            }

            return $this->evaluateActiveRow($row, $idempotencyKey, $scope, $requestHash, $expiresAt);
        }

        $storedHash = (string) ($row['request_hash'] ?? '');
        if ($storedHash !== '' && !hash_equals($storedHash, $requestHash)) {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::HASH_CONFLICT);
        }

        if ($row['response_status'] === null) {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::IN_PROGRESS);
        }

        $status = (int) $row['response_status'];
        if (IdempotencyResponseCachePolicy::shouldCache($status)) {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::REPLAY, $row);
        }

        $this->deleteRow($idempotencyKey, $scope);
        if ($this->insertPendingIfAbsent($idempotencyKey, $scope, $requestHash, $expiresAt)) {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
        }

        $row = $this->fetchRowForUpdate($idempotencyKey, $scope);
        if ($row === null) {
            throw new \RuntimeException('Idempotency acquisition failed after concurrent insert');
        }

        return $this->evaluateActiveRow($row, $idempotencyKey, $scope, $requestHash, $expiresAt);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRowForUpdate(string $idempotencyKey, string $scope): ?array
    {
        return $this->fetchOne(
            'SELECT idempotency_key, scope, request_hash, response_status, response_body, expires_at
             FROM idempotency_records
             WHERE idempotency_key = :idempotency_key AND scope = :scope
             LIMIT 1 FOR UPDATE',
            ['idempotency_key' => $idempotencyKey, 'scope' => $scope],
            false
        );
    }

    private function insertPendingIfAbsent(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        $stmt = $this->writePdo->prepare(
            'INSERT IGNORE INTO idempotency_records (idempotency_key, scope, request_hash, response_status, response_body, expires_at)
             VALUES (:idempotency_key, :scope, :request_hash, NULL, NULL, :expires_at)'
        );
        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
            'request_hash' => $requestHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s.u'),
        ]);

        return $stmt->rowCount() === 1;
    }

    private function deleteRow(string $idempotencyKey, string $scope): void
    {
        $this->writePdo->prepare(
            'DELETE FROM idempotency_records
             WHERE idempotency_key = :idempotency_key AND scope = :scope'
        )->execute([
            'idempotency_key' => $idempotencyKey,
            'scope' => $scope,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isActiveRow(array $row): bool
    {
        $expiresAt = (string) ($row['expires_at'] ?? '');
        if ($expiresAt === '') {
            return false;
        }

        return strtotime($expiresAt) >= time();
    }
}
