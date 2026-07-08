<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ErpSyncReadRepositoryInterface;

final class MysqlErpSyncReadRepository extends BaseRepository implements ErpSyncReadRepositoryInterface
{
    protected function table(): string
    {
        return 'erp_product_sync';
    }

    public function syncStatusForCompany(int $companyId, ?string $sinceToken, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $where = ['eps.erp_company_id = :company_id', 'eps.deleted_at IS NULL', 'p.deleted_at IS NULL'];
        $params = ['company_id' => $companyId];

        if ($sinceToken !== null && $sinceToken !== '') {
            $where[] = 'eps.updated_at > :since_token';
            $params['since_token'] = $sinceToken;
        }

        $rows = $this->fetchAll(
            'SELECT eps.uuid, eps.erp_company_id, p.uuid AS product_uuid,
                    pv.uuid AS variant_uuid, eps.platform_source_version, eps.erp_inventory_id,
                    eps.last_imported_at, eps.last_sync_at, eps.imported_by, eps.sync_status,
                    eps.sync_note, eps.updated_at
             FROM erp_product_sync eps
             INNER JOIN products p ON p.id = eps.product_id
             LEFT JOIN product_variants pv ON pv.id = eps.product_variant_id AND pv.deleted_at IS NULL
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY eps.updated_at ASC, eps.id ASC
             LIMIT ' . $limit,
            $params
        );

        $since = null;
        if ($rows !== []) {
            $last = $rows[array_key_last($rows)];
            $since = isset($last['updated_at']) ? (string) $last['updated_at'] : null;
        }

        return [
            'items' => $rows,
            'since' => $since,
        ];
    }
}
