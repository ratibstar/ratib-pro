<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchWriteRepositoryInterface;

final class MysqlImportBatchWriteRepository extends BaseRepository implements ImportBatchWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'import_batches';
    }

    public function createBatch(array $data): string
    {
        $uuid = $this->newUuid();
        $this->writePdo->prepare(
            'INSERT INTO import_batches
             (uuid, import_source_id, source_file_path, source_checksum, status, total_rows, parser_config, created_by)
             VALUES (:uuid, :import_source_id, :source_file_path, :source_checksum, :status, :total_rows, :parser_config, :created_by)'
        )->execute([
            'uuid' => $uuid,
            'import_source_id' => (int) $data['import_source_id'],
            'source_file_path' => $data['source_file_path'] ?? null,
            'source_checksum' => (string) $data['source_checksum'],
            'status' => (string) ($data['status'] ?? 'uploaded'),
            'total_rows' => (int) ($data['total_rows'] ?? 0),
            'parser_config' => json_encode($data['parser_config'] ?? [], JSON_UNESCAPED_UNICODE) ?: '{}',
            'created_by' => $data['created_by'] ?? null,
        ]);

        return $uuid;
    }

    public function insertRows(string $batchUuid, array $rows): void
    {
        $batchId = $this->resolveBatchId($batchUuid);
        $stmt = $this->writePdo->prepare(
            'INSERT INTO import_batch_rows (uuid, import_batch_id, `row_number`, raw_payload, status, created_by)
             VALUES (:uuid, :import_batch_id, :row_number, :raw_payload, :status, :created_by)'
        );

        foreach ($rows as $row) {
            $stmt->execute([
                'uuid' => $this->newUuid(),
                'import_batch_id' => $batchId,
                'row_number' => (int) $row['row_number'],
                'raw_payload' => json_encode($row['raw_payload'] ?? [], JSON_UNESCAPED_UNICODE) ?: '{}',
                'status' => (string) ($row['status'] ?? 'pending'),
                'created_by' => $row['created_by'] ?? null,
            ]);
        }
    }

    public function updateBatchStatus(string $batchUuid, string $status, array $counts = []): void
    {
        $sets = ['status = :status', 'updated_at = CURRENT_TIMESTAMP(6)'];
        $params = ['uuid' => $batchUuid, 'status' => $status];

        foreach (['total_rows', 'valid_rows', 'error_rows'] as $field) {
            if (array_key_exists($field, $counts)) {
                $sets[] = $field . ' = :' . $field;
                $params[$field] = (int) $counts[$field];
            }
        }

        $this->writePdo->prepare(
            'UPDATE import_batches SET ' . implode(', ', $sets) . '
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute($params);
    }

    public function updateRowValidation(string $rowUuid, string $status, ?array $mappedPayload, ?array $errors): void
    {
        $this->writePdo->prepare(
            'UPDATE import_batch_rows
             SET status = :status,
                 mapped_payload = :mapped_payload,
                 validation_errors = :validation_errors,
                 updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute([
            'uuid' => $rowUuid,
            'status' => $status,
            'mapped_payload' => $mappedPayload === null ? null : (json_encode($mappedPayload, JSON_UNESCAPED_UNICODE) ?: '{}'),
            'validation_errors' => $errors === null ? null : (json_encode($errors, JSON_UNESCAPED_UNICODE) ?: '{}'),
        ]);
    }

    public function claimRowsForCommit(string $batchUuid, int $limit): array
    {
        return $this->transaction(function () use ($batchUuid, $limit): array {
            $limit = max(1, min(500, $limit));
            $rows = $this->fetchAll(
                'SELECT ibr.uuid, ibr.`row_number`, ibr.raw_payload, ibr.mapped_payload, ibr.status
                 FROM import_batch_rows ibr
                 INNER JOIN import_batches ib ON ib.id = ibr.import_batch_id AND ib.deleted_at IS NULL
                 WHERE ib.uuid = :batch_uuid
                   AND ibr.status = :status
                   AND ibr.deleted_at IS NULL
                 ORDER BY ibr.`row_number` ASC
                 LIMIT ' . $limit . ' FOR UPDATE',
                ['batch_uuid' => $batchUuid, 'status' => 'valid'],
                false
            );

            return array_map(function (array $row): array {
                foreach (['raw_payload', 'mapped_payload'] as $field) {
                    $decoded = json_decode((string) ($row[$field] ?? '{}'), true);
                    $row[$field] = is_array($decoded) ? $decoded : [];
                }

                return $row;
            }, $rows);
        });
    }

    public function markRowCommitted(string $rowUuid, string $entityUuid): void
    {
        $this->writePdo->prepare(
            'UPDATE import_batch_rows
             SET status = :status, entity_uuid = :entity_uuid, updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute([
            'uuid' => $rowUuid,
            'status' => 'committed',
            'entity_uuid' => $entityUuid,
        ]);
    }

    public function markBatchCommitted(string $batchUuid): void
    {
        $this->writePdo->prepare(
            'UPDATE import_batches
             SET status = :status, committed_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute([
            'uuid' => $batchUuid,
            'status' => 'committed',
        ]);
    }

    public function markBatchRolledBack(string $batchUuid): void
    {
        $this->writePdo->prepare(
            'UPDATE import_batches
             SET status = :status, rolled_back_at = CURRENT_TIMESTAMP(6), updated_at = CURRENT_TIMESTAMP(6)
             WHERE uuid = :uuid AND deleted_at IS NULL'
        )->execute([
            'uuid' => $batchUuid,
            'status' => 'rolled_back',
        ]);
    }

    private function resolveBatchId(string $batchUuid): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM import_batches WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $batchUuid],
            false
        );
        if ($row === null) {
            throw new \RuntimeException('Import batch not found', 404);
        }

        return (int) $row['id'];
    }
}
