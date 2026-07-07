<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestReadRepositoryInterface;

final class MysqlChangeRequestReadRepository extends BaseRepository implements ChangeRequestReadRepositoryInterface
{
    protected function table(): string
    {
        return 'change_requests';
    }

    public function findByUuid(string $uuid): ?array
    {
        $row = $this->fetchOne(
            'SELECT cr.id, cr.uuid, cr.request_type, cr.status, cr.proposed_changes, cr.current_version,
                    cr.submitted_by, cr.reviewer_id, cr.reviewed_by, cr.reviewed_at, cr.applied_at,
                    cr.review_note, cr.created_at, cr.updated_at, p.uuid AS product_uuid
             FROM change_requests cr
             INNER JOIN products p ON p.id = cr.product_id AND p.deleted_at IS NULL
             WHERE cr.uuid = :uuid AND cr.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $uuid]
        );
        if ($row === null) {
            return null;
        }
        $changes = json_decode((string) ($row['proposed_changes'] ?? '{}'), true);
        $row['proposed_changes'] = is_array($changes) ? $changes : [];
        unset($row['proposed_changes_json']);

        return $row;
    }

    public function list(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $where = ['cr.deleted_at IS NULL'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'cr.status = :status';
            $params['status'] = $status;
        }

        $rows = $this->fetchAll(
            'SELECT cr.uuid, cr.request_type, cr.status, cr.current_version, cr.submitted_by,
                    cr.reviewer_id, cr.reviewed_by, cr.reviewed_at, cr.applied_at, cr.created_at,
                    p.uuid AS product_uuid
             FROM change_requests cr
             INNER JOIN products p ON p.id = cr.product_id AND p.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cr.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return $rows;
    }

    public function listItems(int $changeRequestId): array
    {
        $rows = $this->fetchAll(
            'SELECT uuid, field_path, old_value, new_value, created_at
             FROM change_request_items
             WHERE change_request_id = :id
             ORDER BY id ASC',
            ['id' => $changeRequestId]
        );

        foreach ($rows as &$row) {
            $row['old_value'] = json_decode((string) ($row['old_value'] ?? 'null'), true);
            $row['new_value'] = json_decode((string) ($row['new_value'] ?? 'null'), true);
        }
        unset($row);

        return $rows;
    }
}
