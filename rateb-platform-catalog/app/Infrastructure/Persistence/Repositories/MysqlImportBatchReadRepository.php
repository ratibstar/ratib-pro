<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ImportBatchReadRepositoryInterface;

final class MysqlImportBatchReadRepository extends BaseRepository implements ImportBatchReadRepositoryInterface
{
    protected function table(): string
    {
        return 'import_batches';
    }

    public function findByUuid(string $uuid): ?array
    {
        $row = $this->fetchOne(
            'SELECT ib.uuid, ib.source_file_path, ib.source_checksum, ib.status,
                    ib.total_rows, ib.valid_rows, ib.error_rows, ib.parser_config,
                    ib.committed_at, ib.rolled_back_at, ib.created_at, ib.updated_at,
                    ib.created_by, ib.updated_by, isrc.code AS source_code
             FROM import_batches ib
             INNER JOIN import_sources isrc ON isrc.id = ib.import_source_id AND isrc.deleted_at IS NULL
             WHERE ib.uuid = :uuid AND ib.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
        if ($row === null) {
            return null;
        }

        return $this->decodeJsonFields($row, ['parser_config']);
    }

    public function listRows(string $batchUuid, ?string $status, int $limit, int $offset): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $where = ['ib.uuid = :batch_uuid', 'ibr.deleted_at IS NULL', 'ib.deleted_at IS NULL'];
        $params = ['batch_uuid' => $batchUuid];
        if ($status !== null && $status !== '') {
            $where[] = 'ibr.status = :status';
            $params['status'] = $status;
        }

        $rows = $this->fetchAll(
            'SELECT ibr.uuid, ibr.row_number, ibr.raw_payload, ibr.mapped_payload,
                    ibr.validation_errors, ibr.status, ibr.entity_uuid, ibr.created_at, ibr.updated_at
             FROM import_batch_rows ibr
             INNER JOIN import_batches ib ON ib.id = ibr.import_batch_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ibr.row_number ASC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return array_map(fn (array $row): array => $this->decodeJsonFields(
            $row,
            ['raw_payload', 'mapped_payload', 'validation_errors']
        ), $rows);
    }

    public function countRows(string $batchUuid, ?string $status): int
    {
        $where = ['ib.uuid = :batch_uuid', 'ibr.deleted_at IS NULL', 'ib.deleted_at IS NULL'];
        $params = ['batch_uuid' => $batchUuid];
        if ($status !== null && $status !== '') {
            $where[] = 'ibr.status = :status';
            $params['status'] = $status;
        }

        $row = $this->fetchOne(
            'SELECT COUNT(*) AS cnt
             FROM import_batch_rows ibr
             INNER JOIN import_batches ib ON ib.id = ibr.import_batch_id
             WHERE ' . implode(' AND ', $where),
            $params
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function findSourceIdByCode(string $code): ?int
    {
        $row = $this->fetchOne(
            'SELECT id FROM import_sources WHERE code = :code AND deleted_at IS NULL LIMIT 1',
            ['code' => $code]
        );

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function decodeJsonFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null) {
                $row[$field] = $field === 'validation_errors' ? null : ($field === 'parser_config' ? [] : $row[$field] ?? null);
                continue;
            }
            $decoded = json_decode((string) $row[$field], true);
            $row[$field] = is_array($decoded) ? $decoded : ($field === 'raw_payload' ? [] : null);
        }

        return $row;
    }
}
