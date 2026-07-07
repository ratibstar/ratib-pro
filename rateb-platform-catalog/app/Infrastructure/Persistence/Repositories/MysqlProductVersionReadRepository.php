<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductVersionReadRepositoryInterface;

final class MysqlProductVersionReadRepository extends BaseRepository implements ProductVersionReadRepositoryInterface
{
    protected function table(): string
    {
        return 'product_versions';
    }

    public function listByProductUuid(string $productUuid, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        return $this->fetchAll(
            'SELECT pv.uuid, pv.version_number, pv.change_type, pv.change_summary, pv.entity_version, pv.created_at, pv.created_by
             FROM product_versions pv
             INNER JOIN products p ON p.id = pv.product_id
             WHERE p.uuid = :uuid AND p.deleted_at IS NULL
             ORDER BY pv.version_number DESC
             LIMIT ' . $limit,
            ['uuid' => $productUuid]
        );
    }

    public function findByProductAndVersion(string $productUuid, int $versionNumber): ?array
    {
        $row = $this->fetchOne(
            'SELECT pv.uuid, pv.version_number, pv.change_type, pv.change_summary, pv.snapshot_json,
                    pv.entity_version, pv.created_at, pv.created_by
             FROM product_versions pv
             INNER JOIN products p ON p.id = pv.product_id
             WHERE p.uuid = :uuid AND pv.version_number = :version AND p.deleted_at IS NULL
             LIMIT 1',
            ['uuid' => $productUuid, 'version' => $versionNumber]
        );
        if ($row === null) {
            return null;
        }
        $snapshot = json_decode((string) ($row['snapshot_json'] ?? '{}'), true);
        $row['snapshot'] = is_array($snapshot) ? $snapshot : [];
        unset($row['snapshot_json']);

        return $row;
    }
}
