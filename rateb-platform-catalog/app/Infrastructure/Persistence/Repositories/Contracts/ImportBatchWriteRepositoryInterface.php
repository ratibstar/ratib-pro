<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ImportBatchWriteRepositoryInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function createBatch(array $data): string;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function insertRows(string $batchUuid, array $rows): void;

    public function updateBatchStatus(string $batchUuid, string $status, array $counts = []): void;

    /**
     * @param array<string, mixed> $errors
     */
    public function updateRowValidation(string $rowUuid, string $status, ?array $mappedPayload, ?array $errors): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function claimRowsForCommit(string $batchUuid, int $limit): array;

    public function markRowCommitted(string $rowUuid, string $entityUuid): void;

    public function markBatchCommitted(string $batchUuid): void;

    public function markBatchRolledBack(string $batchUuid): void;
}
